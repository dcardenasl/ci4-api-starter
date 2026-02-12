# Design Patterns

**Quick Reference** - For complete details see `../ARCHITECTURE.md` section 16.

## Patterns Used

### Service Layer Pattern
Separate business logic from presentation layer.

### Repository Pattern (via Model)
Abstract database access.

### Data Transfer Object (via Entity)
Transport data between layers with behavior.

### Factory Pattern (Services Container)
Centralize object creation with dependencies.

### Template Method Pattern (ApiController)
Define algorithm skeleton, subclasses provide details.

### Strategy Pattern (Storage Drivers)
Define family of interchangeable algorithms (local vs S3).

### Chain of Responsibility (Filters)
Pass request through chain of handlers.

### Decorator Pattern (Model Traits)
Add responsibilities dynamically (Filterable, Searchable).

**See `../ARCHITECTURE.md` section 16 for detailed pattern explanations and examples.**
