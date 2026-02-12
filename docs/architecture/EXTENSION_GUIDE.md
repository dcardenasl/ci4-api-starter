# Extension Guide

**Quick Reference** - For complete details see `../ARCHITECTURE.md` section 19.

## Adding a New CRUD Resource

Complete step-by-step process:

1. **Create migration** - `php spark make:migration CreateProductsTable`
2. **Create entity** - `app/Entities/ProductEntity.php`
3. **Create model** - `app/Models/ProductModel.php` (with traits, validation)
4. **Create interface** - `app/Interfaces/ProductServiceInterface.php`
5. **Create service** - `app/Services/ProductService.php`
6. **Register service** - Add to `app/Config/Services.php`
7. **Create controller** - `app/Controllers/Api/V1/ProductController.php`
8. **Add routes** - Update `app/Config/Routes.php`
9. **Add language files** - `app/Language/{lang}/Products.php`
10. **Write tests** - Unit, Integration, Feature tests

## Quick Start

See [`../GETTING_STARTED.md`](../GETTING_STARTED.md) for a complete walkthrough with code examples.

## Adding Custom Filters

```php
// 1. Create filter
// app/Filters/MyFilter.php
class MyFilter implements FilterInterface { ... }

// 2. Register alias
// app/Config/Filters.php
public array $aliases = [
    'myfilter' => \App\Filters\MyFilter::class,
];

// 3. Use in routes
$routes->group('', ['filter' => 'myfilter'], function ($routes) {
    // ...
});
```

## Adding Custom Exceptions

```php
// app/Exceptions/PaymentRequiredException.php
class PaymentRequiredException extends ApiException
{
    protected int $statusCode = 402;
}
```

**See `../ARCHITECTURE.md` section 19 for complete extension examples.**
