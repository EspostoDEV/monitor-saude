<?php

namespace App\Http\Controllers;

use App\Http\Resources\EpidemicRecordResource;
use App\Models\EpidemicRecord;
use App\Services\TrendAnalysisService;
use App\Services\RiskEngineService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\JsonResponse;

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

        if ($request->filled('uf')) {
            // Otimização Arquitetural: Totais calculados via Join em vez de Subquery N+1
            $totalsSubquery = EpidemicRecord::query()
                ->select('city_id', \DB::raw('SUM(cases) as total_cases'))
                ->where('year', $year)
                ->where('disease_type', $disease)
                ->groupBy('city_id');

            $records = EpidemicRecord::query()
                ->selectRaw('DISTINCT ON (epidemic_records.city_id) epidemic_records.*')
                ->selectRaw('ST_X(cities.location::geometry) as lng, ST_Y(cities.location::geometry) as lat')
                ->selectRaw('COALESCE(totals.total_cases, 0) as total_cases')
                ->join('cities', 'cities.id', '=', 'epidemic_records.city_id')
                ->leftJoinSub($totalsSubquery, 'totals', 'totals.city_id', '=', 'epidemic_records.city_id')
                ->with('city')
                ->where('cities.uf', $request->uf)
                ->where('year', $year)
                ->where('disease_type', $disease)
                ->orderBy('epidemic_records.city_id')
                ->orderBy('year', 'desc')
                ->orderBy('epi_week', 'desc')
                ->get();

            // Otimização: Cache dos cálculos de inteligência epidemiológica (TTL 10 min)
            $cacheKey = "epi_intel_{$request->uf}_{$year}_{$disease}";
            $records = \Cache::remember($cacheKey, 600, function() use ($records, $disease) {
                foreach ($records as $record) {
                    $record->trend = $this->trendService->calculateTrend($record->city, $disease);
                    
                    // Recalcula o nível com o novo motor
                    $record->level = $this->riskService->getAlertLevel($record->incidence, $record->cases, $record->trend);
                    $record->status = match($record->level) {
                        4 => 'Crítico',
                        3 => 'Alerta',
                        2 => 'Amarelo',
                        default => 'Estável'
                    };
                }
                
                // Resolvemos o Resource para Array antes de salvar no Cache para evitar erro de serialização
                return EpidemicRecordResource::collection($records)->resolve();
            });
        } else {
            // Visão Nacional - Via Materialized View (Performance e Precisão)
            $cacheKey = "epi_intel_national_{$year}_{$disease}";
            $records = \Cache::remember($cacheKey, 600, function() use ($year, $disease) {
                $maxWeek = \DB::table('mv_uf_epidemic_stats')
                    ->where('year', $year)
                    ->where('disease_type', $disease)
                    ->max('epi_week');

                return \DB::table('mv_uf_epidemic_stats')
                    ->select('uf', 'total_cases', 'real_incidence as incidence')
                    ->selectRaw('total_cases as new_cases') // Para a visão nacional simplificada
                    ->where('year', $year)
                    ->where('epi_week', $maxWeek)
                    ->where('disease_type', $disease)
                    ->get()
                    ->map(function($item) use ($disease) {
                        $trend = $this->trendService->calculateTrendForUf($item->uf, $disease);
                        $level = $this->riskService->getAlertLevel($item->incidence, (int)$item->new_cases, $trend);

                        $status = match($level) {
                            4 => 'Crítico',
                            3 => 'Alerta',
                            2 => 'Amarelo',
                            default => 'Estável'
                        };

                        return [
                            'uf' => $item->uf,
                            'total_cases' => (int) $item->total_cases,
                            'new_cases' => (int) $item->new_cases,
                            'incidence' => round($item->incidence, 2),
                            'level' => $level,
                            'status' => $status,
                            'is_state' => true,
                            'trend' => $trend,
                        ];
                    })->values()->all();
            });
        }

        $latestWeek = EpidemicRecord::where('year', $year)->where('disease_type', $disease)->max('epi_week');

        // Estatísticas do cabeçalho
        $stats = [
            'total_cases' => (int) EpidemicRecord::where('year', $year)->where('disease_type', $disease)->sum('cases'),
            'new_cases' => (int) EpidemicRecord::where('year', $year)->where('disease_type', $disease)->where('epi_week', $latestWeek)->sum('cases'),
            'latest_week' => $latestWeek,
            'last_sync' => EpidemicRecord::max('updated_at'),
        ];

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