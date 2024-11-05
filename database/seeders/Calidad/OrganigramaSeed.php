<?php

namespace Database\Seeders\Calidad;

use App\Models\Calidad\Organigrama\CalidadOrganiDependencia;
use App\Models\Calidad\Organigrama\CalidadOrganiOficina;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class OrganigramaSeed extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $dependenciaJuenta = CalidadOrganiDependencia::firstOrCreate(['nom_depen' => 'JUNTA DIRECTIVO',]);
        $dependenciaRevisoria = CalidadOrganiDependencia::firstOrCreate(['nom_depen' => 'REVISORIA FISCA']);

        $dependenciaGerencia = CalidadOrganiDependencia::firstOrCreate(['nom_depen' => 'GERENCIA']);
        CalidadOrganiOficina::create(['nom_oficina' => 'ASESORÍA JURÍDICA', 'dependencia_id' => $dependenciaGerencia->id]);
        CalidadOrganiOficina::create(['nom_oficina' => 'ATENCIÓN AL USUARIO', 'dependencia_id' => $dependenciaGerencia->id]);
        CalidadOrganiOficina::create(['nom_oficina' => 'CONTROL INTERNO', 'dependencia_id' => $dependenciaGerencia->id]);
        CalidadOrganiOficina::create(['nom_oficina' => 'SUBDIRECCION OPERATIVA', 'dependencia_id' => $dependenciaGerencia->id]);
        CalidadOrganiOficina::create(['nom_oficina' => 'AMBULATORIO', 'dependencia_id' => $dependenciaGerencia->id]);
        CalidadOrganiOficina::create(['nom_oficina' => 'PLANEACION', 'dependencia_id' => $dependenciaGerencia->id]);

        $dependenciaAsesoJuri = CalidadOrganiDependencia::firstOrCreate(['nom_depen' => 'SESSORIA JURIDICA']);
        $dependenciaComiteTecnico = CalidadOrganiDependencia::firstOrCreate(['nom_depen' => 'COMITÉ TECNICO']);
        $dependenciaControlInter = CalidadOrganiDependencia::firstOrCreate(['nom_depen' => 'CONTRO INTERNO']);
        $dependenciaPlaneacion = CalidadOrganiDependencia::firstOrCreate(['nom_depen' => 'PLANEACIÓN']);
        $dependenciaOAUsuarios = CalidadOrganiDependencia::firstOrCreate(['nom_depen' => 'ATENCION A USUARIOS']);

        $dependenciaSubdirOpera = CalidadOrganiDependencia::firstOrCreate(['nom_depen' => 'SUBDIRECCION OPERATIVA']);
        CalidadOrganiOficina::create(['nom_oficina' => 'AMBULATORIO', 'dependencia_id' => $dependenciaSubdirOpera->id]);
        CalidadOrganiOficina::create(['nom_oficina' => 'APOYO DIAGNOSTICO Y TERAPEUTICO', 'dependencia_id' => $dependenciaSubdirOpera->id]);
        CalidadOrganiOficina::create(['nom_oficina' => 'CALIDAD', 'dependencia_id' => $dependenciaSubdirOpera->id]);
        CalidadOrganiOficina::create(['nom_oficina' => 'URGENCIAS', 'dependencia_id' => $dependenciaSubdirOpera->id]);
        CalidadOrganiOficina::create(['nom_oficina' => 'INTERNACION', 'dependencia_id' => $dependenciaSubdirOpera->id]);
    }
}
