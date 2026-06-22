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
     */
    public function ver(User $user, Documento $documento): bool
    {
        return $documento->tieneAcceso($user);
    }

    /**
     * Verifica si puede crear documentos.
     */
    public function crear(User $user): bool
    {
        return true;
    }

    /**
     * Verifica si puede editar el documento.
     */
    public function editar(User $user, Documento $documento): bool
    {
        return $documento->puedeEditar($user);
    }

    /**
     * Verifica si puede eliminar el documento.
     */
    public function eliminar(User $user, Documento $documento): bool
    {
        return $documento->user_id === $user->id;
    }

    /**
     * Verifica si puede sincronizar contenido.
     */
    public function sincronizar(User $user, Documento $documento): bool
    {
        return $documento->puedeEditar($user);
    }

    /**
     * Verifica si puede gestionar usuarios asignados.
     */
    public function gestionarUsuarios(User $user, Documento $documento): bool
    {
        return $documento->user_id === $user->id;
    }

    /**
     * Verifica si puede crear versiones.
     */
    public function crearVersion(User $user, Documento $documento): bool
    {
        return $documento->puedeEditar($user);
    }

    /**
     * Verifica si puede restaurar versiones.
     */
    public function restaurarVersion(User $user, Documento $documento): bool
    {
        return $documento->puedeEditar($user);
    }
}
