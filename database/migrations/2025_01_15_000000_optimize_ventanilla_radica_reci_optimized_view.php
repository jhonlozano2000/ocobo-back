<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Optimiza la vista SQL reemplazando subconsultas correlacionadas por LEFT JOINs con agregaciones
     * Esto reduce la complejidad de O(n²) a O(n log n) y mejora significativamente el rendimiento
     */
    public function up(): void
    {
        // Eliminar vista antigua
        DB::statement("DROP VIEW IF EXISTS ventanilla_radica_reci_view");

        // Crear vista optimizada con JOINs en lugar de subconsultas correlacionadas
        DB::statement("
            CREATE VIEW ventanilla_radica_reci_view AS
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

                -- Contadores agregados usando LEFT JOINs (más eficiente que subconsultas)
                COALESCE(archivos_count.total, 0) as total_archivos,
                COALESCE(responsables_stats.total_responsables, 0) as total_responsables,
                COALESCE(responsables_stats.total_custodios, 0) as total_custodios,
                COALESCE(responsables_stats.total_custodios_activos, 0) as total_custodios_activos,
                COALESCE(responsables_stats.total_custodios_con_nombres, 0) as total_custodios_con_nombres,
                COALESCE(responsables_stats.total_responsables_con_nombres, 0) as total_responsables_con_nombres,
                archivos_count.archivos_nombres,
                responsables_stats.ultima_visualizacion

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

            -- Agregación de archivos (reemplaza subconsulta correlacionada)
            LEFT JOIN (
                SELECT
                    radicado_id,
                    COUNT(*) as total,
                    GROUP_CONCAT(archivo SEPARATOR ', ') as archivos_nombres
                FROM ventanilla_radica_reci_archivos
                GROUP BY radicado_id
            ) archivos_count ON archivos_count.radicado_id = vrr.id

            -- Agregación de responsables (reemplaza múltiples subconsultas correlacionadas)
            LEFT JOIN (
                SELECT
                    radica_reci_id,
                    COUNT(*) as total_responsables,
                    SUM(CASE WHEN custodio = 1 THEN 1 ELSE 0 END) as total_custodios,
                    SUM(CASE WHEN custodio = 1 THEN 1 ELSE 0 END) as total_custodios_activos,
                    SUM(CASE WHEN custodio = 1 THEN 1 ELSE 0 END) as total_custodios_con_nombres,
                    COUNT(*) as total_responsables_con_nombres,
                    MAX(fechor_visto) as ultima_visualizacion
                FROM ventanilla_radica_reci_responsa
                GROUP BY radica_reci_id
            ) responsables_stats ON responsables_stats.radica_reci_id = vrr.id
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Eliminar vista optimizada
        DB::statement("DROP VIEW IF EXISTS ventanilla_radica_reci_view");

        // Recrear vista antigua (con subconsultas correlacionadas)
        DB::statement("
            CREATE VIEW ventanilla_radica_reci_view AS
            SELECT
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
                cd.cod as clasificacion_cod,
                cd.nom as clasificacion_nom,
                cd.tipo as clasificacion_tipo,
                cd_parent.cod as clasificacion_parent_cod,
                cd_parent.nom as clasificacion_parent_nom,
                cd_parent.tipo as clasificacion_parent_tipo,
                cd_grandparent.cod as clasificacion_grandparent_cod,
                cd_grandparent.nom as clasificacion_grandparent_nom,
                cd_grandparent.tipo as clasificacion_grandparent_tipo,
                gt.num_docu_nit as tercero_documento,
                gt.nom_razo_soci as tercero_nombre,
                gt.direccion as tercero_direccion,
                gt.telefono as tercero_telefono,
                gt.email as tercero_email,
                gt.tipo as tercero_tipo,
                cld.nombre as medio_recepcion_nombre,
                cld.codigo as medio_recepcion_codigo,
                csa.host as servidor_host,
                csa.ruta as servidor_ruta,
                csa.detalle as servidor_detalle,
                (SELECT COUNT(*) FROM ventanilla_radica_reci_archivos WHERE radicado_id = vrr.id) as total_archivos,
                (SELECT COUNT(*) FROM ventanilla_radica_reci_responsa WHERE radica_reci_id = vrr.id) as total_responsables,
                (SELECT COUNT(*) FROM ventanilla_radica_reci_responsa WHERE radica_reci_id = vrr.id AND custodio = 1) as total_custodios,
                (SELECT COUNT(*) FROM ventanilla_radica_reci_responsa WHERE radica_reci_id = vrr.id AND custodio = 1) as total_custodios_activos,
                (SELECT COUNT(*) FROM ventanilla_radica_reci_responsa WHERE radica_reci_id = vrr.id AND custodio = 1) as total_custodios_con_nombres,
                (SELECT COUNT(*) FROM ventanilla_radica_reci_responsa WHERE radica_reci_id = vrr.id) as total_responsables_con_nombres,
                (SELECT GROUP_CONCAT(archivo SEPARATOR ', ') FROM ventanilla_radica_reci_archivos WHERE radicado_id = vrr.id) as archivos_nombres,
                (SELECT MAX(fechor_visto) FROM ventanilla_radica_reci_responsa WHERE radica_reci_id = vrr.id) as ultima_visualizacion
            FROM ventanilla_radica_reci vrr
            LEFT JOIN clasificacion_documental_trd cd ON vrr.clasifica_documen_id = cd.id
            LEFT JOIN clasificacion_documental_trd cd_parent ON cd.parent = cd_parent.id
            LEFT JOIN clasificacion_documental_trd cd_grandparent ON cd_parent.parent = cd_grandparent.id
            LEFT JOIN gestion_terceros gt ON vrr.tercero_id = gt.id
            LEFT JOIN config_listas_detalles cld ON vrr.medio_recep_id = cld.id
            LEFT JOIN config_server_archivos csa ON vrr.config_server_id = csa.id
        ");
    }
};
