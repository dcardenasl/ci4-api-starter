# Service Container (IoC)


## Registering Services

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

## Usage

```php
// In controllers
$service = \Config\Services::productService();

// Shared instance (singleton per request)
$service1 = \Config\Services::productService();
$service2 = \Config\Services::productService();
// $service1 === $service2 (true)

// New instance
$newService = \Config\Services::productService(false);
```

## Dependency Graph

Services can depend on other services:

```php
public static function authService(bool $getShared = true)
{
    return new \App\Services\AuthService(
        new \App\Models\UserModel(),
        static::jwtService(),           // Dependency
        static::refreshTokenService(),  // Dependency
        static::verificationService()   // Dependency
    );
}
```

