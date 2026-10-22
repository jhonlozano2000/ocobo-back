<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Firma con OTP en Mis Documentos: los eventos de firma son inmutables (WORM), así que al retirar las firmas
 * para volver a editar se anota cuándo y quién; las firmas anteriores quedan como historial, ya no vigentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mi_bandeja_temp_reci_documentos', function (Blueprint $table) {
            $table->timestamp('firmas_retiradas_en')->nullable()->after('estado');
            $table->unsignedBigInteger('firmas_retiradas_por')->nullable()->after('firmas_retiradas_en');
        });
    }

    public function down(): void
    {
        Schema::table('mi_bandeja_temp_reci_documentos', function (Blueprint $table) {
            $table->dropColumn(['firmas_retiradas_en', 'firmas_retiradas_por']);
        });
    }
};
