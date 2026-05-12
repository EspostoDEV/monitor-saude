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
            // Drill-down: Apenas o registro MAIS RECENTE de cada cidade do estado
            $records = EpidemicRecord::query()
                ->select('epidemic_records.*')
                ->selectRaw('ST_X(cities.location::geometry) as lng, ST_Y(cities.location::geometry) as lat')
                ->join('cities', 'cities.id', '=', 'epidemic_records.city_id')
                ->with('city')
                ->where('cities.uf', $request->uf)
                ->where('year', $year)
                ->where('disease_type', $disease)
                // Truque do Postgres para pegar apenas 1 linha por ID baseado na ordem
                ->distinct('city_id')
                ->orderBy('city_id')
                ->orderBy('year', 'desc')
                ->orderBy('epi_week', 'desc')
                ->get();
        } else {
            // Visão Nacional: Agrupado por Estado
            $records = EpidemicRecord::query()
                ->selectRaw('cities.uf, SUM(cases) as cases, AVG(incidence) as incidence, MAX(level) as level')
                ->join('cities', 'cities.id', '=', 'epidemic_records.city_id')
                ->where('year', $year)
                ->where('disease_type', $disease)
                ->groupBy('cities.uf')
                ->get()
                ->map(function($item) {
                    return [
                        'uf' => $item->uf,
                        'cases' => (int) $item->cases,
                        'incidence' => round($item->incidence, 2),
                        'level' => (int) $item->level,
                        'is_state' => true,
                    ];
                });
        }

        return Inertia::render('Dashboard', [
            'filters' => $request->only(['uf', 'disease', 'year']),
            'records' => $request->uf ? EpidemicRecordResource::collection($records) : $records,
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