# Reglas de Contribución (Template)

Estas reglas definen el estándar mínimo de contribución para este repositorio y para proyectos derivados que usen este template.

## 1. No negociables de arquitectura

1. Controllers delgados (`ApiController`, `handleRequest()`, `getDTO()`).
2. Services con lógica de negocio pura; sin manejo HTTP.
3. Transferencia entre capas con DTOs (`readonly`, request DTOs validados).
4. Clases runtime (`Commands`, `Filters`) resuelven dependencias por contenedor/helpers (`Services::*`, `model()`).

## 2. Entregables obligatorios por cambio

1. Cambios alineados con `docs/template/ARCHITECTURE_CONTRACT.md`.
2. Paridad de idioma en `app/Language/en` y `app/Language/es` cuando aplique.
3. Paridad EN/ES en documentación nueva o modificada dentro de `docs/`.
4. Tests actualizados en la suite correspondiente (`Unit`, `Feature`, `Integration`).
5. Ubicación documental alineada con `docs/DOCUMENTATION_SCOPE.md` (sin duplicación entre secciones).

## 3. Quality gates antes de merge

1. `composer cs-check`
2. `composer phpstan`
3. `php scripts/i18n-check.php`
4. `php scripts/docs-i18n-parity-check.php`
5. `vendor/bin/phpunit`
6. Recomendado: `composer quality` para ejecutar toda la cadena.

## 4. Checklist de aceptación de PR

1. Alcance e impacto documentados explícitamente.
2. Impacto de contratos internos/externos declarado.
3. Acciones de migración documentadas si hubo cambios de contrato.
4. No introducir TODOs sin resolver ni atajos temporales.
