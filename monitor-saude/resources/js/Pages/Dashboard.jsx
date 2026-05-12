import React, { useMemo, useEffect, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { MapContainer, TileLayer, CircleMarker, Popup, useMap, GeoJSON } from 'react-leaflet';
import { Activity, Map as MapIcon, ShieldAlert, Info, ArrowLeft, TrendingUp, TrendingDown, Users, Calendar, X, Search, Clock } from 'lucide-react';
import { LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip as ChartTooltip, ResponsiveContainer } from 'recharts';
import axios from 'axios';
import 'leaflet/dist/leaflet.css';

// Importando o GeoJSON dos estados brasileiros
import brazilStates from '../data/brazil-states.json';

const STATE_COORDS = {
    'AC': [-9.0238, -70.8120], 'AL': [-9.5713, -36.7820], 'AP': [0.0356, -51.0705],
    'AM': [-3.4168, -65.8561], 'BA': [-12.9704, -38.5124], 'CE': [-3.7172, -38.5433],
    'DF': [-15.7801, -47.9292], 'ES': [-19.1834, -40.3089], 'GO': [-16.6869, -49.2648],
    'MA': [-2.5307, -44.3068], 'MT': [-12.6819, -56.9211], 'MS': [-20.4428, -54.6464],
    'MG': [-18.5122, -44.5550], 'PA': [-1.4558, -48.4902], 'PB': [-7.1153, -34.8610],
    'PR': [-25.4284, -49.2733], 'PE': [-8.0539, -34.8811], 'PI': [-5.0920, -42.8034],
    'RJ': [-22.9068, -43.1729], 'RN': [-5.7945, -35.2110], 'RS': [-30.0346, -51.2177],
    'RO': [-8.7612, -63.9039], 'RR': [2.8235, -60.6758], 'SC': [-27.5954, -48.5480],
    'SP': [-23.5505, -46.6333], 'SE': [-10.9111, -37.0717], 'TO': [-10.1753, -48.3317]
};

function ChangeView({ center, zoom }) {
    const map = useMap();
    useEffect(() => {
        map.setView(center, zoom);
    }, [center, zoom]);
    return null;
}

export default function Dashboard({ records, filters, stats }) {
    const isStateView = !filters.uf;
    const [hoveredUf, setHoveredUf] = useState(null);
    const [selectedRecord, setSelectedRecord] = useState(null);
    const [history, setHistory] = useState([]);
    const [loadingHistory, setLoadingHistory] = useState(false);
    
    const mapState = useMemo(() => {
        if (filters.uf && STATE_COORDS[filters.uf]) {
            return { center: STATE_COORDS[filters.uf], zoom: 7 };
        }
        return { center: [-15.7801, -47.9292], zoom: 4 };
    }, [filters.uf]);

    useEffect(() => {
        if (selectedRecord) {
            setLoadingHistory(true);
            axios.get(`/api/history/${selectedRecord.city_id}`)
                .then(res => {
                    setHistory(res.data);
                    setLoadingHistory(false);
                })
                .catch(() => setLoadingHistory(false));
        } else {
            setHistory([]);
        }
    }, [selectedRecord]);

    const getLevelColor = (level, opacity = 0.6) => {
        switch (level) {
            case 4: return `rgba(239, 68, 68, ${opacity})`; // Red
            case 3: return `rgba(249, 115, 22, ${opacity})`; // Orange
            case 2: return `rgba(234, 179, 8, ${opacity})`; // Yellow
            case 1: return `rgba(34, 197, 94, ${opacity})`; // Green
            default: return `rgba(71, 85, 105, ${opacity})`; // Slate
        }
    };

    const handleStateClick = (uf) => {
        setSelectedRecord(null);
        router.get('/', { ...filters, uf }, { preserveState: true });
    };

    const handleBack = () => {
        setSelectedRecord(null);
        router.get('/', { ...filters, uf: null }, { preserveState: true });
    };

    const dataList = isStateView ? records : records.data;
    
    const stateDataMap = useMemo(() => {
        if (!isStateView) return {};
        return records.reduce((acc, curr) => {
            acc[curr.uf] = curr;
            return acc;
        }, {});
    }, [records, isStateView]);

    const geoJsonStyle = (feature) => {
        const uf = feature.properties.sigla;
        const data = stateDataMap[uf];
        const isHovered = hoveredUf === uf;

        return {
            fillColor: data ? getLevelColor(data.level, isHovered ? 0.8 : 0.4) : 'transparent',
            weight: isHovered ? 3 : 1,
            opacity: 1,
            color: isHovered ? '#fff' : '#334155',
            fillOpacity: data ? (isHovered ? 0.8 : 0.4) : 0
        };
    };

    const onEachFeature = (feature, layer) => {
        const uf = feature.properties.sigla;
        layer.on({
            mouseover: () => setHoveredUf(uf),
            mouseout: () => setHoveredUf(null),
            click: () => handleStateClick(uf)
        });
    };

    return (
        <div className="min-h-screen bg-slate-950 text-slate-100 p-8 font-sans selection:bg-emerald-500/30 overflow-hidden h-screen flex flex-col">
            <Head title="Monitor Alerta Saúde" />
            
            <header className="mb-6 flex justify-between items-end shrink-0">
                <div>
                    <div className="flex items-center gap-4 mb-3">
                        {filters.uf && (
                            <button 
                                onClick={handleBack}
                                className="p-2.5 bg-slate-900 hover:bg-slate-800 rounded-xl border border-slate-800 transition-all shadow-lg"
                            >
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
                
                <div className="bg-slate-900/40 px-6 py-3 rounded-2xl border border-white/5 backdrop-blur-xl flex items-center gap-8 shadow-2xl">
                    <div className="flex items-center gap-3 border-r border-white/10 pr-8">
                        <TrendingUp className="text-emerald-400" size={20} />
                        <div>
                            <span className="text-[9px] text-slate-500 uppercase font-black tracking-[0.2em] block">Casos Totais</span>
                            <span className="text-xl font-mono font-bold text-white">
                                {(stats?.total_cases || 0).toLocaleString()}
                            </span>
                        </div>
                    </div>
                    <div className="flex items-center gap-3 border-r border-white/10 pr-8">
                        <Activity className="text-orange-400" size={20} />
                        <div>
                            <span className="text-[9px] text-slate-500 uppercase font-black tracking-[0.2em] block">Novos (Sem. #{stats?.latest_week})</span>
                            <span className="text-xl font-mono font-bold text-white">
                                {(stats?.new_cases || 0).toLocaleString()}
                            </span>
                        </div>
                    </div>
                    <div className="flex items-center gap-3">
                        <Search className="text-slate-500" size={18} />
                        <span className="text-sm font-medium text-slate-300">2026 • Dengue</span>
                    </div>
                </div>
            </header>

            <div className="grid grid-cols-1 lg:grid-cols-4 gap-6 flex-1 min-h-0">
                {/* Map Section */}
                <div className="lg:col-span-3 bg-slate-900/50 rounded-[2rem] border border-white/5 overflow-hidden shadow-3xl relative backdrop-blur-sm group flex flex-col">
                    <div className="flex-1 relative">
                        <MapContainer 
                            center={mapState.center} 
                            zoom={mapState.zoom} 
                            style={{ height: '100%', width: '100%', background: '#020617' }}
                            zoomControl={false}
                        >
                            <ChangeView center={mapState.center} zoom={mapState.zoom} />
                            <TileLayer
                                url="https://{s}.basemaps.cartocdn.com/dark_nolabels/{z}/{x}/{y}{r}.png"
                                attribution='&copy; CARTO'
                            />
                            
                            {isStateView ? (
                                <GeoJSON 
                                    data={brazilStates} 
                                    style={geoJsonStyle}
                                    onEachFeature={onEachFeature}
                                />
                            ) : (
                                dataList.map((record) => (
                                    record.city.lat && record.city.lng && (
                                        <CircleMarker
                                            key={record.id}
                                            center={[record.city.lat, record.city.lng]}
                                            pathOptions={{
                                                fillColor: getLevelColor(record.level, 0.8),
                                                fillOpacity: 0.7,
                                                color: selectedRecord?.id === record.id ? '#fff' : 'transparent',
                                                weight: 2
                                            }}
                                            radius={selectedRecord?.id === record.id ? 15 : 6 + (Math.sqrt(record.cases))}
                                            eventHandlers={{
                                                click: () => setSelectedRecord(record)
                                            }}
                                        />
                                    )
                                ))
                            )}
                            
                            <TileLayer
                                url="https://{s}.basemaps.cartocdn.com/dark_only_labels/{z}/{x}/{y}{r}.png"
                                pointerEvents="none"
                            />
                        </MapContainer>

                        {/* City Detail Overlay */}
                        {selectedRecord && (
                            <div className="absolute bottom-6 left-6 right-6 z-[1000] animate-in slide-in-from-bottom-8 duration-500">
                                <div className="bg-slate-900/95 backdrop-blur-2xl p-8 rounded-3xl border border-white/10 shadow-[0_32px_64px_-16px_rgba(0,0,0,0.8)] relative overflow-hidden group">
                                    <div className="absolute top-0 left-0 w-1.5 h-full bg-emerald-500 shadow-[0_0_20px_rgba(16,185,129,0.4)]" />
                                    
                                    <button 
                                        onClick={() => setSelectedRecord(null)}
                                        className="absolute top-4 right-4 p-2 hover:bg-white/5 rounded-full transition-colors"
                                    >
                                        <X size={20} className="text-slate-500" />
                                    </button>

                                    <div className="flex gap-12">
                                        <div className="flex-none w-1/3">
                                            <div className="flex items-start justify-between mb-2">
                                                <h3 className="text-4xl font-black tracking-tighter text-white">{selectedRecord.city.name}</h3>
                                                <div className={`px-3 py-1 rounded-lg text-[10px] font-black uppercase tracking-widest shadow-lg ${
                                                    selectedRecord.level === 4 ? 'bg-red-500 text-white' :
                                                    selectedRecord.level === 3 ? 'bg-orange-500 text-white' :
                                                    'bg-emerald-500 text-white'
                                                }`}>
                                                    {selectedRecord.status}
                                                </div>
                                            </div>
                                            
                                            <div className="flex flex-wrap gap-2 mb-6">
                                                <span className="flex items-center gap-1.5 text-[10px] font-bold text-slate-300 uppercase tracking-widest bg-white/5 px-2 py-1.5 rounded-md border border-white/5">
                                                    <MapIcon size={10} className="text-emerald-400" /> {selectedRecord.city.uf}
                                                </span>
                                                <span className="flex items-center gap-1.5 text-[10px] font-bold text-slate-300 uppercase tracking-widest bg-white/5 px-2 py-1.5 rounded-md border border-white/5">
                                                    <Users size={10} className="text-emerald-400" /> {selectedRecord.population.toLocaleString()} hab.
                                                </span>
                                                <span className="flex items-center gap-1.5 text-[10px] font-bold text-emerald-400 uppercase tracking-widest bg-emerald-400/10 px-2 py-1.5 rounded-md border border-emerald-400/20">
                                                    <Clock size={10} /> {selectedRecord.month}
                                                </span>
                                            </div>

                                            <div className="bg-slate-950/40 p-5 rounded-2xl border border-white/5 space-y-4">
                                                <div className="flex justify-between items-end">
                                                    <div>
                                                        <span className="text-[9px] text-slate-500 uppercase font-black tracking-widest block mb-1">Período</span>
                                                        <div className="text-xs font-bold text-slate-200">
                                                            {selectedRecord.week_range}
                                                        </div>
                                                    </div>
                                                    <div className="grid grid-cols-2 gap-4">
                                                        <div>
                                                            <span className="text-[9px] text-slate-500 uppercase font-black tracking-wider block mb-1">Novos Casos</span>
                                                            <div className="text-xl font-mono font-bold text-white">
                                                                {selectedRecord.cases}
                                                            </div>
                                                        </div>
                                                        <div className="text-right">
                                                            <span className="text-[9px] text-slate-500 uppercase font-black tracking-wider block mb-1">Incidência</span>
                                                            <div className="text-xl font-mono font-bold text-emerald-400">
                                                                {selectedRecord.incidence}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                {/* Termômetro de Risco Reintroduzido */}
                                                <div className="space-y-2 border-t border-white/5 pt-3">
                                                    <div className="h-1.5 w-full bg-slate-800 rounded-full overflow-hidden flex">
                                                        <div 
                                                            className={`h-full transition-all duration-1000 ${
                                                                selectedRecord.incidence > 300 ? 'bg-red-500' : 
                                                                selectedRecord.incidence > 100 ? 'bg-orange-500' : 'bg-emerald-500'
                                                            }`}
                                                            style={{ width: `${Math.min((selectedRecord.incidence / 500) * 100, 100)}%` }}
                                                        />
                                                    </div>
                                                    <p className="text-[9px] text-slate-400 font-medium leading-relaxed">
                                                        {selectedRecord.incidence > 300 
                                                            ? '⚠️ Surto Crítico' 
                                                            : selectedRecord.incidence > 100 
                                                                ? '🟠 Transmissão Moderada' 
                                                                : '✅ Circulação Estável'}
                                                        <span className="mx-2 opacity-20">|</span>
                                                        1 em cada {Math.round(100000 / selectedRecord.incidence).toLocaleString()} pessoas
                                                    </p>
                                                </div>
                                            </div>
                                            
                                            {(selectedRecord.level >= 3 && selectedRecord.cases < 10) && (
                                                <p className="mt-4 text-[10px] text-slate-400 bg-red-500/5 p-3 rounded-xl border border-red-500/10 leading-relaxed flex gap-2">
                                                    <ShieldAlert size={14} className="text-red-500 shrink-0" />
                                                    <span><strong>Vigilância Preventiva:</strong> Alerta mantido pela Fiocruz devido ao histórico recente de surto e risco de persistência.</span>
                                                </p>
                                            )}
                                        </div>

                                        <div className="flex-1 min-w-0">
                                            <div className="flex items-center justify-between mb-4">
                                                <span className="text-[10px] text-slate-500 uppercase font-black tracking-[0.2em]">Evolução Mensal (Semanalmente)</span>
                                                {loadingHistory && <Activity className="animate-spin text-emerald-400" size={14} />}
                                            </div>
                                            <div className="h-52 w-full bg-slate-950/30 rounded-2xl p-4 border border-white/5">
                                                {history.length > 0 ? (
                                                    <ResponsiveContainer width="100%" height="100%">
                                                        <LineChart data={history}>
                                                            <CartesianGrid strokeDasharray="3 3" stroke="#1e293b" vertical={false} />
                                                            <XAxis 
                                                                dataKey="week" 
                                                                axisLine={false} 
                                                                tickLine={false} 
                                                                tick={{fill: '#475569', fontSize: 10}}
                                                                label={{ value: 'Semana Epidemiológica', position: 'insideBottom', offset: -5, fill: '#475569', fontSize: 9, fontWeight: 'bold' }}
                                                            />
                                                            <YAxis 
                                                                axisLine={false} 
                                                                tickLine={false} 
                                                                tick={{fill: '#475569', fontSize: 10}} 
                                                            />
                                                            <ChartTooltip 
                                                                content={({ active, payload }) => {
                                                                    if (active && payload && payload.length) {
                                                                        const data = payload[0].payload;
                                                                        return (
                                                                            <div className="bg-slate-900 border border-white/10 p-3 rounded-xl shadow-2xl">
                                                                                <p className="text-[10px] font-black text-slate-500 uppercase mb-1">{data.month} • Sem #{data.week}</p>
                                                                                <p className="text-sm font-bold text-emerald-400">{data.cases} casos</p>
                                                                                <p className="text-[9px] text-slate-400">{data.week_range}</p>
                                                                            </div>
                                                                        );
                                                                    }
                                                                    return null;
                                                                }}
                                                            />
                                                            <Line 
                                                                type="monotone" 
                                                                dataKey="cases" 
                                                                stroke="#10b981" 
                                                                strokeWidth={3} 
                                                                dot={{ r: 4, fill: '#10b981' }} 
                                                                activeDot={{ r: 6, stroke: '#fff', strokeWidth: 2 }}
                                                                animationDuration={1500}
                                                            />
                                                        </LineChart>
                                                    </ResponsiveContainer>
                                                ) : (
                                                    <div className="h-full flex items-center justify-center text-slate-700 text-xs italic">
                                                        Processando série histórica...
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>
                </div>

                {/* Sidebar/List Section */}
                <div className="bg-slate-900/50 rounded-[2rem] border border-white/5 p-8 shadow-3xl flex flex-col h-full backdrop-blur-sm overflow-hidden">
                    <h2 className="text-xl font-black tracking-tight flex items-center gap-3 mb-8 shrink-0">
                        <div className="w-1.5 h-6 bg-emerald-400 rounded-full"></div>
                        {isStateView ? 'Ranking Nacional' : `Municípios (${filters.uf})`}
                    </h2>
                    
                    <div className="space-y-3 overflow-y-auto flex-1 pr-3 custom-scrollbar">
                        {dataList.sort((a, b) => (b.total_cases || b.cases) - (a.total_cases || a.cases)).map((item) => (
                            <div 
                                key={isStateView ? item.uf : item.id} 
                                onClick={() => isStateView ? handleStateClick(item.uf) : setSelectedRecord(item)}
                                className={`p-4 rounded-2xl border transition-all duration-300 cursor-pointer group relative overflow-hidden ${
                                    selectedRecord?.id === item.id 
                                    ? 'bg-emerald-500/10 border-emerald-500/30' 
                                    : 'bg-slate-800/30 border-white/5 hover:bg-slate-800/50 hover:border-white/10'
                                }`}
                            >
                                <div className="flex justify-between items-center relative z-10">
                                    <div className="min-w-0 text-left">
                                        <h3 className={`font-bold truncate ${selectedRecord?.id === item.id ? 'text-emerald-400' : 'text-slate-200'}`}>
                                            {isStateView ? `Estado de ${item.uf}` : item.city.name}
                                        </h3>
                                        <div className="flex items-center gap-3 mt-1.5">
                                            <div className="flex flex-col">
                                                <span className="text-[8px] text-slate-500 font-black uppercase tracking-widest">Total</span>
                                                <span className="text-[10px] text-slate-300 font-bold">{(item.total_cases || item.cases).toLocaleString()}</span>
                                            </div>
                                            <div className="w-px h-4 bg-white/10" />
                                            <div className="flex flex-col">
                                                <span className="text-[8px] text-orange-500/70 font-black uppercase tracking-widest">Novos</span>
                                                <span className="text-[10px] text-orange-400 font-bold">{(item.new_cases || 0).toLocaleString()}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div className={`shrink-0 w-8 h-8 rounded-lg flex items-center justify-center text-[10px] font-bold ${
                                        item.level === 4 ? 'bg-red-500/10 text-red-500' :
                                        item.level === 3 ? 'bg-orange-500/10 text-orange-500' :
                                        'bg-emerald-500/10 text-emerald-500'
                                    }`}>
                                        L{item.level}
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </div>

            <style dangerouslySetInnerHTML={{ __html: `
                .custom-scrollbar::-webkit-scrollbar { width: 4px; }
                .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
                .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.05); border-radius: 20px; }
                .leaflet-container { font-family: inherit !important; background: #020617 !important; }
            `}} />
        </div>
    );
}
