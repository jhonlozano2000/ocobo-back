<?php

use App\Http\Controllers\VentanillaUnica\Pqrs\VentanillaPqrsArchivosController;
use App\Http\Controllers\VentanillaUnica\Pqrs\VentanillaPqrsController;
use App\Http\Controllers\VentanillaUnica\Pqrs\VentanillaPqrsResponsableController;
use App\Http\Controllers\VentanillaUnica\Pqrs\VentanillaPqrsPaseHistorialController;
use App\Http\Controllers\VentanillaUnica\Pqrs\VentanillaPqrsCompartirHistorialController;
use App\Http\Controllers\VentanillaUnica\Pqrs\VentanillaPqrsComentariosController;
use Illuminate\Support\Facades\Route;

/**
 * Rutas del módulo PQRS (Peticiones, Quejas, Reclamos, Sugerencias) - Ventanilla Única
 *
 * Define todos los endpoints RESTful para la gestión completa de radicados PQRS:
 * - CRUD principal, estados, vencimientos, firmas digitales
 * - Responsables (asignación, custodia, acuse digital)
 * - Historial de pases/reasignaciones
 * - Historial de compartidos (CC)
 * - Comentarios threaded con respuestas
 * - Archivos (digital y adjuntos)
 *
 * Middleware global: auth:sanctum (autenticación via Laravel Sanctum)
 * Rate limiting: throttle:radicacion (escritura) / throttle:api (lectura)
 *
 * @author Jhon Javer Lozano Arce
 * @date 2026-08-20
 */

// ═══════════════════════════════════════════════════════════════
// RUTAS PRINCIPALES PQRS (Escritura - throttle:radicacion)
// ═══════════════════════════════════════════════════════════════

Route::middleware(['auth:sanctum', 'throttle:radicacion'])->group(function () {
    /**
     * POST /api/pqrs
     * Crea un nuevo radicado PQRS (desde radicado recibido o directo)
     * @name pqrs.store
     */
    Route::post('/pqrs', [VentanillaPqrsController::class, 'store'])->name('pqrs.store');

    /**
     * DELETE /api/pqrs
     * Eliminación masiva (bulk) de PQRS por IDs
     * @name pqrs.bulk-destroy
     */
    Route::delete('/pqrs', [VentanillaPqrsController::class, 'bulkDestroy'])->name('pqrs.bulk-destroy');
});

// ═══════════════════════════════════════════════════════════════
// RUTAS PQRS - LECTURA (throttle:api)
// ═══════════════════════════════════════════════════════════════

Route::middleware('auth:sanctum')->group(function () {
    Route::middleware('throttle:api')->group(function () {
        /**
         * GET /api/pqrs
         * Lista paginada de PQRS con filtros, búsqueda y ABAC
         * @name pqrs.index
         */
        Route::get('/pqrs', [VentanillaPqrsController::class, 'index'])->name('pqrs.index');

        /**
         * GET /api/pqrs/export
         * Exporta listado de PQRS a Excel/CSV
         * @name pqrs.export
         */
        Route::get('/pqrs/export', [VentanillaPqrsController::class, 'export'])->name('pqrs.export');

        /**
         * GET /api/pqrs/estadisticas
         * Estadísticas agregadas: totales por estado, prioridad, vencimientos
         * @name pqrs.estadisticas
         */
        Route::get('/pqrs/estadisticas', [VentanillaPqrsController::class, 'estadisticas'])->name('pqrs.estadisticas');

        /**
         * GET /api/pqrs/{id}/linea-tiempo
         * Línea de tiempo completa del PQRS (eventos, pases, comentarios, etc.)
         * @name pqrs.linea-tiempo
         */
        Route::get('/pqrs/{id}/linea-tiempo', [VentanillaPqrsController::class, 'lineaTiempo'])->name('pqrs.linea-tiempo');

        /**
         * GET /api/pqrs/{id}
         * Detalle completo de un PQRS con todas sus relaciones
         * @name pqrs.show
         */
        Route::get('/pqrs/{id}', [VentanillaPqrsController::class, 'show'])->name('pqrs.show');

        /**
         * GET /api/pqrs/mis-radicados
         * PQRS asignados al usuario autenticado (vía responsables)
         * @name pqrs.mis-radicados
         */
        Route::get('/pqrs/mis-radicados', [VentanillaPqrsController::class, 'misRadicados'])->name('pqrs.mis-radicados');

        /**
         * GET /api/pqrs/estados
         * Catálogo de estados de trámite disponibles
         * @name pqrs.estados
         */
        Route::get('/pqrs/estados', [VentanillaPqrsController::class, 'estadosDisponibles'])->name('pqrs.estados');

        /**
         * GET /api/pqrs/estados/{estadoId}/transiciones
         * Transiciones válidas desde un estado (machine state)
         * @name pqrs.transiciones-estado
         */
        Route::get('/pqrs/estados/{estadoId}/transiciones', [VentanillaPqrsController::class, 'transicionesEstado'])->name('pqrs.transiciones-estado');

        /**
         * GET /api/pqrs/{id}/historial-notificaciones
         * Historial de emails/notificaciones enviadas para este PQRS
         * @name pqrs.historial-notificaciones
         */
        Route::get('/pqrs/{id}/historial-notificaciones', [VentanillaPqrsController::class, 'historialNotificaciones'])->name('pqrs.historial-notificaciones');

        /**
         * GET /api/pqrs/{id}/historial-clasificacion
         * Historial de cambios de clasificación documental
         * @name pqrs.historial-clasificacion
         */
        Route::get('/pqrs/{id}/historial-clasificacion', [VentanillaPqrsController::class, 'historialClasificacion'])->name('pqrs.historial-clasificacion');
    });

    // ═══════════════════════════════════════════════════════════════
    // RUTAS PQRS - ESCRITURA (throttle:radicacion)
    // ═══════════════════════════════════════════════════════════════

    Route::middleware('throttle:radicacion')->group(function () {
        /**
         * PUT /api/pqrs/{id}
         * Actualización general del PQRS
         * @name pqrs.update
         */
        Route::put('/pqrs/{id}', [VentanillaPqrsController::class, 'update'])->name('pqrs.update');

        /**
         * DELETE /api/pqrs/{id}
         * Eliminación individual (soft delete)
         * @name pqrs.destroy
         */
        Route::delete('/pqrs/{id}', [VentanillaPqrsController::class, 'destroy'])->name('pqrs.destroy');

        /**
         * PUT /api/pqrs/{id}/estado
         * Cambio de estado de trámite (con validación de transiciones)
         * @name pqrs.cambiar-estado
         */
        Route::put('/pqrs/{id}/estado', [VentanillaPqrsController::class, 'cambiarEstado'])->name('pqrs.cambiar-estado');

        /**
         * POST /api/pqrs/{id}/prorroga
         * Aplica prórroga automática (duplica plazo legal)
         * @name pqrs.prorroga
         */
        Route::post('/pqrs/{id}/prorroga', [VentanillaPqrsController::class, 'aplicarProrroga'])->name('pqrs.prorroga');

        /**
         * PUT /api/pqrs/{id}/asunto
         * Actualiza solo el asunto del PQRS
         * @name pqrs.update-asunto
         */
        Route::put('/pqrs/{id}/asunto', [VentanillaPqrsController::class, 'updateAsunto'])->name('pqrs.update-asunto');

        /**
         * PUT /api/pqrs/{id}/fechas
         * Actualiza fechas (vencimiento, respuesta, etc.)
         * @name pqrs.update-fechas
         */
        Route::put('/pqrs/{id}/fechas', [VentanillaPqrsController::class, 'updateFechas'])->name('pqrs.update-fechas');

        /**
         * PUT /api/pqrs/{id}/clasificacion
         * Cambia clasificación documental (registra en historial)
         * @name pqrs.update-clasificacion
         */
        Route::put('/pqrs/{id}/clasificacion', [VentanillaPqrsController::class, 'updateClasificacion'])->name('pqrs.update-clasificacion');

        /**
         * GET /api/pqrs/{id}/rotulo
         * Genera e imprime rótulo/caratula del PQRS
         * @name pqrs.imprimir-rotulo
         */
        Route::get('/pqrs/{id}/rotulo', [VentanillaPqrsController::class, 'imprimirRotulo'])->name('pqrs.imprimir-rotulo');

        /**
         * POST /api/pqrs/{id}/notificar-email
         * Envía notificación por email (registra en historial)
         * @name pqrs.notificar-email
         */
        Route::post('/pqrs/{id}/notificar-email', [VentanillaPqrsController::class, 'notificarEmail'])->name('pqrs.notificar-email');

        /**
         * POST /api/pqrs/{id}/solicitar-otp-firma
         * Inicia proceso de firma digital (envía OTP)
         * @name pqrs.solicitar-otp-firma
         */
        Route::post('/pqrs/{id}/solicitar-otp-firma', [VentanillaPqrsController::class, 'solicitarOtpFirma'])->name('pqrs.solicitar-otp-firma');

        /**
         * POST /api/pqrs/{id}/validar-otp-firma
         * Valida OTP y completa firma digital
         * @name pqrs.validar-otp-firma
         */
        Route::post('/pqrs/{id}/validar-otp-firma', [VentanillaPqrsController::class, 'validarOtpFirma'])->name('pqrs.validar-otp-firma');

        /**
         * POST /api/pqrs/{id}/guardar-firma
         * Guarda firma digital completada
         * @name pqrs.guardar-firma
         */
        Route::post('/pqrs/{id}/guardar-firma', [VentanillaPqrsController::class, 'guardarFirma'])->name('pqrs.guardar-firma');

        /**
         * POST /api/pqrs/{id}/anular
         * Anula el PQRS (cambia estado, registra motivo)
         * @name pqrs.anular
         */
        Route::post('/pqrs/{id}/anular', [VentanillaPqrsController::class, 'anular'])->name('pqrs.anular');

        /**
         * GET /api/pqrs/pendientes-firma
         * Lista PQRS pendientes de firma digital
         * @name pqrs.pendientes-firma
         */
        Route::get('/pqrs/pendientes-firma', [VentanillaPqrsController::class, 'pendientesFirma'])->name('pqrs.pendientes-firma');
    });

    // ═══════════════════════════════════════════════════════════════
    // RESPONSABLES PQRS - LECTURA/GESTIÓN (throttle:api)
    // ═══════════════════════════════════════════════════════════════

    Route::middleware('throttle:api')->group(function () {
        /**
         * API Resource: pqrs-responsables
         * GET    /api/pqrs-responsables           → index (lista con filtros)
         * POST   /api/pqrs-responsables           → store (asignación batch multi-PQRS)
         * GET    /api/pqrs-responsables/{id}      → show
         * PUT    /api/pqrs-responsables/{id}      → update (cambiar custodio)
         * DELETE /api/pqrs-responsables/{id}      → destroy
         */
        Route::apiResource('pqrs-responsables', VentanillaPqrsResponsableController::class)
            ->only(['index', 'store', 'show', 'update', 'destroy']);

        /**
         * GET /api/pqrs/{pqrs_id}/responsables
         * Lista los responsables asignados a un PQRS específico
         * @name pqrs.responsables.listar
         */
        Route::get('/pqrs/{pqrs_id}/responsables', [VentanillaPqrsResponsableController::class, 'getByPqrs'])
            ->name('pqrs.responsables.listar');

        /**
         * POST /api/pqrs-responsables/{id}/marcar-visto
         * Registra acuse digital (fecha/hora de visualización)
         * @name pqrs.responsables.marcar-visto
         */
        Route::post('/pqrs-responsables/{id}/marcar-visto', [VentanillaPqrsResponsableController::class, 'marcarVisto'])
            ->name('pqrs.responsables.marcar-visto');

        /**
         * Historiales y comentarios - LECTURA (throttle:api)
         *
         * Nota: comentarios show usa ruta plana /api/pqrs/comentarios/{id}
         * para evitar la colisión de parámetros {pqrs_id}/{id} en el binding posicional.
         */
        Route::prefix('pqrs/{pqrs_id}')->group(function () {
            Route::get('pase', [VentanillaPqrsPaseHistorialController::class, 'byPqrs'])
                ->name('pqrs.pase.index');
            Route::get('compartir', [VentanillaPqrsCompartirHistorialController::class, 'byPqrs'])
                ->name('pqrs.compartir.index');
            Route::get('comentarios', [VentanillaPqrsComentariosController::class, 'index'])
                ->name('pqrs.comentarios.index');
        });
        Route::get('pqrs/comentarios/{id}', [VentanillaPqrsComentariosController::class, 'show'])
            ->name('pqrs.comentarios.show');
    });

    // ═══════════════════════════════════════════════════════════════
    // PQRS - ESCRITURA ANIDADA (throttle:radicacion)
    // Pases, compartidos (CC), comentarios y asignación de responsables
    // ═══════════════════════════════════════════════════════════════

    Route::middleware('throttle:radicacion')->group(function () {
        /**
         * POST /api/pqrs/{pqrs_id}/responsables
         * Asigna responsables a un PQRS específico (endpoint alternativo)
         * @name pqrs.responsables.assign
         */
        Route::post('/pqrs/{pqrs_id}/responsables', [VentanillaPqrsResponsableController::class, 'assignToPqrs'])
            ->name('pqrs.responsables.assign');

        /**
         * POST /api/pqrs/{pqrs_id}/pase          → registra pase/reasignación
         * POST /api/pqrs/{pqrs_id}/compartir     → comparte PQRS (CC)
         * POST /api/pqrs/{pqrs_id}/comentarios   → crea comentario/respuesta
         */
        Route::prefix('pqrs/{pqrs_id}')->group(function () {
            Route::post('pase', [VentanillaPqrsPaseHistorialController::class, 'store'])
                ->name('pqrs.pase.store');
            Route::post('compartir', [VentanillaPqrsCompartirHistorialController::class, 'store'])
                ->name('pqrs.compartir.store');
            Route::post('comentarios', [VentanillaPqrsComentariosController::class, 'store'])
                ->name('pqrs.comentarios.store');
        });

        /**
         * Comentarios PQRS - escritura sobre rutas planas:
         * PUT    /api/pqrs/comentarios/{id}             → update (solo autor, no resuelto)
         * POST   /api/pqrs/comentarios/{id}/resolver    → resolver (marcar resuelto)
         * DELETE /api/pqrs/comentarios/{id}             → destroy (solo autor, sin respuestas)
         */
        Route::put('pqrs/comentarios/{id}', [VentanillaPqrsComentariosController::class, 'update'])
            ->name('pqrs.comentarios.update');
        Route::post('pqrs/comentarios/{id}/resolver', [VentanillaPqrsComentariosController::class, 'resolver'])
            ->name('pqrs.comentarios.resolver');
        Route::delete('pqrs/comentarios/{id}', [VentanillaPqrsComentariosController::class, 'destroy'])
            ->name('pqrs.comentarios.destroy');
    });

    // ═══════════════════════════════════════════════════════════════
    // ARCHIVOS PQRS - SUBIDAS (throttle:uploads, patrón Recibidos)
    // ═══════════════════════════════════════════════════════════════

    Route::middleware('throttle:uploads')->group(function () {
        Route::prefix('pqrs/{id}')->group(function () {
            Route::post('archivos/digital/upload', [VentanillaPqrsArchivosController::class, 'subirDigital'])
                ->name('pqrs.archivos.digital.upload');
            Route::post('archivos/adjuntos/upload', [VentanillaPqrsArchivosController::class, 'subirAdjuntos'])
                ->name('pqrs.archivos.adjuntos.upload');
        });
    });

    // ═══════════════════════════════════════════════════════════════
    // ARCHIVOS PQRS - CONSULTA Y ELIMINACIÓN (throttle:api)
    // ═══════════════════════════════════════════════════════════════

    Route::middleware('throttle:api')->group(function () {
        Route::prefix('pqrs/{id}')->group(function () {
            Route::get('archivos', [VentanillaPqrsArchivosController::class, 'listar'])
                ->name('pqrs.archivos.listar');
            Route::get('archivos/digital/descargar', [VentanillaPqrsArchivosController::class, 'descargarDigital'])
                ->name('pqrs.archivos.digital.descargar');
            Route::delete('archivos/digital/eliminar', [VentanillaPqrsArchivosController::class, 'eliminarDigital'])
                ->name('pqrs.archivos.digital.eliminar');
            Route::get('archivos/{archivoId}/descargar', [VentanillaPqrsArchivosController::class, 'descargarAdjunto'])
                ->name('pqrs.archivos.adjuntos.descargar');
            Route::delete('archivos/{archivoId}/eliminar', [VentanillaPqrsArchivosController::class, 'eliminarAdjunto'])
                ->name('pqrs.archivos.adjuntos.eliminar');
        });
    });
});
