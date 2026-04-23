<?php

use App\Http\Controllers\VentanillaUnica\Enviados\VentanillaRadicaEnviadosDigitalController;
use App\Http\Controllers\VentanillaUnica\Enviados\VentanillaRadicaEnviadosAdjuntosController;
use App\Http\Controllers\VentanillaUnica\Enviados\VentanillaRadicaEnviadosController;
use App\Http\Controllers\VentanillaUnica\Enviados\VentanillaRadicaEnviadosFirmantesController;
use App\Http\Controllers\VentanillaUnica\Enviados\VentanillaRadicaEnviadosProyectoresController;
use App\Http\Controllers\VentanillaUnica\Enviados\VentanillaRadicaEnviadosResponsaController;
use App\Http\Controllers\VentanillaUnica\MetadataController;
use Illuminate\Support\Facades\Route;

Route::middleware("auth:sanctum")->group(function () {
    $permEnvi = "Radicar -> Cores. Enviada -> ";

    Route::get("/radica-enviada/estadisticas", [VentanillaRadicaEnviadosController::class, "estadisticas"])->name("radica-enviada.estadisticas")->middleware("can:" . $permEnvi . "Listar");
    Route::put("/radica-enviada/{id}/update-asunto", [VentanillaRadicaEnviadosController::class, "updateAsunto"])->name("radica-enviada.update-asunto")->middleware("can:" . $permEnvi . "Actualizar asunto");
    Route::put("/radica-enviada/{id}/update-fechas", [VentanillaRadicaEnviadosController::class, "updateFechas"])->name("radica-enviada.update-fechas")->middleware("can:" . $permEnvi . "Atualizar fechas de radicados");
    Route::put("/radica-enviada/{id}/update-clasificacion-documental", [VentanillaRadicaEnviadosController::class, "updateClasificacionDocumental"])->name("radica-enviada.update-clasificacion-documental")->middleware("can:" . $permEnvi . "Actualizar clasificacion de radicados");
    Route::post("/radica-enviada/{id}/notificacion", [VentanillaRadicaEnviadosController::class, "enviarNotificacion"])->name("radica-enviada.notificacion")->middleware("can:" . $permEnvi . "Notificar Email");
    Route::get("/radica-enviada/{id}/linea-tiempo", [VentanillaRadicaEnviadosController::class, "lineaTiempo"])->name("radica-enviada.linea-tiempo")->middleware("can:" . $permEnvi . "Mostrar");
    Route::get("/radica-enviada", [VentanillaRadicaEnviadosController::class, "index"])->name("radica-enviada.index")->middleware("can:" . $permEnvi . "Listar");
    Route::post("/radica-enviada", [VentanillaRadicaEnviadosController::class, "store"])->name("radica-enviada.store")->middleware("can:" . $permEnvi . "Crear");
    Route::get("/radica-enviada/{id}", [VentanillaRadicaEnviadosController::class, "show"])->name("radica-enviada.show")->middleware("can:" . $permEnvi . "Mostrar");
    Route::put("/radica-enviada/{id}", [VentanillaRadicaEnviadosController::class, "update"])->name("radica-enviada.update")->middleware("can:" . $permEnvi . "Editar");
    Route::delete("/radica-enviada/{id}", [VentanillaRadicaEnviadosController::class, "destroy"])->name("radica-enviada.destroy")->middleware("can:" . $permEnvi . "Eliminar");
    Route::get("/radica-enviada/search/ocr", [VentanillaRadicaEnviadosController::class, "searchByOcr"])->name("radica-enviada.search-ocr")->middleware("can:" . $permEnvi . "Listar");

    Route::prefix("radica-enviada/{id}/archivos")->name("radica-enviada.archivos.")->group(function () use ($permEnvi) {
        Route::prefix("digital")->name("digital.")->group(function () use ($permEnvi) {
            Route::get("/", [VentanillaRadicaEnviadosDigitalController::class, "getFileInfo"])->name("info")->middleware("can:" . $permEnvi . "Mostrar");
            Route::post("/upload", [VentanillaRadicaEnviadosDigitalController::class, "upload"])->name("upload")->middleware("can:" . $permEnvi . "Subir digital");
            Route::get("/download", [VentanillaRadicaEnviadosDigitalController::class, "download"])->name("download")->middleware("can:" . $permEnvi . "Mostrar");
            Route::delete("/delete", [VentanillaRadicaEnviadosDigitalController::class, "deleteFile"])->name("delete")->middleware("can:" . $permEnvi . "Eliminar digital");
            Route::get("/ocr", [VentanillaRadicaEnviadosDigitalController::class, "getOcr"])->name("ocr")->middleware("can:" . $permEnvi . "Mostrar");
            Route::post("/ocr/recargar", [VentanillaRadicaEnviadosDigitalController::class, "recargarOcr"])->name("ocr.recargar")->middleware("can:" . $permEnvi . "Subir digital");
        });

        Route::prefix("adjuntos")->name("adjuntos.")->group(function () use ($permEnvi) {
            Route::get("/listar", [VentanillaRadicaEnviadosAdjuntosController::class, "listarArchivosAdjuntos"])->name("listar")->middleware("can:" . $permEnvi . "Mostrar");
            Route::get("/{archivoId}/descargar", [VentanillaRadicaEnviadosAdjuntosController::class, "descargarArchivoAdjunto"])->name("descargar")->middleware("can:" . $permEnvi . "Mostrar");
            Route::delete("/{archivoId}/eliminar", [VentanillaRadicaEnviadosAdjuntosController::class, "eliminarArchivoAdjunto"])->name("eliminar")->middleware("can:" . $permEnvi . "Eliminar adjuntos");
            Route::post("/upload-adjuntos", [VentanillaRadicaEnviadosAdjuntosController::class, "subirArchivosAdjuntos"])->name("upload")->middleware("can:" . $permEnvi . "Subir adjuntos");
        });

        Route::get("/historial/archivos-eliminados", [VentanillaRadicaEnviadosDigitalController::class, "historialEliminaciones"])->name("historial.eliminaciones")->middleware("can:" . $permEnvi . "Mostrar");
    });

    Route::apiResource("responsables-enviados", VentanillaRadicaEnviadosResponsaController::class)->except("create", "edit")->middleware("can:" . $permEnvi . "Editar");
    Route::get("/radica-enviada/{radica_enviado_id}/responsables", [VentanillaRadicaEnviadosResponsaController::class, "getByRadicado"])->name("radica-enviada.responsables.listar")->middleware("can:" . $permEnvi . "Editar");     
    Route::post("/radica-enviada/{radica_enviado_id}/responsables", [VentanillaRadicaEnviadosResponsaController::class, "assignToRadicado"])->name("radica-enviada.responsables.asignar")->middleware("can:" . $permEnvi . "Editar");

    Route::apiResource("firmantes-enviados", VentanillaRadicaEnviadosFirmantesController::class)->except("create", "edit")->middleware("can:" . $permEnvi . "Editar");
    Route::get("/radica-enviada/{radica_enviado_id}/firmantes", [VentanillaRadicaEnviadosFirmantesController::class, "getByRadicado"])->name("radica-enviada.firmantes.listar")->middleware("can:" . $permEnvi . "Editar");
    Route::post("/radica-enviada/{radica_enviado_id}/firmantes", [VentanillaRadicaEnviadosFirmantesController::class, "assignToRadicado"])->name("radica-enviada.firmantes.asignar")->middleware("can:" . $permEnvi . "Editar");     

    Route::apiResource("proyectores-enviados", VentanillaRadicaEnviadosProyectoresController::class)->except("create", "edit")->middleware("can:" . $permEnvi . "Editar");
    Route::get("/radica-enviada/{radica_enviado_id}/proyectores", [VentanillaRadicaEnviadosProyectoresController::class, "getByRadicado"])->name("radica-enviada.proyectores.listar")->middleware("can:" . $permEnvi . "Editar");    
    Route::post("/radica-enviada/{radica_enviado_id}/proyectores", [VentanillaRadicaEnviadosProyectoresController::class, "assignToRadicado"])->name("radica-enviada.proyectores.asignar")->middleware("can:" . $permEnvi . "Editar");

    Route::delete("/radica-enviada", [VentanillaRadicaEnviadosController::class, "bulkDestroy"])->name("radica-enviada.bulk-destroy")->middleware("can:" . $permEnvi . "Eliminar");
    Route::post("/radica-enviada/{id}/notificacion-tercero", [VentanillaRadicaEnviadosController::class, "enviarNotificacionTercero"])->name("radica-enviada.notificacion-tercero")->middleware("can:" . $permEnvi . "Notificar Email");

    Route::get("/metadata/clasificacion-niveles", [MetadataController::class, "nivelClasificacionIndex"])->name("metadata.niveles")->middleware("can:" . $permEnvi . "Listar");
    Route::get("/metadata/archivos/{archivoId}/{tipo?}", [MetadataController::class, "show"])->name("metadata.show")->middleware("can:" . $permEnvi . "Mostrar");
    Route::get("/metadata/historial/{metadataId}/{tipo?}", [MetadataController::class, "historial"])->name("metadata.historial")->middleware("can:" . $permEnvi . "Mostrar");
    Route::get("/metadata/exportar/{tipo?}", [MetadataController::class, "exportar"])->name("metadata.exportar")->middleware("can:" . $permEnvi . "Exportar");
});

