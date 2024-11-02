<?php

namespace App\Http\Controllers\ControlAcceso;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use PhpParser\Node\Stmt\TryCatch;
use \Validator;

class UserControlle extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $users = User::all();
        return response()->json(
            [
                'status' => true,
                'data' => $users,
                'message' => 'Listado de usuarios'
            ]
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Validación de los datos
        $validator = Validator::make($request->all(), [
            'num_docu' => 'required|string|max:20|unique:users',
            'nombres' => 'required|string|max:70',
            'apellidos' => 'required|string|max:70',
            'email' => 'required|string|email|max:70|unique:users',
            'password' => 'required|string|min:6',
        ], [
            'num_docu.unique' => 'El número de documento ya está en uso',
            'num_docu.required' => 'Te hizo falta el número de documento',
            'nombres.required' => 'Te hizo falta el nombre',
            'apellidos.required' => 'Te hizo falta el apellido',
            'email.required' => 'Te hizo falta el correo electrónico',
            'email.email' => 'El correo electrónico no es válido',
            'email.max' => 'El correo electrónico es demasiado largo',
            'email.unique' => 'El correo electrónico ya está en uso',
            'password.required' => 'Te hizo falta la contraseña',
            'password.min' => 'La contraseña debe tener al menos 6 caracteres',
        ]);

        // Verificar si la validación falla
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 400); // Devuelve un error 400 (Bad Request) con los errores
        }

        // Crear el nuevo usuario
        $user = User::create([
            'num_docu' => $request->num_docu,
            'nombres' => $request->nombres,
            'apellidos' => $request->apellidos,
            'tel' => $request->tel,
            'movil' => $request->movil,
            'dir' => $request->dir,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

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
    public function update(Request $request, string $id)
    {
        // Encuentra el usuario por ID
        $user = User::find($id);
        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Usuario no encontrado'
            ], 404);
        }

        // Validación de los datos
        $validator = Validator::make($request->all(), [
            'num_docu' => 'required|string|max:20',
            'nombres' => 'required|string|max:70',
            'apellidos' => 'required|string|max:70',
            'email' => 'required|string|email|max:70|unique:users',
            'password' => 'required|string|min:6',
        ], [
            'num_docu.required' => 'Te hizo falta el número de documento',
            'nombres.required' => 'Te hizo falta el nombre',
            'apellidos.required' => 'Te hizo falta el apellido',
            'email.required' => 'Te hizo falta el correo electrónico',
            'email.email' => 'El correo electrónico no es válido',
            'email.max' => 'El correo electrónico es demasiado largo',
            'email.unique' => 'El correo electrónico ya está en uso',
            'password.required' => 'Te hizo falta la contraseña',
            'password.min' => 'La contraseña debe tener al menos 6 caracteres',
        ]);

        // Verificar si la validación falla
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 400); // Devuelve un error 400 (Bad Request) con los errores
        }

        // Actualiza el usuario
        $user->update($request->only([
            'num_docu',
            'nombres',
            'apellidos',
            'dir',
            'tel',
            'movil',
            'password'
        ]));

        // Si la contraseña debe ser hasheada antes de guardar:
        if ($request->has('password')) {
            $user->password = bcrypt($request->input('password'));
            $user->save();
        }

        return response()->json([
            'status' => true,
            'data' => $user,
            'message' => 'Usuario actualizado correctamente'
        ], 200);
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
}
