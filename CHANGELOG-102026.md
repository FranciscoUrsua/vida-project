# CHANGELOG — VIDA 360 — Octubre 2026

> Entradas de octubre 2026. Para meses anteriores, ver `CHANGELOG-092026.md`.

---

## 2026-10-02 — Acceso: policy y auditoría de lectura de expediente

Encargo `docs/instrucciones-cli/instrucciones-cli-acceso-auditoria.md` (y punto 1 del informe de calidad). La ficha del ciudadano solo comprobaba el rol, y las policies de historia y plan miraban `historias_sociales.ciudadano_protegido`, un indicador que nada pone a `true`: un protegido de otra UO se abría por Nivel 2. Casi ninguna lectura quedaba en `audits`.

### Cambios
- `App\Services\AccesoExpediente` (nuevo): `ciudadano()`, `historia()`, `historiaDe()` y `denegarCiudadano()`. Carga sin `AmbitoUoScope`, autoriza con la policy y anota la lectura antes de relanzar el 403. Protegido → `acceso_restringido`; resto → `ver`; contexto con `autorizado`, `motivo = denegado` y `acceso_protegido_id`. Una fila por apertura (atributo de la petición + `registrar: false` en los computed).
- `CiudadanoPolicy::consultaExternaPermitida()` (público): criterio único de colectivo protegido; `HistoriaSocialPolicy` y `PlanDeIntervencionPolicy` delegan en él. Manda `ciudadanos.colectivo_extra_protegido` (se suma el de la historia).
- `AccesoProtegido::scopeVigentePara()`: criterio único de aprobación vigente.
- Cableado: `FichaCiudadanoPage` (mount y computed `ciudadano`, `historiaSocial`, `ucVigente`; `cancelarEdicion` y `guardar`), `CiudadanoPage` (mount y `ciudadano`), `PlanPage` (mount y `ciudadano`), `RegistrarValoracionPage`, `VerFichaPage` y `BuscarCiudadanoPage::registrarAccesoNivel2` (sin `Log::info` ni TODO; no redirige si no autoriza).
- Rutas: la ficha sale del middleware de rol y de `audit.ciudadano` (el rol se comprueba en `mount()` para auditar el intento); el expediente pierde `can:view,historia`; `Route::bind('plan')` sin scope (403 y no 404).
- Buscadores y bandeja (regla 6): `BuscarCiudadanoPage` devuelve nivel 3 sin nombre ni alias; el buscador de citación muestra «Persona con protección especial» sin nombre, documento ni botón (`BuscadorPersonasCita::visible()`, se retira `telefonoVisible()`); la bandeja de asignaciones usa el parcial `persona-pendiente` con la policy. Los buscadores de relación (ficha) y de UC (expediente) descartan protegidos que no se pueden ver.
- Tests: `tests/Feature/Acceso/AccesoExpedienteTest.php` (TF-ACC-01 a 10 y 12, más el caso Nivel 2 de TF-ACC-05), TF-ACC-11 en `AccesoDocumentoTest`, TF-LW-BUS-06 afirma la fila en `audits`. Comprobado en negativo: sin la restricción de protegido fallan 02, 04, 07, 09 y 10. `PanelAccesosRecentesTest` y `Intervencion/.../AccesosExpedienteTest`: los recuentos incluyen la propia apertura; `RegistrarValoracionPageTest`: el usuario del fixture pasa a ser de intervención y adscrito a la UO (la página antes no autorizaba).
- `docs/modulo-auditoria.md` §3.5.

### Decisiones
- **Nivel 2 se mantiene** (decisión del desarrollador): intervención abre sin aprobación el expediente de un no protegido de otra UO, con fila `ver`. TF-ACC-05 y 08 usan tramitación (sin `historia.leer`) para el 403.
- Un 403 ordinario (sin permiso o fuera de ámbito) se anota como `ver` con `autorizado = false`; no se añade acción al enum. TF-ACC-12: sin rol operativo, fila `ver` denegada.
- La fila de abrir un plan apunta a la historia (`auditable_type = HistoriaSocial`), con la ruta del plan en el contexto.
- Documentos fuera del encargo: el controlador sigue siendo el único que anota la descarga.
- `historiaDe()` no exige `historia.leer`: la ficha (también para tramitación) solo necesita saber si hay historia.

---

## 2026-10-02 — Supervisión: «Mi equipo» marca la ficha de profesional sin cuenta

Una ficha de profesional sin cuenta de usuario (p. ej. María López en el CIAM) no puede tener perfil horario ni entrar en el sorteo de referencias, y «Mi equipo» no lo indicaba.

### Cambios
- `equipo-page.blade.php`: badge «Sin cuenta de usuario» (rojo suave) junto al nombre, con un `title` que explica la consecuencia y pide el alta como usuario.
- Test: `PerfilHorarioPorDefectoTest::mi_equipo_marca_la_ficha_de_profesional_sin_cuenta_de_usuario`.

### Decisiones
- Solo etiqueta; el alta de la cuenta sigue por el circuito habitual (Filament), sin acción nueva en «Mi equipo».

---

## 2026-10-01 — Asignación: profesionales dados de baja con casos vigentes

Error 500 en staging en `supervision/asignaciones/actividad` («Undefined array key 26»): el usuario `ts1.ciam@demo.es` se borró desde Filament (soft delete) conservando 58 referencias vigentes, y `ActividadCasosService::resumen()` no cargaba usuarios borrados.

### Cambios
- `ActividadCasosService::resumen()` carga los profesionales con `withTrashed()`: sus casos siguen vigentes hasta que se repartan (RN-08) y el supervisor necesita verlos para iniciar el reparto.
- `RepartoCasos::profesionalOrigen()` y `AsignacionProfesional::profesional()` incluyen usuarios borrados: el reparto y el historial de referencias siguen mostrando quién era el profesional.
- Pestaña «Actividad del equipo»: badge «Dado de baja» junto al nombre.
- Tests: `ActividadCasosTest::el_profesional_dado_de_baja_con_casos_sigue_en_el_resumen` y `RepartoCasosTest::se_reparten_los_casos_de_un_profesional_dado_de_baja` (fallaban antes del arreglo).

### Decisiones
- No se bloquea borrar un usuario con casos vigentes: se deja para decidir (BACKLOG). Mientras tanto, la pantalla de actividad lo muestra para que el supervisor reparta sus casos.

---

## 2026-10-01 — Agenda: perfil horario por defecto al adscribirse a un centro

En el CIAM Puente de Vallecas «Mi equipo» mostraba 11 profesionales y el cuadrante solo 1: «Mi equipo» lista la adscripción a la UO, mientras que el cuadrante y el sorteo de referencias usan el perfil horario en el centro, que solo tenía una auxiliar. Sin ningún TS con perfil, el reparto de los casos del profesional dado de baja no tenía destinos. Decisión del desarrollador: ningún profesional sin horario; por defecto, el del centro.

### Cambios
- Migración `2026_10_01_100001`: `perfiles_horario_profesional.pendiente_verificar` (por defecto `false`: los perfiles existentes ya los configuró alguien).
- `PerfilHorarioPorDefectoService` (Agenda): crea el perfil con el horario vigente del centro (una franja de apertura a cierre en cada día laborable; la jornada resulta de las franjas), con `pendiente_verificar = true`, y manda un aviso a la supervisión de la UO («Nuevo profesional en el centro: verifica su horario»).
- Observers: `UsuarioUoObserver` (al crear la adscripción) y `UsuarioProfesionalObserver` (al vincular una ficha de profesional a una cuenta ya adscrita).
- Comando `agenda:horarios-por-defecto --centro=ID|--todos` para quien estaba adscrito antes: idempotente y con un único aviso por centro.
- Perfil horario (modal de Mi equipo): alerta «Horario no personalizado»; guardar lo verifica. Filament: guardar también verifica, y hay una columna «Horario» en el listado.
- Mi equipo: badges «Horario no personalizado» y «Sin horario en el centro».
- Cuadrante del supervisor: solo perfiles activos **y vigentes hoy**, con el mismo criterio que el sorteo.
- Reparto por salida: el error sin destinos explica qué falta (cargo de referencia + perfil horario activo, sin ausencia prolongada).
- Tests: `Modules/Agenda/tests/Feature/PerfilHorarioPorDefectoTest.php` (11; comprobado en negativo quitando el observer).

### Decisiones
- El perfil rige desde la fecha de adscripción, o desde hoy si esa fecha es anterior (el pasado es inmutable: no reescribe cuadrantes pasados).
- Solo cuentas con `profesional_id`: los perfiles técnicos no tienen horario. Las fichas de profesional sin cuenta (p. ej. María López en el CIAM) no pueden tener perfil, porque se asocia al usuario.
- Horario del centro = apertura a cierre: en el CIAM (09:00–18:00, L–V) da 45 h semanales, por encima del máximo de 40 que sugiere el formulario. Como todos reciben el mismo, el peso en el sorteo es igual para todos hasta que el supervisor lo personalice.
- Si la UO tuviera varios centros, se crea un perfil en cada uno (hoy cada UO tiene uno).
