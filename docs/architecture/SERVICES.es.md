# Capa de Servicio e Inversión de Control (IoC)

La capa de Servicio contiene toda la lógica de negocio y orquesta las operaciones del dominio. En esta arquitectura, los servicios están **organizados por dominios** y siguen un **patrón de composición**.

---

## Organización Orientada a Dominios

Los servicios se agrupan por dominio funcional para garantizar una alta cohesión y evitar las "Clases Todopoderosas".

- `app/Services/Auth/`: Login, Registro, OAuth.
- `app/Services/Tokens/`: JWT, Tokens de Refresco, Revocación.
- `app/Services/Users/`: Gestión de Identidad y RBAC.
- `app/Services/Files/`: Orquestación de almacenamiento y procesamiento de archivos.
- `app/Services/System/`: Infraestructura (Auditoría, Email, Métricas).

---

## Patrón de Composición de Servicios

Los servicios grandes se descomponen en componentes especializados inyectados a través del constructor. Esto mantiene a los orquestadores ligeros y a la lógica testeable.

### Componentes de Soporte (Support):
- **Handlers**: Encapsulan lógica de múltiples pasos (ej. `GoogleAuthHandler`).
- **Guards**: Centralizan aserciones de seguridad (ej. `UserRoleGuard`).
- **Mappers**: Manejan transformaciones de entidades a DTOs (ej. `AuthUserMapper`).

---

## Patrón de Servicio Puro

Los servicios son **Puros** y **Sin Estado** (Stateless). NO deben tener conocimiento de HTTP o JSON.

- **Entrada:** DTOs específicos o tipos escalares.
- **Salida:** DTOs, Entidades o `OperationResult`.
- **Errores:** Lanzados como Excepciones personalizadas que implementan `HasStatusCode`.

### Ejemplo (Composición)

```php
// app/Services/Auth/AuthService.php
public function login(LoginRequestDTO $request): LoginResponseDTO
{
    $user = $this->userModel->where('email', $request->email)->first();
    
    // Delegar seguridad al Guard
    $this->userAccessPolicy->assertCanAuthenticate($user);
    
    // Delegar creación de sesión al Manager
    $session = $this->sessionManager->generateSessionResponse(
        $this->userMapper->mapAuthenticated($user)
    );
    
    return LoginResponseDTO::fromArray($session);
}
```

---

## Registro de Servicios (IoC)

Todos los servicios y sus dependencias deben registrarse en `app/Config/Services.php`.

```php
// app/Config/Services.php
public static function authService(bool $getShared = true)
{
    if ($getShared) {
        return static::getSharedInstance('authService');
    }
    
    return new \App\Services\Auth\AuthService(
        static::userModel(),
        static::jwtService(),
        static::refreshTokenService(),
        new \App\Services\Auth\Support\AuthUserMapper(),
        new \App\Services\Auth\Support\SessionManager(
            static::jwtService(),
            static::refreshTokenService()
        )
    );
}
```

---

## Beneficios

1. **Testabilidad:** Mockea los componentes de soporte para testear orquestadores de forma aislada.
2. **Inmutabilidad:** La mayoría de los servicios son `readonly class` (PHP 8.2+), evitando efectos secundarios.
3. **Descubribilidad:** Carpetas de dominio claras facilitan la localización de la lógica.
