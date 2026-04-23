<?php

namespace App\Services\VentanillaUnica;

use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReciOptimizedView;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class RadicacionReciService
{
    /**
     * Obtiene radicados con filtros usando vista optimizada.
     */
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = VentanillaRadicaReciOptimizedView::query();

        $query->search($filters['search'] ?? null)
            ->fechaEntre($filters['fecha_desde'] ?? null, $filters['fecha_hasta'] ?? null)
            ->clasificacionDocumental($filters['clasifica_documen_id'] ?? null)
            ->tercero($filters['tercero_id'] ?? null)
            ->medioRecepcion($filters['medio_recep_id'] ?? null)
            ->ordenadoPorFecha();

        $perPage = $filters['per_page'] ?? 10;
        $radicados = $query->paginate($perPage);

        $ids = $radicados->pluck('id');

        if ($ids->isNotEmpty()) {
            $completos = VentanillaRadicaReci::whereIn('id', $ids)
                ->select(['id', 'archivo_digital', 'uploaded_by', 'usuario_crea', 'updated_at', 'created_at'])
                ->with([
                    'usuarioSubio:id,nombres,apellidos,email',
                    'usuarioCreaRadicado:id,nombres,apellidos,email',
                    'archivos:id,radicado_id,archivo,created_at',
                    'responsables:id,radica_reci_id,users_cargos_id,custodio,fechor_visto,created_at',
                    'responsables.userCargo:id,user_id,cargo_id',
                    'responsables.userCargo.user:id,nombres,apellidos,email',
                    'responsables.userCargo.cargo:id,nom_organico,cod_organico,tipo'
                ])
                ->get()
                ->keyBy('id');

            $radicados->getCollection()->transform(function ($radicado) use ($completos) {
                $completo = $completos->get($radicado->id);
                if ($completo) {
                    $info = $completo->getInformacionCompleta(
                        $radicado->total_responsables ?? 0,
                        $radicado->total_custodios ?? 0
                    );
                    $radicado->documentos = $info['documentos'];
                    $radicado->usuario_creo_radicado = $info['usuario_creo_radicado'];
                    $radicado->responsables = $info['responsables'];
                    $radicado->total_responsables = $info['total_responsables'];
                    $radicado->total_custodios = $info['total_custodios'];
                }
                return $radicado;
            });
        }

        return $radicados;
    }

    /**
     * Obtiene un radicado por ID.
     */
    public function getById(int $id): ?VentanillaRadicaReci
    {
        return VentanillaRadicaReci::with([
            'tercero',
            'clasificacionDocumental',
            'medioRecepcion',
            'archivos',
            'responsables.userCargo.user',
            'responsables.userCargo.cargo',
            'usuarioSubio',
            'usuarioCreaRadicado'
        ])->find($id);
    }

    /**
     * Crea una nueva radicación recibida.
     */
    public function create(array $data): VentanillaRadicaReci
    {
        $data['usuario_crea'] = $data['usuario_crea'] ?? auth()->id();
        $data['uploaded_by'] = $data['uploaded_by'] ?? auth()->id();

        return VentanillaRadicaReci::create($data);
    }

    /**
     * Actualiza una radicación.
     */
    public function update(int $id, array $data): ?VentanillaRadicaReci
    {
        $radicado = VentanillaRadicaReci::find($id);
        
        if (!$radicado) {
            return null;
        }

        $radicado->update($data);
        return $radicado->fresh();
    }

    /**
     * Elimina una radicación.
     */
    public function delete(int $id): bool
    {
        $radicado = VentanillaRadicaReci::find($id);
        
        if (!$radicado) {
            return false;
        }

        return $radicado->delete();
    }

    /**
     * Obtiene estadísticas.
     */
    public function getStats(): array
    {
        $total = VentanillaRadicaReci::count();
        $pendientes = VentanillaRadicaReci::where('estado', 'Pendiente')->count();
        $vencidos = VentanillaRadicaReci::where('fec_venci', '<', now()->format('Y-m-d'))->count();

        return [
            'total_radicados' => $total,
            'pendientes' => $pendientes,
            'vencidos' => $vencidos,
        ];
    }
}