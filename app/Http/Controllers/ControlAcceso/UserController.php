<?php

namespace App\Http\Controllers\ControlAcceso;

use App\Helpers\ArchivoHelper;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\User;
use App\Http\Requests\ControlAcceso\StoreUserRequest;
use App\Http\Requests\ControlAcceso\UpdateUserRequest;
use App\Http\Requests\ControlAcceso\UpdateUserProfileRequest;
use App\Http\Requests\ControlAcceso\UpdatePasswordRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Services\ControlAcceso\UserService;

class UserController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly UserService $service
    ) {}

    /**
     * Listado de usuarios.
     */
    public function index(Request $request)
    {
        try {
            $filters = $request->all();
            $users = $this->service->getAll($filters);

            return $this->successResponse($users, 'Listado de usuarios obtenido exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el listado', $e->getMessage(), 500);
        }
    }

    /**
     * Crea un nuevo usuario.
     */
    public function store(StoreUserRequest $request)
    {
        try {
            $validated = $request->validated();
            
            if (isset($validated['estado'])) {
                $validated['estado'] = filter_var($validated['estado'], FILTER_VALIDATE_BOOLEAN);
            }

            $user = $this->service->create($validated);

            return $this->successResponse(
                $user->load('roles')->append(['avatar_url', 'firma_url']),
                'Usuario creado exitosamente',
                201
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Error al crear el usuario', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene un usuario específico.
     */
    public function show(string $id)
    {
        try {
            $user = $this->service->getById($id);

            if (!$user) {
                return $this->errorResponse('Usuario no encontrado', null, 404);
            }

            return $this->successResponse($user, 'Usuario encontrado exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el usuario', $e->getMessage(), 500);
        }
    }

    /**
     * Actualiza un usuario.
     */
    public function update(UpdateUserRequest $request, string $id)
    {
        try {
            $validated = $request->validated();

            if (isset($validated['estado'])) {
                $validated['estado'] = filter_var($validated['estado'], FILTER_VALIDATE_BOOLEAN);
            }

            $user = $this->service->update($id, $validated);

            if (!$user) {
                return $this->errorResponse('Usuario no encontrado', null, 404);
            }

            return $this->successResponse(
                $user->load('roles')->append(['avatar_url', 'firma_url']),
                'Usuario actualizado exitosamente'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Error al actualizar el usuario', $e->getMessage(), 500);
        }
    }

    /**
     * Elimina un usuario.
     */
    public function destroy(string $id)
    {
        try {
            if (!$this->service->delete($id)) {
                return $this->errorResponse('Usuario no encontrado', null, 404);
            }

            return $this->successResponse(null, 'Usuario eliminado exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al eliminar el usuario', $e->getMessage(), 500);
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
                'Estadísticas obtenidas exitosamente'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener estadísticas', $e->getMessage(), 500);
        }
    }

    /**
     * Actualiza perfil del usuario autenticado.
     */
    public function updateUserProfile(UpdateUserProfileRequest $request)
    {
        try {
            $user = Auth::user();
            $user->update($request->validated());

            return $this->successResponse(
                $user->append(['avatar_url', 'firma_url']),
                'Perfil actualizado exitosamente'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Error al actualizar el perfil', $e->getMessage(), 500);
        }
    }

    /**
     * Actualiza contraseña.
     */
    public function updatePassword(UpdatePasswordRequest $request)
    {
        try {
            $user = Auth::user();

            if (!Hash::check($request->validated('current_password'), $user->password)) {
                throw ValidationException::withMessages([
                    'current_password' => 'La contraseña actual que ingresaste no es correcta.',
                ]);
            }

            $user->forceFill(['password' => Hash::make($request->validated('password'))])->save();

            return $this->successResponse(null, 'Contraseña actualizada exitosamente');
        } catch (ValidationException $e) {
            return $this->errorResponse('Datos de validación incorrectos', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al actualizar la contraseña', $e->getMessage(), 500);
        }
    }
}