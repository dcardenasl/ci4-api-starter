# CRUD Playbook (Arquitectura Millonaria)

## 1) Preparación

- Inspeccionar ejemplos reales (DTO-First):
  - `app/Controllers/Api/V1/Users/UserController.php`
  - `app/DTO/Request/Users/UserIndexRequestDTO.php`
  - `app/DTO/Response/Users/UserResponseDTO.php`
  - `app/Services/UserService.php`
- Confirmar filtros/rutas en `app/Config/Routes.php`.

## 2) Orden recomendado de implementación

1. **Base de Datos:** Migration, Entity, Model.
2. **Contrato (DTOs):** Request DTO (entrada) y Response DTO (salida con OpenAPI attributes).
3. **Lógica:** Service Interface, Service Implementation (Pure Service).
4. **Infraestructura:** Services config, Language files.
5. **Transporte:** Controller (extends `ApiController`), Routes.
6. **Documentación:** `php spark swagger:generate`.
7. **Calidad:** Tests (Unit, Feature) y `composer quality`.

## 3) Capa DTO (Mandatoria)

- **Request DTO:** Clase `readonly` de PHP 8.2. Validar en constructor vía `validateOrFail($data, 'domain', 'action')`.
- **Response DTO:** Clase `readonly` de PHP 8.2. Incluir atributos `#[OA\Property]`. Mapear desde array/entity vía método estático `fromArray()`.

## 4) Servicio Puro

- NO usar `ApiResponse` ni códigos HTTP.
- Recibir DTOs específicos.
- Retornar DTOs o Entidades.
- Lanzar excepciones personalizadas para errores.

## 5) Controlador Moderno

- Extender `ApiController`.
- Usar `getDTO()` para la entrada.
- Delegar vía closure a `handleRequest(fn() => ...)`.
- La normalización de salida (snake_case) es automática.

## 6) Definición de Terminado (Done)

- [ ] DTOs inmutables creados y validados.
- [ ] Servicio desacoplado de la capa API.
- [ ] Atributos OpenAPI integrados en el Response DTO.
- [ ] `php spark swagger:generate` sin errores.
- [ ] Tests unitarios validando retornos de DTO.
- [ ] Tests de feature validando estructura JSON final.
- [ ] `composer quality` en verde (PHPStan, CS-Fixer).
