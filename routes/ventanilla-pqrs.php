<?php

use App\Http\Controllers\VentanillaUnica\Pqrs\VentanillaPqrsController;
use Illuminate\Support\Facades\Route;

/**
 * Rutas del módulo PQRS (Peticiones, Quejas, Reclamos, Sugerencias) - Ventanilla Única
 *
 * Define todos los endpoints RESTful para la gestión completa de radicados PQRS:
 * - CRUD principal, estados, vencimientos, firmas digitales
 * - Responsables (asignación, custodia, acuse digital)
 * - Historial de pases/reasignaciones
 * - Historial de compartidos (CC)
 *
 * Comentarios y archivos se gestionan sobre el radicado recibido asociado
 * vía /api/radica-recibida/* (fuente única de esos datos).
 *
 * Middleware global: auth:sanctum (autenticación via Laravel Sanctum)
 * Rate limiting: throttle:radicacion (escritura) / throttle:api (lectura)
 *
 * @author Jhon Javer Lozano Arce
 *
 * @date 2026-08-20
 */

// ═══════════════════════════════════════════════════════════════
// RUTAS PRINCIPALES PQRS (Escritura - throttle:radicacion)
// ═══════════════════════════════════════════════════════════════

Route::middleware(['auth:sanctum', 'throttle:radicacion'])->group(function () {
    /**
     * POST /api/pqrs
     * Crea un nuevo radicado PQRS (desde radicado recibido o directo)
     *
     * @name pqrs.store
     */
    Route::post('/pqrs', [VentanillaPqrsController::class, 'store'])->name('pqrs.store');

    /**
     * DELETE /api/pqrs
     * Eliminación masiva (bulk) de PQRS por IDs
     *
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
         *
         * @name pqrs.index
         */
        Route::get('/pqrs', [VentanillaPqrsController::class, 'index'])->name('pqrs.index');

        /**
         * GET /api/pqrs-catalogos
         * Catálogos del formulario PQRS por NOMBRE de lista (Tipos, Prioridad,
         * Modalidad, Medios Recepción, Tipos Solicitud). URI fuera de /pqrs/{id}
         * para evitar colisión con pqrs.show.
         */
        Route::get('/pqrs-catalogos', [VentanillaPqrsController::class, 'catalogos'])->name('pqrs.catalogos');

        /**
         * GET /api/pqrs/export
         * Exporta listado de PQRS a Excel/CSV
         *
         * @name pqrs.export
         */
        Route::get('/pqrs/export', [VentanillaPqrsController::class, 'export'])->name('pqrs.export');

        /**
         * GET /api/pqrs/estadisticas
         * Estadísticas agregadas: totales por estado, prioridad, vencimientos
         *
         * @name pqrs.estadisticas
         */
        Route::get('/pqrs/estadisticas', [VentanillaPqrsController::class, 'estadisticas'])->name('pqrs.estadisticas');

        /**
         * GET /api/pqrs/{id}/linea-tiempo
         * Línea de tiempo completa del PQRS (eventos, pases, comentarios, etc.)
         *
         * @name pqrs.linea-tiempo
         */
        Route::get('/pqrs/{id}/linea-tiempo', [VentanillaPqrsController::class, 'lineaTiempo'])->name('pqrs.linea-tiempo');

        /**
         * GET /api/pqrs/{id}
         * Detalle completo de un PQRS con todas sus relaciones
         *
         * @name pqrs.show
         */
        Route::get('/pqrs/{id}', [VentanillaPqrsController::class, 'show'])->name('pqrs.show');

        /**
         * GET /api/pqrs/mis-radicados
         * PQRS asignados al usuario autenticado (vía responsables)
         *
         * @name pqrs.mis-radicados
         */
        Route::get('/pqrs/mis-radicados', [VentanillaPqrsController::class, 'misRadicados'])->name('pqrs.mis-radicados');

        /**
         * GET /api/pqrs/estados
         * Catálogo de estados de trámite disponibles
         *
         * @name pqrs.estados
         */
        Route::get('/pqrs/estados', [VentanillaPqrsController::class, 'estadosDisponibles'])->name('pqrs.estados');

        /**
         * GET /api/pqrs/estados/{estadoId}/transiciones
         * Transiciones válidas desde un estado (machine state)
         *
         * @name pqrs.transiciones-estado
         */
        Route::get('/pqrs/estados/{estadoId}/transiciones', [VentanillaPqrsController::class, 'transicionesEstado'])->name('pqrs.transiciones-estado');

        /**
         * GET /api/pqrs/{id}/historial-notificaciones
         * Historial de emails/notificaciones enviadas para este PQRS
         *
         * @name pqrs.historial-notificaciones
         */
        Route::get('/pqrs/{id}/historial-notificaciones', [VentanillaPqrsController::class, 'historialNotificaciones'])->name('pqrs.historial-notificaciones');

        /**
         * GET /api/pqrs/{id}/historial-clasificacion
         * Historial de cambios de clasificación documental
         *
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
         *
         * @name pqrs.update
         */
        Route::put('/pqrs/{id}', [VentanillaPqrsController::class, 'update'])->name('pqrs.update');

        /**
         * DELETE /api/pqrs/{id}
         * Eliminación individual (soft delete)
         *
         * @name pqrs.destroy
         */
        Route::delete('/pqrs/{id}', [VentanillaPqrsController::class, 'destroy'])->name('pqrs.destroy');

        /**
         * PUT /api/pqrs/{id}/estado
         * Cambio de estado de trámite (con validación de transiciones)
         *
         * @name pqrs.cambiar-estado
         */
        Route::put('/pqrs/{id}/estado', [VentanillaPqrsController::class, 'cambiarEstado'])->name('pqrs.cambiar-estado');

        /**
         * POST /api/pqrs/{id}/prorroga
         * Aplica prórroga automática (duplica plazo legal)
         *
         * @name pqrs.prorroga
         */
        Route::post('/pqrs/{id}/prorroga', [VentanillaPqrsController::class, 'aplicarProrroga'])->name('pqrs.prorroga');

        /**
         * PUT /api/pqrs/{id}/asunto
         * Actualiza solo el asunto del PQRS
         *
         * @name pqrs.update-asunto
         */
        Route::put('/pqrs/{id}/asunto', [VentanillaPqrsController::class, 'updateAsunto'])->name('pqrs.update-asunto');

        /**
         * PUT /api/pqrs/{id}/fechas
         * Actualiza fechas (vencimiento, respuesta, etc.)
         *
         * @name pqrs.update-fechas
         */
        Route::put('/pqrs/{id}/fechas', [VentanillaPqrsController::class, 'updateFechas'])->name('pqrs.update-fechas');

        /**
         * PUT /api/pqrs/{id}/clasificacion
         * Cambia clasificación documental (registra en historial)
         *
         * @name pqrs.update-clasificacion
         */
        Route::put('/pqrs/{id}/clasificacion', [VentanillaPqrsController::class, 'updateClasificacion'])->name('pqrs.update-clasificacion');

        /**
         * GET /api/pqrs/{id}/rotulo
         * Genera e imprime rótulo/caratula del PQRS
         *
         * @name pqrs.imprimir-rotulo
         */
        Route::get('/pqrs/{id}/rotulo', [VentanillaPqrsController::class, 'imprimirRotulo'])->name('pqrs.imprimir-rotulo');

        /**
         * POST /api/pqrs/{id}/notificar-email
         * Envía notificación por email (registra en historial)
         *
         * @name pqrs.notificar-email
         */
        Route::post('/pqrs/{id}/notificar-email', [VentanillaPqrsController::class, 'notificarEmail'])->name('pqrs.notificar-email');

        /**
         * POST /api/pqrs/{id}/solicitar-otp-firma
         * Inicia proceso de firma digital (envía OTP)
         *
         * @name pqrs.solicitar-otp-firma
         */
        Route::post('/pqrs/{id}/solicitar-otp-firma', [VentanillaPqrsController::class, 'solicitarOtpFirma'])->name('pqrs.solicitar-otp-firma');

        /**
         * POST /api/pqrs/{id}/validar-otp-firma
         * Valida OTP y completa firma digital
         *
         * @name pqrs.validar-otp-firma
         */
        Route::post('/pqrs/{id}/validar-otp-firma', [VentanillaPqrsController::class, 'validarOtpFirma'])->name('pqrs.validar-otp-firma');

        /**
         * POST /api/pqrs/{id}/guardar-firma
         * Guarda firma digital completada
         *
         * @name pqrs.guardar-firma
         */
        Route::post('/pqrs/{id}/guardar-firma', [VentanillaPqrsController::class, 'guardarFirma'])->name('pqrs.guardar-firma');

        /**
         * POST /api/pqrs/{id}/solicitar-anulacion
         * Registra la solicitud de anuracion de un PQRS
         *
         * @name pqrs.solicitar-anulacion
         */
        Route::post('/pqrs/{id}/solicitar-anulacion', [VentanillaPqrsController::class, 'solicitarAnulacion'])->name('pqrs.solicitar-anulacion');

        /**
         * GET /api/pqrs/pendientes-anulacion
         * Lista PQRS con solicitud de anulación pendiente
         *
         * @name pqrs.pendientes-anulacion
         */
        Route::get('/pqrs/pendientes-anulacion', [VentanillaPqrsController::class, 'listarPendientesAnulacion'])->name('pqrs.pendientes-anulacion')->middleware('can:Jefe de Archivo');

        /**
         * POST /api/pqrs/{id}/procesar-anulacion
         * Aprueba o rechaza la anulación de un PQRS
         *
         * @name pqrs.procesar-anulacion
         */
        Route::post('/pqrs/{id}/procesar-anulacion', [VentanillaPqrsController::class, 'procesarAnulacion'])->name('pqrs.procesar-anulacion')->middleware('can:Jefe de Archivo');

        /**
         * GET /api/pqrs/pendientes-firma
         * Lista PQRS pendientes de firma digital
         *
         * @name pqrs.pendientes-firma
         */
        Route::get('/pqrs/pendientes-firma', [VentanillaPqrsController::class, 'pendientesFirma'])->name('pqrs.pendientes-firma');
    });

    // ═══════════════════════════════════════════════════════════════
    // NOTA: Responsables/Pases/Compartidos NO existen en PQRS.
    // Viven en el RADICADO RECIBIDO asociado (flujo: radicado → PQRS).
    // ═══════════════════════════════════════════════════════════════

    // ═══════════════════════════════════════════════════════════════
    // NOTA: Comentarios y archivos de PQRS viven en el radicado recibido
    // (ventanilla_radica_reci_*). Se gestionan vía /api/radica-recibida/*.
    // ═══════════════════════════════════════════════════════════════
});
