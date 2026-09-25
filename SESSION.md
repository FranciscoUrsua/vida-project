# SESSION — Estado actual del proyecto VIDA 360

**Última actualización:** 2026-09-25

---

## Tarea completada

**Seeders de cargos y roles sugeridos alineados con la BD compartida.** El desarrollador rellenó a mano los slugs y las sugerencias de los cargos y eliminó 4 cargos. `CargosSeeder` y `RolesSugeridosCargoSeeder` reproducen ahora ese estado: 9 cargos, todos con sugerencia de roles. Detalle en `CHANGELOG-092026.md`.

Antes, el mismo día: roles sugeridos por cargo con asignación supervisada desde el backoffice, y bloqueo de editar o borrar el propio usuario en Filament. El desarrollador lo ha comprobado: en su propia fila no aparecen los botones.

---

## Estado exacto del proyecto

- **⚠️ La BD local y la de staging son la misma** (`vida@127.0.0.1`, ver `BACKLOG.md`). Todo lo que se migre o cargue «en local» ocurre en staging. **No lanzar `demo:reset` desde local.**
- En esa BD compartida, a 2026-09-25:
  - Migraciones `create_cargo_roles_sugeridos_table` y `add_cargo_roles_revisado_id_to_users_table` aplicadas.
  - 9 cargos con slug (`ts`, `psicologo`, `educadorsocial`, `terapeutaocupacional`, `auxss`, `abogado`, `coordinador`, `administrativo`, `auxadmin`) y todos con roles sugeridos, configurados a mano por el desarrollador. Los seeders coinciden con este estado.
  - Siguen vigentes el mundo `demo_ciam` (980 registros TEST_CIAM) y `pia.admite_entrada_directa = true`.
- **Código de staging** (`/var/www/vida-project/vida`): **no desplegado** con este cambio. Las migraciones ya están aplicadas en la BD, pero el código de staging no conoce las columnas nuevas (no le afecta: solo se añaden). Desplegar `origin/master` para activar el formulario nuevo.
- **Tests:**
  - `Modules/Usuarios/tests/`: 58 passed y 1 incomplete (ya existía). Incluye `RolesSugeridosCargoTest` (11) y `CatalogoCargosSeederTest` (2).
  - `Modules/Supervision/tests`: 49 passed y **3 fallos ya existentes** (`sidebar_sin_plazas_no_muestra_item_plazas`, `ficha_profesional_muestra_tres_pestanas`, `auditoria_con_colectivos_muestra_columna_protegido`). Fallan igual en master sin estos cambios.

---

## Siguiente paso concreto recomendado

1. **Desplegar `origin/master` en staging** y probar el alta de un usuario de dirección: debe pre-rellenar supervision e intervencion y dejar supervision pendiente en Supervisión → Aprobaciones.
2. Configurar explícitamente `configuracion_roles` para todos los roles y decidir qué hacer con el formulario de `UsuarioRolResource`, que aún permite saltarse la supervisión (BACKLOG).
3. Pendientes anteriores, sin cambios: separar la BD local de la de staging, el flujo de creación de planes especializados en la UI (`PlanPage` fija `general_asp`), la asociación tipo de plan ↔ centro, el bug de `User::booted()` (`name` = email), la suite rota de `Modules/Agenda` y `AccesosExpedienteTest`.

---

## Contexto para retomar sin fricción

- **Roles desde el backoffice:** todo cambio de roles del formulario de usuarios pasa por `AsignacionRolesService::sincronizar()`, que calcula la diferencia con los roles efectivos y pendientes y asigna o retira por `usuario_rol`. El `UsuarioRolObserver` sincroniza Spatie. No volver a usar `->relationship('roles')` en ese formulario.
- **Sugerencias por cargo:** solo `RolesSugeridosService` lee `cargo_roles_sugeridos`, y únicamente para el pre-relleno del alta y el aviso. Ningún otro código debe leerla (principio 3.3).
- **El aviso de cambio de cargo** compara `profesional.cargo_id` con `users.cargo_roles_revisado_id`.
- **Idempotencia de mundos aditivos:** cada entidad se crea a través de `DemoRegistrador::obtenerOCrear(clave, Modelo, fn)`. Las decisiones del azar que determinan *qué* se crea deben salir de `DemoContextoAditivo::decidir()`, no de `mt_rand`. No reordenar los `escenarios` de `demo_ciam.yaml`.
- `User::booted()` auto-asigna `consulta_basica` a los usuarios creados con `profesional_id` y sin roles. El alta de Filament lo retira si no está marcado.
