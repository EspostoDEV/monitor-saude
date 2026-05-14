<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SyncHealthDataJob;
use App\Models\City;
use App\Models\EpidemicRecord;
use App\Models\SyncLog;
use App\Models\SyncSession;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

class SyncStatusController extends Controller
{
    public function index()
    {
        // Tenta encontrar uma sessão ativa recente
        $activeSession = SyncSession::where('status', 'running')
            ->where('updated_at', '>', now()->subMinutes(60))
            ->orderBy('id', 'desc')
            ->first();

        $currentSessionId = $activeSession ? $activeSession->session_id : null;

        // Status das doenças baseado na última sessão de sucesso (Issue 1, 7)
        Carbon::setLocale('pt_BR');
        $status = collect(['dengue', 'chikungunya', 'zika'])->map(function ($disease) {
            $lastSession = SyncSession::where('disease', $disease)
                ->where('status', 'finished')
                ->orderBy('completed_at', 'desc')
                ->first();

            $totalRecords = EpidemicRecord::where('disease_type', $disease)->count();
            $lastSync = $lastSession ? Carbon::parse($lastSession->completed_at) : null;

            return [
                'disease' => ucfirst($disease),
                'last_sync' => $lastSync ? $lastSync->diffForHumans() : 'Nunca',
                'total_records' => number_format($totalRecords, 0, ',', '.'),
                'is_fresh' => $lastSync ? $lastSync->gt(now()->subDays(7)) : false,
            ];
        });

        return Inertia::render('Admin/SyncStatus', [
            'status' => $status,
            'activeSessionId' => $currentSessionId,
        ]);
    }

    public function sync(Request $request)
    {
        \Log::info('SYNC_DEBUG: Recebendo pedido de sincronização.', $request->all());

        try {
            $request->validate([
                'disease' => 'required|string|in:dengue,chikungunya,zika',
            ]);
        } catch (\Exception $e) {
            \Log::error('SYNC_DEBUG: Erro de validação: '.$e->getMessage());
            throw $e;
        }

        $sessionId = uniqid('sync_');
        $totalCities = City::count();

        \Log::info("SYNC_DEBUG: Criando sessão {$sessionId} para {$totalCities} cidades.");

        $session = SyncSession::create([
            'session_id' => $sessionId,
            'disease' => $request->disease,
            'total_cities' => $totalCities,
            'status' => 'running',
        ]);

        SyncLog::create([
            'session_id' => $sessionId,
            'disease' => $request->disease,
            'level' => 'info',
            'message' => "Sincronização iniciada para {$request->disease}. Processando {$totalCities} municípios.",
        ]);

        $ufs = City::select('uf')->distinct()->pluck('uf');
        foreach ($ufs as $uf) {
            SyncHealthDataJob::dispatch($request->disease, null, $uf, $sessionId);
        }

        \Log::info('SYNC_DEBUG: Jobs disparados. Retornando para a página.');

        return back()->with([
            'message' => 'Sincronização iniciada!',
            'sessionId' => $sessionId,
        ]);
    }

    public function logs(Request $request)
    {
        $sessionId = $request->query('sessionId');
        $lastId = $request->query('lastId', 0);

        if (! $sessionId) {
            return response()->json(['logs' => [], 'progress' => 0, 'finished' => true]);
        }

        $session = SyncSession::where('session_id', $sessionId)->first();
        if (! $session) {
            return response()->json(['logs' => [], 'progress' => 0, 'finished' => true]);
        }

        $logs = SyncLog::where('session_id', $sessionId)
            ->where('id', '>', $lastId)
            ->orderBy('id', 'asc')
            ->get();

        return response()->json([
            'logs' => $logs,
            'progress' => $session->progress,
            'finished' => $session->status === 'finished' || $session->status === 'failed',
        ]);
    }
}
