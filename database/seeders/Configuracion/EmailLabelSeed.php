<?php

namespace Database\Seeders\Configuracion;

use App\Models\Configuracion\ConfigLista;
use App\Models\Configuracion\ConfigListaDetalle;
use Illuminate\Database\Seeder;

class EmailLabelSeed extends Seeder
{
    public function run(): void
    {
        $lista = ConfigLista::create([
            'cod'    => 'LabEmail',
            'nombre' => 'Labels Email',
            'estado' => true,
        ]);

        ConfigListaDetalle::create(['lista_id' => $lista->id, 'codigo' => 'personal', 'nombre' => 'Personal', 'estado' => true]);
        ConfigListaDetalle::create(['lista_id' => $lista->id, 'codigo' => 'company', 'nombre' => 'Empresa', 'estado' => true]);
        ConfigListaDetalle::create(['lista_id' => $lista->id, 'codigo' => 'important', 'nombre' => 'Importante', 'estado' => true]);
        ConfigListaDetalle::create(['lista_id' => $lista->id, 'codigo' => 'private', 'nombre' => 'Privado', 'estado' => true]);
    }
}
