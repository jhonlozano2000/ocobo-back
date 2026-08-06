# M13 — Módulo Frontend Firma Electrónica Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Create a dedicated M13 frontend module with a centralized view listing all signed documents across all types (received, sent, internal, PQRS) for the current user.

**Architecture:** New backend endpoint returns paginated FirmaEventos for the authenticated user. New frontend module follows existing Mi Bandeja pattern: service → queries → hooks → views → route → sidebar.

**Tech Stack:** Laravel 11 + Spatie Permissions, Next.js 16 App Router, React 19, MUI v6, React Query v3

---

### Task 1: Backend — Resource + Controller + Route

**Files:**
- Create: `app/Http/Resources/Transversal/MisFirmasResource.php`
- Modify: `app/Http/Controllers/Transversal/FirmaEventosController.php`
- Modify: `routes/transversal.php`

- [ ] **Step 1: Create MisFirmasResource**

```php
<?php

namespace App\Http\Resources\Transversal;

use Illuminate\Http\Resources\Json\JsonResource;

class MisFirmasResource extends JsonResource
{
    public function toArray($request)
    {
        $tipoMap = [
            'App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci' => 'reci',
            'App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviados' => 'enviados',
            'App\Models\VentanillaUnica\Interno\VentanillaRadicaInterno' => 'interno',
            'App\Models\VentanillaUnica\Pqrs\VentanillaPqrs' => 'pqrs',
        ];

        return [
            'id' => $this->id,
            'documentable_type' => $tipoMap[$this->documentable_type] ?? $this->documentable_type,
            'documentable_id' => $this->documentable_id,
            'hash_original' => $this->hash_original,
            'hash_firmado' => $this->hash_firmado,
            'integro' => $this->hash_original === $this->hash_firmado,
            'fecha_firma' => $this->fecha_firma?->toIso8601String(),
            'user' => [
                'id' => $this->user_id,
                'name' => $this->user?->name,
                'email' => $this->user?->email,
            ],
        ];
    }
}
```

- [ ] **Step 2: Add misFirmas() to FirmaEventosController**

Read first: `app/Http/Controllers/Transversal/FirmaEventosController.php`

Add method:

```php
use App\Http\Resources\Transversal\MisFirmasResource;
use Illuminate\Http\Request;

public function misFirmas(Request $request)
{
    $tipoMap = [
        'reci' => 'App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci',
        'enviados' => 'App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviados',
        'interno' => 'App\Models\VentanillaUnica\Interno\VentanillaRadicaInterno',
        'pqrs' => 'App\Models\VentanillaUnica\Pqrs\VentanillaPqrs',
    ];

    $query = FirmaEvento::where('user_id', $request->user()->id)
        ->with('user');

    if ($tipo = $request->query('tipo')) {
        if (isset($tipoMap[$tipo])) {
            $query->where('documentable_type', $tipoMap[$tipo]);
        }
    }

    if ($desde = $request->query('desde')) {
        $query->whereDate('fecha_firma', '>=', $desde);
    }

    if ($hasta = $request->query('hasta')) {
        $query->whereDate('fecha_firma', '<=', $hasta);
    }

    $firmas = $query->latest('fecha_firma')->paginate(50);

    return MisFirmasResource::collection($firmas);
}
```

- [ ] **Step 3: Register route (ORDER IS CRITICAL — mis-firmas BEFORE {tipo}/{documentoId})**

In `routes/transversal.php`, inside the `firma-eventos` group:

```php
Route::get('/mis-firmas', [FirmaEventosController::class, 'misFirmas']);
Route::get('/{tipo}/{documentoId}', [FirmaEventosController::class, 'historial']);
```

- [ ] **Step 4: Verify route order**

Run: `php artisan route:list --path=transversal/firma-eventos`
Expected: `mis-firmas` appears before `{tipo}/{documentoId}` in the list. If not, reorder.

- [ ] **Step 5: Commit**

```bash
cd C:\laragon\www\ocobo-back
git add app/Http/Resources/Transversal/MisFirmasResource.php app/Http/Controllers/Transversal/FirmaEventosController.php routes/transversal.php
git commit -m "feat(m13): add GET /firma-eventos/mis-firmas endpoint"
```

---

### Task 2: Backend — Test

**Files:**
- Create: `tests/Feature/Transversal/MisFirmasTest.php`

- [ ] **Step 1: Write the test**

```php
<?php

namespace Tests\Feature\Transversal;

use App\Models\Transversal\FirmaEvento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MisFirmasTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_only_own_signatures()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        FirmaEvento::factory()->create([
            'user_id' => $user->id,
            'documentable_type' => 'App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci',
            'documentable_id' => 1,
        ]);
        FirmaEvento::factory()->create([
            'user_id' => $otherUser->id,
            'documentable_type' => 'App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci',
            'documentable_id' => 2,
        ]);

        $response = $this->actingAs($user)->getJson('/api/transversal/firma-eventos/mis-firmas');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.documentable_id', 1);
    }

    public function test_filters_by_tipo()
    {
        $user = User::factory()->create();

        FirmaEvento::factory()->create([
            'user_id' => $user->id,
            'documentable_type' => 'App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci',
            'documentable_id' => 1,
        ]);
        FirmaEvento::factory()->create([
            'user_id' => $user->id,
            'documentable_type' => 'App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviados',
            'documentable_id' => 2,
        ]);

        $response = $this->actingAs($user)->getJson('/api/transversal/firma-eventos/mis-firmas?tipo=reci');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.documentable_type', 'reci');
    }

    public function test_requires_authentication()
    {
        $response = $this->getJson('/api/transversal/firma-eventos/mis-firmas');
        $response->assertUnauthorized();
    }

    public function test_returns_paginated_results()
    {
        $user = User::factory()->create();
        FirmaEvento::factory(60)->create([
            'user_id' => $user->id,
            'documentable_type' => 'App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci',
            'documentable_id' => 1,
        ]);

        $response = $this->actingAs($user)->getJson('/api/transversal/firma-eventos/mis-firmas');

        $response->assertOk();
        $this->assertCount(50, $response->json('data'));
    }
}
```

- [ ] **Step 2: Create FirmaEvento factory if it doesn't exist**

Check if `database/factories/Transversal/FirmaEventoFactory.php` exists. If not, create it:

```php
<?php

namespace Database\Factories\Transversal;

use App\Models\Transversal\FirmaEvento;
use Illuminate\Database\Eloquent\Factories\Factory;

class FirmaEventoFactory extends Factory
{
    protected $model = FirmaEvento::class;

    public function definition(): array
    {
        return [
            'user_id' => 1,
            'documentable_type' => 'App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci',
            'documentable_id' => 1,
            'hash_original' => $this->faker->sha256,
            'hash_firmado' => $this->faker->sha256,
            'otp_utilizado' => '123456',
            'ip_address' => $this->faker->ipv4,
            'user_agent' => $this->faker->userAgent,
            'fecha_firma' => now(),
        ];
    }
}
```

- [ ] **Step 3: Run tests**

Run: `cd C:\laragon\www\ocobo-back && php artisan test tests/Feature/Transversal/MisFirmasTest.php`
Expected: 4 passed

- [ ] **Step 4: Commit**

```bash
cd C:\laragon\www\ocobo-back
git add tests/Feature/Transversal/MisFirmasTest.php database/factories/Transversal/FirmaEventoFactory.php
git commit -m "test(m13): add MisFirmasTest with 4 feature tests"
```

---

### Task 3: Frontend — Service + Queries

**Files:**
- Create: `src/services/firma-electronica/firmaElectronicaService.js`
- Create: `src/services/firma-electronica/FirmaElectronicaQueries.js`
- Create: `src/services/firma-electronica/index.js`

- [ ] **Step 1: Create firmaElectronicaService.js**

```js
import { axiosClient } from '@/libs/axiosClient'

export const getMisFirmas = async ({ tipo, desde, hasta, page = 1 } = {}) => {
  const params = { page }
  if (tipo) params.tipo = tipo
  if (desde) params.desde = desde
  if (hasta) params.hasta = hasta

  const response = await axiosClient.get('/api/transversal/firma-eventos/mis-firmas', { params })
  return response.data
}

export const firmaElectronicaService = {
  getMisFirmas,
}

export default firmaElectronicaService
```

- [ ] **Step 2: Create FirmaElectronicaQueries.js**

```js
import { useQuery } from 'react-query'

import { getMisFirmas } from './firmaElectronicaService'

export const FIRMA_ELECTRONICA_QUERY_KEYS = {
  misFirmas: params => ['firma-electronica', 'mis-firmas', params],
}

export const useMisFirmasQuery = (params, options = {}) => {
  return useQuery({
    queryKey: FIRMA_ELECTRONICA_QUERY_KEYS.misFirmas(params),
    queryFn: () => getMisFirmas(params),
    staleTime: 30 * 1000,
    gcTime: 60 * 1000,
    retry: 2,
    retryDelay: 2000,
    refetchOnWindowFocus: true,
    refetchOnMount: true,
    keepPreviousData: true,
    ...options,
  })
}
```

- [ ] **Step 3: Create index.js**

```js
export { firmaElectronicaService, default as firmaElectronicaServiceDefault } from './firmaElectronicaService'
export { useMisFirmasQuery, FIRMA_ELECTRONICA_QUERY_KEYS } from './FirmaElectronicaQueries'
```

---

### Task 4: Frontend — Hooks

**Files:**
- Create: `src/hooks/firma-electronica/useFirmaElectronicaListTable.js`
- Create: `src/hooks/firma-electronica/useFirmaElectronicaIndex.js`

- [ ] **Step 1: Create useFirmaElectronicaListTable.js**

```js
import { useCallback, useState } from 'react'

export const useFirmaElectronicaListTable = () => {
  const [page, setPage] = useState(1)
  const [rowsPerPage, setRowsPerPage] = useState(50)

  const handlePageChange = useCallback((e, newPage) => {
    setPage(newPage + 1)
  }, [])

  const handleRowsPerPageChange = useCallback(e => {
    setRowsPerPage(parseInt(e.target.value, 10))
    setPage(1)
  }, [])

  return {
    page,
    rowsPerPage,
    handlePageChange,
    handleRowsPerPageChange,
  }
}

export default useFirmaElectronicaListTable
```

- [ ] **Step 2: Create useFirmaElectronicaIndex.js**

```js
import { useCallback, useState } from 'react'

import { useMisFirmasQuery } from '@/services/firma-electronica/FirmaElectronicaQueries'
import { useFirmaElectronicaListTable } from '@/hooks/firma-electronica/useFirmaElectronicaListTable'

export const useFirmaElectronicaIndex = () => {
  const [filters, setFilters] = useState({ tipo: '', desde: '', hasta: '' })
  const table = useFirmaElectronicaListTable()

  const queryParams = {
    ...filters,
    page: table.page,
  }
  Object.keys(queryParams).forEach(k => { if (!queryParams[k]) delete queryParams[k] })

  const { data, isLoading, isError, error, refetch } = useMisFirmasQuery(queryParams)

  const firmas = data?.data || []
  const pagination = {
    currentPage: data?.current_page || 1,
    lastPage: data?.last_page || 1,
    total: data?.total || 0,
    perPage: data?.per_page || 50,
  }

  const handleFilterChange = useCallback((key, value) => {
    setFilters(prev => ({ ...prev, [key]: value }))
    table.handlePageChange(null, 0)
  }, [table])

  const handleClearFilters = useCallback(() => {
    setFilters({ tipo: '', desde: '', hasta: '' })
    table.handlePageChange(null, 0)
  }, [table])

  return {
    firmas,
    pagination,
    isLoading,
    isError,
    error,
    refetch,
    filters,
    handleFilterChange,
    handleClearFilters,
    table,
  }
}

export default useFirmaElectronicaIndex
```

---

### Task 5: Frontend — Views

**Files:**
- Create: `src/views/firma-electronica/FirmaElectronicaTable.jsx`
- Create: `src/views/firma-electronica/FirmaElectronicaClient.jsx`
- Create: `src/views/firma-electronica/index.jsx`

- [ ] **Step 1: Create FirmaElectronicaTable.jsx**

```js
'use client'

import { useCallback } from 'react'

import {
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  TablePagination,
  Paper,
  IconButton,
  Tooltip,
  Chip,
  Typography,
  TableSortLabel,
} from '@mui/material'

const TIPO_LABELS = {
  reci: 'Recibida',
  enviados: 'Enviada',
  interno: 'Interna',
  pqrs: 'PQRS',
}

const TIPO_COLORS = {
  reci: 'info',
  enviados: 'success',
  interno: 'warning',
  pqrs: 'primary',
}

export default function FirmaElectronicaTable({
  firmas,
  pagination,
  page,
  rowsPerPage,
  onPageChange,
  onRowsPerPageChange,
  onVerify,
}) {
  const handleVerify = useCallback(
    firma => {
      if (onVerify) onVerify(firma)
    },
    [onVerify]
  )

  return (
    <>
      <TableContainer component={Paper}>
        <Table>
          <TableHead>
            <TableRow>
              <TableCell>Tipo</TableCell>
              <TableCell>ID Documento</TableCell>
              <TableCell>Fecha Firma</TableCell>
              <TableCell>Firmante</TableCell>
              <TableCell align='center'>Integridad</TableCell>
              <TableCell align='center'>Acción</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {firmas.length === 0 ? (
              <TableRow>
                <TableCell colSpan={6} align='center'>
                  <Typography variant='body2' color='text.secondary' sx={{ py: 4 }}>
                    No has firmado ningún documento aún
                  </Typography>
                </TableCell>
              </TableRow>
            ) : (
              firmas.map(firma => (
                <TableRow key={firma.id}>
                  <TableCell>
                    <Chip
                      label={TIPO_LABELS[firma.documentable_type] || firma.documentable_type}
                      color={TIPO_COLORS[firma.documentable_type] || 'default'}
                      size='small'
                    />
                  </TableCell>
                  <TableCell>{firma.documentable_id}</TableCell>
                  <TableCell>
                    {firma.fecha_firma
                      ? new Date(firma.fecha_firma).toLocaleDateString('es-CO', {
                          year: 'numeric',
                          month: 'short',
                          day: 'numeric',
                          hour: '2-digit',
                          minute: '2-digit',
                        })
                      : '-'}
                  </TableCell>
                  <TableCell>{firma.user?.name || '—'}</TableCell>
                  <TableCell align='center'>
                    <Tooltip title={firma.integro ? 'Documento íntegro' : 'Documento modificado'}>
                      <Typography
                        variant='body2'
                        sx={{ color: firma.integro ? 'success.main' : 'error.main', fontWeight: 600 }}
                      >
                        {firma.integro ? '✅ Íntegro' : '❌ Modificado'}
                      </Typography>
                    </Tooltip>
                  </TableCell>
                  <TableCell align='center'>
                    <Tooltip title='Verificar integridad'>
                      <IconButton size='small' color='primary' onClick={() => handleVerify(firma)}>
                        <i className='tabler-shield-check' />
                      </IconButton>
                    </Tooltip>
                  </TableCell>
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>
      </TableContainer>
      <TablePagination
        component='div'
        count={pagination.total}
        page={page - 1}
        onPageChange={onPageChange}
        rowsPerPage={rowsPerPage}
        onRowsPerPageChange={onRowsPerPageChange}
        rowsPerPageOptions={[10, 25, 50]}
        labelRowsPerPage='Filas por página'
      />
    </>
  )
}
```

- [ ] **Step 2: Create FirmaElectronicaClient.jsx**

```js
'use client'

import { useCallback, useState } from 'react'

import { Box, Card, CardContent, Grid, MenuItem, TextField, Button, Typography, Alert, Skeleton } from '@mui/material'

import { useFirmaElectronicaIndex } from '@/hooks/firma-electronica/useFirmaElectronicaIndex'
import FirmaElectronicaTable from '@/views/firma-electronica/FirmaElectronicaTable'
import { validarIntegridad } from '@/services/transversal/firmaService'

const TIPO_OPTIONS = [
  { value: '', label: 'Todos' },
  { value: 'reci', label: 'Recibida' },
  { value: 'enviados', label: 'Enviada' },
  { value: 'interno', label: 'Interna' },
  { value: 'pqrs', label: 'PQRS' },
]

export default function FirmaElectronicaClient() {
  const {
    firmas,
    pagination,
    isLoading,
    isError,
    error,
    refetch,
    filters,
    handleFilterChange,
    handleClearFilters,
    table,
  } = useFirmaElectronicaIndex()

  const [verifyResult, setVerifyResult] = useState(null)

  const handleVerify = useCallback(async firma => {
    setVerifyResult(null)
    try {
      const result = await validarIntegridad({
        documentable_type: firma.documentable_type,
        documentable_id: firma.documentable_id,
      })
      setVerifyResult({ ...result, firma })
    } catch (err) {
      setVerifyResult({
        integro: false,
        error: err.response?.data?.message || 'Error al verificar integridad',
        firma,
      })
    }
  }, [])

  if (isError) {
    return (
      <Alert severity='error' action={<Button onClick={refetch}>Reintentar</Button>}>
        {error?.message || 'Error al cargar firmas'}
      </Alert>
    )
  }

  return (
    <Grid container spacing={6}>
      <Grid item xs={12}>
        <Card>
          <CardContent>
            <Grid container spacing={2} alignItems='center'>
              <Grid item xs={12} sm={3}>
                <TextField
                  select
                  fullWidth
                  size='small'
                  label='Tipo documento'
                  value={filters.tipo}
                  onChange={e => handleFilterChange('tipo', e.target.value)}
                >
                  {TIPO_OPTIONS.map(opt => (
                    <MenuItem key={opt.value} value={opt.value}>
                      {opt.label}
                    </MenuItem>
                  ))}
                </TextField>
              </Grid>
              <Grid item xs={12} sm={3}>
                <TextField
                  fullWidth
                  size='small'
                  label='Desde'
                  type='date'
                  value={filters.desde}
                  onChange={e => handleFilterChange('desde', e.target.value)}
                  InputLabelProps={{ shrink: true }}
                />
              </Grid>
              <Grid item xs={12} sm={3}>
                <TextField
                  fullWidth
                  size='small'
                  label='Hasta'
                  type='date'
                  value={filters.hasta}
                  onChange={e => handleFilterChange('hasta', e.target.value)}
                  InputLabelProps={{ shrink: true }}
                />
              </Grid>
              <Grid item xs={12} sm={3}>
                <Button variant='outlined' onClick={handleClearFilters} fullWidth>
                  Limpiar filtros
                </Button>
              </Grid>
            </Grid>
          </CardContent>
        </Card>
      </Grid>

      <Grid item xs={12}>
        <Card>
          <CardContent>
            {isLoading ? (
              <Skeleton variant='rectangular' height={300} />
            ) : (
              <FirmaElectronicaTable
                firmas={firmas}
                pagination={pagination}
                page={table.page}
                rowsPerPage={table.rowsPerPage}
                onPageChange={table.handlePageChange}
                onRowsPerPageChange={table.handleRowsPerPageChange}
                onVerify={handleVerify}
              />
            )}
          </CardContent>
        </Card>
      </Grid>

      {verifyResult && (
        <Grid item xs={12}>
          <Alert
            severity={verifyResult.integro ? 'success' : 'error'}
            onClose={() => setVerifyResult(null)}
            action={
              <Button size='small' onClick={() => setVerifyResult(null)}>
                Cerrar
              </Button>
            }
          >
            <Typography variant='subtitle2'>
              Documento #{verifyResult.firma.documentable_id} —{' '}
              {TIPO_OPTIONS.find(o => o.value === verifyResult.firma.documentable_type)?.label ||
                verifyResult.firma.documentable_type}
            </Typography>
            <Typography variant='body2'>
              {verifyResult.integro
                ? 'El documento se encuentra íntegro. El hash actual coincide con el hash al momento de la firma.'
                : verifyResult.error || 'El documento ha sido modificado después de la firma.'}
            </Typography>
          </Alert>
        </Grid>
      )}
    </Grid>
  )
}
```

- [ ] **Step 3: Create index.jsx (page view wrapper)**

```js
'use client'

import dynamic from 'next/dynamic'

const FirmaElectronicaClient = dynamic(() => import('@/views/firma-electronica/FirmaElectronicaClient'), { ssr: false })

const FirmaElectronicaApp = () => {
  return <FirmaElectronicaClient />
}

export default FirmaElectronicaApp
```

---

### Task 6: Frontend — Route + Sidebar + Dictionary

**Files:**
- Create: `src/app/[lang]/(mi-bandeja)/firma-electronica/page.jsx`
- Modify: `src/components/layout/vertical/VerticalMenu.jsx`
- Modify: `src/data/dictionaries/es.json`
- Modify: `src/data/dictionaries/en.json`

- [ ] **Step 1: Create route page**

```jsx
import { Suspense } from 'react'

import { Breadcrumbs, Typography, Box, Skeleton } from '@mui/material'

import Grid from '@mui/material/Grid2'

import Card from '@mui/material/Card'

import CardContent from '@mui/material/CardContent'

import { getDictionary } from '@/utils/getDictionary'
import Link from '@/components/Link'

import FirmaElectronicaApp from '@/views/firma-electronica'

export const revalidate = 60

function Loading() {
  return (
    <Box sx={{ p: 4 }}>
      <Skeleton variant='rectangular' height={50} sx={{ mb: 4 }} />
      <Skeleton variant='rectangular' height={300} />
    </Box>
  )
}

const FirmaElectronicaPage = async props => {
  try {
    const params = await props.params
    const dictionary = await getDictionary(params.lang)

    return (
      <Suspense fallback={<Loading />}>
        <Grid container spacing={6}>
          <Grid size={{ xs: 12 }}>
            <Breadcrumbs>
              <Link href={`/${params.lang}/mi-bandeja`}>Inicio</Link>
              <Typography>Firma Electrónica</Typography>
            </Breadcrumbs>
          </Grid>
          <Grid size={{ xs: 12 }}>
            <Typography variant='h4'>Firma Electrónica</Typography>
          </Grid>
          <Grid size={{ xs: 12 }}>
            <FirmaElectronicaApp />
          </Grid>
        </Grid>
      </Suspense>
    )
  } catch (error) {
    return (
      <Box sx={{ p: 4 }}>
        <Typography color='error'>Error al cargar la página</Typography>
      </Box>
    )
  }
}

export default FirmaElectronicaPage
```

- [ ] **Step 2: Add sidebar entry**

In `VerticalMenu.jsx`, after the workflows menu items (after line 240, before `</MenuSection>` on line 241), add:

```jsx
          <MenuItem href={`/${locale}/firma-electronica`} icon={<i className='tabler-shield-check' />}>
            Firma Electrónica
          </MenuItem>
```

- [ ] **Step 3: Add dictionary entries**

In `src/data/dictionaries/es.json`, add after `navigation_workflows`:

```json
  "navigation_firma_electronica": {
    "modulo": "Firma Electrónica"
  },
```

In `src/data/dictionaries/en.json`, add:

```json
  "navigation_firma_electronica": {
    "modulo": "Electronic Signature"
  },
```

- [ ] **Step 4: Run frontend lint**

Run: `cd C:\Users\Jhon\Desktop\ocobo-frond && npm run lint`
Expected: No new errors (only existing 37 template errors)

---

### Task 7: Verify + Update Roadmap

**Files:**
- Modify: `C:\Users\Jhon\Desktop\ocobo-documents\roadmap-gap-analysis.md`

- [ ] **Step 1: Run backend tests**

Run: `cd C:\laragon\www\ocobo-back && php artisan test`
Expected: All existing tests pass + 4 new MisFirmas tests pass

- [ ] **Step 2: Update roadmap-gap-analysis.md**

Mark item #6 (M13 Módulo frontend dedicado) as ✅ COMPLETO in the gaps section:

```
6. **M13 Módulo frontend dedicado** — ✅ Vista/ruta/sidebar propio con lista de firmas por usuario
```

Also update the M13 detalle table sub-item if present.

- [ ] **Step 3: Commit frontend changes**

```bash
cd C:\Users\Jhon\Desktop\ocobo-frond
git add src/views/firma-electronica/ src/hooks/firma-electronica/ src/services/firma-electronica/ src/app/\[lang\]/\(mi-bandeja\)/firma-electronica/ src/components/layout/vertical/VerticalMenu.jsx src/data/dictionaries/
git commit -m "feat(m13): add dedicated frontend module with route, sidebar, and centralized signed-docs view"
```
