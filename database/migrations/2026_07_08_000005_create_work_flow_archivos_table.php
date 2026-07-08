<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_flow_archivos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained('workflows')->cascadeOnDelete();
            $table->nullableMorphs('archivable');
            $table->string('nombre_original', 255);
            $table->string('nombre_almacenado', 255);
            $table->string('ruta_almacenada', 500);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('peso_bytes');
            $table->string('hash_sha256', 64);
            $table->string('disk', 50)->default('workflows_archivos');
            $table->string('categoria', 50)->default('adjunto')
                ->comment('adjunto, evidencia, instruccion, resultado');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('categoria');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_flow_archivos');
    }
};
