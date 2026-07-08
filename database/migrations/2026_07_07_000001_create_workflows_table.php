<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflows', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->enum('estado', ['borrador', 'activo', 'inactivo', 'archivado'])->default('borrador');
            $table->json('configuracion_general')->nullable();
            $table->unsignedBigInteger('usuario_crea_id');
            $table->foreign('usuario_crea_id')->references('id')->on('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflows');
    }
};
