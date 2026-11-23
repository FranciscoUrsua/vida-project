# CHANGELOG — VIDA 360 — Noviembre 2026

> Entradas de noviembre 2026. Para meses anteriores, ver `CHANGELOG-102026.md`.

---

## 2026-11-23 — Correcciones de la ronda 1 de staging (pasos 0, 2, 3, 4, 5, 7 y 8)

Instrucciones: `docs/instrucciones-cli/2026-11-correcciones-staging-ronda1.md`. Decisiones R1–R3 del Delegado Digital (23 nov). **Pasos 1 y 6 en espera** del diseño de superficies con varios roles (decisión del desarrollador).

- **Paso 0 (diagnóstico):** sin la suite completa (decisión del desarrollador). EST-02: `consulta_profesional` no tiene superficie y `/` se queda en «Redirigiendo…». VEN-02: el buscador enlazaba a la Historia Social (`role:intervencion`) a `consulta_basica`. EST-04 (19 nov): la valoración guardaba la ficha antes del apunte sin transacción (ficha 14 huérfana, no borrada). EST-09: era de datos, `tec.ciam` sin adscripción; el desarrollador la puso en la UO 16. `APP_DEBUG=true` en staging, sin cambiar.
- **Paso 2 (ventanilla, R-02):** el buscador decide la acción de cada resultado por las policies (`BuscarCiudadanoPage::accionDe()`): Historia Social si `HistoriaSocialPolicy::view`, si no la ficha (`CiudadanoPolicy::view`), y «Solicitar acceso» para protegidas. Nuevo `App\Services\EnlacePersona` para los demás enlaces: el alta con persona existente (que además pasaba el id del ciudadano como id de historia) y la apertura de historia desde la ficha (tramitación se queda en la ficha). No se han tocado rutas.
- **Paso 3 (errores, R-03):** vistas propias 403, 404, 419, 429, 500 y 503 (`errors/pantalla.blade.php`): en la superficie del usuario para los 4xx con sesión y en un marco mínimo para los 5xx, sin sesión o en peticiones Livewire; nunca muestran el mensaje de la excepción (el 403 de Spatie salía en inglés). Identificador por petición (`IdentificadorError`, `ERR-XXXXXXXX`) en la pantalla y en el log (contexto de las excepciones y una línea por pantalla 4xx). Las `DomainException` de acciones Livewire o Filament se avisan con su mensaje sin perder el formulario (`AvisoExcepcionDominio`, enganchado a `Livewire\on('exception')`). Contenedor de toasts en el layout operativo (`<x-avisos-toast>`): los cinco `dispatch('toast')` que ya había no tenían quien los pintara.
- **Paso 4 (R1, RN-15 en toda actuación):** `CentroDeUsuario::puedeActuar()`, `exigirActuacion()`, `exigirActuacionAlAbrir()` (403 `ActuacionNoPermitida`, que sí muestra su mensaje), `sinCentro()` y `motivoSinActuacion()`. Comprobado al abrir y al guardar en entrevista, anotación, gestión, valoración, escala, solicitud de cita, prescripción, solicitud de prestación (también en el servicio), derivación (también en el servicio), informes (asistente, historial y servicio), subida de documentos, nueva atención y planes (crear, editar, seguimiento, activar y cerrar, por `PlanPage::exigirEdicion()`). Valoración y escala, en transacción. Modo lectura sin centro en la Historia Social y la ficha; el plan se ve en lectura a quien no puede editarlo. Las pantallas de citación pasan a `ActuacionNoPermitida`.
- **Paso 5 (R-05):** sin cambios de código: el buscador ya usa la policy (RN-17 con su salvedad). Tests.
- **Paso 7 (R3):** `SalidaCentroBloqueada::enlaces(User)` solo ofrece reparto y ausencias a quien supervisa un centro afectado. A los demás, la notificación nombra la dirección de cada centro y ofrece «Avisar a la dirección del centro» (`AvisoDireccionSalida`, trait `AvisaDireccionDelCentro` en las páginas de usuarios y profesionales de Filament). Vale también para el cierre de adscripción.
- **Paso 8 (R-08):** no reproducido: `ts1.css` tiene `documento.ver_carpeta`. La regla ya era la de varios roles (los permisos se suman); fijada con tests.
- **Decisiones de implementación:** (1) con varios centros y ninguno activo, el mensaje de RN-15 pide elegir el centro en la barra superior; (2) los 5xx no usan el shell operativo, para que un fallo de BD no impida pintar el error; (3) la solicitud de cita cuenta como actuación (se oculta en modo lectura); (4) `RegistrarEscalaPage` no autorizaba la historia al abrirse: ahora pasa por `AccesoExpediente`, como la valoración; (5) en la subida de documentos, primero el permiso (403) y después RN-15 (aviso).
- **Tests:** TF-STG-05 a 18 y 22 a 24 (los 19 a 21 son del paso 6), en `tests/Feature/Errores/PantallasErrorTest.php`, `Modules/Intervencion/tests/Feature/Circuito/UsuarioSinCentroTest.php`, `Modules/Intervencion/tests/Feature/Livewire/BuscadorPorPolicyTest.php`, `Modules/Centro/tests/Feature/TipoCentro/DesactivarDesdeAdministracionTest.php` y `Modules/Documentos/tests/Feature/CarpetaVariosRolesTest.php`. Adaptados a R1: `PlanPageTest`, `CierrePlanTest` y `DocumentosTestSetup` (usuarios con centro), TF-TCE-39 (rechazo al abrir) y TF-TCE-56/58 (enlaces por usuario).

---

## 2026-11-18 — Personas de prueba PRUEBA y agenda del CSS Entrevías (datos de staging)

- **Petición del desarrollador** para la batería de pruebas. Creadas en la BD compartida por los modelos y servicios de la aplicación (alta como `AltaCiudadano`, `AperturaHistoriaService`, `CitacionService::citarDirecto`, `AtencionCitaService`), todas con primer apellido PRUEBA:
  - **Ana PRUEBA García** (1066): historia 959 en el CSS Entrevías (UO 14), referencia `ts1.css`. Citas con `ts1.css`: 5 nov (completada por un apunte), 12 nov (incomparecencia), 25 nov y 10 dic (confirmadas).
  - **Marta PRUEBA López** (1067): `es_vvg` y `colectivo_extra_protegido` (RN-17), alta por la excepción VVG (sin padrón), historia 960 en el CIAM (UO 13), referencia `ts1.ciam`.
  - **Familia en el CSS**: Laura PRUEBA Martín (1068, historia 961, referencia `ts1.css`), Carlos PRUEBA Ruiz (1069) y Hugo PRUEBA Martín (1070, nacido en 2017); Hugo hijo de los dos, Laura y Carlos cónyuges (recíprocas por el modelo); unidad de convivencia 3 con los tres. Solo Laura tiene historia (decisión de implementación).
  - **Jorge PRUEBA Sánchez** (1071): sin historia.
- **Agenda del CSS Entrevías (incidencia):** los cuadrantes de noviembre y diciembre (3 y 4) se publicaron el 16 nov sin líneas ni huecos, porque el personal del centro tiene perfil horario desde el 17 nov (alta tras la limpieza de usuarios). Los cuatro perfiles por defecto (pendientes de verificar) pasan a vigentes desde el 2 nov; los dos cuadrantes vuelven a borrador y se regeneran y publican con `CuadranteGeneratorService` y `CuadrantePublicadorService` (como supervisor, `dir.css`): 84 y 92 líneas, 3.360 y 3.680 huecos. Copia previa de perfiles y cuadrantes en `~/backups/vida-20261118-antes-personas-prueba.dump`.
- Las citas pasadas se crearon hoy sobre huecos pasados: su historial (`cita_eventos`) lleva la fecha de hoy.

---

## 2026-11-18 — Informes: las plantillas no se ofrecían en el CIAM (datos de staging)

- **Causa:** las dos plantillas («Certificado vulnerabilidad» e «Informe psicológico») estaban en la UO 3 (Dirección General de Servicios Sociales). El asistente ofrece las de la UO del profesional y sus ancestros, como dice `modulo-documentos.md`; el CIAM Puente de Vallecas (UO 13) cuelga de Igualdad (13 → 16 → 15 → 2), no de la UO 3. El código es correcto.
- **Cambio (solo datos, BD compartida):** las dos pasan a la UO 2 (Área de Gobierno de Políticas Sociales, Familia e Igualdad), el ancestro común del CSS y del CIAM, por el modelo (queda versión). Antes estaban en la UO 3. Comprobado con la regla del asistente para `dir.ciam` (UO 13) y `dir.css` (UO 14): ambos ven las dos. La edición de plantillas va por rol (`adm_sistema`, `adm_usuarios`), así que el cambio de UO no la afecta.

---

## 2026-11-18 — Prestaciones: catálogo de staging sustituido por las 113 fichas de la Guía 2026

- **Petición del desarrollador**, para la batería de pruebas en staging: completar el catálogo con `docs/referencia/Guiaprestaciones2026.pdf` sin duplicar. Al comprobarlo, las 49 prestaciones de la BD (las del `PrestacionesSeeder`) no eran las de la guía: nombres genéricos, códigos propios (25 coincidían con códigos de la guía, casi todos de otra prestación: 020301 era AES y en la guía es la red de centros de acogida) y algunas no municipales. **Decisión del desarrollador:** borrarlas y cargar la guía completa.
- **BD compartida (staging):** copia previa en `~/backups/vida-20261118-antes-guia-prestaciones.dump` (prestaciones, vínculos, actividades, catálogos, versiones). Borradas físicamente las 49 y sus 4 versiones (los tipos de centro y documentos exigidos caen en cascada; no había solicitudes, prestaciones de persona, prescripciones ni registros que las usaran). Creadas las 113 por el modelo, con sus 160 vínculos de tipo de centro. La actividad 14 «Grupo de apoyo familiares VVG» pasa a 060312 (CIAM). Catálogo `prestacion.objetivo_general` con las etiquetas de la guía y `prestacion.categoria` sustituido por sus 22 categorías (0800 para el objetivo 08, que no tiene).
- **Extracción:** `pdftotext` por secciones de la ficha y enlaces de «Información complementaria» sacados de las anotaciones del PDF (una URL por ficha; 060312 no tiene). Resultado completo en `docs/referencia/prestaciones-guia-2026.json` (base para el futuro seeder).
- **Correspondencia con las columnas:** las secciones sin columna van etiquetadas en `condiciones_acceso` (población, perfil, criterios de prioridad y, en las económicas, cuantía, periodicidad, causas de extinción y reintegro), `obligaciones` (derechos y obligaciones específicos) y al final de `descripcion` (tipo y subtipo de centro, dispositivos técnicos).
- **Vías de acceso (decisión de implementación):** las 6 económicas y el SAD y la teleasistencia (040302, 040303, 040402, 050201, 050202) → expediente externo, tramitado en el centro de solicitud, como en el catálogo anterior; 29 de alojamiento, residencia, centro de día o escuela infantil → plaza; las 73 restantes → directa. Ninguna de tramitación interna ni de derivación: se configuran en Filament para las pruebas.
- **Tipos de centro (decisión de implementación):** el tipo donde se presta (según la ficha) y, además, `css_general` si se pide en el CSS (atención primaria, económicas o procedimiento por servicios sociales o trabajador social): 59; `ciam` para la categoría 0603 y 010306/010307: 14.
- **Migración `2026_11_18_100001`:** `nombre` a 255 y `aportacion_usuario`, `plazo_concesion`, `duracion_maxima`, `proveedor` y `financiacion_detalle` a `text` (la guía trae textos de hasta ~830 caracteres y un nombre de 202); en `PrestacionResource`, esos cinco pasan a `Textarea`. Aplicada en la BD compartida. Tests en `PrestacionesTest` (2; el de textos largos falla sin la migración). `Modules/Prestaciones/tests`: 94 passed.
- **Sin tocar** `PrestacionesSeeder` (decisión del desarrollador): ver BACKLOG.

---

## 2026-11-17 — Actividades: las sesiones nuevas llevan por defecto los profesionales de la actividad

- Petición del desarrollador. `ActividadesPage::nuevaSesion()` preselecciona los profesionales responsables de la actividad; se pueden quitar o añadir otros. Editar una sesión ya guardada mantiene los suyos.
- Test en `CicloVidaActividadTest` (comprobado en negativo); el fichero, 9 passed.

---

## 2026-11-17 — Filament: el widget «Alertas del sistema» no mostraba texto ni dejaba abrirlas (incidencia)

- **Causa:** la columna «Descripción» leía `mensaje`, que no existe en `Alerta` (los campos son `titulo` y `cuerpo`); el tipo salía como valor crudo; no había ninguna acción. Además listaba todas las alertas pendientes de origen sistema o asignación de roles, de cualquier destinatario: `admin@vida.local` veía las de la supervisión del CSS Entrevías y hasta el aviso personal «Tu solicitud de rol… ha sido aprobada» de `dir.css`, que no podía reconocer porque no era suyo.
- **Cambio:** `AlertasSistemaWidget` lista solo las pendientes del usuario (`Alerta::pendientesPara()`, la regla del buzón y la bandeja), con tipo («Alerta»/«Aviso»), título y texto; «Ver» (también al pulsar la fila) abre la alerta y la reconoce o descarta por `AlertaService::reconocer()`; las de asignación de rol enlazan con la ficha del usuario («Ver usuario»).
- Tests: `Modules/Usuarios/tests/Feature/AlertasSistemaWidgetTest.php` (2; fallan con el widget anterior). Usuarios y `FilamentPanelAccessTest`: 96 passed, 1 incomplete (de antes).

---

## 2026-11-17 — Usuarios: solicitudes de rol sin nadie que pudiera aprobarlas (incidencia)

### Causa
- `supervision` y `adm_sistema` tienen aprobación previa (§2.8): la resuelve la supervisión con la UO del usuario en su ámbito (misma UO o superior, por las dos jerarquías). En staging solo hay un supervisor, `dir.ciam@vida.local`, en la rama de Igualdad, así que la solicitud de `dir.css@vida.local` (CSS Entrevías, Atención Primaria) no la podía resolver nadie. Y el primer supervisor de una rama no se podía aprobar nunca. `dir.ciam` tiene el rol por un seeder.
- Además, `RolesPendientesWidget` (escritorio de Filament) permitía a cualquier `adm_sistema` o `adm_usuarios` aprobar cualquier solicitud sin comprobación alguna, también la que acababa de asignar, incluso a sí mismo: un camino para saltarse §2.8.

### Cambios
- **Decisión del desarrollador:** sin supervisor competente, la solicitud la resuelve `adm_sistema`; nunca la suya ni una asignada por él.
- `Modules\Usuarios\Services\ResolucionRolesService` (`supervisoresCompetentes()`, `puedeResolver()`, `aprobar()`, `denegar()`): único punto de resolución. `AprobacionesPage` delega en él (antes tenía la regla dentro) y conserva el 403.
- `RolesPendientesWidget`: columna «La resuelve» (supervisores competentes o adm_sistema) y «Aprobar»/«Denegar» con motivo solo cuando `puedeResolver()` lo permite. `adm_usuarios` ya no aprueba.
- Tests: `Modules/Usuarios/tests/Feature/ResolucionRolesTest.php` (7, negativos comprobados). Usuarios y Supervisión: 116 passed, 1 incomplete (de antes).

### Aprobación manual en staging
- Solicitud #41 (`coo.ciam@vida.local`, `supervision`, UO 16) aprobada a mano a petición del desarrollador, para pruebas: la asignó `admin@vida.local` y no hay supervisor competente, así que la regla nueva no deja resolverla a nadie (es lo esperado). Se cambió el estado por el modelo (el observer activó el rol en Spatie), sin tocar la lógica.

### Para el desarrollador
- Tras el despliegue, `admin@vida.local` ve la solicitud #35 de `dir.css@vida.local` en el escritorio de Filament y la puede aprobar. Con `dir.css` ya supervisor, las siguientes de su centro las resuelve él.

---

## 2026-11-17 — Staging: limpieza de los usuarios @demo.es y sus casos

A petición del desarrollador: todos los usuarios deben ser @vida.local; los @demo.es eran pruebas de una fase anterior.

- **Decisiones del desarrollador:** borrar solo los casos exclusivos de @demo.es (las 58 historias en que también son referencia ts1/ts2/ts3.ciam@vida.local se quedan; en ellas solo se quita lo hecho por los @demo.es) y **borrado físico con copia previa**. Es una excepción puntual a RN-20 (no borrar usuarios ni profesionales) y a las bajas lógicas, solo en staging.
- **Copia previa:** `~/backups/vida-20261117-1111-antes-limpieza-demo-es.dump` (`pg_dump -Fc` de la BD `vida`). Script y salida: `~/backups/limpieza_demo_es-20261117.{php,log}`.
- **Cómo:** una sola transacción. Desde las semillas (14 usuarios, sus 14 profesionales, 365 historias y sus 365 personas) recorre las claves foráneas y las parejas polimórficas: una referencia NOT NULL, o nullable a un caso, persona, plan o cita, borra la fila; una nullable a usuarios, profesionales o repartos se pone a NULL. `versiones.usuario_id`, sin FK, también se anula. Los triggers de inmutabilidad se desactivaron solo dentro de esa transacción. Simulado antes con rollback.
- **Borrado:** 14 usuarios y profesionales, 365 historias y personas, 401 planes (con objetivos, indicadores, actuaciones, firmas y cambios), 1.642 entrevistas, 1.216 seguimientos, 423 asignaciones de referencia, 400 contactos SIA, 1.288 slots, 132 líneas de cuadrante, 18 apuntes, 6 fichas, 1 cita, 1 solicitud de cita y sus 2 eventos, 64 auditorías, alertas, adscripciones y roles. Ningún documento, prescripción ni inscripción en actividades.
- **Queda:** 15 usuarios (todos @vida.local), 12 profesionales (2 sin usuario, de antes), 109 historias (89 abiertas, todas con referencia vigente) y 162 personas. Sin huérfanos; triggers activos.

---

## 2026-11-17 — Filament: guardar una ficha de valoración la dejaba sin campos (incidencia)

### Causa
- El Builder de campos de `TipoFichaResource` tenía un `dehydrateStateUsing` que devolvía `['campos' => [...]]`. Filament 5 sí aplica ese valor, pero lo reindexa como lista: a `mutateFormDataBeforeSave()` llegaba `[[...campos...]]`. `convertirSchemaBlocks()` descartaba en silencio todo bloque sin `type` y guardaba `['campos' => []]`, con el aviso «Guardado». Pasaba en cada guardado, hubiera cambios o no, también al crear. El comentario de las páginas suponía lo contrario (que Filament no aplicaba el valor).

### Cambios
- Fuera el `dehydrateStateUsing` del Builder: la única conversión es `TipoFichaResource::convertirSchemaBlocks()`, desde `mutateFormDataBeforeSave()`/`mutateFormDataBeforeCreate()`. Un bloque sin `type` y `data` lanza `LogicException` en lugar de descartarse.
- Test nuevo `Modules/Intervencion/tests/Feature/Filament/EdicionTipoFichaTest.php` (6 tests: añadir un campo, guardar sin cambios, selección con opciones y orden, añadir a una ficha con datos, crear con campos y bloque inesperado). Los 6 fallan con el código anterior. Tests de fichas de Intervención: 33 passed.

### Datos dañados en la BD compartida (staging)
- Tres tipos de ficha con `{"campos": []}`: #1 «Situación económica» (4 fichas; vacía desde junio: las fichas desde el 30 sep tienen la copia vacía), #2 «Situación de vivienda» (7 fichas; vacía desde antes del 25 sep y guardada de nuevo hoy a las 10:09) y #4 «Madurez personal» (sin fichas, guardada el 14 nov).
- Se pueden recuperar de `fichas.schema_snapshot`: #1 con 5 campos (ficha 9, 30 jun) y #2 con 6 campos (ficha 8, 29 jun). Lo añadido después y #4 no tienen copia. Restaurados #1 y #2 con la conformidad del desarrollador (por el modelo, con la validación del schema).

---

## 2026-11-17 — Fase 6: actividades grupales con inscripción (`2026-11-fase6-actividades.md`)

### Migración
- `2026_11_16_110001_create_inscripciones_sesion_table`: tabla `inscripciones_sesion` (vías `libre`/`prescripcion`, estado `inscrita`/`baja`, CHECK de vía, estado, prescripción obligatoria y motivo de baja; índice único parcial de una inscripción vigente por persona y sesión; trigger que rechaza el DELETE). `actividades.prestacion_id` y `motivo_baja`; `sesiones_actividad.motivo_cancelacion`, `fecha_cancelacion` y `cancelada_por_id`. F6-9: una inscripción por vía `prescripcion` por cada prescripción `activa` en una sesión `programada` (81 en la BD compartida, de mundos demo), inscrita por el usuario del profesional que prescribió. Se aplica con el despliegue.

### Modelo y servicios (módulo Centro)
- `InscripcionSesion` (con `Versionable`), enums `ViaInscripcion` y `EstadoInscripcionSesion`.
- `SesionActividad`: `aforoEfectivo()`, `aforoPrescripcionEfectivo()`, `capacidad()`, `aforoCompartido()` (F6-8), `ocupadas()`, `ocupadasContra()`, `plazasLibres()`, `admiteInscripciones()`, `inicio()` y `tieneParticipantes()`. Retirado el accesor `aforo_disponible`, que nadie usaba. `Actividad`: `prestacion()`, `admiteInscripciones()` y `ciudadanoInscritoEnCentro()`.
- `InscripcionActividadService`: `inscribir()` (acceso libre), `inscribirPorPrescripcion()`, `darDeBaja()` y `registrarBaja()`. Comprueba plazas y escribe con un bloqueo consultivo de PostgreSQL por sesión (`pg_advisory_xact_lock`), para que dos inscripciones simultáneas no superen el aforo. `InscripcionCentroRequerida` para F6-4.
- `CicloVidaActividadService`: `darDeBajaActividad()`, `cancelarSesion()`, `eliminarSesion()` (solo sin inscripciones ni prescripciones) y `marcarCelebrada()`.
- `PrescripcionService::crear()` admite `sesion_actividad` (F6-7): la prescripción nace `activa` con su inscripción, en la misma transacción, o no nace si no queda plaza. `cancelar()` da de baja la inscripción de la prescripción.
- `InscripcionSesionPolicy`: `inscribir`/`inscribirEnCentro` (adscritos al centro, RN-15, con `intervencion`, `consulta_basica` o `supervision`), `darDeBaja` (además, quien hizo la inscripción), `verInscritos` (personal del centro y su supervisión) y `gestionarActividad` (supervisión con el centro en su ámbito).
- Enganches como en las plazas: `PrestacionPersonaService::desdeInscripcion()` (F6-10, desde `PrestacionesServiceProvider`) y `AvisoActividadesService::avisarCancelacion()` (Intervención, por `AlertaService`; un aviso por profesional, sin nombres de personas).

### Interfaz
- Supervisión → Actividades: prestación vinculada, filtro «Ver también las dadas de baja», «Dar de baja» con motivo, sesiones con «Inscritos», «Cancelar» con motivo, «Marcar como celebrada» y «Eliminar» solo si está vacía. El estado de una sesión ya no se edita en el formulario (antes se podía poner «cancelada» a mano sin dar de baja a nadie) y el aforo no baja de lo ya ocupado. Vista de inscritos con vía, quién y cuándo, y aforo ocupado por vía, con `PresentacionPersona` (RN-17).
- Ficha de la persona: bloque «Actividades» (`ciudadania.actividades-persona`) con las inscripciones próximas y anteriores, «Dar de baja» e «Inscribir en actividad» (actividades activas del centro activo con acceso libre o mixto y sesiones con plaza; inscripción en el centro con consentimiento).
- `PrescribirRecursoModal`: tipo de recurso «Actividad grupal (sesión)», con la actividad (de cualquier centro), la sesión con las plazas de prescripción libres y la casilla de inscripción en el centro.

### Tests
- `Modules/Centro/tests/Feature/Actividades` (`AforoTest`, `InscripcionLibreTest`, `InscripcionPrescripcionTest`, `CicloVidaActividadTest`, `PermisosInscripcionTest`) y `Modules/Intervencion/tests/Feature/Actividades/InscripcionFichaTest`: TF-ACT-01 a 26 y 11 casos más (bolsa común, índice único, actividad de baja, protegida no visible, libre sin prescripción, formulario sin estado, aforo por debajo de lo ocupado, gestión solo por la supervisión, inscritos solo del centro, F6-4 en la ficha y TF-ACT-13 por el modal): 37 passed. Escenario en `Modules/Centro/tests/Concerns/ActividadesTestSetup.php`.
- Negativos comprobados (el test falla sin la protección): TF-ACT-03, 04, 08, 14, 18, 22 y 24.
- TF-ACT-04: con `RefreshDatabase`, los datos del test no se ven desde otra conexión, por eso el bloqueo es consultivo y no de fila; el test lo coge desde una segunda conexión y comprueba que la inscripción espera (`lock_timeout`).
- Suites de Centro, Supervisión, Intervención, Ciudadanía y Prestaciones y `tests/Feature/Demo`: 781 passed y 6 incomplete (de antes), sin fallos (unos 38 min). No se ha pasado la suite completa.
- `npm run build` y `ui:auditar`: sin infracciones.

### Decisiones tomadas en la sesión
- **F6-10 (desarrollador):** la prestación de la persona es una por persona y actividad, no una por inscripción; las bajas no la tocan.
- «Supervisión del centro», al inscribir, es la adscrita al centro: la que llega desde una UO superior (p. ej. `$coordIgualdad` por la jerarquía funcional) no inscribe (TF-ACT-22). Sí gestiona la actividad, como el resto de la pantalla de Actividades.
- La baja de una actividad cancela sus sesiones programadas futuras con «Actividad dada de baja: <motivo>» y avisa. Las instrucciones no lo decían: queda en el BACKLOG para confirmarlo.
- La prestación de la persona empieza en la fecha de la sesión de la primera inscripción.
- La inscripción por prescripción también pide la inscripción en el centro si la actividad la exige (F6-4 en el modal).
- No se inscribe a una persona protegida que el usuario no puede ver (RN-17), aunque esté adscrito al centro.
- El bloque «Actividades» va en la ficha de la persona (Ciudadanía) y no en la caja de herramientas de la Historia Social, para que lo usen `consulta_basica` y quien no lee la historia.

---

## 2026-11-16 — Infraestructura: planificador y cola en staging; citas de prueba en el CIAM

### Infraestructura
- El servidor no tenía cron para `schedule:run`: ninguna tarea programada se ejecutaba. El desarrollador añadió la entrada al crontab de `jupiter` (el mismo usuario que el worker de PM2). Queda documentado en la sección 18 de `docs/decisiones-tecnicas.md`.
- `ci.yml`: el despliegue termina con `php artisan queue:restart`. Hasta ahora el worker seguía con el código cargado al arrancar (13 nov).
- BACKLOG: cron y worker en la configuración del servidor para producción.

### Datos de prueba en la BD compartida (CIAM Puente de Vallecas, centro 13)
- 85 citas confirmadas del 17 nov al 7 dic de 2026: una por cada caso de referencia vigente en el centro de cada profesional con rol `intervencion` activo (dir.ciam 4, ts1 25, ts2 25, ts3 27, psicologa 4; aux2 no tiene casos). Tipo «Cita», presencial, urgencia ordinaria, destino y modo `referencia`, en huecos `disponible` (no de urgencia) elegidos al azar, con motivos variados.
- Se crearon con `CitacionService::citarDirecto()` actuando como `adm1.ciam` (tramitación): cada cita tiene su solicitud citada y sus eventos (`cita_eventos`), y el slot queda reservado. Script desechable, no versionado. No están etiquetadas en `demo_world_registros`: `demo:reset` no las identifica como demo.

---

## 2026-11-16 — Agenda: la creación de citas nunca proponía huecos (incidencia)

### Diagnóstico
- La búsqueda de huecos (`BusquedaHuecosService`) funcionaba; en la BD compartida (staging) no había **ningún slot**: 0 cuadrantes, 0 líneas, 0 slots y 0 citas. Los slots solo nacen al publicar un cuadrante y nunca se había publicado ninguno.
- Además, aunque se publicara: `horario_centro_tipo_slot` estaba vacía (el materializador devuelve 0 sin avisar) y los dos horarios tenían la vigencia mal (CIAM Puente de Vallecas, centro 13: `0006-01-01`; CSS Entrevías, centro 14: `2029-06-01`).
- Publicar desde Filament (`CuadranteMesResource`) solo cambiaba el estado, sin materializar los slots (el texto de la acción lo decía).
- El tipo genérico «cita» admitía cualquier tipo de slot activo, también los internos (Descanso, Trabajo propio, Mesa de trabajo).

### Datos corregidos en la BD compartida (decisión del desarrollador: los dos centros; el genérico, solo Entrevista)
- Vigencia de los horarios de los centros 13 y 14 desde el 2026-01-01; tipo de slot Entrevista enlazado a los dos horarios.
- Cuadrantes de noviembre y diciembre de 2026 generados y publicados desde los perfiles horarios (`CuadranteGeneratorService::generarYPublicarAutomaticamente`): CIAM 3.318 + 3.824 slots, Entrevías 616 + 672. La búsqueda propone huecos en los dos centros.

### Cambios de código
- `CuadranteMesResource`: «Publicar» usa `CuadrantePublicadorService` (materializa los slots).
- `TipoCita`: el genérico se limita a sus tipos de slot enlazados si tiene alguno (sin enlaces, cualquiera, como antes). Migración `2026_11_16_120001` que enlaza el genérico con Entrevista.
- `BusquedaHuecosService::hayAgendaPublicada()`; la cita directa y la bandeja de citación dicen «el centro no tiene agenda publicada para estas fechas» en lugar de «no hay huecos» cuando no hay slots en la ventana.
- **Publicación automática del cuadrante (decisión del desarrollador):** servicio `PublicacionAutomaticaCuadrantes`, comando `agenda:publicar-cuadrantes [--mes=AAAA-MM] [--centro=ID]` y tarea programada el día 20 de cada mes a las 02:30 para el mes siguiente (`routes/console.php`). No toca centros que ya tienen cuadrante ese mes (en cualquier estado) ni publica cuadrantes que nacerían vacíos (sin horario vigente, sin tipos de slot o sin perfiles activos). Tests: `PublicacionAutomaticaCuadrantesTest` (5), negativos comprobados (no pisar el cuadrante existente, no publicar vacío).
- **Entrevías:** vigencia del horario corregida a `2019-06-01` (el `2029` era una errata, confirmado por el desarrollador).
- Tests: `Modules/Agenda/tests/Feature/Citas/AgendaPublicadaTest.php` (3), con negativos comprobados (publicar sin materializar, genérico sin restricción). `ui:auditar` sin infracciones. Suite de Agenda: 197 passed.

---

## 2026-11-16 — Fase 5: informes desde la interfaz operativa (`2026-11-fase5-informes.md`)

### Decisiones del desarrollador (16 nov)
- **F5-1 confirmada:** dos formas de firmar; la subida del PDF firmado fuera de VIDA es la activa y AutoFirma integrada queda preparada e inactiva por configuración.
- **F5-2 confirmada:** validación mínima (firma íntegra con `pdfsig`, mismas páginas, identificador en el pie) y se guarda el titular del certificado.
- **`ResolverFuentesInforme::resolver()` se implementa en esta fase:** era un stub que devolvía `[]` (todas las secciones automáticas salían vacías y TF-INF-06 no podía pasar).

### Paso 0 — Verificación
- Suites de partida: Documentos 107 passed; Intervención 337 passed y 1 incomplete (previo).
- `pdfsig` 26.01 instalado; staging está en la misma máquina y ningún perfil de AppArmor confina `pdfsig`.
- Plantillas en la BD compartida: #1 «Certificado vulnerabilidad» (`informe_social`, 4 secciones automáticas) y #2 «Informe psicológico» (`informe_psicologico`). 0 informes. `generarBorrador()` funcionaba con las dos, pero con las secciones automáticas vacías, los merge tags sin sustituir y sin identificador en el pie.

### Cambios
- **Migraciones:** `2026_11_16_100001` (`informes.firmante_certificado` y `deleted_at`; `piso_firmados.sustituido_en`, índice único parcial por plan activo en lugar del `unique`, FK a `planes_intervencion`, documento sin borrado en cascada; tipo documental `piso_firmado` vinculable a persona y plan) y `2026_11_16_100002` (`parametros_informe`).
- **Generación del PDF:** `generarBorrador()` (con la marca «BORRADOR · sin validez») y `generarParaFirmar()`; «Ref. INF-000123» en el pie de cada página (`Informe::identificador()`); secciones automáticas como tabla etiqueta → valor; texto libre saneado (`SaneadorHtmlInforme`, `symfony/html-sanitizer`) y con merge tags; estilo de la primera adscripción vigente del autor (antes se leía `users.unidad_organizativa_id`, que no existe, y se aplicaba siempre el de la UO 1).
- **Firma (paso 3):** `MetodoFirma::PdfFirmadoExternamente`; contrato `FirmadorInforme` con `FirmaExternaSubida` (activa) y `FirmaAutoFirma` (`documentos.firma.autofirma_activa`, por defecto `false`); `ValidadorFirmaPdf` (`pdfinfo`, `pdfsig`, `pdftotext`, con `LC_ALL=C`) y `FirmaRechazadaException`; `ServicioFirmaInforme::firmar(Informe, MetodoFirma, pdf, actor)` valida, custodia sin reescribir, guarda método y titular y deja un apunte `documento` en la historia; `anular()` exige motivo. `FirmadorSimulado` para tests (PAdES en PHP con `openssl_cms_sign`).
- **Interfaz (pasos 1, 2 y 4):** `InformesHistorial` en la caja de herramientas «Informes» de `CiudadanoPage` (sustituye a «Módulo de informes en construcción») y en `PlanPage`; `NuevoInformeWizard` (rutas `intervencion.informes.nuevo` y `editar`) con plantilla, redacción con guardado automático, vista previa e iframe, y firma; `InformePdfController` (vista previa y PDF para firmar, solo el autor, auditado); `PisoFirmadoUpload` en «Seguimiento y firmas» de `PlanPage` con `PisoFirmadoService` (sustitución = versión nueva del mismo documento).
- **Permisos (paso 5):** `InformePolicy` (ver por `HistoriaSocialPolicy::view`; crear por escritura en la historia o responsable/equipo de un plan activo; editar, firmar y eliminar el autor sobre su borrador; anular el autor sobre su firmado) y `PisoFirmadoPolicy`. `DocumentoPolicy`: el PDF de un informe solo con `InformePolicy::view`.
- **Variables auxiliares (paso 6):** `ParametroInforme` (caché), `ParametroInformeSeeder` (desde `DocumentosSeeder`), `VariablesDinamicas`, `MergeTagsCatalogo` con las tres categorías, `ParametroInformeResource` (edita solo `adm_sistema`; la supervisión consulta).
- **Tests:** TF-INF-01 a 28 en `Modules/Documentos/tests/Feature/Informes/` (6 ficheros, trait `InformesTestSetup`; 36 tests con 8 casos base del validador) y TF-DOC-22 a 25 en `VariablesAuxiliaresTest`. 21 negativos comprobados (lista en el fichero de instrucciones). Tests antiguos adaptados: `DocumentosTest` (TF-DOC-13 a 16 y 20 firman con `FirmadorSimulado`; TF-DOC-17 y 18 con planes reales por la FK nueva) y `RetencionDestruccionTest`.
- `npm run build` y `ui:auditar` sin infracciones. PHPStan limpio en los ficheros nuevos y reescritos.
- **Suite completa** (unos 80 min): 1540 passed, 12 incomplete, 1 skipped, 0 failed. Sin fallos nuevos (tampoco aparecen ya los 2 previos de `CuadranteSupervisorTest`).

### Decisiones de implementación no previstas en las instrucciones
- **Identificador** `INF-{id con 6 dígitos}` dibujado en el lienzo de cada página, con o sin estilo de UO. La vista previa lleva la marca de borrador y el validador rechaza un PDF que la contenga (`es_borrador`), para que no se firme la vista previa por error.
- **Borradores con baja lógica** (`SoftDeletes`) en vez de borrado físico (CLAUDE.md: no hay hard deletes).
- **PISO firmado:** tipo documental propio `piso_firmado` creado por migración; sustituir crea versión nueva del mismo documento y un `PisoFirmado` nuevo, y el anterior queda con `sustituido_en` (el pasado no se sobrescribe).
- **`DocumentoPolicy`:** el PDF de un informe solo lo ve quien puede ver el informe, aunque otra vía (p. ej. la de tramitación por sus solicitudes) diera acceso al documento.
- **`InformePolicy` registrada:** la página «Ver» de `InformeResource` (Filament) pasa por ella; `adm_sistema` necesita también poder leer la historia (coherente con «adm_sistema no tiene acceso privilegiado a datos de ciudadanos»).
- **Plantillas del asistente:** las activas de las UO de las adscripciones del profesional y sus ancestros (`visiblesParaUo`).
- **Editor de texto libre:** el `contenteditable` de la síntesis del plan (clase `plan-editor-area`, descripción del catálogo ampliada), con guardado a los 1,5 s sin escribir y al salir del campo.
- **Organigrama de los tests:** `InformesTestSetup` cuelga las juntas y el Área de una UO «Ayuntamiento» (como en producción) para que una plantilla sea visible para el CSS y el CIAM.
- **Prioridad de las variables auxiliares:** contextuales > dinámicas > parámetros, como el `array_merge` de las instrucciones y `modulo-documentos.md` (el texto de las instrucciones decía que los parámetros ganaban a las dinámicas).
- **TF-DOC-22 a 25** van en `VariablesAuxiliaresTest` (con `DocumentosTestSetup`, que siembra roles y `adm_sistema`) y no en `DocumentosTest`.

---

## 2026-11-15 — Fase 4: atención administrativa (`consulta_basica`) y delta de citas (`2026-11-fase4-consulta-basica.md`)

### Decisiones del desarrollador (15 nov)
- **F4-1 confirmada:** `consulta_basica` ve los datos objetivos de una persona protegida solo si está adscrita al centro de su historia o de un plan en curso (D4 y RN-17 sobre §3.2 del documento de roles). Ya lo cumplía `CiudadanoPolicy` por RN-17; queda probado (TF-CBA-09 y 10).
- **Documentos de `tramitacion`:** en la ficha, solo sus documentos del día; además abre los documentos de las personas cuyas solicitudes tramita, y en la página de la solicitud sigue viendo la carpeta de la persona para vincular (RN-10 de la fase 3 bis, que chocaba con «no ven la carpeta completa»).
- **Permiso nuevo `documento.ver_carpeta`** para decidir quién ve la carpeta completa (no estaba en las instrucciones): se da a `adm_sistema`, `supervision`, `intervencion` y `consulta_profesional`, los que la veían.

### Paso 0 — Verificación
- **Suites de partida:** la ejecución quedó contaminada (se editó código mientras corría y se lanzó un test en paralelo sobre `vida_testing`): 17 fallos al final, en Acceso y Documentos, que desaparecen al relanzar esas suites con el código terminado (Acceso 36 passed, Documentos 100 passed). Según SESSION no había fallos previos en estos módulos.
- **Bloques de `FichaCiudadanoPage` antes del cambio:** edición de Capa 1 por `CiudadanoPolicy::update`; relaciones por rol (`intervencion`, `tramitacion`); «Ir a HS» por rol `intervencion`; referencia con fechas e historial y «Otras prestaciones» sin condición; prestaciones de la persona por `solicitud.ver`; accesos recientes por rol (y sin pintar). No había banner de protegido en la ficha ni bloques de citas o de estado de la historia.
- **Subida de documentos:** `CicloVidaDocumentoService::altaDocumento()` → `IngestaDocumentoService`; `documentos.created_by` guarda quién lo subió y cada versión su autor. Sin retirada lógica.

### Cambios
- **Permisos (paso 1):** migración `2026_11_15_130001_permisos_consulta_basica_fase4` (solo añade) y `PermisosSeeder`/`RolesSeeder`. Nuevos: `ciudadano.editar_relaciones`, `historia.ver_estado`, `cita.ver_historial`, `documento.ver_carpeta`, `documento.subir`, `documento.gestionar_propios`. `consulta_basica` gana además `ciudadano.crear`; `tramitacion`, `citas.solicitar` y `citas.gestionar`. Reparto por rol en `modulo-usuarios-permisos.md` §4.4. **`RolesSeeder` pasa a ser aditivo** (antes `syncPermissions`).
- **Ficha por permiso (paso 2):** bloque «Historia Social» (`historia.ver_estado`: estado y referencia vigente, nada más; `HistoriaSocial::etiquetaEstado()`); la referencia con origen, fechas e historial y el cambio de referencia, solo con `historia.leer`; «Otras prestaciones», con `solicitud.ver`; accesos recientes, con `historia.leer`; relaciones con `CiudadanoPolicy::editarRelaciones()` (permiso y regla de operación de la UO, que antes no se aplicaba a las relaciones). `AltaCiudadano` autoriza con `CiudadanoPolicy::create` al montar y al guardar.
- **Historial de citas (paso 3):** componente `Modules\Agenda\Livewire\Citas\HistorialCitasFicha` (`agenda.historial-citas-ficha`), reprogramadas agrupadas con su sustituta, todas las futuras y las 10 pasadas más recientes con «Ver más»; «Reprogramar o cancelar» lleva a `CitaPage`.
- **Documentos propios del día (paso 4):** estado `retirado` y columnas `retirado_en`, `retirado_por_id`, `motivo_retirada` (migración `2026_11_15_130002`, rehace el CHECK de `documentos.estado`); `Documento::disponibles()`, `estaDisponible()`, `esPropioDelDia()`; `CicloVidaDocumentoService::retirar()` y `actualizarDatos()`; `DocumentoPolicy` con `gestionarPropio()` y vías de otros módulos (`AccesoDocumentoAdicional`, `AccesoDocumentosTramitacion` en Prestaciones); `DocumentoController` autoriza con la policy; `DocumentosCiudadano` con las dos vistas y «Sustituir fichero», «Cambiar datos» y «Retirar». Las solicitudes de prestación excluyen los retirados (listas, envío, aportación y vinculación).
- **Citas para `tramitacion` (paso 5):** avisos de solicitud creada a `consulta_basica` y `tramitacion` del centro; la citación usa el layout de cada rol (`User::layoutOperativo()`); «Citación» y «Buscar ciudadano/a» en el menú de tramitación.
- **Superficie (paso 6):** quien da citas sin otra superficie entra por la búsqueda de ciudadanos (`User::destinoInicial()`, por `citas.gestionar`). El menú operativo oculta agenda, casos, mensajes y recursos a quien no tiene `intervencion` (reproduce el middleware de esas rutas), lleva la búsqueda a `ciudadania.buscar` y el alta a quien puede crear ciudadanos.
- **Tests:** TF-CBA-01 a 30 en verde, más TF-CBA-21 ter (documentos de tramitación por la solicitud) y 29 bis (migración de permisos): `Modules/Ciudadania/tests/Feature/ConsultaBasica/` (5 ficheros y el trait `ConsultaBasicaTestSetup`), `Modules/Agenda/tests/Feature/Citas/HistorialFichaTest.php` y `TramitacionCitasTest.php`, `Modules/Documentos/tests/Feature/DocumentosPropiosTest.php`, `Modules/Prestaciones/tests/Feature/Solicitudes/DocumentosTramitacionTest.php`.
- **Negativos comprobados** (se quitó la protección y el test falló): TF-CBA-02 (detalle de la referencia), 04 (regla de UO en relaciones), 10 (consulta externa de protegidos), 14 (nombre interno y motivo), 16 y 17 (acciones del historial por policy), 21 (`DocumentoPolicy` por propios), 22 (día natural) y 25 (motivo obligatorio). TF-CBA-08 y 30 descansan en protecciones que ya existían (la ficha no tiene banner; middleware `role:intervencion`) y no se han mutado.
- **Tests antiguos adaptados al diseño nuevo:** `AutenticacionTest` (TF-AUTH-03, 11, 15, 16, 16b, 17 y 19) y `SinRolTest`: `consulta_basica`, rol mínimo de los tests de autenticación, entra ahora por la búsqueda de ciudadanos; `NavegacionTest` TF-LW-NAV-22: tramitación ya no ve «Ir a HS» ni deshabilitado; `RelacionesCiudadanoTest` TF-LW-REL-06 y 17: `consulta_basica` edita relaciones y el caso sin permiso pasa a supervisión. Nota en `decisiones-tecnicas.md` §1.4.
- **Suite completa:** 1485 passed, 16 failed, 12 incomplete, 2 skipped (unos 51 min). De los 16: los 2 previos de `CuadranteSupervisorTest`; los 11 tests antiguos de la línea anterior (corregidos: Auth, la navegación y las relaciones de Ciudadanía dan 85 passed, 2 incomplete y 1 skipped previos); y TF-CBA-02, 13 y 14, que fallaban por una vista Blade compilada obsoleta: al comprobar el negativo de TF-CBA-14 se restauró la vista con `mv` desde la copia (conserva la fecha antigua) y Laravel siguió sirviendo la mutada. Con `php artisan view:clear` pasan los diez de `HistorialFichaTest` y `EstadoHistoriaTest`. No se ha relanzado la suite completa.
- `npm run build` y `ui:auditar` sin infracciones.
- Documentación: `roles-consulta-basica-tramitacion.md`, `modulo-citas.md`, `modulo-ciudadania.md`, `modulo-documentos.md`, `modulo-usuarios-permisos.md`, `decisiones-tecnicas.md` (sección 16), tabla de estado de las instrucciones y `CLAUDE.md` §6.

### Decisiones de implementación
- **Retirada como estado** (no solo columnas): «no se lista ni se sirve» ya se decidía por el estado, como `destruido`.
- **El historial de citas es un componente de Agenda** incrustado en la ficha, sin acciones propias: reprogramar y cancelar siguen en `CitaPage` y `CitacionService`.
- **«Cambiar datos»** edita también descripción, emisión y metadatos del tipo (no solo el tipo y las personas), porque un tipo nuevo puede exigir metadatos que no tenía. Asociar a alguien que no es de la unidad de convivencia se rechaza con error (en el alta se sigue filtrando en silencio).
- **Relaciones con la regla de UO:** también para `intervencion`, que antes editaba relaciones de cualquier persona.
- **Avisos de cita a dos roles:** las alertas van dirigidas a rol y UO, así que se crea una por rol.
- **Quedan decisiones de interfaz por rol** documentadas: «Ir a HS» y el menú operativo (reproducen `role:intervencion`), el tipo de atención del modal de atención y las excepciones PSH/VVG del alta (BACKLOG).

---

## 2026-11-15 — FK a `ciudadanos` en `accesos_protegidos` y `revelaciones_identidad`

### Cambios
- **Migración** `2026_11_15_120001_add_ciudadano_foreign_to_accesos_y_revelaciones`: FK con `restrictOnDelete` en las dos tablas. Antes comprueba las dos y, si alguna tiene filas cuyo ciudadano no existe, aborta sin tocar ninguna. En la BD compartida ambas están vacías (0 filas, consulta de solo lectura del 15 nov). Con esto **ninguna columna `ciudadano_id` del esquema queda sin FK**.
- `AccesoProtegidoFactory`: `ciudadano_id` pasa de `fake()->numberBetween(1, 99999)` a `Ciudadano::factory()`. Ningún test usa la factory ahora mismo; los que crean accesos lo hacen con `AccesoProtegido::create()` y un ciudadano real.
- Comentarios de las migraciones originales (`2026_03_11_153334` y `2026_05_22_100004`) actualizados para remitir a la nueva.
- **Tests:** `tests/Feature/Acceso`, `tests/Feature/Anonimizacion`, `PersonasProtegidasTest` (Centro), `AccesoDocumentoTest`, `Modules/Usuarios/tests`, `Modules/Supervision/tests` y `Modules/Intervencion/tests`: 539 passed, 6 incomplete (previos). Las tres FK comprobadas en `vida_testing` (`pg_get_constraintdef`).

### Decisiones de implementación
- `revelaciones_identidad` se creó sin FK a propósito («para no crear dependencia de integridad»). Se añade igualmente: los ciudadanos solo tienen baja lógica y `audits`, que también es un registro legal, ya tenía esta FK, así que la restricción no impide nada legítimo. `RevelacionIdentidadService` siempre registra a un ciudadano que existe.

---

## 2026-11-15 — FK `historias_sociales.ciudadano_id → ciudadanos` y tests con ciudadanos inventados

### Cambios
- **Migración** `2026_11_15_110001_add_ciudadano_foreign_to_historias_sociales`: FK con `restrictOnDelete` (los ciudadanos solo tienen baja lógica). Antes de crearla cuenta las historias huérfanas y, si hay alguna, aborta con un mensaje claro. La BD compartida tenía 0 de 474 (comprobado el 15 nov, en solo lectura). Cierra el TODO de la migración de marzo.
- **Tests y factory corregidos**, que creaban historias con un `ciudadano_id` inventado (literal o `fake()->numberBetween`) y ahora usan `Ciudadano::factory()->create()->id`: `AutorizacionDatosTest` (los 4 tests del scope y el helper), `EscalaTest`, `VisibilidadApuntesTest`, `ValoracionFichasTest`, `EntrevistaTest`, `SeguimientoTest`, `VersionadoPlanTest`, `HistoriaSocialPolicyTest`, `ApuntePolicyTest` y `EntrevistaFactory`.
- **Negativo comprobado:** con la FK y sin corregir los fixtures fallaban 60 tests de esos ficheros, todos por la FK.
- **Tests:** `AutorizacionDatosTest`, `Modules/Escalas/tests`, `Modules/Intervencion/tests` y `Modules/Usuarios/tests`: 444 passed, 2 incomplete (previos). `AusenciasSupervisorTest`, que crea historias con un id recibido por parámetro, 9 passed; `DemoAditivoTest` y `MigracionCircuitoTest` (insertan en `historias_sociales` directamente), en verde.

### Decisiones de implementación
- Las otras dos columnas `ciudadano_id` sin FK (`accesos_protegidos` y `revelaciones_identidad`) no se tocan: van al BACKLOG.

---

## 2026-11-15 — Tests: fallo previo de `AutorizacionDatosTest` (FK de `audits`)

### Cambios
- **Causa:** `crearHistoriaSinScope()` creaba la historia con un `ciudadano_id` aleatorio que no existe en `ciudadanos`. `historias_sociales.ciudadano_id` no tiene FK (TODO de la migración de marzo), pero `audits.ciudadano_id` sí, y `HistoriaSocialService::actualizar()` audita la edición con el titular de la historia. Por eso fallaba solo `servicio_historia_social_permite_actualizacion_con_permiso`, el único test del fichero que edita una historia.
- **Corrección (solo en el test):** el helper crea antes un `Ciudadano` real (`Ciudadano::factory()->raw()` sin el scope de UO). No se toca la aplicación.
- **Tests:** `tests/Feature/AutorizacionDatosTest.php`, 18 passed.

### Decisiones de implementación
- La FK `historias_sociales.ciudadano_id → ciudadanos` no se añade en esta sesión. La BD compartida no tiene historias huérfanas (0 de 474, consulta de solo lectura), pero unos 20 tests de 7 ficheros crean historias con ids inventados y fallarían. Va al BACKLOG.

---

## 2026-11-15 — Fase 3 bis: solicitudes de prestación y expedientes (`2026-11-fase3bis-solicitudes-expedientes.md`)

### Decisiones del desarrollador (15 nov)
- **Vía inicial de las prestaciones existentes:** económicas, SAD, SAD intensivo, teleasistencia y ayuda a domicilio para familias → expediente externo (tramitadas por el centro de solicitud hasta configurar otra unidad); alojamientos, residencias, centros de día y acogidas → plaza; el resto → directa.

### Cambios
- **Migraciones:** `2026_11_15_100001` (unidad tramitadora, formulario de solicitud y código del gestor en `prestaciones`; `tipo_fichas.uso`; `prestacion_documentos_exigidos`; vía inicial de las prestaciones existentes), `100002` (`solicitudes_prestacion`, `solicitud_prestacion_personas`, `solicitud_prestacion_eventos` y `prestacion_persona_eventos` con trigger de solo inserción, `resoluciones_solicitud`, `resolucion_lineas`, `prestaciones_persona`; `prescripciones.prestacion_id` y `plan_apuntes.fuente`), `100003` (retira `solicitudes_servicio`, sin registros fuera de tests) y `100004` (permisos `solicitud.*` y `prestacion_persona.gestionar` en una BD ya sembrada).
- **Modelos y servicios** (módulo Prestaciones): `SolicitudPrestacion`, `SolicitudPrestacionEvento`, `ResolucionSolicitud`, `ResolucionLinea`, `PrestacionPersona`, `PrestacionPersonaEvento`, `PrestacionDocumentoExigido`; enums `EstadoSolicitudPrestacion`, `AccionEventoSolicitud`, `OrigenActualizacion`, `SentidoResolucion`, `EstadoPrestacionPersona`. `SolicitudPrestacionService` (único punto de escritura; `registrarActualizacion()` idempotente para las cuatro vías de RN-07; `procesarAviso()`), `ResolucionService`, `PrestacionPersonaService`. Adaptador `Gestor\AdaptadorGestorExpedientes` con `GestorManual` (por defecto) y `GestorSimulado`; `config/prestaciones.php`; comando `expedientes:consultar` en el scheduler. Policies de la solicitud y de la prestación de la persona.
- **Interfaz:** «Solicitar prestación» en la caja de herramientas de la Historia Social, en cada objetivo del plan y tras registrar un seguimiento; pantalla de la solicitud; bandeja de tramitación (`prestaciones.bandeja`) con layout y menú propios del rol `tramitacion`, que entra por ella (`User::destinoInicial()` y onboarding); bloque «Prestaciones» en la ficha; resumen en la cabecera de la Historia Social (`statPrestaciones()`, P-03 resuelta). `PrestacionResource` con vía, unidad tramitadora, formulario, código del gestor y documentos exigidos.
- **Retirada de `SolicitudServicio`** (modelo, test y tabla).
- **Tests:** `Modules/Prestaciones/tests/Feature/Solicitudes/` (7 ficheros): TF-SOL-01 a 44 en verde. `PrestacionFilamentResourceTest` adaptado: la vía de acceso es obligatoria en una prestación activa. Autoload de `Modules\Prestaciones\Tests` en `composer.json`.
- **Negativos:** la sesión de implementación se cortó sin dejar constancia de qué tests se comprobaron en negativo; no se afirma ninguno.
- Docblocks añadidos a métodos anteriores sin documentar en ficheros tocados (`PlanPage::guardarDiagnostico()`, `Apunte::newFactory()`, `TipoFicha::newFactory()` y `booted()`).
- `npm run build` y `ui:auditar` sin infracciones.
- Documentación: `modulo-solicitudes-expedientes.md` (estado y §9, implementación), `modulo-prestaciones.md`, `modulo-intervencion.md`, `modulo-centros.md`, `modulo-documentos.md`, `modulo-integraciones.md`, `roles-consulta-basica-tramitacion.md` §4, `decisiones-pendientes.md` (P-01 y P-03 resueltas), tabla de estado de las instrucciones y `CLAUDE.md` §6.

### Decisiones de implementación no previstas
- **Snapshot de configuración** en la solicitud (vía, unidad, formulario, código del gestor, documentos exigidos y número de versiones de la prestación) en lugar de un `prestacion_version_id`.
- **Documentos:** un documento de la persona puede vincularse también a su solicitud; los subidos desde la solicitud entran por `CicloVidaDocumentoService::altaDocumento()` con los dos vínculos.
- **Seguimiento → solicitud:** `solicitudes_prestacion.seguimiento_id` como campo de contexto, sin pivote.
- **Apunte automático** de resolución con tipo `gestion_coordinacion` y `plan_apuntes.fuente` (`gestor_expedientes` o `registro_manual`).
- **Plazas:** `prescripciones.prestacion_id` solo se rellena si la prescripción nace de «Solicitar prestación»; al asignarse la plaza se crea la prestación de la persona activa. Las prescripciones abiertas desde Recursos no crean ninguna (BACKLOG).
- **Supervisión** recibe todos los permisos de solicitud (crear, ver, tramitar, registrar a mano, gestionar prestaciones de la persona).
- Accesos a solicitudes de personas protegidas auditados como `acceso_restringido`; tramitación no ve la Historia Social.

### Tests de cierre
- `Modules/Prestaciones/tests`: 91 passed.
- Centro 137, Ciudadanía 105, Documentos 100, Usuarios 71 (1 incomplete), `tests/Feature/Auth` 40 (1 incomplete, 1 skipped), `tests/Feature/Acceso` 36, `FilamentPanelAccessTest` 16, Intervención 337 (1 incomplete): sin fallos; los incomplete y skipped son previos.
- **Suite completa** (unos 49 min): 1466 passed, 3 failed, 12 incomplete, 2 skipped. Los 3 fallos son previos: `AutorizacionDatosTest` (FK de `audits`) y los 2 de `CuadranteSupervisorTest` anotados en el BACKLOG el 14 nov. Sin fallos nuevos.

---

## 2026-11-14 — Dependencias: Laravel 12.72.0 (CVE-2026-128083)

### Cambios
- El hook de pre-commit bloqueaba por `composer audit`: `laravel/framework` 12.69.3 afectado por CVE-2026-128083 (baja, «Task Output Injection - Concurrency Process», publicado el 13 nov; corregido en 12.71.0). `composer update laravel/framework --with-dependencies` con `--with` para mantener `symfony/clock`, `css-selector` y `translation` en 7.4 (la primera resolución los subía a 8.1; ver la entrada del 11 nov). Laravel 12.72.0; parches de Symfony 7.4, Carbon 3.15.1, Guzzle 7.16.1, `phpdotenv` 5.8.0 y otros menores. Solo cambia `composer.lock`. `composer audit`: sin avisos.
- Tests tras la actualización: circuito de la fase 3, Acceso, Supervisión y Auth: 156 passed, 1 incomplete y 1 skipped (previos).

---

## 2026-11-14 — Fase 3: circuito de intervención completo (`2026-11-fase3-circuito-intervencion.md`)

### Paso 0 — Verificación
- Suites de referencia (Intervención, Centro, Ciudadanía, Mensajes, `tests/Feature/Acceso`): 710 passed, 1 incomplete, sin fallos previos. Piezas de la fase 2 presentes (`tiposPlan()`, `centroActivo()`, `gestionaProtegido()`, `TipoCentro`).
- BD compartida: 0 planes con `tipo` incoherente; 396 sin `tipo_plan_id` (371 activos y 25 cerrados, todos `general_asp`, creados el 26 jun 2026); de los activos, 239 con historia en el CSS Entrevías y 132 en el CIAM Puente de Vallecas. 0 apuntes `derivacion` creados con la herramienta de la caja. **Ningún tipo de centro tenía tipos de plan** (`tipo_centro_tipo_plan` vacío): no se podía abrir ningún plan en staging.
- Lectores que decidían «es el PISO» por `tipo`: `CiudadanoPage::pisoActivo()`, `MisCasosPage` (subconsulta del plan) y `RegistrarValoracionPage::registrarApunte()`; además `PlanPage::crearNuevoPlan()` fijaba `general_asp`. Nivel 1 por la UO de la historia en `PlanDeIntervencionPolicy`, `CiudadanoPolicy`, `HistoriaSocialPolicy` y `AmbitoUoScope` (todos por `AmbitoUsuario`). Alertas con enlace: `origen_type`/`origen_id` de `Alerta`.

### Decisiones del desarrollador (14 nov)
- Los planes **activos** sin tipo pasan a PISO, salvo los 132 cuya historia es del CIAM, que pasan a **PIA** (entrada directa, centro CIAM). Los cerrados sin tipo no se tocan.
- PIA en el tipo de centro `ciam` y PISO en `css_general` (migración de datos).
- **`supervision` por sí solo no lee ni edita planes**: la dirección o un responsable superior que los necesite tiene además `intervencion`. Se quita `plan.leer` al rol (RolesSeeder y migración `2026_11_14_100004`). Donde las instrucciones dicen «la supervisión del centro edita el plan», se entiende supervisión **con** `intervencion`.
- La retirada de la herramienta «Derivación» de la caja, comentada con Claude Web, ya estaba en estas instrucciones (F3-6, B4.4).

### Cambios
- **Migraciones:** `2026_11_14_100001` (`tipos_plan.es_principal` + índice único parcial; marca `piso`), `100002` (corrección de datos y tipos de plan por tipo de centro), `100003` (`planes_intervencion.centro_id`, relleno por la UO de la historia, índice único parcial «un plan vigente por tipo»), `100004` (`plan.leer` fuera de `supervision`), `100005` (`prestaciones.via_acceso` y `tipo_plan_derivacion_id`), `100006` (`derivaciones` y `derivacion_eventos` con trigger de solo inserción), `100007` (`plan_indicador_valoraciones` con trigger).
- **A — Tipos de plan:** `TipoPlan::es_principal` (uno, de ASP; modelo e índice) y `TipoPlan::principal()`; `PlanDeIntervencion` deriva `tipo` del ámbito, rechaza uno incoherente y exige tipo de plan en los planes nuevos; `esPrincipal()`, scopes `principal()` y `vigentes()`, relación `centro()`. `PlanDeIntervencionService::crear()` con todas las reglas de apertura (A2.2). `PlanPage` crea por el servicio, ofrece solo los tipos abribles y preselecciona si hay uno (botón «Crear plan»). `pisoActivo()` → `planPrincipalActivo()`; `MisCasos` y `RegistrarValoracionPage` por el tipo principal. `HistoriaSocial::centro()`. Seeder: tipos de ASP de ejemplo en `css_general`.
- **A3 — Acceso del equipo del plan:** `AmbitoUsuario::leePorPlan()`, `centrosDeLaPersona()`, `uoIdsPlanesEnCurso()`; `gestionaProtegido()` y `restringirHistoriasGestionables()` incluyen los centros de los planes en curso. `CiudadanoPolicy::view` y `HistoriaSocialPolicy::view` leen por plan (sin `update`). `PlanDeIntervencionPolicy` reescrita: editan responsable, supervisión del centro (con `intervencion`) y, en el principal, la referencia vigente. **Todas las acciones de escritura de `PlanPage` comprueban la policy** (antes solo activar y cerrar). Ficha: bloque «Planes de la persona». `MisCasos`: «Otros planes de los que soy responsable».
- **B — Derivación:** enum `ViaAcceso`, validación en `Prestacion`, campos en `PrestacionResource`, prestación de prueba `DER-PIA` en `PrestacionesSeeder`. Modelos `Derivacion`, `DerivacionEvento` (inmutable), enum `EstadoDerivacion`. `DerivacionService` (derivar, aceptar, rechazar, anular, con apuntes y alertas). Componente `DerivacionesPlan` en el plan principal (modal, historial, anulación), «Derivar» en cada objetivo y sus derivaciones, cabecera «Nace de la derivación…» en el especializado. Bandeja `supervision.derivaciones` (`DerivacionesPage`) con entrada en el menú. Retirada la herramienta «Derivación» y `CiudadanoPage::crearDerivacion()`.
- **C — Evaluación de objetivos:** `PlanIndicadorValoracion` (inmutable); `PlanObjetivoIndicador::registrarValoracion()` deja historial; `EvaluacionObjetivosService` (propuesta de estado y seguimiento en transacción); evaluación en la herramienta Entrevista al programar el seguimiento (todo en una transacción con la entrevista); evolución de cada indicador en el plan.
- **D — Plazas:** `AvisoPlazasService`; `PrescripcionService` con resolver del TSR vigente y aviso de plaza libre (configurados en `IntervencionServiceProvider`); `AsignarPlazaModal` avisa por `AlertaService` (antes solo `Log::info`).
- **E — Referencia:** enum `MotivoCambioReferencia`, componente `CambioReferencia` en «Centro y referencia» de la ficha; `cambiarManual()` exige supervisión adscrita, profesional elegible y avisa al saliente y al entrante; `AsignacionCentroService::asignarManual()`/`descartar()` comprueban el centro.
- **Tests:** `Modules/Intervencion/tests/Feature/Circuito/` (9 ficheros, trait `CircuitoTestSetup`): TF-CIR-01 a 43 en verde (03 y 07 en un solo test de migración). Ajustados tests antiguos que creaban planes sin tipo de plan (ahora obligatorio), `TipoCentroTestSetup` (PISO principal), la factoría de planes (tipo de plan por defecto), `PlanContenidoTest` (7 tipos del seeder), `CierrePlanTest` y `ObjetivosIndicadoresTest` (sin `TipoPlan::first()`, que tras la marca de principal devolvía otro tipo), `MigracionTipoCentroTest` (cuenta las migraciones por nombre) y retirado TF-LW-CIU-15 (`crearDerivacion`, sustituido por TF-CIR-43).
- **Negativos comprobados** (quitando la protección, el test falla): TF-CIR-01, 02, 04, 08 (subconsulta del equipo en el scope), 10, 13, 17, 21, 24, 30, 34, 37, 40 y 43.
- `npm run build` y `ui:auditar` sin infracciones.
- Documentación: `modulo-intervencion.md` (§5.1, §5.2, §5.2.1, §5.7 «Derivación», §6.2 bis), `modulo-prestaciones.md`, `modulo-solicitudes-expedientes.md` RN-01, `modulo-asignacion.md` §4.5, addenda de Tipo de Centro (RN-17 ampliada), `modulo-usuarios-permisos.md` (rol `supervision`), `CLAUDE.md` §6 y tabla de estado de las instrucciones.

### Decisiones de implementación no previstas
- **Centro obligatorio en planes nuevos:** lo exige `PlanDeIntervencionService::crear()`, no el modelo, porque las factorías y los escenarios de demo crean planes sin centro (BACKLOG).
- **Plan principal sin referencia vigente:** lo puede abrir cualquier profesional adscrito al centro de la historia (las instrucciones solo hablan del TSR o la supervisión).
- **«Vigente» para «un plan por tipo»:** `borrador` o `activo`; `en_revision` es la versión sustituida de `crearNuevaVersion()` y no cuenta.
- **Propuesta de estado para `si`:** conseguido (las instrucciones no lo decían).
- **Resolver derivaciones y cambiar la referencia** exige supervisión **adscrita** al centro, no la de UO superiores: lo piden TF-CIR-21 y 37, aunque E1 citaba `supervisa()` (que incluye el ámbito). Puede afectar a la bandeja «sin referencia» resuelta por una coordinación superior (BACKLOG).
- **Identidad en la bandeja de derivaciones:** la supervisión adscrita al destino ve siempre la identidad (RN-19) aunque todavía no tenga acceso a la historia.
- **Lectura por plan para no protegidos:** también la da el rol `intervencion` con el centro del plan en su ámbito, igual que la salvedad de protegidos, para que la regla sea la misma.
- **`planActivo()` de la ficha** (apuntes y cita de seguimiento) pasa a ser el plan principal en curso o, si no hay, el último del que el usuario es responsable.
- **`Derivacion` sin `Versionable`:** el historial de `derivacion_eventos` cumple esa función, como en las citas. Motivos cifrados.
- Las alertas de derivaciones, plazas y referencia **no nombran a la persona**.

### Suite completa
- Antes de los ajustes finales: 1417 passed, 14 failed, 12 incomplete, 2 skipped. De los 14: el previo de `AutorizacionDatosTest`; 2 de `CuadranteSupervisorTest` (Agenda) que **también fallan sin los cambios de esta fase** (comprobado con `git stash`, probablemente por la fecha; al BACKLOG); y 11 de tests antiguos afectados por la fase, ya corregidos (ver arriba).
- **Tests de cierre** (tras los ajustes y Pint): Intervención, Ciudadanía, Supervisión, Prestaciones, `tests/Feature/Auditoria` y `tests/Feature/Acceso`: 592 passed, 1 incomplete (previo); unos 19 min.

---

## 2026-11-13 — Tipo de Centro: cierre de la fase (fase 2, paso 10)

### Cambios
- **TF-TCE-34** (`Modules/Centro/tests/Feature/TipoCentro/MigracionTipoCentroTest.php`): deshace las 11 migraciones desde `2026_11_12_100001`, carga datos con el esquema anterior (catálogo `centro.tipo`, centros con clave, `prestacion_tipo_centro` por clave, un `HorarioCentro`), vuelve a migrar y comprueba el estado (un tipo por clave con denominación, todo con tipo, catálogo antiguo inactivo, horarios intactos sin herencia, columnas de la addenda §6.2 fuera y `centros.horario` como accesor). Todo dentro de la transacción del test (DDL transaccional de PostgreSQL); el esquema de `vida_testing` queda intacto (comprobado). **En negativo:** sin desactivar el catálogo antiguo en la migración 100006, falla.
- Corregida la `down()` de `2026_11_12_100006`: no volvía a crear el índice único `(prestacion_id, tipo_centro)` que su `up()` borra, y no se podía volver a migrar. Solo afecta a la vuelta atrás; nada aplicado cambia.
- Tabla de estado de `2026-10-tipo-centro-tests.md` completa: TF-TCE-01 a 60 implementados y en verde.
- Documentación (§11 del diseño): `modulo-centros.md` (§2.1, §2.2 con la UO instrumental, §4.1), `modulo-asignacion.md` §8 (configuración del tipo, RN-21 y 23), `modulo-agenda.md` §2.1 (herencia y cierres), `modulo-prestaciones.md` (`tipo_centro_id`, `centro.tipo` retirado), `modulo-intervencion.md` (tipos de plan y fichas por tipo de centro), `modulo-usuarios-permisos.md` (ámbito por UO, RN-13 a 23, y resolutor de accesos a protegidos), `propuesta-requisitos-y-tareas-guiadas.md` §3 (selector y centro por defecto, decididos) y `documentacion-proyecto.md` (User, Prestaciones, Centro y Agenda). `CLAUDE.md`: los dos ficheros de Tipo de Centro, hechos.
- BACKLOG: cerrados «Tipo de Centro: en ejecución», «Bandeja de asignaciones con protegidos» (RN-19), lo de varias UO con varios centros de «Perfil horario por defecto: pendientes» (D1) y la extracción del centro del usuario (`CentroDeUsuario`); nuevo «Tipo de Centro: decisiones pendientes del diseño» con §13. «Borrar un usuario con casos vigentes» (D5) ya se cerró en el paso 8 quinquies.
- **Suite completa** (unos 54 min): 1388 passed, 12 incomplete, 1 skipped y 1 failed: el previo de `AutorizacionDatosTest` (FK de `audits`). Sin fallos nuevos.

### Criterios de finalización
- **Rama:** A (la asignación estaba implementada). **Mapeo de tipos:** el del paso 0 (decisiones del 12 nov, entrada de ese día).
- **Lectores fuera de `ConfiguracionEfectivaCentro`:** grep de `inscripcion_libre`, `centro_prestacion`, `centro_segmento_poblacion`, `centros.horario` y los campos de horario de `horarios_centro` en `app`, `Modules` y seeders, sin migraciones ni tests: solo quedan escrituras y definiciones (formularios de Filament, modelos, factories, `TipoCentroService` sobre los campos del propio tipo, `MigracionTipoCentro`) y lecturas a través del servicio (accesor `Centro::horario`, `CentroResource`, `PerfilHorarioPorDefectoService`). `HorarioLaboralService` de Mensajes usa su propio horario de catálogo, no el del centro.
- **Datos en la BD compartida:** 0 centros sin tipo (de 3), 0 filas de `prestacion_tipo_centro` sin tipo, 0 asignaciones de referencia sin centro; las migraciones `2026_11_13_100002` y `100003` aplicadas en staging.
- Ningún camino cambia `centros.tipo_centro_id` (inmutable en el modelo; el formulario no lo envía en edición) ni `tipos_centro.codigo` (el modelo lo rechaza; el formulario no lo envía).

## 2026-11-13 — Tipo de Centro: interfaz en Filament (fase 2, paso 9)

### Cambios
- `TipoCentroResource` (grupo «Centros y Servicios», solo administrador por `TipoCentroPolicy::create`/`update`): identificación (código en lectura tras el alta, denominación vigente e historial), UO funcional y público, catálogo (prestaciones en lectura con enlace a `PrestacionResource`, tipos de plan, fichas, restricción en centro), horarios con vigencia y cierres comunes, asignación, protocolo y centros del tipo en lectura. Alta con denominación y horario inicial por `TipoCentroService::crear()`; edición por `actualizar()` (solo lo que cambia). Acciones: «Programar nueva denominación», «Programar horario», «Añadir cierre» y «Dar de baja» (RN-11: con centros activos se rechaza y se notifica). Sin borrado.
- `CentroResource`: nombre y nombre corto en lectura tras el alta (historial de denominaciones y «Programar nueva denominación»); tipo de gestión, UO, dirección, código postal y coordenadas deshabilitados para quien no administra (la dirección del centro edita contacto, notas y protocolo); fuera el interruptor «Activo» y la fecha de cierre del formulario (baja por la acción «Dar de baja»); UO funcional entre lo «solo tipo»; protocolo y horario con insignia «Heredado del tipo»/«Personalizado» (hint de Filament) y «Volver al protocolo del tipo»/«Volver al horario del tipo»; cierres del tipo (lectura) y propios con «Añadir cierre» (avisa de las citas afectadas, que no se cancelan); exclusiones de catálogo solo si el tipo lo permite («Exclusiones de catálogo», por `ExclusionCatalogoService`).
- `App\Filament\Acciones\CentroTipoAcciones`: acciones y listados comunes a tipos y centros (renombrar, añadir cierre, dar de baja, historiales), todas por los servicios con su policy.
- `HorarioCentroResource` ya tenía el interruptor «Heredar horario del tipo» con el horario del tipo en lectura: sin cambios.
- Tests: `Modules/Centro/tests/Feature/TipoCentro/InterfazTipoCentroTest.php` (5): TF-TCE-31 por Filament, denominación y horario del tipo programados, la directora en el formulario del centro, exclusiones y renombrado y baja de un centro. **En negativo:** abriendo `TipoCentroResource` al backoffice falla TF-TCE-31; sin el deshabilitado de los campos, falla el de la directora.

### Decisiones
- No hay ficha pública del centro en Livewire: esa parte del paso 9 no aplica.
- La insignia de herencia es el *hint* nativo de Filament (sin clases propias).
- `ConfiguracionCentroPage` (Supervisión) cambia el nombre corto de la UO, no del centro: no afecta a las denominaciones y no se toca.
- No se pueden quitar cierres desde la interfaz (no estaba pedido; los cierres pasados no se tocan). Anotado en el BACKLOG.

## 2026-11-13 — Tipo de Centro: bajas de profesionales (fase 2, paso 8 quinquies; RN-20 a 23)

### Cambios
- **Sin borrado (RN-20):** `User::delete()`/`forceDelete()` y `Profesional::delete()`/`forceDelete()` lanzan `LogicException`. `UsuarioResource` y `ProfesionalResource` sin borrado (`canDelete()` y `canDeleteAny()` falsos, `getDeleteAuthorizationResponse()` deniega); la acción «Desactivar» (`App\Filament\Acciones\DesactivarAction`) sustituye a `DeleteAction` en las tablas y en las páginas de edición. Mi equipo (`EquipoPage`): «Dar de baja» pasa a «Desactivar» (sin fecha ni casilla «confirmo que se reasignarán»), con lo pendiente por centro y enlaces; el equipo ya no lista profesionales inactivos.
- Migración `2026_11_13_100003_add_desactivacion_to_users_table`: `users.desactivado_en` y `desactivado_por_id`. `User::estaDesactivado()`, scope `noDesactivados()`; `canAccessPanel()` falso para desactivados.
- Proveedor de autenticación `eloquent_activos` (`AppServiceProvider`, `config/auth.php`): excluye a los desactivados del login, la sesión, «recordarme» y Filament.
- `UsuarioUo`: no se borra (`LogicException`); al adelantar `fecha_fin` exige RN-21 (`SalidaCentroService::exigirSalidaLibre()`) y, guardada, cierra los perfiles horarios del usuario en ese centro. Repeater de adscripciones con `deletable(false)`; `EditUsuario::beforeSave()` comprueba RN-21 antes de guardar nada y notifica con enlaces.
- `Modules\Intervencion\Services\Asignacion\SalidaCentroService`: `pendientes()` (casos de referencia vigentes con `ActividadCasosService::historiasDe()` y citas confirmadas desde hoy en el centro), `exigirSalidaLibre()`, `cerrarAdscripcion()`, `cerrarPerfilesHorarios()`, `pendientesDeUsuario()`, `desactivarUsuario()` (RN-22: todos sus centros; cierra sus adscripciones), `desactivarProfesional()` y `puedeDesactivar()` (nunca a uno mismo; `adm_sistema`; `adm_usuarios` y `supervision` en su ámbito). `PendientesSalida` y `SalidaCentroBloqueada` (mensaje con el recuento y `enlaces()`: «Repartir casos» → Asignaciones, pestaña actividad; «Reasignar citas» → ausencias).
- **RN-23:** migración `2026_11_13_100002_centro_obligatorio_en_asignaciones_profesional` (rellena `centro_id` con el centro de la UO de la historia, 474 filas en la BD compartida, 371 vigentes, y lo hace `NOT NULL`; se detiene si alguna no tiene centro deducible). `AsignacionProfesional` toma el centro de la UO de la historia si no se indica. Fuera el respaldo por UO de `ActividadCasosService::casosVigentes()`; `AperturaHistoriaService` y `AsignacionReferenciaService::crear()` reciben el centro no nulo. Nada en el cambio de adscripción toca las asignaciones (comprobado: el único observer de `UsuarioUo` crea perfiles horarios).
- `PoolReferenciaService`: los usuarios desactivados no entran en el sorteo ni en el reparto. Actividad del equipo: «Dado de baja» también para desactivados.
- Tests: `Modules/Centro/tests/Feature/TipoCentro/BajasProfesionalesTest.php`, TF-TCE-55 a 59 con 56 bis (formulario de Filament) y 58 bis (RN-22). **En negativo:** sin la comprobación del modelo `UsuarioUo` fallan 56 y 58; sin el `delete()` de `User`, falla 55.
- Adaptados: `UsuarioSoftDeleteTest` → `UsuarioSinBorradoTest` (el borrado se rechaza; las cuentas borradas antes siguen ocultas), `UsuarioAutogestionTest` (acción `desactivar`), `SupervisionTest` D04 a D06 (desactivar en Mi equipo), y datos de prueba sin centro por RN-23 (`MisCasosPageTest`, `NavegacionTest`, `PanelRedaccionTest`); `ActividadCasosTest` y `RepartoCasosTest` simulan el «dado de baja» como borrado anterior a RN-20.
- Ejecutados: Usuarios, Supervisión, Centro, Agenda, Auth, Demo, Acceso y `FilamentPanelAccessTest` en verde (previos: 1+1+5 incomplete, 1 skipped); Intervención y Mensajes, 31 fallos de datos de prueba (RN-23 y borrado), corregidos y en verde (ficheros afectados: 35 + los de Asignación y Navegación). `npm run build` + `ui:auditar` sin infracciones.

### Decisiones
- **Decisiones del desarrollador (13 nov):** rellenar `centro_id` y hacerlo obligatorio; conservar el trait `SoftDeletes` solo para las 13 cuentas y 6 profesionales borrados antes (todos de prueba), que siguen ocultos, en lugar de convertirlos en desactivados.
- No había servicio que cerrase adscripciones (solo el repeater de Filament): RN-21 se exige en el modelo `UsuarioUo` para cubrir cualquier camino.
- Cerrar la salida de un centro cierra los perfiles horarios del profesional en él (`activo = false`, `vigente_hasta`): el sorteo y el reparto se basan en el perfil, no en la adscripción.
- Desactivar cierra las adscripciones vigentes con fecha de hoy. La desactivación es inmediata (sin fecha futura).
- Las citas que bloquean son las confirmadas con fecha desde hoy, en el centro y del profesional.
- Pendientes en el BACKLOG («Bajas de profesionales: lo que queda tras RN-20 a 23»): reactivar, vigencia hasta fin del día, hora de las citas, centro del enlace al reparto.

## 2026-11-13 — Tipo de Centro: personas protegidas (fase 2, paso 8 quater; RN-17 a 19)

### Cambios
- `AmbitoUsuario`: `uoIdsProtegidos()` (adscripciones directas vigentes; con rol `intervencion`, todo el ámbito: salvedad de RN-17), `gestionaHistoria()` (nivel 1: ámbito y, si la persona está protegida, RN-17), `gestionaProtegido()`, `historiaProtegida()` y `restringirHistoriasGestionables()` (subconsulta de historias que el usuario gestiona).
- `CiudadanoPolicy`, `HistoriaSocialPolicy` y `PlanDeIntervencionPolicy` (`view`, `update` y `delete`): el nivel 1 pasa por `gestionaHistoria()`; una persona protegida de un centro donde el usuario no la gestiona va al camino de la consulta con acceso aprobado.
- `AmbitoUoScope` (historias, personas, planes) y el scope de `Apunte`: la condición de ámbito es la subconsulta de historias gestionables.
- `BuscarCiudadanoPage`: el nivel de cada resultado sale de `gestionaHistoria()` (antes, solo de las adscripciones directas, distinto de las policies); el aviso de una solicitud de acceso va a cada resolutor de RN-18 y no al rol `supervision` de la UO.
- `App\Services\ResolucionAccesoProtegido`: `resolutores()` (director vigente del centro con cuenta; sin él, `supervision` adscrita directamente a la UO del centro), `puedeResolver()` (nadie resuelve la suya), `pendientesPara()`, `aprobar()` (caducidad futura; por defecto `dias_acceso_protegido`, 30 días) y `denegar()` (motivo obligatorio). Cada resolución se audita (`editar` con antes y después) y se avisa al solicitante.
- Migración `2026_11_13_100001_add_motivo_resolucion_to_accesos_protegidos` (columna nullable) y `AccesoProtegido::motivo_resolucion`.
- `AprobacionesPage`, pestaña «Accesos a expedientes»: lista las solicitudes que el usuario resuelve, con aprobar (fecha de caducidad) y denegar (motivo); la pestaña aparece también sin `tiene_colectivos_protegidos` si hay solicitudes que resolver.
- `AsignacionesPage::aplicar()`: una entrada de persona protegida solo la resuelve la supervisión adscrita al centro (RN-19); la identidad ya seguía a la policy.
- Tests: `Modules/Centro/tests/Feature/TipoCentro/PersonasProtegidasTest.php`, TF-TCE-48 a 54 (50 con aprobar y denegar) y 60, por scope, policy, listados y buscador. **En negativo** (sin la restricción de RN-17) fallan 48, 53 y 60.
- Adaptados: `BuscarCiudadanoPageTest` BUS-08 y 08b (el aviso va a la supervisión adscrita, sin director) y `AuditObserverTest` (el profesional crea apuntes: necesita centro por RN-15 del bloque anterior; no estaba en las suites de impacto).
- **Suite completa** (unos 46 min): 1373 passed, 12 incomplete, 1 skipped y 4 failed: el previo de `AutorizacionDatosTest` y los tres citados, corregidos (16 passed). `npm run build` + `ui:auditar` sin infracciones.

### Decisiones
- **Caducidad por defecto:** no había ninguna configurada; clave `dias_acceso_protegido` de `ConfiguracionService`, 30 días si no está.
- **Motivo de denegación** en una columna de la solicitud (antes no se guardaba en ningún sitio).
- **Nivel del buscador** alineado con las policies (RN-13): quien gestiona por la jerarquía superior ve nivel 1, no 2.
- RN-19 se comprueba con la adscripción al centro (`CentroDeUsuario::adscritoA()`), no con `gestionaProtegido()`: la salvedad de `intervencion` no alcanza a resolver la bandeja de supervisión.

## 2026-11-13 — Tipo de Centro: permisos de tipos y centros (fase 2, paso 8)

### Cambios
- `Modules\Centro\Policies\TipoCentroPolicy` y `CentroPolicy`, registradas en `CentroServiceProvider`. Tipos: lectura para el backoffice; crear, modificar, renombrar, horario, cierres comunes y baja, solo administrador; borrar, nunca. Centros: alta, renombrado y baja, solo administrador; editar, cierres, horario y exclusiones, el administrador o el director vigente del centro; ver, administrador, director o quien lo supervisa (D1.8); borrar, nunca.
- `Centro::esDirigidoPor()` y `scopeDirigidosPor()` (director vigente de `DirectorCentro` cuyo profesional es el del usuario). `User::accedeAlBackoffice()`, que ahora usa también `canAccessPanel()`.
- Servicios con la policy cuando hay actor: `CentroService` (`darDeAlta`, `actualizar`, `darDeBaja` y el nuevo `renombrar`), `TipoCentroService` (`crear`, `programarHorario`, `darDeBaja` y los nuevos `actualizar` y `renombrar`), `CierreCalendarioService::crear()` y `ExclusionCatalogoService` (`excluir` y `quitar`, que recibe un actor opcional). `CentroService::actualizar()` limita al director a `CAMPOS_DEL_DIRECTOR` (teléfono, email, web, notas y protocolo) y rechaza el resto sin cambiar nada.
- Filament: `CentroResource` crea y edita según la policy (antes, el permiso directo) y no borra; `EditCentro` guarda por `CentroService::actualizar()` solo los campos que cambian. `HorarioCentroResource` se abre al director, limitado a sus centros (listado, selector de centro y comprobación al guardar en `CreateHorarioCentro` y `EditHorarioCentro`).
- Tests: `Modules/Centro/tests/Feature/TipoCentro/PermisosTest.php`, TF-TCE-31, 32 y 33. **En negativo** (sin la autorización de los servicios ni el registro de las policies) fallan los tres.
- Suites: Centro, Prestaciones, Supervisión, Usuarios, Auth, acceso al panel y los tests de Filament de horarios, tipos de cita y retención: 354 passed, 2 incomplete y 1 skipped (previos).

### Decisiones
- **Administrador = permiso `centro.gestionar`** (solo lo tiene `adm_sistema`), el que ya usaba `CentroResource`, en lugar de comprobar el nombre del rol.
- **Sin actor, sin comprobación:** los servicios aceptan `actor = null` para migraciones, seeders y comandos; las pantallas pasan siempre el usuario.
- **Campos del director:** contacto (teléfono, email, web), descripción (`notas`) y protocolo. La dirección queda fuera: la usan la geocodificación y la asignación por domicilio.
- El horario de otro centro devuelve 404 al director (el listado ya lo excluye), no 403.
- La parte de Filament de TF-TCE-31 espera a `TipoCentroResource` (paso 9), igual que deshabilitar en el formulario de centro lo que el director no puede editar.

## 2026-11-13 — Tipo de Centro: centro de trabajo en la barra superior y centro supervisado (fase 2, paso 8 ter cerrado)

### Cambios
- `App\Livewire\CentroTrabajo` (`<livewire:centro-trabajo />` en `operativo-shell`, junto al menú del usuario): «Siglas del tipo · Nombre corto del centro» del centro activo; desplegable para cambiar si el usuario tiene más de un centro (`CentroDeUsuario::elegir()`, y recarga la pantalla); con un solo centro, el texto sin selector; sin centro, el nombre de su UO. Solo clases de Bootstrap.
- `CentroDeUsuario`: `centrosSupervisables()` (los del ámbito con rol `supervision`; si no, los propios), `centroSupervisado()` (elegido en el selector de supervisión → activo si es supervisable → el primero por nombre) y `elegirSupervisado()` (rechaza un centro fuera del ámbito). `elegir()` borra la elección de supervisión.
- `Modules\Supervision\Http\Livewire\SelectorCentro` (`supervision.selector-centro`), arriba del menú de Supervisión, compartido por las pantallas de Supervisión y de Agenda del supervisor: solo aparece si hay más de un centro supervisable.
- Pantallas de supervisión con `centroSupervisado()`: Inicio (también el ratio de carga), Mi equipo (también el alta de profesional), Configuración, Asignaciones, Cuadrante, Actividades, menú de Supervisión y, en Agenda, cuadrante, ausencias, excepciones, eventos, reasignación y menú del supervisor (estas cuatro aún buscaban el centro por la primera UO).
- `EquipoPage::profesionales()`: el equipo del centro supervisado (sin centro, todo el ámbito, como antes). Quien trabaja en dos centros aparece en los dos.
- `CuadranteMesComponent::autorizarAcceso()`: supervisión entra en los centros que `supervisa()` (ámbito por las dos jerarquías); antes, solo en los de sus UO activas, y la coordinación funcional recibía 403.
- Tests: `Modules/Centro/tests/Feature/TipoCentro/CentroTrabajoInterfazTest.php`, TF-TCE-43 (barra y elección) y 44 (Mi equipo con el centro activo; selector de supervisión y cuadrante dentro y fuera del ámbito). **En negativo**, sin los cambios de `EquipoPage` y del cuadrante fallan los dos de TF-TCE-44.
- `SupervisionTest`: la UO del supervisor (`uoHija`, tipo `centro`) tiene ya su centro; seis tests leían «la primera UO» del supervisor y ahora leen el centro supervisado.
- Suites: Supervisión, Agenda, Centro, Livewire de Intervención, Ciudadanía, Acceso y Auth: 693 passed, 2 incomplete y 1 skipped (previos) y los 6 de `SupervisionTest`, corregidos (`Modules/Supervision/tests` 38 passed). `npm run build` + `ui:auditar` sin infracciones.

### Decisiones
- **Centro supervisado por defecto:** si el supervisor no tiene centro activo en su ámbito (p. ej. una coordinación funcional sin centros propios), el primero por nombre, para que las pantallas no queden vacías; el selector permite cambiarlo.
- **Selector de supervisión en el menú lateral** y no encima de cada pantalla: está en todas las de supervisión sin cambiar su alto (las `op-page--fill` tienen alto fijo). Es un componente aparte porque el menú se refresca cada 60 s.
- Al cambiar de centro de trabajo en la barra, las pantallas de supervisión vuelven a seguirlo.

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
