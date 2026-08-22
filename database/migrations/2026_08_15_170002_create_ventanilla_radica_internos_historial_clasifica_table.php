<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ventanilla_radica_internos_historial_clasifica', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('radica_interno_id')->comment('ID del radicado interno');
            $table->foreign('radica_interno_id', 'vrihc_int_fk')
                ->references('id')->on('ventanilla_radica_internos')
                ->onDelete('cascade');
            $table->unsignedBigInteger('clasificacion_anterior_id')->nullable();
            $table->foreign('clasificacion_anterior_id', 'vrihc_int_ant_fk')
                ->references('id')->on('clasificacion_documental_trd')
                ->nullOnDelete();
            $table->unsignedBigInteger('clasificacion_nueva_id');
            $table->foreign('clasificacion_nueva_id', 'vrihc_int_nue_fk')
                ->references('id')->on('clasificacion_documental_trd')
                ->restrictOnDelete();
            $table->text('motivo');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->foreign('user_id', 'vrihc_int_usr_fk')
                ->references('id')->on('users')
                ->nullOnDelete();
            $table->timestamps();
            $table->index('radica_interno_id', 'vrihc_int_radicado_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ventanilla_radica_internos_historial_clasifica');
    }
};