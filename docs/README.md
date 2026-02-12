# Documentation Directory

This directory contains documentation for both **human developers** and **AI agents** working with this project.

## For AI Agents (Claude Code)

### 🤖 **AGENT_QUICK_REFERENCE.md**
**Primary reference for AI agents creating CRUD resources**

This is a condensed, optimized guide (~350 lines) containing:
- Complete CRUD implementation checklist (10 steps)
- Request flow diagrams
- Exception reference table
- ApiResponse method reference
- Common validation patterns
- Query features (filtering, searching, sorting)
- Security checklist
- Testing patterns
- Common pitfalls to avoid

**When to use:** AI agents implementing new resources should read this FIRST before writing any code.

---

## For Human Developers

### 📚 **ARCHITECTURE.md**
**Comprehensive architectural reference (2,400+ lines)**

Deep dive into the project's architecture including:
- Complete request/response flow with sequence diagrams
- Detailed explanation of each layer (Controller, Service, Model, Entity)
- Filter system (middleware) internals
- Validation system (3 levels)
- Exception system hierarchy
- ApiResponse library
- Internationalization (i18n)
- Advanced query system (QueryBuilder, FilterParser)
- Service container (IoC)
- JWT authentication flow
- Design patterns used
- Directory structure
- Extension guide with complete examples

**When to use:** Understanding the full architecture, design decisions, or implementing complex features.

---

### 📋 **PLAYBOOK_FEATURES.en.md** / **PLAYBOOK_FEATURES.es.md**
**Feature implementation playbook for developers**

Standardized checklist for implementing new features:
- Analysis & design phase
- Schema & migrations
- Models & entities
- Services & business logic
- Controllers & routes
- Documentation (OpenAPI)
- Testing strategy
- Deployment checklist

**When to use:** Starting a new feature or module from scratch.

---

### 📦 **TECHNOLOGIES.md**
List of technologies and dependencies used in the project.

---

### 📄 **FILE-UPLOAD-FLOW.md** / **FILE-UPLOAD-FLOW.es.md**
Detailed explanation of the file upload/download system including:
- Storage drivers (local, S3)
- Upload flow
- Download flow
- Security considerations

---

## `tech/` Directory

Detailed documentation for specific features:

| File | Description |
|------|-------------|
| `jwt-auth.md` | JWT authentication implementation |
| `refresh-tokens.md` | Refresh token lifecycle |
| `token-revocation.md` | Token blacklist system |
| `email.md` | Email service configuration |
| `email-verification.md` | Email verification flow |
| `password-reset.md` | Password reset flow |
| `file-storage.md` | File storage system |
| `audit-logging.md` | Audit trail system |
| `cors.md` | CORS configuration |
| `rate-limiting.md` | Rate limiting (throttle) |
| `monitoring-health.md` | Health check endpoints |
| `openapi.md` | OpenAPI/Swagger documentation |
| `transactions.md` | Database transactions |
| `QUEUE.md` | Queue system |
| `request-logging.md` | Request logging |

Each file has English (`.md`) and Spanish (`.es.md`) versions.

---

## `postman/` Directory

Postman collections for API testing:
- `ci4-api.postman_collection.json` - Complete API collection
- `ci4-auth-flow.postman_collection.json` - Authentication flow examples
- Environment files with variables

---

## Documentation Strategy

### For Quick Implementation (AI Agents)
```
Read: AGENT_QUICK_REFERENCE.md
```

### For Full Understanding (Human Developers)
```
Read: ARCHITECTURE.md → PLAYBOOK_FEATURES.md → tech/{specific-feature}.md
```

### For Specific Features
```
Read: tech/{feature}.md
```

---

## Updating Documentation

When adding new features:
1. Update `AGENT_QUICK_REFERENCE.md` if it affects CRUD patterns
2. Add detailed explanation to `ARCHITECTURE.md`
3. Create `tech/{feature}.md` for feature-specific details
4. Update both English and Spanish versions
5. Add examples to Postman collections

---

**Note:** `AGENT_QUICK_REFERENCE.md` is intentionally concise (~350 lines) to optimize AI agent context usage. `ARCHITECTURE.md` contains the full, detailed explanation for human comprehension.
