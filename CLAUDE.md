# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**VIDA 360** (Visión Integral de la Persona en Atención Social) is a social services management platform for the Ayuntamiento de Madrid. It manages beneficiary case histories, assessments, social benefits, and service centers for social workers.

The Laravel application lives in the `vida/` subdirectory. All commands below should be run from `vida/`.

## Commands

```bash
# Full setup from scratch
composer run setup

# Start all dev processes concurrently (web server, queue, log tailer, Vite HMR)
composer run dev

# Run tests (uses SQLite in-memory — never touches PostgreSQL)
composer run test

# Frontend only
npm run dev      # Vite HMR dev server
npm run build    # Production build

# Utility scripts (from repo root)
bash bash_scripts/clearCaches.sh     # Clear all Laravel + autoload caches
bash bash_scripts/reiniciaServer.sh  # Kill and restart artisan serve
```

## Architecture

### Directory Layout
- `app/` — Core application code (models, controllers, traits, services)
- `Modules/` — Self-contained domain modules (each has their own Models, Controllers, Requests, Resources, Routes, Providers, migrations)
- `routes/` — Web and API route definitions
- `database/migrations/` — Numbered to enforce dependency order (0001–4001); Centro module migrations were moved here from `Modules/Centro/`
- `resources/views/` — Blade templates using a partial-based layout (`layouts/app.blade.php` → `partials/header`, `sidebar`, `footer`)
- `docs/` — Architecture and requirements documents (Spanish)

### Modular Structure
Three modules registered in `modules_statuses.json`:
- **`Modules/AppUser/`** — System users (social workers, admins); authenticatable via Sanctum
- **`Modules/Centro/`** — Social service centers domain; has complete CRUD with FormRequests and API Resources
- **`Modules/Intervencion/`** — Case/intervention management (Historia, Valoracion, Ficha); routes are currently commented-out stubs

### Two Distinct User Types
- **`AppUser`** — Staff who log in to the system (`app_users` table, `Authenticatable` + `HasApiTokens`)
- **`SocialUser`** — Beneficiaries/citizens receiving services (`social_users` table, not authenticatable)

### Organizational Hierarchy
**`UnidadOrganizativa`** (`unidades_organizativas`, UUID PK) models the administrative tree (departments, services, units). It is self-referential (`parent_id`) with auto-calculated `nivel`. Uses a PostgreSQL recursive CTE for `descendientes()`. `Centro` and `AppUser` belong to an `UnidadOrganizativa`. Cycles in the hierarchy are blocked at model level.

### Cross-Cutting Traits
Two self-booting Eloquent traits applied to all non-auxiliary models:

- **`App\Common\Traits\Auditable`** — Hooks into `retrieved`, `created`, `updated`, `deleted`, `restored` to write every data operation to the `audits` table via `AuditService`. Tracks old/new values, acting user, IP, and user agent. Required by GDPR/RGPD rules in `docs/Requisitos.md`.
- **`App\Common\Traits\Versionable`** — On `created`/`updating`, inserts a full JSON snapshot into the `versions` table (polymorphic). Enables history of identity document changes (e.g., passport → NIE → DNI).

### Privacy and Security
- All PII fields in `SocialUser` use Laravel's `'encrypted'` cast (AES-256 transparent encryption at the application level): names, ID numbers, address components, contact details, coordinates.
- `requiere_permiso_especial` flag on `SocialUser` gates access to protected individuals (minors, domestic violence victims). Controllers enforce this via `can()` checks before returning or modifying data.
- Soft deletes everywhere — no hard deletes on sensitive data.

### Service Layer
- **`PadronService`** — Integrates with Madrid's municipal residents register (Padrón). Currently runs in `mockMode`; production POSTs to an authenticated external API.
- **`DomicilioValidatorService`** — Validates and geocodes Madrid addresses (bounds checking), populates `lat`/`lng`/`formatted_address`.
- **`AuditService`** — Centralized audit log writer; registered as singleton in `AppServiceProvider`.

### API and Routing
- `routes/api.php` — Main API routes (profesionales, centros, directores, prestaciones); currently missing auth middleware on most routes.
- `Modules/Centro/Routes/api.php` — Module-local routes for centros, directores, tipos-centros, centro-profesionales; all protected with `auth:sanctum`.
- **Routing overlap**: Both `routes/api.php` and the Centro module define centros/directores routes. The module routes are auth-protected; this suggests an in-progress migration to fully secured module-owned routes.
- `routes/api_intervencion.php` — Planned intervention routes; all commented out.
- `SANCTUM_STATEFUL_DOMAINS=localhost:4200` in `.env` — configured for a planned Angular SPA front-end.

### Frontend
- Blade/Livewire hybrid. Alpine.js (CDN) handles DOM interactions. Only `LoginWelcome` is currently implemented as a Livewire component.
- SCSS + Bootstrap 5 compiled through Vite. Local fonts in `resources/fonts/` for offline capability.

## Database

Migrations are numbered for strict dependency ordering. Key tables:

| Number | Table | Notes |
|--------|-------|-------|
| 0005 | `audits` | Polymorphic audit log |
| 1005 | `unidades_organizativas` | Self-referential org hierarchy (UUID PK, recursive CTE) |
| 2005 | `social_users` | Most complex table; all PII encrypted, full split address, `requiere_permiso_especial`, `identificacion_historial` JSON |
| 3016 | `centro_profesional` | Many-to-many pivot (centros ↔ profesionales) |
| 4001 | `versions` | Polymorphic version snapshots |

13 seeders provide reference data: world countries, Spanish regions, Madrid districts, professional qualifications, unidades organizativas, and a catalog of ~112 social benefits from Madrid's guide.

## Environment

- Dev uses PostgreSQL (`DB_CONNECTION=pgsql`, `DB_DATABASE=vida`)
- Tests use SQLite in-memory (`phpunit.xml` sets `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`)
- `.env.example` defaults to SQLite for easy local setup
