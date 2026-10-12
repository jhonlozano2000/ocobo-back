<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * M06-10: Inmutabilidad WORM a nivel BD para firmas_eventos.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::unprepared('DROP TRIGGER IF EXISTS prevent_firmas_eventos_update');
            DB::unprepared("
                CREATE TRIGGER prevent_firmas_eventos_update
                BEFORE UPDATE ON firmas_eventos
                FOR EACH ROW
                BEGIN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'Los registros de firmas_eventos son inmutables (WORM) y no pueden modificarse.';
                END;
            ");

            DB::unprepared('DROP TRIGGER IF EXISTS prevent_firmas_eventos_delete');
            DB::unprepared("
                CREATE TRIGGER prevent_firmas_eventos_delete
                BEFORE DELETE ON firmas_eventos
                FOR EACH ROW
                BEGIN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'Los registros de firmas_eventos son inmutables (WORM) y no pueden eliminarse.';
                END;
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::unprepared('DROP TRIGGER IF EXISTS prevent_firmas_eventos_update');
            DB::unprepared('DROP TRIGGER IF EXISTS prevent_firmas_eventos_delete');
        }
    }
};
