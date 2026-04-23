<?php

namespace App\Services\ControlAcceso;

use App\Helpers\ArchivoHelper;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class UserService
{
    /**
     * Obtiene usuarios con filtros.
     */
    public function getAll(array $filters = [])
    {
        $query = User::with([
            'roles',
            'cargoActivo.cargo',
            'divisionPolitica.padre.padre'
        ]);

        if (!empty($filters['solo_activos'])) {
            $query->where('estado', 1);
        }

        if (!empty($filters['search'])) {
            $query->where(fn($q) => $q
                ->where('nombres', 'like', "%{$filters['search']}%")
                ->orWhere('apellidos', 'like', "%{$filters['search']}%")
                ->orWhere('email', 'like', "%{$filters['search']}%")
            );
        }

        return $query->orderBy('nombres')->orderBy('apellidos')->get()
            ->map(fn($u) => $this->mapUserData($u));
    }

    /**
     * Obtiene usuario por ID.
     */
    public function getById(string $id): ?array
    {
        $user = User::with(['roles', 'cargoActivo.cargo', 'divisionPolitica.padre.padre'])->find($id);
        
        return $user ? $this->mapUserData($user) : null;
    }

    /**
     * Crea un nuevo usuario.
     */
    public function create(array $data): User
    {
        if (!empty($data['avatar'])) {
            $data['avatar'] = ArchivoHelper::guardarArchivo(
                new \Illuminate\Http\Request(['avatar' => $data['avatar']]),
                'avatar', 'avatars', null
            );
        }

        if (!empty($data['firma'])) {
            $data['firma'] = ArchivoHelper::guardarArchivo(
                new \Illuminate\Http\Request(['firma' => $data['firma']]),
                'firma', 'firmas', null
            );
        }

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $user = User::create($data);

        if (!empty($data['roles'])) {
            $user->syncRoles($data['roles']);
        }

        if (!empty($data['cargo_id'])) {
            $user->asignarCargo(
                $data['cargo_id'],
                $data['fecha_inicio_cargo'] ?? now()->format('Y-m-d'),
                $data['observaciones_cargo'] ?? null
            );
        }

        return $user->fresh(['roles']);
    }

    /**
     * Actualiza un usuario.
     */
    public function update(string $id, array $data): ?User
    {
        $user = User::findOrFail($id);

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->fill($data)->save();

        if (!empty($data['roles'])) {
            $user->syncRoles($data['roles']);
        }

        if (isset($data['cargo_id'])) {
            if ($data['cargo_id']) {
                $user->asignarCargo(
                    $data['cargo_id'],
                    $data['fecha_inicio_cargo'] ?? now()->format('Y-m-d'),
                    $data['observaciones_cargo'] ?? null
                );
            } else {
                $user->cargos()->update(['estado' => false]);
            }
        }

        return $user->fresh(['roles']);
    }

    /**
     * Elimina un usuario.
     */
    public function delete(string $id): bool
    {
        $user = User::find($id);
        
        if (!$user) {
            return false;
        }

        ArchivoHelper::eliminarArchivo($user->avatar, 'avatars');
        ArchivoHelper::eliminarArchivo($user->firma, 'firmas');
        
        return $user->delete();
    }

    /**
     * Obtiene estadísticas con caché y optimización de consultas.
     */
    public function getStats(): array
    {
        return Cache::remember('users_statistics', now()->addMinutes(10), function () {
            $stats = User::selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN estado = 1 THEN 1 ELSE 0 END) as activos,
                SUM(CASE WHEN estado = 0 THEN 1 ELSE 0 END) as inactivos
            ')->first();

            return [
                'total_users' => (int) ($stats->total ?? 0),
                'total_users_activos' => (int) ($stats->activos ?? 0),
                'total_users_inactivos' => (int) ($stats->inactivos ?? 0),
            ];
        });
    }

    /**
     * Mapea datos del usuario para respuesta.
     */
    private function mapUserData($user): array
    {
        $data = $user->append(['avatar_url', 'firma_url'])->toArray();
        
        unset($data['cargos'], $data['cargo_activo'], $data['division_politica']);

        $data = array_merge($data, [
            'cargo' => null, 'oficina' => null, 'dependencia' => null,
            'pais' => null, 'departamento' => null, 'municipio' => null
        ]);

        // División política
        if ($user->divi_poli_id && $user->divisionPolitica) {
            $this->mapDivisionPolitica($user, $data);
        }

        // Cargo activo
        if ($user->cargoActivo?->cargo) {
            $this->mapCargo($user, $data);
        }

        return $data;
    }

    private function mapDivisionPolitica($user, array &$data): void
    {
        $diviPoli = $user->divisionPolitica;
        
        if ($diviPoli->tipo === 'Municipio') {
            $dept = $diviPoli->padre;
            $pais = $dept?->padre;
            $data['municipio'] = ['id' => $diviPoli->id, 'codigo' => $diviPoli->codigo, 'nombre' => $diviPoli->nombre, 'tipo' => $diviPoli->tipo];
            $data['departamento'] = $dept ? ['id' => $dept->id, 'codigo' => $dept->codigo, 'nombre' => $dept->nombre, 'tipo' => $dept->tipo] : null;
            $data['pais'] = $pais ? ['id' => $pais->id, 'codigo' => $pais->codigo, 'nombre' => $pais->nombre, 'tipo' => $pais->tipo] : null;
        } elseif ($diviPoli->tipo === 'Departamento') {
            $pais = $diviPoli->padre;
            $data['departamento'] = ['id' => $diviPoli->id, 'codigo' => $diviPoli->codigo, 'nombre' => $diviPoli->nombre, 'tipo' => $diviPoli->tipo];
            $data['pais'] = $pais ? ['id' => $pais->id, 'codigo' => $pais->codigo, 'nombre' => $pais->nombre, 'tipo' => $pais->tipo] : null;
        } elseif ($diviPoli->tipo === 'Pais') {
            $data['pais'] = ['id' => $diviPoli->id, 'codigo' => $diviPoli->codigo, 'nombre' => $diviPoli->nombre, 'tipo' => $diviPoli->tipo];
        }
    }

    private function mapCargo($user, array &$data): void
    {
        $cargo = $user->cargoActivo->cargo;
        $data['cargo'] = [
            'id' => $user->cargoActivo->id,
            'cargo_id' => $cargo->id,
            'nom_organico' => $cargo->nom_organico,
            'cod_organico' => $cargo->cod_organico,
            'tipo' => $cargo->tipo,
            'fecha_inicio' => $user->cargoActivo->fecha_inicio?->format('Y-m-d'),
            'observaciones' => $user->cargoActivo->observaciones
        ];

        $jerarquia = $cargo->getJerarquiaCompleta();
        $cargoIndex = array_search($cargo->id, array_column($jerarquia, 'id'));

        if ($cargoIndex > 0) {
            $parent = $jerarquia[$cargoIndex - 1];
            if ($parent['tipo'] === 'Oficina') {
                $data['oficina'] = ['id' => $parent['id'], 'nom_organico' => $parent['nom_organico'], 'cod_organico' => $parent['cod_organico'], 'tipo' => $parent['tipo']];
            } elseif ($parent['tipo'] === 'Dependencia') {
                $data['dependencia'] = ['id' => $parent['id'], 'nom_organico' => $parent['nom_organico'], 'cod_organico' => $parent['cod_organico'], 'tipo' => $parent['tipo']];
            }
        }
    }
}