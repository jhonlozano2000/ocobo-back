# M13 — Módulo Frontend Firma Electrónica

> **Fecha:** 2026-07-14
> **Contexto:** OCOBO roadmap brecha #6 — M13 no tiene vista/ruta/sidebar propio

---

## Objetivo

Crear un módulo frontend dedicado para Firma Electrónica (M13) con una vista centralizada que liste todos los documentos firmados por el usuario actual, permitiendo filtrado por tipo documental y verificación de integridad.

---

## Backend

### Nuevo endpoint

```
GET /api/transversal/firma-eventos/mis-firmas
```

**Middleware:** `auth:sanctum`

**Query params (opcionales):**
- `tipo` — filtrar por `reci`, `enviados`, `interno`, `pqrs`
- `desde` — fecha inicio (Y-m-d)
- `hasta` — fecha fin (Y-m-d)

**Controller:** `FirmaEventosController@misFirmas`

**Lógica:**
```php
FirmaEvento::where('user_id', auth()->id())
    ->when($tipo, fn($q) => $q->where('documentable_type', $map[$tipo]))
    ->when($desde, fn($q) => $q->whereDate('fecha_firma', '>=', $desde))
    ->when($hasta, fn($q) => $q->whereDate('fecha_firma', '<=', $hasta))
    ->latest('fecha_firma')
    ->paginate(50)
```

**Resource:** Reusa el mapeo existente de `documentable_type` a `reci/enviados/interno/pqrs`. Incluye `documentable_type`, `documentable_id`, `fecha_firma`, `hash_original`, `hash_firmado`, `integro` (bool), `user.name`.

**Route:** `routes/transversal.php` — dentro del grupo `firma-eventos`.

---

## Frontend

### Estructura

```
src/views/firma-electronica/
  index.jsx                        ← Page component (client)
  FirmaElectronicaClient.jsx       ← Layout: filters + table
  FirmaElectronicaTable.jsx        ← Data table

src/hooks/firma-electronica/
  useFirmaElectronicaIndex.js      ← Main hook (queries + handlers + state)
  useFirmaElectronicaListTable.js  ← Table state/UI

src/services/firma-electronica/
  firmaElectronicaService.js       ← HTTP functions
  FirmaElectronicaQueries.js       ← useQuery
  index.js                         ← Exports
```

### Ruta

`/[lang]/firma-electronica/` bajo el layout `(mi-bandeja)`.

Registro en `src/app/[lang]/(mi-bandeja)/firma-electronica/page.js`.

### Sidebar

Entrada en `VerticalMenu.jsx` sección M13 — Firma Electrónica.

### Componentes

**FirmaElectronicaClient.jsx:**
- Filtros: tipo documental (select), rango fechas (datepicker)
- Tabla de resultados
- Botón "Verificar integridad" por fila

**FirmaElectronicaTable.jsx:**
- Columnas: Tipo, ID Documento, Radicado/Número, Fecha Firma, Firmante, Integridad (ícono), Acciones
- Paginación desde backend
- Link a detalle del documento original

### Data Flow

```
Page mount → useFirmaElectronicaIndex
  → useFirmaElectronicaListTable (table state: page, sort, filters)
  → FirmaElectronicaQueries.useMisFirmasQuery(params)
    → GET /api/transversal/firma-eventos/mis-firmas?tipo=X&desde=Y&hasta=Z
  → Render FirmaElectronicaClient
```

### Estados UX

- **Loading:** Skeleton table
- **Empty:** Mensaje "No has firmado ningún documento aún"
- **Error: 401/419:** Redirigir a login (manejado por axiosClient)
- **Error: 500:** Toast error genérico
- **Success:** Tabla con datos paginados

---

## Dependencias

- Ninguna nueva — reusa `axiosClient`, MUI Table, Redux auth state, `firmaService.validarIntegridad`
