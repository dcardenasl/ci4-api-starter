# Las Capas de Arquitectura

Este documento explica cada capa en detalle: Controller, DTO, Service, Model y Entity.

---

## Capa Controller

**Ubicación:** `app/Controllers/Api/V1/`

**Responsabilidad:** Manejar peticiones HTTP, mapear datos a DTOs y delegar a los servicios.

### ApiController (Clase Base)

Todos los controllers API extienden `ApiController.php`:

```php
abstract class ApiController extends Controller
{
    protected function handleRequest(string|callable $target, ?array $params = null): ResponseInterface
    {
        try {
            if (is_callable($target)) {
                $result = $target();
            } else {
                $data = $this->collectRequestData($params);
                $result = $this->getService()->$target($data);
            }

            // La normalización ocurre aquí automáticamente
            return $this->respond($result);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }
}
```

### Patrón: DTO-First

```php
public function login(): ResponseInterface
{
    // 1. Obtener DTO validado desde la petición
    $dto = $this->getDTO(LoginRequestDTO::class);

    // 2. Delegar al servicio usando un closure
    return $this->handleRequest(
        fn() => $this->getService()->login($dto)
    );
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
class UserService implements UserServiceInterface
{
    public function store(array $data): UserResponseDTO
    {
        // 1. Lógica
        $userId = $this->userModel->insert($data);
        $user = $this->userModel->find($userId);

        // 2. Retornar objeto tipado (¡SIN ApiResponse aquí!)
        return UserResponseDTO::fromArray($user->toArray());
    }
}
```

### Reglas
- ✅ **Desacoplado:** SIN conocimiento de `ApiResponse`, códigos `status` o JSON.
- ✅ **Tipado:** Usa DTOs para parámetros y valores de retorno.
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
| **Service** | Lógica de Negocio | DTO/Objeto | DTO/Entidad |
| **Model** | Operaciones de BD | Arreglo/Entidad | Entidad/Objeto |
| **Entity** | Representación de Fila | Fila de BD | Propiedades Tipadas |

**Flujo:**
```
Petición → Controller → [RequestDTO] → Service → Model → Entity → [ResponseDTO] → ApiResponse → JSON
```

Cada capa es **testeable independientemente** y tiene **una sola razón para cambiar**.
