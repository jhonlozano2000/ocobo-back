<?php

namespace Database\Seeders\Configuracion;

use App\Models\Configuracion\ConfigLista;
use App\Models\Configuracion\ConfigListaDetalle;
use Illuminate\Database\Seeder;

class PqrsTipoSeed extends Seeder
{
    public function run(): void
    {
        $lista = ConfigLista::create([
            'cod' => 'TipPQRS',
            'nombre' => 'Tipos de PQRS (Ley 1755/2015)',
            'descripcion' => 'Clasificación de peticiones, quejas, reclamos y sugerencias según Ley 1755 de 2015'
        ]);

        $tipos = [
            ['codigo' => 'PET', 'nombre' => 'Petición', 'dias_termino' => 15],
            ['codigo' => 'QUE', 'nombre' => 'Queja', 'dias_termino' => 15],
            ['codigo' => 'REC', 'nombre' => 'Reclamo', 'dias_termino' => 15],
            ['codigo' => 'SUG', 'nombre' => 'Sugerencia', 'dias_termino' => 15],
            ['codigo' => 'DEN', 'nombre' => 'Denuncia', 'dias_termino' => 15],
            ['codigo' => 'INF', 'nombre' => 'Información', 'dias_termino' => 10],
            ['codigo' => 'CON', 'nombre' => 'Consulta', 'dias_termino' => 30],
            ['codigo' => 'SOL', 'nombre' => 'Solicitud', 'dias_termino' => 15],
            ['codigo' => 'COR', 'nombre' => 'Corrección', 'dias_termino' => 15],
            ['codigo' => 'FEL', 'nombre' => 'Felicitación', 'dias_termino' => 15],
        ];

        foreach ($tipos as $tipo) {
            ConfigListaDetalle::create([
                'lista_id' => $lista->id,
                'codigo' => $tipo['codigo'],
                'nombre' => $tipo['nombre'],
                'descripcion' => "Término: {$tipo['dias_termino']} días hábiles",
                'activo' => true,
            ]);
        }

        $this->command->info("Lista TipPQRS creada con ID: {$lista->id}");
    }
}