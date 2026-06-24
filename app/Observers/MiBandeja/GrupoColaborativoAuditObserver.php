<?php

namespace App\Observers\MiBandeja;

use App\Models\MiBandeja\MiBandejaTemp;
use App\Models\MiBandeja\MiBandejaTempArchivoVersion;
use App\Models\MiBandeja\MiBandejaTempAuditLog;
use App\Models\MiBandeja\MiBandejaTempGrupoFirmante;
use App\Models\MiBandeja\MiBandejaTempGrupoProyector;
use App\Models\MiBandeja\MiBandejaTempGrupoResponsable;
use App\Models\MiBandeja\MiBandejaTempNota;

class GrupoColaborativoAuditObserver
{
    private function log(
        int $grupoId,
        int $userId,
        string $accion,
        ?array $old = null,
        ?array $new = null,
    ): void {
        $req = request();

        MiBandejaTempAuditLog::create([
            'grupo_id' => $grupoId,
            'user_id' => $userId,
            'accion' => $accion,
            'ip_origen' => $req?->ip() ?? request()->ip(),
            'user_agent' => $req?->userAgent(),
            'payload_old' => $old,
            'payload_new' => $new,
        ]);
    }

    private function rolDe($model): string
    {
        return match (true) {
            $model instanceof MiBandejaTempGrupoResponsable => 'RESPONSABLE',
            $model instanceof MiBandejaTempGrupoFirmante => 'FIRMANTE',
            $model instanceof MiBandejaTempGrupoProyector => 'PROYECTOR',
            default => 'MIEMBRO',
        };
    }

    /* ─── created dispatch ───────────────────────────────── */

    public function created($model): void
    {
        switch (true) {
            case $model instanceof MiBandejaTemp:
                $this->log(
                    $model->id,
                    $model->usua_crea_id ?? auth()->id() ?? 1,
                    'CREATE_GROUP',
                    null,
                    $model->withoutRelations()->toArray(),
                );
                break;

            case $model instanceof MiBandejaTempArchivoVersion:
                $accion = $model->version === '1.0' ? 'SUBIR_VERSION_INICIAL' : 'CHECK_IN';
                $this->log(
                    $model->grupo_id,
                    $model->user_subio_id,
                    $accion,
                    null,
                    [
                        'version_id' => $model->id,
                        'version' => $model->version,
                        'nombre_original' => $model->nombre_original,
                        'hash_seguridad' => $model->hash_seguridad,
                        'peso_kb' => $model->peso,
                    ],
                );
                break;

            case $model instanceof MiBandejaTempNota:
                $this->log(
                    $model->grupo_id,
                    $model->user_id,
                    'NOTA_CREADA',
                    null,
                    ['nota_id' => $model->id, 'contenido_length' => mb_strlen($model->contenido)],
                );
                break;

            case $model instanceof MiBandejaTempGrupoResponsable:
            case $model instanceof MiBandejaTempGrupoFirmante:
            case $model instanceof MiBandejaTempGrupoProyector:
                $rol = $this->rolDe($model);
                $this->log(
                    $model->grupo_id,
                    $model->user_id,
                    "AGREGAR_{$rol}",
                    null,
                    ['member_id' => $model->id, 'user_id' => $model->user_id],
                );
                break;
        }
    }

    /* ─── updated dispatch ───────────────────────────────── */

    public function updated($model): void
    {
        switch (true) {
            case $model instanceof MiBandejaTemp:
                $userId = auth()->id() ?? $model->usua_crea_id ?? 1;
                $dirty = $model->getDirty();
                $original = $model->getOriginal();

                $accion = 'UPDATE_GROUP';
                if (isset($dirty['estado']) && $original['estado'] !== 'finalizado' && $dirty['estado'] === 'finalizado') {
                    $accion = 'ENVIAR_TRAMITE';
                } elseif (isset($dirty['estado']) && $original['estado'] !== 'listo_envio' && $dirty['estado'] === 'listo_envio') {
                    $accion = 'ALL_MEMBERS_COMPLETED';
                } elseif (isset($dirty['plantilla_cargada']) && $dirty['plantilla_cargada']) {
                    $accion = 'PLANTILLA_ASIGNADA';
                }

                $oldPayload = [];
                $newPayload = [];
                foreach ($dirty as $key => $newVal) {
                    $oldPayload[$key] = $original[$key] ?? null;
                    $newPayload[$key] = $newVal;
                }

                $this->log($model->id, $userId, $accion, $oldPayload, $newPayload);
                break;

            case $model instanceof MiBandejaTempArchivoVersion:
                $dirty = $model->getDirty();

                if (isset($dirty['bloqueado_por_user_id'])) {
                    $original = $model->getOriginal();
                    $accion = $dirty['bloqueado_por_user_id'] !== null ? 'CHECK_OUT' : 'FORCE_RELEASE';

                    $this->log(
                        $model->grupo_id,
                        $dirty['bloqueado_por_user_id'] ?? auth()->id() ?? 1,
                        $accion,
                        ['bloqueado_por_user_id' => $original['bloqueado_por_user_id']],
                        ['bloqueado_por_user_id' => $dirty['bloqueado_por_user_id']],
                    );
                }
                break;

            case $model instanceof MiBandejaTempGrupoResponsable:
            case $model instanceof MiBandejaTempGrupoFirmante:
            case $model instanceof MiBandejaTempGrupoProyector:
                $dirty = $model->getDirty();
                if (!isset($dirty['estado_tarea'])) {
                    break;
                }

                $rol = $this->rolDe($model);
                $this->log(
                    $model->grupo_id,
                    auth()->id() ?? $model->user_id,
                    "MARCAR_CUMPLIDO_{$rol}",
                    ['estado_tarea' => $model->getOriginal('estado_tarea')],
                    ['estado_tarea' => $dirty['estado_tarea']],
                );
                break;
        }
    }

    /* ─── deleted dispatch ───────────────────────────────── */

    public function deleted($model): void
    {
        switch (true) {
            case $model instanceof MiBandejaTemp:
                $this->log(
                    $model->id,
                    auth()->id() ?? 1,
                    'DESTROY_GROUP',
                    $model->withoutRelations()->toArray(),
                    null,
                );
                break;

            case $model instanceof MiBandejaTempGrupoResponsable:
            case $model instanceof MiBandejaTempGrupoFirmante:
            case $model instanceof MiBandejaTempGrupoProyector:
                $rol = $this->rolDe($model);
                $this->log(
                    $model->grupo_id,
                    auth()->id() ?? $model->user_id,
                    "ELIMINAR_{$rol}",
                    ['user_id' => $model->user_id],
                    null,
                );
                break;
        }
    }
}
