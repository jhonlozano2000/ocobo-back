<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use Illuminate\Auth\Access\HandlesAuthorization;

class VentanillaRadicaReciPolicy
{
    use HandlesAuthorization;

    /**
     * Determina si el usuario puede ver la lista de radicados recibidos.
     * 
     * @param User $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('Radicar -> Cores. Recibida -> Listar');
    }

    /**
     * Determina si el usuario puede ver un radicado específico.
     * 
     * @param User $user
     * @param VentanillaRadicaReci $radicado
     * @return bool
     */
    public function view(User $user, VentanillaRadicaReci $radicado): bool
    {
        return $user->hasPermissionTo('Radicar -> Cores. Recibida -> Mostrar');
    }

    /**
     * Determina si el usuario puede crear radicados.
     * 
     * @param User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('Radicar -> Cores. Recibida -> Crear');
    }

    /**
     * Determina si el usuario puede actualizar un radicado.
     * 
     * @param User $user
     * @param VentanillaRadicaReci $radicado
     * @return bool
     */
    public function update(User $user, VentanillaRadicaReci $radicado): bool
    {
        // Verificar si el radicado está en estado editable
        if (!$radicado->esEditable()) {
            return false;
        }

        return $user->hasPermissionTo('Radicar -> Cores. Recibida -> Editar');
    }

    /**
     * Determina si el usuario puede eliminar un radicado.
     * 
     * @param User $user
     * @param VentanillaRadicaReci $radicado
     * @return bool
     */
    public function delete(User $user, VentanillaRadicaReci $radicado): bool
    {
        // Solo el usuario que creó o un administrador pueden eliminar
        if ($radicado->usuario_crea === $user->id) {
            return $user->hasPermissionTo('Radicar -> Cores. Recibida -> Eliminar');
        }

        // Administradores con rol específico pueden eliminar cualquier radicado
        return $user->hasRole('Jefe de Archivo') || $user->hasRole('Administrador');
    }

    /**
     * Determina si el usuario puede actualizar el asunto de un radicado.
     * 
     * @param User $user
     * @param VentanillaRadicaReci $radicado
     * @return bool
     */
    public function updateAsunto(User $user, VentanillaRadicaReci $radicado): bool
    {
        return $user->hasPermissionTo('Radicar -> Cores. Recibida -> Actualizar asunto');
    }

    /**
     * Determina si el usuario puede actualizar fechas de un radicado.
     * 
     * @param User $user
     * @param VentanillaRadicaReci $radicado
     * @return bool
     */
    public function updateFechas(User $user, VentanillaRadicaReci $radicado): bool
    {
        return $user->hasPermissionTo('Radicar -> Cores. Recibida -> Atualizar fechas de radicados');
    }

    /**
     * Determina si el usuario puede actualizar la clasificación documental.
     * 
     * @param User $user
     * @param VentanillaRadicaReci $radicado
     * @return bool
     */
    public function updateClasificacion(User $user, VentanillaRadicaReci $radicado): bool
    {
        return $user->hasPermissionTo('Radicar -> Cores. Recibida -> Actualizar clasificacion de radicados');
    }

    /**
     * Determina si el usuario puede cambiar el estado de un radicado.
     * 
     * @param User $user
     * @param VentanillaRadicaReci $radicado
     * @return bool
     */
    public function cambiarEstado(User $user, VentanillaRadicaReci $radicado): bool
    {
        return $user->hasPermissionTo('Radicar -> Cores. Recibida -> Editar');
    }

    /**
     * Determina si el usuario puede subir archivo digital.
     * 
     * @param User $user
     * @param VentanillaRadicaReci $radicado
     * @return bool
     */
    public function subirDigital(User $user, VentanillaRadicaReci $radicado): bool
    {
        return $user->hasPermissionTo('Radicar -> Cores. Recibida -> Subir digital');
    }

    /**
     * Determina si el usuario puede eliminar archivo digital.
     * 
     * @param User $user
     * @param VentanillaRadicaReci $radicado
     * @return bool
     */
    public function eliminarDigital(User $user, VentanillaRadicaReci $radicado): bool
    {
        // Solo el usuario que subió o administradores
        return $user->hasPermissionTo('Radicar -> Cores. Recibida -> Eliminar digital');
    }

    /**
     * Determina si el usuario puede subir archivos adjuntos.
     * 
     * @param User $user
     * @param VentanillaRadicaReci $radicado
     * @return bool
     */
    public function subirAdjuntos(User $user, VentanillaRadicaReci $radicado): bool
    {
        return $user->hasPermissionTo('Radicar -> Cores. Recibida -> Subir adjuntos');
    }

    /**
     * Determina si el usuario puede eliminar archivos adjuntos.
     * 
     * @param User $user
     * @param VentanillaRadicaReci $radicado
     * @return bool
     */
    public function eliminarAdjuntos(User $user, VentanillaRadicaReci $radicado): bool
    {
        return $user->hasPermissionTo('Radicar -> Cores. Recibida -> Eliminar adjuntos');
    }

    /**
     * Determina si el usuario puede comentar en un radicado.
     * 
     * @param User $user
     * @param VentanillaRadicaReci $radicado
     * @return bool
     */
    public function comentar(User $user, VentanillaRadicaReci $radicado): bool
    {
        return $user->hasPermissionTo('Radicar -> Cores. Recibida -> Comentar');
    }

    /**
     * Determina si el usuario puede ver comentarios.
     * 
     * @param User $user
     * @param VentanillaRadicaReci $radicado
     * @return bool
     */
    public function verComentarios(User $user, VentanillaRadicaReci $radicado): bool
    {
        return $user->hasPermissionTo('Radicar -> Cores. Recibida -> Mostrar');
    }

    /**
     * Determina si el usuario puede notificar por email.
     * 
     * @param User $user
     * @param VentanillaRadicaReci $radicado
     * @return bool
     */
    public function notificarEmail(User $user, VentanillaRadicaReci $radicado): bool
    {
        return $user->hasPermissionTo('Radicar -> Cores. Recibida -> Notificar Email');
    }

    /**
     * Determina si el usuario puede exportar metadatos.
     * 
     * @param User $user
     * @return bool
     */
    public function exportar(User $user): bool
    {
        return $user->hasPermissionTo('Radicar -> Cores. Recibida -> Exportar');
    }
}
