<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B24 (diseño de expedientes §2.1): el expediente guarda los terceros relacionados con su rol
 * (titular, solicitante, contratista...). No es una carpeta por tercero: el expediente sigue
 * clasificado por su serie TRD.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ofi_archivo_expediente_terceros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expediente_id')->constrained('ofi_archivo_expedientes')->cascadeOnDelete();
            $table->foreignId('tercero_id')->constrained('gestion_terceros')->restrictOnDelete();
            $table->string('rol', 30);
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['expediente_id', 'tercero_id', 'rol'], 'ofi_exp_terceros_unico');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ofi_archivo_expediente_terceros');
    }
};
