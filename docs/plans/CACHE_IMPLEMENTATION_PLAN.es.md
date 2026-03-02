# Plan: Mitigación de Concurrencia y Contención en BD

### CI4 API Starter — Documento Técnico

---

## Resumen Ejecutivo

Reducir lecturas concurrentes en `users`, eliminar race conditions en refresh tokens, blacklist y password reset, y hacer rate limiting atómico. La consistencia inmediata se logra mediante **versionado de estado de autenticación** (`auth_version`) comparado en el JWT sin consulta a BD en cada request. Se soporta Redis externo con fallback robusto a BD.

---

## 1. Cambios en APIs / Interfaces / Tipos

### JWT Claims (nuevos campos)

| Campo | Tipo     | Descripción                    |
| ----- | -------- | ------------------------------ |
| `av`  | `int`    | `auth_version` del usuario     |
| `st`  | `string` | Estado de cuenta (opcional)    |
| `ev`  | `bool`   | `email_verified_at` (opcional) |

### Firma actualizada

```php
// JwtServiceInterface
public function encode(int $userId, string $role, array $extraClaims = []): string;
```

### Nuevo servicio

**`UserAuthStateService`** — Lectura y actualización de `auth_version` con caché.

### Variables de entorno nuevas

| Variable                    | Default        | Descripción                                  |
| --------------------------- | -------------- | -------------------------------------------- |
| `AUTH_VERSION_CACHE_TTL`    | `86400`        | TTL del caché en segundos                    |
| `AUTH_VERSION_CACHE_PREFIX` | `user_auth_v_` | Prefijo de clave en Redis/caché              |
| `RATE_LIMIT_STORE`          | `db`           | Backend para rate limiting (`redis` \| `db`) |
| `REDIS_URL`                 | —              | URL de Redis externo (opcional)              |

---

## 2. Cambios de Esquema — Migraciones

### Tabla `users`

```sql
ALTER TABLE users
  ADD COLUMN auth_version INT UNSIGNED NOT NULL DEFAULT 1,
  ADD INDEX idx_auth_version (auth_version); -- opcional
```

### Tabla `password_resets`

```sql
ALTER TABLE password_resets
  ADD UNIQUE INDEX uq_email (email),
  ADD INDEX idx_created_at (created_at);
```

### Nueva tabla `rate_limits` (fallback DB)

```sql
CREATE TABLE rate_limits (
  `key`     VARCHAR(128) PRIMARY KEY,
  count     INT UNSIGNED NOT NULL,
  reset_at  DATETIME NOT NULL,
  INDEX idx_reset_at (reset_at)
);
```

---

## 3. Implementación Detallada

### 3.1 JWT sin lectura de `users` en cada request

**`UserAuthStateService`**

```php
// Leer versión (con caché)
public function getAuthVersion(int $userId): int
{
    $cacheKey = PREFIX . $userId;
    if ($cached = cache($cacheKey)) return $cached;
    $version = $this->userModel->getAuthVersion($userId); // consulta DB
    cache()->save($cacheKey, $version, TTL);
    return $version;
}

// Incrementar versión (invalida tokens)
public function bumpAuthVersion(int $userId): void
{
    $this->userModel->incrementAuthVersion($userId); // UPDATE ... auth_version + 1
    $newVersion = $this->userModel->getAuthVersion($userId);
    cache()->save(PREFIX . $userId, $newVersion, TTL);
}
```

**`JwtAuthFilter`** — lógica actualizada

```
1. Decodificar token
2. Si no hay `av` en claims → 401 (forzar re-login)
3. currentAv = UserAuthStateService->getAuthVersion(uid)
4. Si token.av ≠ currentAv → 401 "token revocado/obsoleto"
5. Validar política de cuenta usando claims (st, ev) — sin consulta a DB
```

> **Beneficio:** Se elimina `userModel->find()` en cada request autenticado.

---

### 3.2 Puntos de cambio que invalidan tokens

Llamar a `UserAuthStateService->bumpAuthVersion($userId)` en:

| Operación                                             | Servicio                                                 |
| ----------------------------------------------------- | -------------------------------------------------------- |
| Cambio de `role`, `status`, `email` o `password`      | `UserService::update`                                    |
| Aprobación de usuario                                 | `UserService::approve`                                   |
| Verificación de email                                 | `VerificationService::verifyEmail`                       |
| Reseteo de contraseña                                 | `PasswordResetService::resetPassword`                    |
| Reactivación de usuario eliminado                     | `PasswordResetService::reactivateDeletedUserForApproval` |
| Login con Google (si modifica `oauth_provider`/email) | `AuthService::loginWithGoogleToken`                      |

---

### 3.3 Refresh token rotation con bloqueo real

Actualizar `HandlesTransactions::wrapInTransaction` para aceptar una conexión explícita:

```php
public function wrapInTransaction(callable $cb, ?BaseConnection $db = null): mixed
```

En `RefreshTokenService::refreshAccessToken`:

```php
$this->wrapInTransaction(function() {
    // findActiveForUpdate() dentro de la misma transacción/conexión
}, $this->refreshTokenModel->db);
```

---

### 3.4 Token blacklist sin race condition

Reemplazar patrón `existsByJti + insert` por operación atómica:

```sql
-- MySQL/MariaDB
INSERT IGNORE INTO token_blacklist (jti, ...) VALUES (...);
-- Si falla por UNIQUE duplicado → considerar éxito (ya revocado)
```

> **Requisito:** índice `UNIQUE` en `token_jti`.

---

### 3.5 Password reset sin TOCTOU

**`sendResetLink`** — UPSERT atómico:

```sql
INSERT INTO password_resets (email, token, created_at)
VALUES (?, ?, NOW())
ON DUPLICATE KEY UPDATE
  token = VALUES(token),
  created_at = VALUES(created_at);
```

**`resetPassword`** — transacción con delete condicional:

```sql
-- Paso 1: intentar consumir el token
DELETE FROM password_resets
WHERE email = ? AND token = ? AND created_at > NOW() - INTERVAL 60 MINUTE;

-- Paso 2: si affectedRows == 0 → token inválido o ya usado → abortar
-- Paso 3: si affectedRows == 1 → actualizar password
```

> **Garantía:** uso único del token incluso con requests concurrentes.

---

### 3.6 Rate limiting atómico

**Si `RATE_LIMIT_STORE=redis`:**

```
INCR key
EXPIRE key ttl   ← atómico vía pipeline/script Lua
```

**Si `RATE_LIMIT_STORE=db`:**

```sql
INSERT INTO rate_limits (`key`, count, reset_at)
VALUES (?, 1, NOW() + INTERVAL ? SECOND)
ON DUPLICATE KEY UPDATE
  count    = IF(reset_at < NOW(), 1, count + 1),
  reset_at = IF(reset_at < NOW(), NOW() + INTERVAL ? SECOND, reset_at);

-- Luego verificar: SELECT count FROM rate_limits WHERE `key` = ?
```

> **Elimina** el race condition del patrón `get → check → save`.

---

## 4. Tests y Escenarios

### 4.1 JWT `auth_version`

| Escenario             | Resultado esperado      |
| --------------------- | ----------------------- |
| Token con `av` viejo  | `401`                   |
| Token con `av` actual | `200`                   |
| Token sin campo `av`  | `401` (forzar re-login) |

### 4.2 Refresh token concurrente

- Simular dos refresh simultáneos del mismo token.
- **Solo uno** debe retornar nuevo access token; el otro debe recibir `401`.

### 4.3 Blacklist race

- Dos revocaciones simultáneas del mismo JTI.
- **Ambas** deben retornar éxito sin error de duplicado.

### 4.4 Password reset concurrente

| Escenario                              | Resultado esperado            |
| -------------------------------------- | ----------------------------- |
| Dos resets con mismo token simultáneos | Solo uno cambia la contraseña |
| `sendResetLink` concurrente            | Solo un token válido al final |

### 4.5 Rate limit

- 2+ requests simultáneos desde el mismo origen.
- El contador debe ser exacto y el límite respetado sin over-count.

---

## 5. Supuestos y Configuración por Defecto

| Parámetro    | Valor / Decisión                               |
| ------------ | ---------------------------------------------- |
| BD           | MySQL / MariaDB                                |
| Hosting      | Shared (sin Docker ni Redis local)             |
| Redis        | Externo y opcional; fallback automático a BD   |
| Tráfico      | Bajo                                           |
| Consistencia | Inmediata — tokens inválidos al cambiar estado |
| Migraciones  | Aceptadas                                      |
