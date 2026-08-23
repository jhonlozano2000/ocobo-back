# Checklist de Seguridad para Producción — OCOBO SGDEA

> Valores verificados en auditoría (23 ago 2026). El entorno local (.env actual) es válido
> para desarrollo; ANTES de pasar a producción revisar cada punto.
>
> Referencias: OWASP Top 10, ISO 27001 A.9 (Control de Acceso), A.10 (Criptografía).

---

## 1. Aplicación Laravel

| # | Variable / Config | Desarrollo (actual) | Producción requerida |
|---|---|---|---|
| 1.1 | `APP_ENV` | `local` | `production` |
| 1.2 | `APP_DEBUG` | `true` | **`false`** — evita exposición de stack traces, rutas y credenciales |
| 1.3 | `APP_URL` | http://ocobo-back.test | Dominio HTTPS real |
| 1.4 | `APP_KEY` | generada localmente | Regenerar en servidor prod; NUNCA copiar la de dev |

## 2. Sesiones y Cookies

| # | Variable / Config | Desarrollo (actual) | Producción requerida |
|---|---|---|---|
| 2.1 | `SESSION_SECURE_COOKIE` | `false` | **`true`** — cookies solo sobre HTTPS |
| 2.2 | `SESSION_DOMAIN` | `ocobo.test` | Dominio real sin `http://` |
| 2.3 | `SESSION_LIFETIME` | `480` (8h) | Considerar 30–60 min (ISO 27001 A.9.4); hoy es decisión documentada |
| 2.4 | `SESSION_DRIVER` | `file` | Recomendado: `database` o `redis` (permite revocar sesiones activas desde admin) |
| 2.5 | `SANCTUM_STATEFUL_DOMAINS` | incluye localhost | **Quitar localhost/127.0.0.1** — solo dominio(s) reales del SPA |
| 2.6 | Cookie flags (config/session.php) | http_only=true ✓ · same_site=lax ✓ | Verificar que sigan así |

## 3. CORS

| # | Variable / Config | Desarrollo (actual) | Producción requerida |
|---|---|---|---|
| 3.1 | `CORS_ALLOWED_ORIGINS` | localhost + ocobo.test | **Solo dominios reales**, nunca `*`; sin IPs hardcoded |
| 3.2 | `supports_credentials` | `true` ✓ | Mantener solo con orígenes específicos |

## 4. Frontend (Next.js)

| # | Ítem | Estado | Acción prod |
|---|---|---|---|
| 4.1 | Sesión NextAuth (`src/libs/auth.js`) | maxAge alineado a SESSION_LIFETIME (8h) ✓ | Si se baja SESSION_LIFETIME en prod, ajustar aquí también |
| 4.2 | Tokens en localStorage | No hay ✓ | Mantener prohibido (solo cookies HttpOnly) |
| 4.3 | CSP | `unsafe-inline`/`unsafe-eval` | Endurecer con nonces cuando sea viable (limitación Next/MUI conocida) |
| 4.4 | `NEXT_PUBLIC_*` | Sin secretos ✓ | Nunca exponer claves API con este prefijo |
| 4.5 | Rewrites proxy a backend | Activos (Infinity) | En prod evaluar servir API por dominio propio y eliminar proxy si no se necesita |

## 5. Límites de Subida de Archivos

| # | Capa | Valor | Nota |
|---|---|---|---|
| 5.1 | Proxy Next.js | `Infinity` (decisión) | El control real vive en el backend |
| 5.2 | PHP `post_max_size` / `upload_max_filesize` | 2G dev | Definir techo explícito en prod según negocio |
| 5.3 | Backend `config_varias.max_tamano_archivo` | Configurable en Otras configuraciones | Fuente única de verdad para los 4 módulos (PQRS incluido desde ago 2026) |
| 5.4 | `config_varias.tipos_archivos_permitidos` | Configurable | Revisar lista mínima necesaria |

## 6. Verificaciones Post-Despliegue

```bash
# APP_DEBUG desactivado: un endpoint inexistente NO debe mostrar stack trace
curl -s https://DOMINIO/api/ruta-inexistente | grep -i "stack"   # debe devolver vacío

# Cookies seguras: Set-Cookie debe incluir Secure y HttpOnly
curl -sI https://DOMINIO/sanctum/csrf-cookie | grep -i set-cookie

# Headers de seguridad presentes
curl -sI https://DOMINIO | grep -Ei "x-frame-options|strict-transport|content-security"

# CORS: un origen ajeno debe ser rechazado
curl -sI -H "Origin: https://evil.example" https://DOMINIO/api/pqrs -X OPTIONS
```

---

*Última auditoría completa: 23 agosto 2026 (commits `427d713`, `5745d3f`, `e4750e4`).*
