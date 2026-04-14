<?php

namespace App\Helpers;

use App\Models\Configuracion\ConfigVarias;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

/**
 * Helper para configurar el mailer dinámicamente desde config_varias.
 */
class MailConfigHelper
{
    /**
     * Configura el mailer SMTP desde las configuraciones guardadas.
     * Debe llamarse antes de enviar cualquier email.
     */
    public static function configureFromConfigVarias(): void
    {
        try {
            $host = ConfigVarias::getValor('correo_host');
            $port = ConfigVarias::getValor('correo_port', '587');
            $username = ConfigVarias::getValor('correo_username');
            $password = ConfigVarias::getValor('correo_password');
            $fromAddress = ConfigVarias::getValor('correo_from_address');
            $fromName = ConfigVarias::getValor('correo_from_name');
            $encryption = ConfigVarias::getValor('correo_encryption', 'tls');

            // Solo configurar si tenemos host y credenciales
            if (empty($host) || empty($username) || empty($password)) {
                Log::debug('MailConfigHelper: Configuración de correo no completa, usando defaults');
                return;
            }

            // Desencriptar la contraseña si está encriptada
            $decryptedPassword = self::decryptPassword($password);

            // Configurar el mailer smtp
            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.host' => $host,
                'mail.mailers.smtp.port' => (int) $port,
                'mail.mailers.smtp.username' => $username,
                'mail.mailers.smtp.password' => $decryptedPassword,
                'mail.mailers.smtp.encryption' => $encryption === 'ssl' ? 'ssl' : 'tls',
                'mail.mailers.smtp.timeout' => 30,
                'mail.mailers.smtp.local_domain' => null,
            ]);

            // Configurar el from global si está definido
            if (!empty($fromAddress)) {
                config([
                    'mail.from.name' => $fromName ?: $username,
                    'mail.from.address' => $fromAddress,
                ]);
            }

            Log::debug('MailConfigHelper: Configuración de correo aplicada desde config_varias');
        } catch (\Exception $e) {
            Log::warning('MailConfigHelper: Error al configurar correo', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Verifica si la configuración de correo está completa.
     */
    public static function isConfigured(): bool
    {
        $host = ConfigVarias::getValor('correo_host');
        $username = ConfigVarias::getValor('correo_username');
        $password = ConfigVarias::getValor('correo_password');

        return !empty($host) && !empty($username) && !empty($password);
    }

    /**
     * Obtiene la configuración actual (sin contraseña).
     */
    public static function getConfig(): array
    {
        return [
            'host' => ConfigVarias::getValor('correo_host', ''),
            'port' => ConfigVarias::getValor('correo_port', '587'),
            'username' => ConfigVarias::getValor('correo_username', ''),
            'from_address' => ConfigVarias::getValor('correo_from_address', ''),
            'from_name' => ConfigVarias::getValor('correo_from_name', ''),
            'encryption' => ConfigVarias::getValor('correo_encryption', 'tls'),
            'configured' => self::isConfigured(),
        ];
    }

    /**
     * Encripta la contraseña del correo para almacenarla.
     */
    public static function encryptPassword(string $password): string
    {
        return encrypt($password);
    }

    /**
     * Desencripta la contraseña del correo para usarla.
     */
    public static function decryptPassword(?string $encryptedPassword): ?string
    {
        if (empty($encryptedPassword)) {
            return null;
        }

        // Verificar si está encriptada (Laravel encryption usa un formato específico)
        try {
            return decrypt($encryptedPassword);
        } catch (\Exception $e) {
            // Si no se puede desencriptar, intentar retornarla como está
            // (podría ser una contraseña guardada antes de la encriptación)
            return $encryptedPassword;
        }
    }
}
