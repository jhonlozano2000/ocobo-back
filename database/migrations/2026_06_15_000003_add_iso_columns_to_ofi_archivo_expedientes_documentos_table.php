<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ofi_archivo_expedientes_documentos', function (Blueprint $table) {
            if (! Schema::hasColumn('ofi_archivo_expedientes_documentos', 'hash_sha256')) {
                $table->string('hash_sha256', 64)->nullable()->after('archivo_path')
                    ->comment('SHA-256 del contenido del archivo para garantizar integridad (ISO 27001 A.10.1.2)');
            }

            if (! Schema::hasColumn('ofi_archivo_expedientes_documentos', 'nombre_original')) {
                $table->string('nombre_original', 255)->nullable()->after('hash_sha256')
                    ->comment('Nombre original del archivo tal como lo subió el usuario');
            }

            if (! Schema::hasColumn('ofi_archivo_expedientes_documentos', 'activo')) {
                $table->boolean('activo')->default(true)->after('nombre_original')
                    ->comment('Borrado lógico para preservar trazabilidad del folio (ISO 27001 A.12.4.1)');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ofi_archivo_expedientes_documentos', function (Blueprint $table) {
            $table->dropColumn(['hash_sha256', 'nombre_original', 'activo']);
        });
    }
};
