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

## 🤖 For AI Agents (Claude Code / OpenAI Codex)

### **[AGENT_QUICK_REFERENCE.md](AGENT_QUICK_REFERENCE.md)**
**Primary reference for AI agents creating CRUD resources**

Condensed guide for AI context usage:
- CRUD implementation checklist
- Request flow and exception reference
- Validation and testing patterns
- Security and architecture guardrails

**When to use:** AI agents implementing new resources should read this FIRST before writing any code.
**CRUD rule:** Start with `php spark make:crud ...` before manual CRUD file creation.

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
14. Read [`architecture/EXTENSION_GUIDE.md`](architecture/EXTENSION_GUIDE.md) - Extend the system

**Full roadmap:** See [`architecture/README.md`](architecture/README.md)

**En español:** all architecture docs are available with `.es.md` files.

---

## 📚 Architecture Documentation

### **[architecture/](architecture/)**
**Organized, focused documents by topic (bilingual EN/ES)**

The architecture documentation is organized into focused documents available in both English and Spanish:

| Document | Topic | Audience | Lines |
|----------|-------|----------|-------|
| [README.md](architecture/README.md) / [.es.md](architecture/README.es.md) | Learning roadmap & index | All | 150 |
| [OVERVIEW.md](architecture/OVERVIEW.md) / [.es.md](architecture/OVERVIEW.es.md) | Architecture overview | Beginner | 200 |
| [LAYERS.md](architecture/LAYERS.md) / [.es.md](architecture/LAYERS.es.md) | The 4 layers (Controller/Service/Model/Entity) | Beginner | 300 |
| [REQUEST_FLOW.md](architecture/REQUEST_FLOW.md) / [.es.md](architecture/REQUEST_FLOW.es.md) | Complete request walkthrough | Beginner | 250 |
| [FILTERS.md](architecture/FILTERS.md) / [.es.md](architecture/FILTERS.es.md) | Middleware system | Intermediate | 200 |
| [VALIDATION.md](architecture/VALIDATION.md) / [.es.md](architecture/VALIDATION.es.md) | Multi-level validation | Intermediate | 200 |
| [EXCEPTIONS.md](architecture/EXCEPTIONS.md) / [.es.md](architecture/EXCEPTIONS.es.md) | Exception handling | Intermediate | 150 |
| [RESPONSES.md](architecture/RESPONSES.md) / [.es.md](architecture/RESPONSES.es.md) | ApiResponse library | Intermediate | 150 |
| [QUERIES.md](architecture/QUERIES.md) / [.es.md](architecture/QUERIES.es.md) | Advanced querying | Advanced | 250 |
| [SERVICES.md](architecture/SERVICES.md) / [.es.md](architecture/SERVICES.es.md) | IoC container | Advanced | 150 |
| [AUTHENTICATION.md](architecture/AUTHENTICATION.md) / [.es.md](architecture/AUTHENTICATION.es.md) | JWT auth system | Advanced | 300 |
| [PATTERNS.md](architecture/PATTERNS.md) / [.es.md](architecture/PATTERNS.es.md) | Design patterns | Advanced | 200 |
| [I18N.md](architecture/I18N.md) / [.es.md](architecture/I18N.es.md) | Internationalization | Advanced | 150 |
| [EXTENSION_GUIDE.md](architecture/EXTENSION_GUIDE.md) / [.es.md](architecture/EXTENSION_GUIDE.es.md) | Extending the system | Advanced | 250 |

**Benefits:**
- ✅ Focused topics (easy to find what you need)
- ✅ Smaller files (easier to read and search)
- ✅ Clear learning progression (beginner → advanced)
- ✅ Faster navigation (direct links to topics)
- ✅ 100% bilingual coverage (English + Spanish)

---

## 📐 Template Governance

Template adoption and quality governance live in:

1. [`template/ARCHITECTURE_CONTRACT.md`](template/ARCHITECTURE_CONTRACT.md)
2. [`template/MODULE_BOOTSTRAP_CHECKLIST.md`](template/MODULE_BOOTSTRAP_CHECKLIST.md)
3. [`template/QUALITY_GATES.md`](template/QUALITY_GATES.md)
4. [`template/VERSIONING_POLICY.md`](template/VERSIONING_POLICY.md)
5. [`template/CONTRIBUTION_RULES.md`](template/CONTRIBUTION_RULES.md)
6. [`release/TEMPLATE_AUDIT_CLOSEOUT.md`](release/TEMPLATE_AUDIT_CLOSEOUT.md)

All markdown docs in `docs/` must keep EN/ES parity (`.md` and `.es.md`).

---

## 📋 Feature Playbooks

### **[PLAYBOOK_FEATURES.md](PLAYBOOK_FEATURES.md)** / **[PLAYBOOK_FEATURES.es.md](PLAYBOOK_FEATURES.es.md)**
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

## 📊 Flow Diagrams

### **[flows/](flows/)**
**Feature-specific sequence diagrams**

Detailed flow diagrams explaining how specific features work:

| Flow | Description | Files |
|------|-------------|-------|
| [FILE-UPLOAD-FLOW.md](flows/FILE-UPLOAD-FLOW.md) / [.es.md](flows/FILE-UPLOAD-FLOW.es.md) | File upload/download system flow | 2 |
| [AUTH-LOGIN-FLOW.md](flows/AUTH-LOGIN-FLOW.md) / [.es.md](flows/AUTH-LOGIN-FLOW.es.md) | Full authentication with JWT token generation | 2 |
| [REGISTER-APPROVAL-FLOW.md](flows/REGISTER-APPROVAL-FLOW.md) / [.es.md](flows/REGISTER-APPROVAL-FLOW.es.md) | Self-registration + admin approval state machine | 2 |
| [PASSWORD-RESET-FLOW.md](flows/PASSWORD-RESET-FLOW.md) / [.es.md](flows/PASSWORD-RESET-FLOW.es.md) | 3-step password reset with frontend redirect | 2 |
| [EMAIL-VERIFICATION-FLOW.md](flows/EMAIL-VERIFICATION-FLOW.md) / [.es.md](flows/EMAIL-VERIFICATION-FLOW.es.md) | Email verification with allowlisted frontend links | 2 |

Flow diagrams include:
- Sequence diagrams showing request/response
- Step-by-step explanations
- Code examples
- Service validations
- Security considerations

---

## 🎯 Feature Planning

### **[features/](features/)**
**Specific feature implementation plans**

Detailed implementation plans for specific features:

| Feature | Description | Status |
|---------|-------------|--------|
| [BLOG_MODULE.md](features/BLOG_MODULE.md) | Blog system (posts, categories, tags) | Planned |

### **[plans/](plans/)**
**Planning templates and guidelines**

Generic templates for planning new features. Currently empty - see [architecture/EXTENSION_GUIDE.md](architecture/EXTENSION_GUIDE.md) for the complete guide on extending the system.

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

### For AI Agents (Claude Code / OpenAI Codex)
```
Read: AGENT_QUICK_REFERENCE.md (optimized for AI context)
Rule: use php spark make:crud first for new CRUD resources
```

### For Specific Features
```
Read: tech/{feature}.md (detailed feature docs)
```

### For Flow Diagrams
```
Read: flows/{feature}-FLOW.md (sequence diagrams)
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
| AI Agent Docs | 1 | ~600 | Optimized quick reference (EN only) |
| Architecture Docs | 28 | ~5,400 | Topic-based learning (EN + ES) |
| Feature Docs | 31 | ~15K | Detailed feature docs (EN + ES) |
| Flow Diagrams | 10 | ~2.3K+ | Sequence diagrams (EN + ES) |
| Feature Plans | 1 | ~5.4K | Implementation plans |
| Playbooks | 2 | ~8K | Implementation checklists (EN + ES) |

**Total organized documentation:** ~37K+ lines across 66+ files
**Bilingual coverage:** 95% (except AGENT_QUICK_REFERENCE.md)
**Benefit:** Find exactly what you need, when you need it

---

## 🎯 Quick Navigation

| I want to... | Read this |
|--------------|-----------|
| **Get started quickly** | [`../GETTING_STARTED.md`](../GETTING_STARTED.md) |
| **Understand architecture** | [`architecture/OVERVIEW.md`](architecture/OVERVIEW.md) |
| **Add a CRUD resource** | [`architecture/EXTENSION_GUIDE.md`](architecture/EXTENSION_GUIDE.md) |
| **Understand file upload** | [`flows/FILE-UPLOAD-FLOW.md`](flows/FILE-UPLOAD-FLOW.md) |
| **Understand authentication** | [`flows/AUTH-LOGIN-FLOW.md`](flows/AUTH-LOGIN-FLOW.md) |
| **Understand registration & approval** | [`flows/REGISTER-APPROVAL-FLOW.md`](flows/REGISTER-APPROVAL-FLOW.md) |
| **Understand password reset** | [`flows/PASSWORD-RESET-FLOW.md`](flows/PASSWORD-RESET-FLOW.md) |
| **Understand email verification** | [`flows/EMAIL-VERIFICATION-FLOW.md`](flows/EMAIL-VERIFICATION-FLOW.md) |
| **Plan a new feature** | [`architecture/EXTENSION_GUIDE.md`](architecture/EXTENSION_GUIDE.md) |
| **Quick lookup (AI)** | [`AGENT_QUICK_REFERENCE.md`](AGENT_QUICK_REFERENCE.md) |
| **Feature-specific docs** | [`tech/`](tech/) directory |

---

**Happy learning!** 📚

*Last updated: February 2026*
