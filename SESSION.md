# SESSION — VIDA 360

_Actualizado: 2026-06-12_

## Tarea completada

Fix del pipeline CI/CD: los deploys no actualizaban el servidor porque `view:cache` fallaba silenciosamente por permisos. Los enlaces de MisCasosPage y los cambios de FichaCiudadanoPage ya están activos en producción/staging.

## Estado actual

### Navegación UI — completada y en producción
- `AgendaPage`: bifurcación de enlace por rol (intervencion → HS, resto → ficha ciudadano).
- `MisCasosPage`: columna nombre enlaza a `ciudadania.ciudadano.ficha`, columna HS enlaza a `intervencion.ciudadano.show`, clic en fila navega a `intervencion.ciudadano.show`. Los `<a>` son nativos (no `wire:navigate`) para evitar conflictos con Blade.
- `FichaCiudadanoPage`: widget permisos eliminado, banner HS condicional por rol, widgets de prestaciones condicionales.
- Tests TF-LW-NAV-01..24 completos (1 incomplete esperado: TF-LW-NAV-03).

### CI/CD — corregido
- `set -e`, permisos antes de artisan, `optimize:clear` antes de regenerar cachés, `git reset --hard` en lugar de `git pull`.

## Siguiente paso recomendado

Retomar el roadmap de pantallas operativas según el documento de instrucciones. Opciones candidatas:

1. **Pantalla de intervención del ciudadano** (`intervencion.ciudadano.show` / `FichaCiudadanoPage` del módulo Intervención) — destino de los enlaces de MisCasosPage que ahora funcionan.
2. **Migración progresiva de Bootstrap a Tailwind/tokens VIDA** en las vistas Livewire existentes (pendiente de decisión de prioridad).
3. **Módulo siguiente** según SESSION anterior o BACKLOG.

## Contexto relevante para retomar

- Rama `master`, último commit `2b11ce7`.
- El compiled view correcto ya está en producción. No hay deuda pendiente de la sesión actual.
- `docs/instrucciones-cli/` contiene instrucciones detalladas para cada módulo/tarea.
