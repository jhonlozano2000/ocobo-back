<?php

namespace Tests\Feature\MiBandeja;

use App\Models\MiBandeja\TempDocumentosRecibidos\Documento;
use App\Models\MiBandeja\TempDocumentosRecibidos\Contenido;
use App\Models\MiBandeja\TempDocumentosRecibidos\DocumentoUsuario;
use App\Models\User;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use App\Models\Configuracion\ConfigLista;
use App\Models\Configuracion\ConfigListaDetalle;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Tests feature M19 Mi Bandeja — Comunicaciones Recibidas / Mis Documentos.
 *
 * Cubre:
 * - 19.29: Listar documentos del usuario (Mis Documentos)
 * - 19.31: CRUD documentos colaborativos (Comunicación Recibida)
 *
 * Nota: El editor colaborativo (Yjs + Reverb) no se testea aquí.
 * Solo se cubren las operaciones CRUD del backend.
 *
 * @author  Jhon Javer Lozano Arce
 * @date    2026-09-14
 */
class ComunicacionesRecibidasDocumentoTest extends TestCase
{
    protected User $user;

    protected User $userAsignado;

    protected User $userAjeno;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->user = User::factory()->create(['email' => 'doc-creador-' . uniqid() . '@test.com']);
        $this->userAsignado = User::factory()->create(['email' => 'doc-asignado-' . uniqid() . '@test.com']);
        $this->userAjeno = User::factory()->create(['email' => 'doc-ajeno-' . uniqid() . '@test.com']);
    }

    // ==================== INDEX (Mis Documentos) ====================

    public function test_listar_documentos_del_usuario(): void
    {
        // Crear documento como creador
        $doc = Documento::create([
            'user_id' => $this->user->id,
            'titulo' => 'Doc de prueba',
            'estado' => 'borrador',
        ]);

        Contenido::create([
            'documento_id' => $doc->id,
            'contenido' => '',
            'contenido_yjs' => '',
            'hash_sha256' => hash('sha256', ''),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/mi-bandeja/comunicaciones-recibidas/documentos');

        $response->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertContains((int) $doc->id, $ids);
    }

    public function test_usuario_ve_documentos_asignados(): void
    {
        // Crear documento como otro usuario
        $doc = Documento::create([
            'user_id' => $this->userAjeno->id,
            'titulo' => 'Doc asignado',
            'estado' => 'borrador',
        ]);

        Contenido::create([
            'documento_id' => $doc->id,
            'contenido' => '',
            'contenido_yjs' => '',
            'hash_sha256' => hash('sha256', ''),
        ]);

        // Asignar al usuario
        DocumentoUsuario::create([
            'documento_id' => $doc->id,
            'user_id' => $this->user->id,
            'rol' => 'responsable',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/mi-bandeja/comunicaciones-recibidas/documentos');

        $response->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertContains((int) $doc->id, $ids);
    }

    public function test_usuario_no_ve_documentos_ajenos(): void
    {
        $doc = Documento::create([
            'user_id' => $this->userAjeno->id,
            'titulo' => 'Doc privado',
            'estado' => 'borrador',
        ]);

        Contenido::create([
            'documento_id' => $doc->id,
            'contenido' => '',
            'contenido_yjs' => '',
            'hash_sha256' => hash('sha256', ''),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/mi-bandeja/comunicaciones-recibidas/documentos');

        $response->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertNotContains((int) $doc->id, $ids);
    }

    // ==================== STORE (Crear documento) ====================

    public function test_crear_documento(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/mi-bandeja/comunicaciones-recibidas/documentos', [
                'titulo' => 'Nuevo documento',
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('mi_bandeja_temp_reci_documentos', [
            'user_id' => $this->user->id,
            'titulo' => 'Nuevo documento',
            'estado' => 'borrador',
        ]);
    }

    public function test_crear_documento_falla_sin_titulo(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/mi-bandeja/comunicaciones-recibidas/documentos', [])
            ->assertStatus(422);
    }

    // ==================== SHOW (Ver documento) ====================

    public function test_ver_documento_creador(): void
    {
        $doc = Documento::create([
            'user_id' => $this->user->id,
            'titulo' => 'Doc visible',
            'estado' => 'borrador',
        ]);

        Contenido::create([
            'documento_id' => $doc->id,
            'contenido' => '',
            'contenido_yjs' => '',
            'hash_sha256' => hash('sha256', ''),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/mi-bandeja/comunicaciones-recibidas/documentos/{$doc->id}");

        $response->assertStatus(200);
    }

    public function test_ver_documento_asignado(): void
    {
        $doc = Documento::create([
            'user_id' => $this->userAjeno->id,
            'titulo' => 'Doc de otro',
            'estado' => 'borrador',
        ]);

        Contenido::create([
            'documento_id' => $doc->id,
            'contenido' => '',
            'contenido_yjs' => '',
            'hash_sha256' => hash('sha256', ''),
        ]);

        DocumentoUsuario::create([
            'documento_id' => $doc->id,
            'user_id' => $this->user->id,
            'rol' => 'firmante',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/mi-bandeja/comunicaciones-recibidas/documentos/{$doc->id}");

        $response->assertStatus(200);
    }

    public function test_usuario_ajeno_no_puede_ver_documento(): void
    {
        $doc = Documento::create([
            'user_id' => $this->userAjeno->id,
            'titulo' => 'Privado',
            'estado' => 'borrador',
        ]);

        Contenido::create([
            'documento_id' => $doc->id,
            'contenido' => '',
            'contenido_yjs' => '',
            'hash_sha256' => hash('sha256', ''),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/mi-bandeja/comunicaciones-recibidas/documentos/{$doc->id}");

        $response->assertStatus(403);
    }

    // ==================== UPDATE ====================

    public function testActualizarDocumentoCreador(): void
    {
        $doc = Documento::create([
            'user_id' => $this->user->id,
            'titulo' => 'Original',
            'estado' => 'borrador',
        ]);

        $response = $this->actingAs($this->user)
            ->putJson("/api/mi-bandeja/comunicaciones-recibidas/documentos/{$doc->id}", [
                'titulo' => 'Actualizado',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('mi_bandeja_temp_reci_documentos', [
            'id' => $doc->id,
            'titulo' => 'Actualizado',
        ]);
    }

    // ==================== DELETE ====================

    public function testEliminarDocumentoCreador(): void
    {
        $doc = Documento::create([
            'user_id' => $this->user->id,
            'titulo' => 'Para eliminar',
            'estado' => 'borrador',
        ]);

        Contenido::create([
            'documento_id' => $doc->id,
            'contenido' => '',
            'contenido_yjs' => '',
            'hash_sha256' => hash('sha256', ''),
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/mi-bandeja/comunicaciones-recibidas/documentos/{$doc->id}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('mi_bandeja_temp_reci_documentos', ['id' => $doc->id]);
    }

    public function testUsuarioAjenoNoPuedeEliminarDocumento(): void
    {
        $doc = Documento::create([
            'user_id' => $this->userAjeno->id,
            'titulo' => 'No mío',
            'estado' => 'borrador',
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/mi-bandeja/comunicaciones-recibidas/documentos/{$doc->id}");

        $response->assertStatus(403);
    }

    // ==================== CONTENIDO ====================

    public function testObtenerContenido(): void
    {
        $doc = Documento::create([
            'user_id' => $this->user->id,
            'titulo' => 'Con contenido',
            'estado' => 'borrador',
        ]);

        Contenido::create([
            'documento_id' => $doc->id,
            'contenido' => 'contenido-yjs-base64',
            'contenido_yjs' => 'contenido-yjs-base64',
            'hash_sha256' => hash('sha256', 'test'),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/mi-bandeja/comunicaciones-recibidas/documentos/{$doc->id}/contenido");

        $response->assertStatus(200);
    }
}
