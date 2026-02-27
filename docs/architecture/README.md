# Architecture Documentation

Welcome to the architecture documentation. This directory contains detailed, focused documents about the CI4 API Starter's architecture.

---

## 📚 Learning Roadmap

### 🟢 Beginner (Day 1 - Understand the basics)

**Start here if you're new to the project:**

1. **[OVERVIEW.md](OVERVIEW.md)** (~15 min read)
   - What is this project?
   - High-level architecture diagram
   - SOLID principles
   - Layer responsibilities

2. **[LAYERS.md](LAYERS.md)** (~20 min read)
   - Deep dive into Controller, Service, Model, Entity
   - Code examples for each layer
   - Rules and responsibilities

3. **[REQUEST_FLOW.md](REQUEST_FLOW.md)** (~15 min read)
   - Complete request/response cycle
   - Step-by-step walkthrough with example
   - Timing and performance

**Time investment:** ~50 minutes
**You'll know:** How the system works end-to-end

---

### 🟡 Intermediate (Week 1 - Master the systems)

**Read these to understand key subsystems:**

4. **[FILTERS.md](FILTERS.md)** (~10 min read)
   - Middleware pipeline
   - JwtAuth, RoleAuth, Throttle, CORS
   - Creating custom filters

5. **[VALIDATION.md](VALIDATION.md)** (~15 min read)
   - 3 levels of validation
   - Input validation classes
   - Model validation rules
   - Business rule validation

6. **[EXCEPTIONS.md](EXCEPTIONS.md)** (~10 min read)
   - Exception hierarchy
   - When to use each exception
   - Exception handling in controllers

7. **[RESPONSES.md](RESPONSES.md)** (~10 min read)
   - ApiResponse library
   - Response structure standards
   - Success, error, and paginated responses

8. **[TESTING.md](TESTING.md)** (~15 min read)
   - Three-layer testing pyramid
   - SQLite integration and speed
   - Identity propagation with ContextHolder
   - Writing your first test

**Time investment:** ~60 minutes
**You'll know:** How to handle input, validate, respond, and test

---

### 🔴 Advanced (Month 1 - Become an expert)

**Read these for advanced features:**

8. **[QUERIES.md](QUERIES.md)** (~20 min read)
   - QueryBuilder advanced usage
   - Filtering with operators
   - Searching (FULLTEXT vs LIKE)
   - Sorting and pagination

9. **[SERVICES.md](SERVICES.md)** (~15 min read)
   - IoC container
   - Dependency injection
   - Service registration
   - Shared instances

10. **[AUTHENTICATION.md](AUTHENTICATION.md)** (~25 min read)
    - JWT authentication flow
    - Access tokens vs refresh tokens
    - Token revocation
    - Security considerations

11. **[PATTERNS.md](PATTERNS.md)** (~15 min read)
    - Service Layer pattern
    - Repository pattern
    - Factory pattern
    - Strategy pattern
    - All design patterns used

12. **[I18N.md](I18N.md)** (~10 min read)
    - Internationalization system
    - Language file structure
    - Translation usage
    - Locale detection

13. **[EXTENSION_GUIDE.md](EXTENSION_GUIDE.md)** (~20 min read)
    - How to add new resources
    - How to add new filters
    - How to add new exceptions
    - Best practices

**Time investment:** ~1 hour 45 minutes
**You'll know:** All advanced features and how to extend the system

---

## 📖 Document Index

| Document | Topic | Lines | Audience |
|----------|-------|-------|----------|
| [OVERVIEW.md](OVERVIEW.md) | Architecture overview | ~200 | Beginner |
| [LAYERS.md](LAYERS.md) | The 4 layers in detail | ~300 | Beginner |
| [REQUEST_FLOW.md](REQUEST_FLOW.md) | Complete request cycle | ~250 | Beginner |
| [FILTERS.md](FILTERS.md) | Middleware system | ~200 | Intermediate |
| [VALIDATION.md](VALIDATION.md) | Multi-level validation | ~200 | Intermediate |
| [EXCEPTIONS.md](EXCEPTIONS.md) | Exception handling | ~150 | Intermediate |
| [RESPONSES.md](RESPONSES.md) | ApiResponse library | ~150 | Intermediate |
| [QUERIES.md](QUERIES.md) | Advanced querying | ~250 | Advanced |
| [SERVICES.md](SERVICES.md) | IoC container | ~150 | Advanced |
| [AUTHENTICATION.md](AUTHENTICATION.md) | JWT auth system | ~300 | Advanced |
| [PATTERNS.md](PATTERNS.md) | Design patterns | ~200 | Advanced |
| [I18N.md](I18N.md) | Internationalization | ~150 | Advanced |
| [EXTENSION_GUIDE.md](EXTENSION_GUIDE.md) | Extending the system | ~250 | Advanced |

**Total:** ~2,700 lines
**Benefit:** Focused, digestible documents

---

## 🗺️ Document Map (What to Read When)

### I want to...

**...understand the big picture**
→ Read [OVERVIEW.md](OVERVIEW.md)

**...know how a request flows**
→ Read [REQUEST_FLOW.md](REQUEST_FLOW.md)

**...understand where to put code**
→ Read [LAYERS.md](LAYERS.md)

**...add a new CRUD resource**
→ Read [EXTENSION_GUIDE.md](EXTENSION_GUIDE.md)

**...understand authentication**
→ Read [AUTHENTICATION.md](AUTHENTICATION.md)

**...add filters/middleware**
→ Read [FILTERS.md](FILTERS.md)

**...understand validation**
→ Read [VALIDATION.md](VALIDATION.md)

**...add advanced filtering/search**
→ Read [QUERIES.md](QUERIES.md)

**...understand exceptions**
→ Read [EXCEPTIONS.md](EXCEPTIONS.md)

**...format API responses**
→ Read [RESPONSES.md](RESPONSES.md)

**...understand dependency injection**
→ Read [SERVICES.md](SERVICES.md)

**...add translations**
→ Read [I18N.md](I18N.md)

**...see all design patterns**
→ Read [PATTERNS.md](PATTERNS.md)

---

## 🎯 Quick Reference

For a condensed, table-based quick reference optimized for rapid lookup:
→ See [`../AGENT_QUICK_REFERENCE.md`](../AGENT_QUICK_REFERENCE.md)

For hands-on, step-by-step tutorial:
→ See [`../GETTING_STARTED.md`](../GETTING_STARTED.md)

---

## 💡 Tips for Reading

1. **Don't read everything at once** - Follow the roadmap based on your experience level

2. **Read in order** - Each document builds on previous ones

3. **Try the examples** - Create a test resource while reading EXTENSION_GUIDE.md

4. **Use the map** - Jump to specific topics as needed

5. **Refer back** - Keep this README bookmarked for quick navigation

---

## 🚀 Next Steps After Reading

Once you've completed the roadmap:

1. **Build something** - Create a new CRUD resource from scratch
2. **Read the code** - Examine existing controllers, services, models
3. **Run tests** - See how testing works across all layers
4. **Contribute** - Improve the docs or add features

---

**Happy learning!** 📚

If you find issues or have suggestions for improving these docs, please open an issue or PR.
