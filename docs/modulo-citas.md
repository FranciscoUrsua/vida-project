# Módulo Citas — Diseño funcional

**Módulo Laravel:** `Modules\Agenda` (subdominio *Citas*). No se crea un módulo nuevo: `Cita`, `Slot` y `EventoAgenda` ya viven en Agenda (principio 4.11).
**Estado:** diseño funcional v1 cerrado. Sin implementar.
**Última revisión:** septiembre 2026
**Dependencias:** Módulo Agenda (slots, disponibilidad, eventos), Módulo Intervención (apuntes, entrevistas, planes), Módulo Atención (`RegistroAtencion`), Módulo Mensajes (alertas y mensajes), Módulo Ciudadanía, Módulo Integraciones (cita previa del Ayuntamiento), Módulo Auditoría.

**Documentos relacionados:**
- Instrucciones de implementación: `docs/instrucciones-cli/2026-09-citas-implementacion.md`
- Tests funcionales: `docs/instrucciones-cli/2026-09-citas-tests.md` (TF-CIT-01 a TF-CIT-43)

---

## Nota de diseño: la agenda y las citas no son lo mismo

El módulo Agenda resuelve **cuándo** está disponible cada profesional: horarios, cuadrantes, excepciones y slots. Este documento resuelve **cómo se ocupa ese tiempo con ciudadanos**: quién pide una cita, quién la da, cómo se mueve o se anula, con qué intervención termina y qué rastro deja.

Tres ideas vertebran el diseño:

1. **La demanda se separa de la cita.** Una *solicitud de cita* registra que alguien necesita ser citado; la *cita* es el hueco concreto que se le asigna. Así hay bandeja de trabajo, lista de espera y medida de demora.
2. **Una cita es un compromiso con un ciudadano.** Si se espera que el ciudadano venga, coja el teléfono o esté en casa para la visita, es una cita. Todo lo demás (reuniones, formación, mesas de trabajo) es un *evento de agenda*, aunque trate sobre un ciudadano.
3. **La cita es el envoltorio temporal; la intervención es el contenido.** La cita se completa cuando el profesional registra lo que ha hecho, y en el timeline aparece dentro de esa intervención, no como un elemento aparte.

---

## 1. Reglas de negocio

**RN-01 — Canales de entrada.** Las citas llegan por dos vías:
- **Externa:** sistema de cita previa del Ayuntamiento. Previsiblemente la mayoría. La integración no existe todavía, pero el modelo la contempla desde el inicio (adaptador con mock, principio 4.6).
- **Interna:** personal con rol `consulta_basica` y supervisión. Puede ser una persona que llega al centro o una llamada telefónica.

**RN-02 — Operaciones.** La gestión de citas comprende altas, cambios de fecha u hora (reprogramaciones) y bajas (cancelaciones).

**RN-03 — Destino de la cita.** Una cita puede pedirse:
- para un **profesional concreto**;
- para el **profesional de referencia** del ciudadano;
- para el **primer profesional libre** de un servicio o perfil, cuando no hay referencia o cuando la referencia no va a estar disponible durante un periodo prolongado.

**RN-04 — El rol `intervencion` no gestiona citas.** Puede crear una **solicitud de cita**: enlaza al ciudadano, indica para qué profesional o servicio, el tipo de cita y su urgencia. La solicitud llega a la bandeja de citación del centro.

**RN-05 — Un profesional no gestiona su propia agenda.** No reasigna sus slots ni mueve o anula sus citas. Si necesita un cambio, lo pide al supervisor desde la pantalla de agenda.

**RN-05 bis — Usuarios con `intervencion` y `consulta_basica`** (combinación sugerida para auxiliares de servicios sociales). Pueden dar citas desde la bandeja, **también en su propia agenda**, pero **no pueden reprogramar ni cancelar citas propias**: para eso rige RN-05.

**RN-06 — Lo que sí hace el profesional con sus citas:**
- **Marcar incomparecencia** del ciudadano.
- **Completar la cita, implícitamente,** al registrar el apunte de lo que ha hecho: entrevista, valoración, seguimiento o acción sobre el plan. No hay botón de "completar".
- **Registrar acompañantes:** la cita se pide a nombre de una persona; al atenderla, el profesional anota si vino acompañada y por quién.

**RN-07 — Trazabilidad total.** Toda gestión (solicitud, alta, reprogramación, cancelación, reasignación, incomparecencia, cierre, petición de cambio) deja un evento inmutable en el historial de la cita (principios 4.3 y 4.4).

**RN-08 — Cita e intervención.** Las citas de un ciudadano enlazan con la intervención que ha tenido lugar: entrevista, valoración, creación, modificación o seguimiento de un plan. Para ciudadanos sin Historia Social, con el registro de atención.

**RN-09 — Timeline.** Las citas no aparecen en el timeline como elementos propios. Aparecen **dentro de la tarjeta de la intervención**, y solo al desplegarla. Las incomparecencias y cancelaciones **no aparecen** en el timeline.

**RN-10 — VIDA no gestiona el contacto con el ciudadano.** No hay límite de intentos de localización ni registro de intentos. Cuándo se da por desistida una solicitud lo decide la persona que la gestiona o el protocolo del centro, fuera de VIDA.

---

## 2. Conceptos

### 2.1 Tipo de cita

Qué se va a hacer en la cita: primera entrevista, entrevista de seguimiento, valoración, revisión del plan, visita domiciliaria, información… Es distinto del *tipo de slot*, que describe el hueco (duración, porcentaje de urgencias, canal permitido).

Cada tipo de cita tiene:
- un **nombre interno**, visible solo para roles con acceso a la Historia Social;
- una **etiqueta pública**, neutra, que ven `consulta_basica`, el canal externo y cualquier aviso al ciudadano;
- la **herramienta** de Intervención que se abre al atender la cita (entrevista inicial, entrevista de seguimiento, valoración, plan, registro de atención o ninguna);
- la **modalidad** por defecto (presencial, telefónica, videollamada, domicilio);
- los **tipos de slot** en los que puede darse;
- si **requiere Historia Social** abierta.

La separación entre nombre interno y etiqueta pública es deliberada: en un CIAM, quien da la cita no debe poder deducir de la etiqueta que se trata de violencia de género, y lo mismo vale para cualquier aviso que llegue al ciudadano.

Es un catálogo global gestionado desde Filament, como los tipos de slot. En modo de agenda `basico` basta un tipo genérico "Cita".

### 2.2 Solicitud de cita

Necesidad de citar a un ciudadano. Toda cita del canal interno nace de una solicitud, aunque se cree y se resuelva en el mismo momento en ventanilla: así la demanda y la demora se miden igual para todos los canales. Las citas del canal externo no tienen solicitud en VIDA; la solicitud vive en el sistema del Ayuntamiento.

Recoge:
- la persona a cuyo nombre se pide y el centro en cuya bandeja cae;
- quién la registra y por qué canal: presencial, teléfono, desde Intervención o generada al programar el siguiente seguimiento de un plan;
- tipo de cita y **urgencia**: ordinaria, preferente o urgente;
- **destino**: profesional concreto, profesional de referencia, un servicio o perfil, o el primer libre;
- **ventana** deseada: "no antes de" y "no después de". Si no se indica el límite, se calcula con el plazo máximo de la urgencia;
- el **motivo profesional**, visible solo para roles con acceso a la Historia Social;
- **observaciones para quien cita**, sin contenido sensible ("mejor por la tarde", "llamar al móvil");
- el **contexto** del que nace, si lo hay: un plan, un seguimiento o un registro de atención.

**Estados:**

| Estado | Significado |
|---|---|
| Pendiente | En la bandeja, sin nadie gestionándola |
| En gestión | Alguien la ha tomado; evita que dos personas llamen al mismo ciudadano |
| Citada | Tiene una cita asignada. No vuelve atrás |
| Desistida | El ciudadano no quiere cita o no se le localiza. Motivo obligatorio |
| Anulada | Quien la pidió la retira. Motivo obligatorio |

Quien tiene una solicitud en gestión puede soltarla (vuelve a pendiente) con una nota.

### 2.3 Cita

Reserva de un slot para un ciudadano. Además de lo que ya define `modulo-agenda.md` (slot, profesional, fecha, estado, origen, referencia externa), la cita guarda:

- la **solicitud** de la que nace (salvo citas externas);
- el **tipo de cita** y la **modalidad**;
- el **modo de asignación**: profesional concreto, referencia, sustituto (la referencia no estaba disponible) o primer libre;
- la **cita anterior**, si es una reprogramación;
- los **acompañantes**;
- si está **pendiente de cierre** (su hora pasó sin apunte ni incomparecencia);
- en citas externas que no se han podido casar con un ciudadano de VIDA, los **datos de identificación recibidos**, hasta que se identifique a la persona.

**Estados:**

| Estado | Significado |
|---|---|
| Confirmada | Cita activa |
| Completada | Tiene un apunte o registro de atención vinculado |
| Incomparecencia | El ciudadano no acudió (`no_show_ciudadano`) |
| Cancelada | Anulada, con motivo y con quién la pidió |
| Reprogramada | Sustituida por otra cita que la referencia como anterior |
| Reasignada / `no_show_profesional` | Sin cambios respecto a Agenda |

**Reprogramar crea una cita nueva**, no modifica la existente. Así se conserva la fecha original y se puede saber cuántas veces se movió una cita y a petición de quién (principio 4.3). La reasignación por ausencia del profesional mantiene su comportamiento actual (misma cita, otro profesional), y ahora también deja evento.

**Al cancelar**, quien cancela decide en ese momento si se abre una solicitud nueva enlazada a la cancelada. No hay regla automática.

### 2.4 Historial de la cita

Cada acción sobre una solicitud o una cita genera un evento con quién la hizo (usuario, canal externo o sistema), por qué canal, a petición de quién (ciudadano, centro, profesional, sistema), el estado antes y después, y el motivo.

Acciones registradas: solicitud creada, tomada, soltada, desistida o anulada; cita creada, reprogramada, cancelada o reasignada; incomparecencia; cita completada; acompañantes registrados; cambio solicitado por el profesional; marcada pendiente de cierre; notificación enviada al canal externo; ciudadano identificado.

El historial es **inmutable** y forma parte del expediente de atención de la persona. No es auditoría técnica y **no se purga** con la retención de la tabla de auditoría.

### 2.5 Acompañantes

La cita se pide a nombre de una sola persona. El profesional que atiende registra quién vino con ella: la **relación** (representante legal, tutor/a, familiar, miembro de la unidad de convivencia, persona de apoyo, intérprete, otra; catálogo del sistema) y la persona, enlazada si está en VIDA o con su nombre si no lo está. Es solo registro: no crea citas ni vínculos para el acompañante.

### 2.6 Eventos de agenda que tratan sobre ciudadanos

Los eventos de agenda (reuniones, formación, mesas de trabajo) siguen siendo eventos. Lo único que cambia es que pueden **referenciar ciudadanos**, para mesas de caso o coordinaciones sobre una persona sin su presencia.

- Referenciar un ciudadano no convierte el evento en cita: no tiene solicitud, ni incomparecencia, ni canal externo.
- Los ciudadanos referenciados solo los ve quien tiene acceso a su Historia Social. El resto ve el título y el tipo del evento.
- El apunte de coordinación que se registre a raíz del evento puede enlazarlo, y así aparece en el timeline con el mismo tratamiento que una cita (§5).

| | Cita | Evento |
|---|---|---|
| Quién la gestiona | `consulta_basica` o supervisión | Supervisor o convocante |
| Datos personales | Sí, con auditoría de acceso | Solo si referencia ciudadanos |
| Ciclo de vida | Solicitud, incomparecencia, reprogramación, canal externo | Convocatoria, asistentes, espacio |
| Timeline | Dentro de la intervención | Solo a través del apunte de coordinación |

---

## 3. Flujos

### 3.1 Solicitud desde Intervención

1. El profesional, desde la ficha del ciudadano o al programar el siguiente seguimiento de un plan, crea una solicitud: tipo, urgencia, destino, ventana, motivo y observaciones.
2. La solicitud cae en la bandeja de citación del centro y se avisa a las personas con `consulta_basica` del centro. No se envía a una persona concreta, para que no se pierda por ausencias.
3. Alguien de la bandeja la toma, busca hueco, contacta con el ciudadano y da la cita.
4. Si pasa la fecha límite sin cita, se avisa al supervisor.

### 3.2 Cita en ventanilla o por teléfono

Quien atiende busca al ciudadano, elige tipo, urgencia y destino, y el sistema le propone huecos. Al confirmar, se crean a la vez la solicitud y la cita. Es el mismo flujo que "generar cita" desde un registro de atención.

### 3.3 Búsqueda de huecos

El sistema **propone**; la persona que cita **elige**.

1. Solo slots de tipos compatibles con el tipo de cita, en el centro y dentro de la ventana.
2. Según la urgencia: ordinaria y preferente, solo slots disponibles; urgente, también los reservados para urgencias (con aviso al supervisor al consumirlos, como ya define Agenda).
3. Según el destino:
   - **Profesional concreto:** solo ese profesional.
   - **Referencia:** primero el profesional de referencia. Si no tiene hueco en la ventana, o tiene una ausencia registrada que cubre más días de la ventana que el umbral de *ausencia prolongada* del centro, se proponen otros profesionales del mismo perfil como **sustitutos**. La referencia del ciudadano no cambia.
   - **Servicio o primer libre:** todos los profesionales del perfil, por orden cronológico.
4. Orden: fecha y hora. En destino "referencia", los huecos de la referencia van primero aunque sean más tardíos, siempre que estén en la ventana.

Si no hay huecos, se dice y la solicitud sigue en la bandeja.

### 3.4 Cambios y bajas

- **Reprogramar:** quien gestiona citas elige el nuevo hueco. La cita original queda como reprogramada y libera su slot; la nueva la referencia.
- **Cancelar:** motivo obligatorio y a petición de quién. Quien cancela decide si abre una solicitud nueva.
- **El profesional pide un cambio:** desde su agenda, sobre una cita o un slot. Se abre un mensaje al supervisor del centro con el enlace y queda registrado en el historial de la cita.

### 3.5 Atención y cierre

1. El profesional abre la cita desde su agenda. Se abre la ficha del ciudadano con la herramienta del tipo de cita ya abierta, la cita enlazada y, si la solicitud tenía contexto (un plan, un seguimiento), ese contexto seleccionado.
2. Al guardar el apunte, la cita pasa a completada. Si el ciudadano no tiene Historia Social, el cierre lo hace el registro de atención.
3. Si el profesional registra un apunte desde la ficha del ciudadano sin pasar por la agenda, y ese ciudadano tiene una cita con él ese día o pendiente de cierre, la herramienta propone vincularla, marcado por defecto.
4. Una cita puede tener varios apuntes (entrevista y después valoración); se completa con el primero.
5. No se puede vincular un apunte a una cita con incomparecencia o cancelada, salvo supervisión con motivo (para corregir un error de marcado).

### 3.6 Citas sin cerrar

Al final de cada día laboral, las citas confirmadas cuya hora ha pasado sin apunte ni incomparecencia se marcan como **pendientes de cierre** y se avisa al profesional. Si siguen así pasados los días que fije el centro, se avisa al supervisor. **El sistema nunca cierra una cita por su cuenta.** Mientras una cita esté pendiente de cierre, su slot no se da por no ocupado.

### 3.7 Incomparecencias

El profesional las marca desde su agenda. No aparecen en el timeline ni generan avisos: el profesional las ve en la agenda del ciudadano y en el historial, y decide si tiene que hacer algo.

---

## 4. Canal externo: cita previa del Ayuntamiento

Se mantiene lo definido en `modulo-agenda.md` §4.1 y §4.2 (entrada por API, adaptador, mock, slots de urgencia nunca expuestos, notificación de cambios al sistema externo). Se añade:

- **Idempotencia:** una misma cita recibida dos veces no se crea dos veces.
- **Identificación de la persona:** se intenta casar por sus documentos de identidad. Si no hay coincidencia única, la cita se crea **pendiente de identificar**, con los datos recibidos guardados cifrados. Se resuelve en ventanilla con el flujo normal de búsqueda y alta con control de duplicados. **No se crean ciudadanos provisionales.**
- **Tipo de cita, datos que envía el sistema externo y reconciliación entre sistemas:** pendientes de la integración con Cita Previa.

---

## 5. Timeline

- **Resumen de la tarjeta:** sin cambios.
- **Tarjeta desplegada:** si el apunte está vinculado a una cita, se añade una sección *Cita* con:
  - canal de origen (cita previa, ventanilla, teléfono, solicitud interna) y, si hubo solicitud, quién la pidió y cuándo;
  - fecha prevista y fecha real, y demora desde la solicitud;
  - modo de asignación (con el profesional de referencia si fue sustituto);
  - número de reprogramaciones, con acceso al historial de la cita;
  - acompañantes.
- Si el apunte está vinculado a un evento de agenda, la sección se llama *Coordinación* y muestra el evento y los profesionales convocados.
- Incomparecencias y cancelaciones no aparecen.

---

## 6. Bandeja de citación

Pantalla de operación (Livewire) para `consulta_basica` y supervisión del centro.

- Solicitudes pendientes y en gestión del centro, ordenadas por urgencia y fecha límite, con el tiempo que llevan en bandeja.
- Del ciudadano muestra los datos de identificación y contacto. De la solicitud, la **etiqueta pública** del tipo, la urgencia, el destino y las observaciones para quien cita. **Nunca** el motivo profesional ni el nombre interno del tipo.
- Citas externas pendientes de identificar.
- Desde aquí se toman, se sueltan, se citan, se dan por desistidas o se anulan las solicitudes.

---

## 7. Permisos

| Acción | `consulta_basica` | `intervencion` | `supervision` |
|---|---|---|---|
| Ver bandeja de citación | ✅ | — | ✅ |
| Crear solicitud de cita | ✅ | ✅ | ✅ |
| Dar citas | ✅ (también en agenda propia, RN-05 bis) | — | ✅ |
| Reprogramar y cancelar citas | ✅ (no las propias) | — | ✅ |
| Reasignar por ausencia del profesional | — | — | ✅ |
| Cancelación retroactiva | — | — | ✅ |
| Marcar incomparecencia en sus citas | — | ✅ | ✅ |
| Registrar acompañantes en sus citas | — | ✅ | ✅ |
| Pedir cambio al supervisor | — | ✅ | — |
| Ver motivo de la solicitud y nombre interno del tipo | — | ✅ | ✅ |

"Propias" significa citas en la agenda del propio usuario.

---

## 8. Configuración por centro

Ningún valor en código (principio 3.1). Se configuran por centro, junto al horario:

- **Umbral de ausencia prolongada** (días): a partir de cuánto se proponen sustitutos de la referencia.
- **Plazo máximo por urgencia** (días laborables): para calcular la fecha límite de una solicitud.
- **Días para avisar al supervisor** de citas pendientes de cierre.

---

## 9. Impacto en otros documentos

**`modulo-agenda.md`:**
- §1 y §2.10: los eventos pasan de "sin ciudadano asociado" a "sin compromiso con el ciudadano; pueden referenciar ciudadanos".
- §2.2: "genera apunte automático" pierde su función, porque ahora es el apunte el que completa la cita. Se retira.
- §2.8: nuevos datos de la cita y estado reprogramada.
- §4.4: la integración con Intervención se invierte.
- PF-05.1, PF-05.5 y PF-06.2 cambian (ver documento de tests).
- Referencias a principios desactualizadas: el adaptador es el 4.6 y Filament/Livewire el 4.12.

**`modulo-intervencion.md`:** el apunte puede enlazar una cita o un evento de agenda.

**`modulo-atencion.md`:** el registro de atención puede completar una cita, además de generarla.

**`modulo-mensajes.md`:** la cita y el slot pasan a ser elementos enlazables desde un mensaje.

**`BACKLOG.md`:** la creación de citas simplificada resuelve "Generar cita desde RegistroAtencion" y desbloquea "Citas en escenarios de demo".

---

## 10. Decisiones tomadas

| Tema | Decisión |
|---|---|
| Citas y eventos | Entidades distintas. Criterio: la cita es un compromiso con un ciudadano. Los eventos pueden referenciar ciudadanos |
| Varias personas en una cita | La cita es de una persona; los acompañantes se registran al atender |
| Cierre de la cita | Implícito al registrar el apunte. El profesional sí marca incomparecencias |
| Reprogramación | Crea una cita nueva enlazada a la anterior |
| Timeline | Detalle de la cita solo al desplegar la tarjeta. Sin incomparecencias |
| Auxiliares con `intervencion` y `consulta_basica` | Dan citas también en su agenda; no reprograman ni cancelan las suyas |
| Solicitud nueva al cancelar | A criterio de quien cancela |
| Intentos de contacto | Sin límite ni registro en VIDA; criterio de la persona o protocolo del centro |
| Incomparecencias reiteradas | Sin avisos; las revisa el profesional |
| Citas externas sin identificar | Cita pendiente de identificar; sin ciudadanos provisionales |

## 11. Decisiones pendientes

- **Notificación al ciudadano** (recordatorios, cambios): diferida al módulo de comunicaciones ciudadanas. Cuando exista, usará solo la etiqueta pública del tipo de cita.
- **Contrato con Cita Previa:** mapeo de servicios a tipos de cita, datos de identificación que envía y reconciliación. Pendiente de la integración.
- **Indicadores** (demora, incomparecencias, atención por sustituto, reprogramaciones): se decidirán con el módulo de analítica (principio 3.14). El historial de la cita ya guarda lo necesario para calcularlos.
