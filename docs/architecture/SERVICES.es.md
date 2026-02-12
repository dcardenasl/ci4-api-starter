# Contenedor de Servicios (IoC)


## Registrar Servicios

```php
// app/Config/Services.php
public static function productService(bool $getShared = true)
{
    if ($getShared) {
        return static::getSharedInstance('productService');
    }

    return new \App\Services\ProductService(
        new \App\Models\ProductModel()
    );
}
```

## Uso

```php
// En controllers
$service = \Config\Services::productService();

// Instancia compartida (singleton por petición)
$service1 = \Config\Services::productService();
$service2 = \Config\Services::productService();
// $service1 === $service2 (true)

// Nueva instancia
$newService = \Config\Services::productService(false);
```

## Grafo de Dependencias

Los servicios pueden depender de otros servicios:

```php
public static function authService(bool $getShared = true)
{
    return new \App\Services\AuthService(
        new \App\Models\UserModel(),
        static::jwtService(),           // Dependencia
        static::refreshTokenService(),  // Dependencia
        static::verificationService()   // Dependencia
    );
}
```

