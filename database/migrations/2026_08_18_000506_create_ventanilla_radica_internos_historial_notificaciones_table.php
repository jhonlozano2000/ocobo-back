<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Historial de notificaciones por correo enviadas para radicados internos.
     */
    public function up(): void
    {
        Schema::create('ventanilla_radica_internos_historial_notificaciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('radicado_id');
            $table->string('tipo', 30)->default('interno'); // interno | remitente | destinatario
            $table->json('destinatarios')->nullable();
            $table->integer('total_enviados')->default(0);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();

            $table->foreign('radicado_id', 'vrhin_radicado_fk')
                ->references('id')
                ->on('ventanilla_radica_internos')
                ->cascadeOnDelete();

            $table->foreign('user_id', 'vrhin_user_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index('radicado_id', 'vrhin_radicado_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ventanilla_radica_internos_historial_notificaciones');
    }
};
