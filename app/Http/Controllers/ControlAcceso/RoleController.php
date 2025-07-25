<?php

namespace App\Http\Controllers\ControlAcceso;

use App\Http\Controllers\Controller;
use Spatie\Permission\Models\Role;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class RoleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $registros = Role::all();

        return response()->json([
            'status' => true,
            'data' =>  $registros,
            'message' => 'Listado de roles'
        ], 200);
    }

    /**
     * Display a listing of the resource.
     */
    public function listPermisos()
    {
        $permission = Permission::orderBy('name', 'asc')->get();

        return response()->json([
            'status' => true,
            'data' =>  $permission,
            'message' => 'Listado de roles y permisos'
        ], 200);
    }

    /**
     * Display a listing of the resource.
     */
    public function listRolesPermisos()
    {
        $roles = Role::with('permissions')->get();

        return response()->json([
            'status' => true,
            'data' =>  $roles,
            'message' => 'Listado de roles y permisos'
        ], 200);
    }


    /**
     * Listar roles con permisos y cantidad de usuarios asignados.
     */
    public function rolesConUsuarios()
    {
        $roles = Role::with('permissions')->withCount('users')->get();
        return response()->json($roles);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Validaciones
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:roles|max:255',
            'permissions' => 'required|array', // Los permisos son obligatorios y deben ser un arreglo
            'permissions.*' => 'exists:permissions,name', // Cada permiso debe existir en la tabla permissions
        ], [
            'name.required' => 'El nombre del rol es obligatorio.',
            'name.unique' => 'El nombre del rol ya se encuentra registrado.',
            'permissions.required' => 'Debe asignar al menos un permiso al rol.',
            'permissions.array' => 'Los permisos deben enviarse como un arreglo.',
            'permissions.*.exists' => 'Uno o más permisos no existen en el sistema.',
        ]);

        // Verificar si la validación falla
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422); // Devuelve un código 422 (Unprocessable Entity) para validaciones fallidas
        }

        DB::beginTransaction();
        try {
            // Crear el rol
            $role = Role::create(['name' => $request->name]);

            // Asignar permisos al rol
            $role->syncPermissions($request->permissions);
            DB::commit();

            return response()->json([
                'status' => true,
                'data' => $role->load('permissions'),
                'message' => 'Rol creado exitosamente.',
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            // Manejo de excepciones
            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al crear el rol.',
                'error' => $e->getMessage(), // Útil para depuración; eliminar en producción
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $role = Role::with('permissions')->find($id);

        if (!$role) {
            return response()->json([
                'status' => false,
                'message' => 'Rol no encontrado'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $role,
            'message' => 'Rol encontrado'
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Role $role)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|unique:roles,name,' . $role->id . '|max:255',
            'permissions' => 'required|array',
            'permissions.*' => 'exists:permissions,name'
        ], [
            'name.required' => 'El nombre del rol es obligatorio.',
            'name.unique' => 'El nombre del rol ya está en uso.',
            'permissions.required' => 'Debe asignar al menos un permiso.',
            'permissions.*.exists' => 'Uno o más permisos no son válidos.'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422); // Usar 422 en ambos métodos
        }

        DB::beginTransaction();
        try {
            $role->update(['name' => $request->name]);
            $role->syncPermissions($request->permissions);
            DB::commit();

            return response()->json([
                'status' => true,
                'data' => $role->load('permissions'),
                'message' => 'Rol actualizado exitosamente'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al actualizar el rol.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $role = Role::find($id);

        if (!$role) {
            return response()->json([
                'status' => false,
                'message' => 'Rol no encontrado'
            ], 404);
        }

        // Antes de eliminar un rol, podrías verificar si hay usuarios asignados a ese rol
        // y prevenir la eliminación si es necesario, o advertir al usuario.
        // Por ahora, solo eliminamos si no hay usuarios asignados.
        if ($role->users()->count() > 0) {
            return response()->json([
                'status' => false,
                'message' => 'No se puede eliminar el rol porque hay usuarios asignados a él.'
            ], 409); // Conflict
        }

        $role->delete();

        return response()->json([
            'status' => true,
            'message' => 'Rol eliminado exitosamente'
        ], 200);
    }
}
