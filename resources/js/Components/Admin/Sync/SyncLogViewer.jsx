import React from 'react';
import { Terminal, Loader2, Clock } from 'lucide-react';

export default function SyncLogViewer({ 
    logs, 
    progress, 
    isSyncing, 
    logContainerRef 
}) {
    return (
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
    );
}
