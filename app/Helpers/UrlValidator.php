<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

/**
 * Helper para Validación de URLs y Prevención de SSRF
 *
 * OWASP A10:2021 - Server-Side Request Forgery
 *
 * Previene que la aplicación realice solicitudes a:
 * - URLs internas (localhost, 127.0.0.1, redes privadas)
 * - Metadatos de cloud (AWS, GCP, Azure)
 * - Direcciones IP blacklistadas
 */
class UrlValidator
{
    /**
     * Redes privadas que no deben ser accesibles
     */
    private const BLOCKED_IP_PATTERNS = [
        '/^127\.\d{1,3}\.\d{1,3}\.\d{1,3}$/',  // Localhost
        '/^10\.\d{1,3}\.\d{1,3}\.\d{1,3}$/',  // 10.x.x.x
        '/^172\.(1[6-9]|2\d|3[0-1])\.\d{1,3}\.\d{1,3}$/',  // 172.16-31.x.x
        '/^192\.168\.\d{1,3}\.\d{1,3}$/',  // 192.168.x.x
        '/^0\.\d{1,3}\.\d{1,3}\.\d{1,3}$/',  // 0.x.x.x
        '/^169\.254\.169\.254$/',  // AWS/GCP/Azure metadata
        '/^metadata\.googlecompute\.internal$/',  // GCP metadata
    ];

    /**
     * Hostnames que no deben ser accesibles
     */
    private const BLOCKED_HOSTS = [
        'localhost',
        'localhost.localdomain',
        'metadata.googlecompute.internal',
        'metadata.google.internal',
        '169.254.169.254',
        'metadata.azure.com',
        'cloudflare-metadata.com',
    ];

    /**
     * Puertos sensibles que deben ser bloqueados
     */
    private const BLOCKED_PORTS = [
        22,   // SSH
        23,   // Telnet
        3306, // MySQL
        5432, // PostgreSQL
        6379, // Redis
        27017, // MongoDB
    ];

    /**
     * Valida que una URL sea segura para realizar solicitudes
     *
     * @param string $url
     * @return array ['valido' => bool, 'error' => string|null]
     */
    public static function validarUrl(string $url): array
    {
        // Verificar que tenga un esquema
        if (!preg_match('/^https?:\/\//', $url)) {
            return ['valido' => false, 'error' => 'URL debe comenzar con http:// o https://'];
        }

        // Parsear la URL
        $parsed = parse_url($url);

        if ($parsed === false || empty($parsed['host'])) {
            return ['valido' => false, 'error' => 'URL inválida'];
        }

        $host = $parsed['host'];
        $port = $parsed['port'] ?? ($parsed['scheme'] === 'https' ? 443 : 80);

        // Verificar si es una IP bloqueada
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (self::esIpBloqueada($host)) {
                Log::warning('SSRF: URL bloqueada - IP interna', ['url' => $url, 'ip' => $host]);
                return ['valido' => false, 'error' => 'No se permiten URLs a direcciones IP internas'];
            }
        }

        // Verificar si el hostname está bloqueado
        if (self::esHostBloqueado($host)) {
            Log::warning('SSRF: URL bloqueada - Host bloqueado', ['url' => $url, 'host' => $host]);
            return ['valido' => false, 'error' => 'Este hostname no está permitido'];
        }

        // Verificar puerto bloqueado
        if (self::esPuertoBloqueado($port)) {
            Log::warning('SSRF: Puerto bloqueado', ['url' => $url, 'port' => $port]);
            return ['valido' => false, 'error' => 'Puerto no permitido'];
        }

        // Verificar que no apunte a red privada usando DNS
        try {
            $resolvedIps = self::resolverHost($host);
            foreach ($resolvedIps as $ip) {
                if (self::esIpBloqueada($ip)) {
                    Log::warning('SSRF: DNS resuelve a IP bloqueada', ['url' => $url, 'ip' => $ip]);
                    return ['valido' => false, 'error' => 'El hostname resuelve a una dirección IP no permitida'];
                }
            }
        } catch (\Exception $e) {
            // Si no se puede resolver, permitir con precaución
            Log::warning('SSRF: No se pudo resolver hostname', ['url' => $url, 'error' => $e->getMessage()]);
        }

        return ['valido' => true, 'error' => null];
    }

    /**
     * Valida que una URL sea segura antes de realizar una solicitud HTTP
     *
     * @param string $url
     * @throws \InvalidArgumentException
     */
    public static function validarYSolicitar(string $url): void
    {
        $resultado = self::validarUrl($url);

        if (!$resultado['valido']) {
            throw new \InvalidArgumentException($resultado['error']);
        }
    }

    /**
     * Verifica si una IP está en las listas de bloqueo
     */
    private static function esIpBloqueada(string $ip): bool
    {
        foreach (self::BLOCKED_IP_PATTERNS as $pattern) {
            if (preg_match($pattern, $ip)) {
                return true;
            }
        }

        // Verificar IPv6 localhost
        if ($ip === '::1' || $ip === '::') {
            return true;
        }

        return false;
    }

    /**
     * Verifica si un hostname está bloqueado
     */
    private static function esHostBloqueado(string $host): bool
    {
        $hostLower = strtolower($host);

        foreach (self::BLOCKED_HOSTS as $blockedHost) {
            if ($hostLower === strtolower($blockedHost) || str_ends_with($hostLower, '.' . strtolower($blockedHost))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Verifica si un puerto está bloqueado
     */
    private static function esPuertoBloqueado(int $port): bool
    {
        return in_array($port, self::BLOCKED_PORTS);
    }

    /**
     * Resuelve un hostname a IPs
     *
     * @return array
     */
    private static function resolverHost(string $host): array
    {
        $records = dns_get_record($host, DNS_A);

        if ($records === false) {
            return [];
        }

        return array_column($records, 'ip');
    }

    /**
     * Realiza una solicitud HTTP validando la URL primero
     *
     * @param string $method
     * @param string $url
     * @param array $options
     * @return \Illuminate\Http\Client\Response
     * @throws \InvalidArgumentException
     */
    public static function httpRequest(string $method, string $url, array $options = [])
    {
        self::validarYSolicitar($url);

        return Http::timeout(30)->retry(3, 100)->{$method}($url, $options);
    }
}