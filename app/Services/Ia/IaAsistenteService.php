<?php

namespace App\Services\Ia;

use App\Models\Calidad\CalidadOrganigrama;
use App\Models\Configuracion\ConfigDiviPoli;
use App\Models\Configuracion\ConfigVarias;
use App\Models\ControlAcceso\UserCargo;
use App\Models\Ia\IaBorradorAuditoria;
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
     * Solicita al microservicio de IA una sugerencia de borrador inicial para un documento en blanco.
     *
     * Combina la frase obligatoria y la tipología (memorando, acta, circular u oficio) con
     * el contexto de sesión del funcionario (dependencia y cargo), el filtro ABAC jerárquico
     * y los datos institucionales multitenant de OCOBO.
     *
     * @param  array  $datos  Datos validados de SugerirDocumentoBlancoRequest
     * @param  User|null  $user  Usuario autenticado
     * @return array<string, mixed>
     */
    public function sugerirDocumentoBlanco(array $datos, ?User $user = null): array
    {
        if (! $this->enabled) {
            return [
                'success' => false,
                'estado' => 'SERVICIO_DESHABILITADO',
                'tipo_documento' => $datos['tipo_documento'] ?? 'oficio',
                'asunto_sugerido' => '',
                'contenido_estructurado' => '',
                'campos_por_completar' => [],
                'precedentes_usados' => [],
                'modelo_usado' => 'N/A',
                'error' => 'El Asistente de IA se encuentra deshabilitado por configuración del sistema.',
            ];
        }

        $dependenciasPermitidas = $this->obtenerDependenciasPermitidas($user);
        $contextoFuncionario = $this->obtenerContextoFuncionario($user);
        $infoInst = $this->obtenerDatosInstitucion();

        $payload = [
            'frase' => $datos['frase'],
            'tipo_documento' => $datos['tipo_documento'],
            'dependencia_nombre' => $contextoFuncionario['dependencia_nombre'],
            'cargo_funcionario' => $contextoFuncionario['cargo_funcionario'],
            'dependencias_permitidas' => $dependenciasPermitidas,
            'serie_trd' => $datos['serie_trd'] ?? null,
            'subserie_trd' => $datos['subserie_trd'] ?? null,
            'top_k' => (int) ($datos['top_k'] ?? 3),
            'institucion_nombre' => $infoInst['institucion_nombre'],
            'institucion_ciudad' => $infoInst['institucion_ciudad'],
            'institucion_departamento' => $infoInst['institucion_departamento'],
            'modelo_solicitado' => $datos['modelo_solicitado'] ?? $this->model,
            'anonimizar_automaticamente' => true,
            'stream' => false,
        ];

        $timeoutControlado = min($this->timeout, 90);

        try {
            $response = Http::withHeaders([
                'X-Ocobo-AI-Key' => $this->apiKey,
                'Accept' => 'application/json',
            ])->timeout($timeoutControlado)->post("{$this->baseUrl}/v1/drafts/blank-document", $payload);

            if ($response->successful()) {
                $json = $response->json();
                $json['contexto_funcionario'] = $contextoFuncionario;

                return $json;
            }

            Log::warning('Fallo en sugerencia de documento en blanco IA', [
                'status' => $response->status(),
                'tipo_documento' => $datos['tipo_documento'] ?? null,
            ]);

            return [
                'success' => false,
                'estado' => 'ERROR_SERVICIO',
                'tipo_documento' => $datos['tipo_documento'] ?? 'oficio',
                'asunto_sugerido' => '',
                'contenido_estructurado' => '',
                'campos_por_completar' => [],
                'precedentes_usados' => [],
                'modelo_usado' => 'N/A',
                'error' => "El motor de IA retornó error HTTP {$response->status()}",
            ];
        } catch (ConnectionException $e) {
            return [
                'success' => false,
                'estado' => 'SERVICIO_NO_DISPONIBLE',
                'tipo_documento' => $datos['tipo_documento'] ?? 'oficio',
                'asunto_sugerido' => '',
                'contenido_estructurado' => '',
                'campos_por_completar' => [],
                'precedentes_usados' => [],
                'modelo_usado' => 'N/A',
                'error' => 'No se pudo establecer conexión con el motor de IA local (:8002).',
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'estado' => 'ERROR_INESPERADO',
                'tipo_documento' => $datos['tipo_documento'] ?? 'oficio',
                'asunto_sugerido' => '',
                'contenido_estructurado' => '',
                'campos_por_completar' => [],
                'precedentes_usados' => [],
                'modelo_usado' => 'N/A',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Obtiene la dependencia y el cargo activo del funcionario autenticado para contextualizar el borrador.
     *
     * @return array{dependencia_id: int|null, dependencia_nombre: string|null, cargo_funcionario: string|null}
     */
    public function obtenerContextoFuncionario(?User $user): array
    {
        if (! $user) {
            return [
                'dependencia_id' => null,
                'dependencia_nombre' => null,
                'cargo_funcionario' => null,
            ];
        }

        try {
            $cargoActivo = UserCargo::cargoActivoDelUsuario($user->id);
            if (! $cargoActivo || ! $cargoActivo->cargo) {
                return [
                    'dependencia_id' => null,
                    'dependencia_nombre' => null,
                    'cargo_funcionario' => null,
                ];
            }

            $cargo = $cargoActivo->cargo;
            $jerarquia = $cargo->getJerarquiaCompleta();

            $dependenciaId = null;
            $dependenciaNombre = null;
            foreach ($jerarquia as $nodo) {
                if (in_array($nodo['tipo'] ?? '', ['Dependencia', 'Oficina'], true)) {
                    $dependenciaId = isset($nodo['id']) ? (int) $nodo['id'] : null;
                    $dependenciaNombre = $nodo['nom_organico'] ?? null;
                }
            }

            return [
                'dependencia_id' => $dependenciaId ?? (int) $cargo->id,
                'dependencia_nombre' => $dependenciaNombre ?? $cargo->nom_organico,
                'cargo_funcionario' => $cargo->nom_organico,
            ];
        } catch (\Throwable $e) {
            return [
                'dependencia_id' => null,
                'dependencia_nombre' => null,
                'cargo_funcionario' => null,
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

    /**
     * Registra en la bitácora de auditoría (ISO 27001) la sugerencia de borrador en blanco.
     *
     * @param  array<string, mixed>  $datos  Datos de la petición validados
     * @param  array<string, mixed>  $resultado  Resultado retornado por el motor de IA
     * @param  User|null  $user  Usuario solicitante
     * @param  string|null  $ipAddress  Dirección IP origen
     * @param  string|null  $userAgent  User Agent origen
     * @return int|null ID de la auditoría generada o null si falló la persistencia
     */
    public function registrarAuditoriaBorradorBlanco(
        array $datos,
        array $resultado,
        ?User $user = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): ?int {
        $contexto = $resultado['contexto_funcionario'] ?? [];

        try {
            $auditoria = IaBorradorAuditoria::create([
                'user_id' => $user?->id,
                'id_radicado' => ! empty($datos['documento_id']) ? 'DOC-'.$datos['documento_id'] : null,
                'tipo_radicado' => 'blanco_'.($datos['tipo_documento'] ?? 'oficio'),
                'radicado_id' => $datos['documento_id'] ?? null,
                'asunto_hash' => hash('sha256', (string) ($resultado['asunto_sugerido'] ?? '')),
                'prompt_hash' => hash('sha256', (string) ($datos['frase'] ?? '')),
                'modelo_llm' => (string) ($resultado['modelo_usado'] ?? 'N/A'),
                'estado' => ! empty($resultado['fallback']) ? 'BORRADOR_BLANCO_FALLBACK' : 'BORRADOR_BLANCO_OK',
                'score_confianza' => null,
                'citas_utilizadas' => [
                    'tipo_documento' => $datos['tipo_documento'] ?? 'oficio',
                    'dependencia_id' => $contexto['dependencia_id'] ?? null,
                    'dependencia_nombre' => $contexto['dependencia_nombre'] ?? null,
                    'frase_longitud' => mb_strlen((string) ($datos['frase'] ?? '')),
                    'serie_trd' => $datos['serie_trd'] ?? null,
                    'subserie_trd' => $datos['subserie_trd'] ?? null,
                    'precedentes_usados' => $resultado['precedentes_usados'] ?? [],
                    'feedback' => null,
                ],
                'advertencias' => [],
                'tokens_verificar' => $resultado['campos_por_completar'] ?? [],
                'borrador_generado' => $resultado['contenido_estructurado'] ?? null,
                'tiempo_total_ms' => isset($resultado['tiempo_total_ms']) ? (float) $resultado['tiempo_total_ms'] : null,
                'fallback' => (bool) ($resultado['fallback'] ?? false),
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ]);

            return $auditoria->id;
        } catch (\Throwable $e) {
            Log::warning('No se pudo persistir auditoría de borrador en blanco IA', [
                'error' => $e->getMessage(),
                'user_id' => $user?->id,
            ]);

            return null;
        }
    }

    /**
     * Registra o actualiza el feedback de usuario sobre una sugerencia de borrador (ISO 27001).
     *
     * @param  int  $auditoriaId  ID del registro de auditoría
     * @param  string  $accion  Acción ejecutada (insertado, editado, descartado, regenerado)
     * @param  int|null  $userId  ID del usuario solicitante
     * @return bool True si se actualizó el registro, False en caso contrario
     */
    public function registrarFeedbackAuditoria(int $auditoriaId, string $accion, ?int $userId = null): bool
    {
        $query = IaBorradorAuditoria::where('id', $auditoriaId);
        if ($userId) {
            $query->where('user_id', $userId);
        }

        $auditoria = $query->first();
        if (! $auditoria) {
            return false;
        }

        $meta = is_array($auditoria->citas_utilizadas) ? $auditoria->citas_utilizadas : [];
        $meta['feedback'] = [
            'accion' => $accion,
            'registrado_en' => now()->toIso8601String(),
        ];

        $auditoria->citas_utilizadas = $meta;

        return $auditoria->save();
    }
}
