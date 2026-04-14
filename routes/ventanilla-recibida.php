<?php

use App\Http\Controllers\VentanillaUnica\VentanillaRadicaReciController;
use App\Http\Controllers\VentanillaUnica\VentanillaRadicaReciArchivosController;
use App\Http\Controllers\VentanillaUnica\VentanillaRadicaReciResponsaController;
use Illuminate\Support\Facades\Route;

Route::middleware("auth:sanctum")->group(function () {
    $permReci = "Radicar -> Cores. Recibida -> ";

    Route::get("/radica-recibida/estadisticas", [VentanillaRadicaReciController::class, "estadisticas"])->name("radica-recibida.estadisticas")->middleware("can:" . $permReci . "Listar");
    Route::put("/radica-recibida/{id}/update-asunto", [VentanillaRadicaReciController::class, "updateAsunto"])->name("radica-recibida.update-asunto")->middleware("can:" . $permReci . "Actualizar asunto");
    Route::put("/radica-recibida/{id}/update-fechas", [VentanillaRadicaReciController::class, "updateFechas"])->name("radica-recibida.update-fechas")->middleware("can:" . $permReci . "Atualizar fechas de radicados");
    Route::put("/radica-recibida/{id}/update-clasificacion-documental", [VentanillaRadicaReciController::class, "updateClasificacionDocumental"])->name("radica-recibida.update-clasificacion-documental")->middleware("can:" . $permReci . "Actualizar clasificacion de radicados");
    Route::post("/radica-recibida/{id}/notificacion", [VentanillaRadicaReciController::class, "enviarNotificacion"])->name("radica-recibida.notificacion")->middleware("can:" . $permReci . "Notificar Email");
    Route::post("/radica-recibida/{id}/notificacion-tercero", [VentanillaRadicaReciController::class, "enviarNotificacionTercero"])->name("radica-recibida.notificacion-tercero")->middleware("can:" . $permReci . "Notificar Email");
    Route::get("/radica-recibida/{id}/linea-tiempo", [VentanillaRadicaReciController::class, "lineaTiempo"])->name("radica-recibida.linea-tiempo")->middleware("can:" . $permReci . "Mostrar");
    Route::get("/radica-recibida", [VentanillaRadicaReciController::class, "index"])->name("radica-recibida.index")->middleware("can:" . $permReci . "Listar");
    Route::post("/radica-recibida", [VentanillaRadicaReciController::class, "store"])->name("radica-recibida.store")->middleware("can:" . $permReci . "Crear");
    Route::get("/radica-recibida/{id}", [VentanillaRadicaReciController::class, "show"])->name("radica-recibida.show")->middleware("can:" . $permReci . "Mostrar");
    Route::put("/radica-recibida/{id}", [VentanillaRadicaReciController::class, "update"])->name("radica-recibida.update")->middleware("can:" . $permReci . "Editar");
    Route::delete("/radica-recibida/{id}", [VentanillaRadicaReciController::class, "destroy"])->name("radica-recibida.destroy")->middleware("can:" . $permReci . "Eliminar");

    Route::prefix("radica-recibida/{id}")->name("radica-recibida.")->group(function () use ($permReci) {
        Route::get("/archivos/adjuntos/listar", [VentanillaRadicaReciArchivosController::class, "listarArchivosAdjuntos"])->name("archivos.adjuntos.listar")->middleware("can:" . $permReci . "Mostrar");
        Route::get("/archivos/adjuntos/{archivoId}/descargar", [VentanillaRadicaReciArchivosController::class, "descargarArchivoAdjunto"])->name("archivos.adjuntos.descargar")->middleware("can:" . $permReci . "Mostrar");
        Route::delete("/archivos/adjuntos/{archivoId}/eliminar", [VentanillaRadicaReciArchivosController::class, "eliminarArchivoAdjunto"])->name("archivos.adjuntos.eliminar")->middleware("can:" . $permReci . "Eliminar adjuntos");
        Route::get("/archivos/historial/archivos-eliminados", [VentanillaRadicaReciArchivosController::class, "historialEliminaciones"])->name("archivos.historial.eliminaciones")->middleware("can:" . $permReci . "Mostrar");
        Route::get("/archivos/info/", [VentanillaRadicaReciArchivosController::class, "getFileInfo"])->name("archivos.info")->middleware("can:" . $permReci . "Mostrar");
        Route::post("/archivos/upload-digital", [VentanillaRadicaReciArchivosController::class, "upload"])->name("archivos.upload.digital")->middleware("can:" . $permReci . "Subir digital");
        Route::post("/archivos/upload-adjuntos", [VentanillaRadicaReciArchivosController::class, "subirArchivosAdjuntos"])->name("archivos.upload-adjuntos")->middleware("can:" . $permReci . "Subir adjuntos");
        Route::get("/archivos/download", [VentanillaRadicaReciArchivosController::class, "download"])->name("archivos.download")->middleware("can:" . $permReci . "Mostrar");
        Route::delete("/archivos/delete", [VentanillaRadicaReciArchivosController::class, "deleteFile"])->name("archivos.delete")->middleware("can:" . $permReci . "Eliminar digital");
    });

    Route::apiResource("responsables", VentanillaRadicaReciResponsaController::class)->except("create", "edit")->middleware("can:" . $permReci . "Editar");
    Route::get("/radica-recibida/{radica_reci_id}/responsables", [VentanillaRadicaReciResponsaController::class, "getByRadicado"])->name("radica-recibida.responsables.listar")->middleware("can:" . $permReci . "Editar");
    Route::post("/radica-recibida/{radica_reci_id}/responsables", [VentanillaRadicaReciResponsaController::class, "assignToRadicado"])->name("radica-recibida.responsables.asignar")->middleware("can:" . $permReci . "Editar");
});
