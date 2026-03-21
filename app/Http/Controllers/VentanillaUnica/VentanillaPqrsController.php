<?php

namespace App\Http\Controllers\VentanillaUnica;

use App\Http\Controllers\Controller;
use App\Models\VentanillaUnica\VentanillaPqrs;
use App\Models\VentanillaUnica\VentanillaRadicaReci;
use App\Helpers\CalendarioHelper;
use App\Http\Traits\ApiResponseTrait;
use App\Traits\AuditViewTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class VentanillaPqrsController extends Controller
{
    use ApiResponseTrait, AuditViewTrait;

    // Términos de ley (Días hábiles)
    private const TERMINOS = [
        'Peticion' => 15,
        'Informacion' => 10,
        'Consulta' => 30,
        'Queja' => 15,
        'Reclamo' => 15,
        'Sugerencia' => 15,
        'Denuncia' => 15
    ];

    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Lista las PQRS activas con su estado de vencimiento (Semáforo).
     */
    public function index(Request $request)
    {
        try {
            $query = VentanillaPqrs::with([
                'radicado.tercero',
                'radicado.clasificacionDocumental',
                'tipoPqrs',
                'dependenciaResponsable'
            ]);

            // Aplicar filtros
            if ($request->has('dependencia_id')) {
                $query->where('dependencia_responsable_id', $request->dependencia_id);
            }

            if ($request->has('estado')) {
                $query->where('estado_tramite', $request->estado);
            }

            $pqrs = $query->latest('fecha_vencimiento')->paginate($request->get('per_page', 15));

            // Inyectar días restantes dinámicamente para el frontend
            $pqrs->getCollection()->transform(function($item) {
                $item->dias_habiles_restantes = CalendarioHelper::diasHabilesRestantes($item->fecha_vencimiento);
                return $item;
            });

            return $this->successResponse($pqrs, 'Listado de PQRS obtenido');

        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener PQRS', $e->getMessage(), 500);
        }
    }

    /**
     * Convierte un radicado en una PQRS controlada.
     */
    public function store(Request $request)
    {
        $request->validate([
            'radicado_id' => 'required|exists:ventanilla_radica_reci,id|unique:ventanilla_pqrs,radicado_id',
            'tipo_pqrs_id' => 'required|exists:config_lista_detalles,id',
            'tipo_label' => 'required|string', // Ej: 'Peticion', 'Informacion'
            'dependencia_responsable_id' => 'required|exists:calidad_organigrama,id',
            'es_anonimo' => 'boolean',
            'prioridad' => 'string|in:Normal,Urgente,Tutela',
            'canal_preferido' => 'string|in:Correo Electronico,Correo Fisico'
        ]);

        try {
            DB::beginTransaction();

            $radicado = VentanillaRadicaReci::findOrFail($request->radicado_id);
            
            // Determinar término en días hábiles
            $diasTermino = self::TERMINOS[$request->tipo_label] ?? 15;
            if ($request->prioridad === 'Tutela') $diasTermino = 2; // Términos perentorios

            // Calcular fecha de vencimiento
            $fechaVencimiento = CalendarioHelper::calcularVencimiento($radicado->fec_radicado ?? $radicado->created_at, $diasTermino);

            $pqrs = VentanillaPqrs::create([
                'radicado_id' => $request->radicado_id,
                'tipo_pqrs_id' => $request->tipo_pqrs_id,
                'dependencia_responsable_id' => $request->dependencia_responsable_id,
                'estado_tramite' => 'Pendiente',
                'fecha_vencimiento' => $fechaVencimiento,
                'fecha_vencimiento_original' => $fechaVencimiento,
                'es_anonimo' => $request->es_anonimo ?? false,
                'canal_preferido' => $request->canal_preferido ?? 'Correo Electronico',
                'prioridad' => $request->prioridad ?? 'Normal',
            ]);

            DB::commit();

            return $this->successResponse($pqrs, 'Radicado convertido a PQRS exitosamente. Vence el: ' . $fechaVencimiento->format('Y-m-d'), 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al crear la PQRS', $e->getMessage(), 500);
        }
    }

    /**
     * Aplica una prórroga a la PQRS (Duplica el término original).
     */
    public function aplicarProrroga($id)
    {
        try {
            DB::beginTransaction();

            $pqrs = VentanillaPqrs::findOrFail($id);

            if ($pqrs->tiene_prorroga) {
                return $this->errorResponse('Esta PQRS ya tiene una prórroga aplicada', null, 422);
            }

            // Obtener el nombre del tipo para saber cuántos días sumar de nuevo
            $tipoLabel = $pqrs->tipoPqrs->nombre;
            $diasExtra = self::TERMINOS[$tipoLabel] ?? 15;

            // La prórroga suma el término original a la fecha de vencimiento actual
            $nuevaFecha = CalendarioHelper::calcularVencimiento($pqrs->fecha_vencimiento, $diasExtra);

            $pqrs->update([
                'tiene_prorroga' => true,
                'fecha_vencimiento' => $nuevaFecha,
                'observaciones' => $pqrs->observaciones . "\n[PRÓRROGA] Aplicada el " . now()->format('Y-m-d')
            ]);

            DB::commit();

            return $this->successResponse($pqrs, 'Prórroga aplicada exitosamente. Nuevo vencimiento: ' . $nuevaFecha->format('Y-m-d'));

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al aplicar prórroga', $e->getMessage(), 500);
        }
    }
}
