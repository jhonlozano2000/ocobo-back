<?php

namespace App\Services\OfiArchivo;

use App\Models\ClasificacionDocumental\ClasificacionDocumentalTRD;
use App\Models\OfiArchivo\OfiArchivoExpediente;
use App\Models\OfiArchivo\OfiArchivoExpedienteDocumento;
use App\Models\OfiArchivo\OfiArchivoExpedienteHojaControl;
use App\Models\OfiArchivo\OfiArchivoPrestamo;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use App\Models\Workflows\WorkflowInstancia;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * B14 (diseño de expedientes §3.4): condiciones para cerrar un expediente y la instantánea
 * de la TRD con la que se calculan su retención y su disposición final.
 */
class CierreExpedienteService
{
    public const DISK = 'archivo_expedientes';

    private static ?bool $disponible = null;

    /** Las columnas llegan con la migración 2026_10_19_140000. */
    public static function disponible(): bool
    {
        return self::$disponible ??= Schema::hasColumn('ofi_archivo_expedientes', 'trd_snapshot');
    }

    /**
     * Lo que impide cerrar el expediente; vacío si se puede cerrar.
     *
     * @return list<string>
     */
    public function impedimentos(OfiArchivoExpediente $expediente, Carbon $fechaCierre): array
    {
        $impedimentos = [];

        if ($expediente->fecha_apertura && $fechaCierre->lt($expediente->fecha_apertura->copy()->startOfDay())) {
            $impedimentos[] = 'La fecha de cierre es anterior a la apertura del expediente ('.$expediente->fecha_apertura->format('Y-m-d').').';
        }

        // B06: sin la migración del nivel de acceso no hay qué exigir
        $pendientes = Schema::hasColumn('ofi_archivo_expedientes', 'nivel_acceso') ? $expediente->pendientesDeCompletar() : [];
        if ($pendientes) {
            $nombres = ['nivel_acceso' => 'nivel de acceso', 'subserie_trd_id' => 'subserie TRD'];
            $impedimentos[] = 'El expediente está por completar; falta declarar: '
                .implode(', ', array_map(fn ($p) => $nombres[$p] ?? $p, $pendientes)).'.';
        }

        if (in_array($expediente->soporte, ['fisico', 'hibrido'], true) && blank($expediente->ubicacion_fisica)) {
            $impedimentos[] = 'El expediente '.($expediente->soporte === 'fisico' ? 'físico' : 'híbrido').' no tiene ubicación física registrada.';
        }

        // B19: la hoja de control se firma al cierre; sin documentos no hay qué firmar
        if (HojaControlService::disponible() && HojaControlService::aplica($expediente)
            && ! OfiArchivoExpedienteHojaControl::activos()->where('expediente_id', $expediente->id)->exists()) {
            $impedimentos[] = 'La hoja de control no tiene documentos físicos registrados.';
        }

        $prestamos = OfiArchivoPrestamo::where('expediente_id', $expediente->id)->whereIn('estado', ['prestado', 'vencido'])->count();
        if ($prestamos > 0) {
            $impedimentos[] = "El expediente tiene {$prestamos} préstamo(s) sin devolver.";
        }

        if ($flujos = $this->flujosEnCurso($expediente)) {
            $impedimentos[] = 'Hay flujos de trabajo en curso sobre radicados del expediente (instancias '.implode(', ', $flujos).').';
        }

        $documentos = $expediente->documentos()->where('activo', true)->with('documentable')->get();

        if (TiposDocumentalesExpediente::disponible()) {
            $sinTipo = $documentos->where('tipo', 'tipo_documental')->whereNull('tipo_documental_id')->pluck('numero_folio');
            if ($sinTipo->isNotEmpty()) {
                $impedimentos[] = 'Folios sin tipificar con la TRD: '.$sinTipo->implode(', ').'.';
            }
        }

        $sinHuella = $documentos->reject(fn (OfiArchivoExpedienteDocumento $doc) => $this->huellaVerificada($doc))->pluck('numero_folio');
        if ($sinHuella->isNotEmpty()) {
            $impedimentos[] = 'Folios sin huella o cuyo archivo no coincide con ella: '.$sinHuella->implode(', ').'.';
        }

        return $impedimentos;
    }

    /**
     * Instantánea de la TRD vigente al cierre (la subserie si la tiene; si no, la serie).
     *
     * @return array<string, mixed>|null
     */
    public function instantaneaTrd(OfiArchivoExpediente $expediente): ?array
    {
        $trd = ClasificacionDocumentalTRD::with('versionTRD')->find($expediente->subserie_trd_id ?: $expediente->serie_trd_id);
        if (! $trd) {
            return null;
        }
        $serie = $expediente->subserie_trd_id ? ClasificacionDocumentalTRD::find($expediente->serie_trd_id) : $trd;

        return [
            'trd_id' => $trd->id,
            'tipo' => $trd->tipo,
            'cod' => $trd->cod,
            'nom' => $trd->nom,
            'serie' => $serie ? ['id' => $serie->id, 'cod' => $serie->cod, 'nom' => $serie->nom] : null,
            'version_id' => $trd->version_id,
            'version' => $trd->versionTRD?->version,
            'a_g' => (int) $trd->a_g,
            'a_c' => (int) $trd->a_c,
            'ct' => (bool) $trd->ct,
            'e' => (bool) $trd->e,
            'm_d' => (bool) $trd->m_d,
            's' => (bool) $trd->s,
            'disposicion_final' => $trd->disposicion_final,
            'procedimiento' => $trd->procedimiento,
            'tomada_en' => now()->toDateTimeString(),
        ];
    }

    /**
     * Campos que el cierre guarda en el expediente: instantánea y fechas de retención.
     *
     * @return array<string, mixed>
     */
    public function datosRetencion(OfiArchivoExpediente $expediente, Carbon $fechaCierre): array
    {
        $trd = $this->instantaneaTrd($expediente);

        return [
            'trd_snapshot' => $trd,
            'fecha_fin_gestion' => $trd ? $fechaCierre->copy()->addYears($trd['a_g'])->toDateString() : null,
            'fecha_fin_central' => $trd ? $fechaCierre->copy()->addYears($trd['a_g'] + $trd['a_c'])->toDateString() : null,
        ];
    }

    /**
     * Instancias en curso cuyo radicado de origen está incorporado (activo) en el expediente.
     *
     * @return list<int>
     */
    private function flujosEnCurso(OfiArchivoExpediente $expediente): array
    {
        if (! Schema::hasColumn('workflow_instancias', 'radicado_id')) {
            return [];
        }

        return WorkflowInstancia::where('estado', 'en_curso')
            ->whereIn('radicado_id', OfiArchivoExpedienteDocumento::where('expediente_id', $expediente->id)
                ->where('activo', true)
                ->where('documentable_type', VentanillaRadicaReci::class)
                ->select('documentable_id'))
            ->orderBy('id')->pluck('id')->all();
    }

    /** El folio tiene huella y, si es un archivo cargado al expediente, el archivo aún coincide con ella. */
    private function huellaVerificada(OfiArchivoExpedienteDocumento $doc): bool
    {
        if (! $doc->archivo_path) {
            // Radicado incorporado: su huella es la del radicado
            return (bool) ($doc->hash_sha256 ?: $doc->documentable?->hash_sha256);
        }
        if (! $doc->hash_sha256) {
            return false;
        }

        $disco = Storage::disk(self::DISK);
        $stream = $disco->exists($doc->archivo_path) ? $disco->readStream($doc->archivo_path) : null;
        if (! $stream) {
            return false;
        }

        $contexto = hash_init('sha256');
        hash_update_stream($contexto, $stream);
        fclose($stream);

        return hash_equals($doc->hash_sha256, hash_final($contexto));
    }
}
