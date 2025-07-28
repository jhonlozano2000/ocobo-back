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
        Schema::create('users_sedes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('sede_id');
            $table->boolean('estado')->default(true);
            $table->text('observaciones')->nullable();
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('sede_id')->references('id')->on('config_sedes')->onDelete('cascade');

            // Unique constraint para evitar duplicados
            $table->unique(['user_id', 'sede_id'], 'users_sedes_unique');

            // Indexes para mejorar performance
            $table->index(['user_id', 'estado']);
            $table->index(['sede_id', 'estado']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users_sedes');
    }
};
