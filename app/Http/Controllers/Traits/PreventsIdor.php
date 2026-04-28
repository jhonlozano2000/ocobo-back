<?php

namespace App\Http\Controllers\Traits;

use App\Services\Seguridad\AuditLogService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Trait para Prevención de IDOR (Insecure Direct Object Reference)
 *
 * OWASP A01:2021 - Broken Access Control
 *
 * Proporciona métodos para verificar que el usuario actual
 * tiene permiso para acceder/modificar un recurso específico.
 *
 * Uso:
 * 1. Agregar el trait al controller
 * 2. Usar $this->authorizeOwnership($request, $model, 'user_id') en métodos
 */
trait PreventsIdor
{
    /**
     * Verifica que el usuario actual es dueño del recurso
     *
     * @param Request $request
     * @param Model $model
     * @param string $ownerField Campo que contiene el ID del dueño
     * @param string|null $message Mensaje de error personalizado
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    protected function authorizeOwnership(Request $request, Model $model, string $ownerField = 'user_id', ?string $message = null): void
    {
        $user = $request->user();

        if (!$user) {
            abort(401, 'No autenticado.');
        }

        $resourceId = $model->getKey();
        $ownerId = $model->{$ownerField};

        // Superusers pueden acceder a todo
        if ($user->hasRole('superadmin') || $user->hasRole('admin')) {
            return;
        }

        if ($ownerId !== $user->id) {
            // Registrar el intento de acceso no autorizado
            AuditLogService::logPermisoDenegado(
                'access',
                get_class($model) . ':' . $resourceId
            );

            AuditLogService::logIntentoIntrusion(
                'idor',
                'Intento de acceso a recurso de otro usuario',
                [
                    'model' => get_class($model),
                    'resource_id' => $resourceId,
                    'owner_id' => $ownerId,
                    'requesting_user' => $user->id,
                ]
            );

            abort(403, $message ?? 'No tiene permiso para acceder a este recurso.');
        }
    }

    /**
     * Verifica que el recurso pertenece a la organización/del usuario actual
     *
     * @param Request $request
     * @param Model $model
     * @param string $orgField Campo que contiene el ID de organización
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    protected function authorizeOrganizationAccess(Request $request, Model $model, string $orgField = 'empresa_id'): void
    {
        $user = $request->user();

        if (!$user) {
            abort(401, 'No autenticado.');
        }

        // Superusers pueden acceder a todo
        if ($user->hasRole('superadmin') || $user->hasRole('admin')) {
            return;
        }

        $orgId = $model->{$orgField} ?? null;

        if ($orgId && $orgId !== $user->empresa_id) {
            AuditLogService::logPermisoDenegado(
                'organization_access',
                get_class($model) . ':' . $model->getKey()
            );

            abort(403, 'No tiene permiso para acceder a este recurso.');
        }
    }

    /**
     * Verifica acceso a múltiples recursos (para operaciones batch)
     *
     * @param Request $request
     * @param array $models Array de modelos
     * @param string $ownerField Campo que contiene el ID del dueño
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    protected function authorizeBulkOwnership(Request $request, array $models, string $ownerField = 'user_id'): void
    {
        $user = $request->user();

        if (!$user) {
            abort(401, 'No autenticado.');
        }

        // Superusers pueden acceder a todo
        if ($user->hasRole('superadmin') || $user->hasRole('admin')) {
            return;
        }

        $unauthorizedIds = [];

        foreach ($models as $model) {
            if ($model->{$ownerField} !== $user->id) {
                $unauthorizedIds[] = $model->getKey();
            }
        }

        if (!empty($unauthorizedIds)) {
            AuditLogService::logPermisoDenegado(
                'bulk_access',
                'IDs: ' . implode(', ', $unauthorizedIds)
            );

            abort(403, 'No tiene permiso para acceder a algunos de los recursos solicitados.');
        }
    }

    /**
     * Verifica que el usuario tiene acceso a un recurso por su ID
     * (Útil para rutas like /api/resource/{id})
     *
     * @param Request $request
     * @param string $modelClass Clase del modelo
     * @param int $resourceId ID del recurso
     * @param string $ownerField Campo que contiene el ID del dueño
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    protected function authorizeResourceAccess(Request $request, string $modelClass, int $resourceId, string $ownerField = 'user_id'): void
    {
        $user = $request->user();

        if (!$user) {
            abort(401, 'No autenticado.');
        }

        // Superusers pueden acceder a todo
        if ($user->hasRole('superadmin') || $user->hasRole('admin')) {
            return;
        }

        $model = $modelClass::find($resourceId);

        if (!$model) {
            abort(404, 'Recurso no encontrado.');
        }

        if ($model->{$ownerField} !== $user->id) {
            AuditLogService::logPermisoDenegado(
                'resource_access',
                $modelClass . ':' . $resourceId
            );

            AuditLogService::logIntentoIntrusion(
                'idor',
                'Intento de acceso a recurso por ID',
                [
                    'model' => $modelClass,
                    'resource_id' => $resourceId,
                    'owner_id' => $model->{$ownerField},
                    'requesting_user' => $user->id,
                ]
            );

            abort(403, 'No tiene permiso para acceder a este recurso.');
        }
    }
}
