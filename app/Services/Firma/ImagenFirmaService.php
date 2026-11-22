<?php

namespace App\Services\Firma;

use App\Helpers\ArchivoHelper;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ImagenFirmaService
{
    public function actualizarPerfil(User $usuario, UploadedFile $archivo): User
    {
        $upload = ArchivoHelper::guardarUploadedConHash($archivo, 'firmas');
        if (! $upload) {
            throw ValidationException::withMessages(['firma' => 'No se pudo guardar la imagen de firma.']);
        }

        try {
            $user = DB::transaction(function () use ($usuario, $upload) {
                $user = User::lockForUpdate()->findOrFail($usuario->id);
                $anterior = $user->firma;
                $user->update(['firma' => $upload['nombre_almacenado']]);
                ArchivoHelper::eliminarAlConfirmar([$anterior], 'firmas');
                return $user;
            });
        } catch (\Throwable $error) {
            ArchivoHelper::eliminarArchivo($upload['nombre_almacenado'], 'firmas');
            throw $error;
        }
        return $user;
    }

    public function capturar(User $usuario): ?string
    {
        if (! $usuario->firma) {
            return null;
        }

        $bytes = ArchivoHelper::obtenerContenido($usuario->firma, 'firmas');
        $mime = $bytes ? (@getimagesizefromstring($bytes)['mime'] ?? null) : null;
        if (! in_array($mime, ['image/jpeg', 'image/png'], true)) {
            throw ValidationException::withMessages(['firma' => 'Actualice la imagen de firma de su perfil en formato PNG o JPG.']);
        }

        return 'data:'.$mime.';base64,'.base64_encode($bytes);
    }
}
