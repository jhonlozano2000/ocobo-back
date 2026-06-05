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
        $lista = ConfigLista::create(['cod' => 'TipDocu', 'nombre' => 'Tipos de documentos']);
        ConfigListaDetalle::create(['lista_id' => $lista->id, 'codigo' => 'NI', 'nombre' => 'Nit']);
        ConfigListaDetalle::create(['lista_id' => $lista->id, 'codigo' => 'CC', 'nombre' => 'Cedula']);
        ConfigListaDetalle::create(['lista_id' => $lista->id, 'codigo' => 'CE', 'nombre' => 'Cedula']);
        ConfigListaDetalle::create(['lista_id' => $lista->id, 'codigo' => 'RC', 'nombre' => 'Registro']);
        ConfigListaDetalle::create(['lista_id' => $lista->id, 'codigo' => 'TI', 'nombre' => 'Tarjeta']);
        ConfigListaDetalle::create(['lista_id' => $lista->id, 'codigo' => 'AS', 'nombre' => 'Adulto']);
        ConfigListaDetalle::create(['lista_id' => $lista->id, 'codigo' => 'MS', 'nombre' => 'Menor']);
        ConfigListaDetalle::create(['lista_id' => $lista->id, 'codigo' => 'NU', 'nombre' => 'Numero']);
        ConfigListaDetalle::create(['lista_id' => $lista->id, 'codigo' => 'RU', 'nombre' => 'Rut']);
        ConfigListaDetalle::create(['lista_id' => $lista->id, 'codigo' => 'PA', 'nombre' => 'PASAPORTE']);

        $lista = ConfigLista::create(['cod' => 'TipComu', 'nombre' => 'Tipos de Comunicaciones']);
        ConfigListaDetalle::create(['lista_id' => $lista->id, 'codigo' => '', 'nombre' => 'Comunicaciones recibida']);
        ConfigListaDetalle::create(['lista_id' => $lista->id, 'codigo' => '', 'nombre' => 'Comunicaciones enviada']);
        ConfigListaDetalle::create(['lista_id' => $lista->id, 'codigo' => '', 'nombre' => 'Comunicaciones interna']);
        ConfigListaDetalle::create(['lista_id' => $lista->id, 'codigo' => '', 'nombre' => 'PQRSF']);

        $lista = ConfigLista::create(['cod' => 'TipRecep', 'nombre' => 'Tipos de Recepción']);
        ConfigListaDetalle::create(['lista_id' => $lista->id, 'codigo' => '', 'nombre' => 'Buzon de Sugerencias']);
        ConfigListaDetalle::create(['lista_id' => $lista->id, 'codigo' => '', 'nombre' => 'Correo Certificado']);
        ConfigListaDetalle::create(['lista_id' => $lista->id, 'codigo' => '', 'nombre' => 'Correo Electrónico']);
        ConfigListaDetalle::create(['lista_id' => $lista->id, 'codigo' => '', 'nombre' => 'Correo Tradicional']);
        ConfigListaDetalle::create(['lista_id' => $lista->id, 'codigo' => '', 'nombre' => 'Mensajero']);
        ConfigListaDetalle::create(['lista_id' => $lista->id, 'codigo' => '', 'nombre' => 'Verbal']);
        ConfigListaDetalle::create(['lista_id' => $lista->id, 'codigo' => '', 'nombre' => 'Personal']);
        ConfigListaDetalle::create(['lista_id' => $lista->id, 'codigo' => '', 'nombre' => 'Pagina Web']);

        $lista = ConfigLista::create(['cod' => 'TipProce', 'nombre' => 'Tipos de Procesos']);
        ConfigListaDetalle::create(['lista_id' => $lista->id, 'codigo' => '', 'nombre' => 'Proceso comunicaciones recibida']);
        ConfigListaDetalle::create(['lista_id' => $lista->id, 'codigo' => '', 'nombre' => 'Proceso comunicaciones enviada']);
        ConfigListaDetalle::create(['lista_id' => $lista->id, 'codigo' => '', 'nombre' => 'Proceso comunicaciones interna']);
        ConfigListaDetalle::create(['lista_id' => $lista->id, 'codigo' => '', 'nombre' => 'Proceso Expedientes']);
        ConfigListaDetalle::create(['lista_id' => $lista->id, 'codigo' => '', 'nombre' => 'Proceso PQRSF']);

        $lista = ConfigLista::create(['cod' => 'TipRespues', 'nombre' => 'Tipos de respuesta']);
        ConfigListaDetalle::create(['lista_id' => $lista->id, 'codigo' => '', 'nombre' => 'Aprobada']);
        ConfigListaDetalle::create(['lista_id' => $lista->id, 'codigo' => '', 'nombre' => 'Rechazado']);
        ConfigListaDetalle::create(['lista_id' => $lista->id, 'codigo' => '', 'nombre' => 'En proceso']);
        ConfigListaDetalle::create(['lista_id' => $lista->id, 'codigo' => '', 'nombre' => 'En espera']);
        ConfigListaDetalle::create(['lista_id' => $lista->id, 'codigo' => '', 'nombre' => 'En revisión']);
        ConfigListaDetalle::create(['lista_id' => $lista->id, 'codigo' => '', 'nombre' => 'En espera']);
        ConfigListaDetalle::create(['lista_id' => $lista->id, 'codigo' => '', 'nombre' => 'En espera']);

        $lista = ConfigLista::create(['cod' => 'TipSoli', 'nombre' => 'Tipos de solicitud']);
        ConfigListaDetalle::create(['lista_id' => $lista->id, 'codigo' => '', 'nombre' => 'Solicitud de información']);
        ConfigListaDetalle::create(['lista_id' => $lista->id, 'codigo' => '', 'nombre' => 'Solicitud de servicio']);
        ConfigListaDetalle::create(['lista_id' => $lista->id, 'codigo' => '', 'nombre' => 'Solicitud de trámite']);
        ConfigListaDetalle::create(['lista_id' => $lista->id, 'codigo' => '', 'nombre' => 'Solicitud de recurso']);
        ConfigListaDetalle::create(['lista_id' => $lista->id, 'codigo' => '', 'nombre' => 'Solicitud de recurso']);

        $lista = ConfigLista::create(['cod' => 'TipoPQRSF', 'nombre' => 'Tipos de PQRS']);
        ConfigListaDetalle::create(['lista_id' => $lista->id, 'codigo' => '', 'nombre' => 'Pregunta']);
        ConfigListaDetalle::create(['lista_id' => $lista->id, 'codigo' => '', 'nombre' => 'Queja']);
        ConfigListaDetalle::create(['lista_id' => $lista->id, 'codigo' => '', 'nombre' => 'Reclamo']);
        ConfigListaDetalle::create(['lista_id' => $lista->id, 'codigo' => '', 'nombre' => 'Solicitud']);
        ConfigListaDetalle::create(['lista_id' => $lista->id, 'codigo' => '', 'nombre' => 'Felicitaciones']);

        $lista = ConfigLista::create(['cod' => 'PrioriPqrs', 'nombre' => 'Prioridad PQRS']);
        ConfigListaDetalle::create(['lista_id' => $lista->id, 'codigo' => '', 'nombre' => 'Urgente']);
        ConfigListaDetalle::create(['lista_id' => $lista->id, 'codigo' => '', 'nombre' => 'Normal (15 Días)']);
        ConfigListaDetalle::create(['lista_id' => $lista->id, 'codigo' => '', 'nombre' => 'Tutela (10 Días)']);

        $lista = ConfigLista::create(['cod' => 'ModaliPqrs', 'nombre' => 'Modalidad PQRS']);
        ConfigListaDetalle::create(['lista_id' => $lista->id, 'codigo' => '', 'nombre' => 'Reconocimiento de un derecho']);
        ConfigListaDetalle::create(['lista_id' => $lista->id, 'codigo' => '', 'nombre' => 'Intervención de una entidad o funcionario']);
        ConfigListaDetalle::create(['lista_id' => $lista->id, 'codigo' => '', 'nombre' => 'Resolución de una situación jurídica']);
        ConfigListaDetalle::create(['lista_id' => $lista->id, 'codigo' => '', 'nombre' => 'Prestación de un servicio']);
        ConfigListaDetalle::create(['lista_id' => $lista->id, 'codigo' => '', 'nombre' => 'Requerir información']);
        ConfigListaDetalle::create(['lista_id' => $lista->id, 'codigo' => '', 'nombre' => 'Consultar, examinar y requerir copias']);
        ConfigListaDetalle::create(['lista_id' => $lista->id, 'codigo' => '', 'nombre' => 'Formular consultas']);
        ConfigListaDetalle::create(['lista_id' => $lista->id, 'codigo' => '', 'nombre' => 'Formular quejas']);
        ConfigListaDetalle::create(['lista_id' => $lista->id, 'codigo' => '', 'nombre' => 'Formular reclamos']);
        ConfigListaDetalle::create(['lista_id' => $lista->id, 'codigo' => '', 'nombre' => 'Formular sugerencias']);
        ConfigListaDetalle::create(['lista_id' => $lista->id, 'codigo' => '', 'nombre' => 'Formular felicitaciones']);
        ConfigListaDetalle::create(['lista_id' => $lista->id, 'codigo' => '', 'nombre' => 'Presentar recursos']);
    }
}
