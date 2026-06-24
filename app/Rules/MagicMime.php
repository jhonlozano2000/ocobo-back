<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class MagicMime implements ValidationRule
{
    private const MAP = [
        'doc' => 'application/msword',
        'dotx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.template',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'pdf' => 'application/pdf',
        'odt' => 'application/vnd.oasis.opendocument.text',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'xls' => 'application/vnd.ms-excel',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'ppt' => 'application/vnd.ms-powerpoint',
        'txt' => 'text/plain',
        'csv' => 'text/csv',
        'jpeg' => 'image/jpeg',
        'jpg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!$value instanceof \Illuminate\Http\UploadedFile) {
            $fail('El archivo no es válido.');

            return;
        }

        $extension = strtolower($value->getClientOriginalExtension());

        if (!isset(self::MAP[$extension])) {
            $fail("El formato .{$extension} no está permitido.");

            return;
        }

        $finfo = \finfo_open(\FILEINFO_MIME_TYPE);
        $mimeReal = \finfo_file($finfo, $value->getPathname());
        \finfo_close($finfo);

        $mimeEsperado = self::MAP[$extension];

        if ($mimeReal !== $mimeEsperado) {
            $fail(
                "El tipo MIME real del archivo ({$mimeReal}) no coincide con la extensión .{$extension}. "
                . 'Posible manipulación del archivo.'
            );
        }
    }
}
