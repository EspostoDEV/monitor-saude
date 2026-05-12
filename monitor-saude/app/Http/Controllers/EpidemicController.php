<?php

namespace App\Http\Controllers;

use App\Http\Resources\EpidemicRecordResource;
use App\Models\EpidemicRecord;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EpidemicController extends Controller
{
    public function index(Request $request): Response
    {
        $request->validate([
            'city_id' => 'nullable|exists:cities,id',
            'disease' => 'nullable|string',
            'year' => 'nullable|integer',
        ]);

        $query = EpidemicRecord::query()
            ->with('city')
            ->when($request->city_id, fn($q) => $q->where('city_id', $request->city_id))
            ->when($request->disease, fn($q) => $q->where('disease_type', $request->disease))
            ->when($request->year, fn($q) => $q->where('year', $request->year))
            ->orderBy('year', 'desc')
            ->orderBy('epi_week', 'desc');

        return Inertia::render('Dashboard', [
            'filters' => $request->only(['city_id', 'disease', 'year']),
            'records' => EpidemicRecordResource::collection($query->paginate(20)),
        ]);
    }
}