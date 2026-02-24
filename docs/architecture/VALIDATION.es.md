# Sistema de Validacion

Este proyecto usa una estrategia de validacion por capas con un contrato estricto dominio/accion.

## Capas de Validacion

1. Validacion de entrada (`app/Validations/*Validation.php`)
2. Validacion de modelo (`Model::$validationRules`)
3. Reglas de negocio (logica de servicios)

Cada capa tiene una responsabilidad distinta:
- Validacion de entrada: forma del request, formato y restricciones.
- Validacion de modelo: integridad de persistencia/DB.
- Reglas de negocio: decisiones de dominio (propiedad, estados, efectos secundarios).

## Contrato Dominio/Accion

La validacion se organiza por dominio (por ejemplo: `auth`, `user`, `file`, `token`, `audit`) y accion (`login`, `update`, `upload`, etc.).

Helpers principales:

```php
validateOrFail($data, 'auth', 'login');
$validation = getValidationRules('file', 'upload');
$errors = validateInputs($data, $validation['rules'], $validation['messages']);
```

Comportamiento importante:
- `validateOrFail()` ahora falla rapido si el dominio no esta registrado.
- `validateOrFail()` ahora falla rapido si la accion no existe para un dominio valido.
- Dominio/accion desconocidos lanzan `InvalidArgumentException` (error de configuracion/programacion).
- Input invalido del usuario lanza `ValidationException` (HTTP 422).

## Como se Aplica en Servicios

Se usan intencionalmente dos patrones:

1. `validateOrFail(...)` para flujo directo de 422.
2. `getValidationRules(...) + validateInputs(...)` cuando el servicio mapea errores a `BadRequestException` con contexto propio.

Ejemplos recientes en el codigo:
- Password reset usa `validateOrFail($data, 'auth', ...)`.
- User update usa `validateOrFail($data, 'user', 'update')`.
- Servicios de file/audit/token consumen reglas centralizadas via helpers.

## Notas de Validacion de Modelo

Las reglas del modelo deben evitar conflictos con updates parciales.

Ejemplo:
- La regla de email en `UserModel` usa `permit_empty|valid_email_idn|max_length[255]|is_unique[...]`.
- Campos requeridos para update deben forzarse en validacion de entrada (`user:update`), no como `required` global del modelo.

## Reglas Comunes en Uso

- `required`, `permit_empty`
- `is_natural_no_zero`
- `valid_email_idn`
- `valid_token[64]`
- `strong_password`
- `max_length[N]`, `min_length[N]`
