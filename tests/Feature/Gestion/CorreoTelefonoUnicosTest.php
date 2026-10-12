<?php

namespace Tests\Feature\Gestion;

use App\Models\Gestion\GestionTercero;
use App\Models\User;
use App\Rules\TelefonoUnico;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * El correo electrónico y el número de teléfono no se pueden repetir entre usuarios ni
 * entre terceros. El teléfono se compara solo por dígitos.
 */
class CorreoTelefonoUnicosTest extends TestCase
{
    use DatabaseTransactions;

    private const TERCEROS = '/api/gestion/terceros';

    private function valida(mixed $valor, ?int $ignorar = null): bool
    {
        return Validator::make(['tel' => $valor], ['tel' => [new TelefonoUnico('users', ['tel', 'movil'], $ignorar)]])->passes();
    }

    public function test_telefono_de_usuario_no_se_repite_entre_tel_y_movil(): void
    {
        $existente = User::factory()->create(['estado' => 1, 'tel' => '(601) 555-0101', 'movil' => '300 999 0101']);

        $this->assertFalse($this->valida('6015550101'));
        $this->assertFalse($this->valida('3009990101'), 'el móvil de otro usuario tampoco se puede usar como teléfono');
        $this->assertTrue($this->valida('6015550101', $existente->id), 'al editar, su propio número no cuenta');
        $this->assertTrue($this->valida('3110000000'));
        $this->assertTrue($this->valida(null));
    }

    public function test_tercero_no_repite_correo_ni_telefono(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $user = User::factory()->create(['estado' => 1]);
        foreach (['Crear', 'Editar'] as $accion) {
            $user->givePermissionTo(Permission::findOrCreate('Gestión - Terceros -> '.$accion, 'web'));
        }
        $existente = GestionTercero::create([
            'num_docu_nit' => 'UNQ-001', 'nom_razo_soci' => 'Existente', 'tipo' => 'Natural',
            'email' => 'unico-tercero@example.com', 'telefono' => '310 555 0101',
        ]);
        $base = ['nom_razo_soci' => 'Nuevo', 'tipo' => 'Natural'];

        $this->actingAs($user)->postJson(self::TERCEROS, $base + ['num_docu_nit' => 'UNQ-002', 'email' => 'UNICO-TERCERO@example.com'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->postJson(self::TERCEROS, $base + ['num_docu_nit' => 'UNQ-003', 'telefono' => '3105550101'])
            ->assertUnprocessable()->assertJsonValidationErrors('telefono');

        // Editar el mismo tercero conservando sus datos sí se permite
        $this->putJson(self::TERCEROS.'/'.$existente->id, [
            'num_docu_nit' => 'UNQ-001', 'nom_razo_soci' => 'Existente editado', 'tipo' => 'Natural',
            'email' => 'unico-tercero@example.com', 'telefono' => '310 555 0101',
        ])->assertOk();
    }
}
