<?php

namespace App\Models\MiBandeja\TempDocumentosRecibidos;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Usuario asignado a un documento colaborativo.
 *
 * Representa la relación entre un usuario y un documento,
 * con un rol específico que define sus permisos.
 *
 * @property int $id
 * @property int $documento_id ID del documento
 * @property int $user_id ID del usuario
 * @property string $rol Rol: firmante|responsable|proyector
 * @property \Carbon\Carbon|null $ultimo_acceso Última actividad
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read Documento $documento
 * @property-read User $usuario
 */
class DocumentoUsuario extends Model
{
    /** @var string Nombre de la tabla */
    protected $table = 'mi_bandeja_temp_reci_usuarios';

    /** @var array<string> Campos asignables en masa */
    protected $fillable = [
        'documento_id',
        'user_id',
        'rol',
        'ultimo_acceso',
    ];

    /** @var array<string, string> Conversiones de tipos */
    protected $casts = [
        'ultimo_acceso' => 'datetime',
    ];

    /**
     * Relación con el documento.
     *
     * @return BelongsTo<Documento, DocumentoUsuario>
     */
    public function documento(): BelongsTo
    {
        return $this->belongsTo(Documento::class, 'documento_id');
    }

    /**
     * Relación con el usuario.
     *
     * @return BelongsTo<User, DocumentoUsuario>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Obtiene el nombre del usuario.
     *
     * @return string
     */
    public function nombreUsuario(): string
    {
        return $this->usuario->name ?? 'Usuario';
    }

    /**
     * Obtiene el color asociado al rol.
     *
     * @return string
     */
    public function color(): string
    {
        $colors = [
            'firmante' => '#E53935',
            'responsable' => '#43A047',
            'proyector' => '#1E88E5',
        ];

        return $colors[$this->rol] ?? '#757575';
    }
}
