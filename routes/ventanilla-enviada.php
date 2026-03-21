<?php

use App\Http\Controllers\VentanillaUnica\VentanillaRadicaEnviadosArchivosController;
use App\Http\Controllers\VentanillaUnica\VentanillaRadicaEnviadosController;
use App\Http\Controllers\VentanillaUnica\VentanillaRadicaEnviadosFirmantesController;
use App\Http\Controllers\VentanillaUnica\VentanillaRadicaEnviadosProyectoresController;
use App\Http\Controllers\VentanillaUnica\VentanillaRadicaEnviadosResponsaController;
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

    Route::prefix("radica-enviada/{id}")->name("radica-enviada.")->group(function () use ($permEnvi) {
        Route::get("/archivos/adjuntos/listar", [VentanillaRadicaEnviadosArchivosController::class, "listarArchivosAdjuntos"])->name("archivos.adjuntos.listar")->middleware("can:" . $permEnvi . "Mostrar");
        Route::get("/archivos/historial/archivos-eliminados", [VentanillaRadicaEnviadosArchivosController::class, "historialEliminaciones"])->name("archivos.historial.eliminaciones")->middleware("can:" . $permEnvi . "Mostrar");  
        Route::get("/archivos/adjuntos/{archivoId}/descargar", [VentanillaRadicaEnviadosArchivosController::class, "descargarArchivoAdjunto"])->name("archivos.adjuntos.descargar")->middleware("can:" . $permEnvi . "Mostrar");     
        Route::delete("/archivos/adjuntos/{archivoId}/eliminar", [VentanillaRadicaEnviadosArchivosController::class, "eliminarArchivoAdjunto"])->name("archivos.adjuntos.eliminar")->middleware("can:" . $permEnvi . "Eliminar adjuntos");
        Route::get("/archivos/info/", [VentanillaRadicaEnviadosArchivosController::class, "getFileInfo"])->name("archivos.info")->middleware("can:" . $permEnvi . "Mostrar");
        Route::post("/archivos/upload-digital", [VentanillaRadicaEnviadosArchivosController::class, "upload"])->name("archivos.upload.digital")->middleware("can:" . $permEnvi . "Subir digital");
        Route::post("/archivos/upload-adjuntos", [VentanillaRadicaEnviadosArchivosController::class, "subirArchivosAdjuntos"])->name("archivos.upload-adjuntos")->middleware("can:" . $permEnvi . "Subir adjuntos");
        Route::get("/archivos/download", [VentanillaRadicaEnviadosArchivosController::class, "download"])->name("archivos.download")->middleware("can:" . $permEnvi . "Mostrar");
        Route::delete("/archivos/delete", [VentanillaRadicaEnviadosArchivosController::class, "deleteFile"])->name("archivos.delete")->middleware("can:" . $permEnvi . "Eliminar digital");
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
});

