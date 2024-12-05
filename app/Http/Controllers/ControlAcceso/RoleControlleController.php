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
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|unique:roles|max:255',
            'permissions' => 'array', // Los permisos se envían como un array
            'permissions.*' => 'exists:permissions,name' // Valida que cada permiso exista
        ]);

        // Verificar si la validación falla
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 400); // Devuelve un error 400 (Bad Request) con los errores
        }

        $role = Role::create(['name' => $request->name]);

        // Asignar permisos al rol si se proporcionan
        if ($request->has('permissions')) {
            $role->syncPermissions($request->permissions);
        }

        return response()->json([
            'status' => true,
            'data' => $role->load('permissions'),
            'message' => 'Rol creado exitosamente'
        ], 201);
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
    public function update(Request $request, string $id)
    {
        $role = Role::find($id);

        if (!$role) {
            return response()->json([
                'status' => false,
                'message' => 'Rol no encontrado'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|unique:roles,name,' . $id . '|max:255',
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,name'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $role->name = $request->name;
        $role->save();

        // Actualizar permisos del rol
        if ($request->has('permissions')) {
            $role->syncPermissions($request->permissions);
        }

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
