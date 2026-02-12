<?php

namespace App\Http\Controllers\VentanillaUnica;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Http\Requests\Ventanilla\UpdateVentanillaRadicaEnviadosResponsaRequest;
use App\Http\Requests\Ventanilla\VentanillaRadicaEnviadosResponsaRequest;
use App\Http\Requests\Ventanilla\ListResponsablesEnviadosRequest;
use App\Models\VentanillaUnica\VentanillaRadicaEnviadosRespona;
use Illuminate\Support\Facades\DB;

class VentanillaRadicaEnviadosResponsaController extends Controller
{
    use ApiResponseTrait;

    public function __construct()
    {
        $this->middleware('can:Radicar -> Cores. Enviada -> Editar');
    }

    public function index(ListResponsablesEnviadosRequest $request)
    {
        try {
            $query = VentanillaRadicaEnviadosRespona::with(['userCargo.user', 'userCargo.cargo', 'radicado']);

            if ($request->filled('radica_enviado_id')) {
                $query->where('radica_enviado_id', $request->radica_enviado_id);
            }

            if ($request->filled('user_id')) {
                $query->whereHas('userCargo', fn ($q) => $q->where('user_id', $request->user_id));
            }

            $query->orderBy('created_at', 'desc');

            if ($request->filled('per_page')) {
                $responsables = $query->paginate($request->per_page);
                $responsables->getCollection()->transform(fn ($r) => array_merge($r->toArray(), ['info' => $r->getInfoResponsable()]));
            } else {
                $responsables = $query->get();
                $responsables = $responsables->map(fn ($r) => array_merge($r->toArray(), ['info' => $r->getInfoResponsable()]));
            }

            return $this->successResponse($responsables, 'Listado de responsables obtenido exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el listado de responsables', $e->getMessage(), 500);
        }
    }

    public function store(VentanillaRadicaEnviadosResponsaRequest $request)
    {
        try {
            DB::beginTransaction();

            $validatedData = $request->validated();

            if (empty($validatedData['responsables'])) {
                return $this->errorResponse('Se debe enviar un array de responsables no vacío', null, 400);
            }

            $responsablesCreados = [];

            foreach ($validatedData['responsables'] as $item) {
                $radicaEnviadoId = $item['radica_enviado_id'] ?? null;
                if (!$radicaEnviadoId) {
                    return $this->errorResponse('Cada responsable debe incluir radica_enviado_id', null, 400);
                }

                $responsable = VentanillaRadicaEnviadosRespona::create([
                    'radica_enviado_id' => $radicaEnviadoId,
                    'users_cargos_id' => (int) $item['users_cargos_id'],
                    'custodio' => filter_var($item['custodio'], FILTER_VALIDATE_BOOLEAN),
                ]);
                $responsablesCreados[] = $responsable->load(['userCargo.user', 'userCargo.cargo', 'radicado']);
            }

            DB::commit();

            return $this->successResponse($responsablesCreados, 'Responsables asignados exitosamente', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al asignar responsables', $e->getMessage(), 500);
        }
    }

    public function show($id)
    {
        try {
            $responsable = VentanillaRadicaEnviadosRespona::with(['userCargo.user', 'userCargo.cargo', 'radicado'])->find($id);

            if (!$responsable) {
                return $this->errorResponse('Responsable no encontrado', null, 404);
            }

            $data = $responsable->toArray();
            $data['info'] = $responsable->getInfoResponsable();

            return $this->successResponse($data, 'Responsable encontrado exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el responsable', $e->getMessage(), 500);
        }
    }

    public function update($id, UpdateVentanillaRadicaEnviadosResponsaRequest $request)
    {
        try {
            DB::beginTransaction();

            $responsable = VentanillaRadicaEnviadosRespona::find($id);

            if (!$responsable) {
                return $this->errorResponse('Responsable no encontrado', null, 404);
            }

            $updateData = $request->only(['radica_enviado_id', 'users_cargos_id', 'custodio']);
            $updateData = array_filter($updateData, fn ($v) => $v !== null && $v !== '');

            if (isset($updateData['custodio'])) {
                $updateData['custodio'] = filter_var($updateData['custodio'], FILTER_VALIDATE_BOOLEAN);
            }

            if (!empty($updateData)) {
                $responsable->update($updateData);
            }

            DB::commit();

            return $this->successResponse(
                $responsable->fresh(['userCargo.user', 'userCargo.cargo', 'radicado']),
                'Responsable actualizado exitosamente'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al actualizar el responsable', $e->getMessage(), 500);
        }
    }

    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            $responsable = VentanillaRadicaEnviadosRespona::find($id);

            if (!$responsable) {
                return $this->errorResponse('Responsable no encontrado', null, 404);
            }

            $responsable->delete();

            DB::commit();

            return $this->successResponse(null, 'Responsable eliminado exitosamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al eliminar el responsable', $e->getMessage(), 500);
        }
    }

    public function getByRadicado($radica_enviado_id)
    {
        try {
            $responsables = VentanillaRadicaEnviadosRespona::with(['userCargo.user', 'userCargo.cargo'])
                ->where('radica_enviado_id', $radica_enviado_id)
                ->orderBy('created_at', 'desc')
                ->get();

            $data = $responsables->map(fn ($r) => $r->getInfoResponsable())->filter()->values();

            return $this->successResponse($data, 'Responsables del radicado obtenidos exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener los responsables', $e->getMessage(), 500);
        }
    }

    public function assignToRadicado($radica_enviado_id, VentanillaRadicaEnviadosResponsaRequest $request)
    {
        try {
            DB::beginTransaction();

            $validatedData = $request->validated();
            $responsables = $validatedData['responsables'] ?? [];

            if (empty($responsables)) {
                return $this->errorResponse('Se debe enviar un array de responsables no vacío', null, 400);
            }

            $responsablesCreados = [];

            foreach ($responsables as $item) {
                $responsable = VentanillaRadicaEnviadosRespona::create([
                    'radica_enviado_id' => (int) $radica_enviado_id,
                    'users_cargos_id' => (int) $item['users_cargos_id'],
                    'custodio' => filter_var($item['custodio'], FILTER_VALIDATE_BOOLEAN),
                ]);
                $responsablesCreados[] = $responsable->load(['userCargo.user', 'userCargo.cargo', 'radicado']);
            }

            DB::commit();

            return $this->successResponse($responsablesCreados, 'Responsables asignados exitosamente', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return $this->errorResponse('Error de validación', $e->errors(), 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al asignar responsables', $e->getMessage(), 500);
        }
    }
}
