<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // mi_bandeja_temp_grupo_firmantes
        Schema::create('mi_bandeja_temp_grupo_firmantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grupo_id')->constrained('mi_bandeja_temp')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('cargo_id')->nullable()->constrained('calidad_organigrama')->onDelete('set null');
            $table->unsignedInteger('orden_firma')->default(1);
            $table->boolean('subio_plantilla')->default(false);
            $table->boolean('descargo_plantilla')->default(false);
            $table->timestamp('fechor_terminado')->nullable();
            $table->timestamp('fechor_firmado')->nullable();
            $table->timestamps();
        });

        // mi_bandeja_temp_grupo_proyectores
        Schema::create('mi_bandeja_temp_grupo_proyectores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grupo_id')->constrained('mi_bandeja_temp')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('cargo_id')->nullable()->constrained('calidad_organigrama')->onDelete('set null');
            $table->boolean('subio_plantilla')->default(false);
            $table->boolean('descargo_plantilla')->default(false);
            $table->timestamp('fechor_terminado')->nullable();
            $table->timestamps();
        });

        // mi_bandeja_temp_grupo_archi_adjuntos
        Schema::create('mi_bandeja_temp_grupo_archi_adjuntos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grupo_id')->constrained('mi_bandeja_temp')->onDelete('cascade');
            $table->string('archivo');
            $table->string('nombre_original');
            $table->string('tipo_mime');
            $table->unsignedBigInteger('peso');
            $table->string('hash_sha256', 64)->nullable();
            $table->foreignId('subido_por')->constrained('users')->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mi_bandeja_temp_grupo_archi_adjuntos');
        Schema::dropIfExists('mi_bandeja_temp_grupo_proyectores');
        Schema::dropIfExists('mi_bandeja_temp_grupo_firmantes');
    }
};
