<?php

namespace App\Services\OfiArchivo;

use App\Models\MiBandeja\TempDocumentosRecibidos\Contenido;
use App\Models\MiBandeja\TempDocumentosRecibidos\Documento;
use App\Models\OfiArchivo\OfiArchivoPlantillaGenerada;
use App\Models\User;
use App\Services\MiBandeja\TempDocumentosRecibidos\LogoEntidadService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Plantillas generadas con el editor: se editan con un documento de trabajo oculto (uno por usuario y plantilla)
 * y de ellas se crean documentos con las variables llenas.
 */
class PlantillaGeneradaService
{
    /** Configuración de página del documento que guarda la plantilla. */
    public const CAMPOS_PAGINA = [
        'tamano_papel',
        'orientacion',
        'margenes',
        'configuracion_columnas',
        'configuracion_header',
        'configuracion_footer',
        'configuracion_formato',
    ];

    /** Lo que vuelve a tomar el documento de trabajo si la plantilla no lo define (valores de la tabla). */
    private const PAGINA_POR_DEFECTO = [
        'tamano_papel' => 'a4',
        'orientacion' => 'vertical',
        'margenes' => null,
        'configuracion_columnas' => null,
        'configuracion_header' => null,
        'configuracion_footer' => null,
        'configuracion_formato' => null,
    ];

    public const PERMISO = 'Gestion de Archivo -> Plantillas -> ';

    /** Marcas de revisión que no pasan a la plantilla (comentarios y cambios sugeridos). */
    private const MARCAS_REVISION = ['comentario', 'insercion'];

    public function __construct(
        private VariablesPlantillaService $variables,
        private LogoEntidadService $logos,
    ) {}

    /**
     * Las institucionales las gestiona quien tiene el permiso del Generador; las personales, solo su autor.
     *
     * @param  string  $accion  Crear, Editar o Eliminar
     *
     * @throws AuthorizationException
     */
    public function autorizar(User $user, OfiArchivoPlantillaGenerada $plantilla, string $accion = 'Editar'): void
    {
        $puede = $plantilla->institucional
            ? $user->can(self::PERMISO.$accion)
            : $plantilla->user_id === $user->id;

        if (! $puede) {
            throw new AuthorizationException('No tiene permiso para gestionar esta plantilla.');
        }
    }

    /** Plantilla vacía: una hoja en blanco con la configuración de página por defecto. */
    public function crear(array $datos, User $user, bool $institucional): OfiArchivoPlantillaGenerada
    {
        return OfiArchivoPlantillaGenerada::create([
            'user_id' => $user->id,
            'tipo_id' => $datos['tipo_id'] ?? null,
            'nombre' => $datos['nombre'],
            'descripcion' => $datos['descripcion'] ?? null,
            'institucional' => $institucional,
            // Las institucionales nacen sin publicar: se activan cuando estén listas
            'activo' => (bool) ($datos['activo'] ?? ! $institucional),
            'contenido' => ['type' => 'doc', 'content' => [['type' => 'paragraph']]],
            'configuracion' => null,
        ]);
    }

    /** «Guardar como plantilla»: plantilla personal con el contenido y la página de un documento. */
    public function crearDesdeDocumento(Documento $documento, array $datos, User $user): OfiArchivoPlantillaGenerada
    {
        return OfiArchivoPlantillaGenerada::create([
            'user_id' => $user->id,
            'tipo_id' => $datos['tipo_id'] ?? null,
            'nombre' => $datos['nombre'],
            'descripcion' => $datos['descripcion'] ?? null,
            'institucional' => false,
            'contenido' => $this->limpiar($documento->contenido?->contenido_yjs ?: ['type' => 'doc', 'content' => []]),
            'configuracion' => $this->configuracionDe($documento),
        ]);
    }

    public function duplicar(OfiArchivoPlantillaGenerada $plantilla, User $user, bool $institucional): OfiArchivoPlantillaGenerada
    {
        return OfiArchivoPlantillaGenerada::create([
            'user_id' => $user->id,
            'tipo_id' => $plantilla->tipo_id,
            'nombre' => mb_substr('Copia de '.$plantilla->nombre, 0, 120),
            'descripcion' => $plantilla->descripcion,
            'institucional' => $institucional,
            'activo' => ! $institucional,
            'contenido' => $plantilla->contenido,
            'configuracion' => $plantilla->configuracion,
        ]);
    }

    /** Borra la plantilla y sus documentos de trabajo. */
    public function eliminar(OfiArchivoPlantillaGenerada $plantilla): void
    {
        DB::transaction(function () use ($plantilla) {
            $plantilla->documentosTrabajo()->get()->each->delete();
            $plantilla->delete();
        });
    }

    /**
     * Documento de trabajo del usuario para editar la plantilla en el editor. Si ya tenía uno se reutiliza (con
     * lo que no haya guardado), salvo que la plantilla se haya guardado después: entonces se pone al día.
     */
    public function abrirEdicion(OfiArchivoPlantillaGenerada $plantilla, User $user): Documento
    {
        return DB::transaction(function () use ($plantilla, $user) {
            $documento = Documento::where('plantilla_generada_id', $plantilla->id)
                ->where('user_id', $user->id)
                ->with('contenido')
                ->first();

            if (! $documento) {
                $documento = Documento::create([
                    'plantilla_generada_id' => $plantilla->id,
                    'user_id' => $user->id,
                    'titulo' => mb_substr($plantilla->nombre, 0, 255),
                    'estado' => 'borrador',
                ] + $this->paginaDe($plantilla));
                Contenido::create([
                    'documento_id' => $documento->id,
                    'contenido_yjs' => $plantilla->contenido,
                    'hash_contenido' => '',
                    'actualizado_por' => $user->id,
                ]);

                return $documento;
            }

            $actualizado = $documento->contenido?->updated_at;
            if (! $actualizado || $plantilla->updated_at->gt($actualizado)) {
                $documento->update($this->paginaDe($plantilla) + self::PAGINA_POR_DEFECTO
                    + ['titulo' => mb_substr($plantilla->nombre, 0, 255)]);
                Contenido::updateOrCreate(
                    ['documento_id' => $documento->id],
                    ['contenido_yjs' => $plantilla->contenido, 'hash_contenido' => '', 'actualizado_por' => $user->id]
                );
            }

            return $documento;
        });
    }

    /** Pasa a la plantilla lo que tiene su documento de trabajo. */
    public function guardarDesdeDocumento(Documento $documento, User $user): OfiArchivoPlantillaGenerada
    {
        $plantilla = $documento->plantillaGenerada;
        $plantilla->update([
            'contenido' => $this->limpiar($documento->contenido?->contenido_yjs ?: ['type' => 'doc', 'content' => []]),
            'configuracion' => $this->configuracionDe($documento),
            'user_actualiza_id' => $user->id,
        ]);

        return $plantilla;
    }

    /**
     * Página y contenido para un documento nuevo, con las variables llenas con los datos del usuario y de la
     * entidad.
     *
     * @return array{pagina: array, contenido: array}
     */
    public function paraDocumento(OfiArchivoPlantillaGenerada $plantilla, User $user): array
    {
        $valores = $this->variables->valores($user);
        $pagina = $this->paginaDe($plantilla);

        foreach (['configuracion_header', 'configuracion_footer'] as $franja) {
            if (isset($pagina[$franja])) {
                $pagina[$franja] = $this->ajustarFranja($this->variables->resolverFranja($pagina[$franja], $valores));
            }
        }

        return [
            'pagina' => $pagina,
            'contenido' => $this->variables->resolverContenido($plantilla->contenido, $valores),
        ];
    }

    /**
     * Quita del encabezado o pie lo que quedaría vacío: el logo si la entidad no tiene y el texto libre si sus
     * datos no están configurados. Sin elementos, la franja se apaga.
     */
    private function ajustarFranja(?array $franja): ?array
    {
        if (! is_array($franja) || ! isset($franja['elementos']) || ! is_array($franja['elementos'])) {
            return $franja;
        }

        $franja['elementos'] = array_values(array_filter($franja['elementos'], fn ($elemento) => match ($elemento) {
            'logo' => $this->logos->archivo() !== null,
            'texto_libre' => trim((string) ($franja['texto_libre'] ?? '')) !== '',
            default => true,
        }));
        if (! $franja['elementos']) {
            $franja['habilitado'] = false;
        }

        return $franja;
    }

    /** Configuración de página de la plantilla, sin los campos vacíos (el documento usa los suyos por defecto). */
    private function paginaDe(OfiArchivoPlantillaGenerada $plantilla): array
    {
        return array_filter(
            array_intersect_key($plantilla->configuracion ?? [], array_flip(self::CAMPOS_PAGINA)),
            fn ($valor) => $valor !== null
        );
    }

    private function configuracionDe(Documento $documento): array
    {
        return $documento->only(self::CAMPOS_PAGINA);
    }

    /**
     * Contenido sin las marcas de revisión: se quitan los comentarios y las inserciones sugeridas quedan como
     * texto normal; lo marcado para eliminar se descarta.
     */
    public function limpiar(array $nodo): array
    {
        if (isset($nodo['marks']) && is_array($nodo['marks'])) {
            $nodo['marks'] = array_values(array_filter(
                $nodo['marks'],
                fn ($marca) => ! in_array($marca['type'] ?? null, self::MARCAS_REVISION, true)
            ));
            if (! $nodo['marks']) {
                unset($nodo['marks']);
            }
        }

        if (isset($nodo['content']) && is_array($nodo['content'])) {
            $nodo['content'] = array_values(array_map(
                fn ($hijo) => $this->limpiar($hijo),
                array_filter($nodo['content'], fn ($hijo) => is_array($hijo) && ! $this->eliminado($hijo))
            ));
        }

        return $nodo;
    }

    private function eliminado(array $nodo): bool
    {
        foreach ($nodo['marks'] ?? [] as $marca) {
            if (($marca['type'] ?? null) === 'eliminacion') {
                return true;
            }
        }

        return false;
    }
}
