<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('mi_disco_archivos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 255);
            $table->string('nombre_original', 255);
            $table->foreignId('carpeta_id')->nullable()->constrained('mi_disco_carpetas')->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('ruta_almacenamiento', 500);
            $table->unsignedBigInteger('peso')->default(0);
            $table->string('mime_type', 100)->nullable();
            $table->string('hash_sha256', 64)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'carpeta_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mi_disco_archivos');
    }
};
