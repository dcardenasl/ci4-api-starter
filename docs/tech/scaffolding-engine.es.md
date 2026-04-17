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

Dos puntos de entrada — elige el que encaje con tu entorno:

### 1. `bin/make-crud.sh` (recomendado, shell-safe)

Default preferido. Envuelve `php spark make:crud`, cita los pipes correctamente, ejecuta `composer cs-fix` automáticamente e imprime los comandos de seguimiento exactos.

```bash
bash bin/make-crud.sh Producto Catalogo \
  'nombre:string:required|searchable,precio:decimal:required|filterable,categoria_id:fk:categorias:required' \
  yes
```

Firma: `bash bin/make-crud.sh <Resource> <Domain> '<Fields>' [SoftDelete=yes] [Route]`

Úsalo en: pipelines de CI, Claude Code / asistentes IA, scripts de shell y cualquier contexto no-TTY.

### 2. `php spark make:crud` (interactivo)

Cuando quieras que el motor te consulte cada campo y sus modificadores:

```bash
php spark make:crud Cliente --domain Ventas
```

O la variante `--fields` explícita (con comillas simples para que el shell no consuma los pipes):

```bash
php spark make:crud Producto --domain Catalogo --fields='nombre:string:required|searchable,precio:decimal:required|filterable,categoria_id:fk:categorias:required'
```

> ⚠️ En entornos no-TTY `--fields` puede perder silenciosamente los modificadores separados por pipe y el motor cae a modo interactivo — que luego cuelga para siempre esperando input. Esta es exactamente la razón por la que existe `bin/make-crud.sh`.

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
