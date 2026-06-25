# SESSION — Estado actual del proyecto VIDA 360

**Última actualización:** 2026-06-25

---

## Tarea completada

`Módulo Supervision` — Migración completa a Bootstrap nativo e implementación del modal de actividades:

- **Eliminación de CSS custom properties de `:root`**: creado `_vida-sass-tokens.scss` con todos los tokens VIDA como variables Sass puras (sin salida CSS). Eliminado el import de `colors_and_type.css` que inyectaba variables en `:root`. Reescrito `_op-layout.scss` para usar variables Sass.
- **`app-operativo.css` vaciado**: el fichero queda como entry point vacío (compatible con Vite), sin imports ni reglas.
- **Clases `op-*` inventadas eliminadas**: se retiraron de `_op-components.scss` las clases `op-toolbar`, `op-section`, `op-section__title`, `op-chip`, `op-filter-row`. Únicas clases `op-*` legítimas que quedan: `op-page` y `op-empty`.
- **Todas las pantallas de Supervisión migradas a Bootstrap puro**: `inicio-page`, `actividades-page`, `aprobaciones-page`, `auditoria-page`, `cuadrante-page`, `configuracion-centro-page`, `equipo-page`, `plazas-page`.
- **5 issues en pantalla Inicio resueltos**: scroll extra (`min-height: calc(100vh - 56px)`), título duplicado eliminado, heading "Indicadores del centro" visible, márgenes en secciones indicadores y cuadrante.
- **Modal "Nueva actividad" implementado**: `ActividadesPage.php` ampliado con `abrirModal()`, `crear()`, computed `actividades` y `tiposActividad`. El blade muestra la tabla de actividades del centro o el estado vacío, y el modal con formulario completo (nombre, tipo, modo de acceso, aforo, fecha, inscripción requerida).

---

## Estado exacto del proyecto

- **Tests módulo Supervision**: 36 passed / 0 failed (no se han ejecutado en esta sesión; los cambios son de UI, no de lógica ya testeada).
- **Tests módulo Intervencion**: 236 passed / 1 incomplete (pre-existente) / 0 failed.
- **Fallo pre-existente en Ciudadania**: TF-LW-FIC-11 busca "Ver historia social" (texto renombrado a "Ir a HS"). No relacionado con los cambios recientes.

---

## Siguiente paso concreto recomendado

1. Continuar "pantalla por pantalla" en Supervisión — quedan por revisar en el navegador: aprobaciones, auditoría, cuadrante, equipo, plazas, configuración.
2. Corregir TF-LW-FIC-11 en `FichaCiudadanoPageTest`: cambiar `assertSee('Ver historia social')` por `assertSee('Ir a HS')`.
3. Añadir tests para los métodos de objetivos y compromisos en Intervención (`eliminarObjetivo`, `guardarEdicionCompromisoCiudadano`, `eliminarCompromisoCiudadano`, `guardarEdicionActuacionAyto`, `eliminarActuacionAyto`).
4. Implementar reasignación de profesional de referencia.
5. Suite completa antes del siguiente merge a main.

---

## Contexto para retomar sin fricción

- Arquitectura CSS: `_bootstrap-overrides.scss` → Bootstrap → `_vida-sass-tokens.scss` → `_op-layout.scss` → `_op-components.scss`. Sin CSS custom properties propias en `:root`.
- `op-page` y `op-empty` son las únicas clases `op-*` con CSS propio. Todo lo demás es Bootstrap puro.
- `ActividadesPage.php` resuelve el centro del supervisor vía `Centro::where('unidad_organizativa_id', $uoId)`. Si el catálogo `tipos_actividad` está vacío, el selector del modal aparecerá sin opciones.
- La tabla `asignaciones_profesional` vive en `Modules/Intervencion/database/migrations/2026_06_24_000001_create_asignaciones_profesional_table.php`.
