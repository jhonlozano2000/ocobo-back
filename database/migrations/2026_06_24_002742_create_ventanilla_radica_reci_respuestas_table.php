<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración para crear las tablas de respuestas de radicados recibidos en Ventanilla Única.
 * Incluye tablas para respuestas, versiones y participantes.
 */
return new class extends Migration
{
    /**
     * Ejecuta la migración.
     * Crea las tablas necesarias para el sistema de respuestas de radicados recibidos.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('ventanilla_radica_reci_respuestas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('radicado_id');
            $table->string('titulo')->nullable();
            $table->longText('contenido')->nullable();
            $table->json('contenido_json')->nullable();
            $table->integer('version')->default(1);
            $table->integer('version_actual')->default(1);
            $table->enum('estado', ['borrador', 'en_edicion', 'finalizado', 'enviado'])->default('borrador');
            $table->unsignedBigInteger('user_editando_id')->nullable();
            $table->timestamp('fecha_inicio_edicion')->nullable();
            $table->integer('lock_tiempo')->default(300);
            $table->unsignedBigInteger('user_crea_id');
            $table->unsignedBigInteger('user_actualiza_id')->nullable();
            $table->timestamps();

            $table->foreign('radicado_id', 'rr_rad_fk')->references('id')->on('ventanilla_radica_reci')->onDelete('cascade');
            $table->foreign('user_crea_id', 'rr_crea_fk')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('user_editando_id', 'rr_edit_fk')->references('id')->on('users')->onDelete('set null');
            $table->foreign('user_actualiza_id', 'rr_act_fk')->references('id')->on('users')->onDelete('set null');
            $table->index('radicado_id');
        });

        Schema::create('ventanilla_radica_reci_respuestas_version', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('respuesta_id');
            $table->integer('version');
            $table->longText('contenido')->nullable();
            $table->json('contenido_json')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->string('cambios_resumen')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('respuesta_id', 'rrv_resp_fk')->references('id')->on('ventanilla_radica_reci_respuestas')->onDelete('cascade');
            $table->foreign('user_id', 'rrv_user_fk')->references('id')->on('users')->onDelete('cascade');
            $table->index('respuesta_id');
        });

        Schema::create('ventanilla_radica_reci_respuestas_participantes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('respuesta_id');
            $table->unsignedBigInteger('user_id');
            $table->string('rol')->nullable();
            $table->boolean('puede_editar')->default(false);
            $table->boolean('puede_revisar')->default(false);
            $table->boolean('puede_aprobar')->default(false);
            $table->timestamp('fecha_asignacion')->nullable();
            $table->timestamps();

            $table->foreign('respuesta_id', 'rrp_resp_fk')->references('id')->on('ventanilla_radica_reci_respuestas')->onDelete('cascade');
            $table->foreign('user_id', 'rrp_user_fk')->references('id')->on('users')->onDelete('cascade');
            $table->index('respuesta_id');
        });
    }

    /**
     * Revierte la migración.
     * Elimina las tablas en orden inverso para respetar las dependencias.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('ventanilla_radica_reci_respuestas_participantes');
        Schema::dropIfExists('ventanilla_radica_reci_respuestas_version');
        Schema::dropIfExists('ventanilla_radica_reci_respuestas');
    }
};
