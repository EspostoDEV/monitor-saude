<?php

namespace App\Http\Controllers;

use App\Http\Resources\EpidemicRecordResource;
use App\Models\EpidemicRecord;
use App\Repositories\Contracts\EpidemicRepositoryInterface;
use App\Services\RiskEngineService;
use App\Services\TrendAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EpidemicController extends Controller
{
    protected RiskEngineService $riskService;

    protected EpidemicRepositoryInterface $repository;

    public function __construct(
        TrendAnalysisService $trendService,
        RiskEngineService $riskService,
        EpidemicRepositoryInterface $repository
    ) {
        $this->trendService = $trendService;
        $this->riskService = $riskService;
        $this->repository = $repository;
    }

    public function index(Request $request): Response
    {
        $request->validate([
            'uf' => 'nullable|string|size:2',
            'disease' => 'nullable|string',
            'year' => 'nullable|integer',
        ]);

        $disease = $request->disease ?? 'dengue';
        $year = $request->year ?? EpidemicRecord::where('disease_type', $disease)->max('year') ?? 2024;
        $uf = $request->uf;

        // 1. Resolvemos a última semana e última sincronização via Repositório
        $latestWeek = $this->repository->getLatestWeek($year, $disease);
        $lastSync = $this->repository->getLastSyncAt($disease);

        if ($uf) {
            $records = \Cache::remember("epi_intel_{$uf}_{$year}_{$disease}_w{$latestWeek}", 600, function () use ($uf, $year, $disease, $latestWeek) {
                $data = $this->repository->getLatestRecordsByUf($uf, $year, $disease, $latestWeek);
                $ufStats = $this->repository->getUfGlobalStats($uf, $year, $disease, $latestWeek);

                foreach ($data as $record) {
                    $record->trend = $this->trendService->calculateTrend($record->city, $disease);
                    $record->status = $record->status_label;
                }

                return [
                    'records' => EpidemicRecordResource::collection($data)->resolve(),
                    'uf_total_cases' => $ufStats['uf_total_cases'],
                    'uf_new_cases' => $ufStats['uf_new_cases'],
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
            $records = \Cache::remember("epi_intel_national_{$year}_{$disease}_w{$latestWeek}", 600, function () use ($year, $disease, $latestWeek) {
                return $this->repository->getNationalStats($year, $disease, $latestWeek)
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
