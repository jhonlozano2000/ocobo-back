<?php

namespace App\Http\Controllers\ControlAcceso;

use App\Http\Controllers\Controller;
use App\Http\Requests\ControlAcceso\UserRequest;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Str;

class UserControlle extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $users = User::with(['cargos', 'roles', 'permissions'])->get();
        return response()->json([
            'status' => true,
            'data' => $users,
            'message' => 'Listado de usuarios'
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(UserRequest $request)
    {
        // Crear el nuevo usuario
        $user = new User();
        $user->num_docu = $request->num_docu;
        $user->nombres = $request->nombres;
        $user->apellidos = $request->apellidos;
        $user->tel = $request->tel;
        $user->movil = $request->movil;
        $user->dir = $request->dir;
        $user->email = $request->email;
        $user->divi_poli_id = $request->divi_poli_id; // Asignar división política

        // Asignar roles
        $user->assignRole($request->roles);

        // Manejo de archivos (avatar y firma)
        $user->avatar = $this->guardarArchivo($request, 'avatar', 'avatars');
        $user->firma = $this->guardarArchivo($request, 'firma', 'firmas');

        // Actualizo la contraseña
        if ($request->has('password')) {
            $user->password = bcrypt($request->input('password'));
            $user->save();
        }

        // Asignar el nuevo cargo (finalizando los anteriores automáticamente)
        $user->assignCargo($request->cargo_id);

        return response()->json([
            'status' => true,
            'data' => $user,
            'message' => 'Usuario creado correctamente'
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Usuario no encontrado'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $user,
            'message' => 'Usuario encontrado'
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UserRequest $request, string $id)
    {

        // Encuentra el usuario por ID
        $user = User::find($id);
        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Usuario no encontrado'
            ], 404);
        }

        // Actualiza el usuario
        $user->update([
            'num_docu' => $request->num_docu,
            'nombres' => $request->nombres,
            'apellidos' => $request->apellidos,
            'tel' => $request->tel,
            'movil' => $request->movil,
            'dir' => $request->dir,
            'divi_poli_id' => $request->divi_poli_id, // Actualizar división política
        ]);

        // Almaceno los roles
        $user->assignRole($request->roles);

        // Si la contraseña debe ser hasheada antes de guardar:
        if ($request->has('password')) {
            $user->password = bcrypt($request->input('password'));
            $user->save();
        }

        // Asignar el nuevo cargo (finalizando los anteriores automáticamente)
        $user->assignCargo($request->cargo_id);

        return response()->json([
            'status' => true,
            $user,
            'message' => 'Usuario actualizado correctamente'
        ], 201);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        // Encuentra el usuario por ID
        $user = User::find($id);
        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Usuario no encontrado'
            ], 404);
        }

        // Elimina el usuario
        $user->delete();

        return response()->json([
            'status' => true,
            'message' => 'Usuario eliminado correctamente'
        ], 200);
    }

    public function estadisticas()
    {
        $totalUsers = User::count();
        $totalUsersActivos = User::where('estado', 1)->count();
        $totalUsersInactivos = User::where('estado', 0)->count();

        return response()->json([
            'status' => true,
            'data' => [
                'total_users' => $totalUsers,
                'total_users_activos' => $totalUsersActivos,
                'total_users_inactivos' => $totalUsersInactivos,
            ]
        ], 200);
    }

    private function guardarArchivo($request, $campo, $disk, $archivoActual = null)
    {
        if ($request->hasFile($campo)) {
            $file = $request->file($campo);

            // Eliminar archivo anterior si existe
            if ($archivoActual && Storage::disk($disk)->exists($archivoActual)) {
                Storage::disk($disk)->delete($archivoActual);
            }

            // Guardar el nuevo archivo
            $nombreArchivo = Str::random(50) . "." . $file->extension();
            return $file->storeAs($disk, $nombreArchivo);
        }

        return $archivoActual;
    }
}
