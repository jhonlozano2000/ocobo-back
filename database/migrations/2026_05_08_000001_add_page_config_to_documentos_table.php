<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mi_bandeja_temp_reci_documentos', function (Blueprint $table) {
            $table->enum('tamano_papel', ['a4', 'carta', 'legal', 'oficio'])->default('a4')->after('es_publico');
            $table->enum('orientacion', ['vertical', 'horizontal'])->default('vertical')->after('tamano_papel');
            $table->json('margenes')->nullable()->after('orientacion');
            $table->json('configuracion_columnas')->nullable()->after('margenes');
            $table->json('configuracion_header')->nullable()->after('configuracion_columnas');
            $table->json('configuracion_footer')->nullable()->after('configuracion_header');
        });
    }

    public function down(): void
    {
        Schema::table('mi_bandeja_temp_reci_documentos', function (Blueprint $table) {
            $table->dropColumn([
                'tamano_papel',
                'orientacion',
                'margenes',
                'configuracion_columnas',
                'configuracion_header',
                'configuracion_footer',
            ]);
        });
    }
};
