# Checklist de Bootstrap de Módulo

Checklist para crear módulos nuevos de forma consistente.

## 1. Scaffold

1. Ejecutar `php spark make:crud {Resource} --domain {Domain} --route {slug}`.
2. Confirmar archivos generados en capas y tests.

## 2. Persistencia

1. Crear migraciones.
2. Ajustar modelo (`allowedFields`, validación, filtros/búsqueda/orden).
3. Ajustar entidad (`casts`, fechas).

## 3. DTOs

1. Completar `Index/Create/Update` Request DTOs.
2. Completar Response DTO y `fromArray()`.
3. Mantener mensajes y reglas localizadas.

## 4. Service + Controller

1. Servicio puro con contratos DTO/`OperationResult`.
2. Controller delgado con `handleRequest(...)`.
3. Rutas y filtros de acceso.

## 5. Calidad

1. Pruebas Unit/Feature/Integration.
2. `composer quality` en verde.
3. Sin TODOs pendientes en archivos generados.
