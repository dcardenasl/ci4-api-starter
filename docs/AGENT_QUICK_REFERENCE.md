# ⚡ Agent & Developer Quick Reference Guide

This "Cheat Sheet" is designed for rapid onboarding and high-speed development.

## 🚀 Core Commands

| Command | Purpose | When to use? |
|---------|---------|--------------|
| `php spark make:crud {Name}` | **Scaffold Module** | Starting a new CRUD resource. |
| `php spark migrate` | **Apply DB changes** | After generating a new CRUD. |
| `php spark swagger:generate` | **Update OpenAPI** | After adding endpoints or DTOs. |
| `composer quality` | **Full Health Check** | Before pushing any code. |
| `composer cs-fix` | **Fix Linting** | To auto-format your code. |

## 🏗️ Scaffolding Syntax (CLI Mode)

Use the `--fields` option for rapid generation:
`php spark make:crud Name --domain Domain --fields="col:type:options"`

**Available Types:** `string`, `text`, `int`, `bool`, `decimal`, `email`, `date`, `datetime`, `fk`, `json`.
**Common Options:** `required`, `nullable`, `searchable`, `filterable`, `fk:tableName`.

*Example:*
`php spark make:crud Product --fields="name:string:required|searchable,category_id:fk:categories"`

## ✅ Quality Standards Checklist

1.  **Immutability:** Always use `readonly class` for DTOs.
2.  **DTO-First:** No direct input mapping in Controllers; use `RequestDataCollector`.
3.  **Audit:** Use the `Auditable` trait for any model with sensitive data.
4.  **Tests:** New services must include Unit tests; controllers must have Feature tests.
5.  **Docs:** Ensure OpenAPI tags and summaries are clear and grouped by Domain.

## 📁 File Structure Map (Layered API)

- `app/Controllers/Api/V1/{Domain}/` -> Entry point.
- `app/DTO/Request/{Domain}/` -> Request validation.
- `app/DTO/Response/{Domain}/` -> Response transformation.
- `app/Interfaces/{Domain}/` -> Service contracts.
- `app/Services/{Domain}/` -> Business logic.
- `app/Models/` -> Database orchestration.
- `app/Documentation/{Domain}/` -> OpenAPI definitions.
