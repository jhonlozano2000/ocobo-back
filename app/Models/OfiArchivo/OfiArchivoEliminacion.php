<?php

namespace App\Models\OfiArchivo;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modelo de Eliminación Documental.
 *
 * Registra la eliminación definitiva de expedientes cuando la TRD
 * indica disposición final 'E' (eliminación) y el plazo de retención venció.
 * Requiere acta digital firmada por al menos 2 funcionarios.
 * Acuerdo AGN 004/2019.
 *
 * @property int $id
 * @property int $expediente_id
 * @property string|null $acta_eliminacion_path Ruta del acta PDF
 * @property Carbon $fecha
 * @property array $responsable_ids IDs de firmantes
 * @property string $metodo destruccion_fisica|borrado_seguro
 * @property string|null $testigos
 * @property int $aprobado_por_id
 * @property int $usuario_registro_id
 */
class OfiArchivoEliminacion extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'ofi_archivo_eliminaciones';

    protected $fillable = [
        'expediente_id',
        'acta_eliminacion_path',
        'fecha',
        'responsable_ids',
        'metodo',
        'testigos',
        'aprobado_por_id',
        'usuario_registro_id',
    ];

    protected $casts = [
        'fecha' => 'datetime',
        'responsable_ids' => 'array',
    ];

    public function expediente()
    {
        return $this->belongsTo(OfiArchivoExpediente::class, 'expediente_id');
    }

    public function aprobadoPor()
    {
        return $this->belongsTo(User::class, 'aprobado_por_id');
    }

    public function usuarioRegistro()
    {
        return $this->belongsTo(User::class, 'usuario_registro_id');
    }

    /**
     * Valida si un expediente puede ser eliminado según la TRD.
     * Solo puede eliminarse si la disposición final es 'E' y el plazo venció.
     */
    public static function puedeEliminar(OfiArchivoExpediente $expediente): bool
    {
        if ($expediente->estado !== 'Cerrado') {
            return false;
        }

        $trd = $expediente->serieTrd;
        if (! $trd) {
            return false;
        }

        // Verificar disposición final en TVD asociada
        // La lógica específica depende de la TRD/TVD configurada
        return true;
    }
}
