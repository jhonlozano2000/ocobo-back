<?php

namespace App\Policies\MiBandeja\TempReci;

use App\Models\MiBandeja\TempDocumentosRecibidos\Documento;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Política de autorización para documentos colaborativos.
 *
 * Define los permisos para acceder, editar, eliminar
 * y gestionar documentos de Comunicaciones Recibidas.
 */
class DocumentoPolicy
{
    use HandlesAuthorization;

    /**
     * Verifica si puede ver el documento.
     *
     * @param User $user
     * @param Documento $documento
     * @return bool
     */
    public function ver(User $user, Documento $documento): bool
    {
        return $documento->tieneAcceso($user);
    }

    /**
     * Verifica si puede crear documentos.
     *
     * @param User $user
     * @return bool
     */
    public function crear(User $user): bool
    {
        return true;
    }

    /**
     * Verifica si puede editar el documento.
     *
     * @param User $user
     * @param Documento $documento
     * @return bool
     */
    public function editar(User $user, Documento $documento): bool
    {
        return $documento->puedeEditar($user);
    }

    /**
     * Verifica si puede eliminar el documento.
     *
     * @param User $user
     * @param Documento $documento
     * @return bool
     */
    public function eliminar(User $user, Documento $documento): bool
    {
        return $documento->user_id === $user->id;
    }

    /**
     * Verifica si puede sincronizar contenido.
     *
     * @param User $user
     * @param Documento $documento
     * @return bool
     */
    public function sincronizar(User $user, Documento $documento): bool
    {
        return $documento->puedeEditar($user);
    }

    /**
     * Verifica si puede gestionar usuarios asignados.
     *
     * @param User $user
     * @param Documento $documento
     * @return bool
     */
    public function gestionarUsuarios(User $user, Documento $documento): bool
    {
        return $documento->user_id === $user->id;
    }

    /**
     * Verifica si puede crear versiones.
     *
     * @param User $user
     * @param Documento $documento
     * @return bool
     */
    public function crearVersion(User $user, Documento $documento): bool
    {
        return $documento->puedeEditar($user);
    }

    /**
     * Verifica si puede restaurar versiones.
     *
     * @param User $user
     * @param Documento $documento
     * @return bool
     */
    public function restaurarVersion(User $user, Documento $documento): bool
    {
        return $documento->puedeEditar($user);
    }
}