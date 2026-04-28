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
        Schema::create('ventanilla_radica_reci_comentarios', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('radica_reci_id');
            $table->foreign('radica_reci_id')
                ->references('id')
                ->on('ventanilla_radica_reci')
                ->onDelete('cascade');

            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->text('contenido');
            $table->unsignedBigInteger('parent_id')->nullable()->comment('Para respuestas/comentarios anidados');
            $table->foreign('parent_id')
                ->references('id')
                ->on('ventanilla_radica_reci_comentarios')
                ->onDelete('set null');

            $table->boolean('resuelto')->default(false)->comment('Si el comentario ha sido resuelto/cerrado');
            $table->unsignedBigInteger('resuelto_por')->nullable()->comment('Usuario que resolvió');
            $table->foreign('resuelto_por')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
            $table->timestamp('fecha_resolucion')->nullable();

            $table->text('etiquetas')->nullable()->comment('JSON array de usuarios mencionados @usuario');
            $table->boolean('es_nota_interna')->default(false)->comment('Nota solo visible para usuarios internos');

            $table->timestamps();

            $table->index(['radica_reci_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index('parent_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ventanilla_radica_reci_comentarios');
    }
};