<?php`r`nnamespace App\Models\VentanillaUnica\Pqrs;

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
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\AbacHierarquico;


/**
 * Modelo VentanillaPqrs â€” PQRS del mÃ³dulo Ventanilla Ãšnica
 *
 * Representa una PQRS (PeticiÃ³n, Queja, Reclamo, Sugerencia, FelicitaciÃ³n)
 * con soporte completo para:
 * - Responsables (custodio Ãºnico, assignaciÃ³n, acuse digital)
 * - Pases/reasignaciones con historial
 * - Compartidos (CC) con historial
 * - Firma digital con OTP
 * - Vencimiento con prÃ³rrogas
 * - Estados de trÃ¡mite con transiciones
 * - Notificaciones y correspondencia
 * - ClasificaciÃ³n documental con historial de cambios
 *
 * @property int $id
 * @property int|null $ventanilla_radica_reci_id - Radicado recibido origen (si aplica)
 * @property int|null $gestion_tercero_id - Tercero asociado (GestionTercero)
 * @property int|null $clasificacion_documental_trd_id - ClasificaciÃ³n TRD
 * @property int|null $config_divi_poli_id_afectado - DivisiÃ³n polÃ­tica del afectado
 * @property int|null $tipo_pqrs_id - Tipo de PQRS (catÃ¡logo lista 7)
 * @property string $prioridad - Normal|Alta|Urgente
 * @property string $estado_tramite - Pendiente|En trÃ¡mite|Respondido|Cerrado
 * @property \Carbon\Carbon|null $fecha_vencimiento - Fecha lÃ­mite de respuesta
 * @property \Carbon\Carbon|null $fecha_vencimiento_original - Vencimiento antes de prÃ³rroga
 * @property \Carbon\Carbon|null $fecha_respuesta - Fecha efectiva de respuesta
 * @property bool $tiene_prorroga - Si tiene prÃ³rroga activa
 * @property string $fallo_judicial - SÃ­/No
 * @property \Carbon\Carbon|null $fechor_tramite - Fecha/hora de trÃ¡mite
 * @property string|null $observaciones
 * @property string|null $num_docu_afectado - NÃºmero de documento del afectado
 * @property string|null $nom_afectado - Nombre del afectado
 * @property string|null $dir_afectado - DirecciÃ³n del afectado
 * @property string|null $tel_afectado - TelÃ©fono del afectado
 * @property string|null $movil_afectado - MÃ³vil del afectado
 * @property string|null $detalle_solicitud - Asunto/detalle de la solicitud
 * @property string|null $modalidad - Modalidad (solo para quejas/reclamos)
 * @property string|null $derecho_solicitado - Derecho solicitado
 * @property string|null $area_afectada
 * @property string|null $funcionarios_implicados
 * @property string|null $derecho_vulnerado
 * @property string|null $pretension
 * @property string|null $area_mejora
 * @property string|null $motivo_felicitacion
 * @property string|null $autoridad_destino
 * @property string $tipo_persona - Natural|JurÃ­dica
 * @property string $estado_firma - Sin firma|Pendiente OTP|Firmado
 * @property string|null $firma_digital - Firma en base64
 * @property \Carbon\Carbon|null $fecha_firma
 * @property string|null $ip_firma
 * @property bool $firmado_en_representacion
 * @property string|null $nombre_representado
 *
 * @property-read \App\Models\Configuracion\UsersCargos|null $usersCargos
 * @property-read \App\Models\Gestion\GestionTercero|null $gestionTercero
 * @property-read \App\Models\ClasificacionDocumental\ClasificacionDocumentalTRD|null $clasificacionDocumental
 * @property-read \App\Models\Configuracion\ConfigDiviPoli|null $diviPoliAfectado
 * @property-read \App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci|null $ventanillaRadicaReci
 * @property-read \Illuminate\Database\Eloquent\Collection $responsables
 * @property-read \Illuminate\Database\Eloquent\Collection $historialPases
 * @property-read \Illuminate\Database\Eloquent\Collection $historialCompartir
 * @property-read \Illuminate\Database\Eloquent\Collection $archivosPqrs
 * @property-read \Illuminate\Database\Eloquent\Collection $historialNotificaciones
 * @property-read \Illuminate\Database\Eloquent\Collection $historialClasificacion
 * @property-read int $diasRestantesVencimiento
 * @property-read bool $vencido
 * @property-read bool $critico
 * @property-read bool $puedeProrrogar
 * @property-read string $textoEstadoVencimiento
 *
 * @author Jhon Javer Lozano Arce
 * @date 2026-08-20
 */

class VentanillaPqrs extends Model
{
    use HasFactory, SoftDeletes, AbacHierarquico;

    protected $table = 'ventanilla_pqrs';

    protected const ABAC_USER_COLUMN = 'usuario_crea';
    // Sin ABAC_RESPONSABLES_RELATION: los responsables viven en el radicado
    // recibido asociado, no en la PQRS (el trait omite esa clÃ¡usula).

    /** Bypass del filtrado jerÃ¡rquico ABAC: quien lista PQRS ve todos. */
    protected const ABAC_VER_TODOS_PERMISO = 'Radicar -> PQRSF -> Listar';

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
        'InformaciÃ³n' => 10,
        'Informacion' => 10,
        'Consulta' => 30,
        'Queja' => 15,
        'Reclamo' => 15,
        'Sugerencia' => 15,
        'Denuncia' => 15,
        'Solicitud' => 15,
        'CorrecciÃ³n' => 15,
        'Correccion' => 15,
        'FelicitaciÃ³n' => 15,
        'Felicita' => 15,
    ];

    public const DERECHOS_FUNDAMENTALES = [
        'Vida',
        'Integridad personal',
        'Salud',
        'EducaciÃ³n',
        'Trabajo',
        'Vivienda digna',
        'Seguridad social',
        'Libre desarrollo de la personalidad',
        'Intimidad',
        'Derechos de los niÃ±os',
        'Debido proceso',
        'PeticiÃ³n',
        'ParticipaciÃ³n',
        'DemÃ¡s derechos fundamentales (Art. 85 C.P.)',
    ];

    public function radicado(): BelongsTo
    {
        return $this->belongsTo(VentanillaRadicaReci::class, 'ventanilla_radica_reci_id');
    }

    /**
     * Los responsables viven en el radicado recibido asociado, no en la PQRS.
     * Se acceden a travÃ©s del radicado recibido asociado.
     */
    public function responsables(): HasManyThrough
    {
        return $this->hasManyThrough(
            \App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReciResponsable::class,
            VentanillaRadicaReci::class,
            'ventanilla_radica_reci_id', // FK en ventanilla_pqrs -> ventanilla_radica_reci_id
            'radica_reci_id',            // FK en ventanilla_radica_reci_responsables
            'id',                       // PK en VentanillaRadicaReci
            'id',                       // PK en VentanillaRadicaReciResponsable
        );
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

    public function usuarioCreaRadicado(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'usuario_crea');
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
            return ['color' => 'error', 'label' => 'CrÃ­tico'];
        }
        if ($dias <= 5) {
            return ['color' => 'warning', 'label' => 'Urgente'];
        }

        return ['color' => 'info', 'label' => 'En tÃ©rmino'];
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
            'observaciones' => ($this->observaciones ? $this->observaciones."\n" : '').'[PRÃ“RROGA] Aplicada el '.now()->format('Y-m-d H:i:s'),
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

    /**
     * Actualiza el estado de trabajo del radicado asociado basado en los
     * responsables DEL RADICADO y vencimientos.
     */
    public function actualizarEstadoTrabajo(): void
    {
        if (! $this->ventanilla_radica_reci_id) {
            return;
        }

        $radicado = $this->radicado;
        if (! $radicado) {
            return;
        }

        $diasParaVencer = $this->getDiasHabilesRestantes();
        $totalResponsables = $radicado->responsables()->count();
        $totalCustodios = $radicado->responsables()->where('custodio', true)->count();

        $nuevoEstado = match (true) {
            $this->estado_tramite === 'Respondida' => 'finalizado',
            $this->estado_tramite === 'Vencida' => 'vencido',
            $diasParaVencer <= 2 && $totalResponsables > 0 => 'por_vencer',
            $totalResponsables > 0 => 'en_proceso',
            default => 'recibido',
        };

        $radicado->update(['estado_trabajo' => $nuevoEstado]);
    }
}

