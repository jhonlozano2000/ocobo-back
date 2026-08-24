<?php

namespace App\Http\Controllers\VentanillaUnica\Internos;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\VentanillaUnica\Internos\VentanillaRadicaInterno;
use App\Models\VentanillaUnica\Internos\VentanillaRadicaInternoResponsable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VentanillaRadicaInternoResponsableController extends Controller
{
    use ApiResponseTrait;

    public function index()
    {
        try {
            $responsables = VentanillaRadicaInternoResponsable::all();

            return $this->successResponse($responsables, 'Listado de responsables obtenido exitosamente');
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            return $this->errorResponse('Error al obtener el listado de responsables', $e->getMessage(), 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'radica_interno_id' => 'required|integer|exists:ventanilla_radica_internos,id',
                'users_cargos_id' => 'required|integer|exists:users_cargos,id',
                'custodio' => 'sometimes|boolean',
                'fechor_visto' => 'nullable|date',
            ]);

            DB::beginTransaction();

            $responsable = VentanillaRadicaInternoResponsable::create($validated);

            DB::commit();

            return $this->successResponse($responsable, 'Responsable creado exitosamente', 201);
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            DB::rollBack();

            return $this->errorResponse('Error al crear el responsable', $e->getMessage(), 500);
        }
    }

    public function show($id)
    {
        try {
            $responsable = VentanillaRadicaInternoResponsable::find($id);

            if (! $responsable) {
                return $this->errorResponse('Responsable no encontrado', null, 404);
            }

            return $this->successResponse($responsable, 'Responsable encontrado exitosamente');
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            return $this->errorResponse('Error al obtener el responsable', $e->getMessage(), 500);
        }
    }

    public function update($id, Request $request)
    {
        try {
            $validated = $request->validate([
                'radica_interno_id' => 'sometimes|integer|exists:ventanilla_radica_internos,id',
                'users_cargos_id' => 'sometimes|integer|exists:users_cargos,id',
                'custodio' => 'sometimes|boolean',
                'fechor_visto' => 'nullable|date',
            ]);

            DB::beginTransaction();

            $responsable = VentanillaRadicaInternoResponsable::find($id);

            if (! $responsable) {
                return $this->errorResponse('Responsable no encontrado', null, 404);
            }

            $responsable->update($validated);

            DB::commit();

            return $this->successResponse($responsable, 'Responsable actualizado exitosamente');
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            DB::rollBack();

            return $this->errorResponse('Error al actualizar el responsable', $e->getMessage(), 500);
        }
    }

    public function getByRadicado($radica_interno_id)
    {
        try {
            $responsables = VentanillaRadicaInternoResponsable::with(['userCargo.user', 'userCargo.cargo'])
                ->where('radica_interno_id', $radica_interno_id)
                ->orderBy('created_at', 'desc')
                ->get();

            $data = $responsables->map(function ($r) {
                $user = $r->userCargo?->user;
                $cargo = $r->userCargo?->cargo;

                return [
                    'id' => $r->id,
                    'radica_interno_id' => $r->radica_interno_id,
                    'users_cargos_id' => $r->users_cargos_id,
                    'custodio' => $r->custodio,
                    'usuario' => $user ? [
                        'nombres' => $user->nombres,
                        'apellidos' => $user->apellidos,
                        'nombre_completo' => trim($user->nombres.' '.$user->apellidos),
                    ] : null,
                    'cargo' => $cargo ? [
                        'id' => $cargo->id,
                        'nombre' => $cargo->nom_organico,
                        'nom_organico' => $cargo->nom_organico,
                        'codigo' => $cargo->cod_organico,
                    ] : null,
                    'fechor_visto' => $r->fechor_visto,
                    'created_at' => $r->created_at,
                ];
            })->values();

            return $this->successResponse($data, 'Listado de responsables obtenido exitosamente');
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            return $this->errorResponse('Error al obtener el listado de responsables', $e->getMessage(), 500);
        }
    }

    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            $responsable = VentanillaRadicaInternoResponsable::find($id);

            if (! $responsable) {
                return $this->errorResponse('Responsable no encontrado', null, 404);
            }

            $responsable->delete();

            DB::commit();

            return $this->successResponse(null, 'Responsable eliminado exitosamente');
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            DB::rollBack();

            return $this->errorResponse('Error al eliminar el responsable', $e->getMessage(), 500);
        }
    }

    public function assignToRadicado($radica_interno_id, Request $request)
    {
        try {
            DB::beginTransaction();

            $radicado = VentanillaRadicaInterno::find($radica_interno_id);

            if (! $radicado) {
                return $this->errorResponse('Radicado interno no encontrado', null, 404);
            }

            $request->validate([
                'responsables' => 'required|array|min:1',
                'responsables.*.users_cargos_id' => 'required|integer',
                'responsables.*.custodio' => 'nullable|boolean',
            ]);

            $responsablesCreados = [];

            foreach ($request->responsables as $item) {
                $responsable = VentanillaRadicaInternoResponsable::create([
                    'radica_interno_id' => (int) $radica_interno_id,
                    'users_cargos_id' => (int) $item['users_cargos_id'],
                    'custodio' => isset($item['custodio']) ? filter_var($item['custodio'], FILTER_VALIDATE_BOOLEAN) : false,
                ]);
                $responsablesCreados[] = $responsable->load(['userCargo.user', 'userCargo.cargo']);
            }

            DB::commit();

            return $this->successResponse($responsablesCreados, 'Responsables asignados exitosamente', 201);
        } catch (ValidationException $e) {
            DB::rollBack();

            return $this->errorResponse('Error de validación', $e->errors(), 422);
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            DB::rollBack();

            return $this->errorResponse('Error al asignar responsables', $e->getMessage(), 500);
        }
    }

    public function marcarVisto($id): JsonResponse
    {
        try {
            $responsable = VentanillaRadicaInternoResponsable::with('userCargo')->findOrFail($id);

            if ((int) ($responsable->userCargo?->user_id) !== (int) auth()->id()) {
                return $this->errorResponse('Solo el responsable asignado puede registrar su acuse digital', null, 403);
            }

            if (! $responsable->marcarComoVisto()) {
                return $this->successResponse(
                    $responsable->fresh()->load(['userCargo.user', 'userCargo.cargo']),
                    'El radicado ya había sido marcado como visto anteriormente'
                );
            }

            return $this->successResponse(
                $responsable->fresh()->load(['userCargo.user', 'userCargo.cargo']),
                'Acuse digital registrado exitosamente'
            );
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Responsable no encontrado', null, 404);
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            return $this->errorResponse('Error al registrar el acuse digital', $e->getMessage(), 500);
        }
    }
}
