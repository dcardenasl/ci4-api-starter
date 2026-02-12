# Advanced Querying

**Quick Reference** - For complete details see `../ARCHITECTURE.md` section 13.

## QueryBuilder

Models with `Filterable` and `Searchable` traits support advanced querying.

## Features

### Filtering
```bash
GET /api/v1/products?filter[price][gte]=100&filter[name][like]=%phone%
```

**Operators:** `eq`, `neq`, `gt`, `gte`, `lt`, `lte`, `like`, `in`

### Searching
```bash
GET /api/v1/products?search=laptop
```
Searches across `$searchableFields` using FULLTEXT or LIKE.

### Sorting
```bash
GET /api/v1/products?sort=-created_at,name
```
Prefix `-` for descending. Only `$sortableFields` allowed.

### Pagination
```bash
GET /api/v1/products?page=2&limit=50
```

## Usage in Services

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

**See `../ARCHITECTURE.md` section 13 for complete QueryBuilder implementation.**
