<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ventanilla_pqrs_archivos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ventanilla_pqrs_id')
                ->constrained('ventanilla_pqrs')
                ->onDelete('cascade');
            $table->enum('tipo', ['digital', 'adjunto']);
            $table->string('nombre_original', 255);
            $table->string('nombre_guardado', 255);
            $table->string('path', 500);
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('tamanio')->default(0);
            $table->string('hash_sha256', 64)->nullable();
            $table->foreignId('uploaded_by')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['ventanilla_pqrs_id', 'tipo']);
            $table->index('hash_sha256');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ventanilla_pqrs_archivos');
    }
};
