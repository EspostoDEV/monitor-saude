import React, { useEffect } from 'react';
import { MapContainer, TileLayer, CircleMarker, GeoJSON, useMap } from 'react-leaflet';
import { Loader2 } from 'lucide-react';
import 'leaflet/dist/leaflet.css';

/**
 * Internal component to handle map view changes
 */
function ChangeView({ center, zoom }) {
    const map = useMap();
    useEffect(() => {
        map.setView(center, zoom);
        // Force map to recalculate its container size (Issue 12 - Broken Tiles)
        setTimeout(() => {
            map.invalidateSize();
        }, 100);
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
    );
}
