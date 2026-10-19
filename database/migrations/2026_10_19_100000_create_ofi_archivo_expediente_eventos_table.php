<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * B12 (diseño de expedientes): historial del expediente de solo inserción. Cada evento
     * encadena la huella del anterior del mismo expediente (hash_anterior) y la BD rechaza
     * cualquier UPDATE o DELETE (Acuerdo AGN 001/2024, ISO 15489 trazabilidad, ISO 27001 A.8.15).
     */
    public function up(): void
    {
        Schema::create('ofi_archivo_expediente_eventos', function (Blueprint $table) {
            $table->id();
            // Sin cascada: un expediente con historial no puede borrarse físicamente
            $table->foreignId('expediente_id')->constrained('ofi_archivo_expedientes')->restrictOnDelete();
            $table->unsignedInteger('secuencia');
            $table->string('accion', 40);
            $table->longText('detalle')->nullable();
            // Sin FK: un SET NULL en cascada alteraría el evento sin pasar por el trigger
            $table->unsignedBigInteger('usuario_id')->nullable()->index();
            $table->string('ip', 45)->nullable();
            $table->dateTime('ocurrido_en');
            $table->char('hash_anterior', 64)->nullable();
            $table->char('hash', 64);

            $table->unique(['expediente_id', 'secuencia'], 'uk_expediente_evento_secuencia');
            $table->index(['accion', 'ocurrido_en']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::unprepared('DROP TRIGGER IF EXISTS prevent_ofi_archivo_expediente_eventos_update');
            DB::unprepared("
                CREATE TRIGGER prevent_ofi_archivo_expediente_eventos_update
                BEFORE UPDATE ON ofi_archivo_expediente_eventos
                FOR EACH ROW
                BEGIN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'El historial del expediente es inmutable y no puede modificarse.';
                END;
            ");

            DB::unprepared('DROP TRIGGER IF EXISTS prevent_ofi_archivo_expediente_eventos_delete');
            DB::unprepared("
                CREATE TRIGGER prevent_ofi_archivo_expediente_eventos_delete
                BEFORE DELETE ON ofi_archivo_expediente_eventos
                FOR EACH ROW
                BEGIN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'El historial del expediente es inmutable y no puede eliminarse.';
                END;
            ");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::unprepared('DROP TRIGGER IF EXISTS prevent_ofi_archivo_expediente_eventos_update');
            DB::unprepared('DROP TRIGGER IF EXISTS prevent_ofi_archivo_expediente_eventos_delete');
        }
        Schema::dropIfExists('ofi_archivo_expediente_eventos');
    }
};
