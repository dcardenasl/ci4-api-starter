# Matriz de Alcance de Documentación

Esta matriz define dónde debe vivir cada tipo de documentación para evitar duplicaciones.

## Fuentes canónicas

1. `docs/architecture/*`: contratos de arquitectura, invariantes, patrones por capa y ciclo request/response.
2. `docs/tech/*`: detalle operativo e implementación de subsistemas (configuración, comportamiento runtime, troubleshooting).
3. `docs/features/*`: playbooks funcionales (qué implementar/cambiar), criterios de aceptación y enlaces a documentación canónica.
4. `docs/flows/*`: diagramas de secuencia y walkthroughs por escenario.
5. `docs/template/*`: contratos de gobernanza y quality gates para adopción del template.
6. `docs/release/*`: cierre de auditoría y trazabilidad de release.

## Reglas de autoría

1. No duplicar implementaciones técnicas completas en `docs/features/*` si ya existen en `docs/tech/*`.
2. No duplicar reglas de arquitectura en `docs/tech/*`; enlazar a `docs/architecture/*`.
3. Para cada tema nuevo, definir un archivo canónico y mantener el resto como referencia breve.
4. Mantener paridad EN/ES para toda documentación activa en `docs/`.

## Mapeo práctico

1. Configuración/troubleshooting del worker de colas: `docs/tech/QUEUE.md`.
2. Internals y headers de rate limiting: `docs/tech/rate-limiting.md`.
3. Estrategia y constraints de testing API: `docs/architecture/TESTING.md`.
4. Reglas/checklist práctico de pruebas API: `docs/tech/TESTING_GUIDELINES.md`.
5. Checklist de implementación funcional para queue/testing: `docs/features/*.md` como playbook + referencias.
