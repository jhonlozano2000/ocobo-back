<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('mi_bandeja_temp_notas_lecturas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grupo_id')->constrained('mi_bandeja_temp')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('ultima_lectura_at')->useCurrent();
            $table->timestamps();

            $table->unique(['grupo_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mi_bandeja_temp_notas_lecturas');
    }
};
