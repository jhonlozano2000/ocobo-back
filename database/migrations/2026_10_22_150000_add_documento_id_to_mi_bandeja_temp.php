<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vincula un grupo colaborativo con el documento de Mis Documentos (editor en línea) que el grupo trabaja.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mi_bandeja_temp', function (Blueprint $table) {
            $table->foreignId('documento_id')->nullable()->after('plantilla_id')
                ->constrained('mi_bandeja_temp_reci_documentos')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('mi_bandeja_temp', function (Blueprint $table) {
            $table->dropConstrainedForeignId('documento_id');
        });
    }
};
