# Architecture Decision Records (ADR)

This document explains the architectural decisions made in this project and the reasoning behind them. When in doubt about whether something is an inconsistency or a deliberate design choice, consult this document.

## Table of Contents

1. [Controller Architecture](#controller-architecture)
2. [Documentation Strategy](#documentation-strategy)
3. [Testing Strategy](#testing-strategy)
4. [Security Patterns](#security-patterns)
5. [Service Layer Patterns](#service-layer-patterns)
6. [Data Validation Architecture](#data-validation-architecture)
7. [Observability and Governance](#observability-and-governance)

---

## Controller Architecture

### ADR-001: Standardized API Controllers

**Decision:** The project uses a declarative controller pattern via `ApiController`.

#### Business API Controllers

**Pattern:** Extend `ApiController`

**Purpose:** Act as thin orchestrators between HTTP requests and the Service Layer.

**Characteristics:**
```php
class UserController extends ApiController
{
    protected string $serviceName = 'userService';

    public function create(): ResponseInterface {
        // Validation happens automatically inside the DTO constructor
        return $this->handleRequest('store', UserStoreRequestDTO::class);
    }
}
```

**Benefits:**
- ✅ **Zero-Boilerplate:** Automatic request-to-DTO mapping.
- ✅ **Centralized Error Handling:** Global exception-to-JSON transformation.
- ✅ **Standardized Contract:** Automated `{"status": "success", "data": ...}` wrapping.
- ✅ **Semantic Codes:** Automated mapping of actions to status codes (201 for creation, 202 for pending).

#### Infrastructure Controllers

**Pattern:** Extend base `Controller`

**Purpose:** System monitoring, health checks, metrics that follow external standards (Kubernetes/Docker).

---

## Documentation Strategy

### ADR-002: Integrated Living Documentation

**Decision:** OpenAPI schemas live directly within DTO classes as PHP 8 attributes. Endpoint definitions remain in `app/Documentation/`.

**Generation:**
```bash
php spark swagger:generate  # Scans app/DTO/ and app/Documentation/
```

**Single Source of Truth:** The code *is* the documentation. Property types and constraints in DTOs are automatically reflected in the Swagger UI.

---

## Testing Strategy

### ADR-003: Three Test Layers

**Decision:** Tests are organized by integration level to maximize speed and coverage.

1.  **Unit (Fast):** Mocked dependencies. Tests logic in Services, DTOs, and Libraries.
2.  **Integration (DB):** Tests real Database/Model interactions.
3.  **Feature (E2E):** Full HTTP request/response cycle, filters, and authorization.

**Key Rule:** Service unit tests should verify DTO return types and logic without caring about HTTP status codes or JSON structures.

---

## Security Patterns

### ADR-004: Transactional Integrity

**Decision:** All state-changing operations in the Service Layer must be atomic.

**Implementation:** Use the `HandlesTransactions` trait in services.
```php
return $this->wrapInTransaction(function() use ($dto) {
    $id = $this->model->insert($dto->toArray());
    $this->auditService->log('create', ...);
    return $this->mapToResponse($this->model->find($id));
});
```

---

## Service Layer Patterns

### ADR-005: Generic Base CRUD Service

**Decision:** Reduce boilerplate by using a genric `BaseCrudService` for standard operations.

**Key Features:**
- **Automated Mapping:** Converts Entities to Response DTOs automatically using `$responseDtoClass`.
- **Global Security Hook:** `applyBaseCriteria()` allows services to enforce universal filters (e.g., exclude superadmins).
- **Pure Logic:** Services are agnostic to HTTP. They throw exceptions and return DTOs.

---

## Data Validation Architecture

### ADR-006: DTO-First Auto-Validation

**Decision:** **Validation is no longer a separate service.** It is an intrinsic property of the Data Transfer Object.

**The "Shield" Pattern:**
- **Request DTOs** must extend `BaseRequestDTO`.
- Validation happens in the **constructor**. 
- If a DTO is instantiated, the data is **guaranteed to be valid**.

**Example:**
```php
readonly class UserStoreRequestDTO extends BaseRequestDTO {
    protected function rules(): array {
        return ['email' => 'required|valid_email|is_unique[users.email]'];
    }
}
```

**Why this is superior:**
1. **Safety:** Prevents "dirty data" from ever reaching the Service Layer.
2. **Clarity:** The contract (what data is needed) and the validation (what format) are in the same file.
3. **Efficiency:** No need to manually call `validate()` in every service method.

---

## Summary of Design Principles

1. **DTOs as Guardians:** Never pass raw arrays to services.
2. **Thin Controllers:** Controllers only orchestrate; they don't validate or calculate.
3. **Pure Services:** Agnostic to HTTP, focus on business rules.
4. **Declarative Code:** Favour configuration over manual implementation (e.g., `handleRequest`).
5. **Fail Early:** Let the DTO constructor throw the exception.

---

## Observability and Governance

This project tracks reliability through request-level indicators (SLOs) and enforces a strict quality gates via `composer quality`.

See the decision record at `docs/architecture/ADR-004-OBSERVABILITY-GOVERNANCE.md`.
