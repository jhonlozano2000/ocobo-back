<?php

namespace Tests\Feature\Configuracion;

use App\Models\Configuracion\ConfigLista;
use Database\Seeders\Configuracion\TiposPlantillaSeed;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class TiposPlantillaSeedTest extends TestCase
{
    use DatabaseTransactions;

    public function test_crea_la_lista_de_tipos_de_plantilla_sin_duplicar(): void
    {
        $this->seed(TiposPlantillaSeed::class);
        $this->seed(TiposPlantillaSeed::class);

        $listas = ConfigLista::where('cod', TiposPlantillaSeed::COD_LISTA)->get();
        $this->assertCount(1, $listas);
        $this->assertSame(
            array_values(TiposPlantillaSeed::TIPOS),
            $listas->first()->detalles()->orderBy('nombre')->pluck('nombre')->all()
        );
    }
}
