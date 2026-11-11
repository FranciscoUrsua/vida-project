# SESSION — Estado actual del proyecto VIDA 360

**Última actualización:** 2026-11-11 (personas relacionadas protegidas, punto B1 de `2026_11_estabilizacion_y_privacidad.md`)

---

## Tarea completada

**Personas relacionadas protegidas (B1 de `docs/instrucciones-cli/2026_11_estabilizacion_y_privacidad.md`)** (2026-11-11): `App\Services\PresentacionPersona` en ficha, expediente, plan (pantalla y PDF), alta de documentos, buscadores y bandeja; las acciones sobre una relación o un conviviente protegido que no se puede ver no se ofrecen y el servidor las rechaza. TF-ACC-PR-01 a 08 (comprobados en negativo). Detalle en `CHANGELOG-112026.md`.

Antes, el mismo día: **Formato Markdown de los documentos del 10 nov (A5 de `docs/instrucciones-cli/2026_11_estabilizacion_y_privacidad.md`)** (2026-11-11): los ocho ficheros en Markdown sin cambiar una palabra (comprobado por fichero); en `modulo-citas.md`, precisión de RN-03 sobre el profesional de referencia y retirado el bloque duplicado de §9–§11 (decisión del desarrollador). Dudas 1 y 2 del 10 nov resueltas. Detalle en `CHANGELOG-112026.md`.

Y antes: **Supervisión: tests en la suite (A2 de `docs/instrucciones-cli/2026_11_estabilizacion_y_privacidad.md`)** (2026-11-11): `Modules/Supervision/tests/Feature` en `phpunit.xml`; los tres fallos eran tests desfasados o incompletos y se han corregido sin tocar la aplicación («Aprobaciones» fuera del menú desde junio, confirmado por el desarrollador; pestañas del equipo retiradas en junio; F03 sin acceso previo). `Modules/Supervision/tests` 38 passed. Detalle en `CHANGELOG-112026.md`.

Y antes: **Documentos: rechazos que faltaban y causa técnica (A1 de `docs/instrucciones-cli/2026_11_estabilizacion_y_privacidad.md`)** (2026-11-11): `fichero_vacio` (servicio y pantalla), `pdf_sin_contenido` con `SaneadorPdf::tintaTotal()` (inkcov, umbral 0,00001), `ProcesoExternoFallidoException` renombrada a `FalloHerramientaExterna` (nombre del binario, salida de error recortada a 2.000) y extendida a LibreOffice, `report()` de la causa en `DocumentosCiudadano`. TF-DOC-ING-01 a 08 en verde; `Modules/Documentos/tests` 100 passed. El 0 bytes «guardado» en staging no se reproduce (ni hay versión de 0 bytes en la BD). TF-DOC-ING-03 usa un PDF con contenido dañado: el truncado sin trailer lo para antes qpdf (`pdf_no_normalizable`). Docs: `modulo-documentos.md` §3 y `decisiones-tecnicas.md` §14. Detalle y mediciones en `CHANGELOG-112026.md`.

Y antes: **CI «Calidad de código» medido (A3 de `docs/instrucciones-cli/2026_11_estabilizacion_y_privacidad.md`)** (2026-11-11): `analyse-ci` con 2G; 281 errores de PHPStan clasificados por identificador en el BACKLOG (164 son el falso positivo de `Livewire::test()`); corregido el único fallo real (`ResolucionCentroService::seccionesSinCobertura()` devolvía `Support\Collection` y los llamadores usan `load()`). Pendiente de decisión: ignorar `argument.templateType` en tests y desactivar `no_superfluous_phpdoc_tags` en Pint. Detalle en `CHANGELOG-112026.md`.

Antes aún: **Documentos: fallos de qpdf y Ghostscript diagnosticables** (2026-11-11): `qpdf --requires-password` con código 2 y salida de error ya no pasa como «sin cifrar»; todo fallo de proceso de `SaneadorPdf` lleva una `ProcesoExternoFallidoException` y `DocumentosCiudadano::guardar()` lo registra con `report()`. Reglas AppArmor locales de qpdf y gs ampliadas a las rutas del repositorio local (tests y `storage/app/tmp`). `Modules/Documentos/tests`: 91 passed. Detalle en `CHANGELOG-112026.md`.

Y antes aún: **nombre visible del usuario y tests previos**: `User::nombreVisible()` (nombre del Profesional vinculado o email) en topbar, avatar, portada, onboarding y cuadrante de Agenda. TF-AUTH-16/17 y los dos de «Ver historia social» (ahora «Ir a HS») en verde. `booted()` sigue copiando el email en `name`. Detalle en `CHANGELOG-112026.md`.

Más atrás: Symfony fijado a `^7.4`, CI en PHP 8.5 y despliegue reparado (nvm, `php8.5-fpm`).

---

## Estado exacto del proyecto

- **Únicos cambios de código desde el 2026-10-02:** `User::nombreVisible()` y vistas asociadas, el diagnóstico de fallos de `SaneadorPdf` y los rechazos `fichero_vacio`/`pdf_sin_contenido` de la ingesta (todo 2026-11-11). Lo posterior (Tipo de Centro, roles `consulta_basica`/`tramitacion`, revisión de citas, requisitos y tareas guiadas) es solo análisis, ya incorporado a `docs/`. Lo que sigue describe el estado a 2026-10-02 y sigue vigente.
- **⚠️ La BD local y la de staging son la misma** (`vida@127.0.0.1`, ver `BACKLOG.md`). Todo lo que se migre o cargue «en local» ocurre en staging. **No lanzar `demo:reset` desde local.**
- **Migraciones de citas** (`2026_09_30_120001` a `120006`): se aplican en la BD compartida con el push del 2026-10-01. Crean `tipos_cita` (con un tipo genérico `cita` para las citas existentes), `solicitudes_cita`, `cita_eventos` (trigger que rechaza UPDATE y DELETE) y `cita_acompanantes`; amplían `citas`, `plan_apuntes`, `registros_atencion` y `horarios_centro`; **eliminan `tipos_slot.genera_apunte_automatico`**; crean los permisos `citas.*`.
- Para probar citas en staging: el centro necesita horario con tipos de slot enlazados a tipos de cita (Filament: `TipoCitaResource` y sección «Citas» de `HorarioCentroResource`), cuadrante publicado y slots; y el usuario, los permisos `citas.*` que correspondan. Los mundos demo no tienen agenda.
- **Migraciones de la asignación** (`2026_09_30_100001` a `100050`): ya aplicadas en la BD compartida (push del 2026-09-30). Cargan el catálogo de barrios y secciones, ponen **todos los centros existentes en `quien_abre`** (nada cambia hasta configurarlos) y marcan el cargo `ts` como elegible para referencia.
- Para probar el sorteo en staging hay que dar a algún centro, en Filament, tipo (`css_general`), ámbito (barrios o distrito) y modo `sorteo`; y los profesionales necesitan perfil horario activo en ese centro.
- En esa BD compartida, a 2026-09-25/27 (sin cambios): cargos con slug y roles sugeridos; custodia v2 (fase 2a aplicada, `propuestas_eliminacion` vacía); `configuracion_roles` completa; alertas y avisos de prueba #1–#17 en el CIAM Puente de Vallecas (UO 13); mundo `demo_ciam` (980 registros TEST_CIAM) y `pia.admite_entrada_directa = true`.
- **Servidor de pruebas preparado para la custodia** (2026-09-25): `/srv/vida/documentos`, `DOCUMENTOS_RUTA` y `DOCUMENTOS_CLAVE_MAESTRA` en el `.env` de staging, clamd activo. El `.env` **local** no tiene variables `DOCUMENTOS_*`.
- **Código de staging** (`/var/www/vida-project/vida`): se despliega solo con cada push a `master` (job `deploy` de `.github/workflows/ci.yml`). El servidor ejecuta **PHP 8.5** (`php8.5-fpm`); CI usa la misma versión.
- **Perfiles por defecto en el CIAM (UO 13):** se crean con `agenda:horarios-por-defecto --centro=13` tras el despliegue (ver CHANGELOG). Hecho: 9 perfiles pendientes de verificar + 1 previo (comprobado el 2026-10-02). Los demás centros no se completan por ahora: al desarrollador solo le preocupa el CIAM.
- **AppArmor:** qpdf y gs están confinados; las reglas locales (`/etc/apparmor.d/local/{qpdf,gs}`) cubren `/var/www/.../storage/app/tmp` y, en el repositorio local, `storage/framework/testing` y `storage/app/tmp`. Revisar tras cada `apt upgrade` (BACKLOG).
- **Tests:**
  - 2026-11-11, B1: Acceso, Ciudadanía, Supervisión, Agenda, Intervención y `DocumentosCiudadanoTest`: 657 passed, 1 incomplete (previo) y 1 fallo propio (`RelacionesUiTest`, corregido). Tras los retoques finales, Acceso + Livewire de Intervención: 198 passed, 1 incomplete. `ui:auditar` sin infracciones.
  - 2026-11-11, Supervisión (A2): `Modules/Supervision/tests` 38 passed; ya forman parte de la suite `Feature`.
  - 2026-11-11, Documentos (A1): `Modules/Documentos/tests` 100 passed (477 assertions); TF-DOC-ING-03, 04 y 07 comprobados en negativo.
  - 2026-11-11, CI calidad: `AmbitoCoberturaTest` 5 passed. PHPStan: 281 errores (recuento en el BACKLOG).
  - 2026-11-11, Documentos (qpdf/gs): `Modules/Documentos/tests` 91 passed.
  - **PHPStan en local:** el modo paralelo no termina en esta máquina (1 CPU, 1 GB). Lanzar por módulo: `vendor/bin/phpstan analyse --memory-limit=750M --debug --no-progress --error-format=raw Modules/X`.
  - 2026-11-11, nombre visible: `AutenticacionTest`, `FichaAtencionTest`, `FichaCiudadanoPageTest` 51 passed, 1 incomplete, 1 skipped (previos); `CuadranteMesTest` 5 passed. Ya no quedan TF-AUTH-16/17 ni «Ver historia social» como fallos previos. `ui:auditar` sin infracciones.
  - 2026-11-11, Symfony fijado: UI, Auth, Acceso, Ciudadanía y Citas: 244 passed, 1 incomplete, 1 skipped y los 4 fallos previos (TF-AUTH-16/17 y los dos de «Ver historia social»).
  - 2026-10-02, acceso: `tests/Feature/Acceso` 12 passed, TF-ACC-11 y TF-DOC-78 en verde, TF-LW-BUS-06 en verde; Auditoría 29 passed; Intervención 296 passed (1 incomplete); Usuarios 63 passed; Citas 66 passed; AccesoDocumento 6 passed; Ciudadanía 103 passed y 2 fallos previos («Ver historia social», fallan igual sin el cambio); Supervisión 35 passed y los 3 fallos previos del BACKLOG. `ui:auditar` sin infracciones.
  - 2026-10-01, perfil por defecto: Agenda, Supervisión, Usuarios, Centro y `Intervencion/tests/Feature/Asignacion`: 387 passed, 1 incomplete, 4 failed. Tres son los de `SupervisionTest` ya anotados; el cuarto (`SolicitudCitaTest`, que contaba todas las alertas) está corregido y pasa. `ui:auditar` sin infracciones.
  - 2026-10-01, fix de actividad: `Modules/Intervencion/tests/Feature/Asignacion` y `Modules/Supervision/tests`: 70 passed, 3 failed (los tres de `SupervisionTest` ya anotados en BACKLOG, fallan igual sin el cambio). `ui:auditar` sin infracciones.
  - 2026-10-01, Agenda completo (`Modules/Agenda/tests`, incluye las citas): **173 passed**. `ui:auditar` sin infracciones tras `npm run build`.
  - 2026-09-30, asignación: 50 tests en `Modules/Centro/tests/Feature/Asignacion` y `Modules/Intervencion/tests/Feature/Asignacion`, en verde. Geocodificación (mock, observer, códigos): 19 passed.
  - **Suite completa** 2026-09-30 (unos 24 min): 1066 passed, 80 failed; 12 eran del mock y están corregidos; los 68 restantes son previos (Agenda 63, TF-AUTH-16/17, `AutorizacionDatosTest`, Ciudadanía 2 «Ver historia social»). No lanzar a la vez dos ejecuciones de tests: comparten `vida_testing`.
  - `ui:auditar` sin infracciones tras `npm run build`.

---

## Siguiente paso concreto recomendado

0. **Seguir `docs/instrucciones-cli/2026_11_estabilizacion_y_privacidad.md`:** hechos A3, A1, A2 y A5 (bloque A completo; A4 aplazado por el desarrollador hasta la 1.0: la BD compartida local/staging se mantiene a propósito). Hecho B1. Siguiente: **B2** (`VerFichaPage` y la historia de la ruta), después B3 y B4; A4 espera la confirmación del desarrollador. Tras el despliegue de A1, probar en staging la subida de `~/casos_limite/E04_pdf_corrupto_truncado.pdf` (debe rechazarse) y de un PDF y una imagen normales (prueba rápida de `decisiones-tecnicas.md` §14).
1. **El desarrollador prueba en staging asignación y citas** (pendiente desde el 2026-10-01; las pantallas solo se han probado con tests).
   - Asignación: centro con tipo, ámbito y modo `sorteo`; alta con dirección y apertura de historia; bloque «Centro y referencia»; bandeja «Asignaciones», actividad del equipo y reparto. **Configurar igual todos los centros del mismo tipo:** el paso 3 de Tipo de Centro aborta si difieren.
   - Citas: bandeja de citación, cita directa, detalle con reprogramar y cancelar, agenda del profesional, «Solicitar cita» en Intervención, *Atender* → registro de atención → «Dar cita».
2. **Decidir el modelo de varias UO con varios centros** (BACKLOG «Perfil horario por defecto: pendientes»): condiciona Tipo de Centro y los requisitos de acción.
3. **Ejecutar `docs/instrucciones-cli/2026-10-tipo-centro-implementacion.md` (rama A)** con sus tests TF-TCE-01 a 34.
4. **Corregir la creación de planes especializados (PIA):** `PlanPage::crearNuevoPlan()` fija `tipo = general_asp` (BACKLOG; prioritario para el CIAM).
5. **Instrucciones del delta de citas y de `consulta_basica`** (BACKLOG «Citas: delta de la revisión del 30 sep» y «`consulta_basica`: diseño cerrado sin instrucciones»): §6 bis, ficha por rol, estado de la Historia Social, documentos propios del día.
6. Revisar las dudas 3 a 5 de `CHANGELOG-112026.md` (la 1 y la 2 se resolvieron en A5).

---

## Contexto para retomar sin fricción

- Documentación al día a 2026-11-10: ver `docs/estado-proyecto-2026-11-10.md` y su sección Seguimiento.
- **Acceso a expedientes (2026-10-02):** toda pantalla que abre a una persona usa `AccesoExpediente` (`ciudadano()`/`historia()` en `mount()`; en computed, `registrar: false`). No cargar `Ciudadano` ni `HistoriaSocial` con `withoutGlobalScope` en una página. Un protegido que la policy no deja ver no muestra nombre, documento ni alias en ningún listado.
- **Citas (2026-09-30):**
  - Solo escriben en `citas`, `solicitudes_cita` y `cita_eventos` los servicios de `Modules/Agenda/app/Services/Citas/`, `GestionAusenciaService` y `CitaCierreJob`. Todo cambio de estado deja un evento en `cita_eventos` (solo inserción).
  - Tests en `Modules/Agenda/tests/Feature/Citas/` con el trait `CitasTestSetup`.
  - Pantallas: `agenda.citas.bandeja`, `agenda.citas.nueva` (`?atencion=`), `agenda.citas.show`; layout según rol (`ConLayoutDeCitas`).

- **Asignación (2026-09-30):**
  - Escriben en `asignaciones_centro` solo `AsignacionCentroService`; en `asignaciones_profesional`, `AsignacionReferenciaService`, `RepartoCasosService` y `AperturaHistoriaService`. Historial aditivo: se cierra con `fecha_fin` y se crea otra; nunca se cambia `centro_id` ni `profesional_id`.
  - La bandeja se lee con `BandejaAsignacionesService` (alcance: `AsignacionPendiente::visiblesPara($centro)`); la pantalla recarga cada entrada con ese alcance antes de actuar.
  - Centro de un usuario = centro de su UO activa (`CentroDeUsuario`). Supervisa = rol `supervision` + adscrito a esa UO.
  - Aleatoriedad: `Random\Randomizer` inyectado; en tests, `fijarSemilla()` del trait `AsignacionTestSetup` (mini-catálogo territorial propio).
  - `MockGeocodificador` elige la sección del catálogo de forma determinista por portal; recibe por constructor cómo elegirla (el test unitario del parser pasa `fn () => null`).
- **Frontend:** solo Bootstrap fuera de Filament y de los PDF.
  - Tokens: solo en `_bootstrap-overrides.scss` y `_vida-sass-tokens.scss`. No existen variables `--color-*`.
  - Toda clase propia está en `config/ui-catalogo.php`; una nueva va al catálogo en el mismo commit.
  - Colores de estado y tipo: `$enum->tono()->clasesSuave()` (o `Tonos::…()` del módulo). Nada de tablas de clases en las vistas.
  - Estados vacíos: `<x-op.empty icono="…">texto</x-op.empty>`.
  - Toda tarea con Blade o SCSS termina con `npm run build` + `php artisan ui:auditar` en verde.
  - Tras Pint, restaurar los `@return`.
- **Alertas:** toda alerta se crea con `AlertaService::crear()`, nunca con `Alerta::create`. Pendientes de un usuario = `Alerta::pendientesPara($usuario)`. Contadores de la bandeja de mensajes: `ContadoresBandejaService`.
- **Mensajes:** `instrucciones-cli-mensajes.md` está escrito como si el módulo fuera nuevo, pero ya existía; queda el paso 6. El rol es `supervision`, no «supervisor».
- **Panel de redacción:** `$dispatch('abrir-panel-redaccion', { contexto: { tipo, id } })`; el contexto se autoriza con `ContextoMensajeService`.
- En los tests de Livewire, un `findOrFail` fallido llega como 404 (`assertNotFound()`).
