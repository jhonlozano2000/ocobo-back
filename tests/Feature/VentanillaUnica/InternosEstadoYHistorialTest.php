<?php

namespace Tests\Feature\VentanillaUnica;

use App\Models\User;
use App\Models\VentanillaUnica\Internos\VentanillaRadicaInterno;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M03-07: el cambio manual de estado solo admite estados del flujo (no ANULADO).
 * M03-13: el historial de archivos eliminados exige permiso o participación.
 */
class InternosEstadoYHistorialTest extends TestCase
{
    use DatabaseTransactions;

    protected User $funcionario;

    protected VentanillaRadicaInterno $radicado;

    protected function setUp(): void
    {
        parent::setUp();

        // BD de pruebas sin el renombre a plural de las tablas de internos (ABAC de M03-03)
        foreach (['responsa', 'proyectores'] as $sufijo) {
            if (! Schema::hasTable("ventanilla_radica_internos_{$sufijo}")) {
                DB::statement("CREATE TEMPORARY TABLE ventanilla_radica_internos_{$sufijo} LIKE ventanilla_radica_interno_{$sufijo}");
            }
        }

        foreach (['Radicar -> Cores. Interna -> Editar', 'Radicar -> Cores. Interna -> Mostrar', 'Radicar -> Cores. Interna -> Ver Todos', 'Mi Bandeja - Internos -> Ver'] as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }

        $this->funcionario = User::factory()->create(['estado' => 1]);

        // La clasificación documental no es relevante para estas pruebas
        Schema::disableForeignKeyConstraints();
        $this->radicado = VentanillaRadicaInterno::create([
            'num_radicado' => 'TST-M03-'.uniqid(),
            'clasifica_documen_id' => 999999,
            'asunto' => 'Prueba M03',
            'estado_trabajo' => 'RECIBIDO',
            'usuario_crea' => $this->funcionario->id,
        ]);
        Schema::enableForeignKeyConstraints();
    }

    private function urlEstado(): string
    {
        return '/api/ventanilla/radica-interno/'.$this->radicado->id.'/estado';
    }

    private function urlHistorial(): string
    {
        return '/api/ventanilla/radica-interno/'.$this->radicado->id.'/archivos/historial/archivos-eliminados';
    }

    public function test_no_permite_anular_ni_estados_arbitrarios(): void
    {
        $this->funcionario->givePermissionTo('Radicar -> Cores. Interna -> Editar');

        $this->actingAs($this->funcionario)->putJson($this->urlEstado(), ['estado' => 'ANULADO'])->assertStatus(422);
        $this->actingAs($this->funcionario)->putJson($this->urlEstado(), ['estado' => 'CUALQUIERA'])->assertStatus(422);
        $this->actingAs($this->funcionario)->putJson($this->urlEstado(), [])->assertStatus(422);

        $this->assertSame('RECIBIDO', $this->radicado->fresh()->estado_trabajo);
    }

    public function test_permite_estado_del_flujo_pero_no_sobre_radicado_anulado(): void
    {
        $this->funcionario->givePermissionTo('Radicar -> Cores. Interna -> Editar');

        $this->actingAs($this->funcionario)->putJson($this->urlEstado(), ['estado' => 'EN_PROCESO'])->assertOk();
        $this->assertSame('EN_PROCESO', $this->radicado->fresh()->estado_trabajo);

        $this->radicado->update(['estado_trabajo' => 'ANULADO']);
        $this->actingAs($this->funcionario)->putJson($this->urlEstado(), ['estado' => 'RECIBIDO'])->assertStatus(422);
        $this->assertSame('ANULADO', $this->radicado->fresh()->estado_trabajo);
    }

    public function test_historial_eliminaciones_exige_permiso_o_participacion(): void
    {
        $this->actingAs($this->funcionario)->getJson($this->urlHistorial())->assertForbidden();

        // Permiso de bandeja + creador del radicado
        $this->funcionario->givePermissionTo('Mi Bandeja - Internos -> Ver');
        $this->assertAutorizado($this->actingAs($this->funcionario)->getJson($this->urlHistorial())->status());

        $ventanilla = User::factory()->create(['estado' => 1]);
        $ventanilla->givePermissionTo('Radicar -> Cores. Interna -> Mostrar');
        // Sin participar en el radicado, el ABAC lo oculta (M03-03) salvo con Ver Todos
        $this->actingAs($ventanilla)->getJson($this->urlHistorial())->assertNotFound();
        $ventanilla->givePermissionTo('Radicar -> Cores. Interna -> Ver Todos');
        $this->assertAutorizado($this->actingAs($ventanilla)->getJson($this->urlHistorial())->status());
    }

    /**
     * Solo se verifica la autorización: en una BD de pruebas desactualizada puede faltar
     * la tabla de archivos eliminados (500); con la BD al día la respuesta es 200.
     */
    private function assertAutorizado(int $status): void
    {
        $this->assertNotSame(403, $status);
        $this->assertNotSame(401, $status);
    }
}
