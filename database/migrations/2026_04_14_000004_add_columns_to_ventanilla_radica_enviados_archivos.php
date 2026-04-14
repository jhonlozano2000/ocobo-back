<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventanilla_radica_enviados_archivos', function (Blueprint $table) {
            if (!Schema::hasColumn('ventanilla_radica_enviados_archivos', 'archivo_tipo')) {
                $table->string('archivo_tipo', 100)->nullable()->after('archivo')->comment('MIME type del anexo');
            }
            if (!Schema::hasColumn('ventanilla_radica_enviados_archivos', 'archivo_peso')) {
                $table->unsignedBigInteger('archivo_peso')->nullable()->after('archivo_tipo')->comment('Peso en bytes del anexo');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ventanilla_radica_enviados_archivos', function (Blueprint $table) {
            $table->dropColumn(['archivo_tipo', 'archivo_peso']);
        });
    }
};
