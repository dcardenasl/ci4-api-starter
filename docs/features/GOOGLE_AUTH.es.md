# Autenticación con Google

Resumen de integración de login social con Google.

## Flujo

1. El cliente envía ID token de Google.
2. El servicio valida identidad con `GoogleIdentityService`.
3. Se aplica lógica de usuario activo/pendiente.
4. Resultado de comando mediante `OperationResult`.

## Reglas

1. Sin lógica HTTP en servicio.
2. Controlador delega con `handleRequest(...)`.
3. Tests para escenarios válidos e inválidos.

## Referencia

Documento completo en inglés: `GOOGLE_AUTH.md`.
