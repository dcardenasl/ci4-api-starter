# Validation System

**Quick Reference** - For complete details see `../ARCHITECTURE.md` section 9.

## Three Levels of Validation

```
1. Input Validation (app/Validations/)
   ↓
2. Model Validation (Model::$validationRules)
   ↓
3. Business Rules (Service methods)
```

## 1. Input Validation

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

## 2. Model Validation

```php
// app/Models/ProductModel.php
protected $validationRules = [
    'name' => [
        'rules'  => 'required|max_length[255]|is_unique[products.name]',
        'errors' => ['required' => 'Name is required'],
    ],
];
```

## 3. Business Rule Validation

```php
// In service
if ($this->isProductNameTaken($data['name'])) {
    throw new ValidationException('Name already exists', [
        'name' => 'Product name is already in use'
    ]);
}
```

## Common Validation Rules

- `required` - Field must be present
- `permit_empty` - Allow null/empty
- `max_length[N]`, `min_length[N]`
- `is_unique[table.field]`
- `valid_email`
- `numeric`, `integer`
- `greater_than[N]`, `less_than[N]`
- `regex_match[pattern]`

**See `../ARCHITECTURE.md` section 9 for complete validation patterns.**
