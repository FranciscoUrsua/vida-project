# Tipo de Centro — Diseño funcional

> Incluye la addenda D1 (11 nov 2026): `docs/instrucciones-cli/2026-11-tipo-centro-addenda-d1.md` (D1, D2, D4 y D5).

Módulos afectados: Centro (tipo de centro, centro, configuración efectiva), Organizacion (UO funcional), Prestaciones (`prestacion_tipo_centro`), Agenda (horario y calendario), Intervencion (tipos de plan y fichas de valoración disponibles), Usuarios (permisos de administración y dirección de centro). Estado: diseño funcional v1 cerrado. Sin implementar. Última revisión: octubre 2026

Documentos relacionados:

- Instrucciones de implementación: `docs/instrucciones-cli/2026-10-tipo-centro-implementacion.md`
- Tests funcionales: `docs/instrucciones-cli/2026-10-tipo-centro-tests.md` (TF-TCE-01 a TF-TCE-59)
- Modifica: `docs/modulo-centros.md`, `docs/modulo-asignacion.md`, `docs/modulo-agenda.md`, `docs/modulo-prestaciones.md` (ver §11).

## Nota de diseño

Hoy el tipo de un centro es una clave del catálogo `centro.tipo` que solo usa el catálogo de prestaciones (`prestacion_tipo_centro`). Todo lo demás se configura centro a centro. Eso tiene dos problemas:

- No hay forma de colgar todos los centros de un mismo tipo de la UO que dirige ese servicio.
- Lo que es común a todos los centros de un tipo (horario, prestaciones, público, reglas de asignación) se repite en cada centro y puede divergir sin que nadie lo haya decidido.

Este diseño convierte el tipo de centro en una entidad propia. Cuatro ideas lo vertebran:

- El tipo manda; el centro solo personaliza lo que el tipo permite. Cada propiedad tiene una política de herencia fija, conocida de antemano. No se decide caso a caso.
- Identidad estable, denominación cambiante. Un tipo puede cambiar de nombre y de catálogo sin dejar de ser el mismo tipo (los CAF pasan a ser CEF). Si un cambio merece un tipo nuevo o no es una decisión de la dirección, a veces política; el sistema ofrece los dos caminos y no decide.
- Un centro es de un tipo toda su vida. Si tiene que ser de otro tipo, se da de baja y se da de alta otro centro, enlazados.
- **Dos jerarquías.** La orgánica es el árbol de UO; la funcional va por tipo de centro. El ámbito de un usuario es la unión de ambas (RN-13).

## 1. Reglas de negocio

**RN-01 — Un centro, un tipo.** Todo centro tiene exactamente un tipo de centro, obligatorio desde el alta. El tipo de un centro no se cambia nunca. Si un centro debe pasar a otro tipo, se da de baja y se da de alta uno nuevo que lo referencia como antecesor.

**RN-02 — Identidad estable del tipo.** El tipo tiene un código interno inmutable. Su nombre y sus siglas tienen historial con vigencia: un renombrado cierra la denominación vigente y abre otra, sin cambiar la identidad del tipo ni la de sus centros.

**RN-03 — Dependencia funcional en el tipo.** Cada tipo depende de una UO funcional: la que coordina ese servicio. Todos los centros del tipo dependen funcionalmente de ella sin configurarlo centro a centro. La dependencia orgánica del centro es la de su UO instrumental (RN-14) en el árbol de UO, que no cambia y es independiente.

**RN-04 — Políticas de herencia.** Cada propiedad sigue una de estas políticas (detalle en §4):

| Política | Regla |
|---|---|
| Solo tipo | El centro no puede modificarla |
| Sobrescribible | El tipo da el valor por defecto; el centro puede sustituirlo |
| Acumulable | Se aplican los valores del tipo y los del centro a la vez |
| Restringible | El centro puede excluir elementos del tipo, nunca añadir. Solo si el tipo lo permite (RN-08) |
| Solo centro | Dato propio del centro |

**RN-05 — Valor efectivo.** La aplicación nunca lee la configuración del tipo o la del centro por separado: usa siempre el valor efectivo, que es el valor propio del centro si existe y, si no, el del tipo.

**RN-06 — Sustitución completa.** Un valor propio sustituye entero al del tipo. Un horario propio no se mezcla franja a franja con el del tipo.

**RN-07 — Propagación.** Un cambio en el tipo se aplica de inmediato a todos los centros que heredan esa propiedad, y no afecta a los que la tienen personalizada. Ningún cambio del tipo reasigna personas, profesionales de referencia ni casos.

**RN-08 — Restricción de catálogo desactivada por defecto.** El tipo tiene el indicador permite restricción en centro, a no por defecto. Con el indicador a no, todos los centros del tipo tienen exactamente el catálogo del tipo. En Madrid no se usa; existe para otros modelos organizativos.

**RN-09 — Reglas de asignación por tipo.** La regla de asignación de centro (por domicilio o libre elección) y la de profesional de referencia (sorteo, libre elección, quien abre) son iguales para todos los centros de un tipo y se definen en el tipo.

**RN-10 — Los cambios de tipo los decide la dirección.** Renombrar o cambiar el catálogo de un tipo existente, o crear un tipo nuevo y dar de baja el anterior, son operaciones del administrador del sistema. El sistema no valida si un cambio es "sustantivo".

**RN-11 — Bajas, nunca borrados.** Un tipo con centros activos no se puede dar de baja. No se puede dar de alta un centro en un tipo dado de baja. Ni tipos ni centros se borran: los referencian historias, asignaciones, prescripciones e informes.

**RN-12 — Todo deja rastro.** Los cambios en tipos y centros se versionan (estrategia transversal versiones) y se auditan. Las denominaciones y los horarios tienen vigencia explícita, de modo que siempre se puede responder "cómo era a fecha X".

**RN-13 — Ámbito por las dos jerarquías.** El ámbito de un usuario son sus UO con sus descendientes orgánicas, más las UO de los centros cuyo tipo tiene la UO funcional dentro de ese ámbito orgánico. Cambiar la UO funcional de un tipo cambia de inmediato el ámbito de quien la tenga dentro del suyo.

**RN-14 — UO instrumental del centro.** Cada centro tiene su propia UO, con el mismo nombre, y ninguna UO tiene más de un centro. La UO de un centro no puede tener UO hijas. Cuelga orgánicamente de la UO a la que el centro está adscrito (p. ej. su junta de distrito).

**RN-15 — Atención individual solo desde un centro.** Abrir Historia Social, dar o atender citas, registrar atención, hacer apuntes e inscribir en actividades exige que el usuario esté adscrito al centro en que se hace la operación. Un usuario sin centro consulta y supervisa según su rol, pero no atiende.

**RN-16 — Centro de trabajo.** La operación toma el centro de su contexto; sin contexto, el centro activo del usuario (el elegido en la sesión o, por defecto, el de su perfil horario del día). Un profesional puede estar adscrito a varios centros, con un perfil horario en cada uno.

**RN-17 — Ámbito reducido para personas protegidas.** Para una persona de colectivo especialmente protegido, el ámbito de gestión (nivel 1) se limita a los usuarios **adscritos directamente a la UO del centro** de su Historia Social, según su rol. Para cualquier otro usuario, aunque el centro esté dentro de su ámbito orgánico o funcional (RN-13), la persona está fuera de ámbito: la ve como «Persona con protección especial», sin datos identificativos, y necesita un acceso aprobado y vigente.

**RN-18 — Concede el director del centro.** Las solicitudes de acceso a una persona protegida, con motivo obligatorio, las resuelve el **director vigente del centro** de su Historia Social (`DirectorCentro`). Si el centro no tiene director registrado, las resuelve quien tenga rol `supervision` adscrito directamente a la UO del centro. Nadie resuelve su propia solicitud. El acceso concedido tiene fecha de caducidad y queda auditado (`acceso_restringido`).

**RN-19 — Bandeja de asignaciones.** Las entradas de personas protegidas solo las ve con identidad, y solo las resuelve, la supervisión adscrita directamente al centro. Los supervisores que llegan al centro por una UO superior ven «Persona con protección especial» y no pueden resolverlas.

**RN-20 — Sin borrado.** `User` y `Profesional` no se eliminan, ni siquiera con borrado lógico, desde ninguna pantalla ni servicio. La baja de un usuario es su desactivación: no puede iniciar sesión, conserva su historial y sus registros siguen apuntando a él.

**RN-21 — Salida de un centro.** Cerrar la adscripción de un profesional a la UO de un centro exige que no tenga asignaciones de referencia vigentes en ese centro ni citas confirmadas futuras en sus slots de ese centro. Si las tiene, la acción ofrece «Repartir casos» (reparto por salida) y «Reasignar citas» (flujo de ausencia existente), y solo cierra la adscripción cuando las dos cosas están resueltas.

**RN-22 — Desactivación.** Desactivar un usuario exige lo mismo de RN-21 en todos sus centros.

**RN-23 — El caso pertenece al centro.** La asignación de referencia es de una historia en un centro. Ningún cambio de adscripción del profesional mueve, cierra ni reasigna casos automáticamente.

## 2. Tipo de Centro

### 2.1 Qué contiene

| Atributo | Descripción | Obligatorio |
|---|---|---|
| Código | Clave interna inmutable (p. ej. `css_general`, `ciam`) | Sí |
| Denominación | Nombre y siglas, con vigencia (§8.1) | Sí |
| UO funcional | UO que dirige el servicio (RN-03) | Sí |
| Público destinatario | Segmentos de población a los que se dirige | Sí |
| Catálogo de prestaciones | Prestaciones que ofrecen sus centros (`prestacion_tipo_centro`, §6.1) | Sí |
| Tipos de plan de intervención | Planes que se pueden abrir en sus centros (PISO, PIA…) | Sí |
| Fichas de valoración | Tipos de ficha disponibles en sus centros | No (vacío = todas) |
| Horario por defecto | Horario semanal con vigencia (§5) | Sí |
| Cierres de calendario comunes | Festivos y cierres de todos sus centros (§5.2) | No |
| Protocolo base | Texto con quién hace qué en los centros del tipo | No |
| Regla de asignación de centro | Por domicilio o libre elección (`inscripcion_libre`) | Sí |
| Regla de asignación de referencia | Sorteo, libre elección o quien abre | Sí |
| Parámetros del reparto | Ventana de reparto y meses para considerar un caso dormido (`modulo-asignacion.md` §4.2 y §6) | Sí, con valor por defecto |
| Permite restricción en centro | Indicador de RN-08 | Sí, por defecto no |
| Estado y fechas de alta y baja | RN-11 | Sí |

La equivalencia con la tipología autonómica (HSU) no forma parte del tipo de centro: se define en cada prestación.

### 2.2 Para qué se usa la UO funcional en la v1

- Ámbito de permisos de los usuarios de esa UO y de sus ascendientes sobre todos los centros del tipo (RN-13).
- Agrupar y filtrar centros por la UO que coordina el servicio (listados, informes, analítica).
- Mostrar en la ficha del centro de quién depende funcionalmente.

## 3. Centro

El centro conserva lo que ya tiene en `modulo-centros.md` §4.1, con estos cambios:

| Atributo | Cambio |
|---|---|
| Tipo de centro | Nuevo, obligatorio, inmutable |
| Centro antecesor | Nuevo, opcional. Centro al que sustituye (RN-01) |
| Nombre y nombre corto | Pasan a tener historial con vigencia (§8.1) |
| `inscripcion_libre` | Pasa al tipo |
| Segmentos de población | Pasan al tipo |
| Prestaciones del centro (`centro_prestacion`) | Dejan de configurarse en el centro. Se calculan del tipo menos las exclusiones (§6) |
| horario (JSON de visualización) | Se calcula del horario efectivo. Deja de editarse |
| Horario propio | Nuevo comportamiento: opcional; si no tiene, hereda el del tipo (§5) |
| Cierres de calendario propios | Nuevo, opcional; se suman a los del tipo |
| Protocolo propio | Nuevo, opcional; sustituye al del tipo |
| Exclusiones de catálogo | Nuevo, solo si el tipo lo permite |
| UO del centro | **Aclarado:** UO instrumental propia, 1:1, sin hijas, colgada orgánicamente de su UO de adscripción (RN-14). |

Se mantienen sin cambios: UO administrativa, tipo de gestión, dirección y contacto, ámbito territorial, colecciones de plazas, actividades, salas, directores, inscripciones, estado y fechas.

Dos centros pueden compartir dirección postal (p. ej. un CSS y un CIAM en el mismo edificio). Son dos centros independientes; no hay entidad "sede" en la v1.

## 4. Herencia y configuración efectiva

| Propiedad | Política | Notas |
|---|---|---|
| UO funcional | Solo tipo |  |
| Público destinatario | Solo tipo |  |
| Regla de asignación de centro | Solo tipo |  |
| Regla de asignación de referencia y parámetros del reparto | Solo tipo |  |
| Horario semanal | Sobrescribible | Sustitución completa (RN-06) |
| Protocolo | Sobrescribible |  |
| Cierres de calendario | Acumulable | Prioridad en §5.2 |
| Prestaciones | Restringible | Solo con RN-08 activo |
| Tipos de plan | Restringible | Solo con RN-08 activo |
| Fichas de valoración | Restringible | Solo con RN-08 activo |
| Denominación, dirección, contacto, UO administrativa, ámbito territorial, plazas, actividades, salas, directores | Solo centro |  |

En la ficha del centro, cada propiedad sobrescribible se muestra como Heredado del tipo o Personalizado, con la acción Volver al valor del tipo. Las propiedades "solo tipo" se muestran en modo lectura, con el valor del tipo.

## 5. Horario y calendario

### 5.1 Horario semanal

- El tipo tiene un horario por defecto: días laborables, apertura y cierre, y horario de atención, con vigencia (`vigente_desde`, `vigente_hasta`). Un horario de invierno y otro de verano son dos horarios con vigencias distintas.
- El horario de atención al público y el de citas son el mismo. No se distinguen.
- El centro sigue teniendo su `HorarioCentro` (`modulo-agenda.md` §2.1), porque ahí viven también los parámetros de agenda (modo de agenda, márgenes, umbral de ausencia prolongada, plazos de urgencia…). Lo que cambia es que su horario puede ser heredado del tipo o propio.
- Nunca puede haber dos horarios vigentes a la vez para el mismo tipo, ni para el mismo centro.

### 5.2 Cierres de calendario

Días o periodos en que el centro no abre o abre con horario reducido: festivos, cierres puntuales, jornada reducida de agosto.

- Se pueden definir en el tipo (para todos sus centros) y en el centro. Se aplican los dos.
- Para un día concreto manda, por este orden: el cierre del centro, el cierre del tipo y, si no hay ninguno, el horario semanal efectivo.
- La generación de cuadrantes y slots de Agenda usa el calendario efectivo.
- Añadir un cierre no cancela citas ya dadas. El sistema muestra cuántas citas quedan afectadas y en qué centros; su gestión sigue el flujo normal de citas (el sistema nunca cierra una cita por su cuenta).

## 6. Catálogo

### 6.1 Prestaciones

- El catálogo de prestaciones del tipo es `prestacion_tipo_centro`, que ya existe en el módulo de Prestaciones. Se sigue editando desde la ficha de la prestación, de modo que el versionado de prestaciones conserva el historial de qué tipos la ofrecían en cada momento.
- La ficha del tipo muestra sus prestaciones en modo lectura, con enlace a cada una.
- Las prestaciones del centro son las de su tipo (menos sus exclusiones, si RN-08 está activo). La tabla `centro_prestacion` deja de ser la fuente.

### 6.2 Tipos de plan y fichas de valoración

- El tipo indica qué tipos de plan de intervención se pueden abrir en sus centros (p. ej. CSS: PISO; CIAM: PIA).
- El tipo puede limitar los tipos de ficha de valoración disponibles en sus centros. Si no limita ninguno, están todos.

### 6.3 Restricción en centro

Con el indicador de RN-08 activo, el director del centro puede excluir prestaciones, tipos de plan o fichas del catálogo de su tipo. No puede añadir elementos que el tipo no tenga: si un centro necesita algo que su tipo no ofrece, se amplía el catálogo del tipo o se crea un tipo nuevo.

Si el administrador desactiva el indicador, las exclusiones se conservan pero dejan de aplicarse.

## 7. Asignación

Este diseño cambia dónde se configuran las reglas de `modulo-asignacion.md`, no cómo funcionan:

| Configuración | Antes (`modulo-asignacion.md` §8) | Ahora |
|---|---|---|
| Tipo de centro | Clave de catálogo en el centro | Entidad `TipoCentro` |
| Adscripción por domicilio o libre elección | Centro | Tipo |
| Modo de asignación de referencia | Centro | Tipo |
| Ventana de reparto | Centro | Tipo |
| Meses sin apuntes para caso dormido | Centro | Tipo |
| Ámbito territorial | Centro | Centro (sin cambios) |
| Umbral de ausencia prolongada | Horario del centro | Horario del centro (sin cambios) |

La regla "un centro vigente por tipo de centro" (RN-02 de asignación) usa la nueva entidad.

Cobertura de las áreas: este diseño no añade validaciones sobre las áreas de cobertura de los centros de un tipo; quedan a cargo de quien las define.

Cambiar la regla de asignación de un tipo no reasigna a nadie: solo afecta a las asignaciones nuevas (RN-07).

## 8. Evolución del tipo

### 8.1 Cambiar el tipo existente

Ejemplo: los Centros de Apoyo a las Familias (CAF) pasan a llamarse Centros Especializados en Familias (CEF) y asumen prestaciones que hasta entonces eran de los Centros de Atención a la Infancia (CAI).

- Renombrado. El administrador programa una denominación nueva con su fecha de entrada en vigor. Hasta esa fecha se muestra la anterior. Pantallas e informes muestran la denominación vigente en la fecha a la que se refieren: un informe de 2025 dice CAF; uno de 2027, CEF. El código y la identidad del tipo no cambian, ni los de sus centros.
- Cambio de catálogo. Se quitan las prestaciones del tipo CAI y se añaden al tipo CEF desde la ficha de cada prestación. Las prescripciones y planes ya existentes no cambian: siguen en el centro donde se hicieron hasta su cierre natural. Las nuevas se hacen en el tipo que ahora ofrece la prestación.
- Los centros también pueden renombrarse con el mismo mecanismo.

### 8.2 Crear un tipo nuevo

- Se crea el tipo nuevo con su configuración.
- Se dan de alta sus centros, cada uno con su centro antecesor si sustituye a otro.
- Se dan de baja los centros antiguos y, cuando no quede ninguno activo, el tipo antiguo.

Trasladar en bloque a las personas y casos de los centros antiguos a los nuevos no forma parte de la v1. Se usan las asignaciones manuales y el reparto de `modulo-asignacion.md`.

### 8.3 Un centro no cambia de tipo

No hay operación de "cambiar tipo". Se sigue §8.2 para ese centro.

## 9. Gestión y permisos

| Acción | Administrador del sistema | Director del centro |
|---|---|---|
| Crear, modificar, renombrar y dar de baja tipos | Sí | No |
| Activar la restricción en centro (RN-08) | Sí | No |
| Crear y dar de baja centros | Sí | No |
| Ver la configuración que su centro hereda del tipo | Sí | Sí, en lectura |
| Editar datos de contacto y descripción del centro | Sí | Sí, solo su centro |
| Personalizar horario, cierres y protocolo del centro | Sí | Sí, solo su centro |
| Excluir elementos del catálogo (si RN-08 está activo) | Sí | Sí, solo su centro |
| Renombrar un centro | Sí | No |

El director solo ve y edita su centro. Las propiedades "solo tipo" no son editables desde el centro por nadie: se cambian en el tipo. Las restricciones se aplican en el servidor, no solo ocultando campos.

Los usuarios con rol `supervision` cuyo ámbito incluye el centro por la jerarquía funcional (RN-13) lo supervisan como si estuvieran en su UO.

## 10. Configuración

| Dónde | Qué | Por defecto |
|---|---|---|
| Tipo | Código, denominación, UO funcional | — |
| Tipo | Segmentos de población | — |
| Tipo | Tipos de plan; fichas de valoración | Fichas: todas |
| Tipo | Horario por defecto | — |
| Tipo | Cierres de calendario comunes | Ninguno |
| Tipo | Protocolo base | Vacío |
| Tipo | Adscripción por domicilio o libre elección | Domicilio |
| Tipo | Modo de asignación de referencia | Sorteo |
| Tipo | Ventana de reparto (meses) | 12 |
| Tipo | Meses sin apuntes para caso dormido | 6 |
| Tipo | Permite restricción en centro | No |
| Prestación | Tipos de centro que la ofrecen | — |
| Centro | Tipo de centro (al alta, inmutable) | — |
| Centro | Horario: heredado o propio | Heredado |
| Centro | Cierres propios, protocolo propio, exclusiones | Ninguno |

## 11. Impacto en otros documentos

- `modulo-centros.md` §2.1 y §4.1: nuevo `tipo_centro_id` y `centro_antecesor_id`; `inscripcion_libre`, segmentos de población y `centro_prestacion` pasan al tipo; el campo horario pasa a ser calculado. Nueva entidad `TipoCentro`.
- `modulo-asignacion.md` §8: la configuración de adscripción, modo de referencia, ventana y meses de inactividad se lee del tipo (§7 de este documento). La asignación está implementada (2026-09-30): al ejecutar esta fase se sigue la rama A de las instrucciones.
- `modulo-agenda.md` §2.1: `HorarioCentro` puede heredar el horario del tipo; nuevos cierres de calendario; la generación de cuadrantes usa el calendario efectivo.
- `modulo-prestaciones.md`: `prestacion_tipo_centro` referencia la entidad `TipoCentro`; el grupo `centro.tipo` de `catalogos_sistema` se retira.
- `modulo-intervencion.md`: al abrir un plan, solo se ofrecen los tipos de plan del tipo del centro.
- `modulo-centros.md` §2.2: la UO del centro es instrumental (RN-14). El ejemplo «un CSS de Vallecas depende administrativamente de la Junta de Distrito» se reformula: la UO del CSS cuelga orgánicamente de la junta.
- `modulo-usuarios-permisos.md`, «Ámbito por UO»: unión de la jerarquía orgánica y la funcional (RN-13); atención individual solo con adscripción a un centro (RN-15); profesional multicentro (RN-16).
- `propuesta-requisitos-y-tareas-guiadas.md` §3: decidido el selector de centro en la barra superior y el centro por defecto.

## 12. Decisiones tomadas

| Tema | Decisión |
|---|---|
| Tipos por centro | Exactamente uno, inmutable |
| Cambio de tipo de un centro | Baja y alta, enlazadas por antecesor |
| Cambios en el tipo | Renombrar y cambiar catálogo conservando identidad; o tipo nuevo. Lo decide la dirección |
| Subtipos | No |
| Dependencia | Funcional en el tipo; administrativa en el centro, sin cambios |
| Horario | Del tipo por defecto, sobrescribible entero por el centro. Atención y citas, el mismo |
| Prestaciones, planes y fichas | Del tipo. Restricción por centro posible, desactivada por defecto; sin uso en Madrid |
| Reglas de asignación | Iguales para todo el tipo |
| Equivalencia HSU | En la prestación, no en el tipo |
| Personas protegidas por defecto y derivaciones permitidas | No forman parte del tipo |
| Cobertura de áreas | Sin validaciones nuevas; responsabilidad de quien las define |
| Sede compartida | No en v1: dos centros con la misma dirección |
| Gestión | Administrador del sistema: tipos y alta y baja de centros. Director: datos de su centro |
| D1.1 — Centro y UO (11 nov 2026) | 1:1. Cada centro municipal tiene su propia UO instrumental, con el mismo nombre y sin UO hijas |
| D1.2 — Dos jerarquías | Orgánica: el árbol de UO (`parent_id`). Funcional: por tipo de centro, hacia la UO funcional del tipo |
| D1.3 — Ámbito | Unión de las dos jerarquías (RN-13) |
| D1.4 — Atención individual | Solo con adscripción a un centro (RN-15). Sin centro, se consulta y supervisa según el rol |
| D1.5 — Multicentro | Una adscripción por centro, con su perfil horario en cada uno |
| D1.6 — Centro de trabajo | El del contexto; sin contexto, el centro activo del usuario (RN-16) |
| D1.7 — Barra superior | Tipo y nombre del centro activo, con selector si tiene varios; sin centro, el nombre de su UO |
| D1.8 — Supervisión de un centro | Rol `supervision` y el centro dentro de su ámbito (RN-13); las operaciones que exigen centro, sobre uno elegido |
| D2 — Migración (11 nov 2026) | El tipo manda: la migración fija los valores del tipo, descarta los del centro y elimina sus columnas (addenda §6) |
| D4 — Personas protegidas (11 nov 2026) | Solo las gestiona el personal adscrito al centro; las UO superiores necesitan acceso concedido por el director del centro (RN-17 a RN-19) |
| D5 — Bajas de profesionales (11 nov 2026) | Sin borrado; no se sale de un centro con casos o citas pendientes; los casos son del centro (RN-20 a RN-23) |

## 13. Decisiones pendientes

- Canales de cita por tipo (presencial, teléfono, Cita Previa): se definen con la integración de Cita Previa.
- Traslado en bloque de personas y casos al crear un tipo nuevo o mover prestaciones entre tipos.
- Cargos habituales por tipo como ayuda al alta de profesionales.
- Modelos de informe por tipo de centro.
- Protocolos estructurados (hoy, texto).
