<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            DB::statement('
                CREATE VIEW mv_uf_epidemic_stats AS
                SELECT 
                    cities.uf, 
                    epidemic_records.year, 
                    epidemic_records.epi_week, 
                    epidemic_records.disease_type,
                    SUM(epidemic_records.cases) as total_cases, 
                    SUM(epidemic_records.population) as total_population, 
                    (CAST(SUM(epidemic_records.cases) AS FLOAT) / NULLIF(SUM(epidemic_records.population), 0) * 100000) as real_incidence,
                    MAX(epidemic_records.updated_at) as last_sync_at
                FROM epidemic_records
                JOIN cities ON cities.id = epidemic_records.city_id
                GROUP BY cities.uf, epidemic_records.year, epidemic_records.epi_week, epidemic_records.disease_type
            ');
        } elseif ($driver === 'pgsql') {
            DB::statement('
                CREATE MATERIALIZED VIEW mv_uf_epidemic_stats AS
                SELECT 
                    cities.uf, 
                    epidemic_records.year, 
                    epidemic_records.epi_week, 
                    epidemic_records.disease_type,
                    SUM(epidemic_records.cases) as total_cases, 
                    SUM(epidemic_records.population) as total_population, 
                    (SUM(epidemic_records.cases)::float / NULLIF(SUM(epidemic_records.population), 0) * 100000) as real_incidence,
                    MAX(epidemic_records.updated_at) as last_sync_at
                FROM epidemic_records
                JOIN cities ON cities.id = epidemic_records.city_id
                GROUP BY cities.uf, epidemic_records.year, epidemic_records.epi_week, epidemic_records.disease_type
            ');

            DB::statement('CREATE UNIQUE INDEX idx_mv_uf_epi_stats_unique ON mv_uf_epidemic_stats (uf, year, epi_week, disease_type)');
        } else {
            throw new RuntimeException('Database driver '.$driver.' not supported for this migration.');
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            DB::statement('DROP VIEW IF EXISTS mv_uf_epidemic_stats');
        } elseif ($driver === 'pgsql') {
            DB::statement('DROP MATERIALIZED VIEW IF EXISTS mv_uf_epidemic_stats');
        }
    }
};
