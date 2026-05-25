# SESSION — VIDA 360

_Actualizado: 2026-05-25_

## Tarea completada

Habilitado el rol `supervision` en el backoffice Filament: acceso de solo lectura
a Centros, Redes, Profesionales, Plantillas de informe, Estilos de informe, Informes
y Documentos, todos filtrados por el subtree de UO del usuario.

## Estado actual

- **Acceso supervision al backoffice — completo:**
  - `canViewAny()` añadido (o sobreescrito) en 7 resources.
  - `modifyQueryUsing` nuevo o heredado en todos, con scoping por subtree de UO.
  - Escritura (crear/editar/eliminar/anular) bloqueada para supervision en todos los casos.

- **Seguridad en profundidad para datos de ciudadanos — completa** (sesión anterior):
  - `AmbitoUoScope`, Policies completas, servicios de dominio y 18 tests de autorización.

- **Autorización del panel Filament — completa** (sesión anterior).
- **Backoffice Filament — restyling:** completado (2026-05-24).
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
de dominio (deduplicación, niveles de identificación, historial de datos).

## Contexto relevante para retomar

- `app/Services/HistoriaSocialService` (stub de integración con Mensajes) convive con
  `Modules/Intervencion/Services/HistoriaSocialService` (de seguridad). Deben fusionarse
  cuando se consolide el módulo Intervencion.
- Los 9 fallos de PrestacionFilamentResourceTest: "Invalid Livewire snapshot structure" —
  problema pre-existente de setup de tests, no del código de producción.
- La restricción "Nivel 2 = solo lectura fuera de UO" se aplica en la Policy, no en el scope.
