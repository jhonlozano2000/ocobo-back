<?php

use App\Models\OfiArchivo\OfiArchivoExpedienteDocumento;
use App\Services\OfiArchivo\IndiceExpedienteService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * B10 (diseño de expedientes): el índice electrónico deja de calcularse al vuelo.
     *
     * - `ofi_archivo_expediente_indice`: una fila por folio, escrita al incorporarlo. Cada fila
     *   encadena su huella con la anterior (`hash_cadena`); retirar un folio lo anula, no lo borra
     *   [Acuerdo AGN 001/2024 A.4.3.2.3 num. 4].
     * - `ofi_archivo_expediente_indice_versiones`: el índice congelado y firmado en cada cierre
     *   (XML, PDF y firma electrónica, decisión D2). Una reapertura conserva las versiones.
     */
    public function up(): void
    {
        Schema::create('ofi_archivo_expediente_indice', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expediente_id')->constrained('ofi_archivo_expedientes')->restrictOnDelete();
            $table->foreignId('documento_id')->unique()->constrained('ofi_archivo_expedientes_documentos')->restrictOnDelete();
            $table->unsignedInteger('orden');
            $table->unsignedInteger('numero_folio');
            $table->unsignedBigInteger('tipo_documental_id')->nullable();
            $table->string('tipo_documental', 200);
            $table->string('origen', 255);
            $table->string('asunto', 500)->nullable();
            $table->date('fecha_documento')->nullable();
            $table->dateTime('fecha_incorporacion');
            $table->char('hash_sha256', 64)->nullable();
            $table->string('algoritmo', 20)->default('SHA-256');
            $table->string('formato', 20)->nullable();
            $table->unsignedBigInteger('tamano_bytes')->nullable();
            $table->dateTime('anulado_en')->nullable();
            $table->string('motivo_anulacion', 500)->nullable();
            $table->unsignedBigInteger('anulado_por')->nullable();
            $table->char('hash_anterior', 64)->nullable();
            $table->char('hash_cadena', 64);
            $table->timestamps();

            $table->unique(['expediente_id', 'orden'], 'uk_expediente_indice_orden');
        });

        Schema::create('ofi_archivo_expediente_indice_versiones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expediente_id')->constrained('ofi_archivo_expedientes')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('motivo', 40)->default('cierre');
            $table->unsignedInteger('total_folios');
            $table->char('hash_indice', 64)->comment('Mismo valor que ofi_archivo_expedientes.hash_indice al cerrar');
            $table->char('hash_cadena', 64)->nullable()->comment('Última huella de la cadena del índice');
            $table->longText('xml');
            $table->char('hash_xml', 64);
            $table->binary('pdf');
            $table->char('hash_pdf', 64);
            $table->string('firma_tipo', 30)->comment('electronica_interna | digital_certificado');
            $table->unsignedBigInteger('firmado_por');
            $table->string('firmante', 255);
            $table->dateTime('firmado_en');
            $table->char('firma', 64)->comment('HMAC-SHA256 de la versión con la llave de la aplicación');
            $table->string('codigo_verificacion', 24)->unique('uk_expediente_indice_codigo');
            $table->longText('sello_tiempo')->nullable()->comment('Token RFC 3161 en base64, si se pudo sellar');
            $table->dateTime('sello_tiempo_fecha')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['expediente_id', 'version'], 'uk_expediente_indice_version');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE ofi_archivo_expediente_indice_versiones MODIFY pdf LONGBLOB NOT NULL');
        }

        // Los folios existentes forman la cadena en orden de folio
        $indice = app(IndiceExpedienteService::class);
        OfiArchivoExpedienteDocumento::orderBy('expediente_id')->orderBy('numero_folio')
            ->each(fn (OfiArchivoExpedienteDocumento $doc) => $indice->agregar($doc));
    }

    public function down(): void
    {
        Schema::dropIfExists('ofi_archivo_expediente_indice_versiones');
        Schema::dropIfExists('ofi_archivo_expediente_indice');
    }
};
