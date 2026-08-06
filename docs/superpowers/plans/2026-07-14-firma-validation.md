# Firma Validation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add endpoint + UI to verify integrity of electronically signed documents by comparing current SHA-256 against hash stored at signing time.

**Architecture:** Backend recalculates hash from Storage PDF, compares against `hash_firmado` in `firmas_eventos`. Frontend adds "Verificar integridad" button in detail dialogs with result modal.

**Tech Stack:** Laravel 11 (backend), Next.js 16 + MUI v6 (frontend)

---

### Task 1: Create FirmaValidacionService

**Files:**
- Create: `C:\laragon\www\ocobo-back\app\Services\Firma\FirmaValidacionService.php`

- [ ] **Step 1: Write the service**

```php
<?php

namespace App\Services\Firma;

use App\Models\Transversal\FirmaEvento;
use App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviados;
use App\Models\VentanillaUnica\Internos\VentanillaRadicaInterno;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use Illuminate\Support\Facades\Storage;

class FirmaValidacionService
{
    const MODEL_MAP = [
        'radicado_recibido' => VentanillaRadicaReci::class,
        'radicado_enviado' => VentanillaRadicaEnviados::class,
        'radicado_interno' => VentanillaRadicaInterno::class,
    ];

    const DISK_MAP = [
        'radicado_recibido' => 'radicados_recibidos',
        'radicado_enviado' => 'radicados_enviados',
        'radicado_interno' => 'ventanilla_radica_interno_archivos',
    ];

    public function validar(string $tipo, int $id): array
    {
        if (!isset(self::MODEL_MAP[$tipo])) {
            throw new \InvalidArgumentException("Tipo de documento no válido: $tipo");
        }

        $modelClass = self::MODEL_MAP[$tipo];
        $documento = $modelClass::find($id);

        if (!$documento) {
            throw new \RuntimeException('Documento no encontrado');
        }

        if (!$documento->archivo_digital) {
            throw new \RuntimeException('El documento no tiene archivo PDF asociado');
        }

        // Recalcular hash del PDF actual
        $disk = self::DISK_MAP[$tipo];
        $pdfPath = Storage::disk($disk)->path($documento->archivo_digital);

        if (!file_exists($pdfPath)) {
            throw new \RuntimeException('El archivo PDF no se encuentra en el almacenamiento');
        }

        $hashActual = hash_file('sha256', $pdfPath);

        // Buscar último evento de firma con hash_firmado
        $modelClassFull = get_class($documento);
        $ultimoEvento = FirmaEvento::where('documentable_id', $id)
            ->where('documentable_type', $modelClassFull)
            ->whereNotNull('hash_firmado')
            ->orderBy('fecha_firma', 'desc')
            ->first();

        if (!$ultimoEvento) {
            throw new \RuntimeException('El documento no tiene un evento de firma registrado');
        }

        $valido = $hashActual === $ultimoEvento->hash_firmado;

        return [
            'valido' => $valido,
            'hash_actual' => $hashActual,
            'hash_firmado' => $ultimoEvento->hash_firmado,
            'fecha_firma' => $ultimoEvento->fecha_firma,
            'firmante' => $ultimoEvento->user ? [
                'nombres' => trim($ultimoEvento->user->nombres . ' ' . $ultimoEvento->user->apellidos),
                'email' => $ultimoEvento->user->email,
            ] : null,
            'documento' => [
                'tipo' => $tipo,
                'radicado' => $documento->radicado ?? 'N/A',
            ],
        ];
    }
}
```

- [ ] **Step 2: Commit**

```bash
cd C:\laragon\www\ocobo-back && git add app/Services/Firma/FirmaValidacionService.php && git commit -m "feat(firma): add FirmaValidacionService for hash comparison"
```

---

### Task 2: Create FirmaValidarRequest

**Files:**
- Create: `C:\laragon\www\ocobo-back\app\Http\Requests\Transversal\FirmaValidarRequest.php`

- [ ] **Step 1: Write the form request**

```php
<?php

namespace App\Http\Requests\Transversal;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\ValidationException;

class FirmaValidarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'documentable_type' => 'required|string|in:radicado_enviado,radicado_recibido,radicado_interno',
            'documentable_id' => 'required|integer',
        ];
    }

    public function messages(): array
    {
        return [
            'documentable_type.required' => 'El tipo de documento es obligatorio.',
            'documentable_type.in' => 'El tipo de documento no es válido.',
            'documentable_id.required' => 'El ID del documento es obligatorio.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new ValidationException($validator, response()->json([
            'status' => false,
            'message' => 'Errores de validación.',
            'errors' => $validator->errors(),
        ], 422));
    }
}
```

- [ ] **Step 2: Commit**

```bash
cd C:\laragon\www\ocobo-back && git add app/Http/Requests/Transversal/FirmaValidarRequest.php && git commit -m "feat(firma): add FirmaValidarRequest"
```

---

### Task 3: Create FirmaValidacionController

**Files:**
- Create: `C:\laragon\www\ocobo-back\app\Http\Controllers\Transversal\FirmaValidacionController.php`

- [ ] **Step 1: Write the controller**

```php
<?php

namespace App\Http\Controllers\Transversal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Transversal\FirmaValidarRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Services\Firma\FirmaValidacionService;
use Illuminate\Support\Facades\Log;

class FirmaValidacionController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private FirmaValidacionService $validacionService
    ) {}

    public function validar(FirmaValidarRequest $request)
    {
        try {
            $resultado = $this->validacionService->validar(
                $request->input('documentable_type'),
                $request->input('documentable_id')
            );

            return $this->successResponse($resultado,
                $resultado['valido']
                    ? 'El documento se encuentra íntegro'
                    : 'El documento ha sido modificado después de la firma'
            );
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), null, 422);
        } catch (\RuntimeException $e) {
            Log::warning('Error en validación de firma', [
                'error' => $e->getMessage(),
                'documentable_type' => $request->input('documentable_type'),
                'documentable_id' => $request->input('documentable_id'),
            ]);
            return $this->errorResponse($e->getMessage(), null, 404);
        } catch (\Exception $e) {
            Log::error('Error inesperado en validación de firma', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->errorResponse('Error al validar la firma del documento', null, 500);
        }
    }
}
```

- [ ] **Step 2: Commit**

```bash
cd C:\laragon\www\ocobo-back && git add app/Http/Controllers/Transversal/FirmaValidacionController.php && git commit -m "feat(firma): add FirmaValidacionController"
```

---

### Task 4: Register route

**Files:**
- Modify: `C:\laragon\www\ocobo-back\routes\transversal.php`

- [ ] **Step 1: Read the file and add the route**

Read `C:\laragon\www\ocobo-back\routes\transversal.php` first, then add after the existing firma-eventos route:

```php
Route::post('/firma-validar', [App\Http\Controllers\Transversal\FirmaValidacionController::class, 'validar']);
```

- [ ] **Step 2: Commit**

```bash
cd C:\laragon\www\ocobo-back && git add routes/transversal.php && git commit -m "feat(firma): register firma-validar route"
```

---

### Task 5: Create backend test

**Files:**
- Create: `C:\laragon\www\ocobo-back\tests\Feature\Transversal\FirmaValidacionTest.php`

- [ ] **Step 1: Write the test**

```php
<?php

namespace Tests\Feature\Transversal;

use App\Models\Transversal\FirmaEvento;
use App\Models\User;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FirmaValidacionTest extends TestCase
{
    use RefreshDatabase;

    public function test_validar_returns_integrity_true_when_hash_matches()
    {
        Storage::fake('radicados_recibidos');

        $user = User::factory()->create(['estado' => 1]);
        $file = UploadedFile::fake()->create('documento.pdf', 100);
        $path = $file->store('', 'radicados_recibidos');

        $hashOriginal = hash_file('sha256', Storage::disk('radicados_recibidos')->path($path));

        $documento = VentanillaRadicaReci::factory()->create([
            'archivo_digital' => $path,
            'hash_sha256' => $hashOriginal,
            'estado_firma' => 'firmado',
        ]);

        FirmaEvento::create([
            'documentable_id' => $documento->id,
            'documentable_type' => get_class($documento),
            'user_id' => $user->id,
            'hash_original' => $hashOriginal,
            'hash_firmado' => $hashOriginal,
            'otp_utilizado' => '***123',
            'ip_address' => '127.0.0.1',
            'fecha_firma' => now(),
        ]);

        $response = $this->actingAs($user)->postJson('/api/transversal/firma-validar', [
            'documentable_type' => 'radicado_recibido',
            'documentable_id' => $documento->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.valido', true)
            ->assertJsonPath('data.hash_actual', $hashOriginal)
            ->assertJsonPath('data.hash_firmado', $hashOriginal);
    }

    public function test_validar_returns_integrity_false_when_hash_differs()
    {
        Storage::fake('radicados_recibidos');

        $user = User::factory()->create(['estado' => 1]);
        $file = UploadedFile::fake()->create('documento.pdf', 100);
        $path = $file->store('', 'radicados_recibidos');

        $hashOriginal = hash_file('sha256', Storage::disk('radicados_recibidos')->path($path));

        $documento = VentanillaRadicaReci::factory()->create([
            'archivo_digital' => $path,
            'hash_sha256' => 'hash_diferente',
            'estado_firma' => 'firmado',
        ]);

        FirmaEvento::create([
            'documentable_id' => $documento->id,
            'documentable_type' => get_class($documento),
            'user_id' => $user->id,
            'hash_original' => $hashOriginal,
            'hash_firmado' => 'hash_diferente',
            'otp_utilizado' => '***123',
            'ip_address' => '127.0.0.1',
            'fecha_firma' => now(),
        ]);

        $response = $this->actingAs($user)->postJson('/api/transversal/firma-validar', [
            'documentable_type' => 'radicado_recibido',
            'documentable_id' => $documento->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.valido', false);
    }

    public function test_validar_returns_404_when_no_signature_event()
    {
        Storage::fake('radicados_recibidos');

        $user = User::factory()->create(['estado' => 1]);

        $response = $this->actingAs($user)->postJson('/api/transversal/firma-validar', [
            'documentable_type' => 'radicado_recibido',
            'documentable_id' => 999,
        ]);

        $response->assertStatus(404);
    }
}
```

**Note:** You may need to check if VentanillaRadicaReci has a factory. If not, create the document manually using `VentanillaRadicaReci::create([...])` with all required fields.

- [ ] **Step 2: Run tests**

```bash
cd C:\laragon\www\ocobo-back && php artisan test tests/Feature/Transversal/FirmaValidacionTest.php
```

Expected: PASS (3/3)

- [ ] **Step 3: Commit**

```bash
cd C:\laragon\www\ocobo-back && git add tests/Feature/Transversal/FirmaValidacionTest.php && git commit -m "feat(firma): add backend tests for firma validation"
```

---

### Task 6: Add validarIntegridad to frontend firmaService

**Files:**
- Modify: `C:\Users\Jhon\Desktop\ocobo-frond\src\services\transversal\firmaService.js`

- [ ] **Step 1: Read the file and add the function**

Read `C:\Users\Jhon\Desktop\ocobo-frond\src\services\transversal\firmaService.js` first, then add:

```javascript
export async function validarIntegridad(documentableType, documentableId) {
  const res = await axiosClient.post('/api/transversal/firma-validar', {
    documentable_type: documentableType,
    documentable_id: documentableId,
  })
  return res?.data?.data || {}
}
```

- [ ] **Step 2: Commit**

```bash
cd C:\Users\Jhon\Desktop\ocobo-frond && git add src/services/transversal/firmaService.js && git commit -m "feat(firma): add validarIntegridad to firmaService"
```

---

### Task 7: Add validation button + modal to RadicadoDetailsDialog

**Files:**
- Modify: `C:\Users\Jhon\Desktop\ocobo-frond\src\views\ventanilla-unica\correspondencia-recibida\dialogs\RadicadoDetailsDialog.jsx`

- [ ] **Step 1: Read the current file**

Read the file to understand the current structure (look for `dialogSignatureOpen`, `handleCloseDialog`, and the header buttons).

- [ ] **Step 2: Add state and handler**

Add after the existing signature state declarations:
```javascript
const [validationDialogOpen, setValidationDialogOpen] = useState(false)
const [validationResult, setValidationResult] = useState(null)
const [validationLoading, setValidationLoading] = useState(false)
```

Add handle function:
```javascript
const handleValidateIntegridad = async () => {
  setValidationLoading(true)
  setValidationResult(null)
  try {
    const result = await validarIntegridad('radicado_recibido', data?.id)
    setValidationResult(result)
  } catch (err) {
    setValidationResult({ valido: false, error: err?.response?.data?.message || 'Error al validar la firma' })
  } finally {
    setValidationLoading(false)
    setValidationDialogOpen(true)
  }
}
```

Add the import at top:
```javascript
import { validarIntegridad } from '@/services/transversal/firmaService'
```

- [ ] **Step 3: Add button in the header**

Find where the "Firmar Electrónicamente" button is and add after it:
```jsx
<Button
  variant='tonal'
  color='info'
  startIcon={<i className='tabler-shield-check' />}
  onClick={handleValidateIntegridad}
  disabled={validationLoading || !data?.hash_sha256}
>
  {validationLoading ? 'Verificando...' : 'Verificar integridad'}
</Button>
```

- [ ] **Step 4: Add modal before the closing `</>`**

```jsx
{/* Modal de validación de firma */}
<Dialog
  fullWidth
  maxWidth='sm'
  open={validationDialogOpen}
  onClose={() => { setValidationDialogOpen(false); setValidationResult(null) }}
>
  <DialogTitle>
    {validationResult?.valido
      ? '✅ Documento íntegro'
      : '❌ Documento modificado'}
  </DialogTitle>
  <DialogContent>
    {validationResult && (
      <Stack spacing={2}>
        <Alert severity={validationResult.valido ? 'success' : 'error'}>
          {validationResult.valido
            ? 'El documento no ha sido modificado desde su firma electrónica.'
            : 'El documento ha sido modificado después de haber sido firmado electrónicamente.'}
        </Alert>
        <Typography variant='body2'>
          <strong>Hash actual:</strong> {validationResult.hash_actual}
        </Typography>
        <Typography variant='body2'>
          <strong>Hash firmado:</strong> {validationResult.hash_firmado}
        </Typography>
        {validationResult.fecha_firma && (
          <Typography variant='body2'>
            <strong>Fecha de firma:</strong> {new Date(validationResult.fecha_firma).toLocaleString()}
          </Typography>
        )}
        {validationResult.firmante && (
          <Typography variant='body2'>
            <strong>Firmado por:</strong> {validationResult.firmante.nombres} ({validationResult.firmante.email})
          </Typography>
        )}
      </Stack>
    )}
  </DialogContent>
  <DialogActions>
    <Button variant='contained' onClick={() => { setValidationDialogOpen(false); setValidationResult(null) }}>
      Cerrar
    </Button>
  </DialogActions>
</Dialog>
```

Add required MUI imports at top:
```javascript
import Stack from '@mui/material/Stack'
```

- [ ] **Step 5: Commit**

```bash
cd C:\Users\Jhon\Desktop\ocobo-frond && git add src/views/ventanilla-unica/correspondencia-recibida/dialogs/RadicadoDetailsDialog.jsx && git commit -m "feat(firma): add validation button + modal to RadicadoDetailsDialog"
```

---

### Task 8: Add validation to RadicadoEnviadoDetailsDialog

**Files:**
- Modify: `C:\Users\Jhon\Desktop\ocobo-frond\src\views\ventanilla-unica\correspondencia-enviada\dialogs\RadicadoEnviadoDetailsDialog.jsx`

Same changes as Task 7 but with `documentable_type: 'radicado_enviado'`.

- [ ] **Step 1: Add state, handler, import, button, and modal**

Follow the same pattern from Task 7. Read the file first, then make changes.

- [ ] **Step 2: Commit**

```bash
cd C:\Users\Jhon\Desktop\ocobo-frond && git add src/views/ventanilla-unica/correspondencia-enviada/dialogs/RadicadoEnviadoDetailsDialog.jsx && git commit -m "feat(firma): add validation button + modal to RadicadoEnviadoDetailsDialog"
```

---

### Task 9: Add validation to RadicadoInternoDetailsDialog

**Files:**
- Modify: `C:\Users\Jhon\Desktop\ocobo-frond\src\views\ventanilla-unica\correspondencia-interna\dialogs\RadicadoInternoDetailsDialog.jsx`

Same changes as Task 7 but with `documentable_type: 'radicado_interno'`.

- [ ] **Step 1: Add state, handler, import, button, and modal**

Follow the same pattern from Task 7.

- [ ] **Step 2: Commit**

```bash
cd C:\Users\Jhon\Desktop\ocobo-frond && git add src/views/ventanilla-unica/correspondencia-interna/dialogs/RadicadoInternoDetailsDialog.jsx && git commit -m "feat(firma): add validation button + modal to RadicadoInternoDetailsDialog"
```

---

### Task 10: Lint, test, and update roadmap

- [ ] **Step 1: Run backend tests**

```bash
cd C:\laragon\www\ocobo-back && php artisan test tests/Feature/Transversal/FirmaValidacionTest.php
```

Expected: PASS (3/3)

- [ ] **Step 2: Run frontend lint**

```bash
cd C:\Users\Jhon\Desktop\ocobo-frond && npm run lint && npm run format
```

Fix any issues.

- [ ] **Step 3: Commit lint fixes**

```bash
cd C:\Users\Jhon\Desktop\ocobo-frond && git add -A && git commit -m "chore: lint fixes for firma validation"
```

- [ ] **Step 4: Update roadmap-gap-analysis.md**

Read `C:\Users\Jhon\Desktop\ocobo-documents\roadmap-gap-analysis.md` and mark:
- Line with "M13 Validación firma" status → ✅ COMPLETO
- Priority section → mark 2 as completed
