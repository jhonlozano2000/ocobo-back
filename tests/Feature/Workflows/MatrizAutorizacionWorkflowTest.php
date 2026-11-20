<?php

namespace Tests\Feature\Workflows;

use App\Models\User;
use App\Models\Workflows\Tarea;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowInstancia;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** Comprueba pertenencia además del permiso RBAC, con peticiones directas y rollback. */
class MatrizAutorizacionWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    private User $ajeno;

    private User $sinPermisos;

    private Workflow $flujo;

    private array $ids;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('workflows_archivos');
        $owner = User::factory()->create(['estado' => 1]);
        $this->ajeno = User::factory()->create(['estado' => 1]);
        $this->sinPermisos = User::factory()->create(['estado' => 1]);
        $permisos = [
            'Workflows -> Workflows -> Listar', 'Workflows -> Workflows -> Crear', 'Workflows -> Workflows -> Mostrar',
            'Workflows -> Workflows -> Editar', 'Workflows -> Workflows -> Eliminar',
            'Workflows -> Instancias -> Ejecutar', 'Workflows -> Instancias -> Consultar',
            'Workflows -> Tareas -> Listar', 'Workflows -> Tareas -> Mostrar', 'Workflows -> Tareas -> Crear',
            'Workflows -> Tareas -> Editar', 'Workflows -> Tareas -> Eliminar', 'Workflows -> Tareas -> Asignar',
            'Workflows -> Archivos -> Ver', 'Workflows -> Archivos -> Subir',
            'Workflows -> Archivos -> Descargar', 'Workflows -> Archivos -> Eliminar',
        ];
        foreach ($permisos as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }
        $this->ajeno->givePermissionTo($permisos);
        $this->flujo = Workflow::create(['nombre' => 'Privado', 'estado' => 'activo', 'creador_user_id' => $owner->id]);
        $inicio = $this->flujo->nodos()->create(['titulo' => 'Inicio', 'tipo' => 'inicio']);
        $nodo = $this->flujo->nodos()->create(['titulo' => 'Revisión', 'tipo' => 'tarea']);
        $fin = $this->flujo->nodos()->create(['titulo' => 'Fin', 'tipo' => 'fin']);
        foreach ([[$inicio, $nodo], [$nodo, $fin]] as [$from, $to]) {
            $this->flujo->conexiones()->create(['nodo_origen_id' => $from->id, 'nodo_destino_id' => $to->id]);
        }
        $design = $nodo->tareas()->create(['titulo' => 'Actividad privada']);
        $designcheck = $design->checklists()->create(['item_descripcion' => 'Verificar']);
        $instance = WorkflowInstancia::create(['workflow_id' => $this->flujo->id, 'usuario_ejecuta_id' => $owner->id, 'estado' => 'en_curso', 'nodo_actual_id' => $nodo->id, 'fecha_inicio' => now()]);
        $task = Tarea::create(['workflow_id' => $this->flujo->id, 'nombre' => 'Operativa privada', 'estado' => 'en_curso', 'instancia_id' => $instance->id, 'nodo_id' => $nodo->id]);
        $task->propietarios()->attach($owner->id);
        $task->responsables()->attach($owner->id);
        $check = $task->checklists()->create(['item_descripcion' => 'Verificar']);
        $archivo = ['workflow_id' => $this->flujo->id, 'nombre_original' => 'acta.pdf', 'nombre_almacenado' => 'acta.pdf', 'ruta_almacenada' => 'acta.pdf', 'mime_type' => 'application/pdf', 'peso_bytes' => 10, 'hash_sha256' => hash('sha256', 'acta'), 'disk' => 'workflows_archivos', 'uploaded_by' => $owner->id];
        $file = $this->flujo->archivos()->create($archivo);
        $taskfile = $task->archivos()->create($archivo);
        Storage::disk('workflows_archivos')->put('acta.pdf', '%PDF-1.4 acta');
        $this->ids = array_map('strval', ['@flow' => $this->flujo->id, '@node' => $nodo->id, '@designcheck' => $designcheck->id, '@design' => $design->id, '@taskfile' => $taskfile->id, '@task' => $task->id, '@check' => $check->id, '@instance' => $instance->id, '@file' => $file->id, '@user' => $this->ajeno->id]);
    }

    public static function rutasAjenas(): array
    {
        $w = '/api/workflows/workflows/@flow';
        $d = "$w/nodos/@node/tareas";
        $t = '/api/workflows/tareas/@task';

        return [
            'detalle flujo' => ['GET', $w, [], 403],
            'editar flujo' => ['PUT', $w, ['nombre' => 'Cambio'], 403],
            'eliminar flujo' => ['DELETE', $w, [], 403],
            'duplicar flujo' => ['POST', "$w/duplicar", [], 403],
            'estado flujo' => ['PATCH', "$w/estado", ['estado' => 'inactivo'], 403],
            'canvas' => ['POST', "$w/canvas", ['nodos' => [], 'conexiones' => []], 403],
            'kpi' => ['GET', "$w/reportes", [], 403],
            'vencidas' => ['GET', "$w/reportes/vencidas-por-usuario", [], 403],
            'tendencia' => ['GET', "$w/reportes/tendencia-mensual", [], 403],
            'nodos' => ['GET', "$w/nodos", [], 403],
            'crear nodo' => ['POST', "$w/nodos", ['tipo' => 'tarea', 'titulo' => 'Nuevo'], 403],
            'editar nodo' => ['PUT', "$w/nodos/@node", ['titulo' => 'Cambio'], 403],
            'eliminar nodo' => ['DELETE', "$w/nodos/@node", [], 403],
            'plantillas' => ['GET', $d, [], 403],
            'detalle plantilla' => ['GET', "$d/@design", [], 403],
            'crear plantilla' => ['POST', $d, ['titulo' => 'Nuevo'], 403],
            'editar plantilla' => ['PUT', "$d/@design", ['titulo' => 'Cambio'], 403],
            'eliminar plantilla' => ['DELETE', "$d/@design", [], 403],
            'asignar plantilla' => ['POST', "$d/@design/asignar", ['responsable_usuario_id' => '@user'], 403],
            'estado plantilla' => ['PATCH', "$d/@design/estado", ['estado' => 'completada'], 403],
            'orden plantillas' => ['PUT', "$d/reordenar", ['tarea_ids' => ['@design']], 403],
            'checklist plantilla' => ['GET', "$d/@design/checklists", [], 403],
            'crear item plantilla' => ['POST', "$d/@design/checklists", ['item_descripcion' => 'Nuevo'], 403],
            'editar item plantilla' => ['PUT', "$d/@design/checklists/@designcheck", ['esta_completado' => true], 403],
            'borrar item plantilla' => ['DELETE', "$d/@design/checklists/@designcheck", [], 403],
            'orden checklist plantilla' => ['PUT', "$d/@design/checklists/reordenar", ['checklist_ids' => ['@designcheck']], 403],
            'listado ejecuciones' => ['GET', "$w/instancias", [], 403],
            'iniciar ejecucion' => ['POST', "$w/instancias", [], 403],
            'detalle ejecucion' => ['GET', "$w/instancias/@instance", [], 404],
            'ejecutar paso' => ['POST', "$w/instancias/@instance/ejecutar", ['nodo_id' => '@node'], 404],
            'detener ejecucion' => ['PATCH', "$w/instancias/@instance/detener", ['estado' => 'detenida'], 404],
            'devolver paso' => ['POST', "$w/instancias/@instance/devolver", [], 404],
            'listado archivos' => ['GET', "$w/archivos", [], 403],
            'descarga archivo' => ['GET', "$w/archivos/@file/download", [], 403],
            'borrar archivo' => ['DELETE', "$w/archivos/@file", [], 403],
            'subir archivo' => ['POST', "$w/archivos", ['archivable_type' => 'workflow', 'archivable_id' => '@flow', 'archivo' => '@pdf'], 403],
            'tablero' => ['GET', '/api/workflows/@flow/tareas', [], 403],
            'crear tarea independiente' => ['POST', '/api/workflows/tareas', ['workflow_id' => '@flow', 'nombre' => 'Nueva'], 403],
            'detalle tarea' => ['GET', $t, [], 403],
            'editar tarea' => ['PUT', $t, ['nombre' => 'Cambio'], 403],
            'eliminar tarea' => ['DELETE', $t, [], 403],
            'iniciar tarea' => ['POST', "$t/iniciar", [], 403],
            'completar tarea' => ['POST', "$t/completar", [], 403],
            'cancelar tarea' => ['POST', "$t/cancelar", [], 403],
            'reactivar tarea' => ['POST', "$t/reactivar", [], 403],
            'checklist tarea' => ['GET', "$t/checklists", [], 403],
            'crear item tarea' => ['POST', "$t/checklists", ['item_descripcion' => 'Nuevo'], 403],
            'editar item tarea' => ['PUT', "$t/checklists/@check", ['esta_completado' => true], 403],
            'borrar item tarea' => ['DELETE', "$t/checklists/@check", [], 403],
            'orden checklist tarea' => ['PUT', "$t/checklists/reordenar", ['orden' => ['@check']], 403],
            'evidencias' => ['GET', "$t/archivos", [], 403],
            'subir evidencia' => ['POST', "$t/archivos", ['archivo' => '@pdf'], 403],
            'descarga evidencia' => ['GET', "$t/archivos/@taskfile/download", [], 403],
            'borrar evidencia' => ['DELETE', "$t/archivos/@taskfile", [], 403],
        ];
    }

    #[DataProvider('rutasAjenas')]
    public function test_exige_autenticacion_y_pertenencia_aun_con_todos_los_permisos(string $method, string $url, array $data, int $status): void
    {
        $url = strtr($url, $this->ids);
        array_walk_recursive($data, function (&$value) {
            if (is_string($value)) {
                $value = strtr($value, $this->ids);
            }
        });
        $files = [];
        if (($data['archivo'] ?? null) === '@pdf') {
            unset($data['archivo']);
            $files['archivo'] = UploadedFile::fake()->createWithContent('acta.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n");
        }
        $this->withHeader('Accept', 'application/json');
        $this->call($method, $url, $data, [], $files)->assertUnauthorized();
        $this->actingAs($this->sinPermisos)->call($method, $url, $data, [], $files)->assertForbidden();
        $this->actingAs($this->ajeno)->call($method, $url, $data, [], $files)->assertStatus($status);
        if (str_starts_with($url, '/api/workflows/tareas/'.$this->ids['@task'])) {
            // El equipo permite consultar el flujo, pero no intervenir en la tarea de otro.
            $this->flujo->settings()->create(['configuracion_asignacion_json' => ['equipo' => [$this->ajeno->id]]]);
            $this->call($method, $url, $data, [], $files)->assertForbidden();
        }
        $this->assertSame('Privado', $this->flujo->fresh()->nombre);
    }

    public function test_listados_globales_no_incluyen_recursos_ajenos(): void
    {
        $this->actingAs($this->ajeno);
        foreach (['workflows', 'mis-tareas', 'mis-instancias'] as $ruta) {
            $this->getJson('/api/workflows/'.$ruta)->assertOk()->assertJsonCount(0, 'data');
        }
        $this->postJson('/api/workflows/workflows', ['nombre' => 'Mi flujo nuevo'])->assertCreated()
            ->assertJsonPath('data.creador_user_id', $this->ajeno->id);
    }

    public static function rutasGlobales(): array
    {
        return [
            ['GET', '/api/workflows/workflows', []],
            ['POST', '/api/workflows/workflows', ['nombre' => 'Nuevo']],
            ['GET', '/api/workflows/mis-tareas', []],
            ['GET', '/api/workflows/mis-instancias', []],
        ];
    }

    #[DataProvider('rutasGlobales')]
    public function test_rutas_globales_exigen_autenticacion_y_permiso(string $method, string $url, array $data): void
    {
        $this->json($method, $url, $data)->assertUnauthorized();
        $this->actingAs($this->sinPermisos)->json($method, $url, $data)->assertForbidden();
    }

    public function test_matriz_incluye_todos_los_endpoints_registrados_del_modulo(): void
    {
        $actual = [];
        foreach (app('router')->getRoutes() as $route) {
            if (str_starts_with($route->uri(), 'api/workflows/')) {
                foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                    $actual[] = $method.' /'.$route->uri();
                }
            }
        }
        $tokens = ['@flow' => '{workflow}', '@node' => '{nodo}', '@designcheck' => '{checklist}', '@design' => '{tarea}', '@taskfile' => '{archivo}', '@task' => '{tarea}', '@check' => '{checklist}', '@instance' => '{instancia}', '@file' => '{archivo}'];
        $covered = [];
        foreach ([...array_values(self::rutasAjenas()), ...self::rutasGlobales()] as [$method, $uri]) {
            $covered[] = $method.' '.strtr($uri, $tokens);
        }
        $this->assertEqualsCanonicalizing($actual, $covered);
    }

    public function test_participante_puede_consultar_diseno_y_crear_tarea_independiente(): void
    {
        $this->flujo->settings()->create(['configuracion_asignacion_json' => ['equipo' => [$this->ajeno->id]]]);
        $this->actingAs($this->ajeno);
        foreach ([
            '/api/workflows/workflows/@flow',
            '/api/workflows/workflows/@flow/nodos',
            '/api/workflows/workflows/@flow/nodos/@node/tareas',
            '/api/workflows/workflows/@flow/nodos/@node/tareas/@design/checklists',
            '/api/workflows/workflows/@flow/archivos',
        ] as $url) {
            $this->getJson(strtr($url, $this->ids))->assertOk();
        }
        $this->postJson('/api/workflows/tareas', ['workflow_id' => $this->flujo->id, 'nombre' => 'Independiente autorizada'])
            ->assertCreated()->assertJsonPath('data.workflow_id', $this->flujo->id);
    }
}
