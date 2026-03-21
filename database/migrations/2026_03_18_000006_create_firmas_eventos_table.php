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
        Schema::create('firmas_eventos', function (Blueprint $table) {
            $table->id();
            
            // Relación Polimórfica (Permite firmar radicados enviados, actas, expedientes, etc)
            $table->morphs('documentable');
            
            $table->unsignedBigInteger('user_id')->comment('Usuario que realiza la firma');
            $table->foreign('user_id')->references('id')->on('users');

            $table->string('hash_original', 64)->comment('Hash SHA-256 del documento ANTES de firmar');
            $table->string('hash_firmado', 64)->comment('Hash SHA-256 del documento DESPUÉS de estampar la firma');
            
            $table->string('otp_utilizado', 10)->comment('El código OTP (One Time Password) validado');
            $table->string('ip_address', 45)->comment('Dirección IP desde donde se firmó');
            $table->string('user_agent', 255)->comment('Navegador y SO del firmante');
            
            $table->timestamp('fecha_firma')->useCurrent();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('firmas_eventos');
    }
};
