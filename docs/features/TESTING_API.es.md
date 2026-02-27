# Testing de API (Playbook)

Este documento es un playbook funcional para ejecutar pruebas API en nuevos módulos.

## Propósito

Asegurar que cada funcionalidad entregue contratos HTTP estables y comportamiento alineado con la arquitectura.

## Checklist de implementación

1. Usar `Tests\Support\ApiTestCase` para pruebas de endpoints.
2. Cubrir caminos de éxito y error en unit tests de servicios.
3. Agregar integration tests cuando haya lógica de persistencia/consultas.
4. Validar explícitamente autenticación y autorización.
5. Asegurar contrato JSON (`status`, `message`, `data/errors`).

## Comandos

```bash
php spark tests:prepare-db
vendor/bin/phpunit --configuration=phpunit.xml --no-coverage --testdox
composer quality
```

## Criterios de aceptación

1. Feature tests validan la forma final del payload API.
2. Unit tests validan comportamiento de dominio sin acoplar HTTP.
3. Integration tests validan comportamiento de DB/model cuando aplique.
4. Los tests de arquitectura permanecen en verde.

## Referencia técnica canónica

1. Estrategia de testing y constraints de arquitectura: `../architecture/TESTING.md`.
2. Reglas y patrones prácticos de pruebas: `../tech/TESTING_GUIDELINES.md`.
