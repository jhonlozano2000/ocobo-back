<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Préstamo electrónico (diseño de expedientes §3.10): el solicitante consulta los documentos del
 * expediente mientras dure el préstamo y cada consulta o descarga queda registrada. En la versión
 * pública de un expediente clasificado o reservado solo se ven los documentos que autorizó quien
 * aprobó.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ofi_archivo_prestamos', function (Blueprint $table) {
            $table->json('documentos_autorizados')->nullable()->after('version_publica')
                ->comment('Documentos del expediente que se pueden consultar; null = todos');
        });

        Schema::create('ofi_archivo_prestamo_accesos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prestamo_id')->constrained('ofi_archivo_prestamos');
            $table->foreignId('documento_id')->constrained('ofi_archivo_expedientes_documentos');
            $table->foreignId('user_id')->constrained('users');
            $table->string('accion', 10)->comment('ver o descargar');
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['prestamo_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ofi_archivo_prestamo_accesos');

        Schema::table('ofi_archivo_prestamos', function (Blueprint $table) {
            $table->dropColumn('documentos_autorizados');
        });
    }
};
