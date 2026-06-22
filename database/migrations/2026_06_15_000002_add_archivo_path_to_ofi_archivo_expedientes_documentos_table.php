<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ofi_archivo_expedientes_documentos', function (Blueprint $table) {
            $table->string('archivo_path')->nullable()->after('detalle')
                ->comment('Ruta del archivo en Storage (null si es radicado vinculado)');
        });
    }

    public function down(): void
    {
        Schema::table('ofi_archivo_expedientes_documentos', function (Blueprint $table) {
            $table->dropColumn('archivo_path');
        });
    }
};
