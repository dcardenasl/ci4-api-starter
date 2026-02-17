## MVP de Reportes de Negocio/Usuarios (API JSON)

### Resumen
Implementar un módulo `reports` orientado a negocio con 5 endpoints admin-only, usando datos ya existentes en `users`, `request_logs`, `files`, `audit_logs`, `refresh_tokens` y `token_blacklist`.  
El MVP entrega métricas listas para dashboard, con ventanas móviles (`7d`, `30d`, `90d`) y comparación contra período anterior, sin agregar infraestructura externa.

### Objetivo y Criterios de Éxito
- Objetivo: dar visibilidad de adquisición, activación, retención y uso de funcionalidades.
- Éxito funcional:
1. Responder métricas consistentes para ventanas móviles.
2. Permitir filtrar por `window` y agrupar por `day|week`.
3. Entregar comparación `current` vs `previous`.
4. Mantener protección admin (`roleauth:admin`) y formato `ApiResponse::success`.
- Éxito técnico:
1. Sin romper endpoints actuales.
2. Cobertura de pruebas Feature + Unit + Integration.
3. Tiempo de respuesta razonable para dataset mediano (objetivo <500ms local en `30d`).

### Cambios de API/Interfaces (públicos)
Agregar rutas en `app/Config/Routes.php` dentro de `jwtauth` + `roleauth:admin`:

1. `GET /api/v1/reports/overview`
- Query params: `window=7d|30d|90d` (default `30d`)
- Respuesta: tarjetas resumen de funnel, actividad, retención rápida, archivos y seguridad de cuentas.

2. `GET /api/v1/reports/users/funnel`
- Query params: `window=7d|30d|90d` (default `30d`)
- Respuesta:
  - `invited`
  - `registered`
  - `verified_email`
  - `approved`
  - `activation_rate` (approved/registered)
  - `avg_time_to_approval_hours`

3. `GET /api/v1/reports/users/activity`
- Query params: `window=7d|30d|90d`, `group_by=day|week` (default `day`)
- Respuesta:
  - `new_users`
  - `active_users` (usuarios con requests en la ventana)
  - `activity_by_role`
  - `activity_by_auth_type` (`oauth` vs `password`)
  - `timeseries[]`

4. `GET /api/v1/reports/users/retention`
- Query params: `window=30d|90d` (default `90d`), `cohort=week` (default `week`)
- Respuesta:
  - cohortes por semana de alta
  - `% retained_d7`
  - `% retained_d30`
  - `dormant_users` (sin actividad en últimos 30 días)

5. `GET /api/v1/reports/files/usage`
- Query params: `window=7d|30d|90d`, `group_by=user|mime|day` (default `user`)
- Respuesta:
  - `uploads_count`
  - `total_size_bytes`
  - `avg_file_size_bytes`
  - `top_mime_types`
  - `top_users_by_storage`

Formato de salida:
- `ApiResponse::success(data, null, meta)`
- `meta` incluirá `window`, `since`, `until`, `comparison` (`current`, `previous`, `delta_pct` cuando aplique).

### Diseño Interno (implementación)
1. Crear `app/Controllers/Api/V1/ReportsController.php`
- Métodos: `overview`, `usersFunnel`, `usersActivity`, `usersRetention`, `filesUsage`.
- Validar query params y mapear defaults.
- Delegar cálculo a servicio.

2. Crear `app/Services/ReportsService.php` + interfaz
- Orquestar consultas por dominio.
- Normalizar ventanas y rangos de fechas.
- Resolver comparación contra período anterior automáticamente.

3. Extender modelos o usar métodos específicos:
- `UserModel`: altas, aprobaciones, verificación, segmentación por rol/proveedor.
- `RequestLogModel`: usuarios activos y actividad temporal.
- `FileModel`: volumen de uploads y almacenamiento.
- `AuditLogModel`: eventos administrativos relevantes para overview.
- `RefreshTokenModel`/`TokenBlacklistModel`: estado de sesiones para señal de seguridad.

4. Validación de filtros
- `window`: solo `7d|30d|90d`.
- `group_by`: `day|week`.
- `cohort`: `week`.
- En error: `ApiResponse::validationError` con HTTP 422.

5. Performance mínima
- Reusar índices existentes.
- Limitar listas “top” a 10 elementos por default.
- Calcular agregaciones en SQL (COUNT/SUM/AVG/GROUP BY), no en PHP sobre colecciones grandes.

### Escenarios y Casos de Prueba
Feature tests (controlador/rutas):
1. Admin autenticado obtiene `200` en todos los endpoints.
2. Usuario no admin recibe `403`.
3. Sin JWT recibe `401`.
4. Parámetros inválidos (`window=365d`) retornan `422`.
5. Respuesta mantiene contrato (`status`, `data`, `meta`).

Unit tests (servicio):
1. Cálculo correcto de `since/until` para `7d/30d/90d`.
2. Comparación `current vs previous` y `delta_pct` con divisor cero controlado.
3. Funnel con datos parciales (sin verificados o sin aprobados).
4. Retención cuando no hay cohortes (respuesta vacía consistente).

Integration tests (modelos/DB):
1. Agregados de actividad por rol y auth_type.
2. Retención D7/D30 con fixtures controlados.
3. Cálculo de almacenamiento por usuario y MIME.

### Edge Cases y Fallos
1. Ventana sin datos: retornar ceros/arreglos vacíos, nunca error 500.
2. Usuarios soft-deleted:
- Para actividad histórica: incluirlos en métricas históricas (si hubo eventos en ventana).
- Para listados top: marcar con `user_id` y omitir PII extra.
3. Verificación inexistente (`email_verified_at` null): contar como no verificado.
4. Delta porcentual con base 0: devolver `null` en `delta_pct` y campo `delta_abs`.

### Compatibilidad y Rollout
1. No requiere migraciones para MVP.
2. No rompe endpoints actuales de `metrics`/`audit`.
3. Documentar en OpenAPI y Postman después de implementar endpoints.
4. Activar gradualmente en frontend consumiendo primero `/reports/overview`.

### Supuestos y Defaults (cerrados)
1. Audiencia inicial: admins internos (no usuario final).
2. Consumo: API JSON para dashboard.
3. Modelo temporal: rolling windows con comparación automática.
4. Ventana por defecto: `30d`.
5. Agrupación por defecto: `day`.
6. Límite de “top” por defecto: 10.
7. Moneda/unidades: tamaños en bytes, tiempos en ms/horas según métrica.
