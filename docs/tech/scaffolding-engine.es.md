# ⚡ Motor de Scaffolding (CRUD Error-Cero)

El Motor de Scaffolding es la herramienta de productividad central de este starter kit. Automatiza la creación de módulos CRUD 100% funcionales y por capas, asegurando que la **Base de Datos**, los **DTOs**, los **Servicios** y **OpenAPI** estén siempre sincronizados.

## 🏗️ La Arquitectura Modular

En lugar de un único comando de "plantilla", nuestro motor utiliza generadores especializados coordinados por un **Orquestador**:

1.  **DtoGenerator**: Crea DTOs de Request (Index, Create, Update) y Response con tipos readonly de PHP 8.2 y anotaciones de Swagger.
2.  **MigrationGenerator**: Produce migraciones de CI4 con tipos de DB, restricciones y claves foráneas correctas.
3.  **ModelEntityGenerator**: Configura la Entidad (`$casts`) y el Modelo (`$allowedFields`, `$searchableFields`, `$filterableFields`).
4.  **ServiceGenerator**: Genera la Interfaz de Servicio y la capa de lógica de negocio.
5.  **ControllerGenerator**: Orquesta todo con el trait `HasCrudActions` y la documentación de endpoints de OpenAPI.

## 🧠 Cableado Inteligente (ConfigWireman)

El motor "conecta" automáticamente el nuevo módulo al sistema:
- **Trait de Dominio:** Crea `{Dominio}DomainServices.php` si no existe.
- **Registro de Servicio:** Inyecta el nuevo Servicio y su Mapper en el trait del dominio.
- **Servicios Principales:** Registra el nuevo trait de dominio en `Config/Services.php` mediante `use` y `require_once`.

## 🧬 Mapeo de Tipos (El Cerebro)

Utilizamos un **TypeMapper** unificado para asegurar la consistencia. Por ejemplo, la definición de un campo `decimal` resulta en:
- **DB:** `DECIMAL(10,2)`
- **PHP:** `float`
- **Validación:** `required|decimal`
- **OpenAPI:** `type: "number", format: "float"`

## 🛡️ Barreras de Seguridad

- **Detección de Conflictos:** El orquestador verifica si alguno de los ~10 archivos ya existe. Si encuentra un conflicto, **no se escribe nada** y se informa del error detalladamente.
- **Verificación de Sintaxis:** Cada generación es verificada automáticamente usando PHP Lint (`php -l`) a través de un Test de Humo especializado.

## 🛠️ Ejemplos de Uso

### 1. Modo Interactivo (Ideal para humanos)
```bash
php spark make:crud Cliente --domain Ventas
```
*El comando te preguntará por cada nombre de campo, tipo y opciones.*

### 2. Modo CLI (Ideal para automatización)
```bash
php spark make:crud Producto --fields="nombre:string:required|searchable,precio:decimal:required,categoria_id:fk:categorias"
```
*Las opciones como `searchable`, `filterable`, `required` o `nullable` pueden combinarse.*
