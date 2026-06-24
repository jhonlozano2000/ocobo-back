<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración para crear las tablas de grupos colaborativos temporales en Mi Bandeja.
 * Incluye tablas para grupos, responsables, firmantes, proyectores y adjuntos.
 */
return new class extends Migration
{
    /**
     * Ejecuta la migración.
     * Crea las tablas necesarias para grupos colaborativos temporales.
     *
     * @return void
     */
    public function up(): void
    {
        // Tabla principal: mi_bandeja_temp
        Schema::create('mi_bandeja_temp', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->unsignedBigInteger('radicado_id');
            $table->enum('radicado_tipo', ['recibido', 'enviado', 'interno']);
            $table->enum('estado', ['borrador', 'activo', 'finalizado', 'archivado'])->default('borrador');
            $table->enum('estado_grupo', ['activo', 'inactivo', 'anulado'])->default('activo');
            $table->unsignedBigInteger('usua_crea_id');
            $table->unsignedBigInteger('usua_crea_plantilla_id')->nullable();
            $table->string('asunto')->nullable();
            $table->json('con_copia')->nullable();
            $table->json('anexos')->nullable();
            $table->boolean('plantilla_cargada')->default(false);
            $table->timestamps();

            $table->foreign('radicado_id')->references('id')->on('ventanilla_radica_reci')->onDelete('cascade');
            $table->foreign('usua_crea_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('usua_crea_plantilla_id')->references('id')->on('users')->onDelete('set null');
            $table->index(['radicado_id', 'radicado_tipo']);
            $table->index('estado_grupo');
        });

        // Responsables del grupo
        Schema::create('mi_bandeja_temp_grupo_responsables', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('grupo_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('cargo_id')->nullable();
            $table->boolean('es_custodio')->default(false);
            $table->boolean('subio_plantilla')->default(false);
            $table->boolean('descargo_plantilla')->default(false);
            $table->timestamp('fechor_terminado')->nullable();
            $table->timestamps();

            $table->foreign('grupo_id')->references('id')->on('mi_bandeja_temp')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('cargo_id')->references('id')->on('calidad_organigrama')->onDelete('set null');
            $table->unique(['grupo_id', 'user_id']);
            $table->index('grupo_id');
        });

        // Firmantes del grupo
        Schema::create('mi_bandeja_temp_grupo_firmantes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('grupo_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('cargo_id')->nullable();
            $table->integer('orden_firma')->default(1);
            $table->boolean('subio_plantilla')->default(false);
            $table->boolean('descargo_plantilla')->default(false);
            $table->timestamp('fechor_terminado')->nullable();
            $table->timestamp('fechor_firmado')->nullable();
            $table->timestamps();

            $table->foreign('grupo_id')->references('id')->on('mi_bandeja_temp')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('cargo_id')->references('id')->on('calidad_organigrama')->onDelete('set null');
            $table->unique(['grupo_id', 'user_id']);
            $table->index('grupo_id');
        });

        // Proyectores del grupo
        Schema::create('mi_bandeja_temp_grupo_proyectores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('grupo_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('cargo_id')->nullable();
            $table->boolean('subio_plantilla')->default(false);
            $table->boolean('descargo_plantilla')->default(false);
            $table->timestamp('fechor_terminado')->nullable();
            $table->timestamps();

            $table->foreign('grupo_id')->references('id')->on('mi_bandeja_temp')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('cargo_id')->references('id')->on('calidad_organigrama')->onDelete('set null');
            $table->unique(['grupo_id', 'user_id']);
            $table->index('grupo_id');
        });

        // Adjuntos del grupo
        Schema::create('mi_bandeja_temp_grupo_archi_adjuntos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('grupo_id');
            $table->string('archivo');
            $table->string('nombre_original');
            $table->string('tipo_mime');
            $table->unsignedBigInteger('peso');
            $table->string('hash_sha256', 64);
            $table->unsignedBigInteger('subido_por');
            $table->timestamps();

            $table->foreign('grupo_id')->references('id')->on('mi_bandeja_temp')->onDelete('cascade');
            $table->foreign('subido_por')->references('id')->on('users')->onDelete('cascade');
            $table->index('grupo_id');
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
        Schema::dropIfExists('mi_bandeja_temp_grupo_archi_adjuntos');
        Schema::dropIfExists('mi_bandeja_temp_grupo_proyectores');
        Schema::dropIfExists('mi_bandeja_temp_grupo_firmantes');
        Schema::dropIfExists('mi_bandeja_temp_grupo_responsables');
        Schema::dropIfExists('mi_bandeja_temp');
    }
};