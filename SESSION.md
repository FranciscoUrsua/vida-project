# SESSION — VIDA 360

_Actualizado: 2026-05-25_

## Tarea completada

Scoping por UO en UsuarioResource y PlantillaInformeResource: adm_usuarios solo ve y gestiona
datos de su propio subtree jerárquico de UOs.

## Estado actual

- **Autorización del panel Filament — completa:**
  - `User` implementa `FilamentUser` con `canAccessPanel`: adm_sistema, supervision, adm_usuarios.
  - `AutorizaGestion` trait restringe los 4 métodos can* a adm_sistema + adm_usuarios en 27 Resources.
  - 4 Resources con auth manual según rol especial (Rol, Usuario, Profesional, LogAlertas).
  - Middleware `FilamentAuthenticate` redirige a login en lugar de 403 para usuarios sin acceso al panel.
  - `ListRecords` base class personalizada: cierra el bypass por URL directa (authorizeAccess).
  - 4 widgets con `canView()` por rol; `DeleteAction` de tabla autorizado server-side.
  - **Scoping por UO** en UsuarioResource y PlantillaInformeResource: adm_usuarios ve y gestiona
    solo datos de su subtree jerárquico. `TieneUO::uoSubtreeIds()` centraliza el cálculo.
  - 16 tests en `tests/Feature/FilamentPanelAccessTest.php` — todos pasan ✅.

- **Backoffice Filament — restyling:** completado (sesión 2026-05-24).
- **Seeders:** todos los módulos con seeder e idempotentes (sesión 2026-05-23).
- **Suite completa:** ~110 tests pasan (sin Prestaciones) ✅.

## Tests incompletos actuales

| Test | Clase | Motivo |
|---|---|---|
| 3.5, 3.6, 3.8 | KAnonimatoTest | Requieren modelo Extraccion + job asíncrono |
| 6.6 | PerfilesTest | Requiere modelo Extraccion + relación con PerfilAnonimizacion |
| TF-USU-31 | UnidadOrganizativaTest | Policy jerárquica de adscripción de usuarios a UO |

## Siguiente paso recomendado

**TF-USU-31** — implementar la validación jerárquica en `UsuarioUoPolicy`:
un profesional solo puede ser adscrito a una UO descendiente de la UO del usuario que realiza
la operación. Tests ya documentados en `docs/instrucciones-cli/usuarios-tests.md`.

## Contexto relevante para retomar

- La autorización del panel Filament cubre todos los vectores identificados:
  acceso al panel, visibilidad de nav items, URL directa, widgets de dashboard, acciones de tabla.
- `ActividadCatalogosWidget` devuelve tabla vacía (stub) hasta que se instale auditoría.
- Los 9 fallos de PrestacionFilamentResourceTest: "Invalid Livewire snapshot structure" —
  es un problema de setup de tests, no del código de producción.
- `canAccessPanel` → logout + redirect a /admin/login (no 403) gracias al middleware personalizado.
