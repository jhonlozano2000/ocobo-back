<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ventanilla_radica_historial_clasificacion_documental')) {
            Schema::rename('ventanilla_radica_historial_clasificacion_documental', 'ventanilla_radica_reci_historial_clasifica');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ventanilla_radica_reci_historial_clasifica')) {
            Schema::rename('ventanilla_radica_reci_historial_clasifica', 'ventanilla_radica_historial_clasificacion_documental');
        }
    }
};