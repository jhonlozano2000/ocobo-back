<?php

namespace App\Services\Firma;

use App\Models\Transversal\FirmaEvento;
use App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviados;
use App\Models\VentanillaUnica\Internos\VentanillaRadicaInterno;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use Illuminate\Support\Facades\Storage;

class FirmaValidacionService
{
    const MODEL_MAP = [
        'radicado_recibido' => VentanillaRadicaReci::class,
        'radicado_enviado' => VentanillaRadicaEnviados::class,
        'radicado_interno' => VentanillaRadicaInterno::class,
    ];

    const DISK_MAP = [
        'radicado_recibido' => 'radicados_recibidos',
        'radicado_enviado' => 'radicados_enviados',
        'radicado_interno' => 'ventanilla_radica_interno_archivos',
    ];

    public function validar(string $tipo, int $id): array
    {
        if (!isset(self::MODEL_MAP[$tipo])) {
            throw new \InvalidArgumentException("Tipo de documento no válido: $tipo");
        }

        $modelClass = self::MODEL_MAP[$tipo];
        $documento = $modelClass::find($id);

        if (!$documento) {
            throw new \RuntimeException('Documento no encontrado');
        }

        if (!$documento->archivo_digital) {
            throw new \RuntimeException('El documento no tiene archivo PDF asociado');
        }

        // Recalcular hash del PDF actual
        $disk = self::DISK_MAP[$tipo];
        $pdfPath = Storage::disk($disk)->path($documento->archivo_digital);

        if (!file_exists($pdfPath)) {
            throw new \RuntimeException('El archivo PDF no se encuentra en el almacenamiento');
        }

        $hashActual = hash_file('sha256', $pdfPath);

        // Buscar último evento de firma con hash_firmado
        $modelClassFull = get_class($documento);
        $ultimoEvento = FirmaEvento::where('documentable_id', $id)
            ->where('documentable_type', $modelClassFull)
            ->whereNotNull('hash_firmado')
            ->orderBy('fecha_firma', 'desc')
            ->first();

        if (!$ultimoEvento) {
            throw new \RuntimeException('El documento no tiene un evento de firma registrado');
        }

        $valido = $hashActual === $ultimoEvento->hash_firmado;

        return [
            'valido' => $valido,
            'hash_actual' => $hashActual,
            'hash_firmado' => $ultimoEvento->hash_firmado,
            'fecha_firma' => $ultimoEvento->fecha_firma,
            'firmante' => $ultimoEvento->user ? [
                'nombres' => trim($ultimoEvento->user->nombres . ' ' . $ultimoEvento->user->apellidos),
                'email' => $ultimoEvento->user->email,
            ] : null,
            'documento' => [
                'tipo' => $tipo,
                'radicado' => $documento->radicado ?? 'N/A',
            ],
        ];
    }
}
