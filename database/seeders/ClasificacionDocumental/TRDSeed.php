<?php

namespace Database\Seeders\ClasificacionDocumental;

use Illuminate\Database\Seeder;
use App\Models\ClasificacionDocumental\ClasificacionDocumentalTRD;
use App\Models\Calidad\CalidadOrganigrama;

class TRDSeed extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Obtener una dependencia existente
        $dependencia = CalidadOrganigrama::first();
        
        if (!$dependencia) {
            $this->command->error('No hay dependencias disponibles. Ejecuta primero OrganigramaSeed.');
            return;
        }

        $this->command->info('Creando datos de prueba para TRD...');

        // Crear Series principales
        $serie1 = ClasificacionDocumentalTRD::create([
            'tipo' => 'Serie',
            'cod' => 'S001',
            'nom' => 'Gestión Administrativa',
            'parent' => null,
            'dependencia_id' => $dependencia->id,
            'a_g' => '5',
            'a_c' => '10',
            'ct' => true,
            'e' => false,
            'm_d' => false,
            's' => false,
            'procedimiento' => 'PROC-001',
            'estado' => true,
            'user_register' => 1
        ]);

        $serie2 = ClasificacionDocumentalTRD::create([
            'tipo' => 'Serie',
            'cod' => 'S002',
            'nom' => 'Gestión Financiera',
            'parent' => null,
            'dependencia_id' => $dependencia->id,
            'a_g' => '7',
            'a_c' => '15',
            'ct' => true,
            'e' => false,
            'm_d' => true,
            's' => false,
            'procedimiento' => 'PROC-002',
            'estado' => true,
            'user_register' => 1
        ]);

        // Crear SubSeries para la Serie 1
        $subserie1 = ClasificacionDocumentalTRD::create([
            'tipo' => 'SubSerie',
            'cod' => 'SS001',
            'nom' => 'Contratos de Personal',
            'parent' => $serie1->id,
            'dependencia_id' => $dependencia->id,
            'a_g' => '3',
            'a_c' => '8',
            'ct' => true,
            'e' => false,
            'm_d' => false,
            's' => false,
            'procedimiento' => 'PROC-003',
            'estado' => true,
            'user_register' => 1
        ]);

        $subserie2 = ClasificacionDocumentalTRD::create([
            'tipo' => 'SubSerie',
            'cod' => 'SS002',
            'nom' => 'Actas de Reuniones',
            'parent' => $serie1->id,
            'dependencia_id' => $dependencia->id,
            'a_g' => '2',
            'a_c' => '5',
            'ct' => false,
            'e' => true,
            'm_d' => true,
            's' => false,
            'procedimiento' => 'PROC-004',
            'estado' => true,
            'user_register' => 1
        ]);

        // Crear SubSeries para la Serie 2
        $subserie3 = ClasificacionDocumentalTRD::create([
            'tipo' => 'SubSerie',
            'cod' => 'SS003',
            'nom' => 'Presupuestos Anuales',
            'parent' => $serie2->id,
            'dependencia_id' => $dependencia->id,
            'a_g' => '4',
            'a_c' => '12',
            'ct' => true,
            'e' => false,
            'm_d' => true,
            's' => false,
            'procedimiento' => 'PROC-005',
            'estado' => true,
            'user_register' => 1
        ]);

        // Crear Tipos de Documento
        ClasificacionDocumentalTRD::create([
            'tipo' => 'TipoDocumento',
            'cod' => 'TD001',
            'nom' => 'Contrato de Trabajo',
            'parent' => $subserie1->id,
            'dependencia_id' => $dependencia->id,
            'a_g' => '3',
            'a_c' => '8',
            'ct' => true,
            'e' => false,
            'm_d' => false,
            's' => false,
            'procedimiento' => 'PROC-006',
            'estado' => true,
            'user_register' => 1
        ]);

        ClasificacionDocumentalTRD::create([
            'tipo' => 'TipoDocumento',
            'cod' => 'TD002',
            'nom' => 'Acta de Consejo Directivo',
            'parent' => $subserie2->id,
            'dependencia_id' => $dependencia->id,
            'a_g' => '2',
            'a_c' => '5',
            'ct' => false,
            'e' => true,
            'm_d' => true,
            's' => false,
            'procedimiento' => 'PROC-007',
            'estado' => true,
            'user_register' => 1
        ]);

        ClasificacionDocumentalTRD::create([
            'tipo' => 'TipoDocumento',
            'cod' => 'TD003',
            'nom' => 'Presupuesto de Gastos',
            'parent' => $subserie3->id,
            'dependencia_id' => $dependencia->id,
            'a_g' => '4',
            'a_c' => '12',
            'ct' => true,
            'e' => false,
            'm_d' => true,
            's' => false,
            'procedimiento' => 'PROC-008',
            'estado' => true,
            'user_register' => 1
        ]);

        $this->command->info('✅ Datos de prueba TRD creados exitosamente');
        $this->command->info("📊 Se crearon:");
        $this->command->info("   - 2 Series principales");
        $this->command->info("   - 3 SubSeries");
        $this->command->info("   - 3 Tipos de Documento");
    }
} 