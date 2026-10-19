<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * B17 (diseño de expedientes §3.7): la transferencia se hace por lote de una dependencia, con
     * FUID al enviar, cotejo ítem por ítem al recibir y acta de entrega firmada por las dos partes.
     * Mientras el lote está en camino, sus expedientes quedan 'en_transferencia'.
     * `ofi_archivo_transferencias` pasa a ser el detalle del lote; las filas anteriores quedan sin lote.
     */
    public function up(): void
    {
        Schema::create('ofi_archivo_transferencia_lotes', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 30)->unique()->comment('TP-2026-0001 (primaria) o TS-2026-0001 (secundaria)');
            $table->enum('tipo', ['primaria', 'secundaria']);
            $table->unsignedBigInteger('dependencia_id')->comment('Oficina productora de los expedientes');
            $table->string('dependencia_cod', 50)->nullable();
            $table->string('dependencia_nom', 255)->nullable();
            $table->string('origen', 200);
            $table->string('destino', 200);
            $table->enum('estado', ['enviado', 'recibido', 'devuelto'])->default('enviado');
            $table->text('observaciones')->nullable();
            $table->text('observaciones_recepcion')->nullable();

            $table->foreignId('usuario_envia_id')->constrained('users');
            $table->string('firmante_envio', 255);
            $table->dateTime('fecha_envio');
            $table->char('firma_envio', 64)->comment('HMAC de quien envía sobre el FUID');
            $table->foreignId('usuario_recibe_id')->nullable()->constrained('users');
            $table->string('firmante_recepcion', 255)->nullable();
            $table->dateTime('fecha_recepcion')->nullable();
            $table->char('firma_recepcion', 64)->nullable()->comment('HMAC de quien recibe sobre el acta');

            $table->binary('fuid');
            $table->char('hash_fuid', 64);
            $table->binary('acta')->nullable();
            $table->char('hash_acta', 64)->nullable();
            $table->string('codigo_verificacion', 30)->unique();
            $table->timestamps();

            $table->index(['estado', 'tipo'], 'idx_lote_estado_tipo');
            $table->index('dependencia_id', 'idx_lote_dependencia');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE ofi_archivo_transferencia_lotes MODIFY fuid LONGBLOB NOT NULL, MODIFY acta LONGBLOB NULL');
            DB::statement("ALTER TABLE ofi_archivo_expedientes MODIFY estado ENUM('abierto','cerrado','en_transferencia','transferido','eliminado') NOT NULL DEFAULT 'abierto'");
        }

        Schema::table('ofi_archivo_transferencias', function (Blueprint $table) {
            $table->foreignId('lote_id')->nullable()->after('id')->constrained('ofi_archivo_transferencia_lotes');
            $table->enum('cotejo', ['conforme', 'no_conforme'])->nullable()->after('estado');
            $table->string('observacion_cotejo', 1000)->nullable()->after('cotejo');
        });
    }

    public function down(): void
    {
        Schema::table('ofi_archivo_transferencias', function (Blueprint $table) {
            $table->dropConstrainedForeignId('lote_id');
            $table->dropColumn(['cotejo', 'observacion_cotejo']);
        });

        if (DB::getDriverName() === 'mysql') {
            // Un lote en camino vuelve a gestión antes de quitar el estado
            DB::table('ofi_archivo_expedientes')->where('estado', 'en_transferencia')->update(['estado' => 'cerrado']);
            DB::statement("ALTER TABLE ofi_archivo_expedientes MODIFY estado ENUM('abierto','cerrado','transferido','eliminado') NOT NULL DEFAULT 'abierto'");
        }

        Schema::dropIfExists('ofi_archivo_transferencia_lotes');
    }
};
