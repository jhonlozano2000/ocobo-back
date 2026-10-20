<?php

namespace Database\Seeders\Demo;

use App\Models\Calidad\CalidadOrganigrama;
use App\Models\ClasificacionDocumental\ClasificacionDocumentalTRD;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Datos de demostración del módulo de archivo: expedientes, préstamos y transferencias repartidos
 * en los últimos seis meses para que los dashboards no salgan en ceros.
 *
 * No forma parte de DatabaseSeeder. Se ejecuta a mano:
 *   php artisan db:seed --class="Database\Seeders\Demo\ArchivoDemoSeeder"
 *
 * Usa solo INSERT (los triggers de inmutabilidad actúan en UPDATE/DELETE), no se ejecuta en
 * producción y no hace nada si ya existen expedientes de demostración (numeración *-DEMO-*).
 */
class ArchivoDemoSeeder extends Seeder
{
    private const MARCA = 'DEMO';

    private const EXPEDIENTES = 36;

    private const ASUNTOS = [
        'Contrato de prestación de servicios', 'Historia laboral', 'Proceso de contratación directa',
        'Derecho de petición', 'Acta de comité', 'Informe de gestión', 'Convenio interadministrativo',
        'Proceso disciplinario', 'Póliza de seguros', 'Plan de mejoramiento', 'Licencia de construcción',
        'Comprobantes de egreso',
    ];

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('ArchivoDemoSeeder no se ejecuta en producción.');

            return;
        }

        if (DB::table('ofi_archivo_expedientes')->where('numero_expediente', 'like', '%-'.self::MARCA.'-%')->exists()) {
            $this->command?->warn('Ya existen expedientes de demostración; no se insertó nada.');

            return;
        }

        $dependencias = CalidadOrganigrama::where('tipo', 'Dependencia')->orderBy('id')->limit(5)->get(['id', 'cod_organico', 'nom_organico']);
        $series = ClasificacionDocumentalTRD::where('tipo', 'Serie')->orderBy('id')->limit(6)->get(['id', 'cod']);
        $usuarios = User::where('estado', 1)->orderBy('id')->limit(8)->pluck('id');

        if ($dependencias->isEmpty() || $series->isEmpty() || $usuarios->isEmpty()) {
            $this->command?->error('Faltan dependencias, series TRD o usuarios activos. Ejecuta primero DatabaseSeeder.');

            return;
        }

        mt_srand(2026);
        $archivista = $usuarios->first();
        $elegir = fn ($coleccion) => $coleccion[mt_rand(0, $coleccion->count() - 1)];

        DB::transaction(function () use ($dependencias, $series, $usuarios, $archivista, $elegir) {
            $consecutivos = [];

            for ($i = 0; $i < self::EXPEDIENTES; $i++) {
                $dependencia = $elegir($dependencias);
                $serie = $elegir($series);
                $apertura = now()->subDays(mt_rand(0, 175))->setTime(mt_rand(7, 17), mt_rand(0, 59));
                $anio = $apertura->year;
                $clave = "{$anio}-{$serie->cod}";
                $consecutivos[$clave] = ($consecutivos[$clave] ?? 0) + 1;

                // ~55 % abiertos, ~30 % cerrados en gestión, ~15 % transferidos al archivo central
                $suerte = mt_rand(1, 100);
                $estado = $suerte <= 55 ? 'abierto' : 'cerrado';
                $fase = $suerte > 85 && $apertura->lt(now()->subDays(30)) ? 'central' : 'gestion';
                $cierre = $estado === 'cerrado' ? $this->entre($apertura, now()) : null;

                $id = DB::table('ofi_archivo_expedientes')->insertGetId([
                    'numero_expediente' => sprintf('%d-%s-%s-%04d', $anio, self::MARCA, $serie->cod, $consecutivos[$clave]),
                    'nombre_expediente' => $elegir(collect(self::ASUNTOS)).' '.mt_rand(100, 999),
                    'anio' => $anio,
                    'dependencia_id' => $dependencia->id,
                    'dependencia_cod' => mb_substr((string) $dependencia->cod_organico, 0, 10),
                    'dependencia_nom' => mb_substr((string) $dependencia->nom_organico, 0, 100),
                    'serie_trd_id' => $serie->id,
                    'estado' => $estado,
                    'fase' => $fase,
                    'soporte' => $elegir(collect(['electronico', 'electronico', 'fisico', 'hibrido'])),
                    'nivel_acceso' => 'publico',
                    'por_completar' => false,
                    'fecha_apertura' => $apertura->toDateString(),
                    'fecha_cierre' => $cierre?->toDateString(),
                    'folios_fisicos' => mt_rand(0, 180),
                    'total_folios_elec' => mt_rand(1, 60),
                    'observaciones_generales' => 'Expediente de demostración.',
                    'usuario_apertura_id' => $elegir($usuarios),
                    'usuario_cierre_id' => $cierre ? $archivista : null,
                    'motivo_cierre' => $cierre ? 'Trámite finalizado' : null,
                    'created_at' => $apertura,
                    'updated_at' => $cierre ?? $apertura,
                ]);

                if ($estado === 'cerrado') {
                    $this->transferencia($id, $dependencia->nom_organico, $cierre, $fase === 'central', $archivista);
                }

                if ($fase === 'gestion' && mt_rand(1, 100) <= 60) {
                    $this->prestamo($id, $apertura, $elegir($usuarios), $archivista);
                }
            }
        });

        // Los totales del dashboard se cachean 5 minutos
        Cache::forget('archivo_estadisticas_agregadas');

        $this->command?->info('Datos de demostración de archivo creados: '.self::EXPEDIENTES.' expedientes con préstamos y transferencias.');
    }

    /** Transferencia primaria: completada si el expediente ya pasó al archivo central; si no, a veces pendiente. */
    private function transferencia(int $expedienteId, string $origen, Carbon $cierre, bool $completada, int $archivista): void
    {
        if (! $completada && mt_rand(1, 100) > 40) {
            return;
        }

        $fecha = $this->entre($cierre, now());

        DB::table('ofi_archivo_transferencias')->insert([
            'expediente_id' => $expedienteId,
            'tipo' => 'primaria',
            'origen' => mb_substr($origen, 0, 200),
            'destino' => 'Archivo central',
            'responsable_origen_id' => $archivista,
            'responsable_destino_id' => $completada ? $archivista : null,
            'fecha_transferencia' => $fecha,
            'estado' => $completada ? 'completada' : 'pendiente',
            'cotejo' => $completada ? 'conforme' : null,
            'usuario_registro_id' => $archivista,
            'created_at' => $fecha,
            'updated_at' => $fecha,
        ]);
    }

    /** Préstamo físico: devuelto, en curso, vencido o solicitado según la fecha. */
    private function prestamo(int $expedienteId, Carbon $apertura, int $solicitante, int $archivista): void
    {
        $prestamo = $this->entre($apertura, now());
        $esperada = $prestamo->copy()->addDays(mt_rand(5, 15));
        $suerte = mt_rand(1, 100);

        [$estado, $real] = match (true) {
            $suerte <= 15 => ['solicitado', null],
            $esperada->isFuture() => ['prestado', null],
            $suerte <= 45 => ['vencido', null],
            default => ['devuelto', $this->entre($prestamo, $esperada)],
        };

        DB::table('ofi_archivo_prestamos')->insert([
            'expediente_id' => $expedienteId,
            'modalidad' => 'fisico',
            'solicitante_id' => $solicitante,
            'motivo' => 'Consulta del expediente',
            'fecha_prestamo' => $estado === 'solicitado' ? null : $prestamo,
            'fecha_devolucion_esperada' => $esperada,
            'fecha_devolucion_real' => $real,
            'estado' => $estado,
            'usuario_registro_id' => $archivista,
            'aprobador_id' => $estado === 'solicitado' ? null : $archivista,
            'fecha_aprobacion' => $estado === 'solicitado' ? null : $prestamo,
            'created_at' => $prestamo,
            'updated_at' => $real ?? $prestamo,
        ]);
    }

    private function entre(Carbon $desde, Carbon $hasta): Carbon
    {
        $segundos = max(0, $hasta->getTimestamp() - $desde->getTimestamp());

        return $desde->copy()->addSeconds(mt_rand(0, $segundos));
    }
}
