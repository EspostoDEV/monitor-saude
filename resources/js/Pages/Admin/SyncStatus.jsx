import React, { useState, useEffect, useRef } from 'react';
import { Head, useForm, router } from '@inertiajs/react';
import { 
    Activity, 
    Database, 
    RefreshCw, 
    CheckCircle2, 
    AlertCircle, 
    Clock, 
    Terminal, 
    Loader2,
    ChevronRight,
    Search
} from 'lucide-react';

export default function SyncStatus({ status, activeSessionId }) {
    const [isSyncing, setIsSyncing] = useState(!!activeSessionId);
    const [logs, setLogs] = useState([]);
    const [progress, setProgress] = useState(0);
    const [sessionId, setSessionId] = useState(activeSessionId);
    const lastLogIdRef = useRef(0);
    const logContainerRef = useRef(null);
    const pollingRef = useRef(null);

    const { post, processing } = useForm();

    // Scroll automático para o final do console
    useEffect(() => {
        if (logContainerRef.current) {
            logContainerRef.current.scrollTop = logContainerRef.current.scrollHeight;
        }
    }, [logs]);

    // Polling inteligente (Recursivo, evita atropelamento)
    const fetchLogs = async () => {
        if (!sessionId) return;

        try {
            // Usamos o Ref para garantir o valor mais atualizado do ID
            const response = await fetch(`/admin/sync-logs?sessionId=${sessionId}&lastId=${lastLogIdRef.current}`);
            const data = await response.json();

            if (data.logs && data.logs.length > 0) {
                setLogs(prev => [...prev, ...data.logs]);
                // Atualizamos o Ref imediatamente
                lastLogIdRef.current = data.logs[data.logs.length - 1].id;
            }

            setProgress(data.progress);

            if (data.finished) {
                setIsSyncing(false);
            } else {
                pollingRef.current = setTimeout(fetchLogs, 2000);
            }
        } catch (error) {
            console.error("Erro no polling:", error);
            pollingRef.current = setTimeout(fetchLogs, 5000);
        }
    };

    useEffect(() => {
        if (isSyncing && sessionId) {
            fetchLogs();
        }
        return () => {
            if (pollingRef.current) clearTimeout(pollingRef.current);
        };
    }, [isSyncing, sessionId]);

    const handleSync = (disease) => {
        setLogs([]);
        setProgress(0);
        lastLogIdRef.current = 0;
        
        console.log("Iniciando post para:", disease);
        router.post('/admin/sync-trigger', { disease }, {
            onSuccess: (page) => {
                console.log("Sucesso! Props recebidos:", page.props);
                const newSessionId = page.props.flash?.sessionId;
                if (newSessionId) {
                    console.log("Session ID capturado:", newSessionId);
                    setSessionId(newSessionId);
                    setIsSyncing(true);
                } else {
                    console.warn("Session ID não encontrado nos flash props.");
                }
            },
            onError: (errors) => {
                console.error("Erro na requisição Sync:", errors);
            },
            onFinish: () => {
                console.log("Requisição finalizada.");
            }
        });
    };

    return (
        <div className="min-h-screen bg-[#0f172a] text-slate-300">
            <Head title="Sincronização de Dados" />

            <div className="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
                <div className="mb-8">
                    <h2 className="text-2xl font-bold text-white flex items-center gap-3">
                        <RefreshCw className={`w-8 h-8 text-blue-500 ${isSyncing ? 'animate-spin' : ''}`} />
                        Sincronização Epidemiológica
                    </h2>
                    <p className="text-gray-400 mt-1">Gerenciamento de carga de dados nacionais do InfoDengue.</p>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    {status.map((item) => (
                        <div key={item.disease} className="bg-gray-800/40 rounded-xl p-6 border border-gray-700/50 hover:border-blue-500/30 transition-all group">
                            <div className="flex justify-between items-start mb-4">
                                <div className="p-3 bg-blue-500/10 rounded-xl group-hover:bg-blue-500/20 transition-colors">
                                    <Database className="w-6 h-6 text-blue-400" />
                                </div>
                                <button 
                                    onClick={() => handleSync(item.disease.toLowerCase())}
                                    disabled={isSyncing || processing}
                                    className="px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-medium transition-all shadow-lg shadow-blue-900/20"
                                >
                                    {isSyncing && sessionId ? 'Em curso...' : 'Sincronizar'}
                                </button>
                            </div>
                            <h3 className="text-white font-semibold text-lg mb-4">{item.disease}</h3>
                            <div className="space-y-3 text-sm">
                                <div className="flex justify-between items-center">
                                    <span className="text-gray-500">Última Sincronização</span>
                                    <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${item.is_fresh ? 'bg-green-500/10 text-green-400' : 'bg-amber-500/10 text-amber-400'}`}>
                                        {item.last_sync}
                                    </span>
                                </div>
                                <div className="flex justify-between items-center border-t border-gray-700/30 pt-3">
                                    <span className="text-gray-500">Registros</span>
                                    <span className="text-white font-mono font-bold">{item.total_records}</span>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>

                <div className="bg-gray-800/30 rounded-2xl overflow-hidden border border-gray-700/50 shadow-2xl">
                    <div className="p-6 border-b border-gray-700/50 bg-gray-800/50 flex justify-between items-center">
                        <div className="flex items-center gap-3">
                            <Terminal className="w-5 h-5 text-blue-400" />
                            <h3 className="font-medium text-white">Console de Operação</h3>
                        </div>
                        <div className="flex items-center gap-6">
                            <div className="text-right">
                                <div className="text-xs text-gray-500 uppercase tracking-wider mb-1">Progresso</div>
                                <div className="text-lg font-mono text-blue-400 font-bold">{progress}%</div>
                            </div>
                            {isSyncing && <Loader2 className="w-6 h-6 text-blue-500 animate-spin" />}
                        </div>
                    </div>

                    <div className="p-0">
                        <div className="h-1.5 bg-gray-900 overflow-hidden">
                            <div 
                                className="h-full bg-blue-500 transition-all duration-500 ease-out shadow-[0_0_15px_rgba(59,130,246,0.5)]"
                                style={{ width: `${progress}%` }}
                            />
                        </div>
                        
                        <div 
                            ref={logContainerRef}
                            className="bg-black/90 p-6 font-mono text-sm h-[400px] overflow-y-auto border-t border-gray-800 scrollbar-thin scrollbar-thumb-gray-700 scrollbar-track-transparent selection:bg-blue-500/30"
                        >
                            {logs.length === 0 ? (
                                <div className="flex flex-col items-center justify-center h-full text-gray-600 gap-4 opacity-50">
                                    <Clock className="w-12 h-12 stroke-[1]" />
                                    <p className="italic">Aguardando comando para iniciar processamento...</p>
                                </div>
                            ) : (
                                logs.map((log) => (
                                    <div key={log.id} className="mb-2 flex gap-4 group hover:bg-white/5 p-1 rounded transition-colors">
                                        <span className="text-gray-600 shrink-0 select-none">
                                            [{new Date(log.created_at).toLocaleTimeString('pt-BR')}]
                                        </span>
                                        <span className={
                                            log.level === 'error' ? 'text-red-400' :
                                            log.level === 'success' ? 'text-green-400' :
                                            'text-blue-300'
                                        }>
                                            {log.message}
                                        </span>
                                    </div>
                                ))
                            )}
                        </div>
                    </div>
                </div>

                <footer className="mt-12 py-8 border-t border-gray-800 flex flex-col md:flex-row justify-between items-center gap-4 text-xs text-gray-500 font-medium">
                    <div className="flex items-center gap-6">
                        <div className="flex items-center gap-2">
                            <div className="w-2 h-2 rounded-full bg-green-500 shadow-[0_0_8px_rgba(34,197,94,0.5)]"></div>
                            <span>API InfoDengue Online</span>
                        </div>
                        <span>PostGIS v3.5</span>
                    </div>
                    <div>MonitorSaúde Alpha v2.0 • 2026</div>
                </footer>
            </div>
        </div>
    );
}
