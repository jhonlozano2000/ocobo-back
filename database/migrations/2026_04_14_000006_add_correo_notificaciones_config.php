<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $configuraciones = [
            [
                'clave' => 'correo_host',
                'valor' => '',
                'descripcion' => 'Servidor SMTP para notificaciones de ventanilla',
            ],
            [
                'clave' => 'correo_port',
                'valor' => '587',
                'descripcion' => 'Puerto SMTP para notificaciones de ventanilla',
            ],
            [
                'clave' => 'correo_username',
                'valor' => '',
                'descripcion' => 'Usuario SMTP para notificaciones de ventanilla',
            ],
            [
                'clave' => 'correo_password',
                'valor' => '',
                'descripcion' => 'Contraseña SMTP para notificaciones de ventanilla',
            ],
            [
                'clave' => 'correo_from_address',
                'valor' => '',
                'descripcion' => 'Dirección de correo remitente para notificaciones',
            ],
            [
                'clave' => 'correo_from_name',
                'valor' => '',
                'descripcion' => 'Nombre del remitente para notificaciones',
            ],
            [
                'clave' => 'correo_encryption',
                'valor' => 'tls',
                'descripcion' => 'Tipo de encriptación SMTP (tls, ssl)',
            ],
        ];

        foreach ($configuraciones as $config) {
            $existe = DB::table('config_varias')->where('clave', $config['clave'])->exists();
            if (!$existe) {
                DB::table('config_varias')->insert($config);
            }
        }
    }

    public function down(): void
    {
        $claves = [
            'correo_host',
            'correo_port',
            'correo_username',
            'correo_password',
            'correo_from_address',
            'correo_from_name',
            'correo_encryption',
        ];

        DB::table('config_varias')->whereIn('clave', $claves)->delete();
    }
};
