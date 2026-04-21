# VIDA 360 — Design System Skill

Use this when producing UI, slides, prototypes or marketing for **VIDA 360** (*Visión Integral de la Persona en Atención Social* — Ayuntamiento de Madrid).

## At a glance
- **Tone.** Warm calm, not cheerful. Serious, respectful, reassuring. Never gamified.
- **Audience.** Trained municipal social workers. Do not patronise.
- **Language.** Spanish. Sentence case. No emoji in product UI.
- **Stack shown to.** Laravel 12 + Livewire (operational surface) · Filament 5.3 (backoffice).

## Getting started
1. Read `README.md` top-to-bottom before any design work.
2. Link `colors_and_type.css` as the single source of tokens.
3. Use `ui_kits/vida_app/kit.css` for operational-surface patterns.

## Palette in one line
Primary `#2A5B8A` (Azul Retiro) · Accent `#C76E4A` (Terracotta) · Paper `#FAF7F1` · Ink-900 `#1D160E` · Protected `#6B3D6B`.

## Type in one line
Source Sans 3 (UI) · Source Serif 4 (display only) · JetBrains Mono (codes, DNI, audit IDs).

## Must-follow rules
- Body ≥ 16px. Line-height 1.5.
- Buttons: sentence case. No Title Case.
- Chips: `--radius-pill`, 12px / 600, semantic soft-bg + ink-coloured text.
- Cards: white bg, 1px ink-200 border, `--shadow-1`, 8px radius, 20px padding.
- Focus ring is mandatory: 2px `--color-primary`, 2px offset.
- AI-assisted output carries the `Sugerencia IA` chip + `wand-2` icon and needs professional validation.
- Protected records (menores, VG): show the protected banner; never hide the status.
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
