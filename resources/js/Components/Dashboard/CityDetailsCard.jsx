import React from 'react';
import { X, Users, TrendingUp, TrendingDown, Clock, ShieldAlert, Activity } from 'lucide-react';

// Lazy load Recharts for performance
const LineChart = React.lazy(() => import('recharts').then(m => ({ default: m.LineChart })));
const Line = React.lazy(() => import('recharts').then(m => ({ default: m.Line })));
const XAxis = React.lazy(() => import('recharts').then(m => ({ default: m.XAxis })));
const YAxis = React.lazy(() => import('recharts').then(m => ({ default: m.YAxis })));
const CartesianGrid = React.lazy(() => import('recharts').then(m => ({ default: m.CartesianGrid })));
const ChartTooltip = React.lazy(() => import('recharts').then(m => ({ default: m.Tooltip })));
const ResponsiveContainer = React.lazy(() => import('recharts').then(m => ({ default: m.ResponsiveContainer })));

export default function CityDetailsCard({ 
    selectedRecord, 
    setSelectedRecord, 
    history, 
    STATE_NAMES 
}) {
    if (!selectedRecord) return null;

    return (
        <div className="absolute inset-0 z-[1000] flex items-center justify-center p-6 bg-slate-950/40 backdrop-blur-sm">
            <div className="max-w-4xl w-full bg-slate-900/95 backdrop-blur-2xl p-8 rounded-3xl border border-white/10 shadow-2xl relative overflow-hidden flex flex-col max-h-full animate-in zoom-in-95 duration-300">
                <div className="absolute top-0 left-0 w-1.5 h-full bg-emerald-500 shadow-[0_0_20px_rgba(16,185,129,0.4)]" />
                <button onClick={() => setSelectedRecord(null)} className="absolute top-4 right-4 p-2 hover:bg-white/5 rounded-full transition-colors z-50">
                    <X size={20} className="text-slate-500" />
                </button>

                <div className="flex gap-8 overflow-y-auto pr-2 custom-scrollbar">
                    <div className="flex-none w-80">
                        <h3 className="text-4xl font-black tracking-tighter text-white mb-2">
                            {selectedRecord.city?.name || (STATE_NAMES[selectedRecord.uf] || selectedRecord.uf)}
                        </h3>
                        
                        <div className="flex flex-wrap gap-2 mb-6">
                            <span className={`px-3 py-1 rounded-lg text-[10px] font-black uppercase tracking-widest ${
                                selectedRecord.level === 4 ? 'bg-red-500 text-white' :
                                selectedRecord.level === 3 ? 'bg-orange-500 text-white' : 
                                selectedRecord.level === 2 ? 'bg-yellow-500 text-slate-900' :
                                'bg-emerald-500 text-white'
                            }`}>L{selectedRecord.level} - {selectedRecord.status}</span>
                            <span className="flex items-center gap-1 text-[10px] font-bold text-slate-300 bg-white/5 px-2 py-1 rounded-md border border-white/5">
                                <Users size={10} className="text-emerald-400" /> {selectedRecord.population?.toLocaleString() || '---'} hab.
                            </span>
                            <span className={`flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-widest px-2 py-1 rounded-md border ${
                                selectedRecord.trend === 'up' ? 'text-red-400 bg-red-400/10 border-red-400/20' :
                                selectedRecord.trend === 'down' ? 'text-emerald-400 bg-emerald-400/10 border-emerald-400/20' : 'text-slate-400 bg-slate-400/10 border-slate-400/20'
                            }`}>
                                {selectedRecord.trend === 'up' ? <TrendingUp size={10} /> : selectedRecord.trend === 'down' ? <TrendingDown size={10} /> : <Clock size={10} />}
                                {selectedRecord.trend === 'up' ? 'Alta' : selectedRecord.trend === 'down' ? 'Queda' : 'Estável'}
                            </span>
                        </div>

                        <div className="bg-slate-950/40 p-5 rounded-2xl border border-white/5 space-y-6">
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <span className="text-[9px] text-slate-500 uppercase font-black block mb-1">Novos Casos</span>
                                    <div className="text-xl font-mono font-bold text-white">{selectedRecord.cases || selectedRecord.new_cases}</div>
                                </div>
                                <div className="text-right">
                                    <span className="text-[9px] text-slate-500 uppercase font-black block mb-1">Incidência</span>
                                    <div className="text-xl font-mono font-bold text-emerald-400">{selectedRecord.incidence}</div>
                                </div>
                            </div>

                            <div className="space-y-4 pt-4 border-t border-white/5">
                                <div className="flex justify-between items-center">
                                    <span className="text-[10px] text-slate-500 uppercase font-black tracking-widest">Análise Técnica</span>
                                    <span className="text-[9px] text-slate-400">1 em cada {Math.round(100000 / selectedRecord.incidence).toLocaleString()} pessoas</span>
                                </div>
                                <div className="space-y-3">
                                    {selectedRecord.alert_explanation && (
                                        <div className="bg-white/5 p-3 rounded-xl border border-white/5 flex gap-3">
                                            <ShieldAlert size={14} className={selectedRecord.level >= 3 ? 'text-orange-400' : 'text-emerald-400'} />
                                            <p className="text-[11px] text-slate-300 leading-relaxed">{selectedRecord.alert_explanation}</p>
                                        </div>
                                    )}
                                    {selectedRecord.trend_explanation && (
                                        <div className="bg-white/5 p-3 rounded-xl border border-white/5 flex gap-3">
                                            <TrendingUp size={14} className={selectedRecord.trend === 'up' ? 'text-red-400' : 'text-emerald-400'} />
                                            <p className="text-[11px] text-slate-300 leading-relaxed">{selectedRecord.trend_explanation}</p>
                                        </div>
                                    )}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div className="flex-1 bg-slate-950/40 p-5 rounded-2xl border border-white/5 flex flex-col min-h-[300px]">
                        <h3 className="text-[10px] text-slate-500 uppercase font-black tracking-widest mb-4 flex items-center gap-2">
                            <Activity size={12} className="text-emerald-500" /> Evolução nas últimas 12 semanas
                        </h3>
                        <div className="flex-1 w-full min-h-[250px]">
                            {history.length > 0 ? (
                                <React.Suspense fallback={<div className="h-full flex items-center justify-center text-slate-700 text-xs italic">Preparando gráficos...</div>}>
                                    <ResponsiveContainer width="100%" height="100%">
                                        <LineChart data={history}>
                                            <CartesianGrid strokeDasharray="3 3" stroke="#1e293b" vertical={false} />
                                            <XAxis 
                                                dataKey="week" 
                                                axisLine={false} 
                                                tickLine={false} 
                                                tick={({ x, y, payload }) => {
                                                    const item = history.find(h => h.week === payload.value);
                                                    return (
                                                        <text x={x} y={y + 12} fill="#475569" fontSize={9} textAnchor="middle" fontWeight="bold">
                                                            {item?.month}
                                                        </text>
                                                    );
                                                }}
                                                interval={2}
                                            />
                                            <YAxis axisLine={false} tickLine={false} tick={{fill: '#475569', fontSize: 10}} />
                                            <ChartTooltip content={({ active, payload }) => (
                                                active && payload && payload.length > 0 && (
                                                    <div className="bg-slate-900 border border-white/10 p-3 rounded-xl shadow-2xl">
                                                        <p className="text-[10px] font-black text-slate-500 uppercase mb-1">
                                                            {payload[0].payload.month} • {payload[0].payload.week_range}
                                                        </p>
                                                        <p className="text-sm font-bold text-emerald-400">{payload[0].value} casos</p>
                                                        <p className="text-[9px] text-slate-400 italic">Semana Epidemiológica #{payload[0].payload.week}</p>
                                                    </div>
                                                )
                                            )} />
                                            <Line type="monotone" dataKey="cases" stroke="#10b981" strokeWidth={3} dot={{ r: 4, fill: '#10b981' }} animationDuration={1000} />
                                        </LineChart>
                                    </ResponsiveContainer>
                                </React.Suspense>
                            ) : <div className="h-full flex items-center justify-center text-slate-700 text-xs italic">Processando histórico...</div>}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
