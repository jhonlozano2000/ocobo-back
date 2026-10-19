<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * B06 (diseño de expedientes): el expediente se clasifica en serie y subserie, declara su
     * soporte (electrónico, físico o híbrido) y su nivel de acceso con el fundamento legal cuando
     * no es público (Ley 1712/2014 arts. 18-19; Acuerdo AGN 001/2024).
     *
     * D6: los expedientes existentes no se bloquean; quedan "por completar" hasta que alguien
     * declare su nivel de acceso (y su subserie, si la serie tiene subseries).
     */
    public function up(): void
    {
        $mysql = DB::getDriverName() === 'mysql';

        // `mixto` pasa a llamarse `hibrido` (expediente con parte física y parte electrónica)
        if ($mysql) {
            DB::statement("ALTER TABLE ofi_archivo_expedientes MODIFY soporte ENUM('electronico','fisico','mixto','hibrido') NULL DEFAULT 'electronico' COMMENT 'Declaración de medio'");
        }
        DB::table('ofi_archivo_expedientes')->where('soporte', 'mixto')->update(['soporte' => 'hibrido']);
        DB::table('ofi_archivo_expedientes')->whereNull('soporte')->update(['soporte' => 'electronico']);
        if ($mysql) {
            DB::statement("ALTER TABLE ofi_archivo_expedientes MODIFY soporte ENUM('electronico','fisico','hibrido') NOT NULL DEFAULT 'electronico' COMMENT 'Declaración de medio'");
        }

        Schema::table('ofi_archivo_expedientes', function (Blueprint $table) {
            $table->enum('nivel_acceso', ['publico', 'clasificado', 'reservado'])->nullable()->after('soporte')
                ->comment('Ley 1712/2014; nulo en expedientes anteriores a B06 (por completar)');
            $table->string('fundamento_acceso', 500)->nullable()->after('nivel_acceso')
                ->comment('Norma que clasifica o reserva la información');
            $table->boolean('por_completar')->default(false)->after('fundamento_acceso')->index();
        });

        // El árbol de la TRD dejaba guardar una SubSerie en serie_trd_id: se mueve a su columna
        if ($mysql) {
            DB::statement("
                UPDATE ofi_archivo_expedientes e
                JOIN clasificacion_documental_trd t ON t.id = e.serie_trd_id
                JOIN clasificacion_documental_trd p ON p.id = t.parent AND p.tipo = 'Serie'
                SET e.subserie_trd_id = t.id, e.serie_trd_id = p.id
                WHERE t.tipo = 'SubSerie' AND e.subserie_trd_id IS NULL
            ");
        }

        DB::table('ofi_archivo_expedientes')->update(['por_completar' => true]);
    }

    public function down(): void
    {
        Schema::table('ofi_archivo_expedientes', function (Blueprint $table) {
            $table->dropIndex(['por_completar']);
            $table->dropColumn(['nivel_acceso', 'fundamento_acceso', 'por_completar']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE ofi_archivo_expedientes MODIFY soporte ENUM('electronico','fisico','mixto','hibrido') NOT NULL DEFAULT 'electronico' COMMENT 'Declaración de medio'");
            DB::table('ofi_archivo_expedientes')->where('soporte', 'hibrido')->update(['soporte' => 'mixto']);
            DB::statement("ALTER TABLE ofi_archivo_expedientes MODIFY soporte ENUM('electronico','fisico','mixto') NOT NULL DEFAULT 'electronico' COMMENT 'Declaración de medio'");
        }
    }
};
