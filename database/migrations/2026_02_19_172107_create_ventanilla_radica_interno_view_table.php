<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("DROP VIEW IF EXISTS ventanilla_radica_interno_view");

        DB::statement("
            CREATE VIEW ventanilla_radica_interno_view AS
            SELECT
                vri.id,
                vri.num_radicado,
                vri.created_at,
                vri.fec_venci,
                vri.archivo_digital,
                vri.asunto,
                vri.clasifica_documen_id,
                vri.usuario_crea,

                cd.cod as clasificacion_cod,
                cd.nom as clasificacion_nom,
                cd_parent.nom as clasificacion_parent_nom,
                CONCAT(u_crea.nombres, \" \", u_crea.apellidos) as usuario_crea_nombre,

                -- Destinatarios
                (SELECT COUNT(*) FROM ventanilla_radica_internos_destina WHERE radica_interno_id = vri.id) as total_destinatarios,
                dest.nombres as destinatarios_nombres,

                -- Responsables
                (SELECT COUNT(*) FROM ventanilla_radica_interno_responsa WHERE radica_interno_id = vri.id) as total_responsables,
                resp.nombres as responsables_nombres,

                -- Proyectores
                (SELECT COUNT(*) FROM ventanilla_radica_interno_proyectores WHERE radica_interno_id = vri.id) as total_proyectores,
                proy.nombres as proyectores_nombres,

                -- Firmantes
                (SELECT COUNT(*) FROM ventanilla_radica_internos_firmantes WHERE radica_interno_id = vri.id) as total_firmantes,
                firm.nombres as firmantes_nombres,

                -- Custodio
                (SELECT COUNT(*) FROM ventanilla_radica_interno_responsa WHERE radica_interno_id = vri.id AND custodio = 1) as total_custodios

            FROM ventanilla_radica_internos vri
            LEFT JOIN clasificacion_documental_trd cd ON vri.clasifica_documen_id = cd.id
            LEFT JOIN clasificacion_documental_trd cd_parent ON cd.parent = cd_parent.id
            LEFT JOIN users u_crea ON vri.usuario_crea = u_crea.id

            -- Destinatarios Join
            LEFT JOIN (
                SELECT d.radica_interno_id, GROUP_CONCAT(CONCAT(u.nombres, \" \", u.apellidos) SEPARATOR \", \") as nombres
                FROM ventanilla_radica_internos_destina d
                JOIN users_cargos uc ON d.users_cargos_id = uc.id
                JOIN users u ON uc.user_id = u.id
                GROUP BY d.radica_interno_id
            ) dest ON dest.radica_interno_id = vri.id

            -- Responsables Join
            LEFT JOIN (
                SELECT r.radica_interno_id, GROUP_CONCAT(CONCAT(u.nombres, \" \", u.apellidos) SEPARATOR \", \") as nombres
                FROM ventanilla_radica_interno_responsa r
                JOIN users_cargos uc ON r.users_cargos_id = uc.id
                JOIN users u ON uc.user_id = u.id
                GROUP BY r.radica_interno_id
            ) resp ON resp.radica_interno_id = vri.id

            -- Proyectores Join
            LEFT JOIN (
                SELECT p.radica_interno_id, GROUP_CONCAT(CONCAT(u.nombres, \" \", u.apellidos) SEPARATOR \", \") as nombres
                FROM ventanilla_radica_interno_proyectores p
                JOIN users_cargos uc ON p.users_cargos_id = uc.id
                JOIN users u ON uc.user_id = u.id
                GROUP BY p.radica_interno_id
            ) proy ON proy.radica_interno_id = vri.id

            -- Firmantes Join
            LEFT JOIN (
                SELECT f.radica_interno_id, GROUP_CONCAT(CONCAT(u.nombres, \" \", u.apellidos) SEPARATOR \", \") as nombres
                FROM ventanilla_radica_internos_firmantes f
                JOIN users u ON f.users_id = u.id
                GROUP BY f.radica_interno_id
            ) firm ON firm.radica_interno_id = vri.id
        ");
    }

    public function down(): void
    {
        DB::statement("DROP VIEW IF EXISTS ventanilla_radica_interno_view");
    }
};
