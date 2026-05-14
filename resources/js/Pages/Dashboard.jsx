import React, { useMemo, useEffect, useState, useCallback } from 'react';
import { Head, router } from '@inertiajs/react';
import { Activity, Loader2 } from 'lucide-react';
import axios from 'axios';
import { getLevelColor } from '@/Utils/epidemiology';

// Dashboard Components (Lazy loaded for better TTI)
const ControlHeader = React.lazy(() => import('@/Components/Dashboard/ControlHeader'));
const EpidemiologyMap = React.lazy(() => import('@/Components/Dashboard/EpidemiologyMap'));
const StatsSidebar = React.lazy(() => import('@/Components/Dashboard/StatsSidebar'));
const CityDetailsCard = React.lazy(() => import('@/Components/Dashboard/CityDetailsCard'));


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
    'OT': 'Outros', 'SP': 'São Paulo', 'SE': 'Sergipe', 'TO': 'Tocantins'
};

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

    // Initialization logic
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
                .catch(err => {
                    console.error("Falha ao buscar histórico:", err);
                    setHistory([]); // Limpa para evitar dados stale
                    setLoadingHistory(false);
                });
        }
    }, [selectedRecord]);

    // Navigation Handlers
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

    // Data Processing
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
            
            <React.Suspense fallback={
                <div className="h-full w-full bg-slate-950 flex flex-col items-center justify-center">
                    <Loader2 className="w-10 h-10 text-emerald-500 animate-spin opacity-20 mb-4" />
                    <span className="text-[10px] text-slate-700 font-black tracking-[0.3em] uppercase">Inicializando Sistema...</span>
                </div>
            }>
                <ControlHeader 
                    filters={filters} 
                    handleBack={handleBack} 
                    stats={stats} 
                />

                <div className="grid grid-cols-1 lg:grid-cols-4 gap-6 flex-1 min-h-0">
                    <div className="lg:col-span-3 bg-slate-900/50 rounded-[2rem] border border-white/5 overflow-hidden shadow-3xl relative flex flex-col">
                        <div className="flex-1 relative">
                            <EpidemiologyMap 
                                mapState={mapState}
                                isStateView={isStateView}
                                geoData={geoData}
                                geoJsonStyle={geoJsonStyle}
                                onEachFeature={onEachFeature}
                                dataList={dataList}
                                selectedRecord={selectedRecord}
                                setSelectedRecord={setSelectedRecord}
                                getLevelColor={getLevelColor}
                            />

                            <CityDetailsCard 
                                selectedRecord={selectedRecord}
                                setSelectedRecord={setSelectedRecord}
                                history={history}
                                STATE_NAMES={STATE_NAMES}
                            />
                        </div>
                    </div>

                    <StatsSidebar 
                        isStateView={isStateView}
                        filters={filters}
                        searchTerm={searchTerm}
                        setSearchTerm={setSearchTerm}
                        sortedDataList={sortedDataList}
                        handleStateClick={handleStateClick}
                        setSelectedRecord={setSelectedRecord}
                        STATE_NAMES={STATE_NAMES}
                    />
                </div>
            </React.Suspense>

            <style dangerouslySetInnerHTML={{ __html: `
                .custom-scrollbar::-webkit-scrollbar { width: 4px; }
                .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.05); border-radius: 20px; }
                .leaflet-container { font-family: inherit !important; background: #020617 !important; }
            `}} />
        </div>
    );
}
