<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ofi_archivo_expedientes_documentos', function (Blueprint $table) {
            $columns = [
                'tipo_documental' => fn () => $table->string('tipo_documental', 200)->nullable()->after('tipo')->comment('Ej: Resolución, Oficio, Anexo'),
                'orden' => fn () => $table->unsignedSmallInteger('orden')->default(0)->after('numero_folio')->comment('Orden de visualización UI'),
                'fecha_documento' => fn () => $table->date('fecha_documento')->nullable()->after('orden')->comment('Fecha de producción del documento original'),
                'asunto' => fn () => $table->string('asunto', 500)->nullable()->after('fecha_documento'),
                'autor' => fn () => $table->string('autor', 200)->nullable()->after('asunto'),
                'formato_archivo' => fn () => $table->string('formato_archivo', 20)->nullable()->after('autor')->comment('Ej: pdf, docx, xml'),
                'tamano_bytes' => fn () => $table->unsignedBigInteger('tamano_bytes')->nullable()->after('formato_archivo'),
            ];

            foreach ($columns as $name => $adder) {
                if (! Schema::hasColumn('ofi_archivo_expedientes_documentos', $name)) {
                    $adder();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('ofi_archivo_expedientes_documentos', function (Blueprint $table) {
            $table->dropColumn([
                'tipo_documental',
                'orden',
                'fecha_documento',
                'asunto',
                'autor',
                'formato_archivo',
                'tamano_bytes',
            ]);
        });
    }
};
