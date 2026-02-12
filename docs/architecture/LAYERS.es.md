# Las Cuatro Capas

Este documento explica cada capa en detalle: Controller, Service, Model y Entity.

---

## Capa Controller

**Ubicación:** `app/Controllers/Api/V1/`

**Responsabilidad:** Manejar peticiones y respuestas HTTP SOLAMENTE.

### ApiController (Clase Base)

Todos los controllers API extienden `ApiController.php`:

```php
abstract class ApiController extends Controller
{
    use ResponseTrait;

    protected string $serviceName = '';  // El hijo define esto

    protected function handleRequest(string $method, ?array $params = null): ResponseInterface
    {
        try {
            $data = $this->collectRequestData($params);
            $result = $this->getService()->$method($data);
            $status = $this->getSuccessStatus($method);
            return $this->respond($result, $status);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }
}
```

**Métodos clave:**
- `handleRequest()` - Método plantilla para todas las operaciones CRUD
- `collectRequestData()` - Combinar GET, POST, JSON, archivos, parámetros de ruta
- `sanitizeInput()` - Prevención XSS mediante `strip_tags()`
- `handleException()` - Convertir excepciones a respuestas HTTP

### Controllers Hijos

```php
class ProductController extends ApiController
{
    protected string $serviceName = 'productService';

    // ¡Eso es todo! Métodos heredados:
    // - index()   → handleRequest('index')
    // - show($id) → handleRequest('show', ['id' => $id])
    // - create()  → handleRequest('store')
    // - update($id) → handleRequest('update', ['id' => $id])
    // - delete($id) → handleRequest('destroy', ['id' => $id])
}
```

### Reglas
- ❌ NO lógica de negocio
- ❌ NO consultas a base de datos
- ❌ NO lógica de validación
- ✅ SOLO manejo HTTP
- ✅ Delegar al service
- ✅ Retornar respuesta HTTP

---

## Capa Service

**Ubicación:** `app/Services/`

**Responsabilidad:** Lógica de negocio, validación, orquestación.

### Patrón

```php
// Interface primero (app/Interfaces/)
interface ProductServiceInterface
{
    public function index(array $data): array;
    public function show(array $data): array;
    public function store(array $data): array;
    public function update(array $data): array;
    public function destroy(array $data): array;
}

// Implementación (app/Services/)
class ProductService implements ProductServiceInterface
{
    public function __construct(
        protected ProductModel $productModel
    ) {}

    public function store(array $data): array
    {
        // 1. Validar reglas del modelo
        if (!$this->productModel->validate($data)) {
            throw new ValidationException(
                'Validation failed',
                $this->productModel->errors()
            );
        }

        // 2. Reglas de negocio
        if ($this->isProductNameTaken($data['name'])) {
            throw new ConflictException('Product name already exists');
        }

        // 3. Procesar
        $productId = $this->productModel->insert($data);
        $product = $this->productModel->find($productId);

        // 4. Formatear respuesta
        return ApiResponse::created($product->toArray());
    }
}
```

### Responsabilidades

1. **Validar reglas de negocio** - Más allá de la validación del modelo
2. **Orquestar operaciones** - Coordinar múltiples modelos/servicios
3. **Transformar datos** - Preparar para persistencia o respuesta
4. **Lanzar excepciones** - Para todas las condiciones de error
5. **Formatear respuestas** - Usar métodos `ApiResponse::*()`

### Reglas
- ✅ Contiene TODA la lógica de negocio
- ✅ Lanza excepciones personalizadas
- ✅ Retorna arrays (via ApiResponse)
- ✅ Implementa interface
- ❌ NO código HTTP (no Request, no Response)
- ❌ NO consultas directas a base de datos (usar modelos)

---

## Capa Model

**Ubicación:** `app/Models/`

**Responsabilidad:** Operaciones de base de datos SOLAMENTE.

### Patrón

```php
class ProductModel extends Model
{
    use Filterable, Searchable;  // Características de consulta

    protected $table            = 'products';
    protected $returnType       = ProductEntity::class;
    protected $allowedFields    = ['name', 'price', 'description'];
    protected $useSoftDeletes   = true;
    protected $useTimestamps    = true;

    // Reglas de validación
    protected $validationRules = [
        'name' => [
            'rules'  => 'required|max_length[255]',
            'errors' => [
                'required'   => 'Name is required',
                'max_length' => 'Name too long',
            ],
        ],
        'price' => 'required|numeric|greater_than[0]',
    ];

    // Características de consulta
    protected array $searchableFields = ['name', 'description'];
    protected array $filterableFields = ['name', 'price', 'created_at'];
    protected array $sortableFields   = ['id', 'name', 'price'];
}
```

### Características Clave

**Validación Automática:**
```php
$id = $this->productModel->insert($data);  // Auto-valida
if (!$id) {
    $errors = $this->productModel->errors();  // Obtener errores
}
```

**Validación Manual:**
```php
if (!$this->productModel->validate($data)) {
    $errors = $this->productModel->errors();
}
```

**Timestamps:**
- Establece automáticamente `created_at` y `updated_at`

**Soft Deletes:**
- `delete()` establece `deleted_at` en lugar de eliminar la fila
- `find()` excluye soft-deleted por defecto
- `withDeleted()` incluye soft-deleted

**Traits:**
- `Filterable` - Añade método `applyFilters()`
- `Searchable` - Añade método `search()`

### Reglas
- ✅ Operaciones de base de datos mediante query builder
- ✅ Reglas de validación de datos
- ✅ Retornar entities
- ❌ NO lógica de negocio
- ❌ NO consultas SQL crudas

---

## Capa Entity

**Ubicación:** `app/Entities/`

**Responsabilidad:** Representación y transformación de datos.

### Patrón

```php
class ProductEntity extends Entity
{
    // Casteo de tipos
    protected $casts = [
        'id'    => 'integer',
        'price' => 'float',
        'stock' => 'integer',
    ];

    // Campos de fecha
    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    // Ocultar campos sensibles
    protected array $hidden = ['internal_notes', 'cost'];

    // Sobrescribir toArray() para ocultar campos
    public function toArray(bool $onlyChanged = false, bool $cast = true, bool $recursive = false): array
    {
        $data = parent::toArray($onlyChanged, $cast, $recursive);

        foreach ($this->hidden as $field) {
            unset($data[$field]);
        }

        return $data;
    }

    // Propiedades computadas
    public function isInStock(): bool
    {
        return $this->stock > 0;
    }

    public function getFormattedPrice(): string
    {
        return '$' . number_format($this->price, 2);
    }

    // Mutadores
    public function setName(string $value): self
    {
        $this->attributes['name'] = strtoupper($value);
        return $this;
    }
}
```

### Características

**Casteo de Tipos:**
```php
$product = $model->find(1);
$product->id;    // int (la BD retorna string)
$product->price; // float
$product->created_at; // CodeIgniter\I18n\Time
```

**Ocultando Campos:**
```php
$product->toArray();  // 'internal_notes' excluido
```

**Propiedades Computadas:**
```php
if ($product->isInStock()) {
    echo $product->getFormattedPrice();
}
```

### Reglas
- ✅ Casteo de tipos
- ✅ Propiedades computadas
- ✅ Ocultar campos sensibles
- ✅ Métodos helper de dominio
- ❌ NO operaciones de base de datos
- ❌ NO lógica de negocio (mantenerlo simple)

---

## Resumen

| Capa | Ubicación | Responsabilidad | Retorna | Puede Acceder |
|-------|----------|----------------|---------|------------|
| **Controller** | `Controllers/` | Manejo HTTP | `ResponseInterface` | Services |
| **Service** | `Services/` | Lógica de negocio | `array` (ApiResponse) | Models, otros Services |
| **Model** | `Models/` | Ops de BD | Entities | Database |
| **Entity** | `Entities/` | Representación de datos | `self`, `array` | Solo sus datos |

**Flujo:**
```
Controller → Service → Model → Entity
     ↓          ↓        ↓        ↓
   HTTP    Business   Database  Data
  Manejo    Lógica   Operaciones Repr.
```

Cada capa es **testeable independientemente** y tiene **una sola razón para cambiar**.
