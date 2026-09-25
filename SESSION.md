# SESSION — Estado actual del proyecto VIDA 360

**Última actualización:** 2026-09-25

---

## Tarea completada

**Documentos: custodia v2 terminada (paso 7, UI operativa, y cierre del paso 9).** Tarjeta «Documentos» en la ficha del ciudadano, corrección de la conversión de imágenes con Imagick y primera suite completa. Detalle en `CHANGELOG-092026.md`.

---

## Estado exacto del proyecto

- **⚠️ La BD local y la de staging son la misma** (`vida@127.0.0.1`, ver `BACKLOG.md`). Todo lo que se migre o cargue «en local» ocurre en staging. **No lanzar `demo:reset` desde local.**
- En esa BD compartida, a 2026-09-25:
  - Migraciones `create_cargo_roles_sugeridos_table` y `add_cargo_roles_revisado_id_to_users_table` aplicadas.
  - 9 cargos con slug (`ts`, `psicologo`, `educadorsocial`, `terapeutaocupacional`, `auxss`, `abogado`, `coordinador`, `administrativo`, `auxadmin`) y todos con roles sugeridos, configurados a mano por el desarrollador. Los seeders coinciden con este estado.
  - Custodia v2: migraciones de la fase 2a aplicadas y `TiposDocumentalesSeeder` ejecutado. `propuestas_eliminacion` ya creada (vacía; se aplicó por error desde local, ver CHANGELOG). La prueba de la fase 2b en staging como `www-data` salió bien (2026-09-25).
  - `configuracion_roles` completa: `adm_sistema` y `supervision` con aprobación previa, el resto con alerta supervisada.
  - Siguen vigentes el mundo `demo_ciam` (980 registros TEST_CIAM) y `pia.admite_entrada_directa = true`.
- **Servidor de pruebas preparado para la custodia** (2026-09-25): `/srv/vida/documentos` (www-data, 0700), `DOCUMENTOS_RUTA` y `DOCUMENTOS_CLAVE_MAESTRA` en el `.env` de staging, y clamd activo (`/var/run/clamav/clamd.ctl`). El `.env` **local** no tiene variables `DOCUMENTOS_*`: en local la custodia falla hasta que se añadan.
- **Código de staging** (`/var/www/vida-project/vida`): se despliega solo con cada push a `master` (job `deploy` de `.github/workflows/ci.yml`, tras pasar `test`). No hace falta desplegar a mano.
- **Tests:**
  - `Modules/Documentos`: 88 passed (unos 190 s: cada ingesta pasa por Ghostscript).
  - **Suite completa** (2026-09-25, unos 20 min): 908 passed y 76 failed, 75 de ellos fuera de Documentos (sobre todo Agenda) y ya existentes. Ver CHANGELOG y BACKLOG. No lanzar a la vez dos ejecuciones de tests: comparten `vida_testing`.
  - `Modules/Usuarios/tests/`: 63 passed y 1 incomplete (ya existía).
  - `Modules/Supervision/tests`: 34 passed y **3 fallos ya existentes** (`sidebar_sin_plazas_no_muestra_item_plazas`, `ficha_profesional_muestra_tres_pestanas`, `auditoria_con_colectivos_muestra_columna_protegido`). Fallan igual en master sin estos cambios. La cifra anterior de «49 passed» era errónea: el módulo tiene 37 tests.

---

## Siguiente paso concreto recomendado

1. **Revisar en staging la tarjeta «Documentos»** de la ficha del ciudadano con un usuario de intervención: subir un PDF, una foto y un DOCX, una versión nueva y desvincular. Es la primera vez que se ve en navegador; los tests no cubren el aspecto visual.
2. **Restricción de colectivos protegidos en la ficha del ciudadano** (BACKLOG, prioritario; el desarrollador lo ha aplazado a propósito: «tiene su miga»).
3. Fallos previos de la suite completa (BACKLOG), antes de cualquier merge a `main`.
4. Pendientes anteriores: cuatro ojos en la aprobación de roles, variables auxiliares de informes (TF-DOC-22 a 25), UI de informes y PISO, `Modules/Agenda`.

---

## Contexto para retomar sin fricción

- **Roles desde el backoffice:** todo cambio de roles del formulario de usuarios pasa por `AsignacionRolesService::sincronizar()`, que calcula la diferencia con los roles efectivos y pendientes y asigna o retira por `usuario_rol`. El `UsuarioRolObserver` sincroniza Spatie. No volver a usar `->relationship('roles')` en ese formulario.
- **Custodia de documentos:** solo `AlmacenFlysystem` toca el disco `documentos`; todo alta pasa por `CicloVidaDocumentoService::altaDocumento()` → `IngestaDocumentoService`. Los tests usan `Storage::fake('documentos')`, la clave de `phpunit.xml`, `DOCUMENTOS_ANTIVIRUS=fake` y el trait `DocumentosTestSetup` (con `assertIngestaRechazada()` / `assertIngestaSinRastro()`). El hash guardado es el del PDF normalizado, no el del fichero subido. Los PDF firmados (canal `generado`) no se sanean. Toda destrucción de contenido pasa por `DestructorVersiones` (primero la clave, después el objeto; se audita como `borrar`). Los documentos solo salen por `DocumentoController`, que autoriza con `DocumentoPolicy` antes de descifrar. Tras Pint, restaurar los `@return` (su configuración los elimina).
- **Historial de roles** (`UsuarioRolResource`) es de solo lectura; no volver a añadirle alta ni edición. Las solicitudes pendientes que ve un supervisor salen siempre de `UsuarioRol::resolublesPor()`.
- **Sugerencias por cargo:** solo `RolesSugeridosService` lee `cargo_roles_sugeridos`, y únicamente para el pre-relleno del alta y el aviso. Ningún otro código debe leerla (principio 3.3).
- **El aviso de cambio de cargo** compara `profesional.cargo_id` con `users.cargo_roles_revisado_id`.
- **Idempotencia de mundos aditivos:** cada entidad se crea a través de `DemoRegistrador::obtenerOCrear(clave, Modelo, fn)`. Las decisiones del azar que determinan *qué* se crea deben salir de `DemoContextoAditivo::decidir()`, no de `mt_rand`. No reordenar los `escenarios` de `demo_ciam.yaml`.
- `User::booted()` auto-asigna `consulta_basica` a los usuarios creados con `profesional_id` y sin roles. El alta de Filament lo retira si no está marcado.
