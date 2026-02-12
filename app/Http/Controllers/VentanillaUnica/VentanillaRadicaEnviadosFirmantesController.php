<?php

namespace App\Http\Controllers\VentanillaUnica;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Http\Requests\Ventanilla\UpdateVentanillaRadicaEnviadosFirmasRequest;
use App\Http\Requests\Ventanilla\VentanillaRadicaEnviadosFirmasRequest;
use App\Models\VentanillaUnica\VentanillaRadicaEnviadosFirmas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VentanillaRadicaEnviadosFirmantesController extends Controller
{
    use ApiResponseTrait;

    public function __construct()
    {
        $this->middleware('can:Radicar -> Cores. Enviada -> Editar');
    }

    public function index(Request $request)
    {
        try {
            $query = VentanillaRadicaEnviadosFirmas::with(['userCargo.user', 'userCargo.cargo', 'radicado']);

            if ($request->filled('radica_enviado_id')) {
                $query->where('radica_enviado_id', $request->radica_enviado_id);
            }

            if ($request->filled('user_id')) {
                $query->whereHas('userCargo', fn ($q) => $q->where('user_id', $request->user_id));
            }

            $query->orderBy('created_at', 'desc');

            $perPage = $request->get('per_page');
            $firmas = $perPage ? $query->paginate($perPage) : $query->get();

            return $this->successResponse($firmas, 'Listado de firmantes obtenido exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el listado de firmantes', $e->getMessage(), 500);
        }
    }

    public function store(VentanillaRadicaEnviadosFirmasRequest $request)
    {
        try {
            DB::beginTransaction();

            $firmasData = $request->validated()['firmas'] ?? [];

            if (empty($firmasData)) {
                return $this->errorResponse('Se debe enviar un array de firmantes no vacío', null, 400);
            }

            $firmasCreadas = [];

            foreach ($firmasData as $item) {
                $radicaEnviadoId = $item['radica_enviado_id'] ?? $request->route('radica_enviado_id');
                if (!$radicaEnviadoId) {
                    return $this->errorResponse('Cada firmante debe incluir radica_enviado_id', null, 400);
                }

                $firma = VentanillaRadicaEnviadosFirmas::create([
                    'radica_enviado_id' => (int) $radicaEnviadoId,
                    'users_cargos_id' => (int) $item['users_cargos_id'],
                ]);
                $firmasCreadas[] = $firma->load(['userCargo.user', 'userCargo.cargo', 'radicado']);
            }

            DB::commit();

            return $this->successResponse($firmasCreadas, 'Firmantes asignados exitosamente', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al asignar firmantes', $e->getMessage(), 500);
        }
    }

    public function assignToRadicado($radica_enviado_id, VentanillaRadicaEnviadosFirmasRequest $request)
    {
        try {
            DB::beginTransaction();

            $firmasData = $request->validated()['firmas'] ?? [];

            if (empty($firmasData)) {
                return $this->errorResponse('Se debe enviar un array de firmantes no vacío', null, 400);
            }

            $firmasCreadas = [];

            foreach ($firmasData as $item) {
                $firma = VentanillaRadicaEnviadosFirmas::create([
                    'radica_enviado_id' => (int) $radica_enviado_id,
                    'users_cargos_id' => (int) $item['users_cargos_id'],
                ]);
                $firmasCreadas[] = $firma->load(['userCargo.user', 'userCargo.cargo', 'radicado']);
            }

            DB::commit();

            return $this->successResponse($firmasCreadas, 'Firmantes asignados exitosamente', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return $this->errorResponse('Error de validación', $e->errors(), 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al asignar firmantes', $e->getMessage(), 500);
        }
    }

    public function show($id)
    {
        try {
            $firma = VentanillaRadicaEnviadosFirmas::with(['userCargo.user', 'userCargo.cargo', 'radicado'])->find($id);

            if (!$firma) {
                return $this->errorResponse('Firmante no encontrado', null, 404);
            }

            return $this->successResponse($firma, 'Firmante encontrado exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el firmante', $e->getMessage(), 500);
        }
    }

    public function update($id, UpdateVentanillaRadicaEnviadosFirmasRequest $request)
    {
        try {
            DB::beginTransaction();

            $firma = VentanillaRadicaEnviadosFirmas::find($id);

            if (!$firma) {
                return $this->errorResponse('Firmante no encontrado', null, 404);
            }

            $updateData = $request->only(['radica_enviado_id', 'users_cargos_id']);
            $updateData = array_filter($updateData, fn ($v) => $v !== null && $v !== '');

            if (!empty($updateData)) {
                $firma->update($updateData);
            }

            DB::commit();

            return $this->successResponse(
                $firma->fresh(['userCargo.user', 'userCargo.cargo', 'radicado']),
                'Firmante actualizado exitosamente'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al actualizar el firmante', $e->getMessage(), 500);
        }
    }

    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            $firma = VentanillaRadicaEnviadosFirmas::find($id);

            if (!$firma) {
                return $this->errorResponse('Firmante no encontrado', null, 404);
            }

            $firma->delete();

            DB::commit();

            return $this->successResponse(null, 'Firmante eliminado exitosamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al eliminar el firmante', $e->getMessage(), 500);
        }
    }

    public function getByRadicado($radica_enviado_id)
    {
        try {
            $firmas = VentanillaRadicaEnviadosFirmas::with(['userCargo.user', 'userCargo.cargo'])
                ->where('radica_enviado_id', $radica_enviado_id)
                ->orderBy('created_at', 'desc')
                ->get();

            return $this->successResponse($firmas, 'Firmantes del radicado obtenidos exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener los firmantes', $e->getMessage(), 500);
        }
    }
}
