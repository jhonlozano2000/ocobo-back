<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Crea la vista SQL optimizada para listado de PQRS
     * Equivalente a ventanilla_radica_reci_view
     * NOTA: PQRS no tiene usuario_crea directamente, se une al radicado asociado
     */
    public function up(): void
    {
        DB::statement('DROP VIEW IF EXISTS ventanilla_pqrs_view');

        DB::statement("
            CREATE VIEW ventanilla_pqrs_view AS
            SELECT
                -- Campos principales del PQRS
                vp.id,
                vp.created_at,
                vp.fecha_vencimiento,
                vp.prioridad,
                vp.estado_tramite,
                vp.tipo_pqrs_id,
                vp.clasificacion_documental_trd_id,
                vp.gestion_tercero_id,
                vp.detalle_solicitud,
                vp.tiene_prorroga,
                vp.fecha_vencimiento_original,
                vp.estado_firma,
                vp.ventanilla_radica_reci_id,

                -- Campos del radicado asociado (para ABAC y otros)
                vrr.usuario_crea,
                vrr.num_radicado,
                vrr.fec_radicado as fec_radicado,
                vrr.fec_venci as radicado_fec_venci,
                vrr.asunto as radicado_asunto,
                vrr.estado_trabajo as radicado_estado_trabajo,
                vrr.medio_recep_id as radicado_medio_recep_id,
                vrr.config_server_id as radicado_config_server_id,

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

                -- Datos del tipo PQRS
                cld_tipo.nombre as tipo_pqrs_nombre,
                cld_tipo.codigo as tipo_pqrs_codigo,

                -- Datos del medio de recepción del radicado
                cld_medio.nombre as medio_recepcion_nombre,
                cld_medio.codigo as medio_recepcion_codigo,

                -- Datos del servidor de archivos del radicado
                csa.host as servidor_host,
                csa.ruta as servidor_ruta,
                csa.detalle as servidor_detalle,

                -- Contadores agregados usando LEFT JOINs
                COALESCE(archivos_count.total, 0) as total_archivos,
                COALESCE(responsables_stats.total_responsables, 0) as total_responsables,
                COALESCE(responsables_stats.total_custodios, 0) as total_custodios,
                COALESCE(responsables_stats.total_custodios_activos, 0) as total_custodios_activos,
                COALESCE(responsables_stats.ultima_visualizacion, NULL) as ultima_visualizacion

            FROM ventanilla_pqrs vp

            -- Join con radicado asociado (clave para ABAC)
            LEFT JOIN ventanilla_radica_reci vrr ON vp.ventanilla_radica_reci_id = vrr.id

            -- Join con clasificación documental y su jerarquía
            LEFT JOIN clasificacion_documental_trd cd ON vp.clasificacion_documental_trd_id = cd.id
            LEFT JOIN clasificacion_documental_trd cd_parent ON cd.parent = cd_parent.id
            LEFT JOIN clasificacion_documental_trd cd_grandparent ON cd_parent.parent = cd_grandparent.id

            -- Join con tercero
            LEFT JOIN gestion_terceros gt ON vp.gestion_tercero_id = gt.id

            -- Join con tipo PQRS
            LEFT JOIN config_listas_detalles cld_tipo ON vp.tipo_pqrs_id = cld_tipo.id

            -- Join con medio de recepción del radicado
            LEFT JOIN config_listas_detalles cld_medio ON vrr.medio_recep_id = cld_medio.id

            -- Join con servidor de archivos del radicado
            LEFT JOIN config_server_archivos csa ON vrr.config_server_id = csa.id

            -- Agregación de archivos
            LEFT JOIN (
                SELECT
                    ventanilla_pqrs_id,
                    COUNT(*) as total
                FROM ventanilla_pqrs_archivos
                GROUP BY ventanilla_pqrs_id
            ) archivos_count ON archivos_count.ventanilla_pqrs_id = vp.id

            -- Agregación de responsables
            LEFT JOIN (
                SELECT
                    radica_reci_id,
                    COUNT(*) as total_responsables,
                    SUM(CASE WHEN custodio = 1 THEN 1 ELSE 0 END) as total_custodios,
                    SUM(CASE WHEN custodio = 1 AND fechor_visto IS NOT NULL THEN 1 ELSE 0 END) as total_custodios_activos,
                    MAX(fechor_visto) as ultima_visualizacion
                FROM ventanilla_radica_reci_responsa
                GROUP BY radica_reci_id
            ) responsables_stats ON responsables_stats.radica_reci_id = vp.ventanilla_radica_reci_id
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS ventanilla_pqrs_view');
    }
};
