<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mi_bandeja_temp', function (Blueprint $table) {
            $table->unsignedBigInteger('plantilla_id')->nullable()->after('usua_crea_plantilla_id');
            $table->longText('respuesta_final')->nullable()->after('plantilla_cargada');
            $table->foreign('plantilla_id')->references('id')->on('ofi_archivo_plantillas_documentos')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('mi_bandeja_temp', function (Blueprint $table) {
            $table->dropForeign(['plantilla_id']);
            $table->dropColumn(['plantilla_id', 'respuesta_final']);
        });
    }
};
