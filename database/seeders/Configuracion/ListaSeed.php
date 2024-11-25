<?php

namespace Database\Seeders\Configuracion;

use App\Models\Configuracion\ConfigLista;
use App\Models\Configuracion\ConfigListaDetalle;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ListaSeed extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $lista = ConfigLista::make(['nombre' => 'Tipos de documentos']);
        ConfigListaDetalle::make(['lista_id' => $lista->id, 'codigo' => 'NI', 'nombre' => 'Nit']);
        ConfigListaDetalle::make(['lista_id' => $lista->id, 'codigo' => 'CC', 'nombre' => 'Cedula']);
        ConfigListaDetalle::make(['lista_id' => $lista->id, 'codigo' => 'CE', 'nombre' => 'Cedula']);
        ConfigListaDetalle::make(['lista_id' => $lista->id, 'codigo' => 'RC', 'nombre' => 'Registro']);
        ConfigListaDetalle::make(['lista_id' => $lista->id, 'codigo' => 'TI', 'nombre' => 'Tarjeta']);
        ConfigListaDetalle::make(['lista_id' => $lista->id, 'codigo' => 'AS', 'nombre' => 'Adulto']);
        ConfigListaDetalle::make(['lista_id' => $lista->id, 'codigo' => 'MS', 'nombre' => 'Menor']);
        ConfigListaDetalle::make(['lista_id' => $lista->id, 'codigo' => 'NU', 'nombre' => 'Numero']);
        ConfigListaDetalle::make(['lista_id' => $lista->id, 'codigo' => 'RU', 'nombre' => 'Rut']);
        ConfigListaDetalle::make(['lista_id' => $lista->id, 'codigo' => 'PA', 'nombre' => 'PASAPORTE']);
    }
}
