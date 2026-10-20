<?php

namespace Database\Seeders\ControlAcceso;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

class UsersSeeder extends Seeder
{
    /** Contraseña del administrador inicial en local, si no se define ADMIN_PASSWORD. */
    private const PASSWORD_LOCAL = 'Ocobo123*';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::create(['divi_poli_id' => null, 'num_docu' => '123456', 'nombres' => 'administrador', 'apellidos' => 'administrador', 'email' => 'admin@admin.com', 'password' => $this->passwordInicial(), 'estado' => 1]);

        // Contraseña temporal: el administrador debe cambiarla en el primer login
        $user->forceFill(['debe_cambiar_password' => true])->save();
        $user->assignRole('Administrador');
    }

    /**
     * En producción la contraseña sale de ADMIN_PASSWORD y debe cumplir la política; nunca la de local.
     */
    private function passwordInicial(): string
    {
        $password = config('security.admin_password');

        if (! app()->environment('production')) {
            return $password ?: self::PASSWORD_LOCAL;
        }

        $validator = Validator::make(
            ['password' => $password],
            ['password' => ['required', 'string', Password::min(8)->mixedCase()->numbers()->symbols()]]
        );

        if ($validator->fails() || $password === self::PASSWORD_LOCAL) {
            throw new RuntimeException('En producción define ADMIN_PASSWORD (mínimo 8 caracteres, mayúsculas, minúsculas, números y símbolos) antes de ejecutar los seeders.');
        }

        return $password;
    }
}
