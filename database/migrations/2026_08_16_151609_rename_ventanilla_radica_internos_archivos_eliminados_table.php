<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('ventanilla_radica_internos_archi_elimi', 'ventanilla_radica_internos_archivos_eliminados');
    }

    public function down(): void
    {
        Schema::rename('ventanilla_radica_internos_archivos_eliminados', 'ventanilla_radica_internos_archi_elimi');
    }
};