# Patrones de Diseño

**Referencia Rápida** - Para detalles completos ver `../ARCHITECTURE.md` sección 16.

## Patrones Utilizados

### Service Layer Pattern
Separar lógica de negocio de la capa de presentación.

### Repository Pattern (via Model)
Abstraer acceso a base de datos.

### Data Transfer Object (via Entity)
Transportar datos entre capas con comportamiento.

### Factory Pattern (Services Container)
Centralizar creación de objetos con dependencias.

### Template Method Pattern (ApiController)
Definir esqueleto de algoritmo, subclases proveen detalles.

### Strategy Pattern (Storage Drivers)
Definir familia de algoritmos intercambiables (local vs S3).

### Chain of Responsibility (Filters)
Pasar petición a través de cadena de manejadores.

### Decorator Pattern (Model Traits)
Añadir responsabilidades dinámicamente (Filterable, Searchable).

**Ver `../ARCHITECTURE.md` sección 16 para explicaciones detalladas de patrones y ejemplos.**
