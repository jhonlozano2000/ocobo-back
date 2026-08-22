<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ventanilla_radica_enviados_historial_clasifica', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('radica_enviados_id')->comment('ID del radicado enviado');
            $table->foreign('radica_enviados_id', 'vrehc_env_fk')
                ->references('id')->on('ventanilla_radica_enviados')
                ->onDelete('cascade');
            $table->unsignedBigInteger('clasificacion_anterior_id')->nullable();
            $table->foreign('clasificacion_anterior_id', 'vrehc_env_ant_fk')
                ->references('id')->on('clasificacion_documental_trd')
                ->nullOnDelete();
            $table->unsignedBigInteger('clasificacion_nueva_id');
            $table->foreign('clasificacion_nueva_id', 'vrehc_env_nue_fk')
                ->references('id')->on('clasificacion_documental_trd')
                ->restrictOnDelete();
            $table->text('motivo');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->foreign('user_id', 'vrehc_env_usr_fk')
                ->references('id')->on('users')
                ->nullOnDelete();
            $table->timestamps();
            $table->index('radica_enviados_id', 'vrehc_env_radicado_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ventanilla_radica_enviados_historial_clasifica');
    }
};