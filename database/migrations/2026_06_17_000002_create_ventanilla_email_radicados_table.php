<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ventanilla_email_radicados', function (Blueprint $table) {
            $table->id();

            $table->string('imap_uid')->unique()->comment('UID del correo en IMAP');
            $table->string('imap_folder', 100)->default('INBOX')->comment('Carpeta IMAP');
            $table->string('asunto', 500)->nullable()->comment('Asunto del correo');
            $table->string('remitente_email', 255)->nullable()->comment('Email del remitente');
            $table->string('remitente_nombre', 255)->nullable()->comment('Nombre del remitente');
            $table->dateTime('fecha_correo')->nullable()->comment('Fecha del correo');
            $table->longText('body_text')->nullable()->comment('Contenido texto plano');
            $table->longText('body_html')->nullable()->comment('Contenido HTML');
            $table->boolean('tiene_adjuntos')->default(false)->comment('Tiene archivos adjuntos');
            $table->json('adjuntos_info')->nullable()->comment('Info de adjuntos [{filename, size, mime}]');

            $table->unsignedBigInteger('radicado_id')->nullable()->comment('FK al radicado generado');
            $table->foreign('radicado_id')->references('id')->on('ventanilla_radica_reci');

            $table->enum('estado', ['pendiente', 'radicado', 'respondido', 'error'])->default('pendiente');
            $table->text('error_mensaje')->nullable()->comment('Mensaje de error si falla');
            $table->dateTime('sincronizado_en')->nullable()->comment('Cuándo se sincronizó');
            $table->dateTime('radicado_en')->nullable()->comment('Cuándo se radió');
            $table->dateTime('respondido_en')->nullable()->comment('Cuándo se respondió');

            $table->timestamps();

            $table->index('estado');
            $table->index('fecha_correo');
            $table->index('remitente_email');
            $table->index('sincronizado_en');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ventanilla_email_radicados');
    }
};
