<?php

namespace App\Http\Controllers\Api\Workflows\Concerns;

use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowNodo;
use App\Models\Workflows\WorkFlowTarea;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * M19-05: las rutas anidadas bajo workflows/{workflow} solo exigían el permiso plano (can:).
 * Este middleware de controlador comprueba que {nodo} y {tarea} cuelgan del workflow de la ruta
 * y, si se indica una ability, la autoriza contra WorkflowPolicy (creador o Administrador).
 */
trait AutorizaWorkflowDeRuta
{
    protected function workflowDeRuta(?string $ability = null): Closure
    {
        return function (Request $request, Closure $next) use ($ability) {
            $workflow = $request->route('workflow');
            $workflow = $workflow instanceof Workflow ? $workflow : Workflow::findOrFail((int) $workflow);

            $nodo = $request->route('nodo');
            if ($nodo !== null) {
                $nodoId = $nodo instanceof WorkflowNodo ? $nodo->id : (int) $nodo;
                abort_unless(WorkflowNodo::where('workflow_id', $workflow->id)->whereKey($nodoId)->exists(), 404, 'Nodo no encontrado en este workflow');

                $tarea = $request->route('tarea');
                if ($tarea !== null) {
                    $tareaId = $tarea instanceof WorkFlowTarea ? $tarea->id : (int) $tarea;
                    abort_unless(WorkFlowTarea::where('nodo_id', $nodoId)->whereKey($tareaId)->exists(), 404, 'Tarea no encontrada en este nodo');
                }
            }

            if ($ability !== null) {
                Gate::authorize($ability, $workflow);
            }

            return $next($request);
        };
    }
}
