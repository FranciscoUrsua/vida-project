# BACKLOG — VIDA 360

Registro de ideas, mejoras, decisiones pendientes y deuda técnica.
Actualizar con fecha y contexto breve al añadir cada entrada. Lo cerrado se retira (queda en el CHANGELOG y en git).
Revisado entero el 2026-11-27.

---

# 🔧 DEUDA TÉCNICA Y PENDIENTES

---

**Envío de correos: VIDA no envía correo electrónico** — 2026-11-27
Transversal / autenticación / notificaciones
- La aplicación no tiene configurado el envío de correo (ni servidor SMTP corporativo ni proveedor). Sin él no hay recuperación de contraseña estándar (enlace con token por correo, `Password::sendResetLink`), ni avisos por correo de alertas o citas.
- **Atajo actual (27 nov 2026, decisión del desarrollador):** «¿Olvidaste tu contraseña?» avisa dentro de VIDA a la supervisión del centro y a `adm_usuarios` de su ámbito (`App\Services\Auth\AvisoContrasenaOlvidada`); la supervisión comprueba la identidad en persona y `adm_usuarios` asigna una contraseña nueva en el backoffice. Solo se avisa: la supervisión no restablece contraseñas.
- Por decidir: servidor de correo (¿SMTP del Ayuntamiento?), remitente, plantillas y si la recuperación pasa a ser por correo o por el directorio corporativo (LDAP/AD, `modulo-usuarios-permisos.md`). Cuando exista el correo, sustituir el atajo por el flujo estándar de Laravel y obligar a cambiar la contraseña asignada por `adm_usuarios` en el primer acceso.

---

**Contraseñas: política definitiva, auditoría y recuperación** — 2026-11-27
Usuarios / autenticación / seguridad
- El 27 nov se añadió el cambio de la propia contraseña (`App\Livewire\CambiarContrasena`) con una política provisional: 12 caracteres con letras y números, distinta de la actual, cierre de las demás sesiones. Falta: (1) confirmar la política con el Ayuntamiento (o dejarla al directorio corporativo si se federa la identidad, `modulo-usuarios-permisos.md`); (2) dejar en la auditoría los cambios de contraseña y los inicios de sesión, que hoy no se registran. La recuperación de contraseña es provisional: ver «Envío de correos».

---

**Campos `numero` de las fichas sin mínimo ni máximo** — 2026-11-27
Intervención (fichas) / ficha sociodemográfica
- El tipo `numero` del schema de `TipoFicha` no admite límites. En la ficha «Datos sociodemográficos», `anio_llegada_espana` («año con cuatro cifras») y `numero_pagas` aceptan cualquier número; solo lo indica la ayuda. Valorar `min`/`max` en el schema, en `TipoFichaResource`, en `<x-ficha.campos>` y en `ValidadorDatosFicha::validarTipos()`.

---

**Formato de fecha abreviado: «14 mar. 2026» frente al design system** — 2026-11-27
UI operativa / transversal
- `translatedFormat('j M Y')` con el locale `es` de Carbon pinta el mes con punto («14 mar. 2026»), y el design system fija «14 abr 2026». Lo usan muchas vistas (ficha en lectura, tarjetas de la ficha del ciudadano…). Decidir si se corrige con un helper de formato común o se acepta el punto y se actualiza el design system. Visto al escribir los tests del bloque D de la ficha sociodemográfica (comparan con el mismo formato, no con un literal).

---

**Cifrado de fichas: auditorías antiguas en claro y analítica** — 2026-11-27
Intervención (fichas) / Auditoría / seguridad
- Desde el 27 nov, `fichas.datos` y `fichas.notas` van cifrados y `Versionable`/`AuditObserver` copian los atributos cifrados sin descifrar. La migración cifró las fichas y sus versiones, pero **no** las filas antiguas de `audits`: las ediciones anteriores de fichas, y de cualquier modelo con campos cifrados (p. ej. `Ciudadano`), tienen en `datos_antes` los valores descifrados. Decidir si se cifran o se purgan esos `datos_antes` (la tabla de auditoría se trata como inmutable).
- La analítica de las fichas (BigQuery, decisiones técnicas §19.2) tendrá que descifrar en la aplicación: no hay consultas SQL posibles sobre `datos`.

---

**Ficha sociodemográfica: pendientes de datos y de schema** — 2026-11-26
Catálogos / Intervención (fichas de persona)
- `comun.pais` sin `equivalencias.ine` (código de país del padrón): la tabla de Países/Nacionalidades de IDA-Padrón solo se descarga desde su utilidad web y `arce.ine.es` no respondía desde el entorno de desarrollo. Decisión del desarrollador: seguir sin ella. Cuando haya fichero, añadirlo a `database/data/catalogos/fuentes/`, rellenar `ine` en la normalización y volver a cargar (la carga es idempotente). Con ese fichero entra también Taiwán, que M49 no incluye.
- Claves especiales de `comun.pais` (`XXA`, `XXB`, `XXC`, `XXX`, `XKX`): el Doc 9303 parte 3 de la OACI devuelve 403 al descargarlo. Pendiente de que el desarrollador deje el PDF; sin él no se cargan.
- `comun.idioma` no incluye las lenguas de signos (la española, `ssp`, solo tiene código ISO 639-3): si se quieren recoger, decidir si el catálogo admite claves 639-3.
- La «visibilidad condicional» de campos que cita el docblock de `TipoFicha` no está implementada. `anio_llegada_espana` (solo si `pais_nacimiento` ≠ `ESP`) queda como campo normal con ayuda. Decidir si se implementa la visibilidad condicional en schema, backoffice y `<x-ficha.campos>`.

---

**`ApuntePolicy::view` no mira el acceso a la Historia** — 2026-11-26
Intervención (apuntes) / seguridad
- `Modules\Intervencion\Policies\ApuntePolicy::view()` devuelve `true` para cualquier apunte no privado: quien protege es solo el scope `ambito_uo` de `Apunte`. Un apunte cargado sin ese scope (`withoutGlobalScopes()`) y autorizado con la policy quedaría a la vista de cualquiera, también del profesional de encargo (EA-7). Visto en el bloque B de las actuaciones del encargo, donde no se ha tocado («el resto de las vías no cambia»). Propuesta: `view` = autor, o `HistoriaSocialPolicy::view` de su historia, o `ActuacionesEncargo::esVisible()`; revisar antes a quién afecta (supervisión sin `historia.leer`, R2).
- Hay además una `Modules\Usuarios\Policies\ApuntePolicy` registrada para `App\Models\Apunte` con campos que no existen (`privada`, `profesional_id`): parece código muerto. Confirmar y retirar.

---

**Casos de `abogada.ciam` pendientes de reparto en staging** — 2026-11-25
Intervención / Asignación
- HS 941 a 944 tienen como referencia a `abogada.ciam`, que ya no tiene rol intervención (se lo quitaron antes de que existiera el bloqueo de RN-03 bis). Decidido: la supervisión del CIAM Puente de Vallecas (p. ej. `dir.ciam`) reparte sus casos desde Supervisión → Asignaciones → actividad (ensayado: propone a `ts1`, `ts2` y `ts3`). Siguen pendientes a 27 nov (las cuatro con referencia a `abogada.ciam`). El encargo profesional ya existe (25 nov): si la abogada debe seguir con esas personas, el TSR que reciba los casos puede hacerle un encargo.

---

**Tablas operativas: pendientes tras la ordenación por cabecera** — 2026-11-25
Transversal
- Ordenación de prioridad media sin hacer: Actividades (nombre, tipo, fecha de alta, estado), Excepciones y Eventos del supervisor, Control de alertas (fecha, atendidas) y Mi equipo. Con `OrdenaPorColumnas` y `<x-op.th-orden>` es poco trabajo por tabla.
- Listados sin paginar que crecen: la pestaña «Enviadas» de la bandeja de tramitación (acumula para siempre), el catálogo de Actividades, y Control de alertas, que en «Recientes» corta a 100 sin avisar.
- `ui:auditar` no detecta clases decididas con un ternario dentro de `{{ }}` en un atributo `class` (lo había en el pie de Mis casos; sigue en el estado de Recursos → Activas, `recursos-page.blade.php`). Ampliar el auditor y pasar ese estado a `Tono`.
- `TipoSlotResource` es un catálogo global y lo modifica cualquier supervisión de centro (T-AGS-02). Confirmar si debe ser así o pasar a `adm_sistema`.

---

**Domicilio con el callejero oficial de Madrid** — 2026-11-25
Ciudadanía / Centro (asignación)
- (24 nov) Pasos 0, 1, 2 y 5 hechos; callejero cargado y adaptador activo en staging. En producción: primera importación y después activar `geocoder.proveedor = callejero` (la migración `2026_11_25_100004` solo lo hace si el callejero ya está cargado).
- (24 nov) Los nombres de vía con cifras («Cañada Real Sector 6», «26 de Abril») no se analizan bien en texto libre: el parser toma la primera cifra como número. Con el autocompletado (25 nov) se eligen sin problema.
- (25 nov) **Ámbitos territoriales sin configurar en staging:** CSS Arganzuela y CSS Entrevías tienen adscripción por domicilio pero ningún ámbito (barrios, secciones o distritos), y `ambitos_territoriales` está vacía (comprobado de nuevo el 27 nov). Sin ellos la asignación por domicilio no resuelve a nadie aunque la dirección tenga sección. Configurarlos en Filament (`CentroResource`) y, después, reevaluar a las 74 personas con domicilio normalizado.
- (25 nov) Quedan 96 direcciones de texto libre sin normalizar en staging (58 sin número, 38 que no están en el callejero): corregirlas a mano desde la ficha con el autocompletado si interesa.
- (25 nov) El autocompletado solo está en la persona; los centros (`CentroResource`, Filament) siguen con texto libre.
- (24 nov) Sin aviso si la importación semanal deja de funcionar: `callejero_importaciones` registra cada carga, pero nadie mira si la última tiene más de 40 días. Añadirlo a la vigilancia del sistema cuando exista.
- (24 nov) Fuera del municipio, sin normalizar (decisión 4 de las instrucciones); los números del callejero cambian de mes a mes, y las direcciones ya normalizadas no se revisan si su portal desaparece.

---

**Backoffice: restos tras cerrar la configuración a `adm_usuarios` y la supervisión** — 2026-11-25
Módulo: Usuarios / Filament
- **`tramitacion` con permisos de apuntes en la BD compartida:** tiene `apunte.leer`, `apunte.leer_propio`, `apunte.leer_ajeno`, `atencion.crear` y `atencion.leer_ajeno`, que `RolesSeeder` no le da y que contradicen `roles-consulta-basica-tramitacion.md` («sin acceso a apuntes»). Pendiente de confirmar con el desarrollador antes de retirarlos (migración como la `2026_11_25_100001`).
- **La edición de roles no se audita:** `RolResource` cambia `role_has_permissions` sin dejar rastro; así entraron sin explicación `centro.gestionar` y `configuracion.acceder` en `supervision`. Auditar los cambios de permisos de un rol y avisar cuando la BD se aparte de `RolesSeeder`.
- **`DocumentoResource` para la supervisión:** lista los documentos dados de alta por usuarios de su UO, aunque desde R2 la supervisión sola no ve documentos en la ficha. Decidir si se cierra también aquí.
- **Agendas sin ámbito:** `PerfilHorarioProfesionalResource`, `ExcepcionProfesionalResource` y `CuadranteMesResource` no filtran por la UO de `adm_usuarios` («gestión de agendas de los usuarios de su UO»).

---

**Auditoría de accesos: escala del filtro por ciudadano** — 2026-11-24
Módulo: Supervisión
- El filtro y el orden por ciudadano descifran en PHP los nombres de todos los ciudadanos con historia en el ámbito del supervisor. Con miles de historias por UO convendrá un índice de búsqueda (blind index o tokens con hash), el mismo que necesitan el buscador y `MotorMatching` (TODO `fecha_nacimiento_hash`).

---

**Supervisión: pantalla «Ciudadanos del centro»** — 2026-11-23
Módulo: Supervisión
- Listado de las personas asignadas al centro, filtrable, con datos para la gestión: cita pendiente, fecha de la última atención, plan activo, colectivo (mayor, menor, mujer…). Sin acceso a la Historia Social salvo con `intervencion`. Pendiente de diseño. Origen: EST-08, ronda 1 de staging.

---

**Barrido `role:` → permisos en todas las rutas operativas** — 2026-11-24
Transversal (encargo siguiente a la ronda 1)
- Con R4 pasaron a permisos las rutas de las pantallas del menú (`supervision.*`, `agenda.supervisor.*`, agenda/casos/mensajes/recursos de Intervención, `ciudadania.*`, bandeja de tramitación). Quedan con `role:`: Historia Social, plan, valoración, escala, ficha de valoración y `intervencion.buscar.index` (`Modules/Intervencion/routes/web.php`), informes (`Modules/Documentos/routes/web.php`) y la solicitud de prestación (`role:intervencion|supervision|tramitacion`).
- Añadir un test de arquitectura que falle si una ruta operativa usa el middleware `role:`.
- Quedan comprobaciones de rol dentro del código que deberían pasar a permiso o policy: `CiudadanoPolicy::create()` niega el alta a cualquiera con `supervision` aunque tenga `intervencion` (`dir.ciam` y `dir.css` no ven «Alta de ciudadano/a»), `FichaCiudadanoPage::puedeVerHistoria()` (`hasRole('intervencion')`), `User::layoutOperativo()` (solo el título de la barra) y `User::accedeAlBackoffice()`.

---

**`consulta_profesional` y encargo profesional: lo que queda** — 2026-11-25 (antes 2026-11-24)
Módulos: Intervención (derivaciones), Supervisión, Documentos, Agenda, Mensajes
- Hechos los bloques A a F (`docs/instrucciones-cli/2026-11-encargo-profesional.md`, cerrada el 25 nov): el TSR encarga desde la Historia Social o el plan principal, la supervisión del destino asigna en la bandeja y `consulta_profesional` lo trabaja en «Mis derivaciones», con ficha y buscador limitados a sus encargos. Permisos retirados por la migración `2026_11_25_200002`.
- **Actuaciones del profesional de encargo** (petición del 25 nov): anotaciones, fichas, escalas e informes desde el encargo, limitados por el área del encargo y visibles en la Historia Social. Instrucciones aprobadas: `docs/instrucciones-cli/2026-11-encargo-actuaciones.md` (bloques A a F, TF-EAC-01 a 36). Hechas el 26 nov (bloques A a F).
- **Informes del encargo: anular un firmado.** El descarte del borrador ya existe (`EncargoPage::eliminarBorrador()`, bloque F). Falta anular un firmado desde el encargo, si hace falta, con la misma regla que `InformesHistorial` (autor, motivo).
- **Herramientas de prueba del área jurídica** (26 nov): la ficha «Valoración jurídica inicial (prueba)», la escala `prueba_riesgo_vivienda` (sin validar) y la plantilla «Informe jurídico del encargo (prueba)», creadas en la BD compartida con `HerramientasPruebaAreaJuridicaSeeder` (no está en `DatabaseSeeder`). Retirarlas (desactivarlas) antes de la 1.0 o sustituirlas por las herramientas reales de cada área.
- **Plantillas del encargo en el asistente de la Historia:** el asistente de la Historia Social ofrece toda plantilla activa de la UO del profesional y sus ancestros, también las pensadas para un encargo (con la fuente `encargo.datos`, que fuera de un encargo sale vacía). Decidir si se excluyen del asistente de la Historia las plantillas con `encargo.datos` o si se marcan las plantillas como «de encargo».
- **Pre-relleno de fichas y serie temporal de escalas** (decisión del 26 nov, bloque D): no existen en la valoración de la Historia ni en el encargo. Desde el 27 nov el pre-relleno sí se usa en las fichas de la persona (`RegistrarFichaPersonaPage`, `Ficha::prerellenarConAvisos()`). Si se añaden, desde un encargo solo pueden proponer o mostrar lo del propio profesional en sus encargos de esa persona (EA-8).
- **PostgreSQL local: `max_locks_per_transaction`.** Con 4 procesos de paratest, el borrado de tablas de las BD paralelas falla a veces con «out of shared memory» (25 nov, 79 errores antes de ejecutar los tests; con 2 procesos, todo en verde). Subir `max_locks_per_transaction` en el PostgreSQL local (p. ej. 256) o usar 2 procesos en local.
- **Test inestable TF-AUTH-17** (`tests/Feature/Auth/AutenticacionTest.php`): `assertDontSee('JU')` busca en toda la página, token CSRF aleatorio incluido; falló una vez el 25 nov y pasó al repetirlo. Acotarlo al avatar.
- Bloque E, detalle menor: en la agenda, la cita de una persona protegida cuyo encargo ya se cerró sigue mostrando su nombre (la agenda no enmascara; «Atender» lleva a un encargo que responde 403).
- Fuera de alcance, para cuando haga falta: reasignar un encargo aceptado a otro profesional (hoy se anula y se encarga de nuevo).

---

**Pruebas en staging, ronda 2** — 2026-11-23
Transversal
- Repetir INF-01, INF-08, EST-02 a EST-09 y VEN-01 a VEN-05 tras las correcciones de la ronda 1 (`docs/instrucciones-cli/2026-11-correcciones-staging-ronda1.md`). Desde el 24 nov (pasos 1 y 6, R4) se pueden repetir todos. Comprobar además la preferencia de página de inicio con `dir.ciam` y `dir.css`, y que `coo.ciam` ve la ficha en lectura sin documentos ni enlace a la Historia Social.

---

**Correcciones de la ronda 1 de staging: pendiente** — 2026-11-23 (actualizada el 2026-11-27)
Módulos: Usuarios y permisos, Supervisión, transversal
- **Supervisión sola sigue viendo prestaciones y atenciones en la ficha** (`solicitud.ver`, `atencion.leer`): R2 no los incluye en su lista de bloques, pero tampoco los excluye. Decidir si se ocultan.
- **Ficha 14 huérfana en staging:** valoración de `tec.ciam` del 19 nov guardada sin apunte (EST-04). No se ha borrado; el defecto que la produjo (sin transacción) está corregido.
- **`adm1.css` tiene `consulta_basica` en Spatie pero no en `usuario_rol`**: datos de staging inconsistentes; no se han tocado.
- **`APP_DEBUG=true` en staging:** un 500 sigue enseñando la traza de Laravel en vez de la pantalla propia. Cambiarlo, a decisión del desarrollador.
- **Excepción `supervisa` al abrir el plan principal** (`PlanDeIntervencionService::exigirAperturaDelPrincipal`): la supervisión del centro lo abre sin estar adscrita. Con R1 la pantalla ya no se abre a quien no tiene centro; revisar si la excepción del servicio debe quedar.
- **Otros `abort(403, 'mensaje')`** (informes, documentos, configuración de UO, equipo): la pantalla propia muestra el texto genérico. Si alguno debe enseñar su motivo, pasarlo a `ActuacionNoPermitida` o a una excepción equivalente.

---

**Agenda y protegidos: huecos encontrados al preparar las pruebas** — 2026-11-18
Módulos: Agenda, Ciudadanía
- **Un cuadrante publicado no incorpora al personal nuevo:** `CuadranteSupervisorPage::regenerar()` solo funciona en borrador, y `agenda:publicar-cuadrantes` no toca los centros que ya tienen cuadrante. Quien se incorpora a un centro a mitad de mes no tiene huecos hasta el mes siguiente (le pasó al CSS Entrevías: cuadrantes vacíos, corregidos a mano el 18 nov). Decidir cómo se añaden líneas y huecos a un cuadrante publicado sin tocar las citas que ya hay.
- **Ninguna pantalla marca a una persona como protegida:** `colectivo_extra_protegido` (el indicador que manda en RN-17) solo se lee; el alta pone `es_vvg`, pero no el indicador de protección. A Marta PRUEBA se le puso por script. Decidir quién lo marca y dónde (¿el alta VVG debería ponerlo?).

---

**Prestaciones: el seeder ya no coincide con el catálogo de staging** — 2026-11-18
Módulo: Prestaciones
- **Riesgo:** `PrestacionesSeeder` (lo llama `DatabaseSeeder`) hace `updateOrCreate` por código con las 49 prestaciones de prueba. Si se ejecuta sobre la BD compartida, **sobrescribe 25 prestaciones de la guía** con otros datos (mismos códigos, otra prestación) y vuelve a crear las otras 24. No lanzar `db:seed` ni nada que llame a `DatabaseSeeder` en la BD compartida hasta resolverlo.
- Decidir si el seeder pasa a cargar `docs/referencia/prestaciones-guia-2026.json` (las 113 de la guía) o queda como conjunto de prueba con códigos propios que no choquen. Igual con el catálogo `prestacion.categoria` del seeder de catálogos, que tiene las categorías antiguas.
- La prestación `DER-PIA` (derivación de prueba al PIA) que crea el seeder no está en staging: para probar la derivación hay que configurar una en Filament.
- Revisar a mano las vías de acceso y los tipos de centro de las 113, asignados con una regla (ver CHANGELOG del 18 nov). La extracción del PDF puede tener algún párrafo colado entre columnas al cambiar de página; se corrigieron los detectados (060312, palabras partidas).

---

**Agenda: generación de cuadrantes y slots (incidencia del 16 nov)** — 2026-11-16
Módulos: Agenda, Centro
- **Planificador en el servidor:** `agenda:publicar-cuadrantes` (16 nov, programado el día 20) depende de él. **Comprobar que el servidor ejecuta el planificador** (`* * * * * cd /var/www/vida-project/vida && php artisan schedule:run` en el crontab de `www-data`); si no, ni esta tarea ni el cierre de citas ni la expiración de slots se ejecutan. Pendiente: avisar a la supervisión de los centros saltados (sin horario, sin tipos de slot o sin perfiles).
- **Slots solapados con varios tipos de slot:** `SlotMaterializadorService` crea, para cada franja, slots de cada tipo enlazado al horario. Con más de un tipo, un profesional tiene huecos superpuestos a la misma hora y podría recibir dos citas a la vez. Hoy los horarios de staging solo enlazan Entrevista. Decidir cómo reparte el cuadrante las franjas entre tipos.
- **Fallos silenciosos del materializador:** sin horario vigente o sin tipos de slot devuelve 0 sin avisar; la publicación debería advertirlo al supervisor.

---

**Aprobación de roles: pendientes** — 2026-11-17
Módulos: Usuarios, Supervisión
- **Ergonomía (petición del desarrollador):** aprobar y denegar desde la pantalla de usuarios de Filament (p. ej. una acción en la fila del usuario o en su ficha con la solicitud pendiente), no solo desde el escritorio. Reutilizar `ResolucionRolesService`.
- **Con un solo `adm_sistema`**, sus propias asignaciones con aprobación previa no las resuelve nadie cuando no hay supervisor competente (caso de #41, aprobada a mano el 17 nov). Opciones: un segundo `adm_sistema`, o que `adm_usuarios` haga las asignaciones y `adm_sistema` las apruebe.
- §2.8 dice «el supervisor de la UO superior», pero la regla (y la alerta de `AsignacionRolesService`, que va a la supervisión de la UO de la primera adscripción vigente) (desde antes) admite también la supervisión de la misma UO: la dirección de un centro aprueba al coordinador de su centro. Confirmar si se quiere así.
- Un supervisor que además es `adm_usuarios` puede asignar un rol y aprobarlo él (no es su propia solicitud): solo `adm_sistema` tiene la regla de «no lo que asignó él». Valorar extenderla a todos (en instalaciones pequeñas puede no haber un segundo supervisor).

---

**Tras la limpieza de @demo.es en staging (17 nov)** — 2026-11-17
Módulos: Usuarios, Intervención
- Copia previa en `~/backups/vida-20261117-1111-antes-limpieza-demo-es.dump`: borrarla cuando se confirme que no hace falta nada de lo borrado.
- 2 profesionales sin usuario (de antes de la limpieza): revisar si sobran.
- Las 58 historias compartidas perdieron los planes y el historial hechos por los @demo.es; si alguna prueba dependía de ellos, rehacerla.

---

**Fichas de valoración vaciadas por Filament (incidencia del 17 nov)** — 2026-11-17
Módulos: Intervención, Filament
- Arreglado el guardado (ver `CHANGELOG-112026.md`). Restaurados el 17 nov, con la conformidad del desarrollador, #1 «Situación económica» (5 campos, copia de la ficha 9) y #2 «Situación de vivienda» (6 campos, copia de la ficha 8). **Pendiente, a mano en Filament:** los campos añadidos después a #2 (hoy tiene 6). #4 «Madurez personal» ya no existe en la BD (27 nov). Las fichas 10 a 13 se cumplimentaron con el schema vacío.
- `TipoFicha` no tiene `Versionable` ni auditoría: un cambio de schema no deja rastro. Valorar versionarlo (principio 3.5).

---

**Fase 6 (actividades grupales con inscripción): pendientes** — 2026-11-17
Módulos: Centro, Supervisión, Intervención, Ciudadanía
- **Lista de espera de actividades** (F6-3): con el aforo de la vía completo, la inscripción o la prescripción se rechaza. Diseñar la lista de espera por sesión junto a la de plazas (`modulo-solicitudes-expedientes.md` §8).
- **`demo:load` crea prescripciones a sesiones sin inscripción:** `DemoContextoAditivo::inscribirEnActividades()` escribe `Prescripcion::create()` directamente, sin `inscripciones_sesion` ni control de aforo. La migración de la fase migró las existentes (F6-9), pero una carga nueva vuelve a dejarlas sin plaza. Pasar el loader por `PrescripcionService::crear()` (o crear la inscripción) y añadir `inscripciones_sesion` a la futura purga por etiqueta.
- **Quién puede prescribir:** `PrescribirRecursoModal` no comprueba permisos al confirmar (ni para plazas ni para sesiones); F6-2 dice «cualquier profesional que pueda editar el plan». Tampoco guarda `plan_intervencion_id` (siempre nulo), así que la prestación de la persona nacida de una prescripción no queda ligada al plan.
- **Prestación de la persona al quedarse sin inscripciones** (F6-10): no se finaliza sola; la finaliza el profesional. Valorar un aviso cuando la persona ya no tiene inscripciones vigentes en la actividad.
- **Baja de actividad con sesiones futuras:** se ha decidido que la baja cancela sus sesiones programadas futuras con «Actividad dada de baja: <motivo>» y avisa (las instrucciones no lo decían). Confirmar con el desarrollador.
- **Probar en staging** tras el despliegue (migración `2026_11_16_110001`): en Supervisión → Actividades, vincular una prestación a una actividad, ver inscritos, cancelar una sesión y dar de baja una actividad; en la ficha de una persona, «Inscribir en actividad»; desde la Historia Social, «Prescribir recurso» → «Actividad grupal (sesión)».

---

**Fase 5 (informes desde la interfaz operativa): pendientes** — 2026-11-16
Módulos: Documentos, Intervención
- **Activar AutoFirma integrada** (F5-1 b): está preparada e inactiva (`DOCUMENTOS_AUTOFIRMA_ACTIVA=false`). Probarla en puestos reales con certificado de empleado público siguiendo `docs/decisiones-tecnicas.md` §17, copiar `autoscript.js` en `public/vendor/autofirma/` y activarla por entorno.
- **Validación criptográfica completa y número de colegiado:** `ValidadorFirmaPdf` solo comprueba integridad, páginas e identificador (F5-2). Falta la cadena de confianza (CA de la FNMT o @firma, CRL/OCSP), comprobar que el titular del certificado es el autor y extraer el número de colegiado (`informes.numero_colegiado_firmante`, hoy vacío). Valorar el servicio @firma de la AGE como validador.
- **Probar en staging** tras el despliegue: redactar un informe desde la caja de herramientas «Informes» de la Historia Social, vista previa, descargar el PDF para firmar, firmarlo con AutoFirma de escritorio y subirlo; anularlo; subir y sustituir el PISO firmado desde un plan principal. La migración `2026_11_16_100001` (firmante, baja lógica de borradores, `piso_firmados` con sustitución y tipo `piso_firmado`) y la `100002` (`parametros_informe`) se aplican con el despliegue; los parámetros de ejemplo, con `php artisan db:seed --class="Modules\Documentos\Database\Seeders\ParametroInformeSeeder"` si se quieren.
- **Fuentes automáticas sin resolver:** `ciudadano.unidad_convivencia`, `historia_social.prestaciones_historico`, `historia_social.plan_activo` y `escalas.historico_barthel` salen «Sin datos disponibles». También siguen a «—» los merge tags de expediente, plan, centro y número de colegiado (`construirMapaValores()`).
- **Plantillas de staging:** las dos que hay (#1 «Certificado vulnerabilidad», #2 «Informe psicológico») están en la UO 3. El asistente solo ofrece las de las UO del profesional y sus ancestros: comprobar que llegan a los centros donde se van a usar.
- **Editor de texto libre:** es el `contenteditable` de la síntesis del plan (negrita, cursiva, lista, `document.execCommand`, en desuso en los navegadores). Si se necesita más (tablas, insertar variables con un clic), valorar un editor de verdad empaquetado con Vite.
- **El autor como firmante:** hoy cualquier certificado íntegro vale; ligar el titular del certificado al profesional (DNI del certificado frente al de la ficha) cuando se haga la validación completa.
- **Parámetros configurables por UO** — actualmente los parámetros de `parametros_informe` son globales. Variables como `{{ distrito }}` o `{{ nombre_centro }}` podrían necesitar valores distintos según la UO del autor del informe. Diseño previsto: añadir `unidad_organizativa_id nullable` a `parametros_informe` y aplicar resolución jerárquica igual que en `EstiloInforme` — buscar en la UO del autor y subir por la jerarquía hasta encontrar valor. Si no hay valor en ningún nivel, usar el parámetro global (sin UO).

---

**Fase 4 (atención administrativa): pendientes** — 2026-11-15
Módulos: Ciudadanía, Agenda, Documentos, Intervención, Prestaciones
- **Probar en staging** con un usuario `consulta_basica` y otro `tramitacion` adscritos a un centro: inicio en el buscador, ficha (estado de la historia, citas, documentos del día), alta, relaciones, citación y retirada de un documento. La migración de permisos y la de retirada se aplican con el despliegue.
- **Layout de la ficha y del buscador para `tramitacion`:** `FichaCiudadanoPage`, `BuscarCiudadanoPage` y `AltaCiudadano` fijan `layouts.operativo`, así que tramitación los ve con el menú operativo (que ya solo muestra búsqueda, alta y citación sin `intervencion`). La citación ya usa su layout (`User::layoutOperativo()`); extenderlo a esas tres pantallas.
- **Decisiones de interfaz que aún miran el rol en la ficha y el alta:** «Ir a HS» y el menú operativo reproducen el middleware `role:intervencion` de las rutas de Intervención (documentado); el tipo de atención del modal «Nueva atención» se oculta a `consulta_basica` por rol, y `AltaCiudadano` ofrece las excepciones de padrón PSH y VVG por rol (`intervencion`/`supervision`). Pasarlos a permisos cuando se revise el registro de atención.
- **Bandeja de alertas y mensajes para `consulta_basica`:** recibe los avisos de solicitud de cita como toast, pero no tiene la bandeja: su ruta exige `agenda.ver_propia` o `derivacion.ver_asignadas` (R4 y encargo), y `consulta_basica` no tiene ninguno de los dos.
- **Retirar documentos fuera del día:** solo se retiran los propios del día. Si hace falta que intervención o supervisión retiren un documento subido por error, decidir quién y añadir la acción (el servicio ya existe: `CicloVidaDocumentoService::retirar()`).
- **Avisos duplicados:** un usuario con `consulta_basica` y `tramitacion` a la vez recibe dos avisos por cada solicitud de cita (uno por rol, `AvisosCitas::solicitudCreada()`).

---

**Fase 3 bis (solicitudes y expedientes): pendientes** — 2026-11-15
Módulos: Prestaciones, Intervención, Documentos
- **Endpoint HTTP de avisos del gestor de expedientes:** fuera de alcance (no hay capa de API ni gestor). El procesamiento está en `SolicitudPrestacionService::procesarAviso()`; falta la ruta, su autenticación y el adaptador real.
- **Unidades tramitadoras reales:** la migración dejó las prestaciones de expediente externo con unidad «centro de solicitud». Configurar en el panel la UO que tramita cada una (p. ej. un servicio central de SAD) y sus formularios y documentos exigidos.
- **Pendientes del diseño (§8 de `modulo-solicitudes-expedientes.md`):** listas de espera de prestaciones con plaza, aviso por demora de inicio, integración con proveedores para el inicio efectivo, cofinanciación e HSU, indicadores.
- **Reasignar una solicitud tomada** (la supervisión, según TF-SOL-12): no hay acción; hoy solo la suelta quien la tiene.
- **Prescripciones de plaza abiertas desde Recursos** (sin pasar por «Solicitar prestación») no tienen prestación y no crean prestación de la persona al asignarse.
- **Prestaciones de la persona en el expediente (`CiudadanoPage`):** solo el resumen de la cabecera; el bloque completo está en la ficha (Ciudadanía).

---

**Fase 3 (circuito de intervención): pendientes** — 2026-11-14
Módulos: Intervención, Supervisión, Centro, Prestaciones
- **«Cambiar referencia» en Mi equipo y en la bandeja de asignaciones** (E1): de momento solo está en la ficha (`intervencion.cambio-referencia`). El componente se puede incrustar tal cual.
- **Supervisión de UO superiores y referencias:** `cambiarManual()` exige ahora supervisión **adscrita** al centro (TF-CIR-37). La bandeja de asignaciones «sin referencia» deja de poder resolverla una coordinación de UO superior que entre por el selector de centro. Confirmar que es lo querido o abrir una excepción para la bandeja.
- **Centro obligatorio en el modelo:** hoy lo exige `PlanDeIntervencionService::crear()`; las factorías y los escenarios de demo crean planes sin centro. Pasarlo al modelo cuando los escenarios de demo y las factorías lo den (y queden resueltos los planes antiguos sin centro deducible).
- **`PlanPage::asignarTipoPlan()`** (planes antiguos sin tipo; hoy no queda ninguno, así que solo importa para datos importados): no pasa por las reglas del servicio (tipo del centro, uno vigente por tipo); si choca con el índice único, salta una excepción de base de datos. Llevarlo al servicio.
- **Datos antiguos tras la migración:** 1 PISO tipado con historia en el CIAM Puente de Vallecas (se queda como PISO). Ya no queda ningún plan sin tipo (27 nov: 0 de 50).
- **Contador de derivaciones pendientes en el menú de Supervisión** (como el de asignaciones).
- **`AsignarPlazaModal::coleccionesDelCentro()`** decide el centro por `Profesional::unidad_organizativa_id` (dato antiguo) y no por `CentroDeUsuario`: un profesional sin ese campo no ve plazas.
- **`CuadranteSupervisorTest` (Agenda) falla también sin la fase 3** (2 tests: «franjas atención muestran horas correctas» y «publicar dos veces no duplica slots»), visto el 14 nov 2026 (sábado): probablemente dependen de la fecha. Revisar con `Carbon::setTestNow()`.
- **Avisos con destinatario «supervisión de la UO» sin UO:** si una persona con plaza no tiene historia, la alerta a la supervisión queda sin destinatarios (vencida y aviso en el log).

---

**Tipo de Centro: decisiones pendientes del diseño** — 2026-11-13
Módulo: Centro (de `docs/modulo-tipo-centro.md` §13)
- Canales de cita por tipo (presencial, teléfono, Cita Previa): con la integración de Cita Previa.
- Traslado en bloque de personas y casos al crear un tipo nuevo o mover prestaciones entre tipos.
- Cargos habituales por tipo como ayuda al alta de profesionales.
- Modelos de informe por tipo de centro.
- Protocolos estructurados (hoy, texto).
- Colores por tipo de centro en la barra superior (aplazado en `propuesta-requisitos-y-tareas-guiadas.md` §3).

---

**Tipo de Centro: interfaz, pendientes** — 2026-11-13
Módulo: Centro
- No hay acción para anular un cierre de calendario futuro (tipo o centro) registrado por error.
- No hay vista pública del centro en Livewire con denominación vigente, tipo y UO funcional (el paso 9 la pedía «si existe»).
- Las fichas de valoración del tipo: sin ninguna marcada valen todas; en el formulario no se distingue «todas» de «ninguna elegida todavía».

---

**Bajas de profesionales: lo que queda tras RN-20 a 23** — 2026-11-13
Módulo: Usuarios / Asignación / Agenda
- No hay «Reactivar» para un usuario desactivado (ni en Filament ni en Mi equipo). Hoy habría que poner `desactivado_en` a null a mano y volver a adscribirlo.
- La desactivación cierra las adscripciones con fecha de hoy, que siguen siendo vigentes hasta el final del día (`UsuarioUo::scopeVigentes`). Los listados que leen adscripciones (destinatarios de mensajes, equipos) pueden mostrarlo hasta mañana; el sorteo y el reparto ya lo excluyen.
- Las citas confirmadas que bloquean la salida se cuentan desde hoy, sin mirar la hora: una cita de esta mañana ya pasada pero sin cerrar también bloquea. Valorar contar solo desde ahora.
- El enlace «Repartir casos» lleva a la pestaña de actividad del centro supervisado, que puede no ser el centro del aviso si el supervisor tiene varios.

---

**Servidor: revisar las reglas AppArmor de qpdf y gs tras cada actualización del sistema** — 2026-11-11
Módulo: Documentos / infraestructura
En el servidor hay reglas locales de AppArmor en `/etc/apparmor.d/local/qpdf` y `/etc/apparmor.d/local/gs` (`/var/www/vida-project/vida/storage/app/tmp/** rwk,`). Sin ellas, los perfiles de Ubuntu impiden a qpdf y gs leer la zona temporal de ingesta y **se rechazan todos los PDF** con `pdf_no_normalizable` (la causa queda en el log: `Permission denied`). **Revisar tras cada actualización del sistema** (`apt upgrade`, cambio de versión de Ubuntu o del paquete `apparmor`): que las reglas siguen, que `sudo aa-status` carga los perfiles y que se puede subir un PDF. Las reglas incluyen también las rutas del repositorio local (`storage/framework/testing/**` y `storage/app/tmp/**`, añadidas el 2026-11-11); sin ellas fallan los tests que suben PDF. Detalle en `docs/modulo-documentos.md` §3.

---

**Symfony fijado a `^7.4`: revisar con Laravel 13** — 2026-11-11
Módulo: transversal / dependencias
`symfony/event-dispatcher`, `symfony/html-sanitizer` y `symfony/string` están fijados a `^7.4` en `composer.json` desde el 2026-11-11. Motivo: Laravel 12 fija su núcleo de Symfony a `^7.2`, y no se mezclan componentes 8.x con un núcleo 7.x. Las restricciones de Laravel y Filament admiten la 8.x en esos tres paquetes, y un `composer update` general los subía a la 8.1. **Revisar al migrar a Laravel 13:** quitar las tres restricciones o subirlas junto con el resto de Symfony.

---

**Requisitos de acción y tareas guiadas — en análisis** — 2026-11-10
Módulos: transversal
`docs/propuesta-requisitos-y-tareas-guiadas.md`, con bastante por decidir (sus apartados «Por decidir»). Sus dependencias ya están resueltas: Tipo de Centro (13 nov), centro y UO 1:1 (D1, 13 nov) e inscripción en actividades (fase 6, 17 nov).

---

**Acceso a expedientes: pendientes tras AccesoExpediente** — 2026-10-02
Módulo: Ciudadanía / Intervención / Supervisión
- **Normalización de los documentos de identidad** (2026-11-12, visto en B4): el alta (`AltaCiudadano`, por `NormalizadorCiudadano::documento()`) guarda el NIF en mayúsculas sin espacios ni guiones y el NIE **con guiones** (`X-1234567-L`); la ficha (`FichaCiudadanoPage::guardarDocumento()`) guarda el valor tal como se escribe. Como `valor_hash` solo pasa a minúsculas, la búsqueda exacta (buscador, citas, matching, cita externa) encuentra o no según por dónde se guardó y cómo se escriba: `X1234567L` no encuentra un NIE dado de alta en el alta, y `12345678-Z` no encuentra `12345678Z`. Los 2 NIE de la BD compartida están sin guiones. Propuesta: una forma canónica única (mayúsculas, sin espacios ni guiones) en `hashDe()` y en lo guardado, con una migración que recalcule `valor_hash` de los existentes; no se ha hecho porque cambia lo guardado (instrucción B4).
- **`AuditarAccesoCiudadano`** ya no cubre ninguna ruta útil (la ficha audita en el servicio). Valorar retirarlo o aplicarlo a las rutas `{historia}` como red de seguridad sin duplicar filas.
- **`CiudadanoPolicy::create()` y `delete()` con supervisión y otro rol** (2026-11-12): el 12 nov se decidió que en `update()` gane el otro rol (supervisión solo bloquea si el usuario no tiene otro rol con `ciudadano.editar`). `create()` y `delete()` siguen bloqueando a todo usuario con `supervision`. Decidir si se igualan.
- **`Ficha`: doble vínculo con la historia** (2026-11-12): `historia_id` (el que se usa) y `valoracion_id` (nulo en todas las fichas de la BD compartida a 2026-11-12, 11 fichas). `perteneceAHistoria()` usa la valoración solo si `historia_id` es nulo. Cuando se cierre el TODO de vincular siempre a `Valoracion`, mantener `historia_id` o derivarlo, pero no dejar dos fuentes que puedan discrepar.

---

**Supervisión: «Aprobaciones» fuera del menú** — 2026-11-11
Módulo: Supervision
Se retiró del menú el 2026-06-29, cuando la pantalla no tenía contenido operativo. Desde el 2026-09-25 resuelve las solicitudes de rol y de acceso, y `docs/modulo-usuarios-permisos.md` y el CHANGELOG hablan de «Supervisión → Aprobaciones». Hoy solo se llega desde la zona de aprobaciones de Inicio (si hay pendientes) y desde las alertas. Decidido el 2026-11-11 dejarlo así; reconsiderar si los supervisores no la encuentran. (El computed `Sidebar::aprobacionesPendientes()` ya se retiró; el contador sigue en `SupervisionSidebarDataService`.)

---

**Perfil horario por defecto: pendientes** — 2026-10-01
Módulo: Agenda / Supervisión
- El horario por defecto va de apertura a cierre sin pausa (en el CIAM son 45 h/semana). Valorar descontar una pausa o usar la jornada estándar del tipo de relación.

---

**CI «Calidad de código» (PHPStan + Pint) en rojo** — 2026-09-29 · 2026-10-01 · 2026-11-11
Módulo: transversal
*(2026-11-10: fundidas las dos entradas del 2026-09-29 y del 2026-10-01. 2026-11-11: medido y reescrito, punto A3 de `docs/instrucciones-cli/2026_11_estabilizacion_y_privacidad.md`.)*
El workflow falla desde junio de 2026 (último verde: `80c3325`, 2026-06-27) y sigue en rojo el 27 nov (las anotaciones del run 45983064405 son de Agenda: `missingType.iterableValue` en `SemanaTypoComponent`, `PerfilHorarioComponent`, `ExcepcionesComponent` y `CuadranteMesComponent`, `return.type` en `HistorialCitasFicha::grupos()`, `nullsafe.neverNull` en `CitaCierreJob`); hay que repetir el recuento; el despliegue no depende de él. Desde el 2026-11-11 `analyse-ci` usa `--memory-limit=2G`. En CI ya terminaba con 512M (1 min 36 s): el fallo es por errores, no por memoria. En la máquina de desarrollo (1 CPU, 1 GB) el análisis paralelo no termina; hay que lanzarlo por módulo con `--debug` (sin workers).

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

*2026-11-13: resuelto. El auto-commit sí llegó a funcionar (`db260c7`, 382 ficheros, quitó los `@return`); revertido, `"no_superfluous_phpdoc_tags": false` en `pint.json` y Pint pasado en local con esa configuración (decisión del desarrollador). Queda abierto si el CI debe seguir commiteando (`quality.yml`, paso «Commit cambios de formato») o pasar a `pint --test`.*

**Actualizaciones disponibles** (sin aplicar, para no cambiar la medición): `larastan/larastan` 3.10 → 3.14, `phpstan/phpstan` 2.2.1 → 2.3.5.

---

**Citas: pendientes de la fase** — 2026-09-30
Módulos: Agenda, Atención, demo
- **Citas en los mundos demo (paso 10, pospuesto por decisión del desarrollador):** los mundos demo no crean agenda (horario de centro, tipos de slot, cuadrante publicado ni slots), y una cita necesita un slot libre: `CitacionService::citarDirecto()` no basta. Hace falta una agenda mínima para el centro demo (horario, tipo de slot enlazado a tipos de cita, cuadrante publicado y slots de las próximas semanas de los TSR) y después dar citas en algunos escenarios (`TrayectoriaNueva`, `TrayectoriaActiva`). Solo se podrá probar con tests: `demo:reset` no se lanza desde local (BD compartida con staging).
- **Contrato con Cita Previa:** mapeo de servicios a tipos de cita, datos de identificación que envía y reconciliación. Hoy solo el mock (`MockCitaPrevia`). Ver `docs/modulo-citas.md` §11.
- **Indicadores de citas** (demora, incomparecencias, atención por sustituto, reprogramaciones): con el módulo de analítica (principio 3.14). El historial (`cita_eventos`) ya guarda los datos.
- **Gestión de citas desde la agenda del centro:** reprogramar y cancelar se hacen desde el detalle de la cita, al que se llega por la pestaña «Citas del día» de la bandeja de citación. El cuadrante del supervisor aún no enlaza las citas con su detalle.

- **Tipo de slot genérico en modo básico:** `modulo-agenda.md` prevé un tipo «Cita» automático para los centros en `modo_agenda = basico`, pero no existe ni hay forma estable de identificarlo (`tipos_slot` no tiene código). Hoy también el horario básico debe tener sus tipos elegidos en Filament. *(movida el 2026-11-11 desde «Suite de tests: Supervisión no se ejecuta»)*
---

**Asignación de centro y referencia: pendientes** — 2026-09-30
Módulos: Centro, Intervención, Ciudadanía, geocodificación
- **Adaptador real de la BDC** (`BdcGeocodificador`): NDP, distrito, barrio y sección, código INE de la sección y conversión UTM (ETRS89 30N) → WGS84. Hoy solo el mock rellena los códigos. Ver `docs/geocodificacion.md` §6. Desde el 25 nov, en Madrid los códigos los da el adaptador `callejero` (activo en staging): decidir si la BDC sigue haciendo falta.
- **Decisiones pendientes de `docs/modulo-asignacion.md` §11:** personas sin hogar (asignación por coordenadas de pernocta y zonificación de equipos de calle; hoy, «quien abre» y centro manual); asignación geográfica de profesionales (zonas por profesional); cambio a petición de la persona y cupos; fórmulas mixtas de centro (elegir dentro del distrito); procedimiento de revisión de secciones censales y reasignación por NDP.
- **Configurar los centros reales en staging:** ver también los ámbitos sin configurar en «Domicilio con el callejero oficial de Madrid». Para probar el sorteo hay que dar tipo, ámbito y modo a algún centro en Filament. **Aviso (2026-11-10):** el paso 3 de `docs/instrucciones-cli/2026-10-tipo-centro-implementacion.md` aborta si dos centros del mismo tipo tienen distinto modo de asignación, ventana o segmentos. Al configurar centros para probar el sorteo, configurar igual todos los del tipo.

---

**Bootstrap único: pendientes fuera del plan** — 2026-09-28
Módulo: Frontend / Filament
- **R5 da por usada una clase que coincide con un nombre de vista o ruta.** Valorar excluir de la búsqueda de usos las cadenas `vista::…`/`route('…')`.

---

**Frontend: rendimiento de las pantallas grandes (revisión de Grok, fases 6 y 7)** — 2026-09-29
Módulo: Intervención / Ciudadanía / Mensajes
Detalle en `docs/front/revision-frontend-ui.md` §6 y `docs/front/plan-correccion-frontend-ui.md` fases 6 y 7.
- **`CiudadanoPage`** (1.592 líneas el 27 nov) y **`plan-page`** (~66 KB): un buscador `wire:model.live` (UC en el expediente, `relacionBusqueda` en la ficha) vuelve a pintar toda la pantalla. Partir en componentes hijos (timeline, accesos, UC, herramientas).
- **Consultas en la vista:** `\App\Models\Ciudadano::find($ucCiudadanoSeleccionado)` en `ciudadano-page.blade.php`; `$datos`, `$doc` y `$yaEnPlan` dentro de bucles en `plan-page`; `Configuracion::logoUrl()` y `nombreAplicacion()` en cada request del shell.
- **Toasts:** `wire:poll.60s` sobre todo el árbol de `AlertaToast` en todas las pantallas. No volver a Alpine con `wire:ignore`: el morph rompía el estado minimizado (ver CHANGELOG 2026-09-27). Buscar un polling más ligero (solo el contador).
- `$herramientas` del expediente se reconstruye en cada render: sacarla a una constante.

---

**Dato de sexo fuera del catálogo** — 2026-09-29
Módulo: Ciudadanía
En la BD compartida hay un ciudadano con `sexo = 'H'`, guardado desde la ficha antes de alinearla con el catálogo `ciudadano.sexo` (M/F/D). La ficha lo muestra tal cual («H»). Corregirlo desde la ficha (editar y guardar) para que quede versionado; no con un `UPDATE`.

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

**Local y staging comparten la misma base de datos `vida`** — 2026-09-24
Módulo: Infraestructura
Detectado en la Fase 0 del mundo «Prueba CIAM»: `~/code/vida-project/vida/.env` (`APP_ENV=local`) y `/var/www/vida-project/vida/.env` (`APP_ENV=staging`) apuntan a `vida@127.0.0.1`. Toda migración o `demo:*` lanzado «en local» se aplica sobre staging, y un `demo:reset` en local truncaría los datos de staging (incluidos los de otros equipos). Recomendación: crear una BD de desarrollo propia (p. ej. `vida_dev`) para el entorno local.

*2026-11-11: **aplazada por decisión del desarrollador** (A4 de `docs/instrucciones-cli/2026_11_estabilizacion_y_privacidad.md`). Con un solo desarrollador, compartir la BD garantiza ver lo mismo en local y en staging y evita cambiar de contexto. Reconsiderar al cerrar la versión 1.0. Mientras tanto siguen vigentes las precauciones: nada de `demo:reset` ni `migrate:fresh` desde local.*
---

**Retirada de los datos de mundos aditivos (etiqueta `TEST_CIAM`)** — 2026-09-24
Módulo: Demo / World Building
Todo lo creado por `demo:load` queda en `demo_world_registros` (`DemoWorldRegistro::de('TEST_CIAM')`), pero no hay comando de purga por etiqueta: por instrucción, la retirada se diseñará aparte (orden de borrado, soft vs hard delete, auditoría generada por la carga, filas pivote no registradas como `model_has_roles`, `actividad_profesional` y `sesion_actividad_profesional`).

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

**TODOs activos en CiudadanoPage** — 2026-06-15
Módulo: Intervención / Ciudadanía
Varios TODOs documentados en `ciudadano-page.blade.php` que requieren trabajo pendiente:
- `centroActivo()` en `CiudadanoPage.php` — pendiente de implementación. *2026-11-10: el TODO ya no está en la vista y el centro de la persona se ve en el bloque «Centro y referencia» de la ficha (asignación, 2026-09-30), pero la cabecera del expediente sigue sin mostrarlo. Sin cerrar.*
- Menú ⋯ contextual — sin implementar (sigue el TODO en `ciudadano-page.blade.php:125`).

---

**Búsqueda por nombre cifrado con índice hash determinista** — 2026-06-01
Módulo: Ciudadanía
`BuscarCiudadanoPage` carga ≤ 500 registros y filtra en PHP para evitar el problema de búsqueda sobre campos cifrados. Reemplazar por índice hash determinista cuando el módulo Ciudadanía esté completo.

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

**Reducir baseline de PHPStan** — 2026-06-03
Módulo: Tooling
La baseline tenía 772 errores heredados en junio; el 27 nov tiene 523 entradas. Reducirlos progresivamente en cada sesión de refactor.
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

**Código de Primera Atención (PA)** — 2026-06-19
`Módulo: Atención`
Pendiente decidir si RegistroAtencion necesita un identificador visible
tipo "PA-2024-001234" para comunicar al ciudadano o para referencia interna.
Ver sección 9 de docs/modulo-atencion.md.

---

**Tipo actividad en RegistroAtencion** — 2026-06-19
`Módulo: Atención / Centro`
El tipo actividad está definido en el modelo pero sin UI ni generación
automática. La inscripción en actividades ya existe (fase 6, 17 nov), pero no crea registros de atención:
`RegistroAtencion::crearDesdeOrigen()` solo lo usan los tests. Decidir si la asistencia a una sesión debe generarlo.
La relación polimórfica `origen_tipo / origen_id` está preparada.

---

**Tipo contacto en RegistroAtencion** — 2026-06-19
`Módulo: Atención`
Definido en el modelo pero sin UI específica en fase 1. Se implementará
cuando el módulo de Agenda esté operativo (llamadas de seguimiento).

---

**Cron y worker de la cola en producción** — 2026-11-16 (el `queue:restart` del despliegue, ✅ resuelto el mismo día)
`Área: CI/CD / Infraestructura`
El despliegue ya ejecuta `php artisan queue:restart`. Para producción, decidir cómo se gestionan el cron de `schedule:run` y el worker (cron.d y supervisor o systemd), en lugar del crontab y PM2 de un usuario personal. Ver sección 18 de `docs/decisiones-tecnicas.md`.
