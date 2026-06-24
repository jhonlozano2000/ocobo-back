<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grupo_colaborativo_audits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('grupo_id');
            $table->unsignedBigInteger('user_id');
            $table->string('accion', 50);
            $table->string('ip_origen', 45);
            $table->text('user_agent')->nullable();
            $table->json('payload_old')->nullable();
            $table->json('payload_new')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('grupo_id')->references('id')->on('mi_bandeja_temp')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users');

            $table->index(['grupo_id', 'created_at'], 'audits_grupo_chrono');
            $table->index(['user_id', 'created_at'], 'audits_user_chrono');
            $table->index(['accion', 'created_at'], 'audits_accion_chrono');
            $table->index('created_at', 'audits_created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grupo_colaborativo_audits');
    }
};
