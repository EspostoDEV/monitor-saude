<?php

namespace App\Http\Controllers;

use App\Http\Resources\EpidemicRecordResource;
use App\Models\EpidemicRecord;
use App\Services\RiskEngineService;
use App\Services\TrendAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EpidemicController extends Controller
{
    protected TrendAnalysisService $trendService;

    protected RiskEngineService $riskService;

    public function __construct(TrendAnalysisService $trendService, RiskEngineService $riskService)
    {
        $this->trendService = $trendService;
        $this->riskService = $riskService;
    }

    public function index(Request $request): Response
    {
        $request->validate([
            'uf' => 'nullable|string|size:2',
            'disease' => 'nullable|string',
            'year' => 'nullable|integer',
        ]);

        $year = $request->year ?? EpidemicRecord::max('year') ?? 2024;
        $disease = $request->disease ?? 'dengue';
        $uf = $request->uf;

        // 1. Resolvemos a última semana e última sincronização (Global para o contexto de ano/doença)
        $latestWeek = EpidemicRecord::where('year', $year)->where('disease_type', $disease)->max('epi_week');
        $lastSync = EpidemicRecord::where('disease_type', $disease)->max('updated_at');

        if ($uf) {
            // Otimização Arquitetural (Winston Plan): Totais da UF e registros em um único fluxo
            $records = \Cache::remember("epi_intel_{$uf}_{$year}_{$disease}", 600, function () use ($uf, $year, $disease, $latestWeek) {
                // Subquery para totais acumulados por cidade
                $totalsSubquery = EpidemicRecord::query()
                    ->select('city_id', \DB::raw('SUM(cases) as total_cases'))
                    ->where('year', $year)
                    ->where('disease_type', $disease)
                    ->groupBy('city_id');

                $data = EpidemicRecord::query()
                    ->selectRaw('DISTINCT ON (epidemic_records.city_id) epidemic_records.*')
                    ->selectRaw('ST_X(cities.location::geometry) as lng, ST_Y(cities.location::geometry) as lat')
                    ->selectRaw('COALESCE(totals.total_cases, 0) as total_cases')
                    // Injeção de Stats Globais da UF via Subqueries para evitar Queries N+1
                    ->selectRaw('(SELECT SUM(cases) FROM epidemic_records e2 JOIN cities c2 ON c2.id = e2.city_id WHERE c2.uf = ? AND e2.year = ? AND e2.disease_type = ?) as uf_total_cases', [$uf, $year, $disease])
                    ->selectRaw('(SELECT SUM(cases) FROM epidemic_records e3 JOIN cities c3 ON c3.id = e3.city_id WHERE c3.uf = ? AND e3.year = ? AND e3.disease_type = ? AND e3.epi_week = ?) as uf_new_cases', [$uf, $year, $disease, $latestWeek])
                    ->join('cities', 'cities.id', '=', 'epidemic_records.city_id')
                    ->leftJoinSub($totalsSubquery, 'totals', 'totals.city_id', '=', 'epidemic_records.city_id')
                    ->with('city')
                    ->where('cities.uf', $uf)
                    ->where('year', $year)
                    ->where('disease_type', $disease)
                    ->orderBy('epidemic_records.city_id')
                    ->orderBy('year', 'desc')
                    ->orderBy('epi_week', 'desc')
                    ->get();

                foreach ($data as $record) {
                    $record->trend = $this->trendService->calculateTrend($record->city, $disease);
                    $record->status = $record->status_label;
                }

                return [
                    'records' => EpidemicRecordResource::collection($data)->resolve(),
                    'uf_total_cases' => (int) ($data->first()->uf_total_cases ?? 0),
                    'uf_new_cases' => (int) ($data->first()->uf_new_cases ?? 0),
                ];
            });

            $stats = [
                'total_cases' => $records['uf_total_cases'],
                'new_cases' => $records['uf_new_cases'],
                'latest_week' => $latestWeek,
                'last_sync' => $lastSync,
            ];
            $records = $records['records'];
        } else {
            // Visão Nacional - Via Materialized View
            $records = \Cache::remember("epi_intel_national_{$year}_{$disease}", 600, function () use ($year, $disease, $latestWeek) {
                return \DB::table('mv_uf_epidemic_stats')
                    ->select('uf', 'total_cases', 'real_incidence as incidence')
                    ->selectRaw('total_cases as new_cases')
                    ->where('year', $year)
                    ->where('epi_week', $latestWeek)
                    ->where('disease_type', $disease)
                    ->get()
                    ->map(function ($item) use ($disease) {
                        $trend = $this->trendService->calculateTrendForUf($item->uf, $disease);
                        $level = $this->riskService->getAlertLevel($item->incidence, (int) $item->new_cases, $trend);

                        return [
                            'uf' => $item->uf,
                            'total_cases' => (int) $item->total_cases,
                            'new_cases' => (int) $item->new_cases,
                            'incidence' => round($item->incidence, 2),
                            'level' => $level,
                            'status' => $this->riskService->getAlertStatusLabel($level),
                            'is_state' => true,
                            'trend' => $trend,
                        ];
                    })->values()->all();
            });

            $stats = [
                'total_cases' => (int) EpidemicRecord::where('year', $year)->where('disease_type', $disease)->sum('cases'),
                'new_cases' => (int) EpidemicRecord::where('year', $year)->where('disease_type', $disease)->where('epi_week', $latestWeek)->sum('cases'),
                'latest_week' => $latestWeek,
                'last_sync' => $lastSync,
            ];
        }

        return Inertia::render('Dashboard', [
            'filters' => $request->only(['uf', 'disease', 'year']),
            'records' => $records,
            'stats' => $stats,
        ]);
    }

    public function history(Request $request, int $cityId): JsonResponse
    {
        $disease = $request->query('disease', 'dengue');

        $history = EpidemicRecord::query()
            ->where('city_id', $cityId)
            ->where('disease_type', $disease)
            ->orderBy('year', 'desc')
            ->orderBy('epi_week', 'desc')
            ->limit(12)
            ->get()
            ->reverse()
            ->values();

        // Lazy Loading: Calcula as explicações apenas para o registro mais recente do histórico
        $latest = $history->last(); // Após reverse e values, o último é o mais recente
        if ($latest) {
            $latest->trend = $this->trendService->calculateTrend($latest->city, $disease);
            $latest->alert_explanation = $this->riskService->getAlertExplanation($latest->level, $latest->incidence, $latest->cases);
            $latest->trend_explanation = $this->riskService->getTrendExplanation($latest->trend, $latest->incidence);
        }

        return response()->json(EpidemicRecordResource::collection($history));
    }
}
