<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SyncHealthDataJob;
use App\Models\City;
use App\Models\EpidemicRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SyncStatusController extends Controller
{
    public function index()
    {
        // Pega o status de cada doença
        $status = EpidemicRecord::select('disease_type')
            ->selectRaw('MAX(updated_at) as last_sync')
            ->selectRaw('COUNT(*) as total_records')
            ->groupBy('disease_type')
            ->get()
            ->map(function ($item) {
                $lastSync = $item->last_sync ? \Illuminate\Support\Carbon::parse($item->last_sync) : null;
                
                return [
                    'disease' => $item->disease_type,
                    'last_sync' => $lastSync ? $lastSync->diffForHumans() : 'Nunca',
                    'total_records' => number_format($item->total_records, 0, ',', '.'),
                    'is_fresh' => $lastSync ? $lastSync->gt(now()->subDays(7)) : false,
                ];
            });

        return Inertia::render('Admin/SyncStatus', [
            'status' => $status,
        ]);
    }

    public function sync(Request $request)
    {
        $request->validate([
            'disease' => 'required|string|in:dengue,chikungunya,zika',
        ]);

        $ufs = City::select('uf')->distinct()->pluck('uf');

        foreach ($ufs as $uf) {
            SyncHealthDataJob::dispatch($request->disease, null, $uf);
        }

        return back()->with('message', "Sincronização de {$request->disease} iniciada em background!");
    }
}
