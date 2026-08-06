# Validación de Integridad de Firma — Diseño

## Resumen

Endpoint que verifica la integridad de un documento firmado electrónicamente: recalcula SHA-256 del PDF actual y lo compara contra el hash almacenado en el momento de la firma (`hash_firmado` en `firmas_eventos`). Frontend con botón "Verificar integridad" en los dialogs de detalle.

## Stack

- Backend: Laravel 11
- Frontend: Next.js 16 + MUI v6 + Redux Toolkit

## Backend

### Endpoint

`POST /api/transversal/firma-validar`

Body: `{ documentable_type: string, documentable_id: int }`

Logica:

1. Resolver el modelo según `documentable_type` (e.g. `radicado_recibido` → `App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci`)
2. Obtener el path del archivo PDF desde el campo `archivo_digital` del documento
3. Recalcular SHA-256: `hash_file('sha256', Storage::path($archivoDigital))`
4. Buscar el último `FirmaEvento` para el documento (morph `documentable`) donde `hash_firmado` no sea null, ordenado por `fecha_firma` DESC
5. Si no hay firma → error 404
6. Comparar hash recalculado contra `hash_firmado`
7. Responder:

```json
{
  "valido": true,
  "hash_actual": "abc...",
  "hash_firmado": "abc...",
  "fecha_firma": "2026-07-14T12:00:00Z",
  "firmante": {
    "nombres": "Juan Pérez",
    "email": "juan@example.com"
  },
  "documento": {
    "tipo": "radicado_recibido",
    "radicado": "RAD-2026-001"
  }
}
```

### Type mapping

Usar el mismo mapping que `FirmaElectronicaController` ya usa:

```php
const MODEL_MAP = [
    'radicado_recibido' => VentanillaRadicaReci::class,
    'radicado_enviado' => VentanillaRadicaEnviados::class,
    'radicado_interno' => VentanillaRadicaInterno::class,
];
```

### Logging

Registrar evento en `UsersAuthenticationLog`? No — no es evento de autenticación. Usar `AuditLogService::log()` o simplemente no loggear (es consulta, no mutación).

## Frontend

### Botón "Verificar integridad"

En los 3 dialogs de detalle (`RadicadoDetailsDialog`, `RadicadoEnviadoDetailsDialog`, `RadicadoInternoDetailsDialog`), agregar un botón en el header. Usar el mismo patrón de `dialogSignatureOpen` / `handleCloseDialog` que se usó para F2.2.

### Estado

- `validationDialogOpen` (bool)
- `validationResult` (null | { valido, hash_actual, hash_firmado, fecha_firma, firmante })

### Modal de resultado

- Si `valido === true`: Alert verde "✅ Documento íntegro — no ha sido modificado desde la firma"
- Si `valido === false`: Alert rojo "❌ El documento ha sido modificado después de la firma"
- Mostrar: hash actual, hash firmado, fecha firma, firmante
- Botón "Cerrar"

### Servicio

Agregar a `src/services/transversal/firmaService.js`:
```js
export async function validarIntegridad(documentableType, documentableId) {
  const res = await axiosClient.post('/api/transversal/firma-validar', {
    documentable_type: documentableType,
    documentable_id: documentableId,
  })
  return res?.data?.data || {}
}
```

## Archivos a modificar/crear

### Backend
- Create: `app/Http/Controllers/Transversal/FirmaValidacionController.php`
- Create: `app/Http/Requests/Transversal/FirmaValidarRequest.php`
- Create: `app/Services/Firma/FirmaValidacionService.php`
- Modify: `routes/transversal.php` (agregar ruta)

### Frontend
- Modify: `src/services/transversal/firmaService.js` (agregar validarIntegridad)
- Modify: `src/views/ventanilla-unica/correspondencia-recibida/dialogs/RadicadoDetailsDialog.jsx`
- Modify: `src/views/ventanilla-unica/correspondencia-enviada/dialogs/RadicadoEnviadoDetailsDialog.jsx`
- Modify: `src/views/ventanilla-unica/correspondencia-interna/dialogs/RadicadoInternoDetailsDialog.jsx`

## No incluir (scope)

- Validación de firma embebida en PDF (X.509, PKI)
- Verificación batch de documentos
- Exportación de reporte de validación
- Pantalla dedicada de validación (solo botón en dialog de detalle)
