<?php

namespace Tests\Feature\MiBandeja;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests feature M19 Mi Bandeja — Mi Disco (file manager personal).
 *
 * Cubre:
 * - 19.28: CRUD carpetas, upload/download/delete archivos, IDOR
 *
 * @author  Jhon Javer Lozano Arce
 * @date    2026-09-14
 */
class MiDiscoTest extends TestCase
{
    protected User $user;

    protected User $userAjeno;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->user = User::factory()->create(['email' => 'mi-disco-user-' . uniqid() . '@test.com']);
        $this->userAjeno = User::factory()->create(['email' => 'mi-disco-ajeno-' . uniqid() . '@test.com']);
    }

    // ==================== AUTENTICACIÓN ====================

    public function test_requiere_autenticacion(): void
    {
        $this->getJson('/api/mi-bandeja/mi-disco/')->assertStatus(401);
        $this->postJson('/api/mi-bandeja/mi-disco/carpetas', [])->assertStatus(401);
    }

    // ==================== INDEX (listar) ====================

    public function test_lista_carpetas_y_archivos_del_usuario(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/mi-bandeja/mi-disco/');

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonStructure([
                'data' => ['carpetas', 'archivos', 'breadcrumb'],
            ]);
    }

    public function test_no_muestra_carpetas_de_otro_usuario(): void
    {
        Sanctum::actingAs($this->user);

        // Crear carpeta como usuario ajeno directamente en DB
        \App\Models\MiDisco\MiDiscoCarpeta::create([
            'nombre' => 'Carpeta Privada',
            'user_id' => $this->userAjeno->id,
        ]);

        $response = $this->getJson('/api/mi-bandeja/mi-disco/');

        $carpetas = $response->json('data.carpetas');
        $this->assertEmpty($carpetas);
    }

    // ==================== CREAR CARPETA ====================

    public function test_crear_carpeta_raiz(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/mi-bandeja/mi-disco/carpetas', [
            'nombre' => 'Documentos',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', true);

        $this->assertDatabaseHas('mi_disco_carpetas', [
            'nombre' => 'Documentos',
            'user_id' => $this->user->id,
            'carpeta_padre_id' => null,
        ]);
    }

    public function test_crear_subcarpeta(): void
    {
        Sanctum::actingAs($this->user);

        $padre = \App\Models\MiDisco\MiDiscoCarpeta::create([
            'nombre' => 'Padre',
            'user_id' => $this->user->id,
        ]);

        $response = $this->postJson('/api/mi-bandeja/mi-disco/carpetas', [
            'nombre' => 'Hija',
            'carpeta_padre_id' => $padre->id,
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('mi_disco_carpetas', [
            'nombre' => 'Hija',
            'carpeta_padre_id' => $padre->id,
            'user_id' => $this->user->id,
        ]);
    }

    public function test_crear_carpeta_falla_sin_nombre(): void
    {
        Sanctum::actingAs($this->user);

        $this->postJson('/api/mi-bandeja/mi-disco/carpetas', [])
            ->assertStatus(422);
    }

    // ==================== ELIMINAR CARPETA ====================

    public function test_eliminar_carpeta_vacia(): void
    {
        Sanctum::actingAs($this->user);

        $carpeta = \App\Models\MiDisco\MiDiscoCarpeta::create([
            'nombre' => 'ParaEliminar',
            'user_id' => $this->user->id,
        ]);

        $this->deleteJson("/api/mi-bandeja/mi-disco/carpetas/{$carpeta->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('mi_disco_carpetas', ['id' => $carpeta->id]);
    }

    public function test_eliminar_carpeta_con_subcarpetas_y_archivos(): void
    {
        Sanctum::actingAs($this->user);

        $padre = \App\Models\MiDisco\MiDiscoCarpeta::create([
            'nombre' => 'Padre',
            'user_id' => $this->user->id,
        ]);

        \App\Models\MiDisco\MiDiscoCarpeta::create([
            'nombre' => 'Hija',
            'carpeta_padre_id' => $padre->id,
            'user_id' => $this->user->id,
        ]);

        $this->deleteJson("/api/mi-bandeja/mi-disco/carpetas/{$padre->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('mi_disco_carpetas', ['id' => $padre->id]);
        $this->assertDatabaseMissing('mi_disco_carpetas', [
            'nombre' => 'Hija',
            'carpeta_padre_id' => $padre->id,
        ]);
    }

    public function test_no_puede_eliminar_carpeta_de_otro_usuario(): void
    {
        Sanctum::actingAs($this->user);

        $carpetaAjena = \App\Models\MiDisco\MiDiscoCarpeta::create([
            'nombre' => 'Ajena',
            'user_id' => $this->userAjeno->id,
        ]);

        $this->deleteJson("/api/mi-bandeja/mi-disco/carpetas/{$carpetaAjena->id}")
            ->assertStatus(404);
    }

    // ==================== SUBIR ARCHIVO ====================

    public function test_subir_archivo(): void
    {
        Sanctum::actingAs($this->user);

        $archivo = UploadedFile::fake()->create('documento.pdf', 100, 'application/pdf');

        $response = $this->postJson('/api/mi-bandeja/mi-disco/archivos', [
            'archivos' => [$archivo],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', true);

        $this->assertDatabaseHas('mi_disco_archivos', [
            'nombre_original' => 'documento.pdf',
            'user_id' => $this->user->id,
            'mime_type' => 'application/pdf',
        ]);
    }

    public function test_subir_multiples_archivos(): void
    {
        Sanctum::actingAs($this->user);

        $archivos = [
            UploadedFile::fake()->create('a.pdf', 50, 'application/pdf'),
            UploadedFile::fake()->create('b.docx', 80, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ];

        $response = $this->postJson('/api/mi-bandeja/mi-disco/archivos', [
            'archivos' => $archivos,
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('mi_disco_archivos', [
            'nombre_original' => 'a.pdf',
            'user_id' => $this->user->id,
        ]);
        $this->assertDatabaseHas('mi_disco_archivos', [
            'nombre_original' => 'b.docx',
            'user_id' => $this->user->id,
        ]);
    }

    public function test_subir_archivo_falla_sin_archivos(): void
    {
        Sanctum::actingAs($this->user);

        $this->postJson('/api/mi-bandeja/mi-disco/archivos', [])
            ->assertStatus(422);
    }

    // ==================== DESCARGAR ARCHIVO ====================

    public function test_descargar_archivo(): void
    {
        Sanctum::actingAs($this->user);

        // Simular archivo en disco
        $ruta = 'mi_disco/' . $this->user->id . '/test-file.pdf';
        Storage::disk('local')->put($ruta, 'contenido fake');

        $archivo = \App\Models\MiDisco\MiDiscoArchivo::create([
            'nombre' => 'test-file.pdf',
            'nombre_original' => 'documento.pdf',
            'user_id' => $this->user->id,
            'ruta_almacenamiento' => $ruta,
            'peso' => 100,
            'mime_type' => 'application/pdf',
            'hash_sha256' => hash('sha256', 'contenido fake'),
        ]);

        $response = $this->getJson("/api/mi-bandeja/mi-disco/archivos/{$archivo->id}/download");

        $response->assertStatus(200);
    }

    public function test_no_puede_descargar_archivo_de_otro_usuario(): void
    {
        Sanctum::actingAs($this->user);

        $archivoAjeno = \App\Models\MiDisco\MiDiscoArchivo::create([
            'nombre' => 'ajeno.pdf',
            'nombre_original' => 'ajeno.pdf',
            'user_id' => $this->userAjeno->id,
            'ruta_almacenamiento' => 'mi_disco/' . $this->userAjeno->id . '/ajeno.pdf',
            'peso' => 100,
            'mime_type' => 'application/pdf',
            'hash_sha256' => 'abc123',
        ]);

        $this->getJson("/api/mi-bandeja/mi-disco/archivos/{$archivoAjeno->id}/download")
            ->assertStatus(404);
    }

    // ==================== ELIMINAR ARCHIVO ====================

    public function test_eliminar_archivo(): void
    {
        Sanctum::actingAs($this->user);

        $archivo = \App\Models\MiDisco\MiDiscoArchivo::create([
            'nombre' => 'para-eliminar.pdf',
            'nombre_original' => 'para-eliminar.pdf',
            'user_id' => $this->user->id,
            'ruta_almacenamiento' => 'mi_disco/' . $this->user->id . '/para-eliminar.pdf',
            'peso' => 100,
            'mime_type' => 'application/pdf',
            'hash_sha256' => 'abc123',
        ]);

        $this->deleteJson("/api/mi-bandeja/mi-disco/archivos/{$archivo->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('mi_disco_archivos', ['id' => $archivo->id]);
    }

    public function test_no_puede_eliminar_archivo_de_otro_usuario(): void
    {
        Sanctum::actingAs($this->user);

        $archivoAjeno = \App\Models\MiDisco\MiDiscoArchivo::create([
            'nombre' => 'ajeno.pdf',
            'nombre_original' => 'ajeno.pdf',
            'user_id' => $this->userAjeno->id,
            'ruta_almacenamiento' => 'mi_disco/' . $this->userAjeno->id . '/ajeno.pdf',
            'peso' => 100,
            'mime_type' => 'application/pdf',
            'hash_sha256' => 'abc123',
        ]);

        $this->deleteJson("/api/mi-bandeja/mi-disco/archivos/{$archivoAjeno->id}")
            ->assertStatus(404);
    }

    // ==================== BREADCRUMB ====================

    public function test_breadcrumb_se_construye_correctamente(): void
    {
        Sanctum::actingAs($this->user);

        $raiz = \App\Models\MiDisco\MiDiscoCarpeta::create([
            'nombre' => 'Raiz',
            'user_id' => $this->user->id,
        ]);

        $hija = \App\Models\MiDisco\MiDiscoCarpeta::create([
            'nombre' => 'Hija',
            'carpeta_padre_id' => $raiz->id,
            'user_id' => $this->user->id,
        ]);

        $niet = \App\Models\MiDisco\MiDiscoCarpeta::create([
            'nombre' => 'Nieto',
            'carpeta_padre_id' => $hija->id,
            'user_id' => $this->user->id,
        ]);

        $response = $this->getJson("/api/mi-bandeja/mi-disco/?carpeta_id={$niet->id}");

        $breadcrumb = $response->json('data.breadcrumb');
        $this->assertCount(3, $breadcrumb);
        $this->assertEquals('Raiz', $breadcrumb[0]['nombre']);
        $this->assertEquals('Hija', $breadcrumb[1]['nombre']);
        $this->assertEquals('Nieto', $breadcrumb[2]['nombre']);
    }
}
