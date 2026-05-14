import React from 'react';
import { Search, TrendingUp, TrendingDown } from 'lucide-react';

export default function StatsSidebar({ 
    isStateView, 
    filters, 
    searchTerm, 
    setSearchTerm, 
    sortedDataList, 
    handleStateClick, 
    setSelectedRecord,
    STATE_NAMES 
}) {
    return (
        <div className="bg-slate-900/50 rounded-[2rem] border border-white/5 p-8 shadow-3xl flex flex-col h-full backdrop-blur-sm overflow-hidden">
            <h2 className="text-xl font-black tracking-tight flex items-center gap-3 mb-6 shrink-0">
                <div className="w-1.5 h-6 bg-emerald-400 rounded-full"></div>
                {isStateView ? 'Ranking Nacional' : `Municípios (${filters.uf})`}
            </h2>

            <div className="relative mb-6 shrink-0">
                <Search className="absolute left-4 top-1/2 -translate-y-1/2 text-slate-500" size={16} />
                <input 
                    type="text" 
                    placeholder={isStateView ? "Buscar estado..." : "Buscar município..."}
                    className="w-full bg-slate-950/50 border border-white/5 rounded-2xl py-3 pl-12 pr-4 text-sm text-slate-200 placeholder:text-slate-600 focus:outline-none focus:border-emerald-500/50 transition-all"
                    value={searchTerm}
                    onChange={(e) => setSearchTerm(e.target.value)}
                />
            </div>
            
            <div className="space-y-3 overflow-y-auto flex-1 pr-3 custom-scrollbar">
                {sortedDataList.map((item) => (
                    <div 
                        key={isStateView ? item.uf : item.id} 
                        onClick={() => isStateView ? handleStateClick(item.uf) : setSelectedRecord(item)}
                        className="p-4 rounded-2xl border border-white/5 bg-slate-800/30 hover:bg-slate-800/50 transition-all cursor-pointer flex justify-between items-center group"
                    >
                        <div className="min-w-0">
                            <h3 className="font-bold text-slate-200 truncate group-hover:text-emerald-400 transition-colors">
                                {isStateView ? (STATE_NAMES[item.uf] || item.uf) : (item.city?.name || '---')}
                            </h3>
                            <div className="flex items-center gap-3 mt-1">
                                <div className="flex flex-col">
                                    <span className="text-[8px] text-slate-500 uppercase font-black">Novos</span>
                                    <span className="text-[10px] text-orange-400 font-bold">{(item.new_cases || item.cases || 0).toLocaleString()}</span>
                                </div>
                                <div className="w-px h-3 bg-white/10" />
                                <div className="flex items-center gap-1">
                                    {item.trend === 'up' ? <TrendingUp size={12} className="text-red-500" /> : 
                                     item.trend === 'down' ? <TrendingDown size={12} className="text-emerald-500" /> : 
                                     <div className="w-2 h-0.5 bg-slate-600 rounded-full" />}
                                </div>
                            </div>
                        </div>
                        <div className={`shrink-0 w-8 h-8 rounded-lg flex items-center justify-center text-[10px] font-bold ${
                            item.level === 4 ? 'bg-red-500/10 text-red-500 border border-red-500/20' :
                            item.level === 3 ? 'bg-orange-500/10 text-orange-500 border border-orange-500/20' :
                            item.level === 2 ? 'bg-yellow-500/10 text-yellow-500 border border-yellow-500/20' :
                            'bg-emerald-500/10 text-emerald-500 border border-emerald-500/20'
                        }`}>L{item.level}</div>
                    </div>
                ))}
            </div>
        </div>
    );
}
