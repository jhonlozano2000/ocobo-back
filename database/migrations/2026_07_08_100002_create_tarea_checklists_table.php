<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tarea_checklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tarea_id')->constrained('workflow_tareas')->cascadeOnDelete();
            $table->string('item_descripcion', 500);
            $table->boolean('esta_completado')->default(false);
            $table->unsignedInteger('orden')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tarea_checklists');
    }
};
