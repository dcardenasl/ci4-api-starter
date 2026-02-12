# Guía de Extensión

**Referencia Rápida** - Para detalles completos ver `../ARCHITECTURE.md` sección 19.

## Añadir un Nuevo Recurso CRUD

Proceso completo paso a paso:

1. **Crear migration** - `php spark make:migration CreateProductsTable`
2. **Crear entity** - `app/Entities/ProductEntity.php`
3. **Crear model** - `app/Models/ProductModel.php` (con traits, validación)
4. **Crear interface** - `app/Interfaces/ProductServiceInterface.php`
5. **Crear service** - `app/Services/ProductService.php`
6. **Registrar service** - Añadir a `app/Config/Services.php`
7. **Crear controller** - `app/Controllers/Api/V1/ProductController.php`
8. **Añadir rutas** - Actualizar `app/Config/Routes.php`
9. **Añadir archivos de idioma** - `app/Language/{lang}/Products.php`
10. **Escribir tests** - Pruebas Unit, Integration, Feature

## Inicio Rápido

Ver [`../GETTING_STARTED.md`](../GETTING_STARTED.md) para un recorrido completo con ejemplos de código.

## Añadir Filtros Personalizados

```php
// 1. Crear filtro
// app/Filters/MyFilter.php
class MyFilter implements FilterInterface { ... }

// 2. Registrar alias
// app/Config/Filters.php
public array $aliases = [
    'myfilter' => \App\Filters\MyFilter::class,
];

// 3. Usar en rutas
$routes->group('', ['filter' => 'myfilter'], function ($routes) {
    // ...
});
```

## Añadir Excepciones Personalizadas

```php
// app/Exceptions/PaymentRequiredException.php
class PaymentRequiredException extends ApiException
{
    protected int $statusCode = 402;
}
```

**Ver `../ARCHITECTURE.md` sección 19 para ejemplos completos de extensión.**
