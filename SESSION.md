# SESSION — Estado actual del proyecto VIDA 360

**Última actualización:** 2026-11-10 (puesta al día documental)

---

## Tarea completada

**Puesta al día documental** (2026-11-10, `docs/instrucciones-cli/2026-11-puesta-al-dia-documental.md`): documentos nuevos incorporados (citas revisado, roles, Tipo de Centro, propuesta de tareas guiadas, informe de estado); estados de módulo corregidos; decisiones de roles propagadas (incluido el design system: banner de protegido solo para `intervencion`); `decisiones-pendientes.md` y BACKLOG limpios. Solo documentación. Antes del commit, parches de seguridad de Livewire 4.4.8 y Symfony 7.4.x (`composer.lock`), que bloqueaban el hook de pre-commit. Detalle y lista de dudas en `CHANGELOG-112026.md`.

---

## Estado exacto del proyecto

- **Sin cambios de código desde el 2026-10-02.** Lo posterior (Tipo de Centro, roles `consulta_basica`/`tramitacion`, revisión de citas, requisitos y tareas guiadas) es solo análisis, ya incorporado a `docs/`. Lo que sigue describe el estado a 2026-10-02 y sigue vigente.
- **⚠️ La BD local y la de staging son la misma** (`vida@127.0.0.1`, ver `BACKLOG.md`). Todo lo que se migre o cargue «en local» ocurre en staging. **No lanzar `demo:reset` desde local.**
- **Migraciones de citas** (`2026_09_30_120001` a `120006`): se aplican en la BD compartida con el push del 2026-10-01. Crean `tipos_cita` (con un tipo genérico `cita` para las citas existentes), `solicitudes_cita`, `cita_eventos` (trigger que rechaza UPDATE y DELETE) y `cita_acompanantes`; amplían `citas`, `plan_apuntes`, `registros_atencion` y `horarios_centro`; **eliminan `tipos_slot.genera_apunte_automatico`**; crean los permisos `citas.*`.
- Para probar citas en staging: el centro necesita horario con tipos de slot enlazados a tipos de cita (Filament: `TipoCitaResource` y sección «Citas» de `HorarioCentroResource`), cuadrante publicado y slots; y el usuario, los permisos `citas.*` que correspondan. Los mundos demo no tienen agenda.
- **Migraciones de la asignación** (`2026_09_30_100001` a `100050`): ya aplicadas en la BD compartida (push del 2026-09-30). Cargan el catálogo de barrios y secciones, ponen **todos los centros existentes en `quien_abre`** (nada cambia hasta configurarlos) y marcan el cargo `ts` como elegible para referencia.
- Para probar el sorteo en staging hay que dar a algún centro, en Filament, tipo (`css_general`), ámbito (barrios o distrito) y modo `sorteo`; y los profesionales necesitan perfil horario activo en ese centro.
- En esa BD compartida, a 2026-09-25/27 (sin cambios): cargos con slug y roles sugeridos; custodia v2 (fase 2a aplicada, `propuestas_eliminacion` vacía); `configuracion_roles` completa; alertas y avisos de prueba #1–#17 en el CIAM Puente de Vallecas (UO 13); mundo `demo_ciam` (980 registros TEST_CIAM) y `pia.admite_entrada_directa = true`.
- **Servidor de pruebas preparado para la custodia** (2026-09-25): `/srv/vida/documentos`, `DOCUMENTOS_RUTA` y `DOCUMENTOS_CLAVE_MAESTRA` en el `.env` de staging, clamd activo. El `.env` **local** no tiene variables `DOCUMENTOS_*`.
- **Código de staging** (`/var/www/vida-project/vida`): se despliega solo con cada push a `master` (job `deploy` de `.github/workflows/ci.yml`).
- **Perfiles por defecto en el CIAM (UO 13):** se crean con `agenda:horarios-por-defecto --centro=13` tras el despliegue (ver CHANGELOG). Hecho: 9 perfiles pendientes de verificar + 1 previo (comprobado el 2026-10-02). Los demás centros no se completan por ahora: al desarrollador solo le preocupa el CIAM.
- **Tests:**
  - 2026-10-02, acceso: `tests/Feature/Acceso` 12 passed, TF-ACC-11 y TF-DOC-78 en verde, TF-LW-BUS-06 en verde; Auditoría 29 passed; Intervención 296 passed (1 incomplete); Usuarios 63 passed; Citas 66 passed; AccesoDocumento 6 passed; Ciudadanía 103 passed y 2 fallos previos («Ver historia social», fallan igual sin el cambio); Supervisión 35 passed y los 3 fallos previos del BACKLOG. `ui:auditar` sin infracciones.
  - 2026-10-01, perfil por defecto: Agenda, Supervisión, Usuarios, Centro y `Intervencion/tests/Feature/Asignacion`: 387 passed, 1 incomplete, 4 failed. Tres son los de `SupervisionTest` ya anotados; el cuarto (`SolicitudCitaTest`, que contaba todas las alertas) está corregido y pasa. `ui:auditar` sin infracciones.
  - 2026-10-01, fix de actividad: `Modules/Intervencion/tests/Feature/Asignacion` y `Modules/Supervision/tests`: 70 passed, 3 failed (los tres de `SupervisionTest` ya anotados en BACKLOG, fallan igual sin el cambio). `ui:auditar` sin infracciones.
  - 2026-10-01, Agenda completo (`Modules/Agenda/tests`, incluye las citas): **173 passed**. `ui:auditar` sin infracciones tras `npm run build`.
  - 2026-09-30, asignación: 50 tests en `Modules/Centro/tests/Feature/Asignacion` y `Modules/Intervencion/tests/Feature/Asignacion`, en verde. Geocodificación (mock, observer, códigos): 19 passed.
  - **Suite completa** 2026-09-30 (unos 24 min): 1066 passed, 80 failed; 12 eran del mock y están corregidos; los 68 restantes son previos (Agenda 63, TF-AUTH-16/17, `AutorizacionDatosTest`, Ciudadanía 2 «Ver historia social»). No lanzar a la vez dos ejecuciones de tests: comparten `vida_testing`.
  - `ui:auditar` sin infracciones tras `npm run build`.

---

## Siguiente paso concreto recomendado

1. **El desarrollador prueba en staging asignación y citas** (pendiente desde el 2026-10-01; las pantallas solo se han probado con tests).
   - Asignación: centro con tipo, ámbito y modo `sorteo`; alta con dirección y apertura de historia; bloque «Centro y referencia»; bandeja «Asignaciones», actividad del equipo y reparto. **Configurar igual todos los centros del mismo tipo:** el paso 3 de Tipo de Centro aborta si difieren.
   - Citas: bandeja de citación, cita directa, detalle con reprogramar y cancelar, agenda del profesional, «Solicitar cita» en Intervención, *Atender* → registro de atención → «Dar cita».
2. **Decidir el modelo de varias UO con varios centros** (BACKLOG «Perfil horario por defecto: pendientes»): condiciona Tipo de Centro y los requisitos de acción.
3. **Ejecutar `docs/instrucciones-cli/2026-10-tipo-centro-implementacion.md` (rama A)** con sus tests TF-TCE-01 a 34.
4. **Corregir la creación de planes especializados (PIA):** `PlanPage::crearNuevoPlan()` fija `tipo = general_asp` (BACKLOG; prioritario para el CIAM).
5. **Instrucciones del delta de citas y de `consulta_basica`** (BACKLOG «Citas: delta de la revisión del 30 sep» y «`consulta_basica`: diseño cerrado sin instrucciones»): §6 bis, ficha por rol, estado de la Historia Social, documentos propios del día.
6. Revisar las dudas de `CHANGELOG-112026.md` (sobre todo: los documentos nuevos llegaron sin formato Markdown).

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
