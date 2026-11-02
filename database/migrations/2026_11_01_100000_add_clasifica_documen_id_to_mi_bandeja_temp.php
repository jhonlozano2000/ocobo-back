<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Clasificación documental (TRD) del grupo colaborativo: se elige al crear el grupo y se precarga al radicar
 * su documento. Nullable porque los grupos existentes no la tienen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mi_bandeja_temp', function (Blueprint $table) {
            $table->foreignId('clasifica_documen_id')->nullable()->after('documento_id')
                ->constrained('clasificacion_documental_trd')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('mi_bandeja_temp', function (Blueprint $table) {
            $table->dropConstrainedForeignId('clasifica_documen_id');
        });
    }
};
