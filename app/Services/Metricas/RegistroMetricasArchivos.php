<?php

namespace App\Services\Metricas;

use App\Jobs\RegistrarMetricaArchivo;
use App\Models\Metricas\ArchivoMetrica;
use App\Models\MiBandeja\MiBandejaTempArchivoVersion;
use App\Models\MiBandeja\MiBandejaTempGrupoArchiAdjunto;
use App\Models\MiDisco\MiDiscoArchivo;
use App\Models\OfiArchivo\OfiArchivoDigitalizacion;
use App\Models\OfiArchivo\OfiArchivoExpedienteDocumento;
use App\Models\OfiArchivo\OfiArchivoPlantillaDocumento;
use App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviados;
use App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviadosArchivos;
use App\Models\VentanillaUnica\Internos\VentanillaRadicaInterno;
use App\Models\VentanillaUnica\Internos\VentanillaRadicaInternoArchivos;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReciArchivo;
use App\Models\Workflows\WorkFlowArchivo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mime\MimeTypes;

/**
 * Punto único de registro de métricas de archivos. El trait RegistraMetricaArchivo le entrega
 * los datos del archivo; aquí se normalizan (bytes, extensión) y la inserción se difiere hasta
 * después del commit y de la respuesta, para no penalizar la carga.
 */
class RegistroMetricasArchivos
{
    /**
     * Modelos de archivo rastreados (usan RegistraMetricaArchivo). El backfill los recorre.
     *
     * @var list<class-string<Model>>
     */
    public const MODELOS = [
        VentanillaRadicaReci::class,
        VentanillaRadicaReciArchivo::class,
        VentanillaRadicaEnviados::class,
        VentanillaRadicaEnviadosArchivos::class,
        VentanillaRadicaInterno::class,
        VentanillaRadicaInternoArchivos::class,
        MiBandejaTempGrupoArchiAdjunto::class,
        MiBandejaTempArchivoVersion::class,
        MiDiscoArchivo::class,
        OfiArchivoExpedienteDocumento::class,
        OfiArchivoDigitalizacion::class,
        OfiArchivoPlantillaDocumento::class,
        WorkFlowArchivo::class,
    ];

    public function __construct(private DependenciaDeUsuario $dependencias) {}

    /**
     * Programa el registro de un evento. Nunca lanza: una falla de métricas no afecta la carga.
     */
    public function registrar(Model $modelo, string $evento, array $datos): void
    {
        try {
            // El que elimina es el usuario autenticado; el que cargó, el que guarda el registro
            $usuario = $evento === ArchivoMetrica::ELIMINADO
                ? (auth()->id() ?? ($datos['user_id'] ?? null))
                : (($datos['user_id'] ?? null) ?? auth()->id());

            $fila = $this->fila($modelo, $evento, $datos, now(), $usuario);

            // Si la transacción de la carga se revierte, el evento se descarta
            DB::afterCommit(fn () => RegistrarMetricaArchivo::programar($fila));
        } catch (\Throwable $e) {
            Log::warning('No se pudo programar la métrica del archivo', [
                'modelo' => class_basename($modelo),
                'id' => $modelo->getKey(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Fila normalizada, sin la dependencia (se resuelve al insertar).
     */
    public function fila(Model $modelo, string $evento, array $datos, mixed $fecha, ?int $usuarioId): array
    {
        $ruta = (string) ($datos['ruta'] ?? '');

        return [
            'evento' => $evento,
            'modulo' => $datos['modulo'],
            'submodulo' => $datos['submodulo'],
            'archivable_type' => $modelo->getMorphClass(),
            'archivable_id' => (int) $modelo->getKey(),
            'ruta_hash' => sha1($ruta),
            'extension' => self::extension($datos['extension'] ?? null, $ruta, $datos['mime'] ?? null),
            'mime_type' => isset($datos['mime']) ? mb_substr((string) $datos['mime'], 0, 150) : null,
            'bytes' => max(0, (int) round((float) ($datos['bytes'] ?? 0))),
            'user_id' => $usuarioId,
            'ocurrido_en' => Carbon::parse($fecha ?? now())->toDateTimeString(),
        ];
    }

    /**
     * Inserta la fila con la dependencia del usuario en ese momento.
     */
    public function insertar(array $fila): void
    {
        $dependencia = $this->dependencias->resolver($fila['user_id'] ?? null);

        ArchivoMetrica::query()->insert($fila + [
            'dependencia_id' => $dependencia['id'] ?? null,
            'dependencia_nom' => $dependencia['nombre'] ?? null,
            'created_at' => now(),
        ]);
    }

    /**
     * Extensión en minúsculas, sin punto; si el registro no la guarda se deduce de la ruta o del MIME.
     */
    public static function extension(?string $extension, ?string $ruta, ?string $mime): string
    {
        $ext = strtolower(ltrim(trim((string) $extension), '.'));

        if ($ext === '' && $ruta) {
            $ext = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));
        }
        if ($ext === '' && $mime) {
            $ext = MimeTypes::getDefault()->getExtensions($mime)[0] ?? '';
        }
        if ($ext === 'jpeg') {
            $ext = 'jpg';
        }

        return mb_substr((string) preg_replace('/[^a-z0-9]/', '', $ext), 0, 20);
    }
}
