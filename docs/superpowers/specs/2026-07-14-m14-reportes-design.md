# M14 — Reportes Unificados + Exportación + Programados

> **Fecha:** Julio 2026
> **Alcance:** Full — exportación Excel/PDF server-side, vista unificada, reportes programados con scheduler, arreglo de componentes rotos

---

## 1. Backend

### 1.1 ReportesService (`app/Services/ReportesService.php`)

| Método | Descripción |
|--------|-------------|
| `generarUnificado(modulo, filtros)` | Agrega datos del módulo solicitado: Recibidas, Enviadas, Internas, PQRS, Expedientes, Préstamos, Transferencias. Retorna array con `data`, `total`, `resumen` |
| `exportarExcel(data, nombre, headers)` | Crea PhpSpreadsheet con estilos básicos, auto-size columnas. Retorna stream |
| `exportarPDF(data, nombre, orientacion)` | Renderiza blade template a DomPDF, retorna stream |
| `exportarCSV(data, nombre)` | Stream CSV con headers |

### 1.2 ReportesController (`app/Http/Controllers/ReportesController.php`)

| Endpoint | Método | Descripción |
|----------|--------|-------------|
| `GET /api/reportes/unificado` | `index` | Lista paginada + resumen del módulo seleccionado |
| `GET /api/reportes/export` | `export` | Descarga en Excel/PDF/CSV |
| `GET /api/reportes/programados` | `programados` | Lista programaciones |
| `POST /api/reportes/programados` | `storeProgramado` | Crear programación |
| `PUT /api/reportes/programados/{id}` | `updateProgramado` | Editar programación |
| `DELETE /api/reportes/programados/{id}` | `destroyProgramado` | Eliminar programación |
| `POST /api/reportes/programados/{id}/ejecutar` | `ejecutarProgramado` | Ejecución manual |

### 1.3 Migración `create_reportes_programados_table`

```php
Schema::create('reportes_programados', function (Blueprint $table) {
    $table->id();
    $table->string('modulo');                // recibidas|enviadas|internas|pqrs|expedientes|prestamos|transferencias
    $table->json('filtros');                 // filtros serializados
    $table->string('formato', 10);           // excel|pdf|csv
    $table->string('periodicidad', 20);      // daily|weekly|monthly|quarterly
    $table->string('asunto');
    $table->json('destinatarios');           // array de emails
    $table->timestamp('ultima_ejecucion')->nullable();
    $table->timestamp('proxima_ejecucion');
    $table->boolean('activo')->default(true);
    $table->timestamps();
});
```

### 1.4 Modelo `ReporteProgramado`

- `$casts`: `filtros->array`, `destinatarios->array`, `ultima_ejecucion->datetime`, `proxima_ejecucion->datetime`
- `calcularProximaEjecucion()`: según periodicidad suma días (daily=1, weekly=7, monthly=30, quarterly=90)

### 1.5 Artisan Command `reportes:generar-programados`

```php
// En handle():
$programados = ReporteProgramado::where('proxima_ejecucion', '<=', now())
    ->where('activo', true)
    ->get();

foreach ($programados as $p) {
    try {
        $data = app(ReportesService::class)->generarUnificado($p->modulo, $p->filtros);
        $path = app(ReportesService::class)->exportar($data, "reporte_{$p->modulo}", $p->formato);
        
        Mail::to($p->destinatarios)->send(new ReporteProgramadoMailable($path, $p->asunto));
        
        $p->ultima_ejecucion = now();
        $p->proxima_ejecucion = $p->calcularProximaEjecucion();
        $p->save();
    } catch (\Exception $e) {
        Log::error("Reporte programado {$p->id} falló: {$e->getMessage()}");
    }
}
```

### 1.6 Schedule (`bootstrap/app.php`)

```php
->withSchedule(function (Schedule $schedule) {
    $schedule->command('reportes:generar-programados')->dailyAt('06:00');
})
```

### 1.7 Mailable `ReporteProgramadoMailable`

- Constructor: `$filePath`, `$subject`
- `build()`: attach file, subject from programación

### 1.8 Arreglos: export endpoints existentes

A los controladores existentes se les añade un endpoint `export?format=`:
- `RadicacionRecibidaController` → `export`
- `CorrespondenciaEnviadaController` → `export`
- `PqrsController` → `export`
- `GestionArchivoReportesController` (o el que corresponda) → `export`

Cada uno usa `ReportesService::exportarExcel/PDF/CSV` con los datos del módulo específico.

---

## 2. Frontend

### 2.1 Nuevo módulo `reportes/`

```
src/services/reportes/
  reportesService.js
  ReportesQueries.js
  ReportesMutations.js
  index.js

src/hooks/reportes/
  useReportesHandlers.js
  useReportesFormActions.js
  useReportesListTable.js
  useReportesIndex.js

src/views/reportes/
  ReportesUnificadoView.jsx
  ReportesProgramadosView.jsx
  ReportesClient.jsx
  index.jsx
```

### 2.2 reportesService.js

```js
getReporteUnificado(modulo, filtros)   // GET /api/reportes/unificado
exportReporte(modulo, format, filtros)  // GET /api/reportes/export? responseType: blob
getProgramados()                        // GET /api/reportes/programados
createProgramado(data)                  // POST /api/reportes/programados
updateProgramado(id, data)              // PUT /api/reportes/programados/{id}
deleteProgramado(id)                    // DELETE /api/reportes/programados/{id}
ejecutarProgramado(id)                  // POST /api/reportes/programados/{id}/ejecutar
```

### 2.3 Hooks (patrón estándar)

- **useReportesHandlers**: estado de filtros (módulo, fechas, estado), handlers para generar y exportar
- **useReportesFormActions**: CRUD reportes programados con React Query mutations
- **useReportesListTable**: paginación (1-based, 50 rows)
- **useReportesIndex**: orquestador que combina handlers + query + table

### 2.4 Vistas

**ReportesUnificadoView**:
- Selector de módulo (tabs o dropdown)
- Filtros compartidos: rango fechas, dependencia (si aplica), estado
- Tabla de resultados con paginación
- Botones "Exportar Excel", "Exportar PDF", "Exportar CSV"
- Cards de resumen (totales, pendientes, vencidos)

**ReportesProgramadosView**:
- Tabla de programaciones existentes (módulo, periodicidad, próxima ejecución, activo)
- Modal para crear/editar programación
- Toggle activo/inactivo
- Botón "Ejecutar ahora"

**Ruta**: `/[lang]/reportes` y `/[lang]/reportes/programados`

### 2.5 Sidebar

```jsx
<MenuItem href={`/${locale}/reportes`} icon={<i className='tabler-report' />}>
  Reportes
</MenuItem>
```

### 2.6 Arreglos específicos

1. **handleGenerarReporte** en `useReportesHandlers.js` actual: callback vacío → reemplazar con llamada a `useReporteUnificadoQuery` con los filtros actuales
2. **useReporteQuery** actual: `enabled: false` → cambiar a `enabled: !!filtros` y refetch cuando cambian filtros
3. **Stubs export** en `ReportesRadicadoView` y `ReportesPqrsView`: reemplazar `handleExport` con `useExportReporteMutation`
4. **ReportesList.jsx**: añadir botones Export Excel/PDF que llamen al endpoint unificado

---

## 3. Pruebas

### Backend
- `ReportesServiceTest`: generarUnificado para cada módulo, exportarExcel/PDF/CSV
- `ReportesControllerTest`: endpoints retornan JSON correcto, export descarga blob
- `ReporteProgramadoTest`: CRUD, cálculo próxima ejecución
- `ReportesCommandTest`: ejecuta y verifica email enviado

### Frontend
- Servicios: testear llamadas HTTP con mocks
- Hooks: testear estados loading/error/data con React Query provider mock

---

## 4. Roadmap

- `roadmap-gap-analysis.md`: M14 → ✅ COMPLETO, Brecha #7 → ✅
- Fase 5 items 5.1, 5.2, 5.5 → ✅
- Checklist normativo Circular 004 DAFP 2015 y Decreto 1080/2015 → ✅
