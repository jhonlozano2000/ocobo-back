<?php

use App\Http\Controllers\VentanillaUnica\Recibidos\VentanillaRadicaReciController;
use App\Http\Controllers\VentanillaUnica\Recibidos\VentanillaRadicaReciDigitalController;
use App\Http\Controllers\VentanillaUnica\Recibidos\VentanillaRadicaReciAdjuntosController;
use App\Http\Controllers\VentanillaUnica\Recibidos\VentanillaRadicaReciResponsaController;
use App\Http\Controllers\VentanillaUnica\Recibidos\RadicadoComentariosController;
use App\Http\Controllers\VentanillaUnica\Recibidos\RadicadoRespuestasController;
use App\Http\Controllers\VentanillaUnica\MetadataController;
use Illuminate\Support\Facades\Route;

Route::middleware("auth:sanctum")->group(function () {
    $permReci = "Radicar -> Cores. Recibida -> ";

    // Rutas con throttle:api (rate limit general 60/min)
    Route::middleware("throttle:api")->group(function () {
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
        Route::get("/radica-recibida/mis-radicados", [VentanillaRadicaReciController::class, "misRadicados"])->name("radica-recibida.mis-radicados")->middleware("can:" . $permReci . "Listar");
        Route::get("/radica-recibida/pendientes-anulacion", [VentanillaRadicaReciController::class, "listarPendientesAnulacion"])->name("radica-recibida.pendientes-anulacion")->middleware("can:Jefe de Archivo");
        Route::post("/radica-recibida/{id}/solicitar-anulacion", [VentanillaRadicaReciController::class, "solicitarAnulacion"])->name("radica-recibida.solicitar-anulacion")->middleware("can:" . $permReci . "Listar");
        Route::post("/radica-recibida/{id}/procesar-anulacion", [VentanillaRadicaReciController::class, "procesarAnulacion"])->name("radica-recibida.procesar-anulacion")->middleware("can:Jefe de Archivo");
        Route::get("/radica-recibida", [VentanillaRadicaReciController::class, "index"])->name("radica-recibida.index")->middleware("can:" . $permReci . "Listar");
        Route::get("/radica-recibida/{id}", [VentanillaRadicaReciController::class, "show"])->name("radica-recibida.show")->middleware("can:" . $permReci . "Mostrar");
        Route::get("/radica-recibida/search/ocr", [VentanillaRadicaReciController::class, "searchByOcr"])->name("radica-recibida.search-ocr")->middleware("can:" . $permReci . "Listar");
    });

    // Rutas con throttle:radicacion (30/min) - operaciones de creación/edición
    Route::middleware("throttle:radicacion")->group(function () {
        Route::post("/radica-recibida", [VentanillaRadicaReciController::class, "store"])->name("radica-recibida.store")->middleware("can:" . $permReci . "Crear");
        Route::put("/radica-recibida/{id}", [VentanillaRadicaReciController::class, "update"])->name("radica-recibida.update")->middleware("can:" . $permReci . "Editar");
        Route::delete("/radica-recibida/{id}", [VentanillaRadicaReciController::class, "destroy"])->name("radica-recibida.destroy")->middleware("can:" . $permReci . "Eliminar");
        Route::delete("/radica-recibida", [VentanillaRadicaReciController::class, "bulkDestroy"])->name("radica-recibida.bulk-destroy")->middleware("can:" . $permReci . "Eliminar");

        // Respuestas
        Route::post("/radica-recibida/{radicadoId}/respuestas", [RadicadoRespuestasController::class, "store"])->name("radica-recibida.respuestas.store")->middleware("can:" . $permReci . "Crear");
        Route::put("/radica-recibida/respuestas/{id}", [RadicadoRespuestasController::class, "update"])->name("radica-recibida.respuestas.update")->middleware("can:" . $permReci . "Editar");
        Route::post("/radica-recibida/respuestas/{id}/lock", [RadicadoRespuestasController::class, "adquirirLock"])->name("radica-recibida.respuestas.lock")->middleware("can:" . $permReci . "Editar");
        Route::delete("/radica-recibida/respuestas/{id}/lock", [RadicadoRespuestasController::class, "liberarLock"])->name("radica-recibida.respuestas.unlock")->middleware("can:" . $permReci . "Editar");
        Route::post("/radica-recibida/respuestas/{id}/version", [RadicadoRespuestasController::class, "guardarVersion"])->name("radica-recibida.respuestas.version")->middleware("can:" . $permReci . "Editar");
        Route::delete("/radica-recibida/respuestas/{id}", [RadicadoRespuestasController::class, "destruir"])->name("radica-recibida.respuestas.destroy")->middleware("can:" . $permReci . "Eliminar");

        // Comentarios
        Route::post("/radica-recibida/{radicaReciId}/comentarios", [RadicadoComentariosController::class, "store"])->name("radica-recibida.comentarios.store")->middleware("can:" . $permReci . "Comentar");
        Route::put("/radica-recibida/comentarios/{id}", [RadicadoComentariosController::class, "update"])->name("radica-recibida.comentarios.update")->middleware("can:" . $permReci . "Comentar");
        Route::post("/radica-recibida/comentarios/{id}/resolver", [RadicadoComentariosController::class, "resolver"])->name("radica-recibida.comentarios.resolver")->middleware("can:" . $permReci . "Comentar");
        Route::delete("/radica-recibida/comentarios/{id}", [RadicadoComentariosController::class, "destroy"])->name("radica-recibida.comentarios.destroy")->middleware("can:" . $permReci . "Comentar");

        Route::post("/radica-recibida/{radica_reci_id}/responsables", [VentanillaRadicaReciResponsaController::class, "assignToRadicado"])->name("radica-recibida.responsables.asignar")->middleware("can:" . $permReci . "Editar");
    });

    // Rutas con throttle:uploads (10/min) - subida de archivos
    Route::middleware("throttle:uploads")->group(function () {
        Route::post("/radica-recibida/{id}/archivos/digital/upload", [VentanillaRadicaReciDigitalController::class, "upload"])->name("radica-recibida.archivos.digital.upload")->middleware("can:" . $permReci . "Subir digital");
        Route::post("/radica-recibida/{id}/archivos/digital/ocr/recargar", [VentanillaRadicaReciDigitalController::class, "recargarOcr"])->name("radica-recibida.archivos.digital.ocr.recargar")->middleware("can:" . $permReci . "Subir digital");
        Route::post("/radica-recibida/{id}/archivos/adjuntos/upload-adjuntos", [VentanillaRadicaReciAdjuntosController::class, "subirArchivosAdjuntos"])->name("radica-recibida.archivos.adjuntos.upload")->middleware("can:" . $permReci . "Subir adjuntos");
    });

    // Rutas de consulta con throttle:search (30/min)
    Route::middleware("throttle:search")->group(function () {
        Route::get("/radica-recibida/{radicadoId}/respuestas", [RadicadoRespuestasController::class, "index"])->name("radica-recibida.respuestas.index")->middleware("can:" . $permReci . "Listar");
        Route::get("/radica-recibida/respuestas/{id}", [RadicadoRespuestasController::class, "show"])->name("radica-recibida.respuestas.show")->middleware("can:" . $permReci . "Mostrar");
        Route::get("/radica-recibida/{radicaReciId}/comentarios", [RadicadoComentariosController::class, "index"])->name("radica-recibida.comentarios.index")->middleware("can:" . $permReci . "Mostrar");
        Route::get("/radica-recibida/comentarios/{id}", [RadicadoComentariosController::class, "show"])->name("radica-recibida.comentarios.show")->middleware("can:" . $permReci . "Mostrar");
        Route::get("/radica-recibida/{id}/archivos/digital/", [VentanillaRadicaReciDigitalController::class, "getFileInfo"])->name("radica-recibida.archivos.digital.info")->middleware("can:" . $permReci . "Mostrar");
        Route::get("/radica-recibida/{id}/archivos/digital/download", [VentanillaRadicaReciDigitalController::class, "download"])->name("radica-recibida.archivos.digital.download")->middleware("can:" . $permReci . "Mostrar");
        Route::get("/radica-recibida/{id}/archivos/digital/ocr", [VentanillaRadicaReciDigitalController::class, "getOcr"])->name("radica-recibida.archivos.digital.ocr")->middleware("can:" . $permReci . "Mostrar");
        Route::get("/radica-recibida/{id}/archivos/adjuntos/listar", [VentanillaRadicaReciAdjuntosController::class, "listarArchivosAdjuntos"])->name("radica-recibida.archivos.adjuntos.listar")->middleware("can:" . $permReci . "Mostrar");
        Route::get("/radica-recibida/{archivoId}/archivos/adjuntos/descargar", [VentanillaRadicaReciAdjuntosController::class, "descargarArchivoAdjunto"])->name("radica-recibida.archivos.adjuntos.descargar")->middleware("can:" . $permReci . "Mostrar");
        Route::get("/radica-recibida/{id}/archivos/historial/archivos-eliminados", [VentanillaRadicaReciDigitalController::class, "historialEliminaciones"])->name("radica-recibida.archivos.historial.eliminaciones")->middleware("can:" . $permReci . "Mostrar");
    });

    // Rutas con throttle:api (generales)
    Route::middleware("throttle:api")->group(function () {
        Route::delete("/radica-recibida/{id}/archivos/digital/delete", [VentanillaRadicaReciDigitalController::class, "deleteFile"])->name("radica-recibida.archivos.digital.delete")->middleware("can:" . $permReci . "Eliminar digital");
        Route::delete("/radica-recibida/{archivoId}/archivos/adjuntos/eliminar", [VentanillaRadicaReciAdjuntosController::class, "eliminarArchivoAdjunto"])->name("radica-recibida.archivos.adjuntos.eliminar")->middleware("can:" . $permReci . "Eliminar adjuntos");
        Route::apiResource("responsables", VentanillaRadicaReciResponsaController::class)->except("create", "edit")->middleware("can:" . $permReci . "Editar");
        Route::get("/radica-recibida/{radica_reci_id}/responsables", [VentanillaRadicaReciResponsaController::class, "getByRadicado"])->name("radica-recibida.responsables.listar")->middleware("can:" . $permReci . "Editar");
        Route::get("/metadata/clasificacion-niveles", [MetadataController::class, "nivelClasificacionIndex"])->name("metadata.niveles")->middleware("can:" . $permReci . "Listar");
        Route::get("/metadata/archivos/{archivoId}/{tipo?}", [MetadataController::class, "show"])->name("metadata.show")->middleware("can:" . $permReci . "Mostrar");
        Route::get("/metadata/historial/{metadataId}/{tipo?}", [MetadataController::class, "historial"])->name("metadata.historial")->middleware("can:" . $permReci . "Mostrar");
        Route::get("/metadata/exportar/{tipo?}", [MetadataController::class, "exportar"])->name("metadata.exportar")->middleware("can:" . $permReci . "Exportar");
    });
});
