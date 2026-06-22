<?php

namespace App\Http\Controllers\MiBandeja\Recibidos;

use App\Http\Controllers\Controller;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MiBandejaRecibidosController extends Controller
{
    /**
     * Obtener radicados asignados al usuario actual (Mi Bandeja).
     * Filtra por los cargos del usuario a través de ventanilla_radica_reci_responsa.
     */
    public function misRadicados(Request $request)
    {
        try {
            $userId = Auth::id();
            $search = $request->get('search', '');
            $estado = $request->get('estado', '');

            $query = VentanillaRadicaReci::query()
                ->whereHas('usuariosResponsables', function ($q) use ($userId) {
                    $q->where('users_cargos.user_id', $userId);
                })
                ->orderBy('created_at', 'desc');

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('num_radicado', 'like', "%{$search}%")
                        ->orWhere('asunto', 'like', "%{$search}%");
                });
            }

            if ($estado) {
                $query->where('estado_trabajo', $estado);
            }

            $radicados = $query->limit(50)->get();

            return response()->json([
                'status' => true,
                'message' => 'Mis radicados obtenidos',
                'data' => $radicados,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error al obtener mis radicados',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
