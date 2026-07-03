<?php

use App\Models\MiBandeja\TempDocumentosRecibidos\Documento;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('respuesta.{respuestaId}', function ($user, $respuestaId) {
    return true;
});

// ==========================================
// Canales de Editor Colaborativo (Mi Bandeja)
// ==========================================
Broadcast::channel('documentos.{documentoId}', function ($user, $documentoId) {
    $documento = Documento::find($documentoId);

    if (! $documento) {
        return false;
    }

    return $documento->tieneAcceso($user);
});

// ==========================================
// Canales de Grupos Colaborativos
// ==========================================
Broadcast::channel('grupo-colaborativo.{grupoId}', function ($user, $grupoId) {
    $grupo = \App\Models\MiBandeja\MiBandejaTemp::find($grupoId);

    if (! $grupo) {
        return false;
    }

    return $grupo->responsables()->where('user_id', $user->id)->exists()
        || $grupo->firmantes()->where('user_id', $user->id)->exists()
        || $grupo->proyectores()->where('user_id', $user->id)->exists();
});
