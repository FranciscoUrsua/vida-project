# SESSION — Estado actual del proyecto VIDA 360

**Última actualización:** 2026-09-25

---

## Tarea completada

**Backoffice de usuarios: alta rápida de profesional, historial de roles de solo lectura, nadie aprueba su propia solicitud de rol, y nivel de supervisión explícito para todos los roles.** Detalle en `CHANGELOG-092026.md`.

Antes, el mismo día: seeders de cargos alineados con la BD, roles sugeridos por cargo y bloqueo de editar o borrar el propio usuario.

---

## Estado exacto del proyecto

- **⚠️ La BD local y la de staging son la misma** (`vida@127.0.0.1`, ver `BACKLOG.md`). Todo lo que se migre o cargue «en local» ocurre en staging. **No lanzar `demo:reset` desde local.**
- En esa BD compartida, a 2026-09-25:
  - Migraciones `create_cargo_roles_sugeridos_table` y `add_cargo_roles_revisado_id_to_users_table` aplicadas.
  - 9 cargos con slug (`ts`, `psicologo`, `educadorsocial`, `terapeutaocupacional`, `auxss`, `abogado`, `coordinador`, `administrativo`, `auxadmin`) y todos con roles sugeridos, configurados a mano por el desarrollador. Los seeders coinciden con este estado.
  - `configuracion_roles` completa: `adm_sistema` y `supervision` con aprobación previa, el resto con alerta supervisada.
  - Siguen vigentes el mundo `demo_ciam` (980 registros TEST_CIAM) y `pia.admite_entrada_directa = true`.
- **Código de staging** (`/var/www/vida-project/vida`): se despliega solo con cada push a `master` (job `deploy` de `.github/workflows/ci.yml`, tras pasar `test`). No hace falta desplegar a mano.
- **Tests:**
  - `Modules/Usuarios/tests/`: 63 passed y 1 incomplete (ya existía).
  - `Modules/Supervision/tests`: 34 passed y **3 fallos ya existentes** (`sidebar_sin_plazas_no_muestra_item_plazas`, `ficha_profesional_muestra_tres_pestanas`, `auditoria_con_colectivos_muestra_columna_protegido`). Fallan igual en master sin estos cambios. La cifra anterior de «49 passed» era errónea: el módulo tiene 37 tests.

---

## Siguiente paso concreto recomendado

1. **Probar en staging** el alta de un usuario creando el profesional desde el botón «+» del selector: debe quedar seleccionado y con los roles sugeridos de su cargo.
2. Decidir el caso «cuatro ojos» de la aprobación de roles (BACKLOG) y el destinatario de la alerta supervisada (UO del usuario frente a UO superior, BACKLOG).
3. Arreglar los 3 tests rotos de `Modules/Supervision`.
4. Pendientes anteriores, sin cambios: separar la BD local de la de staging, el flujo de creación de planes especializados en la UI (`PlanPage` fija `general_asp`), la asociación tipo de plan ↔ centro, el bug de `User::booted()` (`name` = email), la suite rota de `Modules/Agenda` y `AccesosExpedienteTest`.

---

## Contexto para retomar sin fricción

- **Roles desde el backoffice:** todo cambio de roles del formulario de usuarios pasa por `AsignacionRolesService::sincronizar()`, que calcula la diferencia con los roles efectivos y pendientes y asigna o retira por `usuario_rol`. El `UsuarioRolObserver` sincroniza Spatie. No volver a usar `->relationship('roles')` en ese formulario.
- **Historial de roles** (`UsuarioRolResource`) es de solo lectura; no volver a añadirle alta ni edición. Las solicitudes pendientes que ve un supervisor salen siempre de `UsuarioRol::resolublesPor()`.
- **Sugerencias por cargo:** solo `RolesSugeridosService` lee `cargo_roles_sugeridos`, y únicamente para el pre-relleno del alta y el aviso. Ningún otro código debe leerla (principio 3.3).
- **El aviso de cambio de cargo** compara `profesional.cargo_id` con `users.cargo_roles_revisado_id`.
- **Idempotencia de mundos aditivos:** cada entidad se crea a través de `DemoRegistrador::obtenerOCrear(clave, Modelo, fn)`. Las decisiones del azar que determinan *qué* se crea deben salir de `DemoContextoAditivo::decidir()`, no de `mt_rand`. No reordenar los `escenarios` de `demo_ciam.yaml`.
- `User::booted()` auto-asigna `consulta_basica` a los usuarios creados con `profesional_id` y sin roles. El alta de Filament lo retira si no está marcado.
