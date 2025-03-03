<?php

namespace App\Http\Controllers\VentanillaUnica;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\VentanillaUnica\VentanillaUnica;
use Illuminate\Http\Request;

class PermisosVentanillaUnicaController extends Controller
{
    /**
     * Asignar permisos de radicación a un usuario en una ventanilla
     */
    public function asignarPermisos(Request $request, $ventanillaId)
    {
        $request->validate([
            'usuarios' => 'required|array|min:1',
            'usuarios.*' => 'exists:users,id'
        ]);

        $ventanilla = VentanillaUnica::findOrFail($ventanillaId);
        $ventanilla->usuariosPermitidos()->sync($request->usuarios);

        return response()->json([
            'status' => true,
            'message' => 'Permisos asignados correctamente'
        ]);
    }

    /**
     * Listar ventanillas en las que un usuario puede radicar
     */
    public function listarVentanillasPermitidas($usuarioId)
    {
        $usuario = User::findOrFail($usuarioId);
        $ventanillas = $usuario->ventanillasPermitidas;

        return response()->json([
            'status' => true,
            'data' => $ventanillas
        ]);
    }
}
