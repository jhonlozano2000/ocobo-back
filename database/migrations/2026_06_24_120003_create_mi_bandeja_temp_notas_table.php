<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mi_bandeja_temp_notas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grupo_id')->constrained('mi_bandeja_temp')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->text('contenido');
            $table->timestamp('created_at')->useCurrent();

            $table->index('grupo_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mi_bandeja_temp_notas');
    }
};
