# 📄 plan.workflow.md — PDR Workflow

**Proyecto Base API REST — CodeIgniter 4 (Starter Kit)**

---

## 🧭 Proyecto

**Nombre:** CI4 API Base Starter  
**Objetivo:** Crear una base reutilizable para cualquier API REST en CodeIgniter 4 con JWT, roles, Docker, tests y CI.  
**Stack:** CI4, PHP 8.2+, MySQL, JWT, PHPUnit, Docker, GitHub Actions, Swagger  
**Arquitectura:** Capas (Controller / Service / Repository)  
**Formato respuesta:** JSON estándar `{ success, data, error }`

---

## 🔹 FASE 0 — Inicialización del Proyecto

### 🎯 Objetivo

Crear proyecto base vía Composer y preparar entorno.

### 📌 Tareas

- Crear proyecto con `composer create-project codeigniter4/appstarter`
- Configurar `.env` (app, db, baseURL)
- Instalar dependencias:
  - firebase/php-jwt
  - zircote/swagger-php
  - phpunit/phpunit (dev)

### 📦 Entregables

- Proyecto CI4 funcionando
- `.env` configurado
- `composer.json` actualizado

### ✅ Validación

- `php spark serve` responde
- `composer install` sin errores

---

## 🔹 FASE 1 — Arquitectura por Capas

### 🎯 Objetivo

Separar lógica en Controller / Service / Repository.

### 📌 Tareas

- Crear carpetas:
  - `app/Services`
  - `app/Repositories`
  - `app/Entities`
- Crear ejemplo:
  - `UserController`
  - `UserService`
  - `UserRepository`

### 📦 Entregables

- Estructura por capas
- Ejemplo funcional User

### ✅ Validación

- Endpoint `/api/v1/users` responde vía Service + Repo

---

## 🔹 FASE 2 — Autenticación JWT + Roles

### 🎯 Objetivo

Implementar login JWT con control de roles.

### 📌 Tareas

- Crear `JwtService`
- Crear `JwtAuthFilter`
- Implementar `AuthController::login`
- Claims: uid, role, exp

### 📦 Entregables

- Login devuelve token JWT
- Filtro protege rutas

### ✅ Validación

- Login correcto genera token
- Rutas protegidas fallan sin token

---

## 🔹 FASE 3 — Helpers + Respuesta Estándar

### 🎯 Objetivo

Unificar formato de respuesta.

### 📌 Tareas

- Crear `response_helper.php`
- Implementar `apiResponse()`

### 📦 Entregables

- Todas las respuestas usan:
  `{ "success": true, "data": {}, "error": null }`

### ✅ Validación

- Ningún controller retorna JSON manual

---

## 🔹 FASE 4 — Swagger / OpenAPI

### 🎯 Objetivo

Documentar endpoints automáticamente.

### 📌 Tareas

- Agregar anotaciones @OA en Controllers
- Configurar generación a `public/swagger.json`

### 📦 Entregables

- Archivo swagger.json generado

### ✅ Validación

- Swagger muestra rutas y schemas

---

## 🔹 FASE 5 — Docker + MySQL

### 🎯 Objetivo

Entorno reproducible local.

### 📌 Tareas

- Crear `Dockerfile`
- Crear `docker-compose.yml` (app + db)
- Configurar volumenes

### 📦 Entregables

- Contenedores corriendo

### ✅ Validación

- `docker compose up` levanta API + MySQL

---

## 🔹 FASE 6 — Testing

### 🎯 Objetivo

Tests automáticos por capa.

### 📌 Tareas

- Configurar PHPUnit
- Crear tests:
  - AuthControllerTest
  - UserServiceTest
  - UserRepositoryTest

### 📦 Entregables

- Suite de tests mínima

### ✅ Validación

- `php spark test` en verde

---

## 🔹 FASE 7 — CI con GitHub Actions

### 🎯 Objetivo

Pipeline automático.

### 📌 Tareas

- Crear `.github/workflows/ci.yml`
- Steps:
  - composer install
  - php spark test

### 📦 Entregables

- CI activo

### ✅ Validación

- PR corre tests automáticamente

---

## 🔹 FASE 8 — Seguridad, CORS, Rate Limiting

### 🎯 Objetivo

Endurecer API base.

### 📌 Tareas

- Filtro CORS global
- Throttle por IP/token
- Logs estructurados

### 📦 Entregables

- Seguridad activa

### ✅ Validación

- Requests sin headers CORS bloqueados

---

## 🔹 FASE 9 — Plantilla Reutilizable

### 🎯 Objetivo

Convertir en starter reusable.

### 📌 Tareas

- Limpiar datos de ejemplo
- Documentar README
- Crear script init

### 📦 Entregables

- Starter listo para clonar

### ✅ Validación

- Nuevo proyecto se levanta en <10 min

---

## 🤖 Uso con Claude Code

Este archivo puede ser usado como **PDR** para que Claude ejecute:

➡ Cada fase = una iteración  
➡ Cada tarea = una instrucción concreta  
➡ Cada validación = criterio de aceptación
