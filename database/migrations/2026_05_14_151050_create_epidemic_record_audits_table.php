<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('epidemic_record_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('epidemic_record_id')->constrained()->onDelete('cascade');
            $table->integer('old_cases');
            $table->integer('new_cases');
            $table->string('reason')->nullable(); // Ex: Sync Correction, Manual Edit
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('epidemic_record_audits');
    }
};
