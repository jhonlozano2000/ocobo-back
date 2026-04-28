<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ventanilla_radica_reci_respuestas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('radicado_id')->constrained('ventanilla_radica_reci')->onDelete('cascade');
            $table->string('titulo', 500)->nullable();
            $table->longText('contenido')->nullable();
            $table->json('contenido_json')->nullable();
            $table->integer('version')->default(1);
            $table->integer('version_actual')->default(1);
            $table->enum('estado', ['borrador', 'en_edicion', 'finalizado', 'enviado'])->default('borrador');
            
            // Lock system for concurrent editing
            $table->foreignId('user_editando_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('fecha_inicio_edicion')->nullable();
            $table->integer('lock_tiempo')->default(300); // 5 minutes timeout
            
            // Timestamps
            $table->foreignId('user_crea_id')->constrained('users');
            $table->foreignId('user_actualiza_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            
            // Indexes
            $table->index('estado');
            $table->index(['radicado_id', 'estado']);
            $table->index(['user_editando_id', 'fecha_inicio_edicion']);
        });

        // Tabla de versiones/historial
        Schema::create('ventanilla_radica_reci_respuestas_version', function (Blueprint $table) {
            $table->id();
            $table->foreignId('respuesta_id')->constrained('ventanilla_radica_reci_respuestas')->onDelete('cascade');
            $table->integer('version');
            $table->longText('contenido')->nullable();
            $table->json('contenido_json')->nullable();
            $table->foreignId('user_id')->constrained('users');
            $table->text('cambios_resumen')->nullable();
            $table->timestamps();
            
            $table->unique(['respuesta_id', 'version']);
            $table->index('user_id');
        });

        // Tabla de participantes (quién puede editar)
        Schema::create('ventanilla_radica_reci_respuestas_participantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('respuesta_id')->constrained('ventanilla_radica_reci_respuestas')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users');
            $table->enum('rol', ['editor', 'revisor', 'aprobador'])->default('editor');
            $table->boolean('puede_editar')->default(true);
            $table->boolean('puede_revisar')->default(false);
            $table->boolean('puede_aprobar')->default(false);
            $table->timestamp('fecha_asignacion')->useCurrent();
            $table->timestamps();
            
            $table->unique(['respuesta_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ventanilla_radica_reci_respuestas_participantes');
        Schema::dropIfExists('ventanilla_radica_reci_respuestas_version');
        Schema::dropIfExists('ventanilla_radica_reci_respuestas');
    }
};