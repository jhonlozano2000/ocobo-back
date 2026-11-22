<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mi_bandeja_temp_reci_contenido', fn (Blueprint $table) => $table->json('campos_manuales')->nullable());
    }

    public function down(): void
    {
        Schema::table('mi_bandeja_temp_reci_contenido', fn (Blueprint $table) => $table->dropColumn('campos_manuales'));
    }
};
