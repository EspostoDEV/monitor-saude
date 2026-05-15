<?php

namespace App\Services;

use App\Models\City;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class GeoSpatialService
{
    private const TTL = 604800; // 1 week

    public function getCityGeoJson(City $city, int $zoom): string
    {
        $lod = $this->getLodLevel($zoom);
        $cacheKey = "geojson:{$city->ibge_code}:{$lod}";

        return Cache::remember($cacheKey, self::TTL, function () use ($city, $lod) {
            $tolerance = $this->getTolerance($lod);

            $result = DB::selectOne(
                'SELECT ST_AsGeoJSON(ST_SimplifyPreserveTopology(location, :tolerance)) as geojson 
                 FROM cities WHERE id = :id',
                ['tolerance' => $tolerance, 'id' => $city->id]
            );

            return $result->geojson ?? '';
        });
    }

    private function getLodLevel(int $zoom): string
    {
        if ($zoom <= 5) {
            return 'low';
        }
        if ($zoom <= 10) {
            return 'medium';
        }

        return 'high';
    }

    private function getTolerance(string $lod): float
    {
        return match ($lod) {
            'low' => 0.01,
            'medium' => 0.001,
            'high' => 0.0001,
            default => 0.001,
        };
    }
}
