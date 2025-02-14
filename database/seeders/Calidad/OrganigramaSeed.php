<?php

namespace Database\Seeders\Calidad;

use App\Models\Calidad\CalidadOrganigrama;
use App\Models\Calidad\Organigrama;
use Illuminate\Database\Seeder;

class OrganigramaSeed extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $dependenciaJuenta = CalidadOrganigrama::firstOrCreate(['tipo' => 'Dependencia', 'nom_organico' => 'JUNTA DIRECTIVA', 'cod_organico' => '']);
        $dependenciaRevisoria = CalidadOrganigrama::firstOrCreate(['tipo' => 'Dependencia', 'nom_organico' => 'REVISORIA FISCA', 'cod_organico' => '', 'parent' => 1]);

        $dependenciaGerencia = CalidadOrganigrama::firstOrCreate(['tipo' => 'Dependencia', 'nom_organico' => 'GERENCIA', 'cod_organico' => '100', 'parent' => 1]);
        $dependenciaAsesoJuri = CalidadOrganigrama::firstOrCreate(['tipo' => 'Dependencia', 'nom_organico' => 'SESSORIA JURIDICA', 'cod_organico' => 'GRE', 'parent' => 3]);
        $dependenciaComiteTecnico = CalidadOrganigrama::firstOrCreate(['tipo' => 'Dependencia', 'nom_organico' => 'COMITÉ TECNICO', 'cod_organico' => 'GRE', 'parent' => 3]);
        $dependenciaControlInter = CalidadOrganigrama::firstOrCreate(['tipo' => 'Dependencia', 'nom_organico' => 'CONTRO INTERNO', 'cod_organico' => 'GRE', 'parent' => 3]);
        $dependenciaPlaneacion = CalidadOrganigrama::firstOrCreate(['tipo' => 'Dependencia', 'nom_organico' => 'PLANEACIÓN', 'cod_organico' => 'GRE', 'parent' => 3]);
        $dependenciaOAUsuarios = CalidadOrganigrama::firstOrCreate(['tipo' => 'Dependencia', 'nom_organico' => 'O A USUARIOS', 'cod_organico' => 'GRE', 'parent' => 3]);

        CalidadOrganigrama::create(['tipo' => 'Oficina', 'nom_organico' => 'ASESORÍA JURÍDICA', 'cod_organico' => 'AJU', 'parent' => 3]);
        CalidadOrganigrama::create(['tipo' => 'Oficina', 'nom_organico' => 'ATENCIÓN AL USUARIO', 'cod_organico' => 'ATU', 'parent' => 3]);
        CalidadOrganigrama::create(['tipo' => 'Oficina', 'nom_organico' => 'CONTROL INTERNO', 'cod_organico' => 'COI', 'parent' => 3]);
        CalidadOrganigrama::create(['tipo' => 'Oficina', 'nom_organico' => 'SUBDIRECCION OPERATIVA', 'cod_organico' => 'PLA', 'parent' => 3]);
        CalidadOrganigrama::create(['tipo' => 'Oficina', 'nom_organico' => 'AMBULATORIO', 'cod_organico' => 'PLA', 'parent' => 3]);
        CalidadOrganigrama::create(['tipo' => 'Oficina', 'nom_organico' => 'PLANEACION', 'cod_organico' => 'PLA', 'parent' => 3]);

        $dependenciaSubdirOpera = CalidadOrganigrama::firstOrCreate(['tipo' => 'Dependencia', 'nom_organico' => 'SUBDIRECCION OPERATIVA', 'cod_organico' => 'SDO', 'parent' => 3]);
        CalidadOrganigrama::create(['tipo' => 'Oficina', 'nom_organico' => 'AMBULATORIO', 'cod_organico' => 'AMB', 'parent' => 12]);
        CalidadOrganigrama::create(['tipo' => 'Oficina', 'nom_organico' => 'APOYO DIAGNOSTICO Y TERAPEUTICO', 'cod_organico' => 'ADT', 'parent' => 12]);
        CalidadOrganigrama::create(['tipo' => 'Oficina', 'nom_organico' => 'CALIDAD', 'cod_organico' => 'CLD', 'parent' => 12]);
        CalidadOrganigrama::create(['tipo' => 'Oficina', 'nom_organico' => 'URGENCIAS', 'cod_organico' => 'URC', 'parent' => 12]);
        CalidadOrganigrama::create(['tipo' => 'Oficina', 'nom_organico' => 'INTERNACION', 'cod_organico' => 'INT', 'parent' => 12]);
    }
}
