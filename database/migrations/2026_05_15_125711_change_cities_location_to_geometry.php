<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            // Dropa o índice espacial antigo
            DB::statement('DROP INDEX IF EXISTS cities_location_spatialindex');

            // Altera o tipo de geography para geometry com cast explícito
            DB::statement('ALTER TABLE cities ALTER COLUMN location TYPE geometry(GEOMETRY, 4326) USING location::geometry');

            // Recria o índice espacial
            DB::statement('CREATE INDEX cities_location_spatialindex ON cities USING gist(location)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS cities_location_spatialindex');
            DB::statement('ALTER TABLE cities ALTER COLUMN location TYPE geography(POINT, 4326) USING location::geography');
            DB::statement('CREATE INDEX cities_location_spatialindex ON cities USING gist(location)');
        }
    }
};
