<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('ventanilla_radica_enviados_responsa', 'ventanilla_radica_enviados_responsable');
    }

    public function down(): void
    {
        Schema::rename('ventanilla_radica_enviados_responsable', 'ventanilla_radica_enviados_responsa');
    }
};
