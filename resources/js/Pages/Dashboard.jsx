import React, { useMemo, useEffect, useState, useCallback } from 'react';
import { Head, router } from '@inertiajs/react';
import { useMap } from 'react-leaflet';
import { Activity, Map as MapIcon, ShieldAlert, Info, ArrowLeft, TrendingUp, TrendingDown, Users, Calendar, X, Search, Clock, Loader2 } from 'lucide-react';
import axios from 'axios';
import { getLevelColor } from '@/Utils/epidemiology';
import 'leaflet/dist/leaflet.css';

// Lazy load heavy components (Issue 10 - Performance)
const MapContainer = React.lazy(() => import('react-leaflet').then(m => ({ default: m.MapContainer })));
const TileLayer = React.lazy(() => import('react-leaflet').then(m => ({ default: m.TileLayer })));
const CircleMarker = React.lazy(() => import('react-leaflet').then(m => ({ default: m.CircleMarker })));
const GeoJSON = React.lazy(() => import('react-leaflet').then(m => ({ default: m.GeoJSON })));

const LineChart = React.lazy(() => import('recharts').then(m => ({ default: m.LineChart })));
const Line = React.lazy(() => import('recharts').then(m => ({ default: m.Line })));
const XAxis = React.lazy(() => import('recharts').then(m => ({ default: m.XAxis })));
const YAxis = React.lazy(() => import('recharts').then(m => ({ default: m.YAxis })));
const CartesianGrid = React.lazy(() => import('recharts').then(m => ({ default: m.CartesianGrid })));
const ChartTooltip = React.lazy(() => import('recharts').then(m => ({ default: m.Tooltip })));
const ResponsiveContainer = React.lazy(() => import('recharts').then(m => ({ default: m.ResponsiveContainer })));


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

const STATE_NAMES = {
    'AC': 'Acre', 'AL': 'Alagoas', 'AP': 'Amapá', 'AM': 'Amazonas', 'BA': 'Bahia', 'CE': 'Ceará',
    'DF': 'Distrito Federal', 'ES': 'Espírito Santo', 'GO': 'Goiás', 'MA': 'Maranhão', 'MT': 'Mato Grosso',
    'MS': 'Mato Grosso do Sul', 'MG': 'Minas Gerais', 'PA': 'Pará', 'PB': 'Paraíba', 'PR': 'Paraná',
    'PE': 'Pernambuco', 'PI': 'Piauí', 'RJ': 'Rio de Janeiro', 'RN': 'Rio Grande do Norte',
    'RS': 'Rio Grande do Sul', 'RO': 'Rondônia', 'RR': 'Roraima', 'SC': 'Santa Catarina',
    'SP': 'São Paulo', 'SE': 'Sergipe', 'TO': 'Tocantins'
};

function ChangeView({ center, zoom }) {
    const map = useMap();
    useEffect(() => {
        map.setView(center, zoom);
    }, [center, zoom]);
    return null;
}

export default function Dashboard({ records, filters, stats }) {
    const isStateView = !filters.uf || filters.uf === '';
    const [hoveredUf, setHoveredUf] = useState(null);
    const [selectedRecord, setSelectedRecord] = useState(null);
    const [history, setHistory] = useState([]);
    const [loadingHistory, setLoadingHistory] = useState(false);
    const [geoData, setGeoData] = useState(null);
    const [geoError, setGeoError] = useState(null);
    const [isPageLoading, setIsPageLoading] = useState(true);
    const [searchTerm, setSearchTerm] = useState('');

    useEffect(() => {
        if (geoData || geoError) {
            const timer = setTimeout(() => setIsPageLoading(false), 200);
            return () => clearTimeout(timer);
        }
    }, [geoData, geoError]);

    useEffect(() => {
        const controller = new AbortController();
        fetch('/data/brazil-states.json', { signal: controller.signal })
            .then(res => {
                if (!res.ok) throw new Error('Falha ao carregar dados do mapa');
                return res.json();
            })
            .then(data => setGeoData(data))
            .catch(err => {
                if (err.name !== 'AbortError') setGeoError(err.message);
            });
        return () => controller.abort();
    }, []);
    
    const mapState = useMemo(() => {
        if (filters.uf && STATE_COORDS[filters.uf]) return { center: STATE_COORDS[filters.uf], zoom: 7 };
        return { center: [-15.7801, -47.9292], zoom: 4 };
    }, [filters.uf]);

    useEffect(() => {
        if (selectedRecord) {
            setLoadingHistory(true);
            const cityId = selectedRecord.city_id || selectedRecord.id;
            axios.get(`/api/history/${cityId}`)
                .then(res => {
                    setHistory(res.data);
                    setLoadingHistory(false);
                })
                .catch(() => setLoadingHistory(false));
        }
    }, [selectedRecord]);

    const handleStateClick = useCallback((uf) => {
        setSelectedRecord(null);
        setSearchTerm('');
        router.get('/', { ...filters, uf }, { preserveState: true });
    }, [filters]);

    const handleBack = useCallback(() => {
        setSelectedRecord(null);
        setSearchTerm('');
        router.get('/', { ...filters, uf: null }, { preserveState: true });
    }, [filters]);

    const dataList = useMemo(() => {
        if (!records) return [];
        if (Array.isArray(records)) return records;
        if (records.data && Array.isArray(records.data)) return records.data;
        return Object.values(records).filter(item => item && typeof item === 'object');
    }, [records]);

    const filteredDataList = useMemo(() => {
        if (!searchTerm) return dataList;
        const term = searchTerm.toLowerCase();
        return dataList.filter(item => {
            if (isStateView) {
                const uf = item.uf || '';
                const fullName = STATE_NAMES[uf] || '';
                return uf.toLowerCase().includes(term) || fullName.toLowerCase().includes(term);
            }
            const cityName = item.city?.name || '';
            return cityName.toLowerCase().includes(term);
        });
    }, [dataList, searchTerm, isStateView]);

    const sortedDataList = useMemo(() => {
        return [...filteredDataList].sort((a, b) => (b.total_cases || b.cases || 0) - (a.total_cases || a.cases || 0));
    }, [filteredDataList]);
    
    const stateDataMap = useMemo(() => {
        if (!isStateView || !Array.isArray(dataList)) return {};
        return dataList.reduce((acc, curr) => {
            acc[curr.uf] = curr;
            return acc;
        }, {});
    }, [dataList, isStateView]);

    const geoJsonStyle = useCallback((feature) => {
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
    }, [stateDataMap, hoveredUf]);

    const onEachFeature = useCallback((feature, layer) => {
        const uf = feature.properties.sigla;
        layer.on({
            mouseover: () => setHoveredUf(uf),
            mouseout: () => setHoveredUf(null),
            click: () => handleStateClick(uf)
        });
    }, [handleStateClick]);

    return (
        <div className="min-h-screen bg-slate-950 text-slate-100 p-8 font-sans selection:bg-emerald-500/30 overflow-hidden h-screen flex flex-col relative">
            <Head title="Monitor Alerta Saúde" />
            
            {/* Loading Overlay */}
            {isPageLoading && (
                <div className="absolute inset-0 z-[5000] bg-slate-950 flex flex-col items-center justify-center animate-out fade-out duration-700">
                    <div className="flex flex-col items-center gap-6">
                        <div className="p-4 bg-emerald-500/10 rounded-2xl border border-emerald-500/20">
                            <Activity className="text-emerald-400 animate-bounce" size={48} />
                        </div>
                        <div className="space-y-2 text-center">
                            <h1 className="text-2xl font-black tracking-tight">Monitor<span className="text-emerald-400">Saúde</span></h1>
                            <p className="text-slate-500 text-sm font-medium">Sincronizando inteligência epidemiológica...</p>
                        </div>
                    </div>
                </div>
            )}
            
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
                
                <div className="bg-slate-900/40 px-6 py-3 rounded-2xl border border-white/5 backdrop-blur-xl flex items-center gap-8 shadow-2xl">
                    <div className="flex items-center gap-3 border-r border-white/10 pr-8">
                        <TrendingUp className="text-emerald-400" size={20} />
                        <div>
                            <span className="text-[9px] text-slate-500 uppercase font-black tracking-[0.2em] block">Casos Totais</span>
                            <span className="text-xl font-mono font-bold text-white">{(stats?.total_cases || 0).toLocaleString()}</span>
                        </div>
                    </div>
                    <div className="flex items-center gap-3">
                        <Calendar className="text-slate-500" size={18} />
                        <div className="flex flex-col">
                            <span className="text-sm font-medium text-slate-300">2026 • Dengue • Sem #{stats?.latest_week}</span>
                            {stats?.last_sync && (
                                <span className="text-[10px] text-slate-500 font-medium">Sincronizado: {new Date(stats.last_sync).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })}</span>
                            )}
                        </div>
                    </div>
                </div>
            </header>

            <div className="grid grid-cols-1 lg:grid-cols-4 gap-6 flex-1 min-h-0">
                <div className="lg:col-span-3 bg-slate-900/50 rounded-[2rem] border border-white/5 overflow-hidden shadow-3xl relative flex flex-col">
                    <div className="flex-1 relative">
                        <React.Suspense fallback={
                            <div className="h-full w-full bg-slate-900 flex flex-col items-center justify-center gap-4">
                                <Loader2 className="w-8 h-8 text-emerald-500 animate-spin opacity-20" />
                                <span className="text-xs text-slate-700 font-medium tracking-widest uppercase">Carregando Mapa...</span>
                            </div>
                        }>
                            <MapContainer center={mapState.center} zoom={mapState.zoom} className="h-full w-full bg-slate-900" zoomControl={false}>
                                <ChangeView center={mapState.center} zoom={mapState.zoom} />
                                <TileLayer url="https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png" />
                                
                                {isStateView ? (
                                    geoData && <GeoJSON data={geoData} style={geoJsonStyle} onEachFeature={onEachFeature} />
                                ) : (
                                    dataList.map((record) => (
                                        record.city?.lat && (
                                            <CircleMarker
                                                key={record.id}
                                                center={[record.city.lat, record.city.lng]}
                                                pathOptions={{
                                                    fillColor: getLevelColor(record.level, 0.8),
                                                    fillOpacity: 0.7,
                                                    color: selectedRecord?.id === record.id ? '#fff' : 'transparent',
                                                    weight: 2
                                                }}
                                                radius={selectedRecord?.id === record.id ? 15 : 6 + Math.sqrt(record.cases || 0)}
                                                eventHandlers={{ click: () => setSelectedRecord(record) }}
                                            />
                                        )
                                    ))
                                )}
                                <TileLayer url="https://{s}.basemaps.cartocdn.com/dark_only_labels/{z}/{x}/{y}{r}.png" pointerEvents="none" />
                            </MapContainer>
                        </React.Suspense>

                        {selectedRecord && (
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
                        )}
                    </div>
                </div>

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
                            <div key={isStateView ? item.uf : item.id} onClick={() => isStateView ? handleStateClick(item.uf) : setSelectedRecord(item)}
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
            </div>

            <style dangerouslySetInnerHTML={{ __html: `
                .custom-scrollbar::-webkit-scrollbar { width: 4px; }
                .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.05); border-radius: 20px; }
                .leaflet-container { font-family: inherit !important; background: #020617 !important; }
            `}} />
        </div>
    );
}
