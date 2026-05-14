import React, { useEffect } from 'react';
import { useMap } from 'react-leaflet';
import { Loader2 } from 'lucide-react';

// Lazy load react-leaflet components for performance
const MapContainer = React.lazy(() => import('react-leaflet').then(m => ({ default: m.MapContainer })));
const TileLayer = React.lazy(() => import('react-leaflet').then(m => ({ default: m.TileLayer })));
const CircleMarker = React.lazy(() => import('react-leaflet').then(m => ({ default: m.CircleMarker })));
const GeoJSON = React.lazy(() => import('react-leaflet').then(m => ({ default: m.GeoJSON })));

/**
 * Internal component to handle map view changes
 */
function ChangeView({ center, zoom }) {
    const map = useMap();
    useEffect(() => {
        map.setView(center, zoom);
    }, [center, zoom, map]);
    return null;
}

export default function EpidemiologyMap({ 
    mapState, 
    isStateView, 
    geoData, 
    geoJsonStyle, 
    onEachFeature, 
    dataList, 
    selectedRecord, 
    setSelectedRecord,
    getLevelColor
}) {
    return (
        <React.Suspense fallback={
            <div className="h-full w-full bg-slate-900 flex flex-col items-center justify-center gap-4">
                <Loader2 className="w-8 h-8 text-emerald-500 animate-spin opacity-20" />
                <span className="text-xs text-slate-700 font-medium tracking-widest uppercase">Carregando Mapa...</span>
            </div>
        }>
            <MapContainer 
                center={mapState.center} 
                zoom={mapState.zoom} 
                className="h-full w-full bg-slate-900" 
                zoomControl={false}
            >
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
    );
}
