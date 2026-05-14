import React, { useState, useEffect, useRef } from 'react';
import { Head, router } from '@inertiajs/react';
import { RefreshCw } from 'lucide-react';

// Admin Components
import AdminFooter from '@/Components/Admin/AdminFooter';
import SyncStatsGrid from '@/Components/Admin/Sync/SyncStatsGrid';
import SyncLogViewer from '@/Components/Admin/Sync/SyncLogViewer';

export default function SyncStatus({ status, activeSessionId }) {
    const [isSyncing, setIsSyncing] = useState(!!activeSessionId);
    const [logs, setLogs] = useState([]);
    const [progress, setProgress] = useState(0);
    const [sessionId, setSessionId] = useState(activeSessionId);
    const lastLogIdRef = useRef(0);
    const logContainerRef = useRef(null);
    const pollingRef = useRef(null);

    // Auto-scroll logic
    useEffect(() => {
        if (logContainerRef.current) {
            logContainerRef.current.scrollTop = logContainerRef.current.scrollHeight;
        }
    }, [logs]);

    // Intelligent Polling
    const fetchLogs = async () => {
        if (!sessionId) return;

        try {
            const response = await fetch(`/admin/sync-logs?sessionId=${sessionId}&lastId=${lastLogIdRef.current}`);
            const data = await response.json();

            if (data.logs && data.logs.length > 0) {
                setLogs(prev => [...prev, ...data.logs]);
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
        
        router.post('/admin/sync-trigger', { disease }, {
            onSuccess: (page) => {
                const newSessionId = page.props.flash?.sessionId;
                if (newSessionId) {
                    setSessionId(newSessionId);
                    setIsSyncing(true);
                }
            },
            onError: (errors) => {
                console.error("Erro na requisição Sync:", errors);
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

                <SyncStatsGrid 
                    status={status}
                    handleSync={handleSync}
                    isSyncing={isSyncing}
                    processing={false} // Inertia router handles processing via disabled state
                    sessionId={sessionId}
                />

                <SyncLogViewer 
                    logs={logs}
                    progress={progress}
                    isSyncing={isSyncing}
                    logContainerRef={logContainerRef}
                />

                <AdminFooter />
            </div>
        </div>
    );
}
