<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Insertar configuraciones de días hábiles y vencimiento
        DB::table('config_varias')->insert([
            [
                'clave' => 'considerar_dias_habiles',
                'valor' => 'true',
                'descripcion' => 'Usar días hábiles para cálculo de vencimientos (excluye fines de semana y festivos)'
            ],
            [
                'clave' => 'dias_vencimiento_predeterminado',
                'valor' => '5',
                'descripcion' => 'Días por defecto para vencimiento de radicados'
            ],
        ]);

        // Insertar configuraciones de semáforo
        DB::table('config_varias')->insert([
            [
                'clave' => 'semaforo_activo',
                'valor' => 'true',
                'descripcion' => 'Habilitar semáforo de vencimientos (verde/amarillo/rojo)'
            ],
            [
                'clave' => 'semaforo_verde_dias',
                'valor' => '2',
                'descripcion' => 'Días en zona verde (vencimiento lejano)'
            ],
            [
                'clave' => 'semaforo_amarillo_dias',
                'valor' => '4',
                'descripcion' => 'Días en zona amarilla (por vencer)'
            ],
            [
                'clave' => 'semaforo_rojo_dias',
                'valor' => '5',
                'descripcion' => 'Días en zona roja (vencido o por vencer hoy)'
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('config_varias')->whereIn('clave', [
            'considerar_dias_habiles',
            'dias_vencimiento_predeterminado',
            'semaforo_activo',
            'semaforo_verde_dias',
            'semaforo_amarillo_dias',
            'semaforo_rojo_dias',
        ])->delete();
    }
};
