# VIDA 360 Design System

**VIDA 360** — *Visión Integral de la Persona en Atención Social* — is the social-services management platform of the Ayuntamiento de Madrid. It is a calm, professional tool used every working day by social workers, administrators, and municipal supervisors to manage citizen case histories, benefits (*prestaciones*), centres, intervention plans and follow-ups.

This folder is the design system that drives every UI, slide, prototype, mock and asset produced for VIDA 360. It is organised around three ideas that guide every visual decision:

1. **Warm calm, not cheerful.** Citizens in contact with social services are often in vulnerable situations. The product must feel serious, respectful and reassuring — never gamified, never loud.
2. **Present what the professional needs, when they need it.** Density is low by default; complexity reveals itself on demand. Negative space is structural, not decorative.
3. **Legibility above all.** Large, comfortable type. High contrast. Generous line-height. No decorative fonts. No thin weights on body copy.

---

## Product context

VIDA 360 is a Laravel 12 / PHP 8.3 monolith with a Blade + Livewire + Alpine.js frontend, Bootstrap 5 layout primitives, and a Filament 5.3 admin backoffice. Interfaces split into two distinct surfaces with different ergonomics:

- **Filament backoffice** — configuration, catalogues, users, roles, permissions. Table-dense, keyboard-driven.
- **Livewire operational surface** — the daily professional workspace: citizen histories, intervention plans, notes, agenda, prescriptions, alerts, internal messaging.

Two kinds of people use it, with different access rules:
- `Usuario` — staff (social workers, supervisors, admins). They log in.
- `Ciudadano` — citizens receiving services. They do not log in; their record is the object of care.

### Core domain concepts the design must respect
- **TSR** (*Trabajador Social de Referencia*) — the one social worker responsible for each citizen's global view.
- **SIA** (*Servicio de Información y Asesoramiento*) — the front-of-house service that triages demands.
- **Historia Social** — the single, unique record of a citizen. Pertenece al ciudadano, no al profesional.
- **Plan de intervención** — the action plan; a citizen can have several active in parallel (ASP + one or more specialised).
- **Prestación** — a benefit (service or monetary). Referenced from a municipal catalogue of ~112 entries organised into 8 *objetivos generales*.
- **Centros, redes, plazas, actividades** — places, pools, beds/slots, activities.
- **Alertas** — app-generated notifications with two severities: `aviso` (dismissible) and `alerta` (requires explicit acknowledgement within 4 working hours).
- **Colectivos especialmente protegidos** — minors, gender-violence victims — require prior authorisation to read their record. This is a UI flag, not a hidden field.

### Sources consulted to build this system
- GitHub repo: `FranciscoUrsua/vida-project@master` (imported on demand via `github_read_file` / `github_import_files`)
  - `README.md`, `CLAUDE.md` — project overview, stack, architecture
  - `docs/principios-vida360.md` — design principles, tone, privacy posture
  - `docs/documentacion-proyecto.md` — full module and entity documentation
  - `docs/glosario.md`, `docs/modulo-*.md` — module briefs (ciudadanía, agenda, centros, documentos, integraciones, intervención, mensajes, prestaciones, usuarios-permisos)
  - `vida/resources/views/livewire/admin/gestor-unidades-organizativas.blade.php` — real Livewire page, source of truth for existing Tailwind utility patterns
  - `vida/resources/views/filament/prestaciones/snapshot-modal.blade.php` — Filament partial showing the admin table style
- The original `vida/resources/views/welcome.blade.php` was the stock Laravel welcome page with no VIDA branding; it was not used as a reference and is not imported.

Nothing visual was pre-designed in the codebase — there is no figma file, no brand book, no logo. **The visual language in this system is a proposal derived from the product's values and stated principles** (see Visual Foundations). Flagged for user review below.

---

## Content fundamentals

**Language.** Spanish, always. Code comments, UI strings, model names, error messages — all Spanish (*Principio 4.7*). English only for unavoidable technical terms (`API`, `log`, `token`).

**Voice.** Professional, neutral, direct. We speak to a trained social worker, not a consumer. We never patronise. We never celebrate. We do not use exclamation marks in UI copy except for destructive confirmation (`¡Atención!`).

**Person.** Default to impersonal/infinitive for system-initiated copy (`Buscar ciudadano…`, `Añadir apunte`, `Guardar cambios`). Use *usted* only in citizen-facing surfaces (if/when citizens gain access); in the professional tool, use actions and objects, not second person.

**Casing.** Sentence case for everything — buttons, titles, menu items, tabs. No ALL CAPS except for two-letter acronyms in context (DNI, NIE, UO, TSR, SIA, ASP, VG, PSH, RGPD). No Title Case on buttons.

**Terminology (fixed).**
- *Ciudadano/a*, never "usuario", "cliente", "beneficiario" in UI (internally *Ciudadano* is the model — preserve).
- *Historia Social*, not "expediente" in UI (even though colloquially equivalent).
- *Plan de intervención*, not "plan de actuación".
- *Prestación*, not "ayuda" or "beneficio".
- *Trabajador/a Social de Referencia* or TSR — spell out at first reference on a screen, use acronym afterwards.
- *Apunte* — a dated professional note attached to a Historia Social.
- *Derivación* — referral from ASP to specialised attention.

**Numbers & dates.** Spanish conventions. Date: `14 abr 2026` or `14/04/2026`. Time: 24h (`09:30`). Currency: `€` after the amount with a space (`420,00 €`).

**Empty states.** Factual, no apology.
- Good: *Aún no hay apuntes en esta historia social.*
- Bad: *¡Ups! Parece que todavía no hay nada por aquí.*

**Confirmations.** Explicit action verbs in both buttons.
- Good: *Cancelar* / *Eliminar apunte*
- Bad: *No* / *Sí*

**Protected records.** Clear, neutral warning with action — never sensational.
- Good: *Este ciudadano pertenece a un colectivo especialmente protegido. Es necesario solicitar acceso antes de consultar su historia social.*

**Emoji.** Not used in product UI. Emoji may appear in onboarding email templates or celebratory admin messages at the user's discretion — never in operational screens.

**AI-generated content.** Must be labelled. When a suggestion, classification or alert comes from an AI component, show a small `Sugerencia IA` tag and require explicit professional validation before any consequence (*Principio 3.10*). Copy does not use emojis or sparkles — a plain text badge plus the `wand-2` icon.

---

## Visual foundations

### Mood
Warm neutrals + one trustworthy blue + one caring terracotta accent. White space carries structure. No gradients except as a single very subtle fog from the top of hero banners. No glassmorphism. No neon. No dark gradients. Interfaces read as "well-lit municipal office", not "startup dashboard".

### Colour
- **Primary — `--color-primary` / `#2A5B8A`** — *Azul Retiro*, a deep lake-blue. Used for primary actions, selected states and focus rings. Derived from the `blue-600/700` pair already present in Livewire templates, desaturated to feel calmer and more institutional.
- **Accent — `--color-accent` / `#C76E4A`** — *Terracotta Madrileña*, a warm burnt-sienna. Used sparingly for highlights, hero shapes and to signal the citizen-care side of the product. Not for clickable actions.
- **Neutrals** — a warm grey ramp (`--color-ink-*`) tuned slightly towards sepia rather than pure grey, so text on background feels softer.
- **Semantic** — four functional colours, kept muted: `--color-success` sage-green, `--color-warning` amber ochre, `--color-danger` brick-red, `--color-info` mid-blue.
- **Protected-records** — `--color-protected` deep plum, used only as a badge/border accent on screens showing protected citizens (VG, minors).

### Typography
- **Primary:** *Source Sans 3* (Adobe / Google Fonts). Humanist sans, designed for long-form UI text, excellent legibility at small sizes. Weights used: 400, 500, 600, 700.
- **Display:** *Source Serif 4*, used only for large hero titles in marketing / onboarding / empty-state hero. Not used in product chrome.
- **Mono:** *JetBrains Mono*, used only for codes (`010101`), DNIs/NIEs, audit log identifiers.

All three are loaded from Google Fonts via `colors_and_type.css` — no font files are shipped and none need to be. If the municipality mandates a specific branded family (e.g. a licensed *Gotham* / *Mercury*-style typeface), we'll swap on request.

### Scale
A modest type scale. Body is `16px` minimum. Smallest UI label is `12px` (metadata only). Line-heights are generous: `1.5` for body, `1.35` for titles. Never use letter-spacing on body; `-0.01em` on display sizes only.

### Spacing
4-point scale (`4, 8, 12, 16, 24, 32, 48, 64, 96`). Expressed as `--space-1` … `--space-9`.

### Corner radii
- `--radius-sm` `4px` — inputs, small tags.
- `--radius-md` `8px` — buttons, cards.
- `--radius-lg` `14px` — dialogs, elevated panels.
- `--radius-pill` `999px` — status chips.

No hard-edged rectangles anywhere. No heavily rounded "mobile app" blobs either.

### Elevation & shadow
Two shadow primitives, both warm:
- `--shadow-1` — resting card lift, `0 1px 2px rgba(29, 22, 14, .04), 0 1px 3px rgba(29, 22, 14, .06)`.
- `--shadow-2` — floating panels, modals, `0 8px 24px rgba(29, 22, 14, .08), 0 2px 6px rgba(29, 22, 14, .05)`.
No inset shadows except a 1px inner on focused inputs.

### Borders
Default divider: `1px solid #E6E1D8`. Strong divider (section breaks): `1px solid #D7CFBE`. Borders are always present on data tables and input rows — we never rely on background colour alone.

### Cards
Flat-ish. White background, `1px` border, `--shadow-1`, `--radius-md`. Never shadow without border; never border without padding. Card title sits above content with 16px gap; no horizontal rule under the title by default.

### Backgrounds & textures
- App background: `--color-paper` `#FAF7F1` — very soft cream.
- Content surfaces: `#FFFFFF`.
- Section hero backgrounds (rare): `--color-sand` `#F2EADA`, with a single wide terracotta circle clipped at low opacity as a motif — never a photograph, never a gradient.
- No repeating patterns, no grain, no noise, no illustrations behind text.

### Imagery
Iconography instead of photos wherever possible (see ICONOGRAPHY). When a photograph is needed (e.g. a centre's exterior), treat it with a warm duotone filter — terracotta + cream — to keep it calm and consistent. Edges always rounded to `--radius-md`.

### Animation
- Transitions are 160ms `cubic-bezier(.2,.6,.2,1)` (ease-out, slight overshoot *disabled*).
- No bounce. No spring. No shimmer.
- Use animation to explain state change (a panel sliding in, a row highlighting after save), never for delight.
- Skeleton loaders fade in at 120ms; replace with content on load, no stagger.

### Hover
- Buttons darken by 4% (lightness in OKLCH). No shadow change on hover.
- Links gain a 1px underline (if not already underlined).
- Cards that are clickable get a `1px` darker border on hover, no transform.

### Press / active
- Buttons: additional -4% lightness and a 1px inset translate (`transform: translateY(1px)`), 60ms.
- No scale transforms.

### Focus
Always visible. `2px` outline in `--color-primary` with a `2px` offset. Keyboard focus is a first-class interaction.

### Disabled
50% opacity + `cursor: not-allowed`. No further style change — the opacity tells the whole story.

### Transparency & blur
Used only on modal scrims: `rgba(29, 22, 14, .40)` + `backdrop-filter: blur(2px)`. Nowhere else.

### Layout rules
- A persistent left sidebar (240px) on desktop for the operational surface.
- A persistent top header (56px) with breadcrumb, search, user menu, alerts bell.
- Content area is max 1280px wide, centred when viewport exceeds it. Internal forms and tables sit in a 2-column `40% / 60%` split on wide screens.
- Filament backoffice uses its own default chrome; we only restyle tokens (colours + radii + type).

### Data tables
- Header row is `--color-sand`, body rows white, hover `--color-paper`.
- Row height `52px` (comfortable), `40px` (compact toggle).
- First column often carries an avatar or icon (24px).
- Truncation uses ellipsis + tooltip; never wrap in data tables.

### Status chips
Pill (`--radius-pill`), `12px` text, `600` weight, uppercase-off. Background is the semantic colour at 12% alpha, text is the semantic colour at full strength. Examples:
`Activo` (success), `Pendiente` (warning), `Vencida` (danger), `Escalada` (info), `Aprobación previa` (protected).

---

## Iconography

**Icon set.** The repo does not ship a bespoke icon set. Filament ships with Heroicons by default, and Bootstrap 5 surfaces use Bootstrap Icons. Given the warmth/calm brief and the dual backoffice/operational product, we standardise on **[Lucide](https://lucide.dev)** (fork of Feather, 1.5px strokes, rounded caps — warmer than Heroicons, more modern than Bootstrap Icons) and document a 1:1 mapping for the common operations VIDA needs.

- **Stroke width:** 1.75px (default Lucide is 2 — we thin it slightly for the calmer feel).
- **Sizes:** 16 (inline with text), 20 (button), 24 (sidebar, menu), 32 (feature blocks), 48 (empty states only).
- **Colour:** inherits `currentColor`. Never two-tone.

**Loading.** Via CDN — `https://unpkg.com/lucide@latest` — with `data-lucide="..."` attributes. A sample set is copied into `assets/icons/` for offline use.

**Emoji.** Never as UI primitives. Only permissible in free-text user-generated content (e.g. a note a professional types).

**Unicode.** Used only for typographical marks: `—` em dash in empty states, `·` middot as meta separator, `›` chevron for breadcrumbs when no icon is available.

**Logos & wordmark.** No municipal logo is shipped with the repo. A provisional VIDA 360 wordmark has been drafted in `assets/logos/` using the product typography. **Flagged** — please provide the real municipal logo usage rules if this is to appear alongside the Ayuntamiento de Madrid brand.

**Illustrations.** None shipped, none invented. Empty states use a single large Lucide glyph in `--color-ink-400` with accompanying copy.

---

## Index — what's in this folder

| Path | What it is |
|---|---|
| `README.md` | This file — the one document to read first. |
| `colors_and_type.css` | All design tokens. Import this into any HTML artifact. |
| `SKILL.md` | Portable skill descriptor for Claude Code / agents. |
| `assets/logos/` | Provisional VIDA 360 wordmark (SVG). |
| `assets/icons/` | Sample Lucide icons copied locally (SVG). |
| `fonts/` | Notes on the Google Fonts used (loaded via `colors_and_type.css`). |
| `preview/` | HTML preview cards for the Design System tab (one per concept). |
| `ui_kits/vida_app/` | Operational (Livewire) UI kit: sidebar, header, citizen ficha, intervention plan, alerts, messaging. |

---

*Flagged uncertainties are collected at the bottom of this turn's summary.*
