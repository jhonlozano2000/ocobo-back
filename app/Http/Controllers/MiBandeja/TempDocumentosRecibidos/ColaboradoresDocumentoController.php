<?php

namespace App\Http\Controllers\MiBandeja\TempDocumentosRecibidos;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\MiBandeja\TempDocumentosRecibidos\Documento;
use App\Models\MiBandeja\TempDocumentosRecibidos\DocumentoUsuario;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Grupo colaborativo de un documento de Mis Documentos («Compartir», como en Google Docs): el creador agrega
 * funcionarios con un rol, les cambia el rol o los quita. Proyector y responsable editan; el firmante además
 * firma con OTP (si no hay firmantes, firma el creador). Los colaboradores ven el documento en su lista.
 */
class ColaboradoresDocumentoController extends Controller
{
    use ApiResponseTrait;

    public const ROLES = ['proyector', 'responsable', 'firmante'];

    /**
     * Quién tiene acceso: el creador y los colaboradores con su rol.
     */
    public function index(Request $request, Documento $documento): JsonResponse
    {
        return $this->successResponse($this->grupo($documento, $request->user()));
    }

    /**
     * Agrega funcionarios con un rol; si ya estaban, les cambia el rol.
     *
     * @bodyParam usuarios array required Ejemplo: [{"user_id": 5, "rol": "firmante"}]
     */
    public function store(Request $request, Documento $documento): JsonResponse
    {
        $datos = $request->validate([
            'usuarios' => ['required', 'array', 'min:1', 'max:50'],
            'usuarios.*.user_id' => [
                'required', 'integer', 'distinct',
                // El creador ya es dueño del documento: no se agrega como colaborador
                Rule::notIn([$documento->user_id]),
                Rule::exists('users', 'id')->where('estado', 1),
            ],
            'usuarios.*.rol' => ['required', 'string', Rule::in(self::ROLES)],
        ], [
            'usuarios.*.user_id.not_in' => 'El creador ya tiene acceso al documento.',
            'usuarios.*.user_id.exists' => 'El funcionario no existe o está inactivo.',
        ]);

        DB::transaction(function () use ($documento, $datos) {
            foreach ($datos['usuarios'] as $usuario) {
                DocumentoUsuario::updateOrCreate(
                    ['documento_id' => $documento->id, 'user_id' => $usuario['user_id']],
                    ['rol' => $usuario['rol']]
                );
            }
        });

        return $this->successResponse($this->grupo($documento, $request->user()), 'Documento compartido');
    }

    /**
     * Quita a un colaborador: deja de ver y editar el documento.
     */
    public function destroy(Request $request, Documento $documento, int $usuario): JsonResponse
    {
        $eliminados = $documento->usuarios()->where('user_id', $usuario)->delete();

        if ($eliminados === 0) {
            return $this->notFoundResponse('El funcionario no es colaborador del documento');
        }

        return $this->successResponse($this->grupo($documento, $request->user()), 'Colaborador quitado');
    }

    private function grupo(Documento $documento, User $user): array
    {
        $creador = User::find($documento->user_id, ['id', 'nombres', 'apellidos']);
        $colaboradores = $documento->usuarios()
            ->with('usuario:id,nombres,apellidos')
            ->orderBy('created_at')
            ->get();

        return [
            'puede_gestionar' => $user->can('gestionarUsuarios', $documento),
            'creador' => $creador ? ['user_id' => $creador->id, 'nombre' => $this->nombre($creador)] : null,
            'colaboradores' => $colaboradores->map(fn (DocumentoUsuario $c) => [
                'user_id' => $c->user_id,
                'nombre' => $c->usuario ? $this->nombre($c->usuario) : null,
                'rol' => $c->rol,
            ])->values(),
        ];
    }

    private function nombre(User $user): string
    {
        return trim("{$user->nombres} {$user->apellidos}") ?: 'Usuario';
    }
}
