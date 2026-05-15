import React from 'react';
import { Activity, ArrowLeft, TrendingUp, Calendar } from 'lucide-react';
import { router } from '@inertiajs/react';

export default function ControlHeader({ filters, handleBack, stats }) {
    return (
        <header className="mb-6 flex justify-between items-end shrink-0">
            <div>
                <div className="flex items-center gap-4 mb-3">
                    {filters.uf && (
                        <button onClick={handleBack} className="p-2.5 bg-slate-900 hover:bg-slate-800 rounded-xl border border-slate-800 transition-all shadow-lg">
                            <ArrowLeft size={20} className="text-emerald-400" />
                        </button>
                    )}
                    <div className="flex items-center gap-3">
                        <div className="p-2 bg-emerald-500/10 rounded-xl border border-emerald-500/20">
                            <Activity className="text-emerald-400" size={24} />
                        </div>
                        <h1 className="text-3xl font-black tracking-tight text-white">
                            Monitor<span className="text-emerald-400">Saúde</span>
                        </h1>
                    </div>
                </div>
            </div>
            
            <div className="flex items-center gap-4">
                <div className="flex bg-slate-900/40 p-1 rounded-xl border border-white/5 backdrop-blur-xl shadow-2xl">
                    {['dengue', 'zika', 'chikungunya', 'gripe'].map((d) => (
                        <button
                            key={d}
                            onClick={() => {
                                if (filters.disease !== d) {
                                    router.get('/', { ...filters, disease: d }, { preserveState: true, preserveScroll: true });
                                }
                            }}
                            className={`px-4 py-2 rounded-lg text-[10px] font-black uppercase tracking-widest transition-all ${
                                (filters.disease || 'dengue') === d
                                    ? 'bg-emerald-500 text-slate-950 shadow-lg shadow-emerald-500/20'
                                    : 'text-slate-500 hover:text-slate-300'
                            }`}
                        >
                            {d}
                        </button>
                    ))}
                </div>

                <div className="bg-slate-900/40 px-6 py-3 rounded-2xl border border-white/5 backdrop-blur-xl flex items-center gap-8 shadow-2xl" role="region" aria-label="Estatísticas Globais">
                    <div className="flex items-center gap-3 border-r border-white/10 pr-8" aria-label={`Total de casos: ${stats?.total_cases || 0}`}>
                        <TrendingUp className="text-emerald-400" size={20} aria-hidden="true" />
                        <div>
                            <span className="text-[9px] text-slate-500 uppercase font-black tracking-[0.2em] block">Casos Totais</span>
                            <span className="text-xl font-mono font-bold text-white">{(stats?.total_cases || 0).toLocaleString()}</span>
                        </div>
                    </div>
                    <div className="flex items-center gap-3" aria-label={`Semana epidemiológica ${stats?.latest_week}`}>
                        <Calendar className="text-slate-500" size={18} aria-hidden="true" />
                        <div className="flex flex-col">
                            <span className="text-sm font-medium text-slate-300">{new Date().getFullYear()} • <span className="capitalize">{filters.disease || 'dengue'}</span> • Sem #{stats?.latest_week}</span>
                            {stats?.last_sync ? (
                                <span className="text-[10px] text-slate-500 font-medium italic">
                                    Sincronizado: {new Date(stats.last_sync).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })}
                                </span>
                            ) : (
                                <span className="text-[10px] text-slate-400 font-medium italic">Aguardando sincronização...</span>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </header>
    );
}
