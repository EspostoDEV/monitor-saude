<?php

namespace App\Http\Controllers;

use App\Services\GeoSpatialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class GeoSpatialController extends Controller
{
    protected GeoSpatialService $geoService;

    public function __construct(GeoSpatialService $geoService)
    {
        $this->geoService = $geoService;
    }

    public function index(Request $request): JsonResponse
    {
        $zoom = (int) $request->query('zoom', 4);
        $uf = $request->query('uf');

        $lod = $this->getLodLevel($zoom);
        $tolerance = $this->getTolerance($lod);

        // Cache estratégico por UF e LOD
        $cacheKey = 'geojson_map_'.($uf ?: 'national')."_{$lod}";

        $geoJson = Cache::remember($cacheKey, 604800, function () use ($uf, $tolerance) {
            $ufFilter = $uf ? 'WHERE uf = :uf' : '';
            $params = $uf ? ['uf' => $uf, 'tolerance' => $tolerance] : ['tolerance' => $tolerance];

            $sql = "
                SELECT jsonb_build_object(
                    'type',     'FeatureCollection',
                    'features', COALESCE(jsonb_agg(features.feature), '[]'::jsonb)
                ) as collection
                FROM (
                  SELECT jsonb_build_object(
                    'type',       'Feature',
                    'id',         id,
                    'geometry',   ST_AsGeoJSON(ST_SimplifyPreserveTopology(location, :tolerance))::jsonb,
                    'properties', jsonb_build_object(
                        'id', id,
                        'ibge_code', ibge_code,
                        'name', name,
                        'uf', uf
                    )
                  ) AS feature
                  FROM cities
                  $ufFilter
                ) AS features
            ";

            $result = DB::selectOne($sql, $params);

            return $result->collection;
        });

        return response()->json(is_string($geoJson) ? json_decode($geoJson) : $geoJson);
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
