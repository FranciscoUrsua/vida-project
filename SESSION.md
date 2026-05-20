# SESSION — VIDA 360

_Actualizado: 2026-05-20_

## Tarea completada

PF-02.3 (validación solapamiento perfiles horarios) implementado. Suite: **259 pasan, 0 fallos, 1 incompleto**.

## Estado actual

- **Módulo Agenda — tests funcionales:** 45 tests — 45 pasan ✅ — 0 pendientes — 0 fallos.
- **Suite completa:** 259 tests pasan ✅ — 0 fallos — 1 incompleto (TF-USU-31).

## Qué se implementó en esta sesión

**`PerfilHorarioProfesional::booted()`** — `Modules/Agenda/app/Models/PerfilHorarioProfesional.php`:
- Evento `saving`: si `activo = true`, comprueba que no exista otro perfil activo para el mismo `(usuario_id, centro_id)`. Si existe, lanza `LogicException`.
- Usa `when($perfil->exists, ...)` para excluir el propio registro en updates.

## Test incompleto restante (1)

| Test | Módulo | Requiere |
|---|---|---|
| TF-USU-31 | Usuarios | Policy/Service que impide a `administrador_usuarios` adscribir usuarios a UO fuera de su ámbito jerárquico |

## Siguiente paso recomendado

**TF-USU-31** — El único test pendiente en toda la suite. Requiere implementar la validación en `UsuarioUoPolicy` (o un Service dedicado) que compruebe que el usuario que intenta adscribir otro a una UO tiene autoridad jerárquica sobre esa UO.

Leer `Modules/Usuarios/tests/Feature/UnidadOrganizativaTest.php` para ver el contexto exacto del test antes de actuar.
