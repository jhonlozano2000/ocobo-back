<?php

namespace App\Http\Controllers\ControlAcceso;

use App\Http\Controllers\Controller;
use App\Models\ControlAcceso\RoleControlle;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Validator;

class RoleControlleController extends Controller
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
            'message' => 'Listado de roles y permisos'
        ], 200);
    }

    /**
     * Display a listing of the resource.
     */
    public function listPermisos()
    {
        $permission = Permission::get();

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

        try {
            // Crear el rol
            $role = Role::create(['name' => $request->name]);

            // Asignar permisos al rol
            $role->syncPermissions($request->permissions);

            return response()->json([
                'status' => true,
                'data' => $role->load('permissions'),
                'message' => 'Rol creado exitosamente.',
            ], 201);
        } catch (\Exception $e) {
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
            ], 400);
        }

        $role->update(['name' => $request->name]);
        $role->syncPermissions($request->permissions);

        return response()->json([
            'status' => true,
            'data' => $role->load('permissions'),
            'message' => 'Rol actualizado exitosamente'
        ], 200);
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

        $role->delete();

        return response()->json([
            'status' => true,
            'message' => 'Rol eliminado exitosamente'
        ], 200);
    }
}
