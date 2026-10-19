<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * B16 (diseño de expedientes §3): la transferencia primaria y la secundaria terminaban las dos
     * en estado 'transferido'. La fase (gestión, central, histórico) dice dónde está el expediente
     * y el estado qué le pasa: tras la primaria queda en central y cerrado; tras la secundaria, en
     * histórico y transferido.
     *
     * Los expedientes actuales toman la fase de su última transferencia aprobada. Los que no tienen
     * ninguna quedan en gestión.
     *
     * En MySQL, un trigger impide reabrir fuera de gestión y que la fase retroceda.
     */
    private const TRIGGER = 'ofi_archivo_expedientes_fase_update';

    public function up(): void
    {
        Schema::table('ofi_archivo_expedientes', function (Blueprint $table) {
            $table->enum('fase', ['gestion', 'central', 'historico'])->default('gestion')->after('estado');
            $table->index(['fase', 'estado'], 'idx_fase_estado_exp');
        });

        $aprobada = fn (string $tipo) => fn ($q) => $q->from('ofi_archivo_transferencias')
            ->whereColumn('ofi_archivo_transferencias.expediente_id', 'ofi_archivo_expedientes.id')
            ->where('tipo', $tipo)->where('estado', 'aprobada');

        DB::table('ofi_archivo_expedientes')->whereExists($aprobada('secundaria'))->update(['fase' => 'historico']);
        DB::table('ofi_archivo_expedientes')->where('fase', 'gestion')->whereExists($aprobada('primaria'))->update(['fase' => 'central']);
        DB::table('ofi_archivo_expedientes')->where('fase', 'central')->where('estado', 'transferido')->update(['estado' => 'cerrado']);

        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        DB::unprepared('DROP TRIGGER IF EXISTS '.self::TRIGGER);
        $orden = fn (string $columna) => "FIELD({$columna}, 'gestion', 'central', 'historico')";
        DB::unprepared('CREATE TRIGGER '.self::TRIGGER." BEFORE UPDATE ON ofi_archivo_expedientes FOR EACH ROW BEGIN
            IF NEW.estado = 'abierto' AND OLD.estado <> 'abierto' AND OLD.fase <> 'gestion' THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Solo un expediente en el archivo de gestión puede reabrirse.';
            END IF;
            IF {$orden('NEW.fase')} < {$orden('OLD.fase')} THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La fase de un expediente no retrocede.';
            END IF;
        END");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::unprepared('DROP TRIGGER IF EXISTS '.self::TRIGGER);
        }
        DB::table('ofi_archivo_expedientes')->where('fase', 'central')->where('estado', 'cerrado')->update(['estado' => 'transferido']);

        Schema::table('ofi_archivo_expedientes', function (Blueprint $table) {
            $table->dropIndex('idx_fase_estado_exp');
            $table->dropColumn('fase');
        });
    }
};
