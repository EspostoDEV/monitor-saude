import React from 'react';
import { Database } from 'lucide-react';

export default function SyncStatsGrid({ 
    status, 
    handleSync, 
    isSyncing, 
    processing, 
    sessionId 
}) {
    return (
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
    );
}
