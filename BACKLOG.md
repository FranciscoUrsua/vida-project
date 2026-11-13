# BACKLOG — VIDA 360

Registro de ideas, mejoras, decisiones pendientes y deuda técnica.
Actualizar con fecha y contexto breve al añadir cada entrada.

---

# 🔧 DEUDA TÉCNICA Y PENDIENTES

---

**Servidor: revisar las reglas AppArmor de qpdf y gs tras cada actualización del sistema** — 2026-11-11
Módulo: Documentos / infraestructura
En el servidor hay reglas locales de AppArmor en `/etc/apparmor.d/local/qpdf` y `/etc/apparmor.d/local/gs` (`/var/www/vida-project/vida/storage/app/tmp/** rwk,`). Sin ellas, los perfiles de Ubuntu impiden a qpdf y gs leer la zona temporal de ingesta y **se rechazan todos los PDF** con `pdf_no_normalizable` (la causa queda en el log: `Permission denied`). **Revisar tras cada actualización del sistema** (`apt upgrade`, cambio de versión de Ubuntu o del paquete `apparmor`): que las reglas siguen, que `sudo aa-status` carga los perfiles y que se puede subir un PDF. Las reglas incluyen también las rutas del repositorio local (`storage/framework/testing/**` y `storage/app/tmp/**`, añadidas el 2026-11-11); sin ellas fallan los tests que suben PDF. Detalle en `docs/modulo-documentos.md` §3.

---

**Symfony fijado a `^7.4`: revisar con Laravel 13** — 2026-11-11
Módulo: transversal / dependencias
`symfony/event-dispatcher`, `symfony/html-sanitizer` y `symfony/string` están fijados a `^7.4` en `composer.json` desde el 2026-11-11. Motivo: Laravel 12 fija su núcleo de Symfony a `^7.2`, y no se mezclan componentes 8.x con un núcleo 7.x. Las restricciones de Laravel y Filament admiten la 8.x en esos tres paquetes, y un `composer update` general los subía a la 8.1. **Revisar al migrar a Laravel 13:** quitar las tres restricciones o subirlas junto con el resto de Symfony.

---

**Citas: delta de la revisión del 30 sep** — 2026-11-10
Módulos: Agenda, Ciudadanía
`docs/modulo-citas.md` se implementó el 2026-09-30 sobre la versión de la mañana; la revisión de la tarde (incorporada al repo el 2026-11-10) añade reglas que no están en el código:
- **§6 bis — historial de citas en la ficha del ciudadano** para `consulta_basica` y `tramitacion`: pasadas y programadas de cualquier centro, con etiqueta pública, centro, profesional, modalidad y estado; reprogramadas agrupadas con su sustituta; reprogramar y cancelar desde ahí las de su UO (no las propias). `FichaCiudadanoPage` no tiene ese bloque: solo usa citas para vincular el registro de atención (`citaVinculable()`, línea 1069) y el botón «Dar cita» (`puedeDarCita()`, línea 1081). Falta también el permiso `cita.ver_historial` (`docs/roles-consulta-basica-tramitacion.md` §5).
- **`tramitacion` en citas (§7, ⏸):** la migración `2026_09_30_120006_add_permisos_citas.php` no le da permisos `citas.*`. Se implanta con el resto del rol (diferido).
- **RN-05 ter ya se cumple** (gestión limitada a centros de la UO del usuario): `CitaPolicy::citar()` (línea 55), `gestionaAjena()` (línea 152) y `SolicitudCitaPolicy::verBandeja()/gestionar()` (líneas 37 y 49) usan `AlcanceCentro::incluye()` (UO activas del usuario), y `CitacionService` autoriza con ellas (líneas 72, 133, 160–161 y 216). No requiere cambios.
Se implementa junto con `consulta_basica` (ver la entrada siguiente).

---

**`consulta_basica`: diseño cerrado sin instrucciones de implementación** — 2026-11-10
Módulos: Usuarios, Ciudadanía, Agenda, Documentos
`docs/roles-consulta-basica-tramitacion.md` §3. Faltan instrucciones CLI y tests. Incluye la ficha por rol (§3.1: sin prestaciones ni situación social, sin banner de protegido), el bloque de estado de la Historia Social con profesional de referencia, el historial de citas (`docs/modulo-citas.md` §6 bis), el alta de ciudadanos, la edición de relaciones, los documentos propios del día (retirada lógica y vínculo con solicitud aún no existen en la custodia v2: `docs/modulo-documentos.md` §2.1) y los permisos nuevos de §5.

---

**Tipo de Centro: en ejecución** — 2026-11-10 (actualizado 2026-11-13)
*2026-11-13: hechos los pasos 1 a 7, 8 bis y 8 ter salvo: barra superior «Tipo · Centro» con selector (TF-TCE-43), selector de centro en las pantallas de supervisión (TF-TCE-44), TF-TCE-39 por las acciones Livewire y la medición de rendimiento del ámbito (8 bis.4). Quedan también el paso 8 (permisos), 8 quater (protegidos), 8 quinquies (bajas), 9 (interfaz) y 10 (cierre).*
Módulos: Centro, Organización, Prestaciones, Agenda, Intervención, Usuarios
Diseño (`docs/modulo-tipo-centro.md`), instrucciones (`docs/instrucciones-cli/2026-10-tipo-centro-implementacion.md`) y tests TF-TCE-01 a 34 (`docs/instrucciones-cli/2026-10-tipo-centro-tests.md`) en el repo. Se sigue la rama A (la asignación ya está implementada). Depende de la decisión sobre varias UO con varios centros (ver «Perfil horario por defecto: pendientes») y de que los centros de un mismo tipo tengan la misma configuración de asignación (ver el aviso en «Asignación de centro y referencia: pendientes»).

---

**Requisitos de acción y tareas guiadas — en análisis** — 2026-11-10
Módulos: transversal
`docs/propuesta-requisitos-y-tareas-guiadas.md`, con bastante por decidir (sus apartados «Por decidir»). Dependencias: TipoCentro (ver «Tipo de Centro: listo para ejecutar»), varios centros por UO (ver «Perfil horario por defecto: pendientes») e inscripción de ciudadanos en actividades (ver «Actividades grupales — gestión completa»).

---

**Acceso a expedientes: pendientes tras AccesoExpediente** — 2026-10-02
Módulo: Ciudadanía / Intervención / Supervisión
- ~~**Personas relacionadas sin filtro de protegido:**~~ Cerrado el 2026-11-11 (B1, TF-ACC-PR-01 a 08): `App\Services\PresentacionPersona` en ficha, expediente, plan (pantalla y PDF), alta de documentos, buscadores y bandeja; acciones rechazadas en servidor. Ver `CHANGELOG-112026.md`.
- ~~**`VerFichaPage` no comprueba que la ficha pertenezca a la historia**~~ Cerrado el 2026-11-12 (B2, TF-ACC-17, 18 y 18 bis): `Ficha::perteneceAHistoria()` en `mount()`; una ficha de otra historia responde 404 sin datos. Ver `CHANGELOG-112026.md`.
- ~~**Búsqueda por documento en `BuscarCiudadanoPage`**~~ Cerrado el 2026-11-12 (B4, TF-ACC-21 a 22): coincidencia exacta por `CiudadanoIdentificador::hashDe()`, sin filtrar por UO; `hsu` filtra por `ni_hsu_cm`. Ver `CHANGELOG-112026.md`.
- **Normalización de los documentos de identidad** (2026-11-12, visto en B4): el alta (`AltaCiudadano`, por `NormalizadorCiudadano::documento()`) guarda el NIF en mayúsculas sin espacios ni guiones y el NIE **con guiones** (`X-1234567-L`); la ficha (`FichaCiudadanoPage::guardarDocumento()`) guarda el valor tal como se escribe. Como `valor_hash` solo pasa a minúsculas, la búsqueda exacta (buscador, citas, matching, cita externa) encuentra o no según por dónde se guardó y cómo se escriba: `X1234567L` no encuentra un NIE dado de alta en el alta, y `12345678-Z` no encuentra `12345678Z`. Los 2 NIE de la BD compartida están sin guiones. Propuesta: una forma canónica única (mayúsculas, sin espacios ni guiones) en `hashDe()` y en lo guardado, con una migración que recalcule `valor_hash` de los existentes; no se ha hecho porque cambia lo guardado (instrucción B4).
- **Bandeja de asignaciones con protegidos:** el supervisor ve «Persona con protección especial» y puede resolver la entrada sin saber de quién es. Decidir si la bandeja de la UO responsable debe verla siempre (hoy sin historia la policy pide acceso aprobado).
- **`AuditarAccesoCiudadano`** ya no cubre ninguna ruta útil (la ficha audita en el servicio). Valorar retirarlo o aplicarlo a las rutas `{historia}` como red de seguridad sin duplicar filas.
- ~~**`guardar()` de la ficha** sigue decidiendo por rol~~ Cerrado el 2026-11-12 (B3, TF-ACC-19 a 20 quater): `guardar()` autoriza con `Gate::authorize('update')` y `puedeEditar()` delega en la policy. Ver `CHANGELOG-112026.md`.
- **Relaciones de la ficha fuera de la UO** (2026-11-12, visto en B3): `FichaCiudadanoPage::puedeEditarRelaciones()` sigue decidiendo por rol (`intervencion`, `tramitacion`), así que en consulta libre (nivel 2) se pueden crear, editar y cerrar relaciones de una persona de otra UO, aunque sus datos ya son de solo lectura. Decidir si pasa también por `CiudadanoPolicy::update` (y lo mismo para la UC del expediente).
- **`CiudadanoPolicy::create()` y `delete()` con supervisión y otro rol** (2026-11-12): el 12 nov se decidió que en `update()` gane el otro rol (supervisión solo bloquea si el usuario no tiene otro rol con `ciudadano.editar`). `create()` y `delete()` siguen bloqueando a todo usuario con `supervision`. Decidir si se igualan.
- **`VerFichaPage`: el autor de la ficha nunca se muestra** (2026-11-12, visto en B2): la vista lee `$ficha->profesional?->name`, pero `Ficha` no tiene relación `profesional` y siempre sale «—». Además, `RegistrarValoracionPage` guarda `profesional_id = auth()->id()` (id de usuario), así que la relación sería con `User` y el nombre, `nombreVisible()`. Fuera del alcance de B2.
- **`Ficha`: doble vínculo con la historia** (2026-11-12): `historia_id` (el que se usa) y `valoracion_id` (nulo en todas las fichas de la BD compartida a 2026-11-12, 11 fichas). `perteneceAHistoria()` usa la valoración solo si `historia_id` es nulo. Cuando se cierre el TODO de vincular siempre a `Valoracion`, mantener `historia_id` o derivarlo, pero no dejar dos fuentes que puedan discrepar.

---

**Supervisión: «Aprobaciones» fuera del menú** — 2026-11-11
Módulo: Supervision
Se retiró del menú el 2026-06-29, cuando la pantalla no tenía contenido operativo. Desde el 2026-09-25 resuelve las solicitudes de rol y de acceso, y `docs/modulo-usuarios-permisos.md` y el CHANGELOG hablan de «Supervisión → Aprobaciones». Hoy solo se llega desde la zona de aprobaciones de Inicio (si hay pendientes) y desde las alertas. Decidido el 2026-11-11 dejarlo así; reconsiderar si los supervisores no la encuentran. `Sidebar::aprobacionesPendientes()` (computed) ya no lo usa la vista: retirarlo o volver a usarlo según se decida.

---

**✅ CERRADO 2026-11-11 — ~~Suite de tests: Supervisión no se ejecuta~~** — 2026-09-30
Módulo: transversal
`Modules/Supervision/tests/Feature` está en la suite `Feature` de `phpunit.xml` (único fichero: `tests/Feature/Livewire/SupervisionTest.php`). `Modules/Supervision/tests`: 38 passed. Los tres fallos eran tests desfasados:
- `sidebar_sin_plazas_no_muestra_item_plazas`: «Aprobaciones» se retiró del menú a propósito el 2026-06-29 (`3eb4458`, «pantalla sin contenido operativo»), sin entrada en el CHANGELOG. Confirmado por el desarrollador el 2026-11-11: el test pasa a exigir que no esté. Los tests del badge (B03/B04) pasaban en vacío y ahora comprueban el contador de `SupervisionSidebarDataService`. Ver la entrada «Supervisión: "Aprobaciones" fuera del menú».
- `ficha_profesional_muestra_tres_pestanas`: las pestañas se retiraron el 2026-06-29 (`a81f340`, «eran stubs») y se sustituyeron por el botón «Perfil horario» de cada fila. Reescrito como `el_perfil_horario_se_abre_desde_la_fila_del_profesional_con_cuenta`.
- `auditoria_con_colectivos_muestra_columna_protegido`: ver «TF-SUP-F03 falla» (cerrada).
La viñeta «Tipo de slot genérico en modo básico» pasa a «Citas: pendientes de la fase».

---

**Borrar un usuario con casos vigentes** — 2026-10-01
Módulo: Usuarios / Asignación
- Filament permite dar de baja (soft delete) a un profesional que aún es referencia de casos; esos casos quedan con un profesional borrado hasta que el supervisor los reparte. Desde el 2026-10-01 la actividad del equipo lo muestra con «Dado de baja», pero conviene decidir si el borrado debe avisar, bloquearse o lanzar el reparto por salida (RN-08). Revisar también otras relaciones a `User` del módulo (planes, apuntes, entrevistas) que no incluyen borrados.

---

**Perfil horario por defecto: pendientes** — 2026-10-01
Módulo: Agenda / Supervisión
- El horario por defecto va de apertura a cierre sin pausa (en el CIAM son 45 h/semana). Valorar descontar una pausa o usar la jornada estándar del tipo de relación.
- Si una UO llega a tener varios centros, el profesional recibe un perfil en cada uno; habría que decidir el centro de destino al adscribir.
- «Mi equipo», el cuadrante y la asignación resuelven el centro con `Centro::where('unidad_organizativa_id', …)->first()` repetido en varias páginas: extraerlo a un único sitio.
- ~~Las fichas de profesional sin cuenta de usuario no pueden tener horario ni entrar en el sorteo; Mi equipo no lo indica.~~ Resuelto el 2026-10-02: badge «Sin cuenta de usuario».
- Varias UO con varios centros: el desarrollador lo está pensando (2026-10-02).

---

**CI «Calidad de código» (PHPStan + Pint) en rojo** — 2026-09-29 · 2026-10-01 · 2026-11-11
Módulo: transversal
*(2026-11-10: fundidas las dos entradas del 2026-09-29 y del 2026-10-01. 2026-11-11: medido y reescrito, punto A3 de `docs/instrucciones-cli/2026_11_estabilizacion_y_privacidad.md`.)*
El workflow falla desde junio de 2026 (último verde: `80c3325`, 2026-06-27); el despliegue no depende de él. Desde el 2026-11-11 `analyse-ci` usa `--memory-limit=2G`. En CI ya terminaba con 512M (1 min 36 s): el fallo es por errores, no por memoria. En la máquina de desarrollo (1 CPU, 1 GB) el análisis paralelo no termina; hay que lanzarlo por módulo con `--debug` (sin workers).

**Recuento (2026-11-11, local por módulo, 281 errores; CI daba 287 sobre `584d5ad`)** por identificador:

| Identificador | Nº | Naturaleza |
|---|---|---|
| `argument.templateType` | 164 | Falso positivo de `Livewire::test()` (ver abajo) |
| `argument.type` | 23 | Larastan estricto con `model property` (`create()`/`update()` con `array<string, mixed>`, columnas cualificadas `tabla.columna` en `where()`/`pluck()`) y formas de colección en Asignación |
| `nullsafe.neverNull` | 17 | `?->` innecesario a la izquierda de `??`; sin efecto |
| `property.notFound` | 14 | Propiedades `#[Computed]` vistas desde `Livewire\Component` en tests; `RegistroAtencion::$cita_id` sin `@property`; `HiloMensajes::$hilo` (computed) |
| `larastan.noEnvCallsOutsideOfConfig` | 13 | `Modules/Documentos/config/documentos.php`: es configuración del módulo, que se fusiona en la caché; falso positivo |
| `return.type` | 10 | `$this` frente a la clase en genéricos de relaciones (Mensajes) y formas de array en colecciones |
| `missingType.iterableValue` | 9 | `@property array` sin tipo de valor en componentes de Agenda |
| `method.notFound` | 7 | `Log::shouldHaveReceived()` (spy), `assertSeeLivewire()`, scope `vigentes()` sobre `Builder` genérico y métodos de componentes en tests: falsos positivos |
| `ignore.count` | 6 | Recuentos del baseline superados por tests nuevos (todos de `argument.templateType` o `property.notFound`) |
| `trait.unused` | 3 | Artefacto del análisis por módulo (`Versionable`, `TieneRoles`, `TieneUO` se usan desde otra ruta) |
| `staticMethod.notFound` | 3 | `Log::shouldHaveReceived()` / `shouldNotHaveReceived()` tras `Log::spy()` |
| Otros (1–2 cada uno) | 12 | `nullCoalesce.offset`, `notIdentical.alwaysTrue`, `ternary.alwaysTrue`, `match.alwaysTrue`, `return.missing` (tras `$action->halt()`, que siempre lanza), `property.nonObject`, `missingType.generics`, `method.childReturnType`, `method.alreadyNarrowedType`, `expr.resultUnused` |

Ningún fallo real pendiente con corrección local evidente: el único (`ResolucionCentroService::seccionesSinCobertura()` con `->load()` sobre `Support\Collection`) se corrigió el 2026-11-11.

**Propuesta para `argument.templateType`:** el docblock de `LivewireManager::test()` (Livewire 4.4.8, la última) declara `class-string<TComponent>|TComponent|string|array`, y con ese `|string` PHPStan no infiere `TComponent` aunque se pase `Componente::class` (comprobado con `dumpType`: `Testable<Livewire\Component>`). No hay extensión ni stubs oficiales de Larastan para Livewire. Propuesta: en `phpstan.neon`, `ignoreErrors` con `identifier: argument.templateType` limitado a `Modules/*/tests/*` y `tests/*`, y retirar del baseline las entradas equivalentes. Los `property.notFound`/`method.notFound` de tests sobre `Livewire\Component` tienen la misma causa (`Testable` no conoce el componente) y seguirían; para ellos no hay solución sin ignorar también esos identificadores en tests. Pendiente de decisión del desarrollador (no se amplía el baseline).

*2026-11-13: resuelto. El auto-commit sí llegó a funcionar (`db260c7`, 382 ficheros, quitó los `@return`); revertido, `"no_superfluous_phpdoc_tags": false` en `pint.json` y Pint pasado en local con esa configuración (decisión del desarrollador). Queda abierto si el CI debe seguir commiteando o pasar a `pint --test`.*
**Pint y PHPDoc (decisión pendiente):** el preset `laravel` de `pint.json` incluye `no_superfluous_phpdoc_tags`, que borra los `@param`/`@return` obligatorios por `CLAUDE.md` §2 (277 de los 334 ficheros que Pint cambiaría). El paso «Commit cambios de formato» del workflow no llega a commitear nada porque `file_pattern: "vida/**/*.php"` se expande en el shell sin `globstar` (solo `vida/*/*.php`); si se arreglara el patrón sin más, el CI borraría los docblocks en cada push. Propuesta: `"no_superfluous_phpdoc_tags": false` en `pint.json` y, después, pasar Pint a `--test` en CI (o arreglar el patrón del auto-commit).

**Actualizaciones disponibles** (sin aplicar, para no cambiar la medición): `larastan/larastan` 3.10 → 3.14, `phpstan/phpstan` 2.2.1 → 2.3.5.

---

**Citas: pendientes de la fase** — 2026-09-30
Módulos: Agenda, Atención, demo
- **Citas en los mundos demo (paso 10, pospuesto por decisión del desarrollador):** los mundos demo no crean agenda (horario de centro, tipos de slot, cuadrante publicado ni slots), y una cita necesita un slot libre: `CitacionService::citarDirecto()` no basta. Hace falta una agenda mínima para el centro demo (horario, tipo de slot enlazado a tipos de cita, cuadrante publicado y slots de las próximas semanas de los TSR) y después dar citas en algunos escenarios (`TrayectoriaNueva`, `TrayectoriaActiva`). Solo se podrá probar con tests: `demo:reset` no se lanza desde local (BD compartida con staging).
- **Contrato con Cita Previa:** mapeo de servicios a tipos de cita, datos de identificación que envía y reconciliación. Hoy solo el mock (`MockCitaPrevia`). Ver `docs/modulo-citas.md` §11.
- **Indicadores de citas** (demora, incomparecencias, atención por sustituto, reprogramaciones): con el módulo de analítica (principio 3.14). El historial (`cita_eventos`) ya guarda los datos.
- ~~**Buscador de personas de citación y colectivos protegidos:**~~ Cerrado el 2026-10-02 (TF-ACC-09): por documento exacto aparece solo «Persona con protección especial», sin nombre, documento ni opción de citar.
- **Gestión de citas desde la agenda del centro:** reprogramar y cancelar se hacen desde el detalle de la cita, al que se llega por la pestaña «Citas del día» de la bandeja de citación. El cuadrante del supervisor aún no enlaza las citas con su detalle.

- **Tipo de slot genérico en modo básico:** `modulo-agenda.md` prevé un tipo «Cita» automático para los centros en `modo_agenda = basico`, pero no existe ni hay forma estable de identificarlo (`tipos_slot` no tiene código). Hoy también el horario básico debe tener sus tipos elegidos en Filament. *(movida el 2026-11-11 desde «Suite de tests: Supervisión no se ejecuta»)*
---

**Asignación de centro y referencia: pendientes** — 2026-09-30
Módulos: Centro, Intervención, Ciudadanía, geocodificación
- **Adaptador real de la BDC** (`BdcGeocodificador`): NDP, distrito, barrio y sección, código INE de la sección y conversión UTM (ETRS89 30N) → WGS84. Hoy solo el mock rellena los códigos. Ver `docs/geocodificacion.md` §6.
- **Decisiones pendientes de `docs/modulo-asignacion.md` §11:** personas sin hogar (asignación por coordenadas de pernocta y zonificación de equipos de calle; hoy, «quien abre» y centro manual); asignación geográfica de profesionales (zonas por profesional); cambio a petición de la persona y cupos; fórmulas mixtas de centro (elegir dentro del distrito); procedimiento de revisión de secciones censales y reasignación por NDP.
- **Cambiar la referencia de un caso ya asignado desde la interfaz:** el servicio existe (`AsignacionReferenciaService::cambiarManual()`, TF-ASG-30), pero la pantalla solo lo usa para historias sin referencia (bandeja) y en el reparto. Falta la acción del supervisor en la ficha o el expediente (conflicto, parentesco, corrección…).
- **Autorización por centro en los servicios:** `AsignacionCentroService::asignarManual()/descartar()` y `AsignacionReferenciaService::cambiarManual()` exigen rol `supervision`, pero no que el supervisor lo sea del centro afectado; hoy lo garantiza la pantalla. Valorar moverlo al servicio, como ya hace `RepartoCasosService`.
- ~~**Colectivos protegidos en la bandeja:**~~ Cerrado el 2026-10-02 (TF-ACC-10): sin nombre ni enlace si la policy no deja ver a la persona. Queda por decidir cómo resuelve el supervisor una entrada que no puede identificar (ver «Acceso a expedientes: pendientes»).
- **Configurar los centros reales en staging:** tras el despliegue todos quedan en `quien_abre` y sin tipo. Para probar el sorteo hay que dar tipo, ámbito y modo a algún centro en Filament. **Aviso (2026-11-10):** el paso 3 de `docs/instrucciones-cli/2026-10-tipo-centro-implementacion.md` aborta si dos centros del mismo tipo tienen distinto modo de asignación, ventana o segmentos. Al configurar centros para probar el sorteo, configurar igual todos los del tipo.

---

**Bootstrap único: pendientes fuera del plan** — 2026-09-28
Módulo: Frontend / Filament
- **Tema de Filament con la paleta antigua:** `resources/css/filament/admin/theme.css` conserva `success` #4B8A5B, `warning` #B8852F, `danger` #B0432E e `info` #3F6E99, que no son los canónicos (ver anexo A de `docs/instrucciones-cli/2026-09-bootstrap-unico.md`) y en success/warning no llegan a AA. Filament queda fuera del plan; decidir si se alinea.
- **R5 da por usada una clase que coincide con un nombre de vista o ruta.** Valorar excluir de la búsqueda de usos las cadenas `vista::…`/`route('…')`.
- **Tests con fallo previo detectados al migrar**: TF-AUTH-16/17 (`User::name` devuelve el correo en la portada `inicio`), `AutorizacionDatosTest::servicio_historia_social_permite_actualizacion_con_permiso` (FK de `audits.ciudadano_id`), Agenda (columna `horario_centro_id` inexistente en `tipos_slot` en `vida_testing`; `foreach` sobre cadena en `cuadrante-supervisor-page`).
- **Vistas públicas con clases sin definir:** `welcome-page__hero`, `welcome-page__summary-section` (welcome) y `auth-card__form` (login). Se resuelven en la fase 3.

---

**Frontend: rendimiento de las pantallas grandes (revisión de Grok, fases 6 y 7)** — 2026-09-29
Módulo: Intervención / Ciudadanía / Mensajes
Detalle en `docs/front/revision-frontend-ui.md` §6 y `docs/front/plan-correccion-frontend-ui.md` fases 6 y 7.
- **`CiudadanoPage`** (1.131 líneas, ~20 `#[Computed]`) y **`plan-page`** (~66 KB): un buscador `wire:model.live` (UC en el expediente, `relacionBusqueda` en la ficha) vuelve a pintar toda la pantalla. Partir en componentes hijos (timeline, accesos, UC, herramientas).
- **Consultas en la vista:** `\App\Models\Ciudadano::find($ucCiudadanoSeleccionado)` en `ciudadano-page.blade.php`; `$datos`, `$doc` y `$yaEnPlan` dentro de bucles en `plan-page`; `Configuracion::logoUrl()` y `nombreAplicacion()` en cada request del shell.
- **Toasts:** `wire:poll.60s` sobre todo el árbol de `AlertaToast` en todas las pantallas. No volver a Alpine con `wire:ignore`: el morph rompía el estado minimizado (ver CHANGELOG 2026-09-27). Buscar un polling más ligero (solo el contador).
- `$herramientas` del expediente se reconstruye en cada render: sacarla a una constante.

---

**Dato de sexo fuera del catálogo** — 2026-09-29
Módulo: Ciudadanía
En la BD compartida hay un ciudadano con `sexo = 'H'`, guardado desde la ficha antes de alinearla con el catálogo `ciudadano.sexo` (M/F/D). La ficha lo muestra tal cual («H»). Corregirlo desde la ficha (editar y guardar) para que quede versionado; no con un `UPDATE`.

---

**✅ CERRADO 2026-10-02 (TF-ACC-01 a TF-ACC-12) — ~~La ficha del ciudadano no aplica la restricción de colectivos protegidos~~** — 2026-09-25
Módulo: Ciudadanía (prioritario: restricción crítica de `CLAUDE.md` §3)
`FichaCiudadanoPage::mount()` carga al ciudadano con `withoutGlobalScope(AmbitoUoScope::class)` y solo comprueba el rol; no llama a `CiudadanoPolicy::view`. Cualquier profesional con rol `intervencion`, `tramitacion`, `consulta_basica` o `supervision` puede abrir la ficha de una persona de colectivo protegido de otra UO sin acceso aprobado. Detectado al implementar `DocumentoPolicy`, que sí aplica la policy. Solución probable: `Gate::authorize('view', $c)` en `mount()` y un test en negativo.
*2026-10-01: siguiente tarea tras probar asignación y citas en staging. Abordar a la vez los otros dos puntos que muestran personas protegidas: la bandeja de asignaciones del supervisor (nombre visible) y el buscador de personas de citación (`BuscadorPersonasCita`: por documento exacto sí la encuentra). Ver «Citas: pendientes de la fase» y «Asignación de centro y referencia: pendientes».*
*2026-10-02: cerrado. Ficha, expediente, plan, valoración y ficha de valoración autorizan y auditan en `App\Services\AccesoExpediente`; las policies de historia y plan delegan el criterio de protegido en `CiudadanoPolicy::consultaExternaPermitida()`; el buscador de ciudadanos, el de citación y la bandeja de asignaciones no muestran nombre ni documento de un protegido sin acceso. Tests en `tests/Feature/Acceso/AccesoExpedienteTest.php` y TF-ACC-11 en `AccesoDocumentoTest`.*

---

**Mensajes — pendientes detectados al corregir el módulo** — 2026-09-26
Módulo: Mensajes
- **Alertas a «cualquier persona de un colectivo»** (decisión aplazada por el desarrollador): una alerta que basta con que atienda un miembro cualquiera del colectivo (p. ej. un trabajador social del centro, vale cualquiera). Hoy todas las alertas `rol_uo` son «a cada miembro»: cada destinatario debe reconocerla. Cuando se aborde: nuevo valor de `destinatario_type` (o un indicador) y que el primer reconocimiento cierre la parte de los demás. ¿Cómo escalar entonces: una sola vez o por destinatario?
- **Avisos del supervisor a un cargo concreto** (p. ej. solo los trabajadores sociales del centro): hoy van a todo el equipo de la UO. Los roles de Spatie no sirven como «colectivo» visible (no tienen nombre legible y no equivalen a cargos); habría que dirigir por `cargo` (nuevo `destinatario_type`).
- **Supervisor con varias UO o con UO hijas:** el aviso y el seguimiento usan las UO con adscripción vigente del supervisor, sin bajar a las UO hijas. Revisar si un supervisor de una UO padre debe ver a los equipos de las hijas.
- **Destinatarios que dejan la UO** conservan sus alertas pendientes (los destinatarios se fijan al crear). Revisar si al cerrar una adscripción deben traspasarse o cerrarse.
- **Bandeja: enlace al contexto de origen** (instrucciones, `BandejaAlertas`): cada alerta debería enlazar a su origen (p. ej. la solicitud de acceso o la asignación de rol) si existe y el usuario puede verlo. No se ha hecho en el paso 2: cada `origen_type` necesita su ruta y su comprobación de permiso.
- **`Modules/Agenda` tiene un sidebar de supervisor (`agenda.supervisor.sidebar`) registrado que ningún layout usa**: el de Supervisión lo sustituyó. No tiene las entradas de la bandeja; retirarlo si se confirma que está muerto.
- **Panel de redacción, pendientes:** (a) más contextos (apunte, entrevista, prestación) cuando haga falta; (b) la búsqueda de ciudadanos filtra por nombre en PHP sobre 500 registros como mucho, igual que la de Intervención: sustituir por el índice de búsqueda cuando exista; (c) el panel no se ha probado en navegador (foco, teclado, pantallas estrechas).
- **Toasts de alertas (2026-09-27), pendientes:**
  - (a) Las alertas de `AccesoProtegido` no llevan «Ver origen»: no hay pantalla que resuelva esas solicitudes (Aprobaciones solo trata `UsuarioRol`).
  - (b) La bandeja podría reutilizar `AlertaToast::enlaceOrigen()` para su propio enlace al origen (punto anterior).
  - (c) No hay toasts en el backoffice Filament.
  - (d) No se han probado en navegador pantallas estrechas ni la convivencia con el panel de redacción. Tampoco hay test de navegador (Dusk o Playwright): la interacción entre el morph de Livewire y Alpine no se detecta con `Livewire::test`.
- **Error en `instrucciones-cli-mensajes.md` (tests mínimos):** «17:30 de un día laborable → 09:00 del día siguiente» es incorrecto con 4 horas laborales; lo correcto es 12:00 (lo que ya comprueba T-HLS-04).

---

**Suite completa con 75 fallos fuera de Documentos** — 2026-09-25
*2026-11-12: suite completa con 1318 passed, 12 incomplete, 1 skipped y **un solo fallo**: `AutorizacionDatosTest::servicio_historia_social_permite_actualizacion_con_permiso` (FK de `audits.ciudadano_id`). Es lo único que queda de esta entrada.*
Módulos: Agenda, Mensajes, Ciudadanía, Autenticación, Intervención
(2026-09-26: los 6 fallos de Mensajes están resueltos; `t_lw_09` se sustituyó por TF-MSG-PAN-10 al retirar `NuevoMensaje`.)
Primera ejecución completa registrada (908 passed, 76 failed; el fallo de Documentos ya está corregido). Detalle por módulo en `CHANGELOG-092026.md` («Documentos: custodia v2, UI operativa y cierre»). La mayoría son de Agenda (esquema que no existe). En Ciudadanía, dos tests buscan un texto que ya no está en la ficha. Revisarlos antes de un merge a `main`.
*2026-11-10: los de Agenda están corregidos (ver la entrada cerrada «Suite de `Modules/Agenda` rota»). Quedan: `AutorizacionDatosTest::servicio_historia_social_permite_actualizacion_con_permiso` (FK de `audits.ciudadano_id`; ver «Bootstrap único: pendientes fuera del plan») (TF-AUTH-16/17 y los 2 de Ciudadanía «Ver historia social» corregidos el 2026-11-11). Los 3 de Supervisión, corregidos e incluidos en `phpunit.xml` el 2026-11-11.*

---

**Documentos — decisiones y mejoras pendientes de la custodia v2** — 2026-09-25
Módulo: Documentos
- Qué hito crea una retención `intervencion_cerrada` (`RetencionService::retener()` existe sin ningún evento conectado).
- Proveedor de la clave maestra en producción (KMS, Vault o HSM); hoy `ProveedorClavesLocal` con `DOCUMENTOS_CLAVE_MAESTRA`.
- Permisos sobre documentos compartidos: regla provisional «puede verlo quien pueda ver al menos una persona vinculada» (con `CiudadanoPolicy::view`, decisión del 2026-09-25). Un documento compartido entre una persona protegida y otra que no lo es se abre por la no protegida.
- Auditoría de accesos a documentos compartidos: una entrada por acceso, con `ciudadano_id` de la persona que da el acceso y el resto en `contexto.ciudadanos_vinculados`. Decidir si la traza de cada TSR debe mostrar también los accesos a documentos compartidos por sus personas.
- Retirar el grupo `documento.tipo` de `catalogos_sistema` (ya migrado a `tipos_documentales`). Su clave `informe_generado` se migró como tipo «aportado por el ciudadano», igual que el resto; probablemente debería desactivarse porque los informes firmados usan `informe_profesional`.
- Futuro, sin fecha: portal del ciudadano (CSV, representantes), remisión a otras administraciones (nuevo motivo de retención).
- `StreamMaxLength` de clamd (25 MB por defecto) debe ser mayor que el tamaño máximo de subida. Si no, los ficheros grandes se rechazan como «antivirus no disponible», un mensaje que confunde.
- Programar `documentos:proponer-destruccion` en el scheduler (hoy solo a mano). Mientras ningún tipo tenga `conservacion_anyos`, no propone nada.
- Si se restaura un ciudadano dado de baja, sus vínculos a documentos siguen inactivos. Decidir si se reactivan.
- UI: abrir versiones anteriores sustituidas (hoy el controlador solo sirve la vigente), y el mismo panel en planes de intervención y valoraciones cuando se vinculen documentos a ellos.
- Conformidad PDF/A estricta: Ghostscript declara PDF/A-2b, pero no se ha validado con veraPDF ni se añade un OutputIntent explícito.
- (2026-11-11) **Coste de la medición de tinta** (`SaneadorPdf::tintaTotal()`, `pdf_sin_contenido`): `inkcov` rasteriza todas las páginas y, en el servidor de 1 CPU, añade unos 6,5 s a un PDF de 51 páginas y 14 s a un escaneo de alta resolución de 6 páginas (además del saneado). Opciones si molesta: `-r36` (la mitad de tiempo; una «x» de 10 pt se sigue detectando) o parar en la primera página con tinta (página a página con `-dFirstPage/-dLastPage`). Medir antes con uso real.
- (2026-11-11) `TF-DOC-ING-01`: el «fichero de 0 bytes se guarda» observado en staging el 11 nov no se reproduce y no hay ninguna versión de 0 bytes en la BD. Si vuelve a verse, anotar el navegador y si la subida de Livewire llegó a terminar.

---

**Cuatro ojos en la aprobación de roles: quien solicita un rol para otro puede aprobarlo** — 2026-09-25
Módulo: Usuarios / Supervisión
Desde 2026-09-25 nadie resuelve su propia solicitud de rol. Pero si un usuario tiene `adm_usuarios` y `supervision` en la misma UO, puede pedir `supervision` o `adm_sistema` para un compañero desde el formulario de usuarios y aprobarla él mismo en Supervisión → Aprobaciones (`usuario_rol.asignado_por` = quien aprueba). Decidir si se bloquea también ese caso; en instalaciones pequeñas puede no haber un segundo supervisor.

---

**Destinatario de la alerta supervisada de roles: UO del usuario frente a «UO superior»** — 2026-09-25
Módulo: Usuarios / Mensajes
La sección 2.8 habla del «supervisor de la UO superior». `AsignacionRolesService` dirige la alerta al rol `supervision` de la UO de la primera adscripción vigente del usuario (a la raíz si no tiene adscripción), igual que las solicitudes de acceso a colectivos protegidos y que `AprobacionesPage`, que muestra al supervisor su propia UO y las inferiores. Confirmar si debe subir a la UO padre.

---

**Local y staging comparten la misma base de datos `vida`** — 2026-09-24
Módulo: Infraestructura
Detectado en la Fase 0 del mundo «Prueba CIAM»: `~/code/vida-project/vida/.env` (`APP_ENV=local`) y `/var/www/vida-project/vida/.env` (`APP_ENV=staging`) apuntan a `vida@127.0.0.1`. Toda migración o `demo:*` lanzado «en local» se aplica sobre staging, y un `demo:reset` en local truncaría los datos de staging (incluidos los de otros equipos). Recomendación: crear una BD de desarrollo propia (p. ej. `vida_dev`) para el entorno local.

*2026-11-11: **aplazada por decisión del desarrollador** (A4 de `docs/instrucciones-cli/2026_11_estabilizacion_y_privacidad.md`). Con un solo desarrollador, compartir la BD garantiza ver lo mismo en local y en staging y evita cambiar de contexto. Reconsiderar al cerrar la versión 1.0. Mientras tanto siguen vigentes las precauciones: nada de `demo:reset` ni `migrate:fresh` desde local.*
---

**No existe asociación tipo de plan ↔ centro / UO / servicio** — 2026-09-24
Módulo: Intervención
`PlanPage::tiposPlanes()` ofrece todos los tipos activos (`piso`, `pia`…) a cualquier profesional de cualquier centro. El punto 1.4 de `docs/instrucciones-cli/2026-09-demo-ciam-aditivo.md` (asociar `pia` al CIAM) se omitió por decisión del desarrollador: no se crea un mecanismo nuevo sin diseño. Decidir si el tipo de plan se restringe por centro, por UO (junto a `plan_nombre_*`) o por servicio especializado (`planes_intervencion.servicio_especializado_id`, hoy sin uso: la tabla `servicios` está vacía).
*2026-11-10: resuelto en diseño por `docs/modulo-tipo-centro.md` (tipos de plan por tipo de centro, §6.2). Se cierra al implementar esa fase.*

---

**La interfaz no permite crear planes especializados; `PlanPage` fija siempre `tipo = general_asp`** — 2026-09-24
Módulo: Intervención
`PlanPage::crearNuevoPlan()` guarda `tipo = TipoPlan::GeneralAsp` sea cual sea el `tipo_plan_id` elegido. Un «PIA» creado hoy desde la UI queda como plan general sin plan ASP, y `CiudadanoPage::pisoActivo()` / `MisCasosPage` lo tratan como el PISO de la persona. Los planes PIA del mundo «Prueba CIAM» sí son `especializado` (entrada directa). Pendiente: derivar `tipo` del `ambito` del tipo de plan al crear, y diseñar el flujo de creación de planes especializados (derivación desde PISO y entrada directa con `admite_entrada_directa`). Fuera del alcance de la sesión CIAM por instrucción explícita.
*2026-11-10: Tipo de Centro no lo resuelve: solo filtra los tipos de plan ofrecidos. Prioritario para el CIAM.*

---

**Retirada de los datos de mundos aditivos (etiqueta `TEST_CIAM`)** — 2026-09-24
Módulo: Demo / World Building
Todo lo creado por `demo:load` queda en `demo_world_registros` (`DemoWorldRegistro::de('TEST_CIAM')`), pero no hay comando de purga por etiqueta: por instrucción, la retirada se diseñará aparte (orden de borrado, soft vs hard delete, auditoría generada por la carga, filas pivote no registradas como `model_has_roles`, `actividad_profesional` y `sesion_actividad_profesional`).

---

**✅ CERRADO 2026-11-10 — ~~Suite de `Modules/Agenda` rota: `tipos_slot.horario_centro_id` no existe en el esquema~~** — 2026-09-24
*2026-11-10: cerrado. Paso 0 de citas (`CHANGELOG-092026.md`, «2026-09-30 — Agenda: tests en verde y fallos de la aplicación»): factories y `AgendaSeeder` enlazan por el pivote `horario_centro_tipo_slot`; `Modules/Agenda/tests` 173 passed el 2026-10-01 (SESSION).*
Módulo: Agenda
Al ejecutar la suite completa (verificación de una actualización de dependencias) se detectaron ~60 tests de `Modules/Agenda` fallando con `QueryException: column "horario_centro_id" of relation "tipos_slot" does not exist`. Reproducible también con las dependencias de Composer anteriores a la actualización de hoy — no relacionado con dependencias. Probable causa: el commit `3003283` ("convertir TipoSlot en catálogo global del sistema") eliminó esa columna del esquema pero algún factory o helper de test (`Modules/Agenda/database/factories/CitaFactory.php`, `AgendaSupervisorTestHelpers.php`, entre otros) sigue insertándola. Revisar si falta una migración por ejecutar en `vida_testing` o si el factory quedó desactualizado tras ese refactor.

---

**✅ CERRADO 2026-11-11** — **TF-AUTH-16 y TF-AUTH-17 fallan: `User::booted()` sobreescribe `name` con el email siempre** — 2026-09-24
*Cerrado: la UI usa `User::nombreVisible()` (nombre del Profesional vinculado o email) y los tests vinculan el usuario a un Profesional; `booted()` no cambia. Ver `CHANGELOG-112026.md`.*
Módulo: Usuarios / Auth
`User::booted()` (hook `creating`) hace `$user->name = $user->email;` de forma incondicional, incluso cuando se crea el usuario con un `name` explícito. Esto rompe `tests/Feature/Auth/AutenticacionTest.php::tf_auth_16_el_nombre_del_usuario_aparece_en_la_ui` y `tf_auth_17_las_iniciales_del_avatar_son_las_dos_primeras_letras_del_name`, que esperan ver "Juana López" y sus iniciales "JL" en la UI tras crear el usuario con ese nombre. Confirmado reproducible en `master` sin relación con ningún cambio de la sesión que lo detectó (soft delete de `User`, ver `docs/decisiones-tecnicas.md` Sección 13). Corrección probable: solo rellenar `name` con el email cuando `name` no se ha establecido explícitamente.

---

**✅ CERRADO 2026-11-11 — ~~TF-SUP-F03 falla: columna "Colectivo protegido" en auditoría~~** — 2026-06-29
Módulo: Supervision
Confirmado: la marca «Colectivo protegido» solo se pinta en las filas de accesos y el test no creaba ninguno (la página mostraba el estado vacío). El test registra ahora, con `AuditService::registrarAcceso()`, un acceso a un ciudadano protegido de la UO del supervisor. TF-SUP-F02 usa el mismo acceso, para que su negativo no pase en vacío.
---

**Notificaciones reales al TSR tras asignación de plaza** — 2026-06-26
Módulo: Intervencion
`AsignarPlazaModal::notificarTsr()` actualmente solo escribe en el log (`Log::info`). Pendiente implementar notificación real (alerta en bandeja o push) cuando se asigne una plaza a una prescripción de un ciudadano con TSR activo. El módulo de Mensajes deberá gestionar el formato final.

---

**Asignación automática de plaza en `PrescribirRecursoModal::confirmar()`** — 2026-06-26
Módulo: Intervencion
Cuando `hayPlazaDisponible = true`, el estado de la prescripción se marca como 'asignada' pero `plaza_id` queda null (la asignación concreta la hace el TS del centro vía `AsignarPlazaModal`). Valorar si conviene auto-asignar la primera plaza libre disponible para reducir fricción en centros pequeños.

---

**Criterio territorial: reemplazar haversine PHP por PostGIS** — 2026-06-26
Módulo: Intervencion / Centro
`PrescribirRecursoModal::opcionesDestino()` ordena por distancia con haversine calculado en PHP (colección cargada en memoria). Con muchas colecciones (> 100), migrar a `ST_Distance` de PostGIS sería más eficiente. Por ahora la solución en PHP es suficiente.

---

**Disponibilidad de salas (validación de conflictos de reserva)** — 2026-06-25
Módulo: Agenda (futuro)
VIDA 360 almacena `sala_id` en `SesionActividad` como dato informativo de ubicación, pero no detecta conflictos cuando dos sesiones distintas usan la misma sala en el mismo horario. Esta funcionalidad se diseñará en el módulo de Agenda. Tampoco se valida que el aforo de la sala sea suficiente para el número de inscritos (queda a criterio del profesional).

---

**Migraciones de slugs en tipos_actividad existentes** — 2026-06-25
Módulo: Centro
El campo `slug` se añadió como nullable para compatibilidad con datos existentes. Si hay filas en `tipos_actividad` previas al seeder, tendrán `slug = NULL` hasta que se editen en Filament o se ejecute `db:seed --class=CentroSeeder`. Pendiente: valorar añadir un comando artisan de migración de slugs (slugify del `nombre`) o documentarlo para los administradores del sistema.

---

**Actividades grupales — gestión completa** — 2026-06-25
Módulo: Supervision / Centro
El modal de alta de actividades está implementado. Pendiente: edición y baja de actividades, gestión de sesiones (`SesionActividad`), e inscripción de ciudadanos desde Intervención. El modelo `Actividad` ya tiene la estructura; falta la UI de ciclo de vida completo.

---

**Filtro por segmento en SelectorPrestacionesCentro** — 2026-06-08
Módulo: Centro / Prestaciones
Implementar el filtro por segmento de población en el selector de prestaciones del centro. Actualmente el filtro aparece en la UI (botones por segmento) pero no aplica ninguna restricción a la query de prestaciones.
Razón: el campo `poblacion_destinataria` en `Prestacion` es un array JSONB de claves de `catalogos_sistema` (grupo `prestacion.poblacion`), no una FK a `segmentos_poblacion`. Para implementar el filtro es necesario definir un mapeo entre los segmentos de población del centro (`SegmentoPoblacion`) y las claves del campo `poblacion_destinataria`, o añadir una relación directa `Prestacion` ↔ `SegmentoPoblacion`.
Ver: `app/Livewire/Centros/SelectorPrestacionesCentro.php` — comentario TODO en `prestacionesFiltradas()`.

---

**TODOs activos en CiudadanoPage** — 2026-06-15
Módulo: Intervención / Ciudadanía
Varios TODOs documentados en `ciudadano-page.blade.php` que requieren trabajo pendiente:
- `centroActivo()` en `CiudadanoPage.php` — pendiente de implementación. *2026-11-10: el TODO ya no está en la vista y el centro de la persona se ve en el bloque «Centro y referencia» de la ficha (asignación, 2026-09-30), pero la cabecera del expediente sigue sin mostrarlo. Sin cerrar.*
- ~~DNI en cabecera — requiere `CiudadanoIdentificador::activo()` (tabla `ciudadano_identificadores` aún no existe).~~ *Resuelto (comprobado el 2026-11-10): `CiudadanoPage::ciudadanoDocumento()` lo lee de `documentoVigente` y la cabecera lo muestra (`ciudadano-page.blade.php:145`).*
- Menú ⋯ contextual — sin implementar (sigue el TODO en `ciudadano-page.blade.php:106`).
- `statPrestaciones()` devuelve null — pendiente integración con módulo Prestaciones (sigue igual, `CiudadanoPage.php:404`).

---

**✅ CERRADO 2026-11-10 — ~~Tabla `ciudadano_identificadores` pendiente de crear~~** — 2026-06-10
*2026-11-10: cerrado. La tabla existe desde el 2026-06-09 (migración `2026_06_09_100002_create_ciudadano_identificadores_table.php`, `CHANGELOG-062026.md`) y el DNI de la cabecera del expediente sale de ella (`CiudadanoPage::ciudadanoDocumento()`, `documentoVigente`). Sigue abierta la búsqueda por documento de `BuscarCiudadanoPage` (`buscarPorIdentificador()`, línea 143, devuelve vacío): ver «Acceso a expedientes: pendientes tras AccesoExpediente».*
Módulo: Ciudadanía
La búsqueda por `doc`/`hsu` en `BuscarCiudadanoPage` devuelve vacío con TODO. La tabla y modelo `CiudadanoIdentificador` no existen aún. Necesario también para `CiudadanoIdentificador::activo()` que se usa en cabecera de ciudadano.

---

**✅ CERRADO 2026-11-10 — ~~UC en UI de intervención~~** — 2026-06-16
*2026-11-10: cerrado. El expediente gestiona la UC (`CiudadanoPage`: modal y buscador de UC, `confirmarAnadirMiembro()`, `confirmarBajaMiembro()`, `verificarMiembro()`, `crearUc()`, líneas 612–732) y el bloque UC de la ficha enlaza la ficha de cada miembro (`ficha-ciudadano-page.blade.php:396`), sin TODO.*
`Módulo: Ciudadanía / Intervención`
Los modelos y migraciones de UnidadConvivencia están implementados.
Pendiente: UI Livewire para gestión de UC dentro de la pantalla de intervención
del ciudadano (añadir/dar de baja miembros, verificar residencia, ver composición).
El botón "Ver ficha" en el bloque UC de `FichaCiudadanoPage` sigue apuntando a TODO.

---

**Tabla `derivaciones` pendiente de crear** — 2026-06-01
Módulo: Intervención
`crearDerivacion()` en `CiudadanoPage` crea solo el Apunte (tipo `derivacion`); la tabla y modelo `Derivacion` no existen. Añadir cuando esté disponible.

---

**Búsqueda por nombre cifrado con índice hash determinista** — 2026-06-01
Módulo: Ciudadanía
`BuscarCiudadanoPage` carga ≤ 500 registros y filtra en PHP para evitar el problema de búsqueda sobre campos cifrados. Reemplazar por índice hash determinista cuando el módulo Ciudadanía esté completo.

---

**✅ CERRADO 2026-11-10 — ~~Integración real de auditoría en BuscarCiudadanoPage (nivel 2)~~** — 2026-06-01
*2026-11-10: cerrado el 2026-10-02 (`CHANGELOG-102026.md`, «Acceso: policy y auditoría de lectura de expediente»): `BuscarCiudadanoPage::registrarAccesoNivel2()` (`Modules/Intervencion/app/Http/Livewire/BuscarCiudadanoPage.php:207`) audita con `AccesoExpediente`, sin `Log::info` ni TODO.*
Módulo: Ciudadanía / Auditoría
El registro de acceso nivel 2 usa `\Log::info()` con TODO. Ya existe la tabla `audits` e `AuditService` desde 2026-06-14; pendiente conectar `BuscarCiudadanoPage` al `AuditService` en lugar del `\Log::info()`.

---

**✅ CERRADO 2026-11-10 — ~~[Demo] Citas en escenarios de demo~~** — 2026-06-03 · *2026-09-30: la creación de citas ya existe, pero falta agenda en los mundos demo; ver «Citas: pendientes de la fase».*
*2026-11-10: cerrada por duplicada: sigue como viñeta «Citas en los mundos demo (paso 10)» de «Citas: pendientes de la fase».*
Módulo: Demo world-building
Las trayectorias del sistema de demo no generan citas porque requieren `slot_id` (FK NOT NULL a la tabla `slots`) y toda la maquinaria de agenda (cuadrantes, perfiles horarios, excepciones). Pendiente para cuando el módulo Agenda exponga una API simplificada de creación de citas de test.
Ver: `database/seeders/Demo/Scenarios/` — todos los escenarios tienen comentario explicit documentando la omisión.

---

**[Demo] Campos ausentes en historias_sociales** — 2026-06-03
Módulo: Demo world-building / HistoriaSocial
La tabla `historias_sociales` no tiene `fecha_apertura`, `fecha_cierre` ni `sia_contacto_id`. Si se añaden estos campos en futuras migraciones, actualizar todos los escenarios de demo para poblarlos. Los escenarios actuales usan `created_at` implícitamente para la fecha de apertura.

---

**[Demo] Tests de integración pesados TF-DEMO-08 a TF-DEMO-12** — 2026-06-03
Módulo: Demo world-building / Tests
Los tests TF-DEMO-08 a TF-DEMO-12 están declarados como `markTestIncomplete`. Requieren:
- BD de demo aislada con roles Spatie (`intervencion`, `supervisor`, `consulta_basica`) ya sembrados
- APP_ENV = 'local' o 'staging' (no 'testing') para que `demo:reset` no aborte
- Suite de tests separada que no use RefreshDatabase global
Ver: `tests/Feature/Demo/DemoWorldLoaderTest.php`

---

**Migrar `app/Services/HistoriaSocialService` al módulo Intervención** — 2026-05-25
Módulo: Intervención
Actualmente coexisten dos `HistoriaSocialService` con namespaces distintos (`App\Services` y `Modules\Intervencion\Services`). Fusionar cuando el módulo Intervención esté consolidado.

---

**TF-LW-NAV-03 marcado como `markTestIncomplete`** — 2026-06-01
Módulo: Intervención / Tests
Requiere datos de plan activo en BD para completarse.
Ver: `Modules/Intervencion/tests/Feature/Livewire/NavegacionTest.php`

---

**Módulo Escalas fase 2 — Livewire de aplicación del pase** — 2026-05-26
Módulo: Escalas / Intervención
Componente Livewire para aplicar un pase de escala desde la Historia Social del ciudadano:
selección de instrumento, presentación sección a sección, confirmación de instrucciones si
`confirmar_instrucciones=true`, cierre del pase con cálculo de scores, y visualización del
historial de pases por escala ordenado cronológicamente.
Bloqueante: definir el punto de entrada desde la Historia Social (¿pestaña independiente?
¿acción en la ficha de valoración?).

---

**Clarificación licencia Zarit ZBI** — 2026-05-26
Módulo: Escalas
Contactar con Steven Zarit (Pennsylvania State University) para confirmar si el uso en un
sistema público municipal de servicios sociales no comercial está cubierto por la excepción
de uso clínico. Si se confirma, añadir al `EscalaSeeder`.
Ref: `docs/modulo-escala.md §7.2`.

---

**Clarificación licencia GDS de Yesavage** — 2026-05-26
Módulo: Escalas
Aclarar si la versión original de 30 ítems (1983) está en dominio público y si la traducción
española validada es de libre uso en contexto público no comercial.
Ref: `docs/modulo-escala.md §7.2`.

---

**Conectar PrescripcionService al TSR activo del ciudadano** — 2026-05-18
Módulo: Centros / Ciudadanía
`PrescripcionService::liberarPlaza()` usa un resolver inyectable para el TSR activo del ciudadano.
En producción debe conectarse al módulo Ciudadanía (o al registro de HistoriaSocial del módulo Intervención)
cuando esté disponible. Actualmente el resolver por defecto devuelve null (sin actualización de alerta).
Ref: `Modules/Centro/app/Services/PrescripcionService.php` — método `setTsrResolver`.

---

**✅ CERRADO 2026-11-10 — ~~Implementar servicios pendientes del módulo Agenda (30 tests bloqueados)~~** — 2026-05-18
*2026-11-10: cerrado. Existen `Services/CuadranteGeneratorService.php`, `Services/DisponibilidadService.php`, `Jobs/SlotExpirationJob.php`, `Services/GestionAusenciaService.php`, el ciclo de vida de `Cita` (`Models/Cita.php` `noShowCiudadano()`, `completar()`, `cancelar()`), el bloqueo y la liberación de slots de `EventoAgenda` (`Models/EventoAgenda.php:197` y `:222`) y PF-02.3 (`tests/Feature/PerfilHorarioTest.php:230`). No queda ningún `markTestIncomplete` en `Modules/Agenda/tests`; Agenda 173 passed el 2026-10-01.*
Módulo: Agenda
Tras implementar `SlotMaterializadorService`, quedan pendientes:
- `CuadranteGeneratorService` (bloquea PF-03.1, PF-03.4, PF-03.5, PF-10.1)
- `DisponibilidadService` (bloquea PF-04.2, PF-09.1, PF-09.2)
- `SlotExpirationJob` (bloquea PF-04.4, PF-04.5, PF-06.3)
- Lógica de ciclo de vida de `Cita` (bloquea PF-05.1, PF-05.2, PF-05.4–PF-05.8)
- Bloqueo de slots al crear `EventoAgenda` (bloquea PF-04.3, PF-08.1–PF-08.4)
- `GestionAusenciaService` (bloquea PF-06.1, PF-06.2, PF-07.1–PF-07.5)
- Validación de solapamiento en `PerfilHorarioProfesional` (bloquea PF-02.3)
Ver `docs/modulo-agenda.md §8` para detalle de cada test.

---

**larastan/larastan — migración pendiente** — 2026-06-03
Módulo: Tooling
El paquete `nunomaduro/larastan` está marcado como abandonado upstream; el sucesor es `larastan/larastan`. Migrar en la próxima sesión de actualización de dependencias planificada.

---

**Reducir baseline de PHPStan** — 2026-06-03
Módulo: Tooling
La baseline actual tiene 772 errores heredados. Reducirlos progresivamente en cada sesión de refactor.
Ref: `phpstan-baseline.neon`.

---

**Estrategia de onboarding de un centro nuevo en el módulo Agenda** — (desde `docs/modulo-agenda.md`)
Módulo: Agenda
Definir la configuración mínima necesaria para activar el módulo en un centro nuevo y
el comportamiento del sistema si un centro no tiene `HorarioCentro` configurado.
Diferido al diseño de la interfaz de onboarding.

---

**Compatibilidad ENI / ENS para adopción por otras administraciones** — 2025-05-13
Transversal
Si el proyecto va a ser adoptado por otros ayuntamientos, revisar el cumplimiento del
Esquema Nacional de Interoperabilidad y el Esquema Nacional de Seguridad.
Las decisiones de arquitectura actuales no deben cerrar esta puerta.

---

**Calibración del valor K en perfiles de anonimización** — 2026-05-21
Transversal / API
K=10 como valor por defecto para datos abiertos es conservador. Evaluar si perfiles
de investigación con convenio pueden usar K=5 con salvaguardas adicionales.
Requiere consulta con el Delegado de Protección de Datos.

---

**Tratamiento de colectivos protegidos en extracciones anonimizadas** — 2026-05-21
Transversal / API
Definir si los registros de ciudadanos de colectivos protegidos (VVG, PSH) se excluyen
de extracciones de Nivel 2 y 3 incluso después de anonimizar, o si la anonimización
es garantía suficiente. Requiere análisis legal y consulta con DPD.

---

**Generalización de ubicación para PSH en extracciones analíticas** — 2026-05-21
Transversal / API
Las PSH tienen coordenadas de pernocta, no dirección postal. Definir cómo se
generaliza este campo en extracciones: zona, distrito de intervención, o supresión
si la densidad es insuficiente para garantizar k-anonimato.

---

**Validación formal del proceso de k-anonimato por el DPD** — 2026-05-21
Transversal / API
Antes de la primera publicación en el portal de datos abiertos, someter el proceso
de k-anonimato a revisión formal por el Delegado de Protección de Datos.
Bloqueante para la activación del perfil datos_abiertos.

---

**Sandbox de API** — 2026-05-20
API
Entorno separado con datos ficticios para desarrollo de integraciones externas.
Diferido hasta que haya un integrador externo real que lo necesite.
Prerequisito: factories y seeders completos para todas las entidades principales.

---

# 💡 IDEAS Y FUNCIONALIDADES AMBICIOSAS

> Ideas que requieren decisión de diseño antes de poder planificarse.

---

**Chat IA para búsqueda de información en la aplicación** — 2025-05-13
Asistente conversacional integrado en la interfaz operativa que permita a los profesionales
consultar información del sistema en lenguaje natural: "¿cuántas plazas libres hay en centros
de mayores en Carabanchel?", "¿qué prestaciones tiene activas este ciudadano?".
Restricción irrenunciable: el principio 3.9 aplica por completo. La IA consulta e informa;
nunca crea, modifica ni elimina registros.
Requiere: definir el modelo de lenguaje, el alcance de datos consultables, la gestión de
permisos en las consultas IA (el asistente no puede devolver datos a los que el profesional
no tendría acceso por los canales normales), y el log de auditoría de consultas.

---

**Generación IA de cuadrantes de agenda** — (desde `docs/modulo-agenda.md`)
Componente IA para proponer cuadrantes mensuales a partir de demanda histórica.
Requiere: definir métricas de input, criterios de calidad de la propuesta, y flujo de
validación profesional antes de publicar. Diferido hasta tener datos históricos suficientes.

---

**Panel de análisis estadístico y cuadro de mandos** — 2025-05-13
Explotación de los datos del sistema para planificación basada en evidencia: indicadores
de carga asistencial por UO, evolución de prestaciones, detección de patrones de demanda.
Conexión natural con el principio de transparencia y rendición de cuentas del nuevo modelo
de servicios sociales. Diferido a fases posteriores del proyecto.

---

**Alertas automáticas por patrones anómalos de acceso** — (desde `docs/modulo-auditoria.md`)
Detección de accesos masivos, accesos reiterados fuera de horario u otros patrones sospechosos,
con alerta automática al supervisor. Diferido a fases posteriores.

---

**Integración con sistemas SIEM corporativos** — (desde `docs/modulo-auditoria.md`)
Exportación de logs de auditoría a sistemas de gestión de eventos de seguridad del Ayuntamiento.
Diferido hasta que se identifique el sistema SIEM del municipio adoptante.

---

**Notificación al ciudadano por cambio de cita** — (desde `docs/modulo-agenda.md`; confirmado en la fase Citas, 2026-09-30)
Avisar al ciudadano cuando su cita se da, se modifica o se cancela, y recordatorios. Usará solo la etiqueta pública del tipo de cita (`docs/modulo-citas.md` §11).
Diferido a la definición del módulo de comunicaciones ciudadanas (canal a determinar:
carpeta ciudadana, SMS, email).

---

**Gestión de conflictos de espacio con bloqueo efectivo** — (desde `docs/modulo-agenda.md`)
El sistema actual solo genera aviso cuando dos actividades compiten por el mismo espacio.
Evaluar si se justifica un sistema de reserva con bloqueo real. Diferido a fases posteriores.

---

**Módulo de comunicaciones con el ciudadano**
Canal de comunicación oficial bidireccional entre el sistema y el ciudadano. Prerequisito
para notificaciones de cita, avisos de prestaciones, etc. Requiere definición del canal
disponible en el municipio adoptante.

---

**Customizador de marca (logo, colores, tipografía)** — mayo 2026
⚠️ Parcialmente implementado: el logotipo configurable en sidebar ya está disponible (2026-06-15).
Pendiente: extender a color primario, color secundario y tipografía (Google Fonts) tanto en
backoffice Filament como en la superficie operativa Livewire.

---

**Alta rápida de ciudadano desde modal UC** — 2026-06-16
`Módulo: Ciudadanía / Intervención`
El enlace "Dar de alta ciudadano nuevo" en el modal UC apunta a `ciudadania.alta`
sin contexto prerellenado. Pendiente: pasar parámetros de contexto a AltaCiudadano
para prerellenar domicilio de la UC y retornar al modal tras el alta (con el
ciudadano recién creado seleccionado para confirmar su adición).

---


**Genograma** — 2026-06-18
`Módulo: Ciudadanía / Intervención`
Ver decisiones pendientes en docs/modulo-ciudadania.md sección 8.
Bloqueado hasta definir: tipo_dinamica en ciudadano_relaciones, fecha_fallecimiento
en ciudadanos, y decisión sobre nodos ligeros para personas no registradas.

---

**✅ CERRADO 2026-11-10 — ~~Añadir TipoRelacionSeeder al DatabaseSeeder raíz~~** — 2026-06-16
*2026-11-10: cerrado: `vida/database/seeders/DatabaseSeeder.php:47` llama a `TipoRelacionSeeder`.*
`Módulo: Ciudadanía`
El seeder existe (`TipoRelacionSeeder`) pero no está encadenado al DatabaseSeeder
raíz del módulo ni al global. Añadir cuando se haga el primer deploy con datos
del catálogo de relaciones.

---

~~**UI del Plan de Intervención en CiudadanoPage**~~ — 2026-06-18 → **COMPLETADO 2026-06-19**
`Módulo: Intervención`
PlanPage Livewire implementado con diagnóstico, objetivos, actuaciones, participantes, firmas y PDF.

---

**✅ CERRADO 2026-11-10 — ~~Instalar barryvdh/laravel-dompdf si no está~~** — 2026-06-18
*2026-11-10: cerrado: `"barryvdh/laravel-dompdf": "^3.1"` en `vida/composer.json:10`.*
`Infraestructura`
El servicio PlanPdfService usa dompdf. Verificar si está instalado:
`composer require barryvdh/laravel-dompdf`
Documentar en SESSION.md tras instalación.

---

**Seguimiento del plan — UI** — 2026-06-18
`Módulo: Intervención`
El modelo SeguimientoPlan ya existe (tests TF-INT-C). Pendiente: integrar el
seguimiento en la UI de CiudadanoPage, con evaluación de objetivos por estado
y programación del siguiente seguimiento.
*2026-11-10: la programación del siguiente seguimiento ya existe (casilla «Programar siguiente seguimiento» al guardar una entrevista, `ciudadano-page.blade.php:417`; crea el `SeguimientoPlan` en `CiudadanoPage.php:1135` y pide la cita). Falta la evaluación de objetivos por estado: sin cerrar.*

---

**Código de Primera Atención (PA)** — 2026-06-19
`Módulo: Atención`
Pendiente decidir si RegistroAtencion necesita un identificador visible
tipo "PA-2024-001234" para comunicar al ciudadano o para referencia interna.
Ver sección 9 de docs/modulo-atencion.md.

---

**Tipo actividad en RegistroAtencion** — 2026-06-19
`Módulo: Atención / Centro`
El tipo actividad está definido en el modelo pero sin UI ni generación
automática. Se activa al implementar el módulo Centro (inscripciones).
La relación polimórfica `origen_tipo / origen_id` está preparada.

---

**Tipo contacto en RegistroAtencion** — 2026-06-19
`Módulo: Atención`
Definido en el modelo pero sin UI específica en fase 1. Se implementará
cuando el módulo de Agenda esté operativo (llamadas de seguimiento).

---

**✅ Resuelto 2026-09-30 — Generar cita desde RegistroAtencion** — 2026-06-19
Tras registrar una atención, quien da citas ve «Dar cita» en la ficha; la cita directa (`agenda.citas.nueva?atencion=`) la guarda como `cita_generada_id`.
`Módulo: Atención / Agenda`
El campo `cita_generada_id` existe en la tabla pero el formulario de nueva
atención no permite crear la cita desde FichaCiudadanoPage aún. Se activará
cuando el módulo Agenda exponga una API de creación de citas simplificada.

---
