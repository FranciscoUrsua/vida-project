# SESSION — Estado actual del proyecto VIDA 360

**Última actualización:** 2026-09-30 (tarde)

> **En curso: Citas** (`docs/instrucciones-cli/2026-09-citas-implementacion.md`). Paso 0 hecho: tests de Agenda en verde (commit `c0b4584`, con 4 fallos de la aplicación corregidos y el pivote `horario_centro_tipo_slot`). Decidido: eliminar la columna `tipos_slot.genera_apunte_automatico` (nadie la lee). **Siguiente: paso 1 (tipos de cita).**

---

## Tarea completada

**Asignación de centro y profesional de referencia** (`docs/instrucciones-cli/2026-09-asignacion-implementacion.md`, pasos 1 a 9; TF-ASG-01 a 34). Unidades territoriales (barrios, secciones censales), códigos territoriales en la dirección, asignación de centro por domicilio o elección, referencia por sorteo con corrección de desvío, libre elección o quien abre, reparto por salida, actividad de los casos, bandeja del supervisor («Asignaciones» en Supervisión) y bloque «Centro y referencia» en la ficha. Detalle y decisiones en `CHANGELOG-092026.md` (2026-09-30).

---

## Estado exacto del proyecto

- **⚠️ La BD local y la de staging son la misma** (`vida@127.0.0.1`, ver `BACKLOG.md`). Todo lo que se migre o cargue «en local» ocurre en staging. **No lanzar `demo:reset` desde local.**
- **Migraciones de esta fase** (`2026_09_30_100001` a `100050`): se aplican en la BD compartida con el despliegue de este push. Cargan el catálogo de barrios y secciones, ponen **todos los centros existentes en `quien_abre`** (nada cambia hasta configurarlos) y marcan el cargo `ts` como elegible para referencia.
- Para probar el sorteo en staging hay que dar a algún centro, en Filament, tipo (`css_general`), ámbito (barrios o distrito) y modo `sorteo`; y los profesionales necesitan perfil horario activo en ese centro.
- En esa BD compartida, a 2026-09-25/27 (sin cambios): cargos con slug y roles sugeridos; custodia v2 (fase 2a aplicada, `propuestas_eliminacion` vacía); `configuracion_roles` completa; alertas y avisos de prueba #1–#17 en el CIAM Puente de Vallecas (UO 13); mundo `demo_ciam` (980 registros TEST_CIAM) y `pia.admite_entrada_directa = true`.
- **Servidor de pruebas preparado para la custodia** (2026-09-25): `/srv/vida/documentos`, `DOCUMENTOS_RUTA` y `DOCUMENTOS_CLAVE_MAESTRA` en el `.env` de staging, clamd activo. El `.env` **local** no tiene variables `DOCUMENTOS_*`.
- **Código de staging** (`/var/www/vida-project/vida`): se despliega solo con cada push a `master` (job `deploy` de `.github/workflows/ci.yml`).
- **Tests:**
  - 2026-09-30, asignación: 50 tests en `Modules/Centro/tests/Feature/Asignacion` y `Modules/Intervencion/tests/Feature/Asignacion`, en verde. Geocodificación (mock, observer, códigos): 19 passed.
  - **Suite completa** 2026-09-30 (unos 24 min): 1066 passed, 80 failed; 12 eran del mock y están corregidos; los 68 restantes son previos (Agenda 63, TF-AUTH-16/17, `AutorizacionDatosTest`, Ciudadanía 2 «Ver historia social»). No lanzar a la vez dos ejecuciones de tests: comparten `vida_testing`.
  - `ui:auditar` sin infracciones tras `npm run build`.

---

## Siguiente paso concreto recomendado

1. **Comprobar en staging la asignación**, tras el despliegue: configurar un centro con tipo, ámbito y modo `sorteo`; dar de alta una persona con dirección y abrir su historia (texto del alta, aviso del profesional asignado, bloque «Centro y referencia» de la ficha); bandeja «Asignaciones» del supervisor, actividad del equipo y un reparto. Las pantallas nuevas solo se han probado con tests, no en el navegador.
2. **Ficha: restricción de colectivos protegidos** (BACKLOG, prioritario: `CLAUDE.md` §3). Revisar a la vez la bandeja de asignaciones, que muestra nombres.
3. **Citas** (`docs/instrucciones-cli/2026-09-citas-implementacion.md`): ya puede empezar, usa la referencia vigente de esta fase.
4. Pendientes de la asignación en BACKLOG: cambio de referencia de un caso ya asignado desde la interfaz, autorización por centro dentro de los servicios, adaptador BDC.
5. Rendimiento de `CiudadanoPage`/`plan-page` y polling de toasts (BACKLOG); fallos previos de Agenda.

---

## Contexto para retomar sin fricción

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
