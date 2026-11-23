# Instrucciones CLI — Correcciones de la ronda 1 de pruebas en staging

**Origen:** primera tanda del guion de pruebas (23 nov 2026): 23 de 90 casos probados, 6 KO y 2 bloqueados. Clasificación completa en `docs/resultados-staging-ronda1-2026-11-23.md` (R-01 a R-10).
**Estado:** decisiones R1, R2 y R3 confirmadas por el Delegado Digital el 23 nov 2026. Listo para ejecutar.
**Requisito previo:** staging en `bb58417` o posterior.
**Tests:** TF-STG-01 a TF-STG-24 (al final).

**Regla de trabajo:** si el código contradice estas instrucciones o aparece un caso no previsto, **detente y pregunta**. Lo marcado como *fuera de alcance* no se toca. Sin funcionalidad nueva fuera de lo que se lista.

**Antes de empezar:** `CLAUDE.md`, `SESSION.md`, `docs/instrucciones-cli/2026-11-tipo-centro-addenda-d1.md` (D1.4, RN-15 a RN-19), `docs/instrucciones-cli/2026-11-fase4-consulta-basica.md`, `docs/roles-consulta-basica-tramitacion.md`, `docs/design-system/SKILL.md`.

---

## Decisiones (confirmadas el 23 nov 2026)

| Id | Decisión |
|---|---|
| **R1** | **RN-15 se aplica a toda actuación.** Además de abrir Historia Social, dar o atender citas, registrar atención, hacer apuntes e inscribir en actividades, exigen que el usuario esté adscrito al centro de la operación: valoraciones, escalas, planes (crear y editar), seguimientos, derivaciones, solicitudes de prestación, prescripciones, informes y subida de documentos a la historia. Un usuario sin centro ve la Historia Social **en modo lectura**: sin botones de actuación, y las actuaciones de otros profesionales se abren en lectura |
| **R2** | **Supervisión sola.** Un usuario con rol `supervision` y sin `intervencion` tiene buscador y ficha de la persona **en lectura**: identificación, contacto, convivencia, relaciones, estado de la Historia Social y profesional de referencia, y citas. No edita, no ve documentos y no abre la Historia Social ni los planes (completa la decisión del 14 nov). Las personas protegidas fuera de RN-17 aparecen enmascaradas, con «Solicitar acceso» (RN-18). Sustituye a la descripción del ROL 2 en `modulo-usuarios-permisos.md` («lectura de Historias Sociales, planes, apuntes»), que se corrige en el cierre |
| **R3** | **Desactivar un profesional desde administración.** Los enlaces a reparto de casos y gestión de ausencias solo se muestran a quien puede usarlos. A quien no puede (p. ej. `adm_sistema`), se le indica qué dirección de centro tiene que hacerlo, con un botón «Avisar a la dirección del centro». El reparto es de la dirección del centro (D5) |

Anótalas en `SESSION.md` al empezar.

---

## Fuera de alcance

- Pantalla de supervisión «Ciudadanos del centro» (va al BACKLOG; ver «Cierre»).
- Normalización de documentos de identidad, `PrescribirRecursoModal`, materializador de slots: siguen en el BACKLOG.
- Cambiar datos de usuarios de staging: informa y pregunta.

---

## Paso 0 — Verificación y reproducción

1. `git pull` y **suite completa** (no se ha pasado tras la fase 6). Anota los fallos previos.
2. En los logs de staging del 23 nov, localiza y resume la causa de: el bucle de primer acceso de `edu.css` (EST-02), el 403 de `aux1.ciam` al abrir un resultado del buscador (VEN-02) y la `DomainException` en `Modules/Centro/app/Services/Asignacion/CentroDeUsuario.php:228` (EST-04).
3. Informa de los roles, adscripciones (UO y centro) y perfiles horarios reales en staging de `edu.css`, `aux1.ciam`, `adm1.css`, `tec.ciam`, `coo.ciam`, `dir.ciam` y `ts1.css`. No los cambies.
4. Lista los puntos de entrada de actuaciones sobre una persona (caja de herramientas, acciones de la ficha y de la Historia Social): entrevista, apunte, atención, valoración, escala, plan, seguimiento, derivación, solicitud de prestación, prescripción, informe, solicitud de cita, inscripción, subida de documentos. Para cada uno: si comprueba RN-15 en el servidor, si se oculta en la interfaz y en qué momento falla hoy un usuario sin centro (al abrir o al guardar).
5. Cómo se deciden el menú lateral y la página de inicio según el rol (fichero y línea), y qué pasa con varios roles.
6. `BuscarCiudadanoPage`: qué acción tiene cada resultado y según qué, y dónde se decide que una persona aparece como «Persona con protección especial» (¿usa `AmbitoUsuario::gestionaProtegido()`?).
7. Comprueba si en EST-04 `tec.ciam` **creó** una Historia Social o solo consultó una existente (auditoría y tabla de historias del 23 nov).

---

## Paso 1 — Primer acceso y página de inicio (R-01, EST-02)

1. Corrige la causa del bucle.
2. Toda combinación de roles resuelve una página de inicio. Si hoy hay una regla de prioridad entre roles, mantenla y documéntala; si no, propón una y pregunta. Si un usuario no tiene ningún rol con superficie de trabajo, ve una página «Tu usuario no tiene un perfil de trabajo configurado. Contacta con la administración de tu centro.» Nunca una redirección sin destino.
3. Multicentro sin perfil horario para hoy: entra, y la barra superior le pide elegir centro con el selector (`centroActivo()` devuelve `null` y no bloquea la entrada).
4. La pantalla de primer acceso termina siempre en la página de inicio resuelta en el punto 2.

## Paso 2 — Ventanilla: del buscador a la ficha (R-02, VEN-02)

1. La acción de cada resultado del buscador se decide por policies, no por rol:
   - `HistoriaSocialPolicy::view` → «Ir a la Historia Social».
   - Si no, `CiudadanoPolicy::view` → «Ir a la ficha de la persona» (`FichaCiudadanoPage`).
   - Persona protegida fuera de ámbito → como hoy.
2. `FichaCiudadanoPage` accesible para `consulta_basica` y `tramitacion`. Revisa los demás enlaces a la Historia Social en pantallas de estos roles (bandeja de citación, alta, citas, bandeja de tramitación) y aplica la misma regla.
3. En las rutas que toques, sustituye el middleware `role:` por permisos o policies.
4. Comprueba que la ficha muestra a estos roles los bloques de la fase 4 (TF-CBA-01 en verde).

## Paso 3 — Errores comprensibles (R-03, transversal)

1. Vistas de error propias, en español y dentro del layout operativo, para 403, 404, 419, 429, 500 y 503: mensaje claro, botón «Volver al inicio», fecha y hora y un identificador que permita encontrar el error en el log. Sin trazas, rutas de fichero ni textos en inglés.
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

## Paso 6 — Supervisión: menú, buscador y ficha (R-06, EST-05, EST-06, EST-08, R2)

1. Menú con varios roles: la unión de las entradas de cada rol. `dir.ciam` (intervención + supervisión) tiene el buscador.
2. Según R2: `supervision` recibe en `RolesSeeder` (idempotente) los permisos de lectura de ficha que ya existen (`ciudadano.ver_ficha`, `historia.ver_estado`, `cita.ver_historial`; añade otro solo si hace falta y explícalo). Ficha en lectura con los bloques de R2. Sin acciones de edición, sin documentos, sin enlace a la Historia Social ni a planes.
3. Si `supervision` tiene hoy permisos de lectura de Historia Social, apuntes o planes en el seeder o en las policies, informa antes de quitarlos.
4. Personas protegidas: RN-17 y RN-18 sin cambios (enmascarada y «Solicitar acceso»).

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
   - `modulo-usuarios-permisos.md`: ROL 2 según R2; regla de menú con varios roles.
   - `modulo-ciudadania.md`: ficha para supervisión (R2).
   - `modulo-documentos.md`: regla de varios roles.
   - `docs/decisiones-pendientes.md`: añade R1–R3 como decididas el 23 nov.
3. BACKLOG, entradas nuevas:
   - **Supervisión: pantalla «Ciudadanos del centro».** Listado de las personas asignadas al centro, filtrable, con datos para la gestión: cita pendiente, fecha de la última atención, plan activo, colectivo (mayor, menor, mujer…). Sin acceso a la Historia Social salvo con `intervencion`. Pendiente de diseño. Origen: EST-08, ronda 1 de staging.
   - **Pruebas en staging, ronda 2:** repetir INF-01, INF-08, EST-02 a EST-09 y VEN-01 a VEN-05 tras este encargo.
4. CHANGELOG y SESSION (con R1–R3). Push y despliegue en verde.
5. Al terminar, informa de qué casos del guion se pueden repetir.

## Criterios de finalización

1. TF-STG-01 a 24 en verde, con negativos comprobados.
2. Ninguna pantalla de error de Laravel visible al usuario en los recorridos de los pasos 1 a 8.
3. Documentación al día y despliegue en verde.

---

# Tests — TF-STG-01 a TF-STG-24

**Convenciones:** PHPUnit con `#[Test]`, PostgreSQL (`vida_testing`), Dado / Cuando / Entonces. Datos: `CircuitoTestSetup` y `TipoCentroTestSetup`. Ubicación junto a los tests del módulo afectado. **[negativo]** = debe fallar sin la protección.

## Grupo A — Inicio y errores

**TF-STG-01 — Rol sin superficie** **[negativo]**: un usuario solo con `consulta_profesional` entra y ve la página informativa (200); no hay cadena de redirecciones.

**TF-STG-02 — Multicentro sin horario hoy**: profesional con dos adscripciones y sin perfil horario para hoy → entra y ve el selector de centro.

**TF-STG-03 — Multicentro con horario hoy**: entra con el centro de su perfil horario del día.

**TF-STG-04 — Primer acceso**: para cada rol y para intervención + supervisión, el primer acceso termina en su página de inicio.

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

**TF-STG-19 — Menú con varios roles**: la directora (intervención + supervisión) tiene el buscador en el menú.

**TF-STG-20 — Ficha para supervisión**: `$coordIgualdad` abre la ficha de una persona no protegida del CIAM y ve los bloques de R2, sin acciones de edición ni documentos.

**TF-STG-21 — Supervisión sin historia ni planes** **[negativo]**: `$coordIgualdad` en rutas de Historia Social, plan y apuntes → 403 propio.

## Grupo E — Administración y documentos (R3)

**TF-STG-22 — Desactivar desde administración**: el administrador intenta desactivar a un profesional con casos → sin enlaces a páginas que no puede abrir; «Avisar a la dirección del centro» envía el aviso a la directora.

**TF-STG-23 — TS ve la carpeta completa** **[negativo]**: `$tsNorte` (solo intervención) ve todos los documentos de la carpeta y no ve el mensaje de documentos del día.

**TF-STG-24 — Varios roles**: un usuario con `intervencion` + `consulta_basica` ve la carpeta completa en su ámbito; `$ventanillaNorte` sigue viendo solo los suyos del día.

## Tabla de estado

| Grupo | Tests | Estado |
|---|---|---|
| A — Inicio y errores | TF-STG-01..07 | |
| B — Ventanilla | TF-STG-08..11 | |
| C — Usuario sin centro | TF-STG-12..16 | |
| D — Protegidas y supervisión | TF-STG-17..21 | |
| E — Administración y documentos | TF-STG-22..24 | |
