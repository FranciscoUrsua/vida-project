# SESSION — VIDA 360

_Actualizado: 2026-05-25_

## Tarea completada

Implementación de estrategia de seguridad en profundidad para datos sensibles:
GlobalScope `AmbitoUoScope`, Policies completas, servicios de dominio con autorización
y suite de tests de autorización (18 tests nuevos, todos verdes).

## Estado actual

- **Seguridad en profundidad para datos de ciudadanos — completa:**
  - `AmbitoUoScope` filtra automáticamente HistoriaSocial, Apunte, Ciudadano y PlanDeIntervencion
    por ámbito de UO del usuario. Sin login: no filtra. adm_sistema: no filtra.
    Desactivable con `withoutGlobalScope(AmbitoUoScope::class)`.
  - **Policies** con 3 pasos: permiso atómico → ámbito UO (subtree) → colectivo protegido.
    - `HistoriaSocialPolicy`: reescrita con `viewAny/view/create/update/delete`.
    - `ApuntePolicy`: regla absoluta de privacidad (precedencia total sobre cualquier rol).
    - `CiudadanoPolicy` (nueva): ámbito vía Historia Social activa.
    - `PlanDeIntervencionPolicy` (nueva): ámbito vía Historia Social del plan.
  - **Servicios de dominio** con par (GlobalScope + Policy):
    - `Modules/Intervencion/Services/HistoriaSocialService`
    - `Modules/Intervencion/Services/ApunteService`
    - `Modules/Intervencion/Services/PlanDeIntervencionService`
    - `app/Services/CiudadanoService`
  - **Seeders actualizados**: nuevos permisos `ciudadano.leer/eliminar`, `historia.crear/eliminar`,
    `apunte.leer/editar/eliminar`, `plan.leer/eliminar` (idempotentes).
  - **18 tests** en `tests/Feature/AutorizacionDatosTest.php` — todos pasan ✅.

- **Autorización del panel Filament — completa** (sesión anterior).
- **Backoffice Filament — restyling:** completado (sesión 2026-05-24).
- **Seeders:** todos idempotentes (sesión 2026-05-23).
- **Suite completa:** ~375 tests pasan (9 fallos pre-existentes en PrestacionFilamentResourceTest) ✅.

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

Alternativa: **Módulo Ciudadania** — implementar el modelo Ciudadano completo con toda la lógica
de dominio (deduplicación, niveles de identificación, historial de datos), ya que el stub en
`App\Models\Ciudadano` tiene el scope y la Policy pero no tiene lógica de negocio.

## Contexto relevante para retomar

- Los cambios están staged pero NO commiteados (dejar para revisión del desarrollador).
- `app/Services/HistoriaSocialService` (stub existente de integración con Mensajes) convive con
  `Modules/Intervencion/Services/HistoriaSocialService` (nuevo, de seguridad). Deben fusionarse
  cuando se consolide el módulo Intervencion.
- La restricción de "Nivel 2 = solo lectura fuera de UO" se aplica en la Policy, no en el scope.
  El scope solo limita el browse automático (listados); la Policy controla el acceso individual.
- Los 9 fallos de PrestacionFilamentResourceTest: "Invalid Livewire snapshot structure" —
  es un problema pre-existente de setup de tests, no del código de producción.
- `CentroResource`, `EstiloInformeResource` y `UsuarioRolResource` tienen cambios pendientes
  de commit de la sesión anterior (scoping por UO en queries de tabla).
