<?php

namespace App\Models\Ia;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Modelo de Auditoría ISO 27001 para Proyecciones y Borradores de IA (OCOBO SGDEA).
 *
 * Registra de forma inmutable cada inferencia, modelo utilizado, tiempo de procesamiento,
 * precedentes institucionales citados, hash SHA-256 del prompt y resultado emitido.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string|null $id_radicado
 * @property string|null $tipo_radicado
 * @property int|null $radicado_id
 * @property string $asunto_hash
 * @property string $prompt_hash
 * @property string $modelo_llm
 * @property string $estado
 * @property float|null $score_confianza
 * @property array|null $citas_utilizadas
 * @property array|null $advertencias
 * @property array|null $tokens_verificar
 * @property string|null $borrador_generado
 * @property float|null $tiempo_retrieval_ms
 * @property float|null $tiempo_reranking_ms
 * @property float|null $tiempo_llm_ms
 * @property float|null $tiempo_total_ms
 * @property bool $fallback
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class IaBorradorAuditoria extends Model
{
    use HasFactory;

    protected $table = 'ia_borradores_auditoria';

    protected $fillable = [
        'user_id',
        'id_radicado',
        'tipo_radicado',
        'radicado_id',
        'asunto_hash',
        'prompt_hash',
        'modelo_llm',
        'estado',
        'score_confianza',
        'citas_utilizadas',
        'advertencias',
        'tokens_verificar',
        'borrador_generado',
        'tiempo_retrieval_ms',
        'tiempo_reranking_ms',
        'tiempo_llm_ms',
        'tiempo_total_ms',
        'fallback',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'score_confianza' => 'float',
        'citas_utilizadas' => 'array',
        'advertencias' => 'array',
        'tokens_verificar' => 'array',
        'tiempo_retrieval_ms' => 'float',
        'tiempo_reranking_ms' => 'float',
        'tiempo_llm_ms' => 'float',
        'tiempo_total_ms' => 'float',
        'fallback' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Usuario responsable de la solicitud de proyección.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope para filtrar por usuario solicitante.
     */
    public function scopeParaUsuario(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope para filtrar por estado de inferencia.
     */
    public function scopeEstado(Builder $query, string $estado): Builder
    {
        return $query->where('estado', $estado);
    }

    /**
     * Scope para ordenar por los más recientes.
     */
    public function scopeRecientes(Builder $query): Builder
    {
        return $query->orderByDesc('id');
    }
}
