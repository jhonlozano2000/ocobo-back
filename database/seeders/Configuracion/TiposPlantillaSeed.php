<?php

namespace Database\Seeders\Configuracion;

use App\Models\Configuracion\ConfigLista;
use App\Models\Configuracion\ConfigListaDetalle;
use Illuminate\Database\Seeder;

/**
 * Lista «Tipos de plantilla» (Configuración → Listas): clasifica las plantillas de documentos, tanto las
 * cargadas (archivos para descargar) como las generadas con el editor. Se pueden agregar más desde Listas.
 *
 * Idempotente: se puede correr en una base ya sembrada sin duplicar.
 */
class TiposPlantillaSeed extends Seeder
{
    public const COD_LISTA = 'TipPlanti';

    /** codigo => nombre */
    public const TIPOS = [
        'ACTA' => 'Acta',
        'CERTIFICADO' => 'Certificado',
        'CIRCULAR' => 'Circular',
        'CONSTANCIA' => 'Constancia',
        'INFORME' => 'Informe',
        'MEMORANDO' => 'Memorando',
        'OFICIO' => 'Oficio',
        'RESOLUCION' => 'Resolución',
    ];

    public function run(): void
    {
        $lista = ConfigLista::firstOrCreate(['cod' => self::COD_LISTA], ['nombre' => 'Tipos de plantilla']);

        foreach (self::TIPOS as $codigo => $nombre) {
            ConfigListaDetalle::firstOrCreate(['lista_id' => $lista->id, 'codigo' => $codigo], ['nombre' => $nombre]);
        }
    }
}
