# M15 — Dashboard Global OCOBO

> **Fecha:** 2026-07-15
> **Basado en:** Roadmap gap #8, diseño aprobado por usuario

## Objetivo

Crear un dashboard global que consolide todas las métricas del sistema OCOBO en una sola vista, usando el mismo estilo visual de los dashboards Vuexy (CRM/Analytics) existentes en el frontend.

## Arquitectura

### Backend

**Nuevo `DashboardGlobalController`** — endpoint único `GET /api/dashboard/global` que agrega estadísticas de TODOS los módulos:

| Módulo | Fuente | Cache |
|--------|--------|-------|
| Radicados Recibidos | `VentanillaRadicaReciController::estadisticas()` | Redis 5min |
| Radicados Enviados | `VentanillaRadicaEnviadosController::estadisticas()` | Redis 5min |
| Correspondencia Interna | `VentanillaRadicaInternoController::estadisticas()` | Redis 5min |
| PQRS | `VentanillaPqrsController::estadisticas()` | Redis 5min |
| Archivo — Expedientes | `ReportesService::estadisticasGenerales()` | Redis 5min |
| Archivo — Préstamos | `ReportesService::estadisticasGenerales()` | Redis 5min |
| Archivo — Transferencias | `ReportesService::estadisticasGenerales()` | Redis 5min |
| Usuarios | `UserService::statistics()` | Redis 5min |
| Firmas (nuevo) | `FirmaEvento::whereDate('created_at', ...)` | Redis 5min |
| Vencimientos próximos (nuevo) | Consulta cross-module | Sin cache |

**Métricas nuevas a implementar:**
- `firmas_hoy`, `firmas_semana`, `firmas_mes` — desde `FirmaEvento`
- `vencimientos_proximos` — próximos 7 días (radicados con fecha_vencimiento, PQRS con fecha_vencimiento, préstamos vencidos)

### Frontend

**Ruta:** `/[lang]/dashboard` — nueva ruta, usa el layout `(dashboard)/(private)` existente.

**Componentes (patrón Vuexy CRM/Analytics):**

1. **KpiRow** — fila superior con 8+ `CardStatsWithAreaChart` (reutilizado de `src/components/card-statistics/StatsWithAreaChart.jsx`):
   - Radicados Hoy, PQRS Urgentes, Expedientes Abiertos, Préstamos Activos, Transferencias Pendientes, Usuarios Activos, Firmas Hoy
   - Cada card con sparkline área mostrando tendencia 6 meses

2. **SemaphoreGrid** — grilla de tarjetas por módulo con semáforo (verde/amarillo/rojo):
   - Recibidas, Enviadas, Internas, PQRS, Archivo, Usuarios
   - Cada card muestra: nombre módulo, indicador semáforo, KPIs clave del módulo
   - Click expande a detalle (tabla o mini dashboard del módulo)

3. **VencimientosProximos** — tabla con los próximos vencimientos (7 días):
   - Tipo (Radicado/PQRS/Préstamo), Descripción, Fecha, Días restantes, Acción

4. **ActividadReciente** — timeline de actividad reciente (del existente en gestion-archivo, pero global)

**Librerías:** MUI Grid2, ApexCharts (react-apexcharts), next/dynamic para componentes pesados

## Data Flow

```
page.jsx (Server Component)
  → getDictionary(params.lang)
  → Suspense → DashboardGlobalClient
    → useDashboardGlobalHandlers (hook)
      → useDashboardGlobalQuery (React Query, staleTime 5min)
        → fetchDashboardGlobalStats() (axiosClient /api/dashboard/global)
          → DashboardGlobalController@index
            → Cache::remember('dashboard_global_stats', 300, fn() => ...)
              → Reutiliza servicios existentes + nuevas queries
```

## Archivos a Modificar/Crear

### Backend (ocobo-back)
| Archivo | Acción |
|---------|--------|
| `app/Http/Controllers/DashboardGlobalController.php` | Crear |
| `routes/dashboard.php` | Crear |
| `app/Providers/RouteServiceProvider.php` | Modificar — registrar ruta |
| `app/Models/Transversal/FirmaEvento.php` | Verificar — necesario para firmas |

### Frontend (ocobo-frond)
| Archivo | Acción |
|---------|--------|
| `src/services/dashboard/dashboardGlobalService.js` | Crear |
| `src/services/dashboard/DashboardGlobalQueries.js` | Crear |
| `src/services/dashboard/index.js` | Crear |
| `src/hooks/dashboard/useDashboardGlobalHandlers.js` | Crear |
| `src/hooks/dashboard/index.js` | Crear |
| `src/views/dashboard/DashboardGlobalView.jsx` | Crear — layout + KpiRow + SemaphoreGrid |
| `src/views/dashboard/KpiCard.jsx` | Crear — wrapper sobre CardStatsWithAreaChart |
| `src/views/dashboard/SemaphoreCard.jsx` | Crear — card módulo con semáforo |
| `src/views/dashboard/VencimientosTable.jsx` | Crear |
| `src/views/dashboard/DashboardGlobalClient.jsx` | Crear — bridge |
| `src/app/[lang]/(dashboard)/(private)/dashboards/global/page.jsx` | Crear |
| `src/app/[lang]/(dashboard)/(private)/dashboards/global/DashboardClient.jsx` | Crear |
| `src/components/layout/vertical/VerticalMenu.jsx` | Modificar — sidebar entry |
| `src/data/dictionaries/es.json` | Modificar — navigation_dashboard |
| `src/data/dictionaries/en.json` | Modificar — navigation_dashboard |

## Testing
- Backend: `tests/Feature/M15/DashboardGlobalTest.php` — auth, estructura respuesta, cache
- Frontend: seguir patrón existente (sin tests aún en el proyecto)

## Checklist Cumplimiento Normativo
- Circular 004 DAFP 2015 (reportes exportables) — cubierto por M14 ✅
- Decreto 1080/2015 (reportes PINAR) — dashboard global muestra KPIs archivísticos
