# 2FA TOTP para usuarios — Diseño

## Resumen

Implementar autenticación de dos factores opcional via TOTP (Google Authenticator/Microsoft Authenticator/etc.) para todos los usuarios del sistema. Usuarios activan/desactivan desde perfil.

## Stack

- Backend: Laravel 11 + Sanctum (SPA cookies)
- Frontend: Next.js 16 + MUI v6 + Redux Toolkit
- Librería TOTP: `pragmarx/google2fa-laravel`
- QR: `bacon/bacon-qr-code` (ya instalado)

## Backend

### Migración

`users` table:
- `two_factor_secret` (TEXT, nullable) — secreto encriptado
- `two_factor_confirmed_at` (TIMESTAMP, nullable) — fecha de activación/confirmación
- `two_factor_recovery_codes` (TEXT, nullable) — JSON array de recovery codes hasheados

### Endpoints

| Endpoint | Método | Auth | Propósito |
|---|---|---|---|
| `/api/2fa/setup` | GET | Sanctum | Genera secret + QR SVG + recovery codes |
| `/api/2fa/confirm` | POST | Sanctum | Confirma activación con código TOTP de 6 dígitos |
| `/api/2fa/disable` | POST | Sanctum | Desactiva (requiere contraseña actual) |
| `/api/2fa/verify` | POST | No auth (token firmado) | Segundo paso del login |

### Flujo setup (perfil)

1. `GET /api/2fa/setup` → genera secret via `Google2FA::generateSecretKey()`, encripta con `encrypt()` y guarda en `two_factor_secret`, genera QR SVG via `Google2FA::getQRCodeSvg()`, genera recovery codes (8 códigos de 10 chars), los devuelve en respuesta pero NO persiste recovery codes (se muestran al usuario una vez)

2. `POST /api/2fa/confirm` → recibe `{ code }`, usa `Google2FA::verifyKey($secret, $code)` para verificar, si ok → `two_factor_confirmed_at = now()`, respuesta incluye `recovery_codes[]`

3. `POST /api/2fa/disable` → recibe `{ password }`, verifica `Hash::check($password, $user->password)`, si ok → limpia `two_factor_secret` y `two_factor_confirmed_at`

### Flujo login

1. `POST /api/login` — si `two_factor_confirmed_at` no es null:
   - NO crear sesión
   - Generar `two_factor_token` (JWT simple con `user_id`, `exp` 5 min, firmado con una clave en `config/security.php`)
   - Responder `{ requires_2fa: true, two_factor_token: "..." }`

2. `POST /api/2fa/verify`:
   - Recibe `{ two_factor_token, code }`
   - Decodifica/valida token (firma + exp)
   - Busca usuario por `user_id` del token
   - `Google2FA::verifyKey($secret, $code)` — tolerancia de 1 período (1 min) para desfase horario
   - Si válido → `Auth::login($user)`, regenera sesión, responde `UserResource`
   - Si no válido → error 422

### Modelo

- User: getter `twoFactorEnabled` (`two_factor_confirmed_at !== null`)
- UserResource: incluir `has_2fa_enabled` (bool)

### Logging

Usar eventos ya definidos en `UsersAuthenticationLog`:
- `2fa_enabled` — cuando se confirma setup
- `2fa_disabled` — cuando se desactiva
- `mfa_code_verified` — verify exitoso
- `mfa_code_failed` — verify fallido

## Frontend

### Flujo login con 2FA

1. `authService.login()` → recibe `{ requires_2fa: true, two_factor_token }`
2. `authMutations.loginMutation()` → detecta `requires_2fa`, guarda `two_factor_token` en memoria (no Redux), retorna `{ ok: true, requires_2fa: true }`
3. `useAuthLoginFormContext()` → en `if (result.ok)`, si `result.requires_2fa`, redirige a `/{lang}/two-steps?token=...`
4. Página `/{lang}/two-steps` → input de 6 dígitos, envía a `POST /api/2fa/verify`
5. Si verify ok → `dispatch(loginSuccess(user))`, redirige a home
6. Si error → muestra mensaje, permite reintentar

### Página two-steps

- Ruta ya pública en middleware.ts
- Input de 6 dígitos (MUI `OtpInput` o `TextField` con maxLength=6)
- Botón "Verificar" → llama a `POST /api/2fa/verify`
- Link "Volver al login" → redirige a `/{lang}/login`
- Si token expira → mostrar "Tu sesión ha expirado, inicia sesión de nuevo"

### Perfil — Seguridad

- `TwoFactorAuthenticationCard.jsx`: conectar a API real
  - Si `user.has_2fa_enabled === false`: botón "Activar" → GET `/api/2fa/setup` → muestra QR SVG + recovery codes + input de confirmación + botón "Confirmar" → POST `/api/2fa/confirm`
  - Si `user.has_2fa_enabled === true`: mostrar "2FA activa" + botón "Desactivar" → confirmación + input de contraseña → POST `/api/2fa/disable`

### Servicios frontend

Crear `src/services/auth/2fa/`:
- `twoFactorService.js` — `setup()`, `confirm(code)`, `disable(password)`, `verify(token, code)`
- Barrel export

## Recovery Codes

- 8 códigos alfanuméricos de 10 chars generados en backend
- Se muestran UNA VEZ al confirmar setup
- Se almacenan hasheados en `two_factor_recovery_codes` (TEXT, nullable) — migración extra
- Al verificar: si TOTP falla, probar recovery codes con `Hash::check()`
- Si recovery code usado: eliminarlo de la lista
- Si se quedan sin recovery codes: deben desactivar y reactivar 2FA

## Seguridad

- `two_factor_secret` guardado encriptado (`encrypt()` / `decrypt()`)
- `two_factor_token` firmado con HMAC-SHA256, expira en 5 min
- Rate limiting en `/api/2fa/verify`: 5 intentos por minuto
- Rate limiting en `/api/2fa/confirm`: 5 intentos por minuto
- Rate limiting en `/api/2fa/disable`: 3 intentos por minuto
- Log de todos los eventos via `UsersAuthenticationLog`

## No incluir (scope)

- Backup codes via email/SMS (solo recovery codes)
- 2FA obligatoria por rol
- Recordar dispositivo (trust this browser)
- WebAuthn / hardware keys
