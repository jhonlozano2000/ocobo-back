<?php

namespace Tests\Feature\ControlAcceso;

use App\Http\Requests\ControlAcceso\StoreUserRequest;
use App\Http\Requests\ControlAcceso\UpdatePasswordRequest;
use App\Http\Requests\ControlAcceso\UpdateUserRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * RBAC-004: al crear o editar un usuario bastaban 6 caracteres, mientras que el cambio
 * y la recuperación de contraseña exigen 8 con mayúsculas, minúsculas, números y símbolos.
 */
class PoliticaContrasenaUsuariosTest extends TestCase
{
    private function valida(string $request, string $password): bool
    {
        $regla = (new $request)->rules()['password'];

        return Validator::make(['password' => $password], ['password' => $regla])->passes();
    }

    public function test_crear_y_editar_exigen_la_misma_politica_que_el_cambio_de_contrasena(): void
    {
        foreach ([StoreUserRequest::class, UpdateUserRequest::class] as $request) {
            $this->assertFalse($this->valida($request, 'abc123'), $request);
            $this->assertFalse($this->valida($request, 'abcdefgh1!'), $request);
            $this->assertTrue($this->valida($request, 'Abcdefg1!'), $request);
        }
    }

    public function test_los_mensajes_personalizados_de_la_politica_se_aplican(): void
    {
        foreach ([StoreUserRequest::class, UpdateUserRequest::class, UpdatePasswordRequest::class] as $request) {
            $form = new $request;
            $errores = Validator::make(['password' => 'abcdefgh'], ['password' => $form->rules()['password']], $form->messages())
                ->errors()->get('password');

            $this->assertStringContainsString('al menos una letra mayúscula y una minúscula', implode(' ', $errores), $request);
        }
    }
}
