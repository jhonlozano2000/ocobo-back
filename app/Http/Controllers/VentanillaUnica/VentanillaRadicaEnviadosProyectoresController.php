<?php

namespace App\Http\Controllers\VentanillaUnica;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Http\Requests\Ventanilla\UpdateVentanillaRadicaEnviadosProyectoresRequest;
use App\Http\Requests\Ventanilla\VentanillaRadicaEnviadosProyectoresRequest;
use App\Models\VentanillaUnica\VentanillaRadicaEnviadosProyectores;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VentanillaRadicaEnviadosProyectoresController extends Controller
{
    use ApiResponseTrait;

    public function __construct()
    {
        $this->middleware('can:Radicar -> Cores. Enviada -> Editar');
    }

    public function index(Request $request)
    {
        try {
            $query = VentanillaRadicaEnviadosProyectores::with(['userCargo.user', 'userCargo.cargo', 'radicado']);

            if ($request->filled('radica_enviado_id')) {
                $query->where('radica_enviado_id', $request->radica_enviado_id);
            }

            if ($request->filled('user_id')) {
                $query->whereHas('userCargo', fn ($q) => $q->where('user_id', $request->user_id));
            }

            $query->orderBy('created_at', 'desc');

            $perPage = $request->get('per_page');
            $proyectores = $perPage ? $query->paginate($perPage) : $query->get();

            return $this->successResponse($proyectores, 'Listado de proyectores obtenido exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el listado de proyectores', $e->getMessage(), 500);
        }
    }

    public function store(VentanillaRadicaEnviadosProyectoresRequest $request)
    {
        try {
            DB::beginTransaction();

            $proyectoresData = $request->validated()['proyectores'] ?? [];

            if (empty($proyectoresData)) {
                return $this->errorResponse('Se debe enviar un array de proyectores no vacío', null, 400);
            }

            $proyectoresCreados = [];

            foreach ($proyectoresData as $item) {
                $radicaEnviadoId = $item['radica_enviado_id'] ?? $request->route('radica_enviado_id');
                if (!$radicaEnviadoId) {
                    return $this->errorResponse('Cada proyector debe incluir radica_enviado_id', null, 400);
                }

                $proyector = VentanillaRadicaEnviadosProyectores::create([
                    'radica_enviado_id' => (int) $radicaEnviadoId,
                    'users_cargos_id' => (int) $item['users_cargos_id'],
                ]);
                $proyectoresCreados[] = $proyector->load(['userCargo.user', 'userCargo.cargo', 'radicado']);
            }

            DB::commit();

            return $this->successResponse($proyectoresCreados, 'Proyectores asignados exitosamente', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al asignar proyectores', $e->getMessage(), 500);
        }
    }

    public function assignToRadicado($radica_enviado_id, VentanillaRadicaEnviadosProyectoresRequest $request)
    {
        try {
            DB::beginTransaction();

            $proyectoresData = $request->validated()['proyectores'] ?? [];

            if (empty($proyectoresData)) {
                return $this->errorResponse('Se debe enviar un array de proyectores no vacío', null, 400);
            }

            $proyectoresCreados = [];

            foreach ($proyectoresData as $item) {
                $proyector = VentanillaRadicaEnviadosProyectores::create([
                    'radica_enviado_id' => (int) $radica_enviado_id,
                    'users_cargos_id' => (int) $item['users_cargos_id'],
                ]);
                $proyectoresCreados[] = $proyector->load(['userCargo.user', 'userCargo.cargo', 'radicado']);
            }

            DB::commit();

            return $this->successResponse($proyectoresCreados, 'Proyectores asignados exitosamente', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return $this->errorResponse('Error de validación', $e->errors(), 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al asignar proyectores', $e->getMessage(), 500);
        }
    }

    public function show($id)
    {
        try {
            $proyector = VentanillaRadicaEnviadosProyectores::with(['userCargo.user', 'userCargo.cargo', 'radicado'])->find($id);

            if (!$proyector) {
                return $this->errorResponse('Proyector no encontrado', null, 404);
            }

            return $this->successResponse($proyector, 'Proyector encontrado exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el proyector', $e->getMessage(), 500);
        }
    }

    public function update($id, UpdateVentanillaRadicaEnviadosProyectoresRequest $request)
    {
        try {
            DB::beginTransaction();

            $proyector = VentanillaRadicaEnviadosProyectores::find($id);

            if (!$proyector) {
                return $this->errorResponse('Proyector no encontrado', null, 404);
            }

            $updateData = $request->only(['radica_enviado_id', 'users_cargos_id']);
            $updateData = array_filter($updateData, fn ($v) => $v !== null && $v !== '');

            if (!empty($updateData)) {
                $proyector->update($updateData);
            }

            DB::commit();

            return $this->successResponse(
                $proyector->fresh(['userCargo.user', 'userCargo.cargo', 'radicado']),
                'Proyector actualizado exitosamente'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al actualizar el proyector', $e->getMessage(), 500);
        }
    }

    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            $proyector = VentanillaRadicaEnviadosProyectores::find($id);

            if (!$proyector) {
                return $this->errorResponse('Proyector no encontrado', null, 404);
            }

            $proyector->delete();

            DB::commit();

            return $this->successResponse(null, 'Proyector eliminado exitosamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al eliminar el proyector', $e->getMessage(), 500);
        }
    }

    public function getByRadicado($radica_enviado_id)
    {
        try {
            $proyectores = VentanillaRadicaEnviadosProyectores::with(['userCargo.user', 'userCargo.cargo'])
                ->where('radica_enviado_id', $radica_enviado_id)
                ->orderBy('created_at', 'desc')
                ->get();

            return $this->successResponse($proyectores, 'Proyectores del radicado obtenidos exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener los proyectores', $e->getMessage(), 500);
        }
    }
}
