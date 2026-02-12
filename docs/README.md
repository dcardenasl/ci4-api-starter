# Documentation Directory

This directory contains documentation for both **human developers** and **AI agents** working with this project.

---

## 🚀 Getting Started

### **[../GETTING_STARTED.md](../GETTING_STARTED.md)**
**START HERE if you're new to the project!**

A hands-on tutorial (~30 minutes) that walks you through:
- Quick setup (5 minutes)
- Core architecture concepts (4 layers)
- Your first API request
- Building a complete Product CRUD from scratch
- Testing the endpoints
- Next steps for learning more

**Who should read:** New developers, first-time users

---

## 🤖 For AI Agents (Claude Code)

### **[AGENT_QUICK_REFERENCE.md](AGENT_QUICK_REFERENCE.md)**
**Primary reference for AI agents creating CRUD resources**

Condensed guide (~600 lines) optimized for AI context usage:
- Complete CRUD implementation checklist (10 steps with code)
- Request flow diagrams
- Exception reference table
- ApiResponse method reference
- Common validation patterns
- Query features (filtering, searching, sorting, pagination)
- Security checklist
- Testing patterns (Unit, Integration, Feature)
- Common pitfalls to avoid
- File naming conventions

**When to use:** AI agents implementing new resources should read this FIRST before writing any code.

**Optimization:** 75% smaller than reading full architecture docs (600 vs 2,400 lines).

---

## 👨‍💻 For Human Developers

### Learning Paths

#### 🟢 Beginner Path (~1 hour)
**Goal:** Understand the basics

1. Read [`../GETTING_STARTED.md`](../GETTING_STARTED.md) - Hands-on intro
2. Read [`architecture/OVERVIEW.md`](architecture/OVERVIEW.md) - Big picture
3. Read [`architecture/LAYERS.md`](architecture/LAYERS.md) - The 4 layers in detail
4. Read [`architecture/REQUEST_FLOW.md`](architecture/REQUEST_FLOW.md) - Complete flow

#### 🟡 Intermediate Path (~1.5 hours)
**Goal:** Master the systems

5. Read [`architecture/FILTERS.md`](architecture/FILTERS.md) - Middleware
6. Read [`architecture/VALIDATION.md`](architecture/VALIDATION.md) - Multi-level validation
7. Read [`architecture/EXCEPTIONS.md`](architecture/EXCEPTIONS.md) - Error handling
8. Read [`architecture/RESPONSES.md`](architecture/RESPONSES.md) - API response format

#### 🔴 Advanced Path (~2 hours)
**Goal:** Become an expert

9. Read [`architecture/QUERIES.md`](architecture/QUERIES.md) - Advanced querying
10. Read [`architecture/SERVICES.md`](architecture/SERVICES.md) - IoC container
11. Read [`architecture/AUTHENTICATION.md`](architecture/AUTHENTICATION.md) - JWT auth
12. Read [`architecture/PATTERNS.md`](architecture/PATTERNS.md) - Design patterns
13. Read [`architecture/I18N.md`](architecture/I18N.md) - Internationalization
14. Read [`architecture/EXTENSION_GUIDE.md`](architecture/EXTENSION.md) - Extend the system

**Full roadmap:** See [`architecture/README.md`](architecture/README.md)

---

## 📚 Architecture Documentation

### **[architecture/](architecture/)**
**Organized, focused documents by topic**

The monolithic `ARCHITECTURE.md` (2,400+ lines) has been reorganized into 13 focused documents (~100-300 lines each):

| Document | Topic | Audience | Lines |
|----------|-------|----------|-------|
| [README.md](architecture/README.md) | Learning roadmap & index | All | 150 |
| [OVERVIEW.md](architecture/OVERVIEW.md) | Architecture overview | Beginner | 200 |
| [LAYERS.md](architecture/LAYERS.md) | The 4 layers (Controller/Service/Model/Entity) | Beginner | 300 |
| [REQUEST_FLOW.md](architecture/REQUEST_FLOW.md) | Complete request walkthrough | Beginner | 250 |
| [FILTERS.md](architecture/FILTERS.md) | Middleware system | Intermediate | 200 |
| [VALIDATION.md](architecture/VALIDATION.md) | Multi-level validation | Intermediate | 200 |
| [EXCEPTIONS.md](architecture/EXCEPTIONS.md) | Exception handling | Intermediate | 150 |
| [RESPONSES.md](architecture/RESPONSES.md) | ApiResponse library | Intermediate | 150 |
| [QUERIES.md](architecture/QUERIES.md) | Advanced querying | Advanced | 250 |
| [SERVICES.md](architecture/SERVICES.md) | IoC container | Advanced | 150 |
| [AUTHENTICATION.md](architecture/AUTHENTICATION.md) | JWT auth system | Advanced | 300 |
| [PATTERNS.md](architecture/PATTERNS.md) | Design patterns | Advanced | 200 |
| [I18N.md](architecture/I18N.md) | Internationalization | Advanced | 150 |
| [EXTENSION_GUIDE.md](architecture/EXTENSION_GUIDE.md) | Extending the system | Advanced | 250 |

**Benefits:**
- ✅ Focused topics (easy to find what you need)
- ✅ Smaller files (easier to read and search)
- ✅ Clear learning progression (beginner → advanced)
- ✅ Faster navigation (direct links to topics)

### **[ARCHITECTURE.md](ARCHITECTURE.md)** (Legacy)
**Comprehensive monolithic reference (2,400+ lines)**

The original complete architecture document. Still available as a reference, but the `architecture/` directory is now the recommended way to learn the architecture.

**When to use:** As a searchable reference when you know exactly what you're looking for.

---

## 📋 Feature Playbooks

### **[PLAYBOOK_FEATURES.en.md](PLAYBOOK_FEATURES.en.md)** / **[PLAYBOOK_FEATURES.es.md](PLAYBOOK_FEATURES.es.md)**
**Feature implementation playbook**

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

## 📦 Additional Documentation

### **[TECHNOLOGIES.md](TECHNOLOGIES.md)**
List of technologies and dependencies used in the project.

### **[FILE-UPLOAD-FLOW.md](FILE-UPLOAD-FLOW.md)** / **[FILE-UPLOAD-FLOW.es.md](FILE-UPLOAD-FLOW.es.md)**
Detailed explanation of the file upload/download system:
- Storage drivers (local, S3)
- Upload flow
- Download flow
- Security considerations

---

## 📂 `tech/` Directory

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

## 🧪 `postman/` Directory

Postman collections for API testing:
- `ci4-api.postman_collection.json` - Complete API collection
- `ci4-auth-flow.postman_collection.json` - Authentication flow examples
- Environment files with variables

---

## 📖 Documentation Strategy

### For New Developers
```
1. Read: ../GETTING_STARTED.md (hands-on tutorial)
2. Follow: architecture/ learning path (beginner → advanced)
3. Reference: AGENT_QUICK_REFERENCE.md (quick lookup)
```

### For AI Agents (Claude Code)
```
Read: AGENT_QUICK_REFERENCE.md (optimized for AI context)
```

### For Specific Features
```
Read: tech/{feature}.md (detailed feature docs)
```

### For Quick Lookup
```
Search: AGENT_QUICK_REFERENCE.md (tables and checklists)
```

---

## ✍️ Updating Documentation

When adding new features:

1. **Update quick reference** - `AGENT_QUICK_REFERENCE.md` if it affects CRUD patterns
2. **Update architecture docs** - Add/update relevant file in `architecture/`
3. **Create feature doc** - `tech/{feature}.md` for feature-specific details
4. **Update both languages** - English (`.md`) and Spanish (`.es.md`)
5. **Add examples** - Update Postman collections
6. **Update this README** - Add links to new documentation

---

## 📊 Documentation Statistics

| Type | Files | Total Lines | Purpose |
|------|-------|-------------|---------|
| Getting Started | 1 | ~450 | Onboarding tutorial |
| AI Agent Docs | 1 | ~600 | Optimized quick reference |
| Architecture Docs | 14 | ~2,700 | Organized topic-based learning |
| Feature Docs | ~40 | varies | Detailed feature documentation |
| Playbooks | 2 | ~300 | Implementation checklists |

**Total organized documentation:** ~4,000+ lines across 58+ files
**Benefit:** Find exactly what you need, when you need it

---

## 🎯 Quick Navigation

| I want to... | Read this |
|--------------|-----------|
| **Get started quickly** | [`../GETTING_STARTED.md`](../GETTING_STARTED.md) |
| **Understand architecture** | [`architecture/OVERVIEW.md`](architecture/OVERVIEW.md) |
| **Add a CRUD resource** | [`architecture/EXTENSION_GUIDE.md`](architecture/EXTENSION_GUIDE.md) |
| **Understand authentication** | [`architecture/AUTHENTICATION.md`](architecture/AUTHENTICATION.md) |
| **Quick lookup (AI)** | [`AGENT_QUICK_REFERENCE.md`](AGENT_QUICK_REFERENCE.md) |
| **Feature-specific docs** | [`tech/`](tech/) directory |
| **Full architecture (legacy)** | [`ARCHITECTURE.md`](ARCHITECTURE.md) |

---

**Happy learning!** 📚

*Last updated: February 2026*
