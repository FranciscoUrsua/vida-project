# VIDA 360 — Design System Skill

Use this when producing UI, slides, prototypes or marketing for **VIDA 360** (*Visión Integral de la Persona en Atención Social* — Ayuntamiento de Madrid).

## At a glance
- **Tone.** Warm calm, not cheerful. Serious, respectful, reassuring. Never gamified.
- **Audience.** Trained municipal social workers. Do not patronise.
- **Language.** Spanish. Sentence case. No emoji in product UI.
- **Stack shown to.** Laravel 12 · Bootstrap 5.3 · Livewire (operational surface) · Filament 5.3 (backoffice) · VIDA `op-*` components.

## Getting started
1. Read `README.md` top-to-bottom before any design work.
2. Tokens: in the application they live in `vida/resources/scss/_bootstrap-overrides.scss` (everything Bootstrap models) and `_vida-sass-tokens.scss` (the rest). In Blade use Bootstrap classes; in SCSS use those Sass variables or Bootstrap's `--bs-*`. **Never `var(--color-*)` outside Filament**: those variables do not exist in the operational or public bundles. `stylesheets/colors_and_type.css` is a reference for design artifacts only.
   Read `docs/instrucciones-cli/2026-09-bootstrap-unico.md` before touching Blade or SCSS.
3. Before any work on Blade, Livewire or SCSS: every class that is not Bootstrap must be in `vida/config/ui-catalogo.php`, and `php artisan ui:auditar` must pass (it blocks CI).
4. For implementation:
   - Blade/Livewire operativo y publico: Bootstrap 5.3 como capa base, tokens VIDA y componentes `op-*` reutilizables.
   - Filament: tema VIDA y componentes nativos.

## Palette in one line
Primary `#2A5B8A` (Azul Retiro) · Accent `#C76E4A` (Terracotta) · Paper `#FAF7F1` · Ink-900 `#1D160E` · Protected `#6B3D6B`.

## Type in one line
Source Sans 3 (UI) · Source Serif 4 (display only) · JetBrains Mono (codes, DNI, audit IDs).

## Must-follow rules
- Body ≥ 16px. Line-height 1.5.
- Buttons: sentence case. No Title Case.
- Chips: `--radius-pill`, 12px / 600, semantic soft-bg + ink-coloured text.
- Cards: white bg, 1px ink-200 border, `--shadow-1`, 8px radius, 20px padding.
- Focus ring is mandatory: Bootstrap's focus ring (`$focus-ring-*` in `_bootstrap-overrides.scss`).
- AI-assisted output carries the `Sugerencia IA` chip + `<x-heroicon-o-sparkles />` icon and needs professional validation.
- Protected records (menores, VG): show the protected banner; never hide the status.
- Bootstrap 5.3 is the primitive layer for Blade/Livewire UI: buttons, forms, tables, modals, grid, spacing. Install locally via npm + Vite, never via CDN.
- Layer order: (1) VIDA tokens as Bootstrap variables → (2) Bootstrap standard classes → (3) shared `op-*` product components → (4) screen-specific classes only for genuine structural needs.
- Never create `xxx-btn`, `xxx-input`, `xxx-modal` classes when Bootstrap already covers it.
- Icons: **Heroicons** everywhere via `<x-heroicon-o-name />` (outline) or `<x-heroicon-s-name />` (solid), using `blade-ui-kit/blade-heroicons`. Dynamic names: `<x-dynamic-component :component="'heroicon-o-' . $name" />`. No Bootstrap Icons, no Tabler Icons, no icon CDNs, no icon fonts.
- No structural inline styles in Blade. Inline styles only for unavoidable dynamic values.
- No gradients, no glassmorphism, no bounce animation, no decorative SVGs, no emoji in product chrome.

## Fixed terminology
`Ciudadano/a` · `Historia Social` · `Plan de intervención` · `Prestación` · `Apunte` · `Derivación` · TSR · SIA · ASP · VG · PSH · UO.

Never say: *usuario, cliente, beneficiario, expediente* (UI), *ayuda*, *plan de actuación*.

## Dates & numbers
`14 abr 2026` · `14/04/2026` · 24h `09:30` · `420,00 €`.

## Don't
- Don't invent a municipal logo. Use the provisional `vida360-wordmark.svg` and flag for user sign-off.
- Don't hardcode colectivos protegidos — they're configurable.
- Don't produce dark-mode variants unless asked.
