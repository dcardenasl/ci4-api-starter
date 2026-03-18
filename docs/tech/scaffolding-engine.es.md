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

## 🛠️ Cómo Usar (Guía de Uso)

El comando `make:crud` es el punto de entrada principal para construir nuevas funcionalidades. Puede usarse en dos modos:

### 1. Modo Interactivo (Guiado)
Recomendado para la mayoría de los desarrolladores, ya que asegura que no se omitan pasos ni opciones de campos.

```bash
php spark make:crud Cliente --domain Ventas
```

El CLI te guiará a través de:
1.  **Nombre del Campo**: (ej: `nombre`, `precio`, `estado`)
2.  **Tipo de Campo**: Elegir de una lista (string, int, fk, etc.)
3.  **Requerimientos**: ¿Es obligatorio? ¿Buscable? ¿Filtrable?
4.  **Claves Foráneas**: Si es tipo `fk`, te preguntará el nombre de la tabla destino.

### 2. Modo CLI (Directo)
Ideal para automatización o cuando ya tienes tu esquema definido.

```bash
php spark make:crud Producto --domain Catalogo --fields="nombre:string:required|searchable,precio:decimal:required|filterable,categoria_id:fk:categorias:required"
```

## 🧬 Sintaxis Detallada de Campos (`--fields`)

Al usar el modo CLI, la cadena de campos sigue este formato:
`nombre:tipo:opciones,nombre2:tipo2:opciones2`

### Tipos Soportados
- `string`: VARCHAR(255) estándar.
- `text`: Campo TEXT largo.
- `int`: INTEGER.
- `bool`: BOOLEAN (TINYINT 1).
- `decimal`: DECIMAL(10,2) mapeado a float.
- `email`: VARCHAR(255) con validación de email.
- `date`: DATE.
- `datetime`: DATETIME.
- `fk`: Clave foránea (BigInt Unsigned). Requiere nombre de tabla en opciones.
- `json`: Campo JSON para datos estructurados.

### Opciones de Campos (Separadas por `|`)
- `required`: El campo debe estar presente y no estar vacío.
- `nullable`: Permite explícitamente valores NULL.
- `searchable`: Habilita búsqueda parcial (`LIKE %query%`) en el endpoint Index.
- `filterable`: Habilita filtrado por coincidencia exacta en el endpoint Index.
- `fk:nombre_tabla`: **(Requerido para tipo `fk`)** Especifica la tabla de base de datos relacionada.

## 🚀 Flujo de Trabajo Post-Scaffolding

Después de ejecutar `make:crud`, sigue siempre estos tres pasos para finalizar tu módulo:

1.  **Verificar Registro**: Ejecuta `php spark module:check {Recurso} --domain {Dominio}`. Esto asegura que el servicio y los traits de dominio estén correctamente conectados en `Config/Services.php`.
2.  **Aplicar Cambios en la BD**: Ejecuta `php spark migrate`. El scaffold genera un archivo de migración en `app/Database/Migrations/`.
3.  **Sincronizar Documentación**: Ejecuta `php spark swagger:generate`. Esto lee los nuevos DTOs y archivos de documentación para actualizar `public/swagger.json`.

Tus nuevos endpoints de API estarán disponibles inmediatamente en `/api/v1/{ruta}`.
