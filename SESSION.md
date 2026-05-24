# SESSION — VIDA 360

_Actualizado: 2026-05-24_

## Tarea completada

Autorización del panel Filament: `canAccessPanel` en User, trait `AutorizaGestion` en 27 Resources,
métodos manuales en 4 Resources especiales (RolResource, UsuarioResource, ProfesionalResource,
LogAlertasResource). 14 tests pasan ✅.

## Estado actual

- **Autorización del panel Filament:**
  - `User` implementa `FilamentUser` con `canAccessPanel`: acceso para adm_sistema, supervision, adm_usuarios.
  - `AutorizaGestion` trait restringe los 4 métodos can* a adm_sistema + adm_usuarios.
  - 27 Resources estándar usan el trait; 4 Resources tienen auth manual según su caso especial.
  - 14 tests en `tests/Feature/FilamentPanelAccessTest.php` — todos pasan.
- **Backoffice Filament — restyling:** completado (sesión 2026-05-24).
- **Seeders:** todos los módulos con seeder e idempotentes (sesión 2026-05-23).
- **Suite completa:** ~110 tests pasan (sin Prestaciones) ✅. Los 9 fallos en
  `PrestacionFilamentResourceTest` son pre-existentes (Livewire snapshot structure — incompatibilidad
  de test, no de código de producción).

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

- `canAccessPanel` devuelve 403 (no redirect) para usuarios autenticados sin rol de panel.
  El redirect a /admin/login solo ocurre para usuarios no autenticados.
- `ActividadCatalogosWidget` devuelve tabla vacía (stub) hasta que se instale auditoría.
- Los 9 fallos de PrestacionFilamentResourceTest: "Invalid Livewire snapshot structure" —
  es un problema de setup de tests, no del código de producción. El Resource funciona en prod.
