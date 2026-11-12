# Tipo de Centro — Addenda D1, D2, D4 y D5: estructura organizativa, ámbito, personas protegidas, bajas y migración

**Fecha:** 11 noviembre 2026
**Estado:** decisiones cerradas por el Delegado Digital el 11 nov 2026.
**Modifica:** `docs/modulo-tipo-centro.md`, `docs/instrucciones-cli/2026-10-tipo-centro-implementacion.md` y `docs/instrucciones-cli/2026-10-tipo-centro-tests.md`. **Esta addenda prevalece sobre esos tres documentos** donde los contradiga.
**Origen:** decisiones D1 («varias UO con varios centros») y D2 («valores de asignación por tipo») de `docs/plan-cierre-funcional-2026-11.md`. D2 está en §6; D4 («personas protegidas fuera del centro»), en §7; D5 («bajas de profesionales»), en §8.

> Para Claude CLI: antes de ejecutar la fase de Tipo de Centro, incorpora esta addenda a los tres documentos (§5) y después impleméntala con el resto de la fase.

---

## 1. Decisiones

| Id | Decisión |
|---|---|
| D1.1 | **Centro y UO son 1:1.** Cada centro municipal tiene su propia UO, con el mismo nombre. Es una UO **instrumental** (sirve para adscribir personal y para el ámbito de permisos) y **no puede tener UO hijas**. Si algún día se modelan equipos o zonas dentro de un centro, serán una entidad propia, no UO |
| D1.2 | **Dos jerarquías.** La **orgánica** es el árbol de UO que ya existe (`parent_id`): el Área con sus direcciones generales, subdirecciones y departamentos por un lado, y las juntas de distrito por otro. La UO de un centro cuelga orgánicamente de la UO a la que está adscrito presupuestaria y laboralmente (p. ej. su junta de distrito). La **funcional** expresa la dependencia operativa y **va por tipo de centro**: todos los centros de un tipo dependen funcionalmente de la UO funcional del tipo (p. ej. el departamento del Área que coordina los CSS) |
| D1.3 | **El ámbito de un usuario es la unión de las dos jerarquías:** sus UO (adscripciones vigentes) con sus descendientes orgánicas, **más** las UO de los centros cuyo tipo tiene la UO funcional dentro de ese ámbito orgánico. Dentro de ese ámbito actúa según su rol, como hoy. Resuelve la decisión pendiente «UO funcional y permisos» de `modulo-tipo-centro.md` §13 |
| D1.4 | **Para atender a un ciudadano hay que estar adscrito a un centro.** Un usuario de una UO sin centro (Área, dirección general, departamento) no tiene centro de trabajo, agenda ni citas, y no realiza operaciones de atención individual (abrir Historia Social, dar o atender citas, registrar atención, apuntes, inscribir en actividades). Sí consulta y supervisa dentro de su ámbito según su rol |
| D1.5 | **Profesional multicentro: una adscripción por centro**, con su perfil horario en cada uno (p. ej. lunes y martes CIAM 1, miércoles y jueves CIAM 2). Aparece en el equipo, los slots, las reuniones internas, los avisos colectivos y la analítica de cada centro |
| D1.6 | **Centro de trabajo de la operación.** Las operaciones toman el centro **del contexto** cuando lo hay: el slot o la agenda en una cita, el centro asignado a la persona al abrir su historia, la actividad en una inscripción. Sin contexto (alta, cita en ventanilla, pantallas de equipo), se usa el **centro activo** del usuario: el que haya elegido en la sesión o, por defecto, el de su perfil horario de ese día; si solo tiene uno, ese |
| D1.7 | **La barra superior muestra el contexto de trabajo:** tipo y nombre del centro activo («CIAM · Puente de Vallecas»), con selector si el usuario tiene varios centros. Un usuario sin centro ve el nombre de su UO, sin selector. Resuelve el «por decidir» de `propuesta-requisitos-y-tareas-guiadas.md` §3 |
| D1.8 | **Supervisión de un centro:** la ejerce quien tiene rol `supervision` y el centro dentro de su ámbito (D1.3), sea por adscripción al centro, por la jerarquía orgánica o por la funcional. Las operaciones de supervisión que exigen centro (bandeja de asignaciones, reparto, cuadrante) se hacen sobre un centro concreto que el supervisor elige entre los de su ámbito |

---

## 2. Cambios en `docs/modulo-tipo-centro.md`

1. **Nota de diseño:** añade una cuarta idea vertebradora: «**Dos jerarquías.** La orgánica es el árbol de UO; la funcional va por tipo de centro. El ámbito de un usuario es la unión de ambas (RN-13).»
2. **RN-03** pasa a decir:
   > **RN-03 — Dependencia funcional en el tipo.** Cada tipo depende de una UO funcional: la que coordina ese servicio. Todos los centros del tipo dependen funcionalmente de ella sin configurarlo centro a centro. La dependencia orgánica del centro es la de su UO instrumental (RN-14) en el árbol de UO, que no cambia y es independiente.
3. **Reglas nuevas:**
   > **RN-13 — Ámbito por las dos jerarquías.** El ámbito de un usuario son sus UO con sus descendientes orgánicas, más las UO de los centros cuyo tipo tiene la UO funcional dentro de ese ámbito orgánico. Cambiar la UO funcional de un tipo cambia de inmediato el ámbito de quien la tenga dentro del suyo.
   >
   > **RN-14 — UO instrumental del centro.** Cada centro tiene su propia UO, con el mismo nombre, y ninguna UO tiene más de un centro. La UO de un centro no puede tener UO hijas. Cuelga orgánicamente de la UO a la que el centro está adscrito (p. ej. su junta de distrito).
   >
   > **RN-15 — Atención individual solo desde un centro.** Abrir Historia Social, dar o atender citas, registrar atención, hacer apuntes e inscribir en actividades exige que el usuario esté adscrito al centro en que se hace la operación. Un usuario sin centro consulta y supervisa según su rol, pero no atiende.
   >
   > **RN-16 — Centro de trabajo.** La operación toma el centro de su contexto; sin contexto, el centro activo del usuario (el elegido en la sesión o, por defecto, el de su perfil horario del día). Un profesional puede estar adscrito a varios centros, con un perfil horario en cada uno.
4. **§2.2** («Para qué se usa la UO funcional en la v1») se sustituye por:
   - Ámbito de permisos de los usuarios de esa UO y de sus ascendientes sobre todos los centros del tipo (RN-13).
   - Agrupar y filtrar centros por la UO que coordina el servicio (listados, informes, analítica).
   - Mostrar en la ficha del centro de quién depende funcionalmente.
5. **§3 (Centro):** añade una fila: «UO del centro | **Aclarado:** UO instrumental propia, 1:1, sin hijas, colgada orgánicamente de su UO de adscripción (RN-14).»
6. **§9 (Gestión y permisos):** añade una nota: «Los usuarios con rol `supervision` cuyo ámbito incluye el centro por la jerarquía funcional (RN-13) lo supervisan como si estuvieran en su UO.»
7. **§11 (Impacto):** añade:
   - `modulo-centros.md` §2.2: la UO del centro es instrumental (RN-14). El ejemplo «un CSS de Vallecas depende administrativamente de la Junta de Distrito» se reformula: la UO del CSS cuelga orgánicamente de la junta.
   - `modulo-usuarios-permisos.md`, «Ámbito por UO»: unión de la jerarquía orgánica y la funcional (RN-13); atención individual solo con adscripción a un centro (RN-15); profesional multicentro (RN-16).
   - `propuesta-requisitos-y-tareas-guiadas.md` §3: decidido el selector de centro en la barra superior y el centro por defecto.
8. **§12 (Decisiones tomadas):** añade las filas de D1.1 a D1.8.
9. **§13 (Pendientes):** quita «UO funcional y permisos».

---

## 3. Cambios en las instrucciones de implementación

### 3.1 Alcance

En «Fuera de alcance», **quita** «Ampliación del ámbito de permisos por la UO funcional». Pasa a estar dentro.

### 3.2 Paso 0 — comprobaciones adicionales

10. **Integridad UO–centro.** Lista los centros cuya UO tenga otro centro, los centros sin UO (externos, si los hay) y las UO de centro con UO hijas. **Si hay alguno de los dos primeros casos entre centros municipales, o alguna UO de centro con hijas, detente e informa.**
11. **Consumidores del ámbito.** Informa, con fichero y línea, de todos los usos de `User::uoSubtreeIds()`, de los `descendientes`/`descendants` de `UnidadOrganizativa` usados para permisos o filtrado, de `AmbitoUoScope`, de `CiudadanoPolicy` y de los recursos de Filament que filtran por «UO y descendientes».
12. **Consumidores del centro del usuario.** Informa de todos los usos de `CentroDeUsuario`, de `uosActivas()->first()` y de `Centro::where('unidad_organizativa_id', …)->first()` (Mi equipo, cuadrante, asignación, perfil horario por defecto, citas, bandejas…).
13. **Operaciones de atención individual.** Lista los puntos de entrada (servicios y acciones Livewire) de: abrir Historia Social, dar/reprogramar/cancelar cita, atender cita, registrar atención, crear apunte, inscribir en actividad. Informa de cuáles comprueban hoy la adscripción a un centro y cuáles no.

### 3.3 Paso 2 — integridad UO–centro

En el modelo `Centro`: no se puede crear ni cambiar un centro municipal a una UO que ya tenga otro centro. En el modelo `UnidadOrganizativa`: no se puede dar `parent_id` = UO de un centro. Las dos reglas, también en base de datos si es posible (índice único parcial en `centros.unidad_organizativa_id`).

### 3.4 Paso 8 bis — Ámbito por las dos jerarquías

1. Servicio `App\Services\AmbitoUsuario` (cache por petición), único punto de cálculo:
   - `uoIds(User): array` — UO de las adscripciones vigentes y sus descendientes orgánicas, **más** las UO de los centros activos cuyo `tipo_centro.unidad_organizativa_id` esté entre esas UO orgánicas.
   - `incluyeCentro(User, Centro): bool`.
   - `centrosEnAmbito(User): Collection`.
2. `User::uoSubtreeIds()` delega en `AmbitoUsuario::uoIds()` (o se sustituye en sus consumidores; elige lo que menos toque y explícalo en el CHANGELOG). **Todos** los consumidores del punto 11 del paso 0 pasan por él. Nadie más calcula ámbitos.
3. Analítica: `UnidadOrganizativa::centrosDependientes(string $jerarquia = 'ambas')` (`organica` / `funcional` / `ambas`), con la misma lógica.
4. Rendimiento: una sola consulta por petición para el ámbito (CTE orgánica + join con `tipos_centro`). Mide el tiempo de una página con scope (p. ej. `MisCasos`) antes y después, y anótalo en el CHANGELOG.

### 3.5 Paso 8 ter — Centro de trabajo

1. `CentroDeUsuario` pasa a ofrecer:
   - `centros(User): Collection` — centros cuya UO es una adscripción vigente directa del usuario (no descendientes ni ámbito funcional).
   - `centroActivo(User): ?Centro` — el elegido en la sesión, si sigue estando en `centros()`; si no, el del perfil horario del usuario para hoy; si no, el único de `centros()`; si no, `null`.
   - `elegir(User, Centro): void` — guarda la elección en la sesión. Rechaza un centro que no esté en `centros()`.
   - `supervisa(User, Centro): bool` — rol `supervision` y `AmbitoUsuario::incluyeCentro()` (D1.8).
2. Sustituye cada uso del punto 12 del paso 0. En cada uno, si la pantalla u operación tiene un centro de contexto, usa ese; si no, `centroActivo()`. Las pantallas de supervisión que exigen centro (bandeja de asignaciones, reparto, cuadrante, Mi equipo) muestran un selector con `centrosEnAmbito()` cuando el supervisor tiene más de uno.
3. **RN-15 en el servidor:** cada punto de entrada del punto 13 del paso 0 comprueba que el usuario está adscrito al centro de la operación. Si no, rechaza con el mensaje «Para atender a ciudadanos tienes que estar adscrito a un centro.» (o «…a este centro.» si tiene otros). Ocultar botones no basta.
4. **Barra superior** (`operativo-shell`): «Tipo · Centro» del centro activo (siglas del tipo y nombre del centro, por la denominación vigente); selector si `centros()` tiene más de uno; nombre de la UO sin selector si no tiene centros. Sin colores por tipo (aplazado). Componentes y clases según el design system y el catálogo de UI.
5. Perfil horario por defecto (`agenda:horarios-por-defecto`): un perfil por adscripción a centro, no por usuario.

### 3.6 Paso 10 — documentación

Además de lo ya previsto: `modulo-centros.md` §2.2, `modulo-usuarios-permisos.md` («Ámbito por UO») y `propuesta-requisitos-y-tareas-guiadas.md` §3, según §2.7 de esta addenda. En el BACKLOG, cierra «Perfil horario por defecto: pendientes» en lo que toca a varias UO con varios centros, y la decisión D1 del plan.

---

## 4. Cambios en los tests

### 4.1 Datos

En `TipoCentroTestSetup`:
- `$uoJuntaVallecas` (UO orgánica, sin centro) y `$uoArea` → `$uoPrimaria` y `$uoIgualdad` como hijas orgánicas de `$uoArea`.
- Cada centro con **su UO instrumental**: `$uoCssNorte`, `$uoCssSur` (hijas orgánicas de una junta `$uoJuntaNorte`) y `$uoCiamVallecas` (hija orgánica de `$uoJuntaVallecas`).
- `$ciamUsera` (tipo `$tipoCiam`, UO `$uoCiamUsera`, hija de otra junta).
- Usuarios: `$coordIgualdad` (`supervision`, adscrito a `$uoIgualdad`); `$jefaJuntaVallecas` (`supervision`, adscrita a `$uoJuntaVallecas`); `$coordPrimaria` (`supervision`, `$uoPrimaria`); `$psicologa` (`intervencion`, adscrita a `$uoCiamVallecas` y `$uoCiamUsera`, perfil horario L-M en Vallecas y X-J en Usera); `$tecnicaArea` (`intervencion`, adscrita a `$uoArea`, sin centro).

### 4.2 Test modificado

**TF-TCE-11 — UO funcional y UO orgánica:** `uoFuncional($ciamVallecas)` es `$uoIgualdad`; su UO es `$uoCiamVallecas`, cuyo padre orgánico es `$uoJuntaVallecas`. Cambiar la UO funcional de `$tipoCiam` cambia la de `$ciamVallecas` sin tocar su UO ni su padre orgánico.

### 4.3 Grupo I — Estructura, ámbito y centro de trabajo (TF-TCE-35 a 46)

**TF-TCE-35 — Ámbito funcional** **[negativo]**
- **Dado** una historia y un ciudadano de `$ciamVallecas`.
- **Cuando** `$coordIgualdad` los consulta (scope y policy).
- **Entonces** los ve, aunque `$uoCiamVallecas` no desciende orgánicamente de `$uoIgualdad`.

**TF-TCE-36 — Ámbito orgánico sin cambios**
- **Dado** el mismo caso.
- **Cuando** `$jefaJuntaVallecas` los consulta.
- **Entonces** los ve; y no ve los de `$ciamUsera`.

**TF-TCE-37 — Fuera de ámbito** **[negativo]**
- **Dado** el mismo caso.
- **Cuando** `$coordPrimaria` los consulta.
- **Entonces** no los ve (salvo acceso aprobado, como hoy).

**TF-TCE-38 — El ámbito sigue a la UO funcional del tipo**
- **Dado** `$tipoCiam` con UO funcional `$uoIgualdad`.
- **Cuando** `$admin` la cambia a `$uoPrimaria` y se hace una petición nueva.
- **Entonces** `$coordPrimaria` ve los datos de `$ciamVallecas` y `$coordIgualdad` deja de verlos.

**TF-TCE-39 — Sin centro no se atiende** **[negativo]**
- **Dado** `$tecnicaArea`.
- **Cuando** intenta abrir una Historia Social, dar una cita y registrar una atención, llamando a los servicios y a las acciones Livewire.
- **Entonces** las tres se rechazan con el mensaje de RN-15. `centros()` está vacío y `centroActivo()` es `null`. Puede consultar a un ciudadano dentro de su ámbito según su rol.

**TF-TCE-40 — Centro activo por defecto del multicentro**
- **Dado** `$psicologa`.
- **Cuando** se pide `centroActivo()` el lunes 2026-11-02 y el miércoles 2026-11-04.
- **Entonces** es `$ciamVallecas` y `$ciamUsera` respectivamente.

**TF-TCE-41 — Elegir centro** **[negativo]**
- **Dado** `$psicologa` el lunes.
- **Cuando** elige `$ciamUsera`; y después intenta elegir `$cssNorte`.
- **Entonces** `centroActivo()` pasa a `$ciamUsera` para esa sesión; la segunda elección se rechaza.

**TF-TCE-42 — El contexto manda**
- **Dado** `$psicologa` con centro activo `$ciamVallecas` y una cita suya en un slot de `$ciamUsera`.
- **Cuando** atiende la cita y registra el apunte.
- **Entonces** la operación se hace en `$ciamUsera` (centro de la cita), sin cambiar su centro activo.

**TF-TCE-43 — Barra superior**
- **Dado** `$psicologa`, una usuaria adscrita solo a `$ciamVallecas` y `$tecnicaArea`.
- **Cuando** cargan una pantalla operativa.
- **Entonces** la primera ve «CIAM · Puente de Vallecas» (el lunes) con selector de dos centros; la segunda, el mismo texto sin selector; la tercera, el nombre de `$uoArea` sin selector.

**TF-TCE-44 — Pantallas de equipo con el centro activo**
- **Dado** una supervisora adscrita a `$uoCiamVallecas` y a `$uoCiamUsera`.
- **Cuando** abre Mi equipo con cada centro activo.
- **Entonces** ve el equipo del centro activo en cada caso; `$psicologa` aparece en los dos.

**TF-TCE-45 — Integridad UO–centro** **[negativo]**
- **Dado** `$uoCssNorte`.
- **Cuando** se intenta crear otro centro con esa UO; y crear una UO con `parent_id = $uoCssNorte`.
- **Entonces** las dos se rechazan.

**TF-TCE-46 — Supervisión funcional y centros dependientes**
- **Dado** `$coordIgualdad`.
- **Cuando** se evalúa `supervisa()` sobre `$ciamVallecas`, `$ciamUsera` y `$cssNorte`; y se pide `$uoArea->centrosDependientes('funcional')`, `('organica')` y `('ambas')`.
- **Entonces** `supervisa()` es verdadero para los dos CIAM y falso para `$cssNorte`. `$uoArea` tiene los cuatro centros por la funcional, ninguno por la orgánica y los cuatro por ambas.

Tabla de estado: añade la fila «I — Estructura, ámbito y centro de trabajo | TF-TCE-35..46». La cabecera del documento pasa a «TF-TCE-01 a TF-TCE-46».

---

## 5. Cómo se incorpora

1. Claude CLI aplica §2, §3 y §4 a los tres documentos del repositorio, sin cambiar nada más, y anota en cada uno, bajo la cabecera, «Incluye la addenda D1 (11 nov 2026)».
2. Esta addenda se guarda en `docs/instrucciones-cli/2026-11-tipo-centro-addenda-d1.md` y se añade a la tabla de `CLAUDE.md` §6.
3. Se ejecuta la fase completa, con la migración según §6.

---

## 6. D2 — Migración: el tipo manda

**Decisión (11 nov 2026):** como todavía no hay datos reales, **la configuración del tipo prevalece sobre la de cada centro**. La migración no se detiene por diferencias entre centros del mismo tipo: asigna al tipo los valores de esta sección y descarta los del centro, dejando constancia. Y el centro **no puede** configurar nada que se configure en el tipo: las propiedades «solo tipo» no se editan desde el centro (ya previsto en `modulo-tipo-centro.md` §4 y §9) y **sus columnas y tablas en el centro se eliminan** en esta misma fase.

### 6.1 Valores iniciales de los tipos

| Propiedad | `css_general` | `ciam` | Otros tipos del catálogo |
|---|---|---|---|
| Adscripción | Por domicilio | Libre elección | Por domicilio |
| Modo de referencia | Quien abre | Quien abre | Quien abre |
| Ventana de reparto | 12 meses | 12 meses | 12 meses |
| Meses para caso dormido | 6 | 6 | 6 |
| Segmentos de población | Unión de los de sus centros | Unión de los de sus centros | Unión de los de sus centros |
| Permite restricción en centro | No | No | No |

«Quien abre» es el comportamiento actual de staging: así la migración no cambia cómo funciona nada. El sorteo se activa después desde la ficha del tipo, cuando se pruebe, y solo afecta a las historias nuevas (RN-07).

Prestaciones: el catálogo del tipo es el de `prestacion_tipo_centro`, como ya preveía el diseño; lo que haya en `centro_prestacion` se descarta.

Horario: **no cambia.** Es una propiedad sobrescribible, no «solo tipo»: los `HorarioCentro` existentes quedan como horario propio (`hereda_horario_tipo = false`), como dice el paso 5. Cada tipo recibe como horario por defecto el más frecuente entre sus centros (en empate, el del centro con menor `id`); si un tipo no tiene centros con horario, L-V 9:00-14:00.

### 6.2 Cambios en las instrucciones de implementación

- **Paso 3:** se sustituye «la migración aborta con un informe de las diferencias» por: la migración asigna al tipo los valores de §6.1 y escribe en el CHANGELOG, por tipo, **los valores de centro descartados** (centro, propiedad, valor anterior). No aborta por diferencias. Sigue deteniéndose si un centro no tiene tipo deducible (paso 0.3).
- **Eliminación de lo que pasa al tipo** (sustituye a «pregunta antes de eliminar» en los pasos 2, 3, 5 y 6): en una migración posterior a la de datos, y después de comprobar con `grep` que nada los lee fuera de las migraciones, se eliminan:
  - `centros.inscripcion_libre`, `centros.tipo_centro` (varchar), `centros.modo_asignacion_referencia`, `centros.ventana_reparto_meses`, `centros.meses_inactividad_caso` y `centros.horario` (JSON; pasa a ser un accesor calculado);
  - las tablas `centro_segmento_poblacion` y `centro_prestacion`;
  - las columnas varchar `prestacion_tipo_centro.tipo_centro` y `asignaciones_centro.tipo_centro`.
  
  El `down()` las recrea vacías. Las entradas del catálogo `centro.tipo` se dejan con `activo = false`, como estaba previsto.
- **Formularios:** `CentroResource` no muestra campos editables de esas propiedades; las enseña en lectura con el valor del tipo y un enlace a la ficha del tipo. Los servicios rechazan cualquier petición que intente fijarlas en un centro.

### 6.3 Cambios en los tests

- **TF-TCE-30** pasa a: **Dado** dos centros de `css_general` con `modo_asignacion_referencia` distinto en `centros`. **Cuando** se ejecuta la migración de datos del paso 3. **Entonces** no aborta; `$tipoCss` queda con el valor de §6.1 (`quien_abre`); el informe de descartes nombra el centro, la propiedad y el valor descartado.
- **TF-TCE-34** añade: las columnas y tablas de §6.2 ya no existen; `centros.horario` se sigue pudiendo leer como accesor y coincide con el horario efectivo.
- **TF-TCE-47 — El centro no fija lo que es del tipo** **[negativo]**: **Dado** `$admin`. **Cuando** envía al servicio de centros y al formulario de `CentroResource` una petición con `inscripcion_libre`, `modo_asignacion_referencia` o segmentos para `$cssNorte`. **Entonces** se rechaza (o se ignora con aviso) y la configuración efectiva de `$cssNorte` sigue siendo la de `$tipoCss`.

La cabecera de los tests pasa a «TF-TCE-01 a TF-TCE-47» y el grupo I a «TF-TCE-35..47».

---

## 7. D4 — Personas protegidas: la protección prevalece sobre el ámbito

**Decisión (11 nov 2026):** la dirección del centro ve siempre a las personas protegidas que atiende su centro. **Las UO superiores, no**: ni por la jerarquía orgánica ni por la funcional. Un director general, como cualquier otro usuario de fuera del centro, solo accede a los datos de una persona protegida si lo solicita, lo motiva y **el director del centro** se lo concede.

### 7.1 Reglas

**RN-17 — Ámbito reducido para personas protegidas.** Para una persona de colectivo especialmente protegido, el ámbito de gestión (nivel 1) se limita a los usuarios **adscritos directamente a la UO del centro** de su Historia Social, según su rol. Para cualquier otro usuario, aunque el centro esté dentro de su ámbito orgánico o funcional (RN-13), la persona está fuera de ámbito: la ve como «Persona con protección especial», sin datos identificativos, y necesita un acceso aprobado y vigente.

**RN-18 — Concede el director del centro.** Las solicitudes de acceso a una persona protegida, con motivo obligatorio, las resuelve el **director vigente del centro** de su Historia Social (`DirectorCentro`). Si el centro no tiene director registrado, las resuelve quien tenga rol `supervision` adscrito directamente a la UO del centro. Nadie resuelve su propia solicitud. El acceso concedido tiene fecha de caducidad y queda auditado (`acceso_restringido`).

**RN-19 — Bandeja de asignaciones.** Las entradas de personas protegidas solo las ve con identidad, y solo las resuelve, la supervisión adscrita directamente al centro. Los supervisores que llegan al centro por una UO superior ven «Persona con protección especial» y no pueden resolverlas. Así queda resuelto el pendiente «Bandeja de asignaciones con protegidos» del BACKLOG.

Los datos agregados (recuentos, analítica) pueden incluir a personas protegidas sin identificarlas.

### 7.2 Hechos que no hay que redescubrir

- `CiudadanoPolicy::view()` da nivel 1 si la UO de la Historia Social está en `uoSubtreeIds()`, **sea o no persona protegida**. Hoy un usuario de una UO superior orgánica ve a las personas protegidas de los centros de su subárbol. Con RN-13, también los vería por la jerarquía funcional. RN-17 lo corrige.
- `BuscarCiudadanoPage` crea la solicitud (`AccesoProtegido`, estado `pendiente`) y avisa «al supervisor de la UO».
- `AprobacionesPage` tiene la pestaña «Accesos a expedientes» y el sidebar cuenta los pendientes, pero **no hay acción para aprobar o denegar accesos**: solo para roles. Ningún código pone un `AccesoProtegido` en `aprobado`.

### 7.3 Qué hacer (paso 8 quater de las instrucciones)

1. `AmbitoUsuario` (paso 8 bis): nuevo método `gestionaProtegido(User, Ciudadano|HistoriaSocial): bool`. Es verdadero si la UO de la historia está entre las adscripciones **directas** vigentes del usuario.
2. `CiudadanoPolicy` (`view`, `update` y el resto de métodos con nivel 1), `HistoriaSocialPolicy`, `AmbitoUoScope` y `AccesoExpediente`: para personas protegidas, nivel 1 solo con `gestionaProtegido()`; en otro caso, el camino de consulta externa (acceso aprobado vigente). Para personas no protegidas, sin cambios (ámbito de RN-13).
3. Resolución de accesos en `AprobacionesPage`, pestaña «Accesos a expedientes»: aprobar (con fecha de caducidad; por defecto, la configurada) o denegar (con motivo). Solo el resolutor de RN-18; validado en el servidor. Se avisa al solicitante. Todo queda auditado.
4. El aviso de una solicitud nueva va al resolutor de RN-18, no «al supervisor de la UO».
5. Bandeja de asignaciones según RN-19.

### 7.4 Tests (TF-TCE-48 a 54)

**TF-TCE-48 — La UO superior no ve a la persona protegida** **[negativo]**: una persona protegida con historia en `$ciamVallecas`. `$coordIgualdad` (funcional) y `$jefaJuntaVallecas` (orgánica) la ven como «Persona con protección especial», sin nombre, documento ni enlace, por scope, policy y buscador. Una persona no protegida del mismo centro sí la ven (TF-TCE-35 y 36 siguen en verde).

**TF-TCE-49 — El centro sí la ve**: una profesional de `intervencion` y la directora, adscritas a `$uoCiamVallecas`, la ven con nivel 1.

**TF-TCE-50 — Concede el director del centro** **[negativo]**: `$coordIgualdad` solicita acceso con motivo. Lo intentan resolver `$coordIgualdad`, `$jefaJuntaVallecas` y la directora de `$ciamVallecas`. Solo la directora puede; tras aprobarlo, `$coordIgualdad` la ve hasta la caducidad y su acceso se audita como `acceso_restringido`.

**TF-TCE-51 — Sin director, la supervisión del centro**: sin `DirectorCentro` vigente en `$ciamVallecas`, resuelve quien tiene `supervision` adscrito a `$uoCiamVallecas`, y no `$coordIgualdad`.

**TF-TCE-52 — Nadie resuelve lo suyo**: la directora solicita acceso a una persona protegida de otro centro del que también es supervisora por ámbito superior; no puede resolverlo ella.

**TF-TCE-53 — Bandeja de asignaciones** **[negativo]**: una entrada de una persona protegida de `$ciamVallecas`. La supervisión adscrita al centro la ve con identidad y la resuelve; `$coordIgualdad` la ve sin identidad y la acción de resolver se rechaza en el servidor.

**TF-TCE-54 — El aviso de solicitud va al director**: al crear una solicitud desde el buscador, la alerta llega a la directora de `$ciamVallecas` y no a `$coordIgualdad` ni a `$jefaJuntaVallecas`.

---

## 8. D5 — Bajas de profesionales: los casos son del centro

**Decisión (11 nov 2026):**
- **No se borran usuarios ni profesionales.** Se desactivan, y sus registros se conservan.
- **Un profesional no puede dejar un centro mientras sea referencia de casos abiertos en él.** El sistema le pide al supervisor que reparta antes sus casos entre otros profesionales del centro (el reparto por salida ya existe, `RepartoCasosPage`), y no cierra la adscripción hasta que esté libre de casos.
- **Los casos son del centro, asignados a un profesional;** no son del profesional. Si un profesional cambia de centro, sus casos se quedan en el centro de origen y se reparten allí. Que un caso cambie de centro porque la persona cambia de domicilio sigue su propio flujo (`modulo-asignacion.md` RN-10).

### 8.1 Reglas

**RN-20 — Sin borrado.** `User` y `Profesional` no se eliminan, ni siquiera con borrado lógico, desde ninguna pantalla ni servicio. La baja de un usuario es su desactivación: no puede iniciar sesión, conserva su historial y sus registros siguen apuntando a él.

**RN-21 — Salida de un centro.** Cerrar la adscripción de un profesional a la UO de un centro exige que no tenga asignaciones de referencia vigentes en ese centro ni citas confirmadas futuras en sus slots de ese centro. Si las tiene, la acción ofrece «Repartir casos» (reparto por salida) y «Reasignar citas» (flujo de ausencia existente), y solo cierra la adscripción cuando las dos cosas están resueltas.

**RN-22 — Desactivación.** Desactivar un usuario exige lo mismo de RN-21 en todos sus centros.

**RN-23 — El caso pertenece al centro.** La asignación de referencia es de una historia en un centro. Ningún cambio de adscripción del profesional mueve, cierra ni reasigna casos automáticamente.

### 8.2 Qué hacer (paso 8 quinquies de las instrucciones)

1. Busca todas las vías de borrado de `User` y `Profesional` (acciones de Filament, policies `delete`/`forceDelete`, servicios, comandos) y elimínalas o haz que la policy las deniegue. Sustitúyelas por «Desactivar» donde haya acción de borrado. **Si quitar `SoftDeletes` de los modelos afecta a datos existentes, pregunta antes.**
2. En el servicio que cierra adscripciones (localízalo en el paso 0) y en la desactivación de usuarios, aplica RN-21 y RN-22 en el servidor, con un mensaje que diga cuántos casos y citas quedan, y enlaces a «Repartir casos» y a la gestión de la ausencia.
3. Comprueba que la asignación de referencia queda ligada al centro (historia + centro) y que nada en el cambio de adscripción la toca (RN-23). Si no es así, detente e informa.
4. Cierra en el BACKLOG «Borrar un usuario con casos vigentes» con esta decisión.

### 8.3 Tests (TF-TCE-55 a 59)

**TF-TCE-55 — No se borra** **[negativo]**: `$admin` intenta borrar un usuario y un profesional por Filament y por servicio; se rechaza y siguen existiendo sin `deleted_at`. «Desactivar» sí funciona y el usuario no puede iniciar sesión.

**TF-TCE-56 — Salida bloqueada con casos** **[negativo]**: `$psicologa` es referencia de 3 casos en `$ciamVallecas`. Cerrar su adscripción a `$uoCiamVallecas` se rechaza con el recuento y el enlace al reparto. Sigue adscrita.

**TF-TCE-57 — Salida tras el reparto**: tras confirmar el reparto de sus 3 casos y no tener citas futuras en `$ciamVallecas`, la adscripción se cierra. Sus casos tienen nueva referencia en `$ciamVallecas`; su adscripción a `$uoCiamUsera` y sus casos allí no cambian.

**TF-TCE-58 — Salida bloqueada con citas**: sin casos, pero con una cita confirmada la semana próxima en un slot de `$ciamVallecas`. La salida se rechaza hasta reasignar la cita.

**TF-TCE-59 — Los casos no viajan**: `$psicologa` se adscribe a un tercer centro. Sus asignaciones en `$ciamVallecas` y `$ciamUsera` no cambian y no aparecen en el nuevo centro.

La cabecera de los tests pasa a «TF-TCE-01 a TF-TCE-59». Añade a la tabla de estado «J — Personas protegidas | TF-TCE-48..54» y «K — Bajas de profesionales | TF-TCE-55..59».
