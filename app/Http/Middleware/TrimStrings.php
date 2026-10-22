<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\TrimStrings as Middleware;

class TrimStrings extends Middleware
{
    /**
     * The names of the attributes that should not be trimmed.
     *
     * @var array<int, string>
     */
    protected $except = [
        'current_password',
        'password',
        'password_confirmation',
        // Contenido del editor (JSON de TipTap): los espacios al borde de cada tramo de texto son parte del
        // documento («Hola » + «mundo» en negrita); recortarlos los pegaba al guardar y al exportar
        'contenido.*',
    ];
}
