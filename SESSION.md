# SESSION — Estado actual del proyecto VIDA 360

**Última actualización:** 2026-09-26

---

## Tarea completada

**Mensajes: paso 1 del plan (fallos previos corregidos y adjuntos retirados).** Las alertas `rol_uo` ya se ven en el buzón y en el contador, se reconocen con registro, y las de acceso protegido escalan. Detalle en `CHANGELOG-092026.md`.

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
  - `Modules/Mensajes/tests`: 61 passed, 1 failed (`t_lw_09`, ya existente; ver BACKLOG).
  - `Modules/Documentos`: 88 passed (unos 190 s: cada ingesta pasa por Ghostscript).
  - **Suite completa** (2026-09-25, unos 20 min): 908 passed y 76 failed, 75 de ellos fuera de Documentos (sobre todo Agenda) y ya existentes. Ver CHANGELOG y BACKLOG. No lanzar a la vez dos ejecuciones de tests: comparten `vida_testing`.
  - `Modules/Usuarios/tests/`: 63 passed y 1 incomplete (ya existía).
  - `Modules/Supervision/tests`: 35 passed (2026-09-26, con TF-SUP-E02b) y **3 fallos ya existentes** (`sidebar_sin_plazas_no_muestra_item_plazas`, `ficha_profesional_muestra_tres_pestanas`, `auditoria_con_colectivos_muestra_columna_protegido`). Fallan igual en master sin estos cambios. La cifra anterior de «49 passed» era errónea: el módulo tiene 37 tests.

---

## Siguiente paso concreto recomendado

1. **Mensajes, paso 2 del plan: unificar la bandeja.** Convertir `BuzonPage` en `BandejaAlertasYMensajes` usando los subcomponentes (`BandejaAlertas`, `BandejaMensajes`, `HiloMensajes`), que hoy no están colocados en ninguna pantalla. Quitar la lógica duplicada de `BuzonPage`, recibir la pestaña inicial como parámetro y poner tres entradas en el menú (Alertas en rojo, Avisos, Mensajes). Antes de empezar, decidir con el desarrollador cómo se muestran las **alertas escaladas** (hoy no las ve nadie; ver BACKLOG).
2. Mensajes, pasos siguientes: (3) `crearAvisoSupervisor()` + `NuevoAvisoSupervisor`, decidiendo antes el reconocimiento por destinatario de los avisos `rol_uo` (BACKLOG); (4) `PanelRedaccion` global en lugar de `NuevoMensaje` y del modal de `BuzonPage`, y después la fase 8 (botón «Escribir mensaje» en intervención, valoración y expediente); (5) `AlertaToast`; (6) llamar a `HistoriaSocialService::obtenerEntradas()` desde la línea de tiempo del ciudadano (existe, pero nadie lo usa).
3. Revisar en staging la tarjeta «Documentos» de la ficha del ciudadano (pendiente de la sesión anterior).
4. Restricción de colectivos protegidos en la ficha del ciudadano (BACKLOG, prioritario).
5. Fallos previos de la suite completa (BACKLOG), antes de cualquier merge a `main`.

---

## Contexto para retomar sin fricción

- **Mensajes, adaptación a las instrucciones nuevas:** `instrucciones-cli-mensajes.md` está escrito como si el módulo fuera nuevo, pero ya existía. Ya cumplen: migraciones, modelos, `HorarioLaboralService`, el job cada 15 min y los recursos Filament (en `app/Filament/Resources/`, no en el módulo, como manda `CLAUDE.md`). Las rutas de las instrucciones (`Modules/Mensajes/Models/`…) no son las del proyecto (`Modules/Mensajes/app/...`). El rol es `supervision`, no «supervisor». Entre fases se pasan solo los tests del módulo, no la suite completa.
- **«Alertas del usuario» = `Alerta::visiblesPara($usuario)`** (directas, o `rol_uo` con un rol suyo y una UO con adscripción vigente). No escribir consultas propias. Toda alerta se crea con `AlertaService::crear()`, nunca con `Alerta::create` (sin ello no hay `expira_en` y no escala).
- La pantalla operativa real es `BuzonPage` (`intervencion.mensajes.index`, una sola entrada «Alertas y mensajes» en el menú). En los tests de Livewire, un `findOrFail` fallido llega como 404 (`assertNotFound()`), no como excepción.

- **Roles desde el backoffice:** todo cambio de roles del formulario de usuarios pasa por `AsignacionRolesService::sincronizar()`, que calcula la diferencia con los roles efectivos y pendientes y asigna o retira por `usuario_rol`. El `UsuarioRolObserver` sincroniza Spatie. No volver a usar `->relationship('roles')` en ese formulario.
- **Custodia de documentos:** solo `AlmacenFlysystem` toca el disco `documentos`; todo alta pasa por `CicloVidaDocumentoService::altaDocumento()` → `IngestaDocumentoService`. Los tests usan `Storage::fake('documentos')`, la clave de `phpunit.xml`, `DOCUMENTOS_ANTIVIRUS=fake` y el trait `DocumentosTestSetup` (con `assertIngestaRechazada()` / `assertIngestaSinRastro()`). El hash guardado es el del PDF normalizado, no el del fichero subido. Los PDF firmados (canal `generado`) no se sanean. Toda destrucción de contenido pasa por `DestructorVersiones` (primero la clave, después el objeto; se audita como `borrar`). Los documentos solo salen por `DocumentoController`, que autoriza con `DocumentoPolicy` antes de descifrar. Tras Pint, restaurar los `@return` (su configuración los elimina).
- **Historial de roles** (`UsuarioRolResource`) es de solo lectura; no volver a añadirle alta ni edición. Las solicitudes pendientes que ve un supervisor salen siempre de `UsuarioRol::resolublesPor()`.
- **Sugerencias por cargo:** solo `RolesSugeridosService` lee `cargo_roles_sugeridos`, y únicamente para el pre-relleno del alta y el aviso. Ningún otro código debe leerla (principio 3.3).
- **El aviso de cambio de cargo** compara `profesional.cargo_id` con `users.cargo_roles_revisado_id`.
- **Idempotencia de mundos aditivos:** cada entidad se crea a través de `DemoRegistrador::obtenerOCrear(clave, Modelo, fn)`. Las decisiones del azar que determinan *qué* se crea deben salir de `DemoContextoAditivo::decidir()`, no de `mt_rand`. No reordenar los `escenarios` de `demo_ciam.yaml`.
- `User::booted()` auto-asigna `consulta_basica` a los usuarios creados con `profesional_id` y sin roles. El alta de Filament lo retira si no está marcado.
