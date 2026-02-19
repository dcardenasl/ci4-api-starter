# Flujo de registro y aprobacion

## Descripcion general

Este flujo cubre dos sub-flujos relacionados: **auto-registro** (el usuario se registra mediante el endpoint publico) y **aprobacion por admin** (un administrador activa la cuenta). Existe una tercera via para **usuarios invitados por el admin**, quienes reciben directamente un enlace para configurar su contrasena sin pasar por la aprobacion.

Tras el auto-registro, la cuenta queda en estado `pending_approval`. Tambien se envia un correo de verificacion. Solo cuando un admin llama al endpoint de aprobacion la cuenta pasa a `active` y permite el inicio de sesion.

---

## Sub-flujo A — Auto-registro

### Paso a paso

1. El cliente envia `POST /api/v1/auth/register` con `email`, `password`, `first_name`, `last_name` y opcionalmente `client_base_url`.
2. El filtro `authThrottle` aplica un limite de velocidad por IP.
3. `AuthController::register()` llama a `handleRequest('register')`.
4. `ApiController::collectRequestData()` combina todas las fuentes del request y sanea la entrada.
5. `AuthService::register()` valida:
   - `password` no puede estar vacio (`BadRequestException` si falta).
   - Reglas de validacion completas via `validateOrFail($data, 'auth', 'register')`: email unico, fuerza de contrasena, campos requeridos.
6. El usuario se inserta con:
   - `role = 'user'` (hardcodeado — el auto-registro nunca puede establecer un rol privilegiado)
   - `status = 'pending_approval'`
   - Contrasena hasheada con `PASSWORD_BCRYPT`
7. Se llama a `VerificationService::sendVerificationEmail(userId, {client_base_url})`:
   - Genera un token aleatorio, establece expiacion en 24 horas.
   - Construye el enlace de verificacion usando `ResolvesWebAppLinks::buildVerificationUrl(token, clientBaseUrl)` — apunta al **frontend** (p.ej. `http://miapp.com/verify-email?token=X`), no a la API.
   - Encola el correo de verificacion (no bloqueante; los errores se registran pero no fallan el registro).
8. Respuesta: `201 Created` con `{ user, message }` indicando al usuario que verifique su correo y aguarde la aprobacion del admin.

### Diagrama

```mermaid
sequenceDiagram
    autonumber
    participant Cliente
    participant authThrottle as Routes / authThrottle
    participant Controller as AuthController
    participant ApiCtrl as ApiController
    participant AuthSvc as AuthService
    participant VerifSvc as VerificationService
    participant EmailSvc as EmailService
    participant Frontend
    participant DB

    Cliente->>authThrottle: POST /api/v1/auth/register {email, password, first_name, last_name, client_base_url?}
    authThrottle-->>Cliente: 429 Too Many Requests (si supera el limite)
    authThrottle->>Controller: request pasa

    Controller->>ApiCtrl: handleRequest('register')
    ApiCtrl->>ApiCtrl: collectRequestData() + sanitizeInput()
    ApiCtrl->>AuthSvc: register(data)

    AuthSvc->>AuthSvc: validateOrFail(data, 'auth', 'register')

    alt Falla la validacion (contrasena debil / email duplicado)
        AuthSvc-->>ApiCtrl: throw ValidationException (422)
        ApiCtrl-->>Cliente: 422 {status: error, errors: {...}}
    end

    AuthSvc->>DB: INSERT users {email, password_hash, role='user', status='pending_approval'}
    DB-->>AuthSvc: userId

    AuthSvc->>VerifSvc: sendVerificationEmail(userId, {client_base_url})
    VerifSvc->>DB: UPDATE users SET email_verification_token, verification_token_expires
    VerifSvc->>VerifSvc: buildVerificationUrl(token, clientBaseUrl)
    Note over VerifSvc: El enlace apunta al FRONTEND<br/>p.ej. http://miapp.com/verify-email?token=X
    VerifSvc->>EmailSvc: queueTemplate('verification', email, {enlace, expira})
    EmailSvc-->>VerifSvc: encolado (no bloqueante)
    VerifSvc-->>AuthSvc: ok (errores registrados, no relanzados)

    AuthSvc-->>ApiCtrl: ApiResponse::created({user}, 'Verifica correo + pendiente de aprobacion')
    ApiCtrl-->>Cliente: 201 Created {status: success, data: {user}, message: ...}

    Note over Frontend: El usuario hace clic en el enlace del correo<br/>→ El frontend llama GET /api/v1/auth/verify-email?token=X<br/>(ver EMAIL-VERIFICATION-FLOW)
```

---

## Sub-flujo B — Aprobacion por admin

### Paso a paso

1. El admin lista los usuarios pendientes: `GET /api/v1/users?filter[status][eq]=pending_approval` (requiere `jwtauth` + `roleauth:admin`).
2. El admin aprueba un usuario: `POST /api/v1/users/{id}/approve`.
3. El filtro `jwtauth` decodifica el token Bearer y establece `user_id` y `role` en el request. El filtro `roleauth:admin` verifica el rol.
4. `UserController` llama a `handleRequest('approve', ['id' => $id])`.
5. `UserService::approve()` ejecuta clausulas de guarda de estado:
   - Usuario no encontrado → `NotFoundException` (404).
   - `status = 'active'` → `ConflictException` (409) "ya aprobado".
   - `status = 'invited'` → `ConflictException` (409) "no se pueden aprobar usuarios invitados".
   - `status != 'pending_approval'` → `ConflictException` (409) estado invalido.
6. Actualiza el usuario: `status = 'active'`, `approved_at = now()`, `approved_by = adminId`.
7. Encola un correo de notificacion de aprobacion (no bloqueante).
8. Respuesta: `200 OK` con el objeto de usuario actualizado.
9. El usuario ya puede iniciar sesion mediante el flujo de login estandar.

### Diagrama

```mermaid
sequenceDiagram
    autonumber
    participant Admin
    participant jwtauth as Routes / jwtauth + roleauth:admin
    participant Controller as UserController
    participant ApiCtrl as ApiController
    participant UserSvc as UserService
    participant EmailSvc as EmailService
    participant DB

    Admin->>jwtauth: POST /api/v1/users/{id}/approve (Bearer <token>)

    jwtauth->>jwtauth: Decodificar JWT + verificar role = admin
    alt Token invalido o rol insuficiente
        jwtauth-->>Admin: 401 / 403
    end

    jwtauth->>Controller: request pasa

    Controller->>ApiCtrl: handleRequest('approve', {id})
    ApiCtrl->>ApiCtrl: collectRequestData() — agrega user_id desde JWT
    ApiCtrl->>UserSvc: approve({id, user_id: adminId})

    UserSvc->>DB: SELECT user WHERE id = ?
    DB-->>UserSvc: UserEntity

    alt Usuario no encontrado
        UserSvc-->>ApiCtrl: throw NotFoundException (404)
        ApiCtrl-->>Admin: 404 {status: error, message: Usuario no encontrado}
    end

    alt status = 'active'
        UserSvc-->>ApiCtrl: throw ConflictException (409)
        ApiCtrl-->>Admin: 409 {status: error, message: Ya aprobado}
    end

    alt status = 'invited'
        UserSvc-->>ApiCtrl: throw ConflictException (409)
        ApiCtrl-->>Admin: 409 {status: error, message: No se pueden aprobar usuarios invitados}
    end

    alt status != 'pending_approval'
        UserSvc-->>ApiCtrl: throw ConflictException (409)
        ApiCtrl-->>Admin: 409 {status: error, message: Estado de aprobacion invalido}
    end

    UserSvc->>DB: UPDATE users SET status='active', approved_at=now, approved_by=adminId
    UserSvc->>EmailSvc: queueTemplate('account-approved', email, {...})
    EmailSvc-->>UserSvc: encolado

    UserSvc-->>ApiCtrl: ApiResponse::success(usuarioActualizado)
    ApiCtrl-->>Admin: 200 OK {status: success, data: {user}}
```

---

## Maquina de estados del usuario

```mermaid
stateDiagram-v2
    [*] --> pending_approval : POST /api/v1/auth/register (auto-registro)
    [*] --> invited : POST /api/v1/users (admin crea usuario)

    pending_approval --> active : POST /api/v1/users/{id}/approve (accion del admin)
    invited --> active : POST /api/v1/auth/reset-password (usuario establece primera contrasena)

    active --> active : login estandar / uso normal

    note right of pending_approval
        La verificacion de correo
        tambien se requiere (si esta
        habilitada)
    end note

    note right of invited
        Las cuentas creadas por el admin
        omiten pending_approval — pasan
        directamente a active cuando se
        usa el enlace de invitacion
    end note
```

---

## Resolucion de URL para enlaces de correo (ResolvesWebAppLinks)

Los correos de verificacion e invitacion contienen enlaces que apuntan al **frontend**, no a la API. El trait `ResolvesWebAppLinks` resuelve la URL base de la siguiente manera:

1. Si `client_base_url` esta presente en el cuerpo del request, validarla:
   - Debe ser una URL `http` o `https` valida (en produccion se requiere `https`).
   - Debe estar en la lista de permitidos `WEBAPP_ALLOWED_BASE_URLS`.
   - Si no esta en la lista: registra una advertencia y usa `WEBAPP_BASE_URL` como respaldo.
2. Si `client_base_url` esta ausente o es invalida, se usa como respaldo `WEBAPP_BASE_URL`.
3. Si `WEBAPP_BASE_URL` tambien falta, se usa `app.baseURL` como respaldo final.

Variables de entorno requeridas:

```env
WEBAPP_BASE_URL=http://localhost:8081
WEBAPP_ALLOWED_BASE_URLS=http://localhost:8081,https://miapp.com
```

---

## Validaciones clave

- `role` siempre se fuerza a `'user'` en el auto-registro — el cliente no puede establecer un rol privilegiado.
- La fuerza de la contrasena y el email unico son validados por `validateOrFail()` usando reglas de validacion de CI4.
- Los errores en el envio del correo de verificacion no revierten el registro; se registran silenciosamente.
- Solo los usuarios con `status = 'pending_approval'` pueden ser aprobados. Otros estados producen un `ConflictException`.
- Los endpoints del admin requieren tanto el filtro `jwtauth` como `roleauth:admin`.

---

## Casos de error

| Condicion | Excepcion | HTTP | Notas |
|-----------|-----------|------|-------|
| Contrasena ausente | `BadRequestException` | 400 | Antes de las reglas de validacion |
| Falla la validacion (contrasena debil, email duplicado) | `ValidationException` | 422 | Desde `validateOrFail()` |
| Limite de velocidad superado (registro) | — (filtro) | 429 | Filtro authThrottle |
| Usuario no encontrado (aprobacion) | `NotFoundException` | 404 | — |
| Usuario ya activo | `ConflictException` | 409 | Ya aprobado |
| Usuario es invited, no pending | `ConflictException` | 409 | Flujo incorrecto |
| Token invalido o rol no es admin (aprobacion) | — (filtro) | 401 / 403 | jwtauth / roleauth |

---

## Ejemplos

Auto-registro:

```bash
curl -X POST http://localhost:8080/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "email": "usuario@ejemplo.com",
    "password": "MiClave123!",
    "first_name": "Ana",
    "last_name": "Garcia",
    "client_base_url": "https://miapp.com"
  }'
```

Aprobacion por admin:

```bash
curl -X POST http://localhost:8080/api/v1/users/42/approve \
  -H "Authorization: Bearer TOKEN_DE_ACCESO_ADMIN"
```
