<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Añade soft deletes a la entidad raíz de Workflows (ISO 27001 A.12.1.3:
 * control de cambios — los flujos no deben borrarse físicamente si tienen
 * historial de instancias/auditoría asociado).
 *
 * @author Jhon Javer Lozano Arce
 * @date 2026-08-26
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflows', function (Blueprint $table) {
            $table->softDeletes()->after('updated_at');
        });
    }

    public function down(): void
    {
        Schema::table('workflows', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
