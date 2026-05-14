<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_sessions', function (Blueprint $create) {
            $create->id();
            $create->string('session_id')->unique();
            $create->string('disease');
            $create->integer('total_cities')->default(0);
            $create->integer('processed_cities')->default(0);
            $create->integer('total_records_found')->default(0);
            $create->enum('status', ['pending', 'running', 'finished', 'failed'])->default('pending');
            $create->text('last_error')->nullable();
            $create->timestamp('completed_at')->nullable();
            $create->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_sessions');
    }
};
