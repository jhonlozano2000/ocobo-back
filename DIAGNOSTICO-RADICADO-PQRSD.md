# Diagnóstico Técnico - OCOBO ECM
## Flujo Crítico: Radicado Recibido → PQRSD

**Fecha:** 2026-06-03
**Estado:** En desarrollo - Fase de integración

---

## 📊 DIAGNÓSTICO DEL ESTADO INICIAL

Ocobo ECM muestra una arquitectura Laravel 12 bien estructurada con separación clara por módulos. El backend implementa autenticación Sanctum con cookies HttpOnly, control de acceso Spatie, y cumplimiento ISO 27001 con logs de auditoría. El frontend está separado y usa Next.js con Vuexy v10.5.0.

### Cuellos de botella identificados:
1. **Radicado-PQRSD Transactional Gap**: El flujo crítico Radicado Recibido → PQRSD está parcialmente implementado. El código PQRS está comentado en `VentanillaRadicaReciController@store()` (líneas 259-285), rompiendo atomicidad
2. **ABAC jerárquico no automático**: Los scopes de visibilidad por jerarquía organizacional deben implementarse explícitamente en los modelos
3. **Acoplamiento servicios-controladores**: Los servicios podrían estar más desacoplados para mejor testabilidad

---

## 🏛️ 1. DIAGNÓSTICO TÉCNICO TRANSACCIONAL (RADICADO RECIBIDO ➔ PQRSD)

### Flujo actual:
1. `VentanillaRadicaReciController@store()` crea radicado con `DB::beginTransaction()`
2. Si `crear_pqrs=true`, se intenta crear PQRS dentro de la transacción
3. **Problema crítico**: El código PQRS está comentado (líneas 259-285), por lo que solo se crea el radicado
4. Si falla PQRS después de crear radicado (cuando se habilite), se haría rollback pero el radicado ya estaría creado

### Riesgo de radicados huérfanos:
**ALTO** cuando se habilite el flujo PQRS, ya que no hay compensación si falla la creación PQRS después del commit del radicado.

---

## 🛡️ 2. MATRIZ DE SEGURIDAD, FILTRADO Y UX

| Componente | Estado | Cumplimiento |
|------------|--------|--------------|
| **FormRequest** | ✅ Implementado | Validaciones estrictas en `StoreRadicadoReciboRequest` |
| **Service Layer** | ⚠️ Parcial | Lógica en controller, no en servicio dedicado para PQRS |
| **ABAC Filtrado** | ❌ Manual | Requiere scopes explícitos en modelos (no global) |
| **JSON Response** | ✅ Estándar | Usa `ApiResponseTrait` con formato `{status, message, data}` |
| **Vuexy Compatibilidad** | ✅ Alineado | Badges semánticos pueden mapearse desde estados |
| **Manejo Errores** | ✅ Robusto | Try/catch con rollback y logging detallado |

### Implementación actual de seguridad:
- **Middleware**: `can:` con permisos definidos en `app/Policies/`
- **Verificación**: `$user->hasPermissionTo()`
- **Limitación**: Sin global scope automático por jerarquía org

---

## 🧪 3. DISEÑO DEL PLAN DE PRUEBA DE INTEGRACIÓN ATÓMICA

### Estrategia de prueba:
1. **Happy Path**: POST `/api/radica-recibida` con `crear_pqrs=true` y datos válidos
2. **Escenarios de fallo**:
   - Permiso denegado por ABAC (usuario sin rol Radicar)
   - Campos faltantes (validación 422)
   - Error en creación PQRS (simular excepción)

### Payloads JSON sugeridos:

#### Happy Path:
```json
{
  "clasifica_documen_id": 1,
  "tercero_id": 123,
  "medio_recep_id": 2,
  "asunto": "Solicitud de información pública",
  "num_folios": 5,
  "num_anexos": 2,
  "descrip_anexos": "Documentos de soporte",
  "crear_pqrs": true,
  "tipo_pqrs_id": 1,
  "prioridad": "Normal"
}
```

#### Fallos de validación:
```json
{
  "clasifica_documen_id": null,
  "tercero_id": 123
}
```

### Validaciones esperadas:
- **Éxito**: Respuesta con `status: true` y datos del radicado creado
- **Fallo de validación**: Respuesta con `status: false` y código 422
- **Fallo de transacción**: Respuesta con `status: false` y código 500, pero sin radicado creado en BD

---

## 📋 PRÓXIMOS PASOS REQUERIDOS

1. Habilitar y probar el flujo PQRS dentro de la transacción
2. Implementar scopes ABAC automáticos para jerarquía organizacional
3. Crear tests de integración para validar atomicidad
4. Asegurar mapeo correcto de estados a clases de Vuexy

---

## 📁 ARCHIVOS DE REFERENCIA

- **Controller**: `app/Http/Controllers/VentanillaUnica/Recibidos/VentanillaRadicaReciController.php`
- **Request**: `app/Http/Requests/Ventanilla/Recibidos/StoreRadicadoReciboRequest.php`
- **Rutas**: `routes/ventanilla-recibida.php`
- **Rutas PQRS**: `routes/ventanilla-pqrs.php`
- **Modelo Radicado**: `app/Models/VentanillaUnica/Recibidos/VentanillaRadicaReci.php`
- **Modelo PQRS**: `app/Models/VentanillaUnica/Pqrs/VentanillaPqrs.php`
- **Service PQRS**: `app/Services/VentanillaUnica/PqrsService.php`

---

## 📊 MÉTRICAS DEL PROYECTO

| Métrica | Valor |
|---------|-------|
| **Versión Backend** | 2.2 |
| **Framework** | Laravel 12.0 |
| **PHP** | 8.3+ |
| **Autenticación** | Sanctum (SPA) |
| **Base de datos** | MySQL (Laragon) |
| **Frontend** | Next.js + Vuexy v10.5.0 |
| **Estado** | En desarrollo activo |

---

*Documento generado automáticamente durante análisis de integración - 2026-06-03*
