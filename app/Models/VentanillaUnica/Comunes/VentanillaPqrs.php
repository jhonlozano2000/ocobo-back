<?php

namespace App\Models\VentanillaUnica\Comunes;

use App\Helpers\CalendarioHelper;
use App\Models\ClasificacionDocumental\ClasificacionDocumentalTRD;
use App\Models\Configuracion\ConfigDiviPoli;
use App\Models\Configuracion\ConfigListaDetalle;
use App\Models\Gestion\GestionTercero;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class VentanillaPqrs extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'ventanilla_pqrs';

    protected $fillable = [
        'ventanilla_radica_reci_id',
        'gestion_tercero_id',
        'clasificacion_documental_trd_id',
        'config_divi_poli_id_afectado',
        'tipo_pqrs_id',
        'prioridad',
        'estado_tramite',
        'fecha_vencimiento',
        'fecha_vencimiento_original',
        'fecha_respuesta',
        'tiene_prorroga',
        'fallo_judicial',
        'fechor_tramite',
        'observaciones',
        'num_docu_afectado',
        'nom_afectado',
        'dir_afectado',
        'tel_afectado',
        'movil_afectado',
        'detalle_solicitud',
        'modalidad',
        'derecho_solicitado',
        'area_afectada',
        'funcionarios_implicados',
        'derecho_vulnerado',
        'pretension',
        'area_mejora',
        'motivo_felicitacion',
        'autoridad_destino',
        'tipo_persona',
        'estado_firma',
        'firma_digital',
        'fecha_firma',
        'ip_firma',
        'firmado_en_representacion',
        'nombre_representado',
    ];

    protected $casts = [
        'fecha_vencimiento' => 'date',
        'fecha_vencimiento_original' => 'date',
        'fecha_respuesta' => 'datetime',
        'fechor_tramite' => 'datetime',
        'fecha_firma' => 'datetime',
        'tiene_prorroga' => 'boolean',
        'firmado_en_representacion' => 'boolean',
    ];

    public const TERMINOS = [
        'Peticion' => 15,
        'Información' => 10,
        'Informacion' => 10,
        'Consulta' => 30,
        'Queja' => 15,
        'Reclamo' => 15,
        'Sugerencia' => 15,
        'Denuncia' => 15,
        'Solicitud' => 15,
        'Corrección' => 15,
        'Correccion' => 15,
        'Felicitación' => 15,
        'Felicita' => 15,
    ];

    public const DERECHOS_FUNDAMENTALES = [
        'Vida',
        'Integridad personal',
        'Salud',
        'Educación',
        'Trabajo',
        'Vivienda digna',
        'Seguridad social',
        'Libre desarrollo de la personalidad',
        'Intimidad',
        'Derechos de los niños',
        'Debido proceso',
        'Petición',
        'Participación',
        'Demás derechos fundamentales (Art. 85 C.P.)',
    ];

    public function radicado(): BelongsTo
    {
        return $this->belongsTo(VentanillaRadicaReci::class, 'ventanilla_radica_reci_id');
    }

    public function tercero(): BelongsTo
    {
        return $this->belongsTo(GestionTercero::class, 'gestion_tercero_id');
    }

    public function tipoPqrs(): BelongsTo
    {
        return $this->belongsTo(ConfigListaDetalle::class, 'tipo_pqrs_id');
    }

    public function clasificacionDocumental(): BelongsTo
    {
        return $this->belongsTo(ClasificacionDocumentalTRD::class, 'clasificacion_documental_trd_id');
    }

    public function divisionPoliticaAfectado(): BelongsTo
    {
        return $this->belongsTo(ConfigDiviPoli::class, 'config_divi_poli_id_afectado');
    }

    public function archivos()
    {
        return $this->hasMany(VentanillaPqrsArchivo::class, 'ventanilla_pqrs_id');
    }

    public function archivoDigital()
    {
        return $this->hasOne(VentanillaPqrsArchivo::class, 'ventanilla_pqrs_id')
            ->where('tipo', 'digital');
    }

    public function scopeActivas($query)
    {
        return $query->whereNull('deleted_at');
    }

    public function scopePendientes($query)
    {
        return $query->where('estado_tramite', 'Pendiente');
    }

    public function scopeEnTramite($query)
    {
        return $query->where('estado_tramite', 'En Tramite');
    }

    public function scopeRespondidas($query)
    {
        return $query->where('estado_tramite', 'Respondida');
    }

    public function scopeVencidas($query)
    {
        return $query->where('estado_tramite', 'Vencida');
    }

    public function scopePorTipo($query, int $tipoId)
    {
        return $query->where('tipo_pqrs_id', $tipoId);
    }

    public function scopePorPrioridad($query, string $prioridad)
    {
        return $query->where('prioridad', $prioridad);
    }

    public function scopeFirmaPendiente($query)
    {
        return $query->where('estado_firma', 'pendiente');
    }

    public function scopeFirmaCompletada($query)
    {
        return $query->where('estado_firma', 'firmada');
    }

    public function scopeProximoVencer($query, int $dias = 5)
    {
        return $query->whereIn('estado_tramite', ['Pendiente', 'En Tramite'])
            ->whereDate('fecha_vencimiento', '<=', now()->addDays($dias))
            ->whereDate('fecha_vencimiento', '>=', now());
    }

    public function getDiasHabilesRestantes(): int
    {
        if (! $this->fecha_vencimiento) {
            return 0;
        }
        try {
            return CalendarioHelper::diasHabilesRestantes($this->fecha_vencimiento);
        } catch (\Exception $e) {
            return 0;
        }
    }

    public function getEstadoColor(): array
    {
        $dias = $this->getDiasHabilesRestantes();

        if ($this->estado_tramite === 'Respondida') {
            return ['color' => 'success', 'label' => 'Respondida'];
        }
        if ($this->estado_tramite === 'Vencida' || $dias < 0) {
            return ['color' => 'error', 'label' => 'Vencida'];
        }
        if ($dias <= 2) {
            return ['color' => 'error', 'label' => 'Crítico'];
        }
        if ($dias <= 5) {
            return ['color' => 'warning', 'label' => 'Urgente'];
        }

        return ['color' => 'info', 'label' => 'En término'];
    }

    public function calcularFechaVencimiento(): Carbon
    {
        $tipoLabel = $this->tipoPqrs->nombre ?? 'Peticion';
        $dias = self::TERMINOS[$tipoLabel] ?? 15;

        if ($this->prioridad === 'Tutela') {
            $dias = 2;
        }

        $fechaInicio = $this->fechor_tramite ?? now();

        return CalendarioHelper::calcularVencimiento($fechaInicio, $dias);
    }

    public function aplicarProrroga(): bool
    {
        if ($this->tiene_prorroga) {
            return false;
        }

        $tipoLabel = $this->tipoPqrs->nombre ?? 'Peticion';
        $diasExtra = self::TERMINOS[$tipoLabel] ?? 15;
        $nuevaFecha = CalendarioHelper::calcularVencimiento($this->fecha_vencimiento, $diasExtra);

        $this->update([
            'tiene_prorroga' => true,
            'fecha_vencimiento' => $nuevaFecha,
            'observaciones' => ($this->observaciones ? $this->observaciones."\n" : '').'[PRÓRROGA] Aplicada el '.now()->format('Y-m-d H:i:s'),
        ]);

        return true;
    }

    public function getInfoCompleta(): array
    {
        $this->load(['radicado', 'tercero', 'tipoPqrs', 'clasificacionDocumental', 'divisionPoliticaAfectado']);

        return [
            'id' => $this->id,
            'num_radicado' => $this->radicado?->num_radicado,
            'tipo_pqrs' => $this->tipoPqrs?->nombre,
            'estado_tramite' => $this->estado_tramite,
            'prioridad' => $this->prioridad,
            'fecha_vencimiento' => $this->fecha_vencimiento?->format('Y-m-d'),
            'dias_habiles_restantes' => $this->getDiasHabilesRestantes(),
            'afectado' => [
                'nombre' => $this->nom_afectado,
                'documento' => $this->num_docu_afectado,
                'direccion' => $this->dir_afectado,
                'telefono' => $this->tel_afectado,
                'movil' => $this->movil_afectado,
                'tercero_id' => $this->gestion_tercero_id,
            ],
            'detalle_solicitud' => $this->detalle_solicitud,
            'fallo_judicial' => $this->fallo_judicial,
            'fechor_tramite' => $this->fechor_tramite?->format('Y-m-d H:i:s'),
            'clasificacion' => $this->clasificacionDocumental?->getNombreCompleto(),
            'tiene_prorroga' => $this->tiene_prorroga,
            'observaciones' => $this->observaciones,
            'estado_color' => $this->getEstadoColor(),
        ];
    }
}
