# Guía de Pruebas (Testing Guidelines)

Este documento describe las mejores prácticas y estándares para escribir pruebas en el proyecto CodeIgniter 4 API Starter.

## 1. Pruebas de API (Feature Tests)

Para todas las pruebas que realicen peticiones HTTP a la API, se **debe** heredar de `Tests\Support\ApiTestCase` en lugar de usar `CIUnitTestCase` directamente.

### Ventajas de `ApiTestCase`:
- **Aislamiento Automático:** Limpia las globales de PHP (`$_POST`, `$_GET`, etc.) y resetea el servicio `Request` entre llamadas consecutivas en un mismo test.
- **Base de Datos Limpia:** Incluye `DatabaseTestTrait` configurado para refrescar las migraciones.
- **Helpers Útiles:** Proporciona `getResponseJson($result)` para obtener el cuerpo de la respuesta fácilmente.

### Ejemplo de uso:

```php
class MyControllerTest extends ApiTestCase
{
    public function testConsecutiveCalls(): void
    {
        // Primera llamada (POST)
        $this->post('/api/v1/resource', ['name' => 'Original']);
        
        // Segunda llamada (PUT) - ApiTestCase asegura que 'name' => 'Original' 
        // no se filtre en esta petición si no lo enviamos.
        $result = $this->put('/api/v1/resource/1', ['name' => 'Updated']);
        
        $result->assertStatus(200);
    }
}
```

## 2. Configuración Dinámica (Environment)

Si necesitas cambiar variables de entorno (usando `env()`) durante un test, asegúrate de actualizar todas las fuentes de las que CodeIgniter lee:

```php
protected function setUp(): void
{
    parent::setUp();
    putenv('MY_VAR=value');
    $_ENV['MY_VAR'] = 'value';
    $_SERVER['MY_VAR'] = 'value';
}
```

## 3. Validaciones y Modelos

- **Placeholders:** Si usas placeholders en las reglas de validación de un modelo (ej: `is_unique[table.field,id,{id}]`), el campo del placeholder (ej: `id`) **debe** tener su propia regla de validación en el array `$validationRules` (aunque sea `permit_empty`) para que el motor de validación de CI4 lo reconozca.
- **Tipado de Entidades:** Evita usar `casts` a `int` o `bool` en las `Entities` para campos que pueden ser `NULL` en la base de datos, ya que PHP los convertirá a `0` o `false`.

## 4. Pruebas de Integración y Base de Datos

- **Conexiones:** Si el código que estás probando abre su propia conexión a la base de datos (como servicios externos o gestores de colas), asegúrate de que usen el grupo de conexión `tests` durante las pruebas para que puedan ver los datos dentro de la transacción del test.
- **Reset de Configuración:** Si cambias configuraciones globales, usa `\CodeIgniter\Config\Factories::reset('config')` para asegurar que las nuevas instancias carguen los valores actualizados.
