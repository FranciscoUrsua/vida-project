# SESSION — Estado actual del proyecto VIDA 360

**Última actualización:** 2026-09-28

---

## Tarea completada

**Bootstrap como único sistema de estilos: plan y fase 1.** Plan en `docs/instrucciones-cli/2026-09-bootstrap-unico.md`. Hecha la fase 1: una sola fuente de tokens (Sass y Bootstrap), Tailwind fuera del bundle público, ficheros CSS huérfanos borrados y documentación alineada. Detalle en `CHANGELOG-092026.md`.

---

## Estado exacto del proyecto

- **⚠️ La BD local y la de staging son la misma** (`vida@127.0.0.1`, ver `BACKLOG.md`). Todo lo que se migre o cargue «en local» ocurre en staging. **No lanzar `demo:reset` desde local.**
- En esa BD compartida, a 2026-09-25:
  - Migraciones `create_cargo_roles_sugeridos_table` y `add_cargo_roles_revisado_id_to_users_table` aplicadas.
  - 9 cargos con slug (`ts`, `psicologo`, `educadorsocial`, `terapeutaocupacional`, `auxss`, `abogado`, `coordinador`, `administrativo`, `auxadmin`) y todos con roles sugeridos, configurados a mano por el desarrollador. Los seeders coinciden con este estado.
  - Custodia v2: migraciones de la fase 2a aplicadas y `TiposDocumentalesSeeder` ejecutado. `propuestas_eliminacion` ya creada (vacía; se aplicó por error desde local, ver CHANGELOG). La prueba de la fase 2b en staging como `www-data` salió bien (2026-09-25).
  - `configuracion_roles` completa: `adm_sistema` y `supervision` con aprobación previa, el resto con alerta supervisada.
  - Alertas y avisos de prueba #1–#17 en el CIAM Puente de Vallecas (UO 13), creados el 2026-09-27 para revisar Mensajes (ver CHANGELOG).
  - Siguen vigentes el mundo `demo_ciam` (980 registros TEST_CIAM) y `pia.admite_entrada_directa = true`.
- **Servidor de pruebas preparado para la custodia** (2026-09-25): `/srv/vida/documentos` (www-data, 0700), `DOCUMENTOS_RUTA` y `DOCUMENTOS_CLAVE_MAESTRA` en el `.env` de staging, y clamd activo (`/var/run/clamav/clamd.ctl`). El `.env` **local** no tiene variables `DOCUMENTOS_*`: en local la custodia falla hasta que se añadan.
- **Código de staging** (`/var/www/vida-project/vida`): se despliega solo con cada push a `master` (job `deploy` de `.github/workflows/ci.yml`, tras pasar `test`). No hace falta desplegar a mano.
- **Tests:**
  - `Modules/Mensajes/tests`: 131 passed (2026-09-27).
  - `Modules/Documentos`: 88 passed (unos 190 s: cada ingesta pasa por Ghostscript).
  - **Suite completa** (2026-09-25, unos 20 min): 908 passed y 76 failed, 75 de ellos fuera de Documentos (sobre todo Agenda) y ya existentes. Ver CHANGELOG y BACKLOG. No lanzar a la vez dos ejecuciones de tests: comparten `vida_testing`.
  - `Modules/Usuarios/tests/`: 63 passed y 1 incomplete (ya existía).
  - `Modules/Supervision/tests`: 35 passed (2026-09-26, con TF-SUP-E02b) y **3 fallos ya existentes** (`sidebar_sin_plazas_no_muestra_item_plazas`, `ficha_profesional_muestra_tres_pestanas`, `auditoria_con_colectivos_muestra_columna_protegido`). Fallan igual en master sin estos cambios. La cifra anterior de «49 passed» era errónea: el módulo tiene 37 tests.

---

## Siguiente paso concreto recomendado

1. **Bootstrap único, fase 2:** tests TF-UI-* y comando `php artisan ui:auditar` (reglas R1 a R6), `config/ui-catalogo.php` inicial con todas las clases en `pendiente`, paso en el CI con `--informe` y el informe inicial en el anexo B del plan. Leer antes el plan completo.
2. Después, fase 3 empezando por Ciudadanía (`alta-ciudadano`, cuyo botón «Dar de alta nueva persona» sigue invisible hasta entonces). Cada módulo migrado lo revisa Grok sobre el código antes de pasar al siguiente.
3. Pendientes anteriores: revisar los toasts de alertas en staging con ts1.ciam@demo.es; Mensajes, paso 6 (`HistoriaSocialService::obtenerEntradas()` en la línea de tiempo); tarjeta «Documentos» de la ficha; restricción de colectivos protegidos en la ficha (BACKLOG, prioritario); fallos previos de la suite completa.

---

## Contexto para retomar sin fricción

- **Frontend:** Bootstrap es el único sistema de estilos fuera de Filament y de los PDF. Los tokens solo están en `_bootstrap-overrides.scss` y `_vida-sass-tokens.scss`. Las variables `--color-*`, `--space-*`, `--radius-*`… **no existen** en los bundles operativo y público. El anexo A del plan tiene la tabla de equivalencias. Los tonos suaves se usan con `bg-*-subtle` y `text-*-emphasis`. El script de auditoría provisional de la sesión del 2026-09-28 no está en el repositorio: el definitivo es `ui:auditar` (fase 2).
- **Mensajes, adaptación a las instrucciones nuevas:** `instrucciones-cli-mensajes.md` está escrito como si el módulo fuera nuevo, pero ya existía. Ya cumplen: migraciones, modelos, `HorarioLaboralService`, el job cada 15 min y los recursos Filament (en `app/Filament/Resources/`, no en el módulo, como manda `CLAUDE.md`). Las rutas de las instrucciones (`Modules/Mensajes/Models/`…) no son las del proyecto (`Modules/Mensajes/app/...`). El rol es `supervision`, no «supervisor». Entre fases se pasan solo los tests del módulo, no la suite completa.
- **Alertas por destinatario:** cada alerta tiene sus filas en `alerta_destinatarios`, fijadas al crearla (directa: una; `rol_uo`: una por cada miembro del colectivo en ese momento). Lo que un usuario tiene por atender = `Alerta::pendientesPara($usuario)`; todo lo que recibió = `visiblesPara()`. No escribir consultas propias. Toda alerta se crea con `AlertaService::crear()`, nunca con `Alerta::create`: sin él no tiene destinatarios (nadie la ve) ni `expira_en` (no escala). En los tests, crear las alertas con el servicio. El `estado` de `alertas` es un resumen que recalcula el servicio.
- **Toasts de alertas:** `AlertaToast` vive en `operativo-shell`, junto al panel. Hace polling cada 60 s. Lo minimizado está en la sesión de Laravel, por usuario (no en el navegador: ver CHANGELOG). La vista no usa Alpine: el morph de Livewire rompía su estado. Todo reconocimiento emite `alerta-reconocida`, que escuchan la bandeja, los toasts y los dos sidebars: si se añade otro sitio donde reconocer, emitir el mismo evento.
- **Panel de redacción:** `PanelRedaccion` vive en `operativo-shell`. Se abre con `$dispatch('abrir-panel-redaccion', { contexto: { tipo, id } })`, o con el parcial `mensajes::partials.boton-escribir-mensaje`, que debe ir dentro de un componente Livewire. Contextos: `TipoContextoMensaje` (historia, ficha, plan). El contexto siempre se resuelve y autoriza con `ContextoMensajeService`, nunca con datos del navegador.
- **Supervisor:** `ControlAlertasPage` (`supervision.control-alertas`) incluye `NuevoAvisoSupervisor`. Avisos del supervisor = `AlertaService::crearAvisoSupervisor()` (`destinatario_type = uo`, todo el equipo salvo él). Escaladas = `AlertaDestinatario::escaladasA()`; se cierran solo con `AlertaService::cerrarEscalada()`, sin plazo (`escalar()` no las toca).
- **Bandeja:** `BandejaAlertasYMensajes` (pantalla, pestaña por ruta) → `BandejaAlertas` (`tipo` alerta|aviso) y `BandejaMensajes` → `HiloMensajes` / `NuevoMensaje`. Rutas: `intervencion.mensajes.index` y `supervision.bandeja`, las dos con `{pestana?}`. Las entradas de menú salen del parcial `mensajes::partials.nav-bandeja`, y los contadores, de `ContadoresBandejaService` (no calcularlos en otro sitio). En los tests de Livewire, un `findOrFail` fallido llega como 404 (`assertNotFound()`), no como excepción.

- **Roles desde el backoffice:** todo cambio de roles del formulario de usuarios pasa por `AsignacionRolesService::sincronizar()`, que calcula la diferencia con los roles efectivos y pendientes y asigna o retira por `usuario_rol`. El `UsuarioRolObserver` sincroniza Spatie. No volver a usar `->relationship('roles')` en ese formulario.
- **Custodia de documentos:** solo `AlmacenFlysystem` toca el disco `documentos`; todo alta pasa por `CicloVidaDocumentoService::altaDocumento()` → `IngestaDocumentoService`. Los tests usan `Storage::fake('documentos')`, la clave de `phpunit.xml`, `DOCUMENTOS_ANTIVIRUS=fake` y el trait `DocumentosTestSetup` (con `assertIngestaRechazada()` / `assertIngestaSinRastro()`). El hash guardado es el del PDF normalizado, no el del fichero subido. Los PDF firmados (canal `generado`) no se sanean. Toda destrucción de contenido pasa por `DestructorVersiones` (primero la clave, después el objeto; se audita como `borrar`). Los documentos solo salen por `DocumentoController`, que autoriza con `DocumentoPolicy` antes de descifrar. Tras Pint, restaurar los `@return` (su configuración los elimina).
- **Historial de roles** (`UsuarioRolResource`) es de solo lectura; no volver a añadirle alta ni edición. Las solicitudes pendientes que ve un supervisor salen siempre de `UsuarioRol::resolublesPor()`.
- **Sugerencias por cargo:** solo `RolesSugeridosService` lee `cargo_roles_sugeridos`, y únicamente para el pre-relleno del alta y el aviso. Ningún otro código debe leerla (principio 3.3).
- **El aviso de cambio de cargo** compara `profesional.cargo_id` con `users.cargo_roles_revisado_id`.
- **Idempotencia de mundos aditivos:** cada entidad se crea a través de `DemoRegistrador::obtenerOCrear(clave, Modelo, fn)`. Las decisiones del azar que determinan *qué* se crea deben salir de `DemoContextoAditivo::decidir()`, no de `mt_rand`. No reordenar los `escenarios` de `demo_ciam.yaml`.
- `User::booted()` auto-asigna `consulta_basica` a los usuarios creados con `profesional_id` y sin roles. El alta de Filament lo retira si no está marcado.
