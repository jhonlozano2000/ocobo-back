<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("
            CREATE VIEW ventanilla_radica_reci_optimized_view AS
            SELECT
                -- Campos principales del radicado
                vrr.id,
                vrr.num_radicado,
                vrr.created_at,
                vrr.fec_venci,
                vrr.archivo_digital,
                vrr.asunto,
                vrr.clasifica_documen_id,
                vrr.tercero_id,
                vrr.medio_recep_id,
                vrr.config_server_id,

                -- Datos de clasificación documental (jerarquía completa)
                cd.cod as clasificacion_cod,
                cd.nom as clasificacion_nom,
                cd.tipo as clasificacion_tipo,
                cd_parent.cod as clasificacion_parent_cod,
                cd_parent.nom as clasificacion_parent_nom,
                cd_parent.tipo as clasificacion_parent_tipo,
                cd_grandparent.cod as clasificacion_grandparent_cod,
                cd_grandparent.nom as clasificacion_grandparent_nom,
                cd_grandparent.tipo as clasificacion_grandparent_tipo,

                -- Datos del tercero
                gt.num_docu_nit as tercero_documento,
                gt.nom_razo_soci as tercero_nombre,
                gt.direccion as tercero_direccion,
                gt.telefono as tercero_telefono,
                gt.email as tercero_email,
                gt.tipo as tercero_tipo,

                -- Datos del medio de recepción
                cld.nombre as medio_recepcion_nombre,
                cld.codigo as medio_recepcion_codigo,

                -- Datos del servidor de archivos
                csa.host as servidor_host,
                csa.ruta as servidor_ruta,
                csa.detalle as servidor_detalle,

                -- Contadores agregados
                (SELECT COUNT(*) FROM ventanilla_radica_reci_archivos WHERE radicado_id = vrr.id) as total_archivos,
                (SELECT COUNT(*) FROM ventanilla_radica_reci_responsa WHERE radica_reci_id = vrr.id) as total_responsables,
                (SELECT COUNT(*) FROM ventanilla_radica_reci_responsa WHERE radica_reci_id = vrr.id AND custodio = 1) as total_custodios,

                -- Información de responsables principales (custodio)
                (SELECT COUNT(*)
                 FROM ventanilla_radica_reci_responsa vrr_resp
                 WHERE vrr_resp.radica_reci_id = vrr.id AND vrr_resp.custodio = 1
                ) as total_custodios_activos,

                -- Nombres de responsables custodios (simplificado)
                (SELECT COUNT(*)
                 FROM ventanilla_radica_reci_responsa vrr_resp
                 WHERE vrr_resp.radica_reci_id = vrr.id AND vrr_resp.custodio = 1
                ) as total_custodios_con_nombres,

                -- Nombres de todos los responsables (simplificado)
                (SELECT COUNT(*)
                 FROM ventanilla_radica_reci_responsa vrr_resp
                 WHERE vrr_resp.radica_reci_id = vrr.id
                ) as total_responsables_con_nombres,

                -- Lista de archivos adjuntos (nombres)
                (SELECT GROUP_CONCAT(archivo SEPARATOR ', ')
                 FROM ventanilla_radica_reci_archivos
                 WHERE radicado_id = vrr.id
                ) as archivos_nombres,

                -- Estado de visualización
                (SELECT MAX(fechor_visto) FROM ventanilla_radica_reci_responsa WHERE radica_reci_id = vrr.id) as ultima_visualizacion

            FROM ventanilla_radica_reci vrr

            -- Join con clasificación documental y su jerarquía
            LEFT JOIN clasificacion_documental_trd cd ON vrr.clasifica_documen_id = cd.id
            LEFT JOIN clasificacion_documental_trd cd_parent ON cd.parent = cd_parent.id
            LEFT JOIN clasificacion_documental_trd cd_grandparent ON cd_parent.parent = cd_grandparent.id

            -- Join con tercero
            LEFT JOIN gestion_terceros gt ON vrr.tercero_id = gt.id

            -- Join con medio de recepción
            LEFT JOIN config_listas_detalles cld ON vrr.medio_recep_id = cld.id

            -- Join con servidor de archivos
            LEFT JOIN config_server_archivos csa ON vrr.config_server_id = csa.id
        ");

        // Crear índices para mejorar el rendimiento de la vista (solo si no existen)
        try {
            DB::statement("CREATE INDEX idx_vrr_optimized_num_radicado ON ventanilla_radica_reci (num_radicado)");
        } catch (\Exception $e) {
            // Índice ya existe
        }
        
        try {
            DB::statement("CREATE INDEX idx_vrr_optimized_created_at ON ventanilla_radica_reci (created_at)");
        } catch (\Exception $e) {
            // Índice ya existe
        }
        
        try {
            DB::statement("CREATE INDEX idx_vrr_optimized_asunto ON ventanilla_radica_reci (asunto)");
        } catch (\Exception $e) {
            // Índice ya existe
        }
        
        try {
            DB::statement("CREATE INDEX idx_vrr_optimized_clasifica_tercero ON ventanilla_radica_reci (clasifica_documen_id, tercero_id)");
        } catch (\Exception $e) {
            // Índice ya existe
        }
        
        try {
            DB::statement("CREATE INDEX idx_vrr_optimized_fec_venci ON ventanilla_radica_reci (fec_venci)");
        } catch (\Exception $e) {
            // Índice ya existe
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Eliminar vista primero
        DB::statement("DROP VIEW IF EXISTS ventanilla_radica_reci_optimized_view");

        // Eliminar índices (evitando los que pueden tener restricciones de clave foránea)
        try {
            DB::statement("DROP INDEX idx_vrr_optimized_num_radicado ON ventanilla_radica_reci");
        } catch (\Exception $e) {
            // Ignorar si no existe
        }
        
        try {
            DB::statement("DROP INDEX idx_vrr_optimized_created_at ON ventanilla_radica_reci");
        } catch (\Exception $e) {
            // Ignorar si no existe
        }
        
        try {
            DB::statement("DROP INDEX idx_vrr_optimized_asunto ON ventanilla_radica_reci");
        } catch (\Exception $e) {
            // Ignorar si no existe
        }
        
        try {
            DB::statement("DROP INDEX idx_vrr_optimized_fec_venci ON ventanilla_radica_reci");
        } catch (\Exception $e) {
            // Ignorar si no existe
        }
    }
};
