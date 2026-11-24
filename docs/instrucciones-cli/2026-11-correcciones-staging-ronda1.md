# Instrucciones CLI — Correcciones de la ronda 1 de pruebas en staging

**Origen:** primera tanda del guion de pruebas (23 nov 2026): 23 de 90 casos probados, 6 KO y 2 bloqueados. Clasificación completa en `docs/resultados-staging-ronda1-2026-11-23.md` (R-01 a R-10).
**Estado:** decisiones R1, R2, R3 y R4 confirmadas por el Delegado Digital el 23 nov 2026. Listo para ejecutar.
**Requisito previo:** staging en `bb58417` o posterior.
**Tests:** TF-STG-01 a TF-STG-31 (al final).

**Regla de trabajo:** si el código contradice estas instrucciones o aparece un caso no previsto, **detente y pregunta**. Lo marcado como *fuera de alcance* no se toca. Sin funcionalidad nueva fuera de lo que se lista.

**Antes de empezar:** `CLAUDE.md`, `SESSION.md`, `docs/instrucciones-cli/2026-11-tipo-centro-addenda-d1.md` (D1.4, RN-15 a RN-19), `docs/instrucciones-cli/2026-11-fase4-consulta-basica.md`, `docs/roles-consulta-basica-tramitacion.md`, `docs/rol-consulta-profesional.md` (solo como contexto: no se implementa aquí), `docs/design-system/SKILL.md`.

---

## Decisiones (confirmadas el 23 nov 2026)

| Id | Decisión |
|---|---|
| **R1** | **RN-15 se aplica a toda actuación.** Además de abrir Historia Social, dar o atender citas, registrar atención, hacer apuntes e inscribir en actividades, exigen que el usuario esté adscrito al centro de la operación: valoraciones, escalas, planes (crear y editar), seguimientos, derivaciones, solicitudes de prestación, prescripciones, informes y subida de documentos a la historia. Un usuario sin centro ve la Historia Social **en modo lectura**: sin botones de actuación, y las actuaciones de otros profesionales se abren en lectura |
| **R2** | **Supervisión sola.** Un usuario con rol `supervision` y sin `intervencion` tiene buscador y ficha de la persona **en lectura**: identificación, contacto, convivencia, relaciones, estado de la Historia Social y profesional de referencia, y citas. No edita, no ve documentos y no abre la Historia Social ni los planes (completa la decisión del 14 nov). Las personas protegidas fuera de RN-17 aparecen enmascaradas, con «Solicitar acceso» (RN-18). Sustituye a la descripción del ROL 2 en `modulo-usuarios-permisos.md` («lectura de Historias Sociales, planes, apuntes»), que se corrige en el cierre |
| **R3** | **Desactivar un profesional desde administración.** Los enlaces a reparto de casos y gestión de ausencias solo se muestran a quien puede usarlos. A quien no puede (p. ej. `adm_sistema`), se le indica qué dirección de centro tiene que hacerlo, con un botón «Avisar a la dirección del centro». El reparto es de la dirección del centro (D5) |
| **R4** | **Superficie operativa única, decidida por permisos.** Ver detalle abajo |

### R4 — Superficie operativa única

1. **Una sola superficie operativa con el menú combinado.** Con varios roles, el usuario no cambia de superficie: ve la unión de las entradas que le dan sus permisos. Los roles suman capacidades (principio 3.3), igual que en la regla de documentos de R-08.
2. **El menú es del usuario, no de la página.** Un único registro de entradas, agrupadas en secciones fijas: *Mi trabajo* (Agenda, Mis casos), *Personas* (Buscador, Alta), *Citación*, *Tramitación* y *Supervisión* (inicio, Equipo, Reparto, Ausencias, Accesos…). Cada entrada declara el permiso o la policy que la muestra. Las secciones vacías no se muestran y una entrada aparece una sola vez aunque la den varios roles. Las pantallas no fijan el menú; como mucho marcan la entrada activa.
3. **La administración queda aparte** (`/admin`): no se mezcla con el menú operativo. Quien tenga acceso ve un enlace «Administración» en el menú de usuario.
4. **Rutas, menú y página de inicio se deciden por permisos o policies, no por rol.** Lo que depende de una persona (Historia Social, ficha, plan) va por policy (permiso + ámbito + RN-15/RN-17). Lo que no depende de una persona (bandejas, agenda, inicio de supervisión) va por permiso. Motivo: los roles se configuran en el backoffice y solo los permisos atómicos se comprueban en código (`modulo-usuarios-permisos.md` §2).
5. **Página de inicio por defecto**, por esta prioridad:
   1. Inicio de Supervisión (`supervision`). La dirección de centro entra por aquí.
   2. Agenda (`intervencion`).
   3. Bandeja de tramitación (`tramitacion`).
   4. Bandeja de citación (`citas.gestionar`). La ventanilla entra por aquí, no por el buscador.
   5. Administración, solo si el usuario no tiene ninguna superficie operativa.
   6. Si no tiene nada de lo anterior: página «Tu usuario no tiene un perfil de trabajo configurado. Contacta con la administración de tu centro.» (p. ej. `consulta_profesional` hasta que exista su superficie, ver `docs/rol-consulta-profesional.md`).
6. **Preferencia de página de inicio.** En el menú de usuario, «Página de inicio»:
   - Opciones: «Automática» (la prioridad del punto 5; valor por defecto) y las superficies operativas a las que el usuario tiene acceso, entre: Inicio de Supervisión, Agenda, Bandeja de tramitación, Bandeja de citación y Buscador.
   - **Administración no es elegible.**
   - Si el usuario solo tiene una superficie operativa, la opción no se muestra.
   - Se guarda en `users.pagina_inicio` (clave de la superficie; `null` = Automática). Solo la cambia el propio usuario.
   - Si la superficie guardada ya no es accesible (p. ej. se le retiró el rol), se aplica la prioridad sin aviso. La preferencia no se borra: si recupera el acceso, vuelve a valer.
   - No depende del centro: el centro lo sigue decidiendo el perfil horario (D1.6).
7. **Un único resolutor**, `DestinoInicial::para(User)` (sustituye a `User::destinoInicial()`): preferencia válida → prioridad → administración → página informativa. Lo usan el login, el primer acceso, el logo o «Inicio» y el botón «Volver al inicio» de las páginas de error (paso 3).
8. **Los enlaces directos mandan:** tras el login, si había un destino solicitado (enlace de una alerta, de un correo), se va a ese destino (`redirect()->intended()`), no a la página de inicio.

Anota R1–R4 en `SESSION.md` al empezar.

---

## Fuera de alcance

- Pantalla de supervisión «Ciudadanos del centro» (va al BACKLOG; ver «Cierre»).
- Superficie de `consulta_profesional` y encargo profesional (diseño en `docs/rol-consulta-profesional.md`; va al BACKLOG).
- Barrido completo de `role:` → permisos en todas las rutas: aquí solo en las rutas que se toquen (paso 2.3 y paso 6). El barrido es el encargo siguiente.
- Normalización de documentos de identidad, `PrescribirRecursoModal`, materializador de slots: siguen en el BACKLOG.
- Cambiar datos de usuarios de staging: informa y pregunta.

---

## Paso 0 — Verificación y reproducción

1. `git pull` y **suite completa** (no se ha pasado tras la fase 6). Anota los fallos previos.
2. En los logs de staging del 23 nov, localiza y resume la causa de: el bucle de primer acceso de `edu.css` (EST-02), el 403 de `aux1.ciam` al abrir un resultado del buscador (VEN-02) y la `DomainException` en `Modules/Centro/app/Services/Asignacion/CentroDeUsuario.php:228` (EST-04).
3. Informa de los roles, adscripciones (UO y centro) y perfiles horarios reales en staging de `edu.css`, `aux1.ciam`, `adm1.css`, `tec.ciam`, `coo.ciam`, `dir.ciam` y `ts1.css`. No los cambies.
4. Lista los puntos de entrada de actuaciones sobre una persona (caja de herramientas, acciones de la ficha y de la Historia Social): entrevista, apunte, atención, valoración, escala, plan, seguimiento, derivación, solicitud de prestación, prescripción, informe, solicitud de cita, inscripción, subida de documentos. Para cada uno: si comprueba RN-15 en el servidor, si se oculta en la interfaz y en qué momento falla hoy un usuario sin centro (al abrir o al guardar).
5. Menú lateral y página de inicio: cómo están construidos hoy los tres menús (¿paneles Filament distintos o un componente de navegación propio?), qué pantallas fijan cada uno y qué rutas usan `role:`. Propón cómo llevar a cabo R4.2 con el menor cambio y **espera confirmación antes del paso 6.1** si implica pasar de varios paneles Filament a uno.
6. `BuscarCiudadanoPage`: qué acción tiene cada resultado y según qué, y dónde se decide que una persona aparece como «Persona con protección especial» (¿usa `AmbitoUsuario::gestionaProtegido()`?).
7. Comprueba si en EST-04 `tec.ciam` **creó** una Historia Social o solo consultó una existente (auditoría y tabla de historias del 23 nov).
8. Inventario de permisos: qué permisos atómicos existen en `RolesSeeder` que sirvan para mostrar cada superficie y entrada de menú de R4 (agenda, inicio de supervisión, bandeja de tramitación, bandeja de citación, buscador, alta, administración).

---

## Paso 1 — Primer acceso y página de inicio (R-01, EST-02, R4)

1. Corrige la causa del bucle.
2. `DestinoInicial::para(User)` según R4.5 a R4.8. Toda combinación de roles resuelve un destino; nunca una redirección sin destino.
3. Permisos de superficie: usa los existentes del paso 0.8. Si falta alguno, créalo en `RolesSeeder` (idempotente), con nombres del tipo `supervision.panel`, `agenda.ver_propia` o `tramitacion.bandeja`, y explícalo en el informe. `citas.gestionar` ya existe.
4. Preferencia de página de inicio según R4.6: migración de `users.pagina_inicio`, opción en el menú de usuario, validación en el servidor (solo superficies accesibles; Administración rechazada).
5. Multicentro sin perfil horario para hoy: entra, y la barra superior le pide elegir centro con el selector (`centroActivo()` devuelve `null` y no bloquea la entrada).
6. La pantalla de primer acceso termina siempre en el destino del punto 2.

## Paso 2 — Ventanilla: del buscador a la ficha (R-02, VEN-02)

1. La acción de cada resultado del buscador se decide por policies, no por rol:
   - `HistoriaSocialPolicy::view` → «Ir a la Historia Social».
   - Si no, `CiudadanoPolicy::view` → «Ir a la ficha de la persona» (`FichaCiudadanoPage`).
   - Persona protegida fuera de ámbito → como hoy.
2. `FichaCiudadanoPage` accesible para `consulta_basica` y `tramitacion`. Revisa los demás enlaces a la Historia Social en pantallas de estos roles (bandeja de citación, alta, citas, bandeja de tramitación) y aplica la misma regla.
3. En las rutas que toques, sustituye el middleware `role:` por permisos o policies (R4.4).
4. Comprueba que la ficha muestra a estos roles los bloques de la fase 4 (TF-CBA-01 en verde).

## Paso 3 — Errores comprensibles (R-03, transversal)

1. Vistas de error propias, en español y dentro del layout operativo, para 403, 404, 419, 429, 500 y 503: mensaje claro, botón «Volver al inicio» (destino de `DestinoInicial`), fecha y hora y un identificador que permita encontrar el error en el log. Sin trazas, rutas de fichero ni textos en inglés.
2. `Spatie\Permission\Exceptions\UnauthorizedException` y `AuthorizationException` → 403 propio («No tienes permiso para ver esta página.»).
3. Excepciones de dominio lanzadas desde acciones Livewire o Filament (como la de `CentroDeUsuario`) → notificación con el mensaje de negocio en español, sin perder lo escrito en el formulario. Revisa que los mensajes de esas excepciones estén en español.
4. Informa del valor de `APP_DEBUG` en staging. No lo cambies sin preguntar.

## Paso 4 — Usuario sin centro: Historia Social en lectura (R-04, EST-04, R1)

1. Una sola comprobación reutilizable (p. ej. un método en `CentroDeUsuario` o una policy) que diga si el usuario puede actuar en el centro de la operación (RN-16: el del contexto o el centro activo). Se usa en la interfaz y en el servidor, para todas las actuaciones de R1.
2. Interfaz: para un usuario sin ningún centro, la Historia Social se muestra en modo lectura: sin acciones de actuación en la caja de herramientas ni en la ficha. Las actuaciones registradas por otros se abren en lectura.
3. Servidor: cada punto de entrada del paso 0.4 comprueba la regla **al abrir el formulario** y al guardar. Mensaje: «Para atender a ciudadanos tienes que estar adscrito a un centro.» (o «…a este centro.» si tiene otros).
4. Crear una Historia Social sin centro: rechazado. Si el paso 0.7 muestra que `tec.ciam` creó una, informa y no la borres.
5. Sin cambios para quien sí tiene centro (TS, psicóloga, dirección).

## Paso 5 — Salvedad de RN-17 en el buscador (R-05, EST-09)

1. El buscador (y la ficha que abre) decide si mostrar la identidad de una persona protegida con `AmbitoUsuario::gestionaProtegido()`, igual que las policies.
2. `tec.ciam` (intervención, UO funcional del tipo CIAM) ve a Marta con identidad. `coo.ciam` (solo supervisión) la ve enmascarada con «Solicitar acceso».

## Paso 6 — Menú único, supervisión, buscador y ficha (R-06, EST-05, EST-06, EST-08, R2, R4)

1. Menú según R4.1 a R4.3, con la propuesta confirmada en el paso 0.5: registro único de entradas por secciones, cada una con su permiso o policy; unión para varios roles; el menú es el mismo en todas las pantallas operativas; Administración como enlace en el menú de usuario. `dir.ciam` (intervención + supervisión) tiene el buscador y su Agenda en el menú.
2. Según R2: `supervision` recibe en `RolesSeeder` (idempotente) los permisos de lectura de ficha que ya existen (`ciudadano.ver_ficha`, `historia.ver_estado`, `cita.ver_historial`; añade otro solo si hace falta y explícalo). Ficha en lectura con los bloques de R2. Sin acciones de edición, sin documentos, sin enlace a la Historia Social ni a planes.
3. Si `supervision` tiene hoy permisos de lectura de Historia Social, apuntes o planes en el seeder o en las policies, informa antes de quitarlos.
4. Personas protegidas: RN-17 y RN-18 sin cambios (enmascarada y «Solicitar acceso»).
5. Las rutas de las pantallas que muestra el menú y que toques en este paso pasan de `role:` a permisos o policies (R4.4).

## Paso 7 — Desactivar un profesional desde administración (R-07, INF-08, R3)

1. En el rechazo de la desactivación, los enlaces a «Repartir casos» y a la gestión de ausencias solo aparecen si el usuario puede abrir esas páginas (policy).
2. Si no puede: el mensaje nombra el centro o centros afectados y su dirección vigente (`DirectorCentro`; si no hay, la supervisión adscrita), con el botón «Avisar a la dirección del centro», que envía un aviso con el profesional y el número de casos y citas.
3. Lo mismo en el cierre de adscripción (EST-12), que ya funcionaba para la dirección: no lo rompas.

## Paso 8 — Documentos con varios roles (R-08, INF-01)

1. Reproduce con `ts1.css` (solo intervención): sube un documento y mira la carpeta. Si aparece «Solo ves los documentos que has subido hoy», es un error: corrígelo.
2. Regla: la restricción de «documentos propios del día» solo se aplica si el usuario no puede ver la carpeta completa por otro rol. Con `intervencion` + `consulta_basica` (`aux2.ciam`) ve la carpeta completa de las personas de su ámbito. `consulta_basica` sola, sin cambios (TF-CBA-21 y 22 en verde).

---

## Cierre

1. Suites tocadas y suite completa sin fallos nuevos. `npm run build` y `ui:auditar` en verde.
2. Documentación:
   - `modulo-tipo-centro.md` y la addenda: RN-15 ampliada (R1) y modo lectura sin centro.
   - `modulo-usuarios-permisos.md`: ROL 2 según R2; **nueva sección «Superficie operativa y página de inicio»** con R4 completo (superficie única, menú por permisos, administración aparte, prioridad, preferencia, enlaces directos, página informativa); ROL 6 remite a `docs/rol-consulta-profesional.md`; permisos de superficie creados en el paso 1.3.
   - `modulo-ciudadania.md`: ficha para supervisión (R2).
   - `modulo-documentos.md`: regla de varios roles.
   - `docs/decisiones-pendientes.md`: añade R1–R4 como decididas el 23 nov.
3. BACKLOG, entradas nuevas:
   - **Supervisión: pantalla «Ciudadanos del centro».** Listado de las personas asignadas al centro, filtrable, con datos para la gestión: cita pendiente, fecha de la última atención, plan activo, colectivo (mayor, menor, mujer…). Sin acceso a la Historia Social salvo con `intervencion`. Pendiente de diseño. Origen: EST-08, ronda 1 de staging.
   - **Barrido `role:` → permisos.** Todas las rutas operativas comprobadas por permiso o policy, con un test de arquitectura que falle si una ruta operativa usa el middleware `role:`. Encargo siguiente a este.
   - **`consulta_profesional` y encargo profesional.** Según `docs/rol-consulta-profesional.md`. Al implantarlo, «Mis derivaciones» entra en la prioridad de R4.5 justo después de Agenda y como superficie elegible.
   - **Pruebas en staging, ronda 2:** repetir INF-01, INF-08, EST-02 a EST-09 y VEN-01 a VEN-05 tras este encargo.
4. CHANGELOG y SESSION (con R1–R4). Push y despliegue en verde.
5. Al terminar, informa de qué casos del guion se pueden repetir.

## Criterios de finalización

1. TF-STG-01 a 31 en verde, con negativos comprobados.
2. Ninguna pantalla de error de Laravel visible al usuario en los recorridos de los pasos 1 a 8.
3. Documentación al día y despliegue en verde.

---

# Tests — TF-STG-01 a TF-STG-31

**Convenciones:** PHPUnit con `#[Test]`, PostgreSQL (`vida_testing`), Dado / Cuando / Entonces. Datos: `CircuitoTestSetup` y `TipoCentroTestSetup`. Ubicación junto a los tests del módulo afectado. **[negativo]** = debe fallar sin la protección.

## Grupo A — Inicio y errores

**TF-STG-01 — Rol sin superficie** **[negativo]**: un usuario solo con `consulta_profesional` entra y ve la página informativa (200); no hay cadena de redirecciones.

**TF-STG-02 — Multicentro sin horario hoy**: profesional con dos adscripciones y sin perfil horario para hoy → entra y ve el selector de centro.

**TF-STG-03 — Multicentro con horario hoy**: entra con el centro de su perfil horario del día.

**TF-STG-04 — Primer acceso**: para cada rol y para intervención + supervisión, el primer acceso termina en el destino de `DestinoInicial`.

**TF-STG-05 — 403 propio** **[negativo]**: la respuesta no contiene «User does not have the right roles» ni texto en inglés; contiene el mensaje en español y el botón de volver.

**TF-STG-06 — 404 y 500 propios**: sin trazas ni rutas de fichero; con identificador para el log.

**TF-STG-07 — Excepción de dominio en una acción**: una `DomainException` en una acción Livewire muestra una notificación con el mensaje y conserva el formulario.

## Grupo B — Ventanilla

**TF-STG-08 — `consulta_basica` va a la ficha**: el resultado del buscador enlaza a `FichaCiudadanoPage`, que responde 200.

**TF-STG-09 — `tramitacion` va a la ficha**: igual.

**TF-STG-10 — `intervencion` va a la Historia Social**: sin cambios.

**TF-STG-11 — Sin Historia Social** **[negativo]**: `consulta_basica` en una ruta de la Historia Social → 403 propio.

## Grupo C — Usuario sin centro (R1)

**TF-STG-12 — Historia en lectura**: con `$tecnicaUoSuperior` (intervención, sin centro), la Historia Social no contiene acciones de actuación en el HTML.

**TF-STG-13 — Rechazo al abrir** **[negativo]**: cada punto de entrada del paso 0.4 (data provider con todas las actuaciones de R1) rechaza al abrir el formulario, con el mensaje de RN-15.

**TF-STG-14 — Actuaciones de otros en lectura**: abre una valoración y un plan de otra profesional sin acciones de edición.

**TF-STG-15 — No abre historias** **[negativo]**: no puede crear una Historia Social.

**TF-STG-16 — Sin regresión**: `$tsNorte`, adscrita, registra las mismas actuaciones que antes.

## Grupo D — Protegidas y supervisión (R2)

**TF-STG-17 — Salvedad de RN-17 en el buscador**: `$tecnicaUoSuperior` con el CIAM en su ámbito busca a `$marta` → identidad.

**TF-STG-18 — Solo supervisión, enmascarada** **[negativo]**: `$coordIgualdad` busca a `$marta` → «Persona con protección especial» y «Solicitar acceso».

**TF-STG-19 — Menú con varios roles**: la directora (intervención + supervisión) tiene el buscador y la Agenda en el menú.

**TF-STG-20 — Ficha para supervisión**: `$coordIgualdad` abre la ficha de una persona no protegida del CIAM y ve los bloques de R2, sin acciones de edición ni documentos.

**TF-STG-21 — Supervisión sin historia ni planes** **[negativo]**: `$coordIgualdad` en rutas de Historia Social, plan y apuntes → 403 propio.

## Grupo E — Administración y documentos (R3)

**TF-STG-22 — Desactivar desde administración**: el administrador intenta desactivar a un profesional con casos → sin enlaces a páginas que no puede abrir; «Avisar a la dirección del centro» envía el aviso a la directora.

**TF-STG-23 — TS ve la carpeta completa** **[negativo]**: `$tsNorte` (solo intervención) ve todos los documentos de la carpeta y no ve el mensaje de documentos del día.

**TF-STG-24 — Varios roles**: un usuario con `intervencion` + `consulta_basica` ve la carpeta completa en su ámbito; `$ventanillaNorte` sigue viendo solo los suyos del día.

## Grupo F — Superficie única y página de inicio (R4)

**TF-STG-25 — Inicio por defecto** (data provider):
- intervención + supervisión + `adm_usuarios` → Inicio de Supervisión.
- intervención + `consulta_basica` → Agenda.
- `tramitacion` + `consulta_basica` → Bandeja de tramitación.
- `consulta_basica` sola → Bandeja de citación.
- `supervision` sola → Inicio de Supervisión.
- `adm_sistema` solo → Administración.
- `consulta_profesional` solo → página informativa.

**TF-STG-26 — Preferencia**: la directora elige «Agenda» → tras el login va a Agenda; vuelve a «Automática» → Inicio de Supervisión.

**TF-STG-27 — Preferencia no válida** **[negativo]**: guardar una superficie a la que el usuario no tiene acceso, o «Administración», se rechaza en el servidor (también llamando directamente a la acción). Con una preferencia guardada cuyo rol se retira → se aplica la prioridad, sin error; al devolverle el rol, vuelve a valer.

**TF-STG-28 — Opción oculta con una sola superficie**: `consulta_basica` sola no ve «Página de inicio» en el menú de usuario.

**TF-STG-29 — El enlace directo manda**: un usuario sin sesión abre el enlace de una alerta → tras el login llega a ese destino, no a su página de inicio.

**TF-STG-30 — Menú único** **[negativo]**: intervención + `consulta_basica` ve el mismo menú en Agenda, en una pantalla de citas y en la Historia Social, con Agenda, Mis casos, Buscador (una sola vez) y Citación; no ve entradas de Supervisión ni de Tramitación.

**TF-STG-31 — Administración aparte**: la directora (con `adm_usuarios`) ve «Administración» en el menú de usuario y no en el menú operativo; un usuario sin acceso a administración no ve el enlace.

## Tabla de estado

| Grupo | Tests | Estado |
|---|---|---|
| A — Inicio y errores | TF-STG-01..07 | |
| B — Ventanilla | TF-STG-08..11 | |
| C — Usuario sin centro | TF-STG-12..16 | |
| D — Protegidas y supervisión | TF-STG-17..21 | |
| E — Administración y documentos | TF-STG-22..24 | |
| F — Superficie única y página de inicio | TF-STG-25..31 | |
