# Testing de API

Guía rápida de pruebas para endpoints HTTP.

## Objetivos

1. Validar contratos de respuesta.
2. Validar autenticación y autorización.
3. Validar estados semánticos (200/201/202/4xx/5xx).

## Enfoque recomendado

1. Feature tests para JSON final.
2. Unit tests para lógica de servicio/DTOs.
3. Integration tests para modelos y persistencia.

## Comandos

```bash
php spark tests:prepare-db
vendor/bin/phpunit --configuration=phpunit.xml --no-coverage --testdox
composer quality
```

## Referencia

Documento completo en inglés: `TESTING_API.md`.
