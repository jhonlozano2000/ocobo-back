<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mi_bandeja_temp_grupo_revisores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('grupo_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('cargo_id')->nullable();
            $table->boolean('subio_plantilla')->default(false);
            $table->boolean('descargo_plantilla')->default(false);
            $table->string('estado_tarea', 20)->default('pendiente');
            $table->timestamp('fechor_terminado')->nullable();
            $table->timestamps();

            $table->foreign('grupo_id')->references('id')->on('mi_bandeja_temp')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('mi_bandeja_temp_grupo_aprobadores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('grupo_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('cargo_id')->nullable();
            $table->boolean('subio_plantilla')->default(false);
            $table->boolean('descargo_plantilla')->default(false);
            $table->string('estado_tarea', 20)->default('pendiente');
            $table->timestamp('fechor_terminado')->nullable();
            $table->timestamps();

            $table->foreign('grupo_id')->references('id')->on('mi_bandeja_temp')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        DB::statement('INSERT INTO mi_bandeja_temp_grupo_revisores (grupo_id, user_id, cargo_id, subio_plantilla, descargo_plantilla, estado_tarea, fechor_terminado, created_at, updated_at) SELECT grupo_id, user_id, cargo_id, subio_plantilla, descargo_plantilla, estado_tarea, fechor_terminado, created_at, updated_at FROM mi_bandeja_temp_grupo_responsables');

        Schema::dropIfExists('mi_bandeja_temp_grupo_responsables');
    }

    public function down(): void
    {
        Schema::create('mi_bandeja_temp_grupo_responsables', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('grupo_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('cargo_id')->nullable();
            $table->boolean('es_custodio')->default(false);
            $table->boolean('subio_plantilla')->default(false);
            $table->boolean('descargo_plantilla')->default(false);
            $table->string('estado_tarea', 20)->default('pendiente');
            $table->timestamp('fechor_terminado')->nullable();
            $table->timestamps();

            $table->foreign('grupo_id')->references('id')->on('mi_bandeja_temp')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        DB::statement('INSERT INTO mi_bandeja_temp_grupo_responsables (grupo_id, user_id, cargo_id, es_custodio, subio_plantilla, descargo_plantilla, estado_tarea, fechor_terminado, created_at, updated_at) SELECT grupo_id, user_id, cargo_id, false, subio_plantilla, descargo_plantilla, estado_tarea, fechor_terminado, created_at, updated_at FROM mi_bandeja_temp_grupo_revisores');

        Schema::dropIfExists('mi_bandeja_temp_grupo_aprobadores');
        Schema::dropIfExists('mi_bandeja_temp_grupo_revisores');
    }
};
