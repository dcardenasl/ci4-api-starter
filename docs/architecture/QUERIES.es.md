# Consultas Avanzadas

**Referencia Rápida** - Para detalles completos ver `../ARCHITECTURE.md` sección 13.

## QueryBuilder

Los modelos con traits `Filterable` y `Searchable` soportan consultas avanzadas.

## Características

### Filtrado
```bash
GET /api/v1/products?filter[price][gte]=100&filter[name][like]=%phone%
```

**Operadores:** `eq`, `neq`, `gt`, `gte`, `lt`, `lte`, `like`, `in`

### Búsqueda
```bash
GET /api/v1/products?search=laptop
```
Busca a través de `$searchableFields` usando FULLTEXT o LIKE.

### Ordenamiento
```bash
GET /api/v1/products?sort=-created_at,name
```
Prefijo `-` para descendente. Solo se permiten `$sortableFields`.

### Paginación
```bash
GET /api/v1/products?page=2&limit=50
```

## Uso en Services

```php
$builder = new QueryBuilder($this->productModel);

if (!empty($data['filter'])) {
    $builder->filter($data['filter']);
}

if (!empty($data['search'])) {
    $builder->search($data['search']);
}

if (!empty($data['sort'])) {
    $builder->sort($data['sort']);
}

$result = $builder->paginate($page, $limit);
```

**Ver `../ARCHITECTURE.md` sección 13 para implementación completa de QueryBuilder.**
