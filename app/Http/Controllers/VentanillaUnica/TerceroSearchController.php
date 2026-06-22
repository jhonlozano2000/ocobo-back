<?php

namespace App\Http\Controllers\VentanillaUnica;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Gestion\GestionTercero;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador para buscar terceros por correo electrónico.
 * Utilizado en el flujo de radicación de emails para auto-detectar el remitente.
 */
class TerceroSearchController extends Controller
{
    use ApiResponseTrait;

    /**
     * Busca un tercero por su dirección de correo electrónico.
     *
     * @queryParam email string required Email a buscar. Example: "juan@example.com"
     */
    public function buscarPorEmail(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'email' => 'required|email',
            ]);

            $email = strtolower(trim($request->email));

            $tercero = GestionTercero::whereRaw('LOWER(email) = ?', [$email])->first();

            if (! $tercero) {
                return $this->errorResponse(
                    'No se encontró un tercero con ese correo electrónico',
                    null,
                    404
                );
            }

            return $this->successResponse([
                'id' => $tercero->id,
                'num_docu_nit' => $tercero->num_docu_nit,
                'nom_razo_soci' => $tercero->nom_razo_soci,
                'tipo' => $tercero->tipo,
                'telefono' => $tercero->telefono,
                'email' => $tercero->email,
                'direccion' => $tercero->direccion,
                'divi_poli_id' => $tercero->divi_poli_id,
            ], 'Tercero encontrado exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Error al buscar el tercero',
                $e->getMessage(),
                500
            );
        }
    }
}
