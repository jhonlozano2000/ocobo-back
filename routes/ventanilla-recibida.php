<?php

use App\Http\Controllers\VentanillaUnica\Recibidos\VentanillaRadicaReciController;
use App\Http\Controllers\VentanillaUnica\Recibidos\VentanillaRadicaReciDigitalController;
use App\Http\Controllers\VentanillaUnica\Recibidos\VentanillaRadicaReciAdjuntosController;
use App\Http\Controllers\VentanillaUnica\Recibidos\VentanillaRadicaReciResponsaController;
use App\Http\Controllers\VentanillaUnica\MetadataController;
use Illuminate\Support\Facades\Route;

Route::middleware("auth:sanctum")->group(function () {
    $permReci = "Radicar -> Cores. Recibida -> ";

    Route::get("/radica-recibida/estadisticas", [VentanillaRadicaReciController::class, "estadisticas"])->name("radica-recibida.estadisticas")->middleware("can:" . $permReci . "Listar");
    Route::get("/radica-recibida/estados", [VentanillaRadicaReciController::class, "estadosDisponibles"])->name("radica-recibida.estados")->middleware("can:" . $permReci . "Listar");
    Route::get("/radica-recibida/estados/{estadoId}/transiciones", [VentanillaRadicaReciController::class, "transicionesEstado"])->name("radica-recibida.estados.transiciones")->middleware("can:" . $permReci . "Listar");
    Route::put("/radica-recibida/{id}/update-asunto", [VentanillaRadicaReciController::class, "updateAsunto"])->name("radica-recibida.update-asunto")->middleware("can:" . $permReci . "Actualizar asunto");
    Route::put("/radica-recibida/{id}/update-fechas", [VentanillaRadicaReciController::class, "updateFechas"])->name("radica-recibida.update-fechas")->middleware("can:" . $permReci . "Atualizar fechas de radicados");
    Route::put("/radica-recibida/{id}/update-clasificacion-documental", [VentanillaRadicaReciController::class, "updateClasificacionDocumental"])->name("radica-recibida.update-clasificacion-documental")->middleware("can:" . $permReci . "Actualizar clasificacion de radicados");
    Route::post("/radica-recibida/{id}/notificacion", [VentanillaRadicaReciController::class, "enviarNotificacion"])->name("radica-recibida.notificacion")->middleware("can:" . $permReci . "Notificar Email");
    Route::post("/radica-recibida/{id}/notificacion-tercero", [VentanillaRadicaReciController::class, "enviarNotificacionTercero"])->name("radica-recibida.notificacion-tercero")->middleware("can:" . $permReci . "Notificar Email");
    Route::get("/radica-recibida/{id}/linea-tiempo", [VentanillaRadicaReciController::class, "lineaTiempo"])->name("radica-recibida.linea-tiempo")->middleware("can:" . $permReci . "Mostrar");
    Route::put("/radica-recibida/{id}/estado", [VentanillaRadicaReciController::class, "cambiarEstado"])->name("radica-recibida.cambiar-estado")->middleware("can:" . $permReci . "Editar");
    Route::get("/radica-recibida/{id}/historial-estados", [VentanillaRadicaReciController::class, "historialEstados"])->name("radica-recibida.historial-estados")->middleware("can:" . $permReci . "Mostrar");
    Route::get("/radica-recibida/search/ocr", [VentanillaRadicaReciController::class, "searchByOcr"])->name("radica-recibida.search-ocr")->middleware("can:" . $permReci . "Listar");
    Route::get("/radica-recibida", [VentanillaRadicaReciController::class, "index"])->name("radica-recibida.index")->middleware("can:" . $permReci . "Listar");
    Route::post("/radica-recibida", [VentanillaRadicaReciController::class, "store"])->name("radica-recibida.store")->middleware("can:" . $permReci . "Crear");
    Route::get("/radica-recibida/{id}", [VentanillaRadicaReciController::class, "show"])->name("radica-recibida.show")->middleware("can:" . $permReci . "Mostrar");
    Route::put("/radica-recibida/{id}", [VentanillaRadicaReciController::class, "update"])->name("radica-recibida.update")->middleware("can:" . $permReci . "Editar");
    Route::delete("/radica-recibida/{id}", [VentanillaRadicaReciController::class, "destroy"])->name("radica-recibida.destroy")->middleware("can:" . $permReci . "Eliminar");
    Route::delete("/radica-recibida", [VentanillaRadicaReciController::class, "bulkDestroy"])->name("radica-recibida.bulk-destroy")->middleware("can:" . $permReci . "Eliminar");

    Route::prefix("radica-recibida/{id}/archivos")->name("radica-recibida.archivos.")->group(function () use ($permReci) {
        Route::prefix("digital")->name("digital.")->group(function () use ($permReci) {
            Route::get("/", [VentanillaRadicaReciDigitalController::class, "getFileInfo"])->name("info")->middleware("can:" . $permReci . "Mostrar");
            Route::post("/upload", [VentanillaRadicaReciDigitalController::class, "upload"])->name("upload")->middleware("can:" . $permReci . "Subir digital");
            Route::get("/download", [VentanillaRadicaReciDigitalController::class, "download"])->name("download")->middleware("can:" . $permReci . "Mostrar");
            Route::delete("/delete", [VentanillaRadicaReciDigitalController::class, "deleteFile"])->name("delete")->middleware("can:" . $permReci . "Eliminar digital");
            Route::get("/ocr", [VentanillaRadicaReciDigitalController::class, "getOcr"])->name("ocr")->middleware("can:" . $permReci . "Mostrar");
            Route::post("/ocr/recargar", [VentanillaRadicaReciDigitalController::class, "recargarOcr"])->name("ocr.recargar")->middleware("can:" . $permReci . "Subir digital");
        });

        Route::prefix("adjuntos")->name("adjuntos.")->group(function () use ($permReci) {
            Route::get("/listar", [VentanillaRadicaReciAdjuntosController::class, "listarArchivosAdjuntos"])->name("listar")->middleware("can:" . $permReci . "Mostrar");
            Route::get("/{archivoId}/descargar", [VentanillaRadicaReciAdjuntosController::class, "descargarArchivoAdjunto"])->name("descargar")->middleware("can:" . $permReci . "Mostrar");
            Route::delete("/{archivoId}/eliminar", [VentanillaRadicaReciAdjuntosController::class, "eliminarArchivoAdjunto"])->name("eliminar")->middleware("can:" . $permReci . "Eliminar adjuntos");
            Route::post("/upload-adjuntos", [VentanillaRadicaReciAdjuntosController::class, "subirArchivosAdjuntos"])->name("upload")->middleware("can:" . $permReci . "Subir adjuntos");
        });

        Route::get("/historial/archivos-eliminados", [VentanillaRadicaReciDigitalController::class, "historialEliminaciones"])->name("historial.eliminaciones")->middleware("can:" . $permReci . "Mostrar");
    });

    Route::apiResource("responsables", VentanillaRadicaReciResponsaController::class)->except("create", "edit")->middleware("can:" . $permReci . "Editar");
    Route::get("/radica-recibida/{radica_reci_id}/responsables", [VentanillaRadicaReciResponsaController::class, "getByRadicado"])->name("radica-recibida.responsables.listar")->middleware("can:" . $permReci . "Editar");
    Route::post("/radica-recibida/{radica_reci_id}/responsables", [VentanillaRadicaReciResponsaController::class, "assignToRadicado"])->name("radica-recibida.responsables.asignar")->middleware("can:" . $permReci . "Editar");

    Route::get("/metadata/clasificacion-niveles", [MetadataController::class, "nivelClasificacionIndex"])->name("metadata.niveles")->middleware("can:" . $permReci . "Listar");
    Route::get("/metadata/archivos/{archivoId}/{tipo?}", [MetadataController::class, "show"])->name("metadata.show")->middleware("can:" . $permReci . "Mostrar");
    Route::get("/metadata/historial/{metadataId}/{tipo?}", [MetadataController::class, "historial"])->name("metadata.historial")->middleware("can:" . $permReci . "Mostrar");
    Route::get("/metadata/exportar/{tipo?}", [MetadataController::class, "exportar"])->name("metadata.exportar")->middleware("can:" . $permReci . "Exportar");
});
