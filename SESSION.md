# SESSION — VIDA 360

_Actualizado: 2026-06-17_

## Tarea completada

Panel de relaciones entre ciudadanos + UC solo lectura implementados en `FichaCiudadanoPage`. 24/24 tests en verde (TF-LW-REL-01..20 + TF-LW-UC-01..04).

## Estado actual

### Cambios aplicados en esta sesión

**Módulo Ciudadania — FichaCiudadanoPage**
- Modal completo de relaciones: crear, editar (solo observaciones) y cerrar.
- `ucVigente()` real (reemplaza stub). `ucMiembros()` con tipo de relación enriquecido.
- `puedeEditarRelaciones()`: solo `intervencion` y `tramitacion`; `consulta_basica` solo lectura.
- Todos los eager loads de ciudadanos relacionados/convivientes usan `withoutGlobalScope(AmbitoUoScope::class)`.
- Blade: panel Relaciones con historial colapsable + modal + panel Convivientes solo lectura.
- Tests: 24/24 verdes en `RelacionesCiudadanoTest.php`.

**CLAUDE.md — PHPDoc**
- Reglas ampliadas con tabla por tipo de artefacto (enums, Filament Pages, Livewire Computed, scopes, relaciones Eloquent, observers).
- Autocomprobación obligatoria al finalizar cada tarea.

## Siguiente paso recomendado

1. **Cargar mundo demo desde el backoffice** — Panel «Demo Worlds», contraseñas `demo1234`.
2. **Modal gestión de relaciones en FichaCiudadanoPage** — implementado; probar en el navegador con datos reales.
3. **Widget UC con tipo de relación en CiudadanoPage** — la pantalla de intervención ya muestra la UC pero sin el tipo de relación. Se puede enriquecer igual que `ucMiembros()` en FichaCiudadanoPage.
4. **Fichas sociales / Formulario de valoración** — bloquea el PISO completo.

## Contexto técnico para retomar

### AmbitoUoScope — regla de oro
Cualquier eager load de `ciudadano` o `ciudadanoRelacionado` desde un componente que pivota sobre ciudadano (no sobre HistoriaSocial) **debe** incluir `withoutGlobalScope(AmbitoUoScope::class)`. Sin eso, los ciudadanos de otras UOs aparecen con datos null.

### Relaciones — edición limitada
La edición de una relación existente solo permite modificar `observaciones`. El tipo y los ciudadanos implicados son inmutables; si el vínculo cambia, hay que cerrar la relación y crear una nueva.

### puedeEditarRelaciones vs puedeEditar
- `puedeEditar`: `['intervencion', 'tramitacion', 'consulta_basica']` — para datos de Capa 1.
- `puedeEditarRelaciones`: `['intervencion', 'tramitacion']` — para relaciones jurídicas.
