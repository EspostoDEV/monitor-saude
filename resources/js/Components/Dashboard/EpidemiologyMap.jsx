import React, { useEffect } from 'react';
import { MapContainer, TileLayer, CircleMarker, GeoJSON, useMap } from 'react-leaflet';
import { Loader2 } from 'lucide-react';
import 'leaflet/dist/leaflet.css';

/**
 * Memoized CircleMarker for high-performance rendering of 5k+ points
 */
const MemoizedCircleMarker = React.memo(({ record, selectedRecord, setSelectedRecord, getLevelColor }) => {
    if (!record.city?.lat) return null;

    return (
        <CircleMarker
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
    );
}, (prev, next) => {
    // Só renderiza novamente se o ID mudar, o nível de alerta mudar ou o status de seleção mudar
    return prev.record.id === next.record.id && 
           prev.record.level === next.record.level && 
           (prev.selectedRecord?.id === prev.record.id) === (next.selectedRecord?.id === next.record.id);
});

/**
 * Robust map handler using ResizeObserver
 */
function MapController({ center, zoom }) {
    const map = useMap();

    useEffect(() => {
        map.setView(center, zoom);
    }, [center, zoom, map]);

    useEffect(() => {
        const observer = new ResizeObserver(() => {
            map.invalidateSize();
        });

        observer.observe(map.getContainer());
        return () => observer.disconnect();
    }, [map]);

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
        <MapContainer 
            center={mapState.center} 
            zoom={mapState.zoom} 
            className="h-full w-full bg-slate-900" 
            zoomControl={false}
        >
            <MapController center={mapState.center} zoom={mapState.zoom} />
            <TileLayer url="https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png" />
            
            {isStateView ? (
                geoData && <GeoJSON data={geoData} style={geoJsonStyle} onEachFeature={onEachFeature} />
            ) : (
                dataList.map((record) => (
                    <MemoizedCircleMarker
                        key={record.id}
                        record={record}
                        selectedRecord={selectedRecord}
                        setSelectedRecord={setSelectedRecord}
                        getLevelColor={getLevelColor}
                    />
                ))
            )}
            <TileLayer url="https://{s}.basemaps.cartocdn.com/dark_only_labels/{z}/{x}/{y}{r}.png" pointerEvents="none" />
        </MapContainer>
    );
}
