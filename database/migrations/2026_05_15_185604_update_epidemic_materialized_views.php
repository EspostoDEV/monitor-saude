<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            $this->createSqliteViews();
        } elseif ($driver === 'pgsql') {
            $this->createPostgresViews();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            DB::statement('DROP VIEW IF EXISTS mv_national_stats');
            DB::statement('DROP VIEW IF EXISTS mv_uf_epidemic_stats');
        } elseif ($driver === 'pgsql') {
            DB::statement('DROP MATERIALIZED VIEW IF EXISTS mv_national_stats');
            DB::statement('DROP MATERIALIZED VIEW IF EXISTS mv_uf_epidemic_stats');
        }
    }

    private function createSqliteViews(): void
    {
        DB::statement('DROP VIEW IF EXISTS mv_uf_epidemic_stats');
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
                \'stable\' as trend,
                1 as alert_level,
                MAX(epidemic_records.updated_at) as last_sync_at
            FROM epidemic_records
            JOIN cities ON cities.id = epidemic_records.city_id
            GROUP BY cities.uf, epidemic_records.year, epidemic_records.epi_week, epidemic_records.disease_type
        ');

        DB::statement('DROP VIEW IF EXISTS mv_national_stats');
        DB::statement('
            CREATE VIEW mv_national_stats AS
            SELECT 
                year,
                disease_type,
                SUM(cases) as total_cases,
                MAX(updated_at) as last_sync_at
            FROM epidemic_records
            GROUP BY year, disease_type
        ');
    }

    private function createPostgresViews(): void
    {
        DB::statement('DROP MATERIALIZED VIEW IF EXISTS mv_uf_epidemic_stats');
        DB::statement("
            CREATE MATERIALIZED VIEW mv_uf_epidemic_stats AS
            WITH raw_stats AS (
                SELECT 
                    cities.uf, 
                    epidemic_records.year, 
                    epidemic_records.epi_week, 
                    epidemic_records.disease_type,
                    SUM(epidemic_records.cases) as total_cases, 
                    SUM(epidemic_records.population) as total_population, 
                    MAX(epidemic_records.updated_at) as last_sync_at
                FROM epidemic_records
                JOIN cities ON cities.id = epidemic_records.city_id
                GROUP BY cities.uf, epidemic_records.year, epidemic_records.epi_week, epidemic_records.disease_type
            ),
            stats_with_incidence AS (
                SELECT 
                    *,
                    (total_cases::float / NULLIF(total_population, 0) * 100000) as real_incidence,
                    (year * 53 + epi_week) as continuity_token
                FROM raw_stats
            ),
            stats_with_lag AS (
                SELECT 
                    *,
                    LAG(real_incidence) OVER (PARTITION BY uf, disease_type ORDER BY year, epi_week) as prev_incidence,
                    LAG(continuity_token) OVER (PARTITION BY uf, disease_type ORDER BY year, epi_week) as prev_token
                FROM stats_with_incidence
            )
            SELECT 
                uf, year, epi_week, disease_type, total_cases, total_population, last_sync_at, real_incidence,
                CASE 
                    WHEN prev_incidence IS NULL OR prev_incidence = 0 THEN 'stable'
                    -- Se houver um buraco de mais de 1 semana real (salto > 2 no token de 53 semanas), ignoramos a tendência
                    WHEN (continuity_token - prev_token) NOT IN (1, 2) THEN 'stable'
                    WHEN real_incidence > prev_incidence * 1.15 THEN 'up'
                    WHEN real_incidence < prev_incidence * 0.85 THEN 'down'
                    ELSE 'stable'
                END as trend,
                CASE 
                    WHEN total_cases < 5 THEN 1
                    WHEN real_incidence >= 600 THEN 4
                    WHEN real_incidence >= 300 THEN 3
                    WHEN real_incidence >= 100 THEN 2
                    ELSE 1
                END as alert_level
            FROM stats_with_lag
        ");

        DB::statement('CREATE UNIQUE INDEX idx_mv_uf_epi_stats_unique ON mv_uf_epidemic_stats (uf, year, epi_week, disease_type)');

        DB::statement('DROP MATERIALIZED VIEW IF EXISTS mv_national_stats');
        DB::statement('
            CREATE MATERIALIZED VIEW mv_national_stats AS
            SELECT 
                year,
                disease_type,
                SUM(cases) as total_cases,
                MAX(updated_at) as last_sync_at
            FROM epidemic_records
            GROUP BY year, disease_type
        ');

        DB::statement('CREATE UNIQUE INDEX idx_mv_national_stats_unique ON mv_national_stats (year, disease_type)');
    }
};
