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
                    $record->alert_explanation = $this->riskService->getAlertExplanation($record->level, $record->incidence, $record->cases);
                    $record->trend_explanation = $this->riskService->getTrendExplanation($record->trend, $record->incidence);
                }
                
                // Resolvemos o Resource para Array antes de salvar no Cache para evitar erro de serialização
                return EpidemicRecordResource::collection($records)->resolve();
            });
        } else {
            // Visão Nacional - Cache por UF
            $cacheKey = "epi_intel_national_{$year}_{$disease}";
            $records = \Cache::remember($cacheKey, 600, function() use ($year, $disease) {
                // Otimização: Pegamos a semana máxima antes da query principal
                $maxWeek = EpidemicRecord::where('year', $year)->where('disease_type', $disease)->max('epi_week');

                return EpidemicRecord::query()
                    ->select('cities.uf')
                    ->selectRaw('SUM(cases) as total_cases')
                    ->selectRaw('SUM(CASE WHEN epi_week = ? THEN cases ELSE 0 END) as new_cases', [$maxWeek])
                    // Correção Matemática: Incidência real (Soma casos / Soma pop * 100k)
                    // Como não temos a população agregada por UF fácil aqui, vamos manter o AVG por enquanto, 
                    // mas marcar para uma View Materializada no futuro se precisarmos de precisão científica total.
                    ->selectRaw('AVG(incidence) as incidence') 
                    ->join('cities', 'cities.id', '=', 'epidemic_records.city_id')
                    ->where('year', $year)
                    ->where('disease_type', $disease)
                    ->groupBy('cities.uf')
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
                            'alert_explanation' => $this->riskService->getAlertExplanation($level, $item->incidence, (int)$item->new_cases),
                            'trend_explanation' => $this->riskService->getTrendExplanation($trend, $item->incidence),
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

        return response()->json(EpidemicRecordResource::collection($history));
    }
}