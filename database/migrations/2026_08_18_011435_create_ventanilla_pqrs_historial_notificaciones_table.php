<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Historial de notificaciones por correo enviadas para un PQRS.
     * Equivalente a ventanilla_radica_historial_notificaciones
     */
    public function up(): void
    {
        Schema::create('ventanilla_pqrs_historial_notificaciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pqrs_id');
            $table->string('tipo', 30)->default('responsable'); // responsable | tercero
            $table->json('destinatarios')->nullable();
            $table->integer('total_enviados')->default(0);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();

            $table->foreign('pqrs_id', 'pqrs_hn_pqrs_fk')
                ->references('id')
                ->on('ventanilla_pqrs')
                ->cascadeOnDelete();

            $table->foreign('user_id', 'pqrs_hn_user_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index('pqrs_id', 'pqrs_hn_pqrs_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ventanilla_pqrs_historial_notificaciones');
    }
};
