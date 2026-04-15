<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("DROP VIEW IF EXISTS ventanilla_radica_reci_view");

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
                vrr.estado_trabajo,

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

                COALESCE(archivos_count.total, 0) as total_archivos,
                COALESCE(responsables_stats.total_responsables, 0) as total_responsables,
                COALESCE(responsables_stats.total_custodios, 0) as total_custodios,
                COALESCE(responsables_stats.total_custodios_activos, 0) as total_custodios_activos,
                COALESCE(responsables_stats.total_custodios_con_nombres, 0) as total_custodios_con_nombres,
                COALESCE(responsables_stats.total_responsables_con_nombres, 0) as total_responsables_con_nombres,
                archivos_count.archivos_nombres,
                responsables_stats.ultima_visualizacion

            FROM ventanilla_radica_reci vrr

            LEFT JOIN clasificacion_documental_trd cd ON vrr.clasifica_documen_id = cd.id
            LEFT JOIN clasificacion_documental_trd cd_parent ON cd.parent = cd_parent.id
            LEFT JOIN clasificacion_documental_trd cd_grandparent ON cd_parent.parent = cd_grandparent.id

            LEFT JOIN gestion_terceros gt ON vrr.tercero_id = gt.id

            LEFT JOIN config_listas_detalles cld ON vrr.medio_recep_id = cld.id

            LEFT JOIN config_server_archivos csa ON vrr.config_server_id = csa.id

            LEFT JOIN (
                SELECT
                    radicado_id,
                    COUNT(*) as total,
                    GROUP_CONCAT(archivo SEPARATOR ', ') as archivos_nombres
                FROM ventanilla_radica_reci_archivos
                GROUP BY radicado_id
            ) archivos_count ON archivos_count.radicado_id = vrr.id

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

    public function down(): void
    {
        DB::statement("DROP VIEW IF EXISTS ventanilla_radica_reci_view");

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

                COALESCE(archivos_count.total, 0) as total_archivos,
                COALESCE(responsables_stats.total_responsables, 0) as total_responsables,
                COALESCE(responsables_stats.total_custodios, 0) as total_custodios,
                COALESCE(responsables_stats.total_custodios_activos, 0) as total_custodios_activos,
                COALESCE(responsables_stats.total_custodios_con_nombres, 0) as total_custodios_con_nombres,
                COALESCE(responsables_stats.total_responsables_con_nombres, 0) as total_responsables_con_nombres,
                archivos_count.archivos_nombres,
                responsables_stats.ultima_visualizacion

            FROM ventanilla_radica_reci vrr

            LEFT JOIN clasificacion_documental_trd cd ON vrr.clasifica_documen_id = cd.id
            LEFT JOIN clasificacion_documental_trd cd_parent ON cd.parent = cd_parent.id
            LEFT JOIN clasificacion_documental_trd cd_grandparent ON cd_parent.parent = cd_grandparent.id

            LEFT JOIN gestion_terceros gt ON vrr.tercero_id = gt.id

            LEFT JOIN config_listas_detalles cld ON vrr.medio_recep_id = cld.id

            LEFT JOIN config_server_archivos csa ON vrr.config_server_id = csa.id

            LEFT JOIN (
                SELECT
                    radicado_id,
                    COUNT(*) as total,
                    GROUP_CONCAT(archivo SEPARATOR ', ') as archivos_nombres
                FROM ventanilla_radica_reci_archivos
                GROUP BY radicado_id
            ) archivos_count ON archivos_count.radicado_id = vrr.id

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
};
