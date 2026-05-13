<?php

namespace App\Http\Controllers;

use App\Http\Resources\EpidemicRecordResource;
use App\Models\EpidemicRecord;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\JsonResponse;

class EpidemicController extends Controller
{
    public function index(Request $request): Response
    {
        $request->validate([
            'uf' => 'nullable|string|size:2',
            'disease' => 'nullable|string',
            'year' => 'nullable|integer',
        ]);

        $year = $request->year ?? EpidemicRecord::max('year') ?? 2024;
        $disease = $request->disease ?? 'dengue';

        if ($request->uf) {
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
        } else {
            // Visão Nacional: Agrupado por Estado com casos totais e novos
            $latestWeek = EpidemicRecord::where('year', $year)->where('disease_type', $disease)->max('epi_week');

            $records = EpidemicRecord::query()
                ->selectRaw('cities.uf')
                ->selectRaw('SUM(cases) as total_cases')
                ->selectRaw("SUM(CASE WHEN epi_week = ? THEN cases ELSE 0 END) as new_cases", [$latestWeek])
                ->selectRaw('AVG(incidence) as incidence')
                ->join('cities', 'cities.id', '=', 'epidemic_records.city_id')
                ->where('year', $year)
                ->where('disease_type', $disease)
                ->groupBy('cities.uf')
                ->get()
                ->map(function($item) {
                    $level = 1;
                    if ($item->incidence > 600) $level = 4;
                    elseif ($item->incidence > 300) $level = 3;
                    elseif ($item->incidence > 100) $level = 2;

                    return [
                        'uf' => $item->uf,
                        'total_cases' => (int) $item->total_cases,
                        'new_cases' => (int) $item->new_cases,
                        'incidence' => round($item->incidence, 2),
                        'level' => $level,
                        'is_state' => true,
                    ];
                });
        }

        $latestWeek = EpidemicRecord::where('year', $year)->where('disease_type', $disease)->max('epi_week');

        // Estatísticas do cabeçalho
        $stats = [
            'total_cases' => (int) EpidemicRecord::where('year', $year)->where('disease_type', $disease)->sum('cases'),
            'new_cases' => (int) EpidemicRecord::where('year', $year)->where('disease_type', $disease)->where('epi_week', $latestWeek)->sum('cases'),
            'latest_week' => $latestWeek,
        ];

        return Inertia::render('Dashboard', [
            'filters' => $request->only(['uf', 'disease', 'year']),
            'records' => $request->uf ? EpidemicRecordResource::collection($records) : $records,
            'stats' => $stats,
        ]);
    }

    public function history(int $cityId): JsonResponse
    {
        $history = EpidemicRecord::query()
            ->where('city_id', $cityId)
            ->orderBy('year', 'desc')
            ->orderBy('epi_week', 'desc')
            ->limit(12)
            ->get()
            ->reverse()
            ->values();

        return response()->json(EpidemicRecordResource::collection($history));
    }
}