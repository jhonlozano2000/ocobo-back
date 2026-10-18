<?php

namespace App\Models\Workflows\Concerns;

/**
 * M19-08: campos de diseño que son texto plano (títulos, descripciones, instrucciones).
 * Se quitan las etiquetas HTML al guardar, venga del canvas, del CRUD o de duplicar;
 * así nada llega como HTML a un render que lo interprete.
 *
 * El modelo declara: protected array $textoPlano = ['titulo', ...];
 */
trait TextoPlano
{
    protected static function bootTextoPlano(): void
    {
        static::saving(function ($modelo) {
            foreach ($modelo->textoPlano as $campo) {
                $valor = $modelo->getAttribute($campo);
                if (is_string($valor) && $modelo->isDirty($campo)) {
                    $modelo->setAttribute($campo, trim(strip_tags($valor)));
                }
            }
        });
    }
}
