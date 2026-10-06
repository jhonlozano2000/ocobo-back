<?php

namespace App\Http\Controllers\ControlAcceso;

use App\Http\Controllers\Controller;
use App\Http\Requests\ControlAcceso\ListRoleRequest;
use App\Http\Requests\ControlAcceso\StoreRoleRequest;
use App\Http\Requests\ControlAcceso\UpdateRoleRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Services\ControlAcceso\RoleService;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class RoleController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly RoleService $service
    ) {}

    /**
     * Listado de roles.
     */
    public function index(ListRoleRequest $request)
    {
        try {
            $filters = $request->validated();
            $roles = $this->service->getAll($filters);

            return $this->successResponse($roles, 'Listado de roles obtenido exitosamente');
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->serverErrorResponse('Error al obtener el listado', $e->getMessage());
        }
    }

    /**
     * Listado de permisos.
     */
    public function listPermisos(ListRoleRequest $request)
    {
        try {
            $filters = $request->validated();
            $permissions = $this->service->getAllPermissions($filters);

            return $this->successResponse($permissions, 'Listado de permisos obtenido exitosamente');
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->serverErrorResponse('Error al obtener el listado', $e->getMessage());
        }
    }

    /**
     * Roles con usuarios.
     */
    public function rolesConUsuarios()
    {
        try {
            return $this->successResponse(
                $this->service->getWithUsers(),
                'Listado de roles con usuarios obtenido exitosamente'
            );
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->serverErrorResponse('Error al obtener roles con usuarios', $e->getMessage());
        }
    }

    /**
     * Crea un nuevo rol.
     */
    public function store(StoreRoleRequest $request)
    {
        try {
            $role = $this->service->create($request->validated());

            return $this->createdResponse($role->load('permissions'), 'Rol creado exitosamente');
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->serverErrorResponse('Error al crear el rol', $e->getMessage());
        }
    }

    /**
     * Obtiene un rol específico.
     */
    public function show(string $id)
    {
        try {
            $role = Role::with('permissions')->find($id);

            if (! $role) {
                return $this->notFoundResponse('Rol no encontrado');
            }

            return $this->successResponse($role, 'Rol encontrado exitosamente');
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->serverErrorResponse('Error al obtener el rol', $e->getMessage());
        }
    }

    /**
     * Actualiza un rol.
     */
    public function update(UpdateRoleRequest $request, Role $role)
    {
        try {
            $updated = $this->service->update($role->id, $request->validated());

            return $this->successResponse($updated->load('permissions'), 'Rol actualizado exitosamente');
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->serverErrorResponse('Error al actualizar el rol', $e->getMessage());
        }
    }

    /**
     * Elimina un rol.
     */
    public function destroy(string $id)
    {
        try {
            $result = $this->service->delete($id);

            if (! $result['success']) {
                if ($result['code'] === 409) {
                    return $this->conflictResponse(
                        $result['message'],
                        ['users_count' => $result['users_count'] ?? null]
                    );
                }

                if ($result['code'] === 404) {
                    return $this->notFoundResponse($result['message']);
                }

                return $this->warningResponse($result['message']);
            }

            return $this->successResponse(null, 'Rol eliminado exitosamente');
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->serverErrorResponse('Error al eliminar el rol', $e->getMessage());
        }
    }

    /**
     * Estadísticas.
     */
    public function estadisticas()
    {
        try {
            return $this->successResponse(
                $this->service->getStats(),
                'Estadísticas de roles obtenidas exitosamente'
            );
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->serverErrorResponse('Error al obtener estadísticas', $e->getMessage());
        }
    }

    /**
     * Listado de roles con sus permisos.
     */
    public function listRolesPermisos()
    {
        try {
            return $this->successResponse(
                $this->service->getAllWithPermissions(),
                'Listado de roles con permisos obtenido exitosamente'
            );
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->serverErrorResponse('Error al obtener roles y permisos', $e->getMessage());
        }
    }
}
