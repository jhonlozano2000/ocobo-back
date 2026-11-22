<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Separate recipient identities from collaborators and their access rights.
     * Each pivot has a real foreign key, without ambiguous polymorphic IDs.
     * Schema errors propagate: MySQL DDL cannot be safely wrapped in a rollback.
     */
    public function up(): void
    {
        Schema::create('documento_destinatarios_internos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('documento_id')->constrained('mi_bandeja_temp_reci_documentos')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('orden')->default(0);
            $table->timestamps();
            $table->unique(['documento_id', 'user_id'], 'doc_dest_interno_unique');
        });

        Schema::create('documento_destinatarios_externos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('documento_id')->constrained('mi_bandeja_temp_reci_documentos')->cascadeOnDelete();
            $table->foreignId('tercero_id')->constrained('gestion_terceros')->restrictOnDelete();
            $table->unsignedInteger('orden')->default(0);
            $table->timestamps();
            $table->unique(['documento_id', 'tercero_id'], 'doc_dest_externo_unique');
        });
    }

    /** Remove the recipient pivots before their referenced tables. */
    public function down(): void
    {
        Schema::dropIfExists('documento_destinatarios_externos');
        Schema::dropIfExists('documento_destinatarios_internos');
    }
};
