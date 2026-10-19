<?php

use App\Services\OfiArchivo\ConfiguracionArchivo;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Préstamos (diseño de expedientes §3.10): el préstamo pasa a ser una solicitud del expediente o de
 * un tomo, en sala, físico o electrónico, que aprueba el responsable del archivo de la fase. El
 * físico y el de sala registran los folios entregados y devueltos. Los préstamos anteriores quedan
 * como físicos del expediente completo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ofi_archivo_prestamos', function (Blueprint $table) {
            // solicitado → aprobado → prestado → devuelto/vencido; rechazado o cancelado antes de entregar
            $table->string('estado', 20)->default('solicitado')->comment('Estado del préstamo')->change();
            $table->dateTime('fecha_prestamo')->nullable()->comment('Fecha y hora de la entrega (o del inicio del acceso electrónico)')->change();

            $table->foreignId('tomo_id')->nullable()->after('expediente_id')
                ->comment('Tomo prestado; null = expediente completo')
                ->constrained('ofi_archivo_expediente_tomos');
            $table->string('modalidad', 12)->default('fisico')->after('tomo_id')
                ->comment('sala, fisico o electronico');
            $table->string('motivo', 500)->nullable()->after('dependencia_destino');

            $table->foreignId('aprobador_id')->nullable()->after('usuario_registro_id')->constrained('users');
            $table->dateTime('fecha_aprobacion')->nullable()->after('aprobador_id');
            $table->string('motivo_rechazo', 500)->nullable()->after('fecha_aprobacion');
            $table->string('fundamento_autorizacion', 500)->nullable()->after('motivo_rechazo')
                ->comment('Por qué se autoriza prestar un expediente clasificado o reservado');
            $table->boolean('version_publica')->default(false)->after('fundamento_autorizacion')
                ->comment('Se entrega la versión pública del expediente clasificado o reservado');

            $table->unsignedInteger('folios_entrega')->nullable()->after('version_publica');
            $table->foreignId('entregado_por_id')->nullable()->after('folios_entrega')->constrained('users');
            $table->unsignedInteger('folios_devolucion')->nullable()->after('entregado_por_id');
            $table->foreignId('recibido_por_id')->nullable()->after('folios_devolucion')->constrained('users');
            $table->boolean('folios_no_coinciden')->default(false)->after('recibido_por_id');
        });

        if (! DB::table('config_varias')->where('clave', ConfiguracionArchivo::PRESTAMO_PLAZO_MAXIMO)->exists()) {
            DB::table('config_varias')->insert([
                'clave' => ConfiguracionArchivo::PRESTAMO_PLAZO_MAXIMO,
                'valor' => (string) max(1, (int) config('archivo.prestamo_plazo_maximo_dias', 15)),
                'descripcion' => 'Días máximos entre la solicitud de un préstamo de expediente y su devolución',
                'tipo' => 'integer',
                'estado' => true,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('config_varias')->where('clave', ConfiguracionArchivo::PRESTAMO_PLAZO_MAXIMO)->delete();

        Schema::table('ofi_archivo_prestamos', function (Blueprint $table) {
            foreach (['tomo_id', 'aprobador_id', 'entregado_por_id', 'recibido_por_id'] as $fk) {
                $table->dropConstrainedForeignId($fk);
            }
            $table->dropColumn([
                'modalidad', 'motivo', 'fecha_aprobacion', 'motivo_rechazo', 'fundamento_autorizacion',
                'version_publica', 'folios_entrega', 'folios_devolucion', 'folios_no_coinciden',
            ]);
        });

        // Las solicitudes sin entregar no caben en el esquema anterior
        DB::table('ofi_archivo_prestamos')->whereNotIn('estado', ['prestado', 'devuelto', 'vencido'])->delete();
        DB::statement("ALTER TABLE ofi_archivo_prestamos MODIFY estado ENUM('prestado','devuelto','vencido') NOT NULL DEFAULT 'prestado' COMMENT 'Estado del préstamo'");
        DB::statement('ALTER TABLE ofi_archivo_prestamos MODIFY fecha_prestamo DATETIME NOT NULL COMMENT \'Fecha y hora del préstamo\'');
    }
};
