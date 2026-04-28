<?php

namespace App\Http\Requests\Traits;

use App\Helpers\ArchivoHelper;
use App\Services\Seguridad\AuditLogService;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Trait para validación centralizada de subida de archivos
 *
 * OWASP A03:2021 - Injection
 * ISO 27001 A.12.2.1 - Validación de datos de entrada
 *
 * Proporciona métodos para validar archivos de forma segura.
 */
trait ValidacionArchivosTrait
{
    /**
     * Lista de MIME types permitidos por defecto
     *
     * @var array
     */
    protected array $allowedMimes = [
        'application/pdf',
        'image/jpeg',
        'image/png',
    ];

    /**
     * Tamaño máximo de archivo en bytes (50MB)
     *
     * @var int
     */
    protected int $maxFileSize = 52428800;

    /**
     * Valida un archivo usando la validación centralizada
     *
     * @param mixed $file Archivo a validar
     * @param array|null $allowedMimes MIME types permitidos (null = usar default)
     * @param int|null $maxSize Tamaño máximo en bytes (null = usar default)
     * @throws HttpResponseException
     * @return array ['valido' => bool, 'error' => string|null]
     */
    protected function validarArchivo($file, ?array $allowedMimes = null, ?int $maxSize = null): array
    {
        $allowedMimes = $allowedMimes ?? $this->allowedMimes;
        $maxSize = $maxSize ?? $this->maxFileSize;

        $resultado = ArchivoHelper::validarArchivoSeguro($file, $allowedMimes);

        if (!$resultado['valido']) {
            AuditLogService::logIntentoIntrusion(
                'upload_invalid',
                $resultado['error'],
                [
                    'campo' => $file->getName() ?? 'unknown',
                    'mime' => $file->getMimeType() ?? 'unknown',
                    'size' => $file->getSize() ?? 0,
                ]
            );

            throw new HttpResponseException(
                response()->json([
                    'success' => false,
                    'message' => $resultado['error'],
                    'error' => 'VALIDACION_ARCHIVO_FALLIDA',
                ], 422)
            );
        }

        if ($file->getSize() > $maxSize) {
            throw new HttpResponseException(
                response()->json([
                    'success' => false,
                    'message' => "El archivo excede el tamaño máximo permitido ({$maxSize} bytes)",
                    'error' => 'ARCHIVO_TAMANIO_EXCEDIDO',
                ], 422)
            );
        }

        return $resultado;
    }

    /**
     * Valida múltiples archivos
     *
     * @param array $files Archivos a validar
     * @param array|null $allowedMimes MIME types permitidos
     * @param int|null $maxSize Tamaño máximo por archivo
     * @throws HttpResponseException
     * @return array Resultados de validación
     */
    protected function validarArchivosMultiples(array $files, ?array $allowedMimes = null, ?int $maxSize = null): array
    {
        $resultados = [];
        $errores = [];

        foreach ($files as $index => $file) {
            $resultado = $this->validarArchivo($file, $allowedMimes, $maxSize);
            $resultados[$index] = $resultado;

            if (!$resultado['valido']) {
                $errores[$index] = $resultado['error'];
            }
        }

        if (!empty($errores)) {
            throw new HttpResponseException(
                response()->json([
                    'success' => false,
                    'message' => 'Algunos archivos no son válidos',
                    'errors' => $errores,
                    'error' => 'VALIDACION_MULTIPLE_FALLIDA',
                ], 422)
            );
        }

        return $resultados;
    }

    /**
     * Verifica que el archivo exista en el request
     *
     * @param string $campo Nombre del campo
     * @throws HttpResponseException
     */
    protected function verificarArchivoExiste(string $campo): void
    {
        if (!$this->hasFile($campo)) {
            throw new HttpResponseException(
                response()->json([
                    'success' => false,
                    'message' => "No se encontró archivo en el campo: {$campo}",
                    'error' => 'ARCHIVO_NO_ENCONTRADO',
                ], 422)
            );
        }
    }
}
