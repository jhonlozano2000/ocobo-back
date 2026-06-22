<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $configuraciones = [
            ['clave' => 'correo_imap_host', 'valor' => 'imap.gmail.com', 'descripcion' => 'Servidor IMAP para recepción de correos'],
            ['clave' => 'correo_imap_port', 'valor' => '993', 'descripcion' => 'Puerto IMAP para recepción de correos'],
            ['clave' => 'correo_imap_encryption', 'valor' => 'ssl', 'descripcion' => 'Tipo de encriptación IMAP (ssl, tls)'],
        ];

        foreach ($configuraciones as $config) {
            $existe = DB::table('config_varias')->where('clave', $config['clave'])->exists();
            if (! $existe) {
                DB::table('config_varias')->insert($config);
            }
        }
    }

    public function down(): void
    {
        $claves = ['correo_imap_host', 'correo_imap_port', 'correo_imap_encryption'];
        DB::table('config_varias')->whereIn('clave', $claves)->delete();
    }
};
