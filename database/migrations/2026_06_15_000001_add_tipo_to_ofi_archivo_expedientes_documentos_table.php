<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ofi_archivo_expedientes_documentos', function (Blueprint $table) {
            $table->enum('tipo', ['tipo_documental', 'expediente_completo'])
                ->default('tipo_documental')
                ->after('expediente_id')
                ->comment('tipo_documental = archivo por tipo TRD, expediente_completo = archivo consolidado');
        });
    }

    public function down(): void
    {
        Schema::table('ofi_archivo_expedientes_documentos', function (Blueprint $table) {
            $table->dropColumn('tipo');
        });
    }
};
