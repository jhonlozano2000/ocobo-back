<?php

namespace App\Services\Ia;

use App\Models\Calidad\CalidadOrganigrama;
use App\Models\Configuracion\ConfigDiviPoli;
use App\Models\Configuracion\ConfigVarias;
use App\Models\ControlAcceso\UserCargo;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Servicio de integración con el microservicio de IA Local OCOBO (FastAPI en :8002).
 *
 * Aplica:
 * - Filtro duro ABAC por jerarquía organizacional para proteger precedentes de otras áreas.
 * - Cabecera obligatoria X-Ocobo-AI-Key para autenticación segura entre servicios.
 * - Timeouts configurables y manejo resiliente ante fallas de red.
 */
class IaAsistenteService
{
    protected string $baseUrl;

    protected string $apiKey;

    protected int $timeout;

    protected bool $enabled;

    protected string $model;

    protected string $modelFallback;

    public function __construct(
        ?string $baseUrl = null,
        ?string $apiKey = null,
        ?int $timeout = null,
        ?bool $enabled = null,
        ?string $model = null,
        ?string $modelFallback = null
    ) {
        try {
            $this->baseUrl = rtrim($baseUrl ?? config('services.ia.url', 'http://127.0.0.1:8002'), '/');
            $this->apiKey = $apiKey ?? config('services.ia.key', 'ocobo_ai_secret_key_2026_local');
            $this->timeout = $timeout ?? (int) config('services.ia.timeout', 180);
            $this->enabled = $enabled ?? (bool) config('services.ia.enabled', true);
            $this->model = $model ?? (string) config('services.ia.model', 'ocobo-asistente:latest');
            $this->modelFallback = $modelFallback ?? (string) config('services.ia.model_fallback', 'qwen2.5:7b-instruct-q4_K_M');
        } catch (\Throwable $e) {
            $this->baseUrl = rtrim($baseUrl ?? 'http://127.0.0.1:8002', '/');
            $this->apiKey = $apiKey ?? 'ocobo_ai_secret_key_2026_local';
            $this->timeout = $timeout ?? 180;
            $this->enabled = $enabled ?? true;
            $this->model = $model ?? 'ocobo-asistente:latest';
            $this->modelFallback = $modelFallback ?? 'qwen2.5:7b-instruct-q4_K_M';
        }
    }

    /**
     * Consulta el estado de salud y ocupación de la cola de inferencia local.
     */
    public function obtenerEstado(): array
    {
        if (! $this->enabled) {
            return [
                'online' => false,
                'error' => 'El servicio de IA institucional se encuentra deshabilitado por configuración del sistema.',
            ];
        }
        try {
            $response = Http::withHeaders([
                'X-Ocobo-AI-Key' => $this->apiKey,
                'Accept' => 'application/json',
            ])->timeout(5)->get("{$this->baseUrl}/api/v1/rag/status");

            if ($response->successful()) {
                return [
                    'online' => true,
                    'data' => $response->json(),
                ];
            }

            return [
                'online' => false,
                'error' => "El servicio de IA respondió HTTP {$response->status()}",
            ];
        } catch (ConnectionException $e) {
            return [
                'online' => false,
                'error' => 'No fue posible conectar con el microservicio de IA en '.$this->baseUrl,
            ];
        } catch (\Throwable $e) {
            return [
                'online' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Busca precedentes documentales en el índice RAG respetando la jerarquía ABAC del usuario.
     *
     * @param  string  $consulta  Texto o asunto a consultar
     * @param  int  $topK  Cantidad de resultados
     * @param  User|null  $user  Usuario solicitante para filtro ABAC
     * @param  bool  $flagHc  Indicador de Historia Clínica
     * @param  bool  $flagTutela  Indicador de Tutela
     */
    public function buscarPrecedentes(
        string $consulta,
        int $topK = 5,
        ?User $user = null,
        bool $flagHc = false,
        bool $flagTutela = false
    ): array {
        if (! $this->enabled) {
            return [
                'success' => false,
                'estado' => 'SERVICIO_DESHABILITADO',
                'precedentes' => [],
                'citas' => [],
                'advertencias' => [],
                'error' => 'El Asistente de IA se encuentra deshabilitado por configuración del sistema.',
            ];
        }

        $dependenciasPermitidas = $this->obtenerDependenciasPermitidas($user);

        $payload = [
            'consulta' => $consulta,
            'top_k' => $topK,
            'dependencias_permitidas' => $dependenciasPermitidas,
            'flag_hc' => $flagHc,
            'flag_tutela' => $flagTutela,
        ];

        try {
            $response = Http::withHeaders([
                'X-Ocobo-AI-Key' => $this->apiKey,
                'Accept' => 'application/json',
            ])->timeout(30)->post("{$this->baseUrl}/api/v1/rag/buscar-precedentes", $payload);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Fallo en búsqueda de precedentes IA', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'success' => false,
                'estado' => 'ERROR_SERVICIO',
                'precedentes' => [],
                'citas' => [],
                'advertencias' => [],
                'error' => "Error del servicio de IA: HTTP {$response->status()}",
            ];
        } catch (ConnectionException $e) {
            return [
                'success' => false,
                'estado' => 'SERVICIO_NO_DISPONIBLE',
                'precedentes' => [],
                'citas' => [],
                'advertencias' => [],
                'error' => 'El microservicio de IA local no está disponible en este momento.',
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'estado' => 'ERROR_INESPERADO',
                'precedentes' => [],
                'citas' => [],
                'advertencias' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Proyecta un borrador de respuesta oficial utilizando RAG + LLM local.
     *
     * @param  array  $datos  Datos validados del FormRequest
     * @param  User|null  $user  Usuario solicitante para filtro ABAC
     */
    public function proyectarBorrador(array $datos, ?User $user = null): array
    {
        if (! $this->enabled) {
            return [
                'success' => false,
                'estado' => 'SERVICIO_DESHABILITADO',
                'borrador_respuesta' => '',
                'citas' => [],
                'advertencias' => [],
                'tokens_verificar' => [],
                'score_confianza' => 0.0,
                'modelo_usado' => 'N/A',
                'error' => 'El Asistente de IA se encuentra deshabilitado por configuración del sistema.',
            ];
        }

        $dependenciasPermitidas = $this->obtenerDependenciasPermitidas($user);
        $infoInst = $this->obtenerDatosInstitucion();

        $payload = [
            'id_radicado' => $datos['id_radicado'] ?? null,
            'asunto' => $datos['asunto'],
            'contenido' => $datos['contenido'] ?? '',
            'tipo_solicitud' => $datos['tipo_solicitud'] ?? 'Derecho de Petición',
            'dependencias_permitidas' => $dependenciasPermitidas,
            'flag_hc' => (bool) ($datos['flag_hc'] ?? false),
            'flag_tutela' => (bool) ($datos['flag_tutela'] ?? false),
            'top_k' => (int) ($datos['top_k'] ?? 5),
            'instrucciones_adicionales' => $datos['instrucciones_adicionales'] ?? null,
            'anonimizar_automaticamente' => (bool) ($datos['anonimizar_automaticamente'] ?? true),
            'institucion_nombre' => $datos['institucion_nombre'] ?? $infoInst['institucion_nombre'],
            'institucion_ciudad' => $datos['institucion_ciudad'] ?? $infoInst['institucion_ciudad'],
            'institucion_departamento' => $datos['institucion_departamento'] ?? $infoInst['institucion_departamento'],
            'modelo_solicitado' => $datos['modelo_solicitado'] ?? $this->model,
        ];

        try {
            $response = Http::withHeaders([
                'X-Ocobo-AI-Key' => $this->apiKey,
                'Accept' => 'application/json',
            ])->timeout($this->timeout)->post("{$this->baseUrl}/api/v1/rag/proyectar-borrador", $payload);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Fallo en proyección de borrador IA', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'success' => false,
                'estado' => 'ERROR_SERVICIO',
                'borrador_respuesta' => '',
                'citas' => [],
                'advertencias' => [],
                'tokens_verificar' => [],
                'score_confianza' => 0.0,
                'modelo_usado' => 'N/A',
                'error' => "El motor de IA retornó error HTTP {$response->status()}",
            ];
        } catch (ConnectionException $e) {
            return [
                'success' => false,
                'estado' => 'SERVICIO_NO_DISPONIBLE',
                'borrador_respuesta' => '',
                'citas' => [],
                'advertencias' => [],
                'tokens_verificar' => [],
                'score_confianza' => 0.0,
                'modelo_usado' => 'N/A',
                'error' => 'No se pudo establecer conexión con el motor de IA local (:8002).',
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'estado' => 'ERROR_INESPERADO',
                'borrador_respuesta' => '',
                'citas' => [],
                'advertencias' => [],
                'tokens_verificar' => [],
                'score_confianza' => 0.0,
                'modelo_usado' => 'N/A',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Resuelve los IDs de dependencias a las que tiene acceso el usuario según ABAC.
     * Si retorna null, significa acceso global sin restricciones (bypass por permisos).
     *
     * @return array<int>|null
     */
    public function obtenerDependenciasPermitidas(?User $user): ?array
    {
        if (! $user) {
            return [];
        }

        // Permiso especial de bypass jerárquico
        if ($user->can('Radicar -> Ver Todos') || $user->can('Asistente IA -> Ver Todos')) {
            return null;
        }

        $cargoActivo = UserCargo::cargoActivoDelUsuario($user->id);
        if (! $cargoActivo || ! $cargoActivo->cargo) {
            return [];
        }

        $ids = [];
        $cargo = $cargoActivo->cargo;

        // 1. Ancestros y el nodo actual (jerarquía vertical hacia arriba)
        $jerarquia = $cargo->getJerarquiaCompleta();
        foreach ($jerarquia as $item) {
            if (! empty($item['id'])) {
                $ids[] = (int) $item['id'];
            }
        }

        // 2. Descendientes (dependencias u oficinas subordinadas)
        $this->acumularDescendientesIds($cargo, $ids);

        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * Recorre recursivamente los nodos subordinados para acumular sus IDs.
     */
    protected function acumularDescendientesIds(CalidadOrganigrama $nodo, array &$ids, int $profundidad = 0): void
    {
        if ($profundidad > CalidadOrganigrama::MAX_PROFUNDIDAD) {
            return;
        }

        $hijos = $nodo->children()->get();
        foreach ($hijos as $hijo) {
            $ids[] = (int) $hijo->id;
            $this->acumularDescendientesIds($hijo, $ids, $profundidad + 1);
        }
    }

    /**
     * Obtiene los metadatos de la institución configurada en OCOBO (multitenant).
     */
    protected function obtenerDatosInstitucion(): array
    {
        try {
            $razonSocial = ConfigVarias::where('clave', 'razon_social_empresa')->value('valor')
                ?: config('app.name', 'Entidad Institucional');

            $ciudad = '[CIUDAD]';
            $depto = '[DEPARTAMENTO]';

            $diviId = ConfigVarias::where('clave', 'divi_poli_id_empresa')->value('valor');
            if ($diviId) {
                $divi = ConfigDiviPoli::with('padre')->find($diviId);
                if ($divi) {
                    $ciudad = $divi->nombre;
                    if ($divi->padre) {
                        $depto = $divi->padre->nombre;
                    }
                }
            }

            return [
                'institucion_nombre' => $razonSocial,
                'institucion_ciudad' => $ciudad,
                'institucion_departamento' => $depto,
            ];
        } catch (\Throwable $e) {
            return [
                'institucion_nombre' => config('app.name', 'Entidad Institucional'),
                'institucion_ciudad' => '[CIUDAD]',
                'institucion_departamento' => '[DEPARTAMENTO]',
            ];
        }
    }
}
