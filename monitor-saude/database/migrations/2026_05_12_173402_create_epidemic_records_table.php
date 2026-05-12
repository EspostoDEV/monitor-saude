<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('epidemic_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('city_id')->constrained()->cascadeOnDelete();
            $table->string('disease_type')->index();
            $table->integer('cases')->unsigned()->default(0);
            $table->integer('epi_week')->index();
            $table->integer('year')->index();
            $table->string('status')->index();
            $table->timestamps();

            $table->unique(['city_id', 'disease_type', 'epi_week', 'year'], 'unique_report_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('epidemic_records');
    }
};
