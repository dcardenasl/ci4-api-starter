# Las Capas de Arquitectura

Este documento explica cada capa en detalle: Controller, DTO, Service, Model y Entity.

---

## Capa Controller

**Ubicación:** `app/Controllers/Api/V1/`

**Responsabilidad:** Manejar peticiones HTTP, mapear datos a DTOs y delegar a los servicios.

### ApiController (Clase Base)

Todos los controllers API extienden `ApiController.php`. Este provee `handleRequest(...)` para:
1. Recolectar datos de entrada (GET/POST/JSON/Files).
2. Instanciar y validar Request DTOs cuando se pasa `DTO::class`.
3. Capturar excepciones y normalizar respuesta JSON.
4. Estandarizar salida (incluyendo paginación canónica cuando aplica).

### Patrón: Orquestación Declarativa

```php
public function create(): ResponseInterface
{
    return $this->handleRequest('store', UserStoreRequestDTO::class);
}
```

---

## Capa DTO (Data Transfer Objects)

**Ubicación:** `app/DTO/`

**Responsabilidad:** Garantizar la integridad de los datos, seguridad de tipos y estabilidad del contrato.

### Request DTOs (Entrada)
- **Auto-validación:** Llaman a `validateOrFail()` en su constructor.
- **Inmutabilidad:** Clases `readonly` de PHP 8.2.
- **Seguridad de Tipos:** Los tipos de propiedades se aplican estrictamente.

### Response DTOs (Salida)
- **Sanitización:** Definen explícitamente qué campos se exponen al cliente.
- **Estandarización:** Normalizan datos de Entidades/Arrays (ej. formateo de fechas).
- **Documentación:** Contienen atributos OpenAPI `#[OA\Property]`.

---

## Capa Service

**Ubicación:** `app/Services/`

**Responsabilidad:** Lógica de negocio, orquestación y operaciones de dominio.

### Patrón de Servicio Puro

```php
class UserService extends BaseCrudService implements UserServiceInterface
{
    public function store(DataTransferObjectInterface $request): DataTransferObjectInterface
    {
        // Lógica de negocio + transacción
        // Retorno DTO tipado (sin ApiResponse aquí)
    }
}
```

Contrato base de `BaseCrudService`:
- `index()` retorna `PaginatedResponseDTO` (`DataTransferObjectInterface`).
- `show()/store()/update()` retornan DTOs de recurso.
- `destroy()` retorna `bool` y `ApiController` normaliza la respuesta.

### Reglas
- ✅ **Desacoplado:** SIN conocimiento de `ApiResponse`, códigos `status` o JSON.
- ✅ **Tipado:** Usa Request DTOs como entrada y DTOs como salida en lecturas.
- ✅ **Comandos:** Usa `OperationResult` para outcomes tipo comando.
- ✅ **Excepcional:** Usa excepciones personalizadas para todos los estados de error.
- ❌ NO manejo directo de petición/respuesta.

---

## Capas Model y Entity

**Ubicación:** `app/Models/` y `app/Entities/`

**Responsabilidad:** Operaciones de base de datos y representación de datos.

- **Models:** Usan el Query Builder de CodeIgniter 4 y traits `Auditable`.
- **Entities:** Representan una fila individual. Deben convertirse a **Response DTOs** antes de salir de la capa de servicio para evitar fugas accidentales de datos.

---

## Resumen

| Capa | Responsabilidad | Entrada | Salida |
|-------|----------------|-------|--------|
| **Controller** | E/S HTTP y Mapeo | Request | Respuesta JSON |
| **DTO** | Contrato y Validación | Arreglo Raw | Objeto Tipado |
| **Service** | Lógica de Negocio | DTO/Objeto | DTO/OperationResult/bool |
| **Model** | Operaciones de BD | Arreglo/Entidad | Entidad/Objeto |
| **Entity** | Representación de Fila | Fila de BD | Propiedades Tipadas |

**Flujo:**
```
Petición → Controller → [RequestDTO] → Service → Model → Entity → [ResponseDTO] → ApiResponse → JSON
```

Cada capa es **testeable independientemente** y tiene **una sola razón para cambiar**.
