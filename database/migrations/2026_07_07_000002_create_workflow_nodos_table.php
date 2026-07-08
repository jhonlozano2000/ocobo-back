<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_nodos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained()->onDelete('cascade');
            $table->enum('tipo', ['inicio', 'tarea', 'condicion', 'notificacion', 'fin']);
            $table->string('titulo');
            $table->text('descripcion')->nullable();
            $table->float('posicion_x')->default(0);
            $table->float('posicion_y')->default(0);
            $table->json('configuracion_json')->nullable();
            $table->unsignedInteger('orden_ejecucion')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_nodos');
    }
};
