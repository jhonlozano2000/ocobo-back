<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_conexiones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained()->onDelete('cascade');
            $table->foreignId('nodo_origen_id')->constrained('workflow_nodos')->onDelete('cascade');
            $table->foreignId('nodo_destino_id')->constrained('workflow_nodos')->onDelete('cascade');
            $table->string('etiqueta')->nullable();
            $table->json('condicion_json')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_conexiones');
    }
};
