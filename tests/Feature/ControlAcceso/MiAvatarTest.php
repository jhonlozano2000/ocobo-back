<?php

namespace Tests\Feature\ControlAcceso;

use App\Helpers\ArchivoHelper;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Cada usuario cambia o quita su propia foto desde Mi perfil, sin permisos de gestión de usuarios.
 */
class MiAvatarTest extends TestCase
{
    use DatabaseTransactions;

    private const URL = '/api/control-acceso/user/avatar';

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('avatars');
        // ArchivoHelper guarda los discos en caché estática: que use el disco falso
        (new \ReflectionProperty(ArchivoHelper::class, 'storageCache'))->setValue(null, []);
        $this->user = User::factory()->create(['estado' => 1]);
    }

    public function test_sube_su_foto_reemplaza_la_anterior_y_devuelve_la_url(): void
    {
        Storage::disk('avatars')->put('vieja.png', 'x');
        $this->user->forceFill(['avatar' => 'vieja.png'])->save();

        $url = $this->actingAs($this->user)
            ->post(self::URL, ['avatar' => UploadedFile::fake()->image('yo.png', 200, 200)], ['Accept' => 'application/json'])
            ->assertOk()
            ->json('data.avatar_url');

        $nueva = $this->user->fresh()->avatar;
        $this->assertNotSame('vieja.png', $nueva);
        Storage::disk('avatars')->assertExists($nueva);
        Storage::disk('avatars')->assertMissing('vieja.png');
        $this->assertSame($this->user->fresh()->avatar_url, $url);
        // Relativa: el navegador la pide por el proxy del front, con la cookie de sesión
        $this->assertSame('/api/archivo/documentos/ver?disk=avatars&path='.$nueva, $url);
    }

    public function test_la_url_del_usuario_en_sesion_llega_sin_escapar(): void
    {
        $this->user->forceFill(['avatar' => 'yo.png'])->save();

        $datos = (new UserResource($this->user))->toArray(request());

        $this->assertSame('/api/archivo/documentos/ver?disk=avatars&path=yo.png', $datos['avatar_url']);
    }

    public function test_su_perfil_completo_trae_la_url_de_la_foto(): void
    {
        $this->user->forceFill(['avatar' => 'yo.png'])->save();

        $this->actingAs($this->user)
            ->getJson("/api/control-acceso/users/{$this->user->id}/perfil-completo")
            ->assertOk()
            ->assertJsonPath('data.user.avatar_url', '/api/archivo/documentos/ver?disk=avatars&path=yo.png');
    }

    public function test_rechaza_lo_que_no_es_png_o_jpg_o_pesa_mas_de_2mb(): void
    {
        $this->actingAs($this->user)
            ->post(self::URL, ['avatar' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertUnprocessable();
        $this->actingAs($this->user)
            ->post(self::URL, ['avatar' => UploadedFile::fake()->image('grande.jpg')->size(3000)], ['Accept' => 'application/json'])
            ->assertUnprocessable();
        $this->assertNull($this->user->fresh()->avatar);
    }

    public function test_quita_su_foto(): void
    {
        Storage::disk('avatars')->put('mia.png', 'x');
        $this->user->forceFill(['avatar' => 'mia.png'])->save();

        $this->actingAs($this->user)->deleteJson(self::URL)->assertOk()->assertJsonPath('data.avatar_url', null);

        $this->assertNull($this->user->fresh()->avatar);
        Storage::disk('avatars')->assertMissing('mia.png');
    }

    public function test_sin_sesion_no_cambia_nada(): void
    {
        $this->post(self::URL, ['avatar' => UploadedFile::fake()->image('yo.png')], ['Accept' => 'application/json'])->assertUnauthorized();
    }
}
