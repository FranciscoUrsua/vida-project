# SESSION — VIDA 360

_Actualizado: 2026-06-01_

## Tarea completada

Implementación de la Entrega 3 del interfaz operativo de Intervención: pantalla del ciudadano
con timeline de Historia Social, 7 herramientas de registro (4 inline + 3 pantalla completa).
23/23 tests nuevos en verde. 92 tests del módulo Intervención pasan.

## Estado actual

- **UI Intervención Entrega 3 — completa:**
  - `CiudadanoPage`: pantalla principal con timeline HS, UC colapsable, cuadrícula de herramientas.
  - Herramientas inline: entrevista, anotación, derivación, gestión/coordinación.
  - `RegistrarValoracionPage` y `RegistrarEscalaPage`: pantallas completas con formularios dinámicos.
  - `calcularScoreEscala()`: suma `valor × peso` por ítem del schema.
  - `TipoApunte` extendido con: `Valoracion`, `Escala`, `GestionCoordinacion`, `PlanIntervencion`.
  - Tests TF-LW-CIU-01..23 en verde.

- **UI Intervención Entrega 2 — completa** (MisCasosPage, BuscarCiudadanoPage, BuzonPage).
- **UI Intervención Entrega 1 — completa** (AgendaPage, Sidebar, layout operativo).
- **Autenticación — completa**.

## Pendientes conocidos

| Componente | Pendiente |
|---|---|
| `CiudadanoPage` | "Ver PISO" → Entrega 4 |
| `crearDerivacion()` | Tabla `derivaciones` no existe — solo crea Apunte |
| UC | Tabla `unidades_convivencia` no existe — stub visible |
| Herramienta Informes | Stub "en construcción" — integración Documentos pendiente |

## Siguiente paso recomendado

**UI Intervención — Entrega 4** o cualquier otra tarea de backlog según prioridad.
La suite de las 3 entregas tiene 92 tests en verde.

## Contexto relevante para retomar

- `Apunte` usa `plan_id` (NOT NULL) → siempre requiere un plan activo para crear apuntes.
- `withoutGlobalScopes()` en `apuntesHS` y `pisoActivo` es deliberado: la policy ya verificó acceso.
- Los 9 fallos de `PrestacionFilamentResourceTest` son pre-existentes.
