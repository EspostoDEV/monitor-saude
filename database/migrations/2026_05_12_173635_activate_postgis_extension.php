<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS postgis');
        }

        Schema::table('cities', function (Blueprint $table) {
            if (Schema::hasColumn('cities', 'lat')) {
                $table->dropColumn(['lat', 'lng']);
            }

            if (! Schema::hasColumn('cities', 'location')) {
                if (DB::getDriverName() === 'pgsql') {
                    $table->geography('location', 'point', 4326);
                    $table->spatialIndex('location');
                } else {
                    // Fallback para SQLite/Ambientes de Teste sem PostGIS
                    $table->text('location')->nullable();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('cities', function (Blueprint $table) {
            if (DB::getDriverName() === 'pgsql') {
                $table->dropSpatialIndex(['location']);
            }
            $table->dropColumn('location');
            $table->decimal('lat', 10, 8)->nullable();
            $table->decimal('lng', 11, 8)->nullable();
        });
    }
};
