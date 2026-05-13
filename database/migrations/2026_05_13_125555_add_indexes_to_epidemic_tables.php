<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('epidemic_records', function (Blueprint $table) {
            // Índice para busca e ordenação rápida (Latest record + Aggregation)
            $table->index(['city_id', 'year', 'disease_type'], 'idx_city_year_disease');
            $table->index(['year', 'epi_week'], 'idx_year_week');
        });

        Schema::table('cities', function (Blueprint $table) {
            // Índice para drill-down por estado
            $table->index('uf', 'idx_cities_uf');
        });
    }

    public function down(): void
    {
        Schema::table('epidemic_records', function (Blueprint $table) {
            $table->dropIndex('idx_city_year_disease');
            $table->dropIndex('idx_year_week');
        });

        Schema::table('cities', function (Blueprint $table) {
            $table->dropIndex('idx_cities_uf');
        });
    }
};
