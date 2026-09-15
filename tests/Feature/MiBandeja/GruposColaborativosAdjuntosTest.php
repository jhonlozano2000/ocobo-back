<?php

namespace Tests\Feature\MiBandeja;

use App\Models\MiBandeja\MiBandejaTemp;
use App\Models\MiBandeja\MiBandejaTempGrupoRevisor;
use App\Models\MiBandeja\MiBandejaTempGrupoArchiAdjunto;
use App\Models\User;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use App\Models\Configuracion\ConfigLista;
use App\Models\Configuracion\ConfigListaDetalle;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Tests feature M19 Mi Bandeja — Adjuntos de Grupos Colaborativos.
 *
 * Cubre:
 * - 19.25 (parcial): Subir/descargar/eliminar adjuntos en grupos colaborativos
 * - IDOR: usuario ajeno no puede gestionar adjuntos
 *
 * @author  Jhon Javer Lozano Arce
 * @date    2026-09-14
 */
class GruposColaborativosAdjuntosTest extends TestCase
{
    protected User $creador;

    protected User $miembro;

    protected User $ajeno;

    protected VentanillaRadicaReci $radicado;

    protected int $grupoId;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('mi_bandeja_temp');

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permisos = [
            'Mi Bandeja - Grupos Colaborativos -> Ver',
            'Mi Bandeja - Grupos Colaborativos -> Crear',
            'Mi Bandeja - Grupos Colaborativos -> Subir Adjuntos',
        ];

        foreach ($permisos as $permiso) {
            Permission::firstOrCreate(['name' => $permiso]);
        }

        $this->creador = User::factory()->create(['email' => 'adj-creador-' . uniqid() . '@test.com']);
        $this->creador->givePermissionTo($permisos);

        $this->miembro = User::factory()->create(['email' => 'adj-miembro-' . uniqid() . '@test.com']);
        $this->miembro->givePermissionTo($permisos);

        $this->ajeno = User::factory()->create(['email' => 'adj-ajeno-' . uniqid() . '@test.com']);
        $this->ajeno->givePermissionTo($permisos);

        $listaMedios = ConfigLista::create([
            'nombre' => 'Tipos de Recepción',
            'cod' => 'L-MEDIO',
        ]);

        $detalleMedio = ConfigListaDetalle::create([
            'lista_id' => $listaMedios->id,
            'nombre' => 'Correo',
            'codigo' => 'CORR',
            'estado' => 1,
        ]);

        $this->radicado = VentanillaRadicaReci::create([
            'num_radicado' => 'RAD-RR-ADJ-' . uniqid(),
            'usuario_crea' => $this->creador->id,
            'medio_recep_id' => $detalleMedio->id,
        ]);

        // Crear grupo
        $response = $this->actingAs($this->creador)
            ->postJson('/api/mi-bandeja/grupos-colaborativos', [
                'nombre' => 'Grupo Adjuntos Test',
                'radicado_id' => $this->radicado->id,
                'radicado_tipo' => 'recibido',
            ]);

        $response->assertStatus(201);
        $this->grupoId = $response->json('data.id');

        // Agregar miembro
        MiBandejaTempGrupoRevisor::create([
            'grupo_id' => $this->grupoId,
            'user_id' => $this->miembro->id,
        ]);
    }

    // ==================== LISTAR ADJUNTOS ====================

    public function test_listar_adjuntos_grupo_vacio(): void
    {
        $response = $this->actingAs($this->creador)
            ->getJson("/api/mi-bandeja/grupos-colaborativos/{$this->grupoId}/adjuntos");

        $response->assertStatus(200)
            ->assertJsonPath('status', true);
    }

    // ==================== SUBIR ADJUNTO ====================

    public function test_creador_puede_subir_adjunto(): void
    {
        $archivo = UploadedFile::fake()->create('adjunto.pdf', 100, 'application/pdf');

        $response = $this->actingAs($this->creador)
            ->postJson("/api/mi-bandeja/grupos-colaborativos/{$this->grupoId}/adjuntos", [
                'archivo' => $archivo,
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('mi_bandeja_temp_grupo_archi_adjuntos', [
            'grupo_id' => $this->grupoId,
            'nombre_original' => 'adjunto.pdf',
        ]);
    }

    public function test_miembro_puede_subir_adjunto(): void
    {
        $archivo = UploadedFile::fake()->create('doc-miembro.docx', 50, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

        $response = $this->actingAs($this->miembro)
            ->postJson("/api/mi-bandeja/grupos-colaborativos/{$this->grupoId}/adjuntos", [
                'archivo' => $archivo,
            ]);

        $response->assertStatus(201);
    }

    // ==================== DESCARGAR ADJUNTO ====================

    public function test_descargar_adjunto(): void
    {
        $ruta = 'grupos/' . $this->grupoId . '/para-descargar.pdf';
        Storage::disk('mi_bandeja_temp')->put($ruta, 'contenido fake');

        $adjunto = MiBandejaTempGrupoArchiAdjunto::create([
            'grupo_id' => $this->grupoId,
            'archivo' => $ruta,
            'nombre_original' => 'para-descargar.pdf',
            'tipo_mime' => 'application/pdf',
            'peso' => 100,
            'hash_sha256' => hash('sha256', 'contenido fake'),
            'subido_por' => $this->creador->id,
        ]);

        $response = $this->actingAs($this->creador)
            ->getJson("/api/mi-bandeja/grupos-colaborativos/{$this->grupoId}/adjuntos/{$adjunto->id}/descargar");

        $response->assertStatus(200);
    }

    // ==================== ELIMINAR ADJUNTO ====================

    public function test_creador_puede_eliminar_adjunto(): void
    {
        $adjunto = MiBandejaTempGrupoArchiAdjunto::create([
            'grupo_id' => $this->grupoId,
            'archivo' => 'grupos/' . $this->grupoId . '/para-eliminar.pdf',
            'nombre_original' => 'para-eliminar.pdf',
            'tipo_mime' => 'application/pdf',
            'peso' => 100,
            'hash_sha256' => hash('sha256', 'test'),
            'subido_por' => $this->creador->id,
        ]);

        $response = $this->actingAs($this->creador)
            ->deleteJson("/api/mi-bandeja/grupos-colaborativos/{$this->grupoId}/adjuntos/{$adjunto->id}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('mi_bandeja_temp_grupo_archi_adjuntos', ['id' => $adjunto->id]);
    }

    // ==================== IDOR ====================

    public function test_usuario_ajeno_no_puede_subir_adjunto(): void
    {
        $archivo = UploadedFile::fake()->create('hack.pdf', 100, 'application/pdf');

        $response = $this->actingAs($this->ajeno)
            ->postJson("/api/mi-bandeja/grupos-colaborativos/{$this->grupoId}/adjuntos", [
                'archivo' => $archivo,
            ]);

        $response->assertStatus(403);
    }

    public function test_usuario_ajeno_no_puede_eliminar_adjunto(): void
    {
        $adjunto = MiBandejaTempGrupoArchiAdjunto::create([
            'grupo_id' => $this->grupoId,
            'archivo' => 'grupos/' . $this->grupoId . '/protegido.pdf',
            'nombre_original' => 'protegido.pdf',
            'tipo_mime' => 'application/pdf',
            'peso' => 100,
            'hash_sha256' => hash('sha256', 'protegido'),
            'subido_por' => $this->creador->id,
        ]);

        $response = $this->actingAs($this->ajeno)
            ->deleteJson("/api/mi-bandeja/grupos-colaborativos/{$this->grupoId}/adjuntos/{$adjunto->id}");

        $response->assertStatus(403);
    }

    public function test_usuario_ajeno_no_puede_descargar_adjunto(): void
    {
        $ruta = 'grupos/' . $this->grupoId . '/privado.pdf';
        Storage::disk('mi_bandeja_temp')->put($ruta, 'contenido privado');

        $adjunto = MiBandejaTempGrupoArchiAdjunto::create([
            'grupo_id' => $this->grupoId,
            'archivo' => $ruta,
            'nombre_original' => 'privado.pdf',
            'tipo_mime' => 'application/pdf',
            'peso' => 100,
            'hash_sha256' => hash('sha256', 'contenido privado'),
            'subido_por' => $this->creador->id,
        ]);

        $response = $this->actingAs($this->ajeno)
            ->getJson("/api/mi-bandeja/grupos-colaborativos/{$this->grupoId}/adjuntos/{$adjunto->id}/descargar");

        $response->assertStatus(403);
    }
}
