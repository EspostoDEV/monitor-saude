<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_logs', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->string('session_id')->index(); // Para agrupar logs de uma mesma rodada
            $blueprint->string('disease');
            $blueprint->string('uf')->nullable();
            $blueprint->string('level')->default('info'); // info, success, error
            $blueprint->text('message');
            $blueprint->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_logs');
    }
};
