# CHANGELOG — VIDA 360 — Noviembre 2026

> Entradas de noviembre 2026. Para meses anteriores, ver `CHANGELOG-102026.md`.

---

## 2026-11-13 — Tipo de Centro: RN-15 en las pantallas y medición del ámbito (fase 2, paso 8 ter y 8 bis.4)

### Cambios
- `FichaCiudadanoPage::abrirHistoriaSocial()` y `guardarAtencion()`, y `AltaCiudadano::confirmarAlta()`: recogen el rechazo de RN-15 (`DomainException`) y lo muestran (`alert-danger` junto a los botones de la ficha, en el pie del modal de atención y bajo el interruptor de apertura del alta); antes acababan en un error 500. En el alta, la persona ya está dada de alta: no se redirige y se indica que se desmarque la apertura para terminar.
- `CitaDirectaPage` y `BandejaCitacionPage`: el 403 sin centro lleva el mensaje de RN-15 en lugar de «Tu unidad no tiene centro asociado». `citar()` ya recogía la `DomainException` del servicio (hereda de `LogicException`).
- Test: `EstructuraAmbitoTest::tf_tce_39_sin_centro_no_se_atiende_desde_las_pantallas` (ficha, alta y pantallas de citación con `$tecnicaArea`; no se crea historia ni atención). **En negativo** (sin los cambios de las pantallas) falla con la `DomainException`.
- `npm run build` + `ui:auditar` sin infracciones. `Modules/Ciudadania/tests`, `Modules/Agenda/tests/Feature/Citas` y `EstructuraAmbitoTest`: 183 passed.

### Medición del ámbito (8 bis.4)
En `vida_testing`, escenario de `TipoCentroTestSetup` con 60 historias; mediana de 5 rondas; «antes» es el cálculo previo de `uoSubtreeIds()` (adscripciones + `descendantsAndSelf()` por UO, sin cache).
- `MisCasosPage` (psicóloga con 60 casos): 69 ms y 4 consultas antes y después. **Esa pantalla no calcula el ámbito:** lista los casos propios sin `AmbitoUoScope`, así que no sirve para medirlo.
- `uoSubtreeIds()` + `HistoriaSocial::count()` con el scope: jefa de junta, 97 → 45 ms y 9 → 4 consultas; coordinación de igualdad, 109 → 44 ms y 9 → 4 consultas (ahora ve 60 historias por la funcional; antes, 0).

## 2026-11-13 — CI: Pint ya no borra los `@param`/`@return`

- El workflow «Calidad de código» hizo su auto-commit de Pint tras el push de `c563617` (`db260c7`, 382 ficheros) y, por la regla `no_superfluous_phpdoc_tags` del preset `laravel`, quitó los `@param`/`@return` que exige `CLAUDE.md` §2.
- Decisión del desarrollador: desactivar la regla y restaurar. `vida/pint.json` con `"no_superfluous_phpdoc_tags": false`; revertido `db260c7` y pasado `vendor/bin/pint` en local con la nueva configuración: se queda el formato del resto de reglas y vuelven 1164 líneas `@return` (ninguna se pierde). `pint --test` pasa, así que el próximo auto-commit del CI no debería cambiar nada.
- Comprobación: `php -l` en todos los PHP reformateados sin errores; `tests/Feature/Acceso`, `tests/Feature/Ui` y `Modules/Centro/tests`: 174 passed.
- Despliegue de `c563617` en verde: las ocho migraciones de la fase 2 (`2026_11_12_100001` a `100008`) aplicadas en la BD compartida (lote 31).

## 2026-11-13 — Tipo de Centro: ámbito por las dos jerarquías, centro de trabajo y RN-15 en el servidor (fase 2, pasos 8 bis y 8 ter, parcial)

### Cambios
- `App\Services\AmbitoUsuario` (servicio `scoped`, cache por petición), único punto de cálculo del ámbito (RN-13): `uoIds()` (adscripciones vigentes con sus descendientes orgánicas, más las UO de los centros activos cuyo tipo tiene la UO funcional en ese subárbol), `incluyeCentro()`, `centrosEnAmbito()` y `olvidar()`. `AppServiceProvider` lo invalida al guardar o borrar `UsuarioUo`, `UnidadOrganizativa`, `Centro` y `TipoCentro`.
- `TieneUO::uoSubtreeIds()` delega en `AmbitoUsuario::uoIds()`: así todos sus consumidores (scopes, policies, buscadores) pasan por el mismo cálculo sin tocarlos uno a uno.
- `UnidadOrganizativa::centrosDependientes('organica'|'funcional'|'ambas')` para analítica. Docblock que faltaba en `isDescendantOf()`.
- `CentroDeUsuario` reescrito (paso 8 ter): `centros()` (adscripciones directas vigentes), `centroActivo()` (sesión → perfil horario de hoy → único centro → `null`), `elegir()` (rechaza un centro ajeno), `supervisa()` (rol `supervision` y centro en el ámbito, D1.8), `adscritoA()` y `exigirAtencion()` (RN-15, con los dos mensajes de la instrucción).
- Pantallas que calculaban el centro por la primera UO activa pasan a `centroActivo()`: Supervisión (`InicioPage`, `EquipoPage`, `CuadrantePage`, `ActividadesPage`, `ConfiguracionCentroPage`, `Sidebar`) y Agenda (`CuadranteSupervisorPage`, `Sidebar` del supervisor).
- **RN-15 en el servidor:** `AperturaHistoriaService::abrir()` (la historia toma la UO del centro activo), `CitacionService::citar()`, `citarDirecto()`, `reprogramar()` y `cancelar()` (centro del slot o de la cita), y `creating` de `Apunte` y `RegistroAtencion` con usuario autenticado (centro de la cita si la hay; si no, el activo: TF-TCE-42).
- `CentroService`: `PROPIEDADES_DEL_TIPO`; el alta y el nuevo `actualizar()` rechazan las propiedades «solo tipo» (TF-TCE-47).
- Tests: `Modules/Centro/tests/Feature/TipoCentro/EstructuraAmbitoTest.php`, grupo I (TF-TCE-35 a 42 y 45 a 47). `tests/TestCase.php`: `centroParaUo()` (centro de la UO, lo crea si falta).
- Tests adaptados a RN-15 (el profesional estaba en una UO sin centro o sin UO): `CiudadanoPageTest`, `RegistrarValoracionPageTest`, `AltaCiudadanoTest`, `FichaAtencionTest` y `FichaCiudadanoPageTest`. En `AltaCiudadanoTest` el tipo del centro asigna la referencia a quien abre: sin centro el modo era `null` (= quien abre) y el test comprueba ese caso.
- Suites: Intervención, Agenda, Ciudadanía, Atención, Supervisión y `tests/Feature/Acceso` (unos 33 min): 645 passed, 1 incomplete (previo) y 23 fallos, todos la `DomainException` de RN-15 en los cinco ficheros citados. Tras adaptarlos, esos cinco ficheros y el grupo I: 99 passed (con `AltaCiudadanoTest` repetido aparte, 23 passed). `Modules/Centro/tests` y `Modules/Usuarios/tests`: 183 passed, 1 incomplete (previo).

### Decisiones
- `uoSubtreeIds()` delega en lugar de sustituirse en sus consumidores: es lo que menos toca (paso 8 bis, punto 2) y deja un solo cálculo.
- RN-15 en `Apunte` y `RegistroAtencion` va en el modelo (`creating`) y solo con usuario autenticado: los procesos del sistema (seeders, comandos, mundos demo) no atienden y no se bloquean.

### Pendiente del paso 8 ter (en SESSION y BACKLOG)
- Barra superior «Tipo · Centro» con selector (TF-TCE-43); selector de centro en las pantallas de supervisión para quien supervisa más de uno (TF-TCE-44); TF-TCE-39 llamando a las acciones Livewire; medición de rendimiento del ámbito en `MisCasos` (8 bis, punto 4).

## 2026-11-12 — Cierre de `2026_11_estabilizacion_y_privacidad.md`

- **Suite completa** (unos 61 min): 1318 passed, 12 incomplete, 1 skipped y 1 failed, el previo `AutorizacionDatosTest::servicio_historia_social_permite_actualizacion_con_permiso` (FK de `audits.ciudadano_id`, en el BACKLOG). **Sin fallos nuevos.**
- Despliegue (`deploy` de `CI/CD`) en verde para B2, B3 y B4 (`ac75415`, `e4b49a6`, `3d85806`). El job «Calidad de código» sigue en rojo por los errores de PHPStan medidos en A3, sin errores nuevos de este bloque.
- Criterios de finalización: TF-DOC-ING-01 a 08 y los de B (TF-ACC-PR-01 a 08, TF-ACC-17 a 22 con sus variantes) en verde con negativos; Supervisión en `phpunit.xml`; PHPStan termina y está medido; los ocho documentos en Markdown; **A4 aplazado a la 1.0 por el desarrollador**; CHANGELOG, BACKLOG, SESSION y `CLAUDE.md` §6 al día.

## 2026-11-12 — Documentación: addenda de Tipo de Centro incorporada

- `docs/instrucciones-cli/2026-11-tipo-centro-addenda-d1.md` (subida por el desarrollador) incorporada a `modulo-tipo-centro.md`, `2026-10-tipo-centro-implementacion.md` y `2026-10-tipo-centro-tests.md`, con la nota «Incluye la addenda D1». Se han aplicado §2–§4 (como pide §5) y también §6–§8 (D2, D4, D5), que están escritas como cambios a esos mismos documentos. RN-13 a RN-23 en el diseño; pasos 8 bis a 8 quinquies en las instrucciones; tests hasta TF-TCE-60.
- **Salvedad de RN-17** (decisión del desarrollador, 12 nov, para que la fase 3 pueda dar por hecha la fase 2): un usuario de una UO superior con rol `intervencion` y el centro de la historia en su ámbito accede a una persona protegida; solo `supervision` sigue necesitando acceso concedido. `gestionaProtegido()` y TF-TCE-60 lo recogen. `$tecnicaIgualdad` en los datos de prueba.
- Paso 0 de la fase 2 iniciado (rama A). Decisiones del desarrollador: centro 14 (CSS Entrevías) → `css_general`, centro 15 (centro de día Entrevías) → `centro_dia` y se le crea su UO instrumental, hija de la UO 4; UO funcionales: `css_general` → UO 4, `ciam` → UO 16, `centro_vg` → UO 15, los otros diez tipos → UO 3; `ciam` y `centro_vg` son tipos distintos.

## 2026-11-12 — Privacidad: búsqueda por documento en el buscador principal (B4 de estabilización)

### Cambios
- `CiudadanoIdentificador::hashDe(string $valor): string` (`sha256(strtolower(valor))`, sin cambios en la normalización) y `TIPO_NI_HSU_CM`. Lo usan el propio modelo al guardar, `MotorMatching`, `BuscadorPersonasCita`, `CitacionService::identificar()` (cita externa, quinto sitio que calculaba el hash) y el buscador. En las consultas, `trim()` antes del hash.
- `BuscarCiudadanoPage::buscarPorIdentificador()`: coincidencia exacta por hash, sin filtrar por UO, solo personas activas, máximo 50; cada resultado por `resolverResultado()` (nivel 3 sin datos para el protegido sin acceso). `doc` admite cualquier tipo; `hsu`, solo `ni_hsu_cm` (valor ya previsto en la migración de `ciudadano_identificadores`). Quitados el TODO y el aviso «no disponible todavía» de la vista, que ahora indica que solo hay coincidencias exactas.
- `BuscarCiudadanoPageTest`, TF-LW-BUS-09: docblock y nombre al día (sin identificador no hay resultados).
- Tests: `tests/Feature/Acceso/BusquedaPorDocumentoTest.php`, TF-ACC-21 (con espacios y minúsculas), 21 bis (otra UO en nivel 2; un número parcial no encuentra), 21 ter (`hsu` por tipo), 21 quater (documento anterior), 22 (protegido sin acceso: nivel 3, sin nombre, alias ni historia) y el hash de la consulta igual al guardado. **En negativo** (sin el cambio) fallan los seis, también 22.
- Suites: `tests/Feature/Acceso`, `BuscarCiudadanoPageTest`, `Modules/Ciudadania/tests` y `Modules/Agenda/tests/Feature/Citas`: 218 passed. `npm run build` + `ui:auditar` sin infracciones. PHPStan sin errores nuevos (los dos de `CitacionService` son previos y en otras líneas).

### Decisiones
- **Documentos anteriores incluidos:** a diferencia de `BuscadorPersonasCita` (solo vigentes), el buscador principal encuentra también por un documento con `fecha_fin`: un NIE antiguo sigue identificando a la persona, y la coincidencia es exacta.
- **Normalización:** no se ha cambiado (instrucción B4). Se ha visto que el alta guarda el NIE con guiones y la ficha guarda lo que se escribe, así que la búsqueda exacta depende de por dónde se guardó: al BACKLOG con la propuesta.

## 2026-11-12 — Privacidad: edición de la ficha por policy (B3 de estabilización)

### Cambios
- `FichaCiudadanoPage::guardar()` autoriza con `Gate::authorize('update', $ciudadano)` (403 si no puede); antes volvía en silencio si el rol no editaba. `puedeEditar()` (botón «Editar datos», `activarEdicion()`, alta de documentos de identidad) delega en la misma policy.
- **Corregido en `CiudadanoPolicy`:** `obtenerHistoriaPrincipal()` buscaba la historia **con** `AmbitoUoScope`. Con sesión iniciada, la historia de otra UO no aparecía y `update()`/`delete()` la tomaban por «sin historia», que permiten: cualquier usuario con `ciudadano.editar` podía editar (también por `DocumentosCiudadano` y `CiudadanoService`) o dar de baja a una persona de otra UO. Ahora se busca sin el scope. `view()` no cambia de resultado (la historia ajena ya caía en la consulta externa).
- `CiudadanoPolicy::update()`: `supervision` solo bloquea si el usuario no tiene otro rol con `ciudadano.editar` (nuevo `otroRolConcede()`).
- `docs/modulo-usuarios-permisos.md` §4.5: la regla de edición.
- Tests: `tests/Feature/Acceso/GuardarFichaPolicyTest.php`, TF-ACC-19, 20 (por `intervencion`, `tramitacion` y `consulta_basica`, con historia en su UO y sin historia), 20 bis (otra UO, no protegida: solo lectura; `update` y `delete` denegados), 20 ter (solo supervisión) y 20 quater (supervisión + intervención edita). **En negativo** (sin los cambios) fallan 19, 20 bis, 20 ter y 20 quater; el 20 pasa antes y después (sin cambio para los roles que editan en su UO). 20 bis falló también con solo el cambio de la ficha: así apareció el fallo del scope.
- Suites: `tests/Feature/Acceso`, `AutorizacionDatosTest`, `tests/Feature/Auth`, `Modules/Ciudadania/tests`, `Modules/Documentos/tests` y `Modules/Intervencion/tests`: 588 passed, 2 incomplete, 1 skipped, 1 failed (el previo `AutorizacionDatosTest::servicio_historia_social_permite_actualizacion_con_permiso`, FK de `audits.ciudadano_id`, ya en el BACKLOG). PHPStan sin errores nuevos en la policy y la ficha (quedan los dos de `$citaVinculable`, falso positivo de `#[Computed]`).

### Decisiones (del desarrollador, 2026-11-12, al detectar diferencias entre la policy y `puedeEditar()`)
- **Fuera de la UO manda la policy:** una persona no protegida de otra UO (consulta libre, nivel 2) se ve pero no se edita, igual que un protegido con acceso aprobado. Hasta ahora `intervencion`, `tramitacion` y `consulta_basica` podían editarla desde la ficha.
- **Supervisión con otro rol que edita, edita.** Solo en `update()`; `create()` y `delete()` quedan como estaban (al BACKLOG).
- Visto y llevado al BACKLOG: las relaciones de la ficha siguen decidiéndose por rol (`puedeEditarRelaciones()`).

## 2026-11-12 — Privacidad: `VerFichaPage` y la historia de la ruta (B2 de estabilización)

### Cambios
- `Ficha::perteneceAHistoria(int $historiaId): bool`: la ficha es de la historia de su `historia_id`; solo si es nulo, de la historia de su valoración (`valoracion_id`); sin ninguno de los dos, de ninguna.
- `VerFichaPage::mount()`: tras autorizar la historia por `AccesoExpediente`, `abort_unless($ficha->perteneceAHistoria(...), 404)`. La respuesta 404 no lleva nada de la ficha. Orden: primero la historia (403 y auditoría como siempre), después la ficha.
- Docblocks de `VerFichaPage` completados (`@return` de `mount()`, `nombreFicha()` y `render()`; forma del array de `camposConValor()` con `obligatorio`).
- Tests: `tests/Feature/Acceso/VerFichaHistoriaTest.php`, TF-ACC-17, 18 y 18 bis (vínculo por valoración y ficha sin vínculo). **En negativo** (sin el `abort_unless`) fallan 17 y 18 bis; 18 comprueba lo que ya funcionaba.
- Suites: `tests/Feature/Acceso` y los tests de Intervención y Mensajes que usan `Ficha` (12 ficheros): 177 passed, 1 incomplete (previo). PHPStan sin errores en los dos ficheros. Pint solo pide quitar el `@return bool` nuevo (regla `no_superfluous_phpdoc_tags`, pendiente de decisión desde A3); se mantiene por `CLAUDE.md`.

### Decisiones
- **Relación ficha → historia:** el modelo tiene dos vínculos (`historia_id`, que usan `RegistrarValoracionPage`, `PlanPage::fichasHistorial()` y `ContextoMensajeService`, y `valoracion_id`, nulo en las 11 fichas de la BD compartida y sin código que lo rellene salvo la factoría). Se ha tomado `historia_id` como vínculo principal y la valoración como respaldo solo cuando `historia_id` es nulo, en lugar de preguntar: no hay ninguna ficha con ambos ni con historias distintas, y la regla no admite una ficha ajena por ninguno de los dos caminos. Anotado en el BACKLOG para cuando se cierre el TODO del modelo.
- Numeración: se usan los ids de la instrucción (TF-ACC-17 y 18); el caso de la valoración, como 18 bis.
- Visto de paso y llevado al BACKLOG: la vista nunca muestra el autor de la ficha (`Ficha` no tiene relación `profesional`).

## 2026-11-11 — Privacidad: personas relacionadas protegidas (B1 de estabilización)

### Cambios
- Nuevo `App\Services\PresentacionPersona` (`visible()`, `paraListado()`): un único presentador para «persona en un listado». Un colectivo protegido que la policy no deja ver sale como «Persona con protección especial», sin id, enlace, contacto ni modelo; el resto, como siempre. Criterio: `CiudadanoPolicy::view()` (no se crea otro). Sin usuario (proceso sin sesión), un protegido nunca se muestra.
- **Ficha** (`FichaCiudadanoPage`): nueva propiedad calculada `personasRelacionadas` para relaciones (vigentes e historial) y convivientes. Una relación con una persona que no se puede ver no es clicable ni enlaza; `abrirModalEditarRelacion()` no la abre y `guardarRelacion()` (edición y alta) y `cerrarRelacion()` responden 403. El buscador y la selección de relación usan el presentador.
- **Expediente** (`CiudadanoPage`): `personasRelacionadas` para representante, convivientes y relaciones. El representante protegido sale sin nombre ni botón de contacto y `abrirModalRepresentante()` no abre el modal; en la UC (cabecera y modal) no hay nombre, enlace, baja ni verificación, y `iniciarBajaMiembro()`, `confirmarBajaMiembro()` y `verificarMiembro()` lo rechazan; el modal «Personas relacionadas» no muestra nombre, teléfono ni enlace (y su `wire:key` ya no lleva el id).
- **Corrección de paso:** `relacionesAgrupadas` y `ucMiembrosActivos` del expediente cargaban a la persona **con** el scope de UO: una persona de otra UO llegaba `null` (en el modal de relaciones daba error al leer `$persona->id`) y un conviviente de otra UO salía vacío. Ahora se cargan sin scope y decide el presentador, como en la ficha.
- Buscadores y bandeja que filtraban por su cuenta, pasados al presentador (cambio de una línea): `CiudadanoPage::ucResultadosBusqueda()`, `BuscadorPersonasCita::visible()`, `BuscarCiudadanoPage::resolverResultado()`, `AsignacionesPage` (aviso tras decidir) y el parcial `persona-pendiente` de la bandeja.
- `docs/modulo-ciudadania.md` §6.2: la regla. `BACKLOG.md`: cerrada la viñeta «Personas relacionadas sin filtro de protegido».
- Suites: Acceso, Ciudadanía, Supervisión, Agenda, Intervención y `DocumentosCiudadanoTest`: 657 passed, 1 incomplete (previo); el único fallo (`RelacionesUiTest`, modal del representante sin representante) se corrigió y, tras los retoques, Acceso + Livewire de Intervención: 198 passed, 1 incomplete. `ui:auditar` sin infracciones. PHPStan sin errores nuevos en el código modificado (los del test nuevo son los falsos positivos de `Livewire::test()` y `create()` ya medidos en el BACKLOG).
- Tests: `tests/Feature/Acceso/PersonasRelacionadasProtegidasTest.php`, TF-ACC-PR-01 a 08. **En negativo** (sin los cambios de la aplicación) fallan 01, 04, 05, 06 y 08; 02, 03 y 07 comprueban lo que ya funcionaba (la persona visible sigue viéndose y el presentador).

### Otros sitios que pintan personas relacionadas (punto 3)
| Sitio | Qué mostraba | Qué se ha hecho |
|---|---|---|
| PDF del plan (`PlanPdfService`, `pdf/plan.blade.php`) | Nombres de los convivientes; cargados con scope (un conviviente de otra UO daba error) | Presentador, sin scope |
| Pantalla del plan (`PlanPage::miembrosUc()`) | Nombres de los convivientes, cargados con scope | Presentador, sin scope |
| Alta de documentos (`DocumentosCiudadano::otrosMiembros()`) | Nombre del conviviente para vincularle el documento | No se ofrece el conviviente que no se puede ver; `datosAlta()` solo acepta los ofrecidos, así que tampoco se puede forzar |
| Bandeja de asignaciones | Ya ocultaba al protegido | Pasa al presentador |
| Participantes del plan y de mensajes | Profesionales, no ciudadanos | Nada |

No queda ninguno para el BACKLOG.

### Decisiones
- **Baja y verificación de convivientes** se tratan como acciones sobre la relación: con un conviviente protegido que no se puede ver no se ofrecen ni se ejecutan. La instrucción habla de «editar, cerrar, abrir su ficha»; extenderlo a la UC es lo coherente con la regla (quien no puede saber quién es no debería actuar sobre esa persona). Si la UO del titular necesita dar de baja a ese conviviente, tendrá que pedir acceso.
- `abrirModalRepresentante()` sin representante sigue abriendo el modal vacío, como antes (lo exige `RelacionesUiTest`); solo se bloquea con un representante que no se puede ver.
- El presentador es estático (lo llaman componentes, servicios y un parcial Blade) y devuelve un array, como proponía la instrucción.
- `PlanPage::miembrosUc()` se reescribe con un bucle para que PHPStan acepte el tipo (la clave del elemento `persona` cambió de `ciudadano`, que daba el modelo entero a la vista).

## 2026-11-11 — Documentación: formato Markdown de los documentos del 10 nov (A5 de estabilización)

### Cambios
- Formato Markdown, sin cambiar el texto, en `docs/modulo-citas.md`, `docs/modulo-tipo-centro.md`, `docs/roles-consulta-basica-tramitacion.md`, `docs/propuesta-requisitos-y-tareas-guiadas.md`, `docs/estado-proyecto-2026-11-10.md`, `docs/instrucciones-cli/2026-10-tipo-centro-implementacion.md`, `docs/instrucciones-cli/2026-10-tipo-centro-tests.md` y `docs/instrucciones-cli/2026-11-puesta-al-dia-documental.md`: `#` según la jerarquía, tablas Markdown en lugar de tabuladores, listas (con sublistas donde el texto las marca), código, rutas, clases, métodos, comandos y claves entre comillas invertidas, y negrita en «RN-xx — título.».
- `docs/modulo-citas.md`, RN-03 (duda 2 del 10 nov, decidida): «para el profesional de referencia del ciudadano (la asignación vigente de su historia: `AsignacionReferenciaService::vigente()`, ver `docs/modulo-asignacion.md`);».
- `docs/modulo-citas.md`: **retirado el bloque duplicado** del final (§9, §10 y §11 de la versión antigua, ya en Markdown, líneas 293–332), que llegó así en la subida del 10 nov (`e6c2a4b`). Le faltaban la línea de `modulo-ciudadania.md` y las filas de `tramitacion`, ámbito e historial de citas. Decisión del desarrollador (2026-11-11).

### Comprobación de palabras (obligatoria)
Secuencia de palabras antes y después con `tr -c '[:alnum:]áéíóúüñÁÉÍÓÚÜÑ' '\n' < f | grep .`, comparada con `diff` contra la versión de `HEAD`:

| Fichero | Palabras | Resultado |
|---|---|---|
| `modulo-citas.md` | 3.664 (sin el bloque retirado) | Idéntica salvo las 13 palabras de la precisión de RN-03 |
| `modulo-tipo-centro.md` | 2.939 | Idéntica |
| `roles-consulta-basica-tramitacion.md` | 1.507 | Idéntica |
| `propuesta-requisitos-y-tareas-guiadas.md` | 954 | Idéntica |
| `estado-proyecto-2026-11-10.md` | 2.255 | Idéntica |
| `instrucciones-cli/2026-10-tipo-centro-implementacion.md` | 2.993 | Idéntica |
| `instrucciones-cli/2026-10-tipo-centro-tests.md` | 2.143 | Idéntica |
| `instrucciones-cli/2026-11-puesta-al-dia-documental.md` | 2.397 | Idéntica |

En `modulo-citas.md` la comparación es contra las líneas 1–292 de `HEAD` (sin el bloque duplicado).

### Decisiones de formato (casos ambiguos: lo más simple)
- **Listas sin numerar** aunque el orden importe (pasos de una tarea guiada, orden de trabajo, puntos del paso 0 citados como «paso 0.3»): numerarlas añadiría palabras (los números cuentan en la comprobación).
- **Sublistas** solo donde una línea acaba en «:» y las siguientes la desarrollan (destinos de §3.3 y sección Cita de §5 en citas; plazo de documentos en roles; niveles, contextos y ejemplo en la propuesta; paso 0, `CentroPolicy` y `CentroResource` en las instrucciones de Tipo de Centro; pasos 1, 3 y 8 de la puesta al día). En `CentroPolicy` y en el último punto del paso 8 de la puesta al día, las líneas finales podrían ser hermanas y no hijas; se han dejado todas como hijas.
- **Títulos de nivel 3** para los nombres de tabla que preceden a su esquema (`tipos_centro`, `denominaciones`, `centros`…), los subapartados «Decidido», «Ideas planteadas», «Por decidir» de la propuesta y los «A.», «B.»… del estado del proyecto. «Nota de diseño», «Contexto», «Convenciones», «Seguimiento», «Antes de empezar» y «Criterios de finalización», de nivel 2.
- **Textos literales a insertar** (la línea Estado y la regla de `SKILL.md` de la puesta al día) como cita (`>`).
- **Código:** se marcan identificadores con guion bajo, rutas, ficheros, clases en CamelCase, métodos, variables, comandos y claves con punto. Las palabras sueltas que solo son código por contexto (p. ej. `activo` en un texto) se dejan como texto salvo en las tablas de campos y métodos, donde la primera columna va entera como código, y los nombres de rol (`intervencion`, `tramitacion`, `supervision`) y `basico`/`ciam`, que se marcan siempre. «PostgreSQL» no se marca.
- La conversión se ha hecho con un script de apoyo (fuera del repositorio) y revisión fichero a fichero.

## 2026-11-11 — Supervisión: tests en la suite y tres fallos corregidos (A2 de estabilización)

### Cambios
- `vida/phpunit.xml`: `Modules/Supervision/tests/Feature` en la suite `Feature` (único fichero, `tests/Feature/Livewire/SupervisionTest.php`).
- `SupervisionTest`, sin cambios en la aplicación:
  - TF-SUP-B01 `sidebar_sin_plazas_no_muestra_item_plazas`: exige que «Aprobaciones» **no** esté en el menú.
  - TF-SUP-B03/B04: pasaban en vacío (`assertSee('3')` y la ausencia de un badge que ya no existe). Ahora `contador_aprobaciones_cuenta_las_solicitudes_del_ambito` (3 en el ámbito y 1 fuera, cuenta 3) y `contador_aprobaciones_es_cero_sin_pendientes`, sobre `SupervisionSidebarDataService::aprobacionesPendientes()`.
  - TF-SUP-D07 `ficha_profesional_muestra_tres_pestanas` → `el_perfil_horario_se_abre_desde_la_fila_del_profesional_con_cuenta`: botón por fila solo con cuenta de usuario, el modal no se abre para quien no la tiene, sin «Suplencias».
  - TF-SUP-F03: registra antes, con `AuditService::registrarAcceso()`, un acceso a un ciudadano protegido de la UO; TF-SUP-F02 usa el mismo acceso para que su negativo no pase con la tabla vacía.
- `BACKLOG.md`: cerradas «Suite de tests: Supervisión no se ejecuta» y «TF-SUP-F03 falla»; la viñeta «Tipo de slot genérico en modo básico» pasa a «Citas: pendientes de la fase»; nueva «Supervisión: "Aprobaciones" fuera del menú».
- Tests: `Modules/Supervision/tests` **38 passed** (75 assertions).

### Evidencia de cada fallo
- **«Aprobaciones»:** retirada del menú a propósito el 2026-06-29 (`3eb4458`, «pantalla sin contenido operativo, gestionable desde Filament»), sin entrada en ningún CHANGELOG. Desde el 2026-09-25 la pantalla resuelve solicitudes de rol y de acceso, y se llega a ella desde Inicio y desde las alertas. **Decisión del desarrollador (2026-11-11): test desfasado, el menú se queda sin el ítem.**
- **Tres pestañas:** retiradas el 2026-06-29 (`a81f340`, «eran stubs») y sustituidas por el botón «Perfil horario» de cada fila con su modal. Test desfasado.
- **TF-SUP-F03:** la marca «Colectivo protegido» solo se pinta en las filas (`aria-label` del icono) y el test no creaba ningún acceso: la página mostraba «No hay accesos registrados». Test incompleto, como decía el BACKLOG.

### Decisiones
- En D07 se comprueba el estado del modal (`modalHorarioAbierto`, `horarioUserId`) y no su título: el modal exige un centro activo de la UO, que este test no prepara.

## 2026-11-11 — Documentos: rechazos que faltaban y causa técnica (A1 de estabilización)

### Cambios
- `IngestaDocumentoService::ingerir()`: rechaza con `fichero_vacio` un fichero de 0 bytes justo después de copiarlo a la zona temporal (antes del antivirus), y con `pdf_sin_contenido` un PDF sin tinta visible después de `normalizar()` (nuevo `rechazarSiSinContenido()`, también para los firmados).
- `SaneadorPdf::tintaTotal()`: `gs -q -dSAFER -o - -sDEVICE=inkcov`, suma C+M+Y+K de todas las páginas. Si Ghostscript falla o no devuelve ninguna página, `pdf_no_normalizable` con la causa adjunta.
- `config/documentos.php`: `ingesta.umbral_tinta_minimo` = 0,00001 (`DOCUMENTOS_UMBRAL_TINTA_MINIMO`).
- `ProcesoExternoFallidoException` (de `584d5ad`) **renombrada a `FalloHerramientaExterna`**, el nombre que fijan las instrucciones. Ahora guarda solo el nombre del binario (no su ruta) y recorta la salida de error a 2.000 caracteres (`MAX_SALIDA_ERROR`).
- `ConversorPdfLocal::desdeOfimatica()`: el fallo y el timeout de LibreOffice llevan `FalloHerramientaExterna` como previa de `conversion_fallida`.
- `DocumentosCiudadano::guardar()`: regla propia «El fichero está vacío.» sobre `fichero`, y `report($e->getPrevious())` en lugar de `report($e)` (al log va la causa técnica).
- `IngestaRechazadaException::MENSAJES`: `fichero_vacio` y `pdf_sin_contenido`.
- Fixtures nuevos (y su generación en `generar-fixtures.sh`): `en-blanco.pdf`, `tres-paginas-segunda-en-blanco.pdf`, `truncado.pdf` y `contenido-danado.pdf`.
- Tests TF-DOC-ING-01 y 08 en `DocumentosCiudadanoTest`; TF-DOC-ING-02 a 07 y el recorte de la salida de error en `IngestaDocumentoTest`. El test de qpdf de `584d5ad` se adapta (al log va la causa, no el rechazo). 03, 04 y 07 comprobados en negativo: fallan sin la medición de tinta y sin la condición de qpdf.
- `docs/modulo-documentos.md` §3: los dos motivos nuevos, la medición de tinta y el registro de la causa. `docs/decisiones-tecnicas.md` §14 «Dependencias del sistema de la custodia».
- Tests: `Modules/Documentos/tests` **100 passed** (477 assertions). PHPStan sin errores nuevos en lo modificado.

### Por dónde pasaba el fichero de 0 bytes
- **No se reproduce.** En local, `finfo` devuelve `application/x-empty` y `DetectorFormato` lo rechaza como `formato_no_admitido` (TF-DOC-ING-01 escrito antes del cambio: mostraba «Este tipo de fichero no se admite»). En la BD compartida no hay ninguna versión de 0 bytes: las cinco subidas del 11 nov son `09_informe…` (24.780 B), `02_documento_identidad…jpg`, `14_informe_derivacion….docx`, `E07_pdf_con_javascript.pdf` y `E04_pdf_corrupto_truncado.pdf` (2.669 B, el que se guardó en blanco). `E10_fichero_vacio.pdf` no llegó a guardarse; lo más probable es que se viera el error y se tomara por otro caso. El rechazo explícito se añade igualmente, con su mensaje propio.

### Umbral de tinta: mediciones
Tinta total (C+M+Y+K, todas las páginas) tras sanear con los parámetros de la tubería:

| Fichero | Páginas | Tinta |
|---|---|---|
| Documentos reales del juego (01 a 22, PDF de ReportLab) | 1–4 | 0,305 a 1,611 |
| `E05_documento_51_paginas.pdf` | 51 | 10,54 |
| `E06_escaneo_alta_resolucion.pdf` | 6 | 18,28 |
| `E07_pdf_con_javascript.pdf` | 1 | 0,054 |
| `valido.pdf` (fixture: una línea de 18 pt por página) | 2 | 0,0045 |
| Página con una sola «x» de 10 pt | 1 | 0,00002 |
| Página con un solo «.» de 10 pt | 1 | 0 |
| Página en blanco | 1 | 0 |
| `E04_pdf_corrupto_truncado.pdf` tras el saneado de entonces | 1 | 0 |

**Propuesta: 0,00001**, la resolución de inkcov (cinco decimales por canal). Rechaza lo que mide 0 (en blanco o «reparado» en blanco) y admite cualquier página con un carácter legible. Un umbral mayor no separa mejor: los casos reales están cuatro órdenes de magnitud por encima, y el riesgo de subirlo es rechazar documentos válidos con muy poco texto.

### Decisiones
- **TF-DOC-ING-03 con un PDF dañado, no truncado.** Con la regla del punto 3 (qpdf con código 2 y salida de error → `pdf_no_normalizable`), un PDF truncado sin trailer, como `E04`, ya no llega a Ghostscript: qpdf escribe «unable to find trailer dictionary» y se rechaza antes. El caso «Ghostscript lo repara y queda en blanco» solo se da con un PDF cuyas referencias están intactas y el contenido dañado (`qpdf --requires-password` no lee los flujos): `contenido-danado.pdf`. El test comprueba los dos: el dañado da `pdf_sin_contenido` y el truncado `pdf_no_normalizable`.
- **Sin `min:1` en la regla de `fichero`:** Laravel mide los ficheros en KB con decimales, así que `min:1` rechazaría todo fichero de menos de 1.024 bytes (una imagen pequeña válida) con «El fichero está vacío.». Se usa una regla propia que mira si son 0 bytes, con el mismo mensaje.
- **Comando sin la ruta del binario, con las rutas de trabajo:** los argumentos son rutas de la zona temporal con nombres neutros (`original`, `saneado.pdf`) y sirven para diagnosticar AppArmor; no llevan datos personales.
- **Coste de la medición:** `inkcov` rasteriza todas las páginas. En esta máquina (1 CPU) añade 6,5 s al PDF de 51 páginas y unos 14 s al escaneo de alta resolución de 6 páginas. Se deja la resolución por defecto (72 ppp); a 36 ppp el escaneo baja a 6,7 s y la «x» se sigue detectando. Anotado en el BACKLOG.
- Pint no se aplica: sus reglas pendientes de decisión (A3) borrarían docblocks.

## 2026-11-11 — CI «Calidad de código»: PHPStan medido (A3 de estabilización)

### Cambios
- `vida/composer.json`: `analyse-ci` con `--memory-limit=2G` (antes 512M).
- `ResolucionCentroService::seccionesSinCobertura()` declara y devuelve siempre `Illuminate\Database\Eloquent\Collection` (antes `Support\Collection` y `collect()` en el caso «ciudad completa»). Sus dos llamadores (`ComprobarCoberturaCommand` y `ListCentros`) llaman a `->load()`, que solo existe en la colección de Eloquent; hoy no fallaba porque salen antes si está vacía. Sin cambio de comportamiento, sin test nuevo: `AmbitoCoberturaTest` 5 passed.
- `BACKLOG.md`: entrada de CI reescrita con el recuento por identificador (281 errores en local; 287 en CI sobre `584d5ad`), la propuesta para el falso positivo de `Livewire::test()` y el conflicto de Pint con los docblocks.

### Decisiones
- **En CI PHPStan ya terminaba** con 512M (1 min 36 s, 287 errores): la falta de memoria que citaba el BACKLOG no se reproduce. Se sube igualmente a 2G, como piden las instrucciones. En la máquina de desarrollo (1 CPU, 1 GB) el modo paralelo se queda parado; se ha medido por módulo con `--debug`.
- **Solo un fallo real con corrección local evidente** (el de `seccionesSinCobertura()`). El resto son falsos positivos de Livewire y de Larastan, anotaciones de tipos o `?->` innecesarios, que se quedan medidos en el BACKLOG. No se toca el baseline.
- **`argument.templateType` sin solución estándar:** Livewire 4.4.8 es la última versión y su docblock de `test()` impide inferir el componente; no hay extensión oficial de Larastan para Livewire. Propuesta en el BACKLOG.
- **Pint solo sobre el fichero modificado.** Su única corrección aplicable era `new EloquentCollection` sin paréntesis; `no_superfluous_phpdoc_tags` se deja sin aplicar porque borraría los `@param`/`@return` exigidos por `CLAUDE.md`. El auto-commit de Pint del workflow no commitea nada por la expansión del patrón `vida/**/*.php`; se documenta en el BACKLOG en lugar de arreglarlo, porque arreglarlo borraría docblocks en todo el proyecto.

## 2026-11-11 — Documentos: fallos de qpdf y Ghostscript con causa y registro

### Cambios
- `SaneadorPdf::rechazarSiProtegido()`: el código 2 de `qpdf --requires-password` solo cuenta como «sin cifrar» si la salida de error está vacía. Con salida de error, el PDF se rechaza con `pdf_no_normalizable`.
- Nueva `Modules/Documentos/app/Exceptions/ProcesoExternoFallidoException` (comando, código de salida y salida de error; código `null` si el proceso no terminó). Todos los rechazos por fallo de proceso de `SaneadorPdf` (protección, conteo de páginas, saneado, recompresión, expansión con qpdf y timeout) la llevan como excepción previa de `IngestaRechazadaException`.
- `DocumentosCiudadano::guardar()`: registra con `report()` los rechazos de la ingesta que tienen excepción previa (causa técnica). Los que no la tienen (formato, virus, páginas…) siguen sin ir al log.
- Tests en `DocumentosCiudadanoTest`: «un fallo de qpdf con salida de error se rechaza y queda en el log» (binario falso que devuelve 2 y escribe en la salida de error; falla con la condición anterior) y «un rechazo sin causa técnica no se registra en el log».
- `docs/modulo-documentos.md` §3 y `BACKLOG.md`: reglas locales de AppArmor para qpdf y gs sobre `storage/app/tmp` en el servidor, que hay que revisar tras cada actualización del sistema.
- Máquina de desarrollo: reglas locales de AppArmor ampliadas (sudo, por el desarrollador) con `/home/jupiter/code/vida-project/vida/storage/framework/testing/** rwk,` y `/home/jupiter/code/vida-project/vida/storage/app/tmp/** rwk,` en `/etc/apparmor.d/local/qpdf` y `/etc/apparmor.d/local/gs`, y perfiles recargados con `apparmor_parser -r`.
- Tests: `Modules/Documentos/tests` **91 passed** (445 assertions). Antes de ampliar las reglas: 64 fallos, todos por `Permission denied` de qpdf/gs; sin el cambio de código, `IngestaDocumentoTest` ya fallaba (7) por la misma causa.

### Decisiones
- **Los rechazos sin causa técnica no se registran,** para no llenar el log con errores del usuario. El criterio es si la `IngestaRechazadaException` tiene excepción previa (el antivirus no disponible también la tiene y también se registra).
- **El test llama a `guardar()` sobre la instancia del componente**, no con `->call()`: `Livewire::test()` sustituye el manejador de excepciones por uno que no registra nada mientras atiende cada llamada, así que `report()` no llegaría al log.
- **Se descubre un fallo oculto en la máquina de desarrollo:** el perfil AppArmor de qpdf le impide leer `storage/framework/testing/ingesta/{uuid}/original`, la zona temporal de los tests, que no tiene extensión y que las reglas locales no cubren. Antes se aceptaba como «sin cifrar» sin que se notara. El desarrollador ha añadido a mano (sudo) las rutas de tests y de la zona temporal local a las reglas; `DocumentosTest` usa `storage/app/tmp`, no la zona de tests, así que hacen falta las dos.

## 2026-11-11 — Usuarios / UI: nombre visible del usuario y tests previos reparados

### Cambios
- `User::nombreVisible()`: nombre completo del Profesional vinculado; si no hay Profesional (perfiles técnicos), el email.
- Vistas que mostraban `User::name` (que siempre es el email) o montaban el nombre a mano pasan a `nombreVisible()`: topbar y menú de usuario de `operativo-shell`, componente `x-avatar` (las iniciales salen de `nombreVisible()` si no se pasa `nombre`), portada `inicio`, onboarding y `Modules/Agenda/.../cuadrante-mes.blade.php`.
- `tests/Feature/Auth/AutenticacionTest.php`: el usuario de prueba se vincula a un Profesional «Juana López García» en lugar de fijar `name`. Nuevo TF-AUTH-16b: sin Profesional la UI muestra el email.
- `FichaAtencionTest` (TF-LW-AT-04) y `FichaCiudadanoPageTest`: el texto esperado pasa de «Ver historia social» a «Ir a HS», que es lo que muestra la ficha.
- Tests: `AutenticacionTest`, `FichaAtencionTest` y `FichaCiudadanoPageTest`: 51 passed, 1 incomplete (TF-AUTH-20) y 1 skipped (TF-AUTH-04), los dos previos. `CuadranteMesTest`: 5 passed. `ui:auditar` sin infracciones tras `npm run build`.

### Decisiones
- **No se toca `User::booted()`.** La corrección propuesta en el BACKLOG (rellenar `name` solo si viene vacío) se descarta: `name` no se expone en formularios y la identidad del usuario es la del Profesional. Los tests TF-AUTH-16/17 se adaptan a ese modelo.
- Los dos fallos de «Ver historia social» eran de los tests, no de la vista: el enlace se renombró a «Ir a HS» y los tests no se actualizaron.

---

## 2026-11-11 — Dependencias y CI: Symfony fijado a 7.4, PHP 8.5 y despliegue reparado

### Cambios
- `composer.json`: `symfony/event-dispatcher`, `symfony/html-sanitizer` y `symfony/string` fijados a `^7.4`, seguido de `composer update --lock`. Las versiones instaladas no cambian (7.4.17, 7.4.23 y 7.4.22). En `composer.lock` solo cambian el `content-hash` y la URL del repositorio de `vlucas/phpdotenv`, que ahora es `phpdotenv/phpdotenv` con la misma referencia. `composer audit`: sin avisos. Motivo y revisión pendiente en el BACKLOG.
- `.github/workflows/` (`ci.yml`, `quality.yml`, `security.yml`, `phpdoc.yml`): `php-version: '8.5'`.
- `ci.yml`, despliegue: `sudo systemctl restart php8.5-fpm`. Además, el script carga nvm (`NVM_DIR` y `nvm.sh`) antes de `npm`.
- Sudoers del servidor, cambiado por el desarrollador: las reglas NOPASSWD de `systemctl restart|reload php8.3-fpm` pasan a `php8.5-fpm`.
- Tests (`tests/Feature/Ui`, `tests/Feature/Auth`, `tests/Feature/Acceso`, `Modules/Ciudadania/tests`, `Modules/Agenda/tests/Feature/Citas`): 244 passed, 1 incomplete, 1 skipped y los mismos 4 fallos previos (TF-AUTH-16/17 y los dos de «Ver historia social»).

### Decisiones
- **PHP 8.5, no 8.4.** El encargo pedía 8.4, pero `systemctl list-units 'php*-fpm*'` en el servidor mostró `php8.5-fpm` activo, `php8.3-fpm` como `not-found` y ningún `php8.4-fpm`. En el servidor solo está instalado `/usr/bin/php8.5`. El desarrollador eligió 8.5 en todo, para que CI pruebe con la misma versión que ejecuta el servidor.
- **Los despliegues fallaban desde el 2026-11-10** (9 ejecuciones seguidas) con `npm: command not found` (exit 127). npm está instalado con nvm, que solo se carga desde `.bashrc` en sesiones interactivas. Por eso esas ejecuciones no llegaron a `migrate`, a los `*:cache` ni al reinicio del FPM. Sí se aplicaron `git reset` y `composer install`. El reinicio a `php8.3-fpm` también habría fallado, porque el servicio ya no existe.

### Verificación del despliegue
Commit `1e74c8d`, ejecución de CI/CD `42988736089`: `test` y `deploy` en verde. El job `deploy` terminó a las 08:43:53 UTC.
- Log del job: `npm ci` y `npm run build` sin errores (`✓ built in 1m 59s`), con nvm cargado. `migrate --force` da «Nothing to migrate». El script termina en `sudo systemctl restart php8.5-fpm` sin errores.
- Servidor: `systemctl show php8.5-fpm` muestra `ActiveState=active`, `SubState=running` y `ActiveEnterTimestamp=2026-11-11 08:43:51 UTC`, dos segundos antes del fin del job. Eso confirma que el reinicio fue del servicio correcto.
- `/var/www/vida-project/vida` está en `1e74c8d`.

---

## 2026-11-10 — Dependencias: parches de seguridad de Livewire y Symfony

El hook de pre-commit bloqueaba todo commit: `composer audit` y `local-php-security-checker` daban 13 avisos en 7 paquetes, publicados entre el 23 de octubre y principios de noviembre (Livewire: bypass de `#[Authorize]`; Symfony `html-sanitizer`, `http-foundation`, `http-kernel`, `mime`, `routing` y `yaml`, uno de severidad alta). Autorizado por el desarrollador.

### Cambios
- `composer update livewire/livewire symfony/html-sanitizer symfony/http-foundation symfony/http-kernel symfony/mime symfony/routing symfony/yaml --with-dependencies`: Livewire 4.4.6 → 4.4.8; Symfony 7.4.x a sus últimos parches (`http-foundation`, `http-kernel`, `routing`, `yaml` y `console` 7.4.23; `mime`, `error-handler`, `var-dumper` y `string` 7.4.22; `html-sanitizer` 7.4.13 → 7.4.23); contratos de Symfony 3.8.1, polyfills 1.43.3, `league/uri` 7.10.0, `laravel/prompts` 0.3.27, `masterminds/html5` 2.12.0. Solo cambia `composer.lock`.
- `composer audit`: sin avisos.
- Tests (`tests/Feature/Ui`, `tests/Feature/Auth`, `tests/Feature/Acceso`, `Modules/Ciudadania/tests`, `Modules/Agenda/tests/Feature/Citas`): 244 passed, 1 incomplete, 1 skipped y 4 fallos, todos previos y conocidos (TF-AUTH-16/17 y los dos de Ciudadanía que buscan «Ver historia social»).

### Decisiones
- La primera resolución subía `symfony/event-dispatcher`, `html-sanitizer` y `string` a la 8.1 (las restricciones de Laravel lo permiten). Se mantienen en 7.4, como el resto de Symfony, con `--with` (restricción temporal; `composer.json` no cambia): un salto de versión mayor no hacía falta para los parches. Ver BACKLOG.

---

## 2026-11-10 — Documentación: puesta al día (noviembre 2026)

Encargo `docs/instrucciones-cli/2026-11-puesta-al-dia-documental.md`. Solo documentación: ningún cambio de código, migraciones, seeders, tests ni configuración. No se han lanzado tests, migraciones ni `demo:*`. Commit de partida: `0b97bf4` (2026-10-02).

### Ficheros incorporados (paso 0)
Subidos por el desarrollador desde el proyecto de Claude «VIDA 360»:
- `docs/modulo-citas.md` (sustituye al anterior), `docs/roles-consulta-basica-tramitacion.md`, `docs/modulo-tipo-centro.md`, `docs/propuesta-requisitos-y-tareas-guiadas.md`, `docs/estado-proyecto-2026-11-10.md`.
- `docs/instrucciones-cli/2026-10-tipo-centro-implementacion.md`, `docs/instrucciones-cli/2026-10-tipo-centro-tests.md` (llegó como `2026-10-centro-tests.md` y se renombró a petición del desarrollador) y `docs/instrucciones-cli/2026-11-puesta-al-dia-documental.md`.

### Paso 1 — `modulo-citas.md`: revisión del 30 sep por la tarde
**Novedades respecto a la versión implementada** (comparación con `0b97bf4`, sin contar el formato):
- Cabecera: los permisos de `tramitacion` están definidos y su implantación es diferida; nuevo documento relacionado, `docs/roles-consulta-basica-tramitacion.md`.
- `tramitacion` se añade junto a `consulta_basica` en el canal interno (§1), la etiqueta pública (§2.1), la tabla cita/evento (§2.6), los avisos de solicitud creada (§3.1) y la bandeja de citación (§6).
- **RN-05 ter** (nueva): la gestión de citas se ejerce en la UO del usuario; `consulta_basica` y `tramitacion` dan, reprograman y cancelan solo en slots de centros de su UO; el historial de citas se consulta de cualquier ciudadano.
- §2.1: la etiqueta pública protege el contenido de la intervención, no el tipo de centro («Que una cita sea de un CIAM no es por sí mismo un dato sensible: el centro se muestra siempre»).
- §3.2: búsqueda y ficha disponibles aunque la persona no tenga Historia Social en la UO de quien atiende; la cita solo en un centro de esa UO.
- **§6 bis** (nueva): historial de citas en la ficha del ciudadano para `consulta_basica` y `tramitacion`.
- §7: columna `tramitacion` (⏸), «en su UO» en dar, reprogramar y cancelar, y fila «Ver historial de citas del ciudadano».
- §9: impacto en `modulo-ciudadania.md` (bloque de historial de citas).
- §10: tres decisiones nuevas («tramitacion y citas», «Ámbito de la gestión de citas», «Historial de citas en la ficha»).
- Se pierde la precisión de RN sobre la referencia («la asignación vigente de su historia (`AsignacionReferenciaService::vigente()`)»), que la versión del repo había añadido al implementar: ver dudas.

**Verificación en el código:**
- **RN-05 ter: implementada.** `vida/Modules/Agenda/app/Policies/AlcanceCentro.php:21-26` (`incluye()`: el centro es de una UO activa del usuario); `CitaPolicy.php:55-58` (`citar`), `:152-160` (`gestionaAjena`, que usan `reprogramar` y `cancelar`), `:179-181`; `SolicitudCitaPolicy.php:37-39` (`verBandeja`) y `:49-51` (`gestionar`). `Services/Citas/CitacionService.php` autoriza con ellas en las líneas 71-72, 133, 160-161 y 216.
- **§6 bis: no implementado.** `vida/Modules/Ciudadania/app/Http/Livewire/FichaCiudadanoPage.php` solo usa citas para vincular el registro de atención (`citaVinculable()`, línea 1069) y para el botón «Dar cita» (`puedeDarCita()`, línea 1081); la vista (`ficha-ciudadano-page.blade.php`) no tiene bloque de historial. No existe el permiso `cita.ver_historial`.
- **`tramitacion` en citas:** `Modules/Agenda/database/migrations/2026_09_30_120006_add_permisos_citas.php:25-29` no le da permisos `citas.*` (coherente con la implantación diferida).
- Línea Estado reescrita; entrada «Citas: delta de la revisión del 30 sep» en BACKLOG.

### Documentos modificados
- `docs/modulo-citas.md`: línea Estado (implementado sobre la revisión anterior; pendiente §6 bis y `cita.ver_historial`; RN-05 ter ya cumplida).
- `docs/modulo-agenda.md`: línea Estado con las pantallas que existen (recursos de Filament, supervisión, agenda del profesional en Intervención, citación) y lo que falta. §8 no lista tests bloqueados (45 de 45).
- `docs/modulo-asignacion.md`: nota al principio de §8 (la configuración pasa al tipo con Tipo de Centro).
- `docs/modulo-tipo-centro.md` §11: la asignación está implementada; rama A.
- `docs/modulo-auditoria.md`: «Pendiente de implementación» → implementado el 2026-06-14 (`CHANGELOG-062026.md`, «Módulo Auditoría — implementación completa») y lectura de expedientes el 2026-10-02.
- `docs/modulo-escala.md`: «Pendiente de implementación» → fase 1 implementada el 2026-05-26 (`CHANGELOG-052026.md`, «Módulo Escalas fase 1»); pendiente la fase 2.
- `docs/modulo-usuarios-permisos.md`: ROL 5 marcado como implantación diferida, con citas según `modulo-citas.md` §7; ROL 7 con capacidades resumidas de §3.4 del documento de roles y ámbito «limita la operación, no la consulta»; §4.4 con `historia.ver_estado`, `cita.ver_historial` y `documento.gestionar_propios` (no implementados).
- `docs/modulo-ciudadania.md`: Capa 1 accesible sin ámbito de UO para `consulta_basica` y `tramitacion`; relaciones editables también por `consulta_basica`; `consulta_basica` puede dar altas (hoy no tiene `ciudadano.crear`); banner de protegido solo para `intervencion` ante datos sensibles (en §6.2); bloques de `FichaCiudadanoPage` por rol (en §7.4). Todo marcado como diseño cerrado sin implementar.
- `docs/modulo-documentos.md`: §1, excepción de `consulta_basica` (solo sus documentos del día); §2.1, lo que falta en la custodia v2 (retirada lógica con quién/cuándo/motivo y vínculo con una solicitud; versiones y vínculos ya existen).
- `docs/design-system/SKILL.md` y `README.md`: el banner de protegido solo se muestra a `intervencion` cuando intenta abrir datos sensibles.
- `docs/decisiones-pendientes.md`: decisiones resueltas retiradas, sección «Decisiones pendientes registradas en otros documentos», totales (34) y fecha.
- `docs/estado-proyecto-2026-11-10.md`: sección «Seguimiento» al final (sin tocar el resto).
- `CLAUDE.md`: §6 con los dos ficheros de Tipo de Centro y este encargo; §5 con el documento de roles y el informe de estado.
- `BACKLOG.md`: ver abajo.

### Decisiones pendientes dadas por resueltas
- Resuelto el 2026-06-29 / 2026-09-30: **A-06** (interfaz Livewire/Filament de Agenda) — `CHANGELOG-062026.md` «2026-06-29 — Agenda: UI de supervisión» y `CHANGELOG-092026.md` «2026-09-30 — Citas (Agenda): implementación completa» (la agenda del profesional sustituye la fixture por citas reales).
- Resuelto el 2026-06-09: **CIU-01** (tabla y modelo `CiudadanoIdentificador`) — `CHANGELOG-062026.md` «Alta de ciudadano — Módulo Ciudadanía»; migración `2026_06_09_100002_create_ciudadano_identificadores_table.php`.
- Resuelto el 2026-06-16/17: **CIU-02** (tablas y modelo de la unidad de convivencia) — migración `2026_06_16_000001_create_unidades_convivencia_tables.php`; `CHANGELOG-062026.md` «Relaciones entre ciudadanos + UC solo lectura en FichaCiudadanoPage».
- Resuelto el 2026-05-21: **C-01** (entidad Servicio) — ya marcado como resuelto en el propio fichero («Módulo Centro — Entidad Servicio Fase 2»); se retira.

### BACKLOG
**Cerradas, con evidencia en cada entrada:** «Suite de `Modules/Agenda` rota» (paso 0 de citas; Agenda 173 passed), «Implementar servicios pendientes del módulo Agenda» (todos los servicios existen; ningún `markTestIncomplete`), «Integración real de auditoría en BuscarCiudadanoPage (nivel 2)» (`registrarAccesoNivel2()` con `AccesoExpediente`), «Tabla `ciudadano_identificadores`» (existe; la búsqueda por documento sigue en «Acceso a expedientes: pendientes»), «UC en UI de intervención» (gestión de la UC en `CiudadanoPage`; la ficha enlaza a cada miembro), «Instalar dompdf» (`composer.json:10`), «Añadir TipoRelacionSeeder al DatabaseSeeder» (`DatabaseSeeder.php:47`) y «[Demo] Citas en escenarios de demo» (duplicada de «Citas: pendientes de la fase»).

**Actualizadas sin cerrar:** «TODOs activos en CiudadanoPage» (DNI resuelto; `centroActivo()`, menú ⋯ y `statPrestaciones()` siguen), «Suite completa con 75 fallos» (solo quedan TF-AUTH-16/17, `AutorizacionDatosTest` y Ciudadanía «Ver historia social»), «Seguimiento del plan — UI» (falta la evaluación de objetivos por estado), «No existe asociación tipo de plan ↔ centro» (resuelto en diseño por Tipo de Centro), «La interfaz no permite crear planes especializados» (Tipo de Centro no lo resuelve; prioritario), «Asignación de centro y referencia: pendientes» (aviso del paso 3 de Tipo de Centro).

**Fundidas:** las dos «CI «Calidad de código» en rojo» (2026-09-29 y 2026-10-01).

**Nuevas:** «Citas: delta de la revisión del 30 sep», «`consulta_basica`: diseño cerrado sin instrucciones de implementación», «Tipo de Centro: listo para ejecutar», «Requisitos de acción y tareas guiadas — en análisis».

### Decisiones de implementación
- La línea de estado de `modulo-auditoria.md` y `modulo-escala.md` se corrige por evidencia inequívoca en el CHANGELOG (paso 2, «resto de documentos»).
- En `modulo-ciudadania.md` no hay un apartado que describa `FichaCiudadanoPage`: los bloques por rol van como elemento de §7.4 (referencias de código), y el banner de protegido al final de §6.2, aclarando que vale para todos los colectivos protegidos.

### Dudas (contradicciones no previstas en las instrucciones, sin resolver)
1. *(Resuelta el 2026-11-11, A5.)* **Los ficheros incorporados han llegado sin formato Markdown** (`modulo-citas.md`, `modulo-tipo-centro.md`, `roles-consulta-basica-tramitacion.md`, `propuesta-requisitos-y-tareas-guiadas.md`, `estado-proyecto-2026-11-10.md` y las tres instrucciones nuevas): sin `#` en los títulos, tablas separadas por tabuladores, sin código entre comillas invertidas. Parecen copiados como texto plano. No se han reformateado (las instrucciones prohíben mejorar la redacción). Conviene volver a subirlos en Markdown o autorizar la conversión.
2. *(Resuelta el 2026-11-11, A5.)* **`modulo-citas.md` pierde una precisión de la versión del repo:** la regla del profesional de referencia decía «la asignación vigente de su historia (`AsignacionReferenciaService::vigente()`, ver `docs/modulo-asignacion.md`)», añadido al implementar; la versión nueva dice solo «para el profesional de referencia del ciudadano». ¿Se repone?
3. **`modulo-documentos.md` §1 no contiene literalmente** la frase «para cualquier profesional con acceso al expediente» que citan las instrucciones; se ha añadido una frase con la disponibilidad general y la excepción de `consulta_basica`.
4. **`docs/decisiones-pendientes.md`, indicios sin constancia:** A-02 (la agenda del profesional ya existe, con «Pedir cambio», pero no consta qué ve del cuadrante de sus compañeros), CIU-03 (el centro se ve en el bloque «Centro y referencia» de la ficha, pero no en la cabecera del expediente) y T-01 (existe `MotorMatching`, pero no consta la estrategia para cambios de documento, nombre o sexo). Se dejan.
5. **`modulo-intervencion.md:793`** dice «Implementado (fase 1 — modelos, policies, factories, tests funcionales)» y no menciona la interfaz, que existe desde junio. No contradice el CHANGELOG, pero se queda corto; no se ha tocado.

---
