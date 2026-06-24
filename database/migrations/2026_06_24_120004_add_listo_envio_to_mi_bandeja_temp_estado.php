<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE mi_bandeja_temp MODIFY COLUMN estado ENUM('borrador', 'en_curso', 'listo_envio', 'finalizado', 'archivado') NOT NULL DEFAULT 'borrador'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE mi_bandeja_temp MODIFY COLUMN estado ENUM('borrador', 'activo', 'finalizado', 'archivado') NOT NULL DEFAULT 'borrador'");
    }
};
