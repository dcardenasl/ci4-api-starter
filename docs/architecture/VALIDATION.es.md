# Sistema de Validación


## Tres Niveles de Validación

```
1. Validación de Entrada (app/Validations/)
   ↓
2. Validación de Modelo (Model::$validationRules)
   ↓
3. Reglas de Negocio (métodos Service)
```

## 1. Validación de Entrada

```php
// app/Validations/ProductValidation.php
class ProductValidation extends BaseValidation
{
    public function getRules(string $action): array
    {
        return match ($action) {
            'store' => [
                'name'  => 'required|max_length[255]',
                'price' => 'required|numeric|greater_than[0]',
            ],
            default => [],
        };
    }
}
```

## 2. Validación de Modelo

```php
// app/Models/ProductModel.php
protected $validationRules = [
    'name' => [
        'rules'  => 'required|max_length[255]|is_unique[products.name]',
        'errors' => ['required' => 'Name is required'],
    ],
];
```

## 3. Validación de Reglas de Negocio

```php
// En service
if ($this->isProductNameTaken($data['name'])) {
    throw new ValidationException('Name already exists', [
        'name' => 'Product name is already in use'
    ]);
}
```

## Reglas de Validación Comunes

- `required` - El campo debe estar presente
- `permit_empty` - Permitir null/vacío
- `max_length[N]`, `min_length[N]`
- `is_unique[table.field]`
- `valid_email`
- `numeric`, `integer`
- `greater_than[N]`, `less_than[N]`
- `regex_match[pattern]`

