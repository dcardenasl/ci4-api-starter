# Checklist de Bootstrap de Módulo

Checklist para crear módulos nuevos de forma consistente.

## 1. Scaffold

1. Ejecutar `php spark make:crud {Resource} --domain {Domain} --route {slug}`.
2. Confirmar archivos generados en capas, tests y migraciones (`Database/Migrations`).
3. Ejecutar `php spark module:check {Resource} --domain {Domain}` y corregir los faltantes reportados.
4. Considerar que `module:check` no valida contenido de migraciones, solo su existencia.

## 2. Persistencia

1. Ejecutar `php spark migrate` para aplicar la migración generada por el scaffold.
2. Ajustar modelo (`allowedFields`, validación, filtros/búsqueda/orden).
3. Ajustar entidad (`casts`, fechas) para que coincidan con el esquema de base de datos.

## 3. DTOs

1. Completar `Index/Create/Update` Request DTOs.
2. Completar Response DTO y `fromArray()`.
3. Mantener mensajes y reglas localizadas.

## 4. Service + Controller

1. Servicio puro con contratos DTO/`OperationResult`.
2. Usar `GenericRepository` por defecto para CRUD estándar.
3. Crear repositorio dedicado (`*RepositoryInterface`) solo para consultas de dominio no triviales.
4. Controller delgado con `handleRequest(...)`.
5. Rutas y filtros de acceso.

## 5. Calidad

1. Pruebas Unit/Feature/Integration.
2. `composer quality` en verde.
3. Sin TODOs pendientes en archivos generados.
4. Validado contra `docs/template/CRUD_FROM_ZERO.es.md`.
