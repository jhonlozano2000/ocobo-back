<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Preferencias de notificación (M16-19):
 * - Nueva categoría «Vencimientos próximos» con sus tres canales. El correo de vencimiento ya se enviaba
 *   a todos, así que queda activo por defecto.
 * - El correo de «Nuevas para ti» empieza a enviarse: queda apagado por defecto. Las filas existentes se
 *   apagan porque hasta ahora la casilla no tenía efecto (nadie lo eligió a conciencia).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users_notification_settings', function (Blueprint $table) {
            $table->boolean('vencimientos')->default(true)->after('new_device_linked_browser');
            $table->boolean('vencimientos_email')->default(true)->after('vencimientos');
            $table->boolean('vencimientos_browser')->default(true)->after('vencimientos_email');
            $table->boolean('new_for_you_email')->default(false)->change();
        });

        DB::table('users_notification_settings')->update(['new_for_you_email' => false]);
    }

    public function down(): void
    {
        Schema::table('users_notification_settings', function (Blueprint $table) {
            $table->dropColumn(['vencimientos', 'vencimientos_email', 'vencimientos_browser']);
            $table->boolean('new_for_you_email')->default(true)->change();
        });
    }
};
