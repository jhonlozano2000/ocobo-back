<?php

namespace App\Http\Controllers\MiBandeja\Enviados;

use App\Http\Controllers\Controller;
use App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviados as VentanillaRadicaEnviado;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MiBandejaEnviadosController extends Controller
{
    /**
     * Obtener radicados enviados asignados al usuario actual (Mi Bandeja).
     * Filtra por los cargos del usuario a través de ventanilla_radica_enviados_responsa.
     */
    public function misRadicados(Request $request)
    {
        try {
            $userId = Auth::id();
            $search = $request->get('search', '');
            $estado = $request->get('estado', '');

            $query = VentanillaRadicaEnviado::query()
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
                'message' => 'Mis radicados enviados obtenidos',
                'data' => $radicados,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error al obtener mis radicados enviados',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
