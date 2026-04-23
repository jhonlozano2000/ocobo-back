<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('config_varias', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 100)->unique(); // Identificador de la configuración
            $table->string('valor', 255)->nullable(); // Valor de la configuración
            $table->string('descripcion', 255)->nullable(); // Explicación de la configuración
            $table->timestamps();
        });

        // Insertar configuraciones iniciales
        DB::table('config_varias')->insert([
            ['clave' => 'max_tamano_archivo', 'valor' => '20480', 'descripcion' => 'Tamaño máximo de archivos en KB (20MB)'],
            ['clave' => 'tipos_archivos_permitidos', 'valor' => 'pdf, docx, xlsx, xls, txt, csv, jpg, jpeg, png, gif, webp, bmp, svg, mp4, webm, ogg, mov, avi, mkv, mp3, wav, ogg, flac, aac', 'descripcion' => 'Extensiones permitidas para carga de archivos'],
        ]);

        DB::table('config_varias')->insert([
            ['clave' => 'formato_num_radicado_reci', 'valor' => 'YYYYMMDD-#####', 'descripcion' => 'Formato del número de radicado'],
            ['clave' => 'numeracion_unificada', 'valor' => 'true', 'descripcion' => 'Define si la numeración de radicados es unificada o por ventanilla'],
        ]);

        DB::table('config_varias')->insert([
            ['clave' => 'multi_sede', 'valor' => '0', 'descripcion' => 'Configuración de múltiples sedes (0: deshabilitado, 1: habilitado)'],
            ['clave' => 'backups_automaticos', 'valor' => '0', 'descripcion' => 'Habilitar backups automáticos (0: deshabilitado, 1: habilitado)'],
            ['clave' => 'frecuencia_backups', 'valor' => 'Diario', 'descripcion' => 'Intervalo de backups automáticos (Diario, Lunes, Martes, Miercoles, Jueves, Viernes, Sabado, Domingo)'],
            ['clave' => 'fecha_ultimo_backups', 'valor' => 'YYYY-MM-DD', 'descripcion' => 'Fecha del ultimo backups'],
        ]);

        // Insertar configuraciones de la empresa
        DB::table('config_varias')->insert([
            ['clave' => 'nit_empresa', 'valor' => '', 'descripcion' => 'NIT de la empresa'],
            ['clave' => 'razon_social_empresa', 'valor' => '', 'descripcion' => 'Razón social de la empresa'],
            ['clave' => 'divi_poli_id_empresa', 'valor' => '', 'descripcion' => 'ID de la división política de la empresa'],
            ['clave' => 'logo_empresa', 'valor' => '', 'descripcion' => 'Nombre del archivo del logo de la empresa'],
            ['clave' => 'direccion_empresa', 'valor' => '', 'descripcion' => 'Dirección de la empresa'],
            ['clave' => 'telefono_empresa', 'valor' => '', 'descripcion' => 'Teléfono de contacto de la empresa'],
            ['clave' => 'correo_electronico_empresa', 'valor' => '', 'descripcion' => 'Correo electrónico de la empresa'],
            ['clave' => 'web_empresa', 'valor' => '', 'descripcion' => 'Sitio web de la empresa'],
        ]);

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
                'descripcion' => 'Días en zona amarillo (por vencer)'
            ],
            [
                'clave' => 'semaforo_rojo_dias',
                'valor' => '5',
                'descripcion' => 'Días en zona roja (vencido o por vencer hoy)'
            ],
        ]);

        // Insertar configuraciones de correo SMTP
        DB::table('config_varias')->insert([
            ['clave' => 'correo_host', 'valor' => 'smtp.gmail.com', 'descripcion' => 'Servidor SMTP para notificaciones de ventanilla'],
            ['clave' => 'correo_port', 'valor' => '587', 'descripcion' => 'Puerto SMTP para notificaciones de ventanilla'],
            ['clave' => 'correo_username', 'valor' => '', 'descripcion' => 'Usuario SMTP para notificaciones de ventanilla'],
            ['clave' => 'correo_password', 'valor' => '', 'descripcion' => 'Contraseña SMTP para notificaciones de ventanilla'],
            ['clave' => 'correo_from_address', 'valor' => '', 'descripcion' => 'Dirección de correo remitente para notificaciones'],
            ['clave' => 'correo_from_name', 'valor' => 'Radicaciones', 'descripcion' => 'Nombre del remitente para notificaciones'],
            ['clave' => 'correo_encryption', 'valor' => 'tls', 'descripcion' => 'Tipo de encriptación SMTP (tls, ssl)'],
            ['clave' => 'notificar_radicado_al_tercero', 'valor' => '1', 'descripcion' => 'Enviar notificación al tercero cuando se radique un documento recibido'],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('config_varias');
    }
};
