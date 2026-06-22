<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ofi_archivo_prestamos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('expediente_id')
                ->comment('Expediente prestado')
                ->constrained('ofi_archivo_expedientes');

            $table->foreignId('solicitante_id')
                ->comment('Usuario que solicita el préstamo')
                ->constrained('users');

            $table->string('dependencia_destino', 200)->nullable()
                ->comment('Dependencia destino del préstamo');

            $table->dateTime('fecha_prestamo')
                ->comment('Fecha y hora del préstamo');

            $table->dateTime('fecha_devolucion_esperada')
                ->comment('Fecha límite de devolución');

            $table->dateTime('fecha_devolucion_real')->nullable()
                ->comment('Fecha real de devolución');

            $table->enum('estado', ['prestado', 'devuelto', 'vencido'])
                ->default('prestado')
                ->comment('Estado del préstamo');

            $table->text('observaciones')->nullable()
                ->comment('Observaciones del préstamo');

            $table->foreignId('usuario_registro_id')
                ->comment('Usuario que registró el préstamo')
                ->constrained('users');

            $table->timestamps();
            $table->softDeletes();

            $table->index('estado', 'idx_prestamo_estado');
            $table->index('fecha_devolucion_esperada', 'idx_prestamo_fecha_dev');
            $table->index(['expediente_id', 'estado'], 'idx_prestamo_exp_estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ofi_archivo_prestamos');
    }
};
