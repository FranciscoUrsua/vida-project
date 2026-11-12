# Solicitudes de prestación y expedientes — Diseño funcional

**Módulos afectados:** `Prestaciones` (vía de acceso y configuración por prestación), `Centro` (sustituye a `SolicitudServicio`), `Intervencion` (solicitud desde la ficha y el plan, prestaciones de la persona), `Integraciones` (gestor de expedientes), `Documentos` (vínculo documento–solicitud), `Mensajes` (avisos), `Usuarios` (rol `tramitacion`).
**Estado:** diseño funcional v1 cerrado. Sin implementar.
**Fecha:** 11 noviembre 2026
**Origen:** decisión D3 de `docs/plan-cierre-funcional-2026-11.md`. Resuelve la decisión pendiente P-01 («Diseño de la entidad Solicitud de prestación») y parte de lo pendiente del rol `tramitacion` (`docs/roles-consulta-basica-tramitacion.md` §4.2).

---

## Nota de diseño

La tramitación de expedientes administrativos es un proceso complejo y muy regulado que corresponde a una aplicación especializada, el **gestor de expedientes**. Pero el expediente nace de la actividad del trabajador social, está asociado a una prestación y, a menudo, a un plan de intervención. Que la tramitación salga de VIDA no significa que VIDA deba ignorarla: el profesional necesita saber que el expediente existe, en qué estado está y qué se ha resuelto, para actuar sobre el caso.

Cuatro ideas vertebran el diseño:

- **Una sola puerta.** El profesional ve siempre la misma acción, «Solicitar prestación». Cada prestación del catálogo define su **vía de acceso** y la solicitud sigue el camino que le corresponde.
- **VIDA prepara, el gestor tramita.** En VIDA se define la solicitud, se reúne la documentación y se decide cuándo está lista. Desde el envío, la tramitación y las comunicaciones con el ciudadano son del gestor de expedientes. VIDA refleja lo que ocurre, no lo gestiona.
- **Agnóstico respecto al gestor.** Hoy no hay gestor de expedientes en el Ayuntamiento y puede que no lo haya en años. VIDA funciona con un adaptador intercambiable y, mientras tanto, con **registro manual**: tramitación anota en VIDA lo que consulta en otra aplicación.
- **Conceder no es empezar a recibir.** Entre la concesión de una prestación y el momento en que la persona empieza a percibirla pueden pasar semanas (ayuda a domicilio) o depender de una plaza libre. VIDA registra las dos fechas.

---

## 1. Reglas de negocio

**RN-01 — Vía de acceso por prestación.** Cada prestación del catálogo tiene una vía de acceso:

| Vía | Qué pasa al solicitarla | Ejemplos |
|---|---|---|
| **Directa** | El profesional la registra como prestada; no hay solicitud ni tramitación | Información, orientación, acompañamiento técnico |
| **Plaza** | Prescripción de una plaza o actividad, con lista de espera (flujo existente de `modulo-centros.md` §2.8) | Centro de día, residencia, actividad grupal |
| **Tramitación interna** | Solicitud a una unidad municipal que la resuelve sin gestor externo | Prestaciones gestionadas por un servicio del Área |
| **Expediente externo** | Solicitud que tramitación prepara y envía al gestor de expedientes | Ayudas económicas, ayuda a domicilio, teleasistencia… según el catálogo |

Las vías de tramitación interna y de expediente externo comparten la entidad **Solicitud de prestación** (§2.2) y se diferencian solo en el envío. La vía plaza conserva su flujo propio.

**RN-02 — Unidad tramitadora por prestación.** Cada prestación con vía de tramitación indica su unidad tramitadora: una UO concreta (p. ej. un servicio central) o «el centro donde se solicita». La solicitud cae en la **bandeja de esa unidad**, no en una persona.

**RN-03 — Bandeja, no persona.** Cualquier usuario con rol `tramitacion` de la unidad tramitadora ve la bandeja y puede tomar una solicitud. Quien la toma queda como responsable hasta que la suelta o la envía. El trabajador social puede sugerir una persona, pero la solicitud no deja de estar en la bandeja.

**RN-04 — Parámetros configurables.** Cada prestación con vía de tramitación tiene un **formulario de solicitud** definido en el panel de administración, con el mismo mecanismo que las fichas de valoración (campos, versiones y copia del esquema en cada solicitud), y una **lista de documentos exigidos** (tipo documental, obligatorio u opcional).

**RN-05 — La preparación la cierra tramitación.** El trabajador social crea la solicitud y la pasa a la bandeja. Tramitación revisa, completa la documentación y decide cuándo enviarla. Con documentos obligatorios sin aportar, el envío pide justificación.

**RN-06 — VIDA no se comunica con el ciudadano por la tramitación.** Requerimientos, subsanaciones y notificaciones al ciudadano son del gestor de expedientes (o de quien tramite). VIDA **registra que han ocurrido** y avisa al trabajador social, por si decide contactar con la persona para ayudarla.

**RN-07 — Cuatro formas de actualizar el estado.** El estado de un expediente enviado se actualiza por:
1. **Aviso del gestor** (entrada por API), si el gestor lo ofrece.
2. **Consulta periódica automática** de los expedientes abiertos.
3. **«Consultar ahora»**, a petición del trabajador social o de tramitación.
4. **Registro manual** por tramitación: estado, fecha, descripción y, si procede, resolución.

Cada actualización guarda su origen (aviso, consulta automática, consulta manual o registro manual) y quién la hizo.

**RN-08 — Concesión e inicio, por separado.** Una resolución favorable crea una **prestación de la persona** en estado «concedida» con su fecha de concesión. Pasa a «activa» cuando se registra la **fecha de inicio efectivo** (cuando la persona empieza a percibirla). Las dos fechas quedan registradas y se pueden consultar y analizar.

**RN-09 — La resolución se incorpora sola.** Al resolverse un expediente, VIDA crea las prestaciones concedidas de la persona, enlazadas al plan si la solicitud venía de él, deja un apunte automático en la historia (`fuente: gestor_expedientes` o `registro_manual`) y avisa al trabajador social. No hace falta que el profesional valide para que conste.

**RN-10 — Lo que ve tramitación.** Tramitación ve los datos de identificación y contacto de la persona, los parámetros de la solicitud, sus documentos y su historial. **No** ve la Historia Social, los apuntes ni el motivo profesional. Los documentos adjuntos pueden contener información sensible: se asume porque son necesarios para la tramitación. Todo acceso queda auditado.

**RN-11 — Trazabilidad.** Toda acción sobre una solicitud (creación, paso a bandeja, toma, suelta, documentos, envío, actualizaciones de estado, resolución, anulación) deja un evento inmutable en su historial, que no se purga con la retención de la auditoría (principios 4.3 y 4.4).

---

## 2. Conceptos

### 2.1 Configuración de la prestación (catálogo)

Se añade a cada prestación:

| Atributo | Descripción |
|---|---|
| Vía de acceso | Directa, plaza, tramitación interna, expediente externo (RN-01) |
| Unidad tramitadora | UO, o «centro de solicitud» (RN-02). Solo vías de tramitación |
| Formulario de solicitud | Esquema versionado (RN-04). Opcional |
| Documentos exigidos | Tipos documentales, obligatorios u opcionales |
| Código en el gestor | Identificador de la prestación o procedimiento en el gestor externo. Solo expediente externo |

Como el resto de la prestación, se versiona: cada solicitud conserva la configuración vigente cuando se creó.

### 2.2 Solicitud de prestación

Recoge:
- la **persona** titular y, si procede, otras personas de la unidad de convivencia afectadas;
- la **prestación** solicitada y la **vía**;
- el **contexto**: plan y objetivo si los hay, centro de solicitud, trabajador social que la crea;
- los **parámetros** (datos del formulario con la copia de su esquema);
- los **documentos** vinculados (n:M con la carpeta de la persona; los que se suben para la solicitud quedan también en la carpeta);
- la **unidad tramitadora**, la persona sugerida y el **responsable** actual de tramitación;
- en expediente externo, el **número de expediente** y el **estado externo** (§2.4).

**Estados:**

| Estado | Significado |
|---|---|
| Borrador | El trabajador social la está preparando |
| En bandeja | Pendiente de que tramitación la tome |
| En preparación | Tramitación la ha tomado y completa la documentación |
| Enviada | Enviada al gestor (o, en tramitación interna, aceptada por la unidad); pendiente de resolución |
| Resuelta | Tiene resolución incorporada (§2.5) |
| Cerrada sin resolución | Desistida o archivada en el gestor. Motivo obligatorio |
| Anulada | Retirada antes del envío por quien la creó o por tramitación. Motivo obligatorio |

Tramitación puede devolver una solicitud al trabajador social (de «en preparación» a «borrador») con una nota, por ejemplo si los parámetros son incoherentes.

### 2.3 Incidencias del expediente

Requerimientos de subsanación, peticiones de documentación, trámites de audiencia y similares. Llegan por cualquiera de las formas de RN-07 y se registran como eventos del historial con su tipo, fecha, descripción y plazo si lo hay. Generan un aviso al trabajador social y a tramitación. Tramitación puede vincular a la solicitud los documentos que se aporten y marcar la incidencia como atendida. VIDA no envía nada al ciudadano (RN-06).

### 2.4 Estado externo

El estado que devuelve el gestor se guarda tal cual (código y texto) y se traduce a un **estado normalizado** de un catálogo del sistema (en trámite, incidencia, resuelta, desistida, archivada…), configurable por adaptador. La interfaz y los avisos usan el normalizado; el detalle del gestor se ve en el historial.

### 2.5 Resolución

Una resolución tiene fecha, sentido global (favorable, parcialmente favorable, desfavorable) y **líneas**: una por prestación resuelta, que puede no coincidir con la solicitada (p. ej. se concede otra de menor intensidad). Cada línea indica concedida o denegada, motivo si se deniega y, si se concede, las condiciones (importe o intensidad, periodo, observaciones).

### 2.6 Prestación de la persona

Lo que la persona tiene o ha tenido. Es la fuente del resumen de prestaciones de la ficha (hoy vacío, `statPrestaciones()`) y del seguimiento del plan.

| Estado | Significado |
|---|---|
| Concedida | Resolución favorable; aún no la percibe |
| Activa | La percibe desde la fecha de inicio efectivo |
| Suspendida | Interrumpida temporalmente, con motivo |
| Finalizada | Terminada, con fecha y motivo |

Se crea por la vía que corresponda: al registrar una prestación directa (activa desde ese día), al resolverse una solicitud (concedida) o al asignarse una plaza (activa desde el inicio en la plaza). El paso a activa lo registran el trabajador social o tramitación; en el futuro, una integración con el proveedor.

---

## 3. Flujos

### 3.1 Solicitar una prestación

1. Desde la ficha de la persona o desde un objetivo del plan, el trabajador social elige «Solicitar prestación» y la prestación.
2. Según la vía:
   - **Directa:** registra la prestación prestada. Fin.
   - **Plaza:** se abre la prescripción existente.
   - **Tramitación:** se crea la solicitud en borrador con el formulario y la lista de documentos de la prestación, marcando los que ya están en la carpeta.
3. El trabajador social completa lo que sabe, vincula documentos, puede sugerir una persona de tramitación y la pasa a la bandeja.

### 3.2 Preparar y enviar

1. Alguien de la unidad tramitadora toma la solicitud.
2. Revisa parámetros y documentos, sube lo que falte, o la devuelve al trabajador social con una nota.
3. La envía:
   - **Expediente externo con adaptador:** VIDA llama a `iniciarSolicitud()`, guarda el número de expediente y pasa a «enviada».
   - **Expediente externo sin adaptador (registro manual):** tramitación anota el número de expediente que le ha dado la otra aplicación y la fecha de presentación.
   - **Tramitación interna:** la unidad la acepta y la resuelve en VIDA.

### 3.3 Seguimiento

- Las actualizaciones llegan por las cuatro formas de RN-07. Cada cambio de estado normalizado o incidencia genera un aviso al trabajador social; las incidencias, también a tramitación.
- En la ficha de la persona y en la vista del plan, el trabajador social ve sus solicitudes con estado, fecha de la última actualización y acceso al historial, y puede pulsar «Consultar ahora».
- Tramitación tiene su bandeja con las solicitudes de su unidad: pendientes de tomar, en preparación y enviadas con incidencias abiertas.

### 3.4 Resolución e inicio

1. La resolución llega del gestor o se registra a mano.
2. VIDA crea las prestaciones concedidas de la persona (RN-08 y RN-09), deja el apunte automático y avisa al trabajador social.
3. Cuando la persona empieza a percibirla, el trabajador social o tramitación registra la fecha de inicio efectivo y la prestación pasa a activa.

---

## 4. Integración con el gestor de expedientes

Se amplía la interfaz de `modulo-integraciones.md` §4.3:

| Operación | Uso |
|---|---|
| `iniciarSolicitud(SolicitudParaGestor): string` | Envía la solicitud con sus parámetros y documentos; devuelve el número de expediente |
| `consultarEstado(string $expediente): EstadoExpediente` | Estado, incidencias nuevas y resolución si la hay |
| `aportarDocumentacion(string $expediente, array $documentos): void` | Envía documentos para atender una incidencia |
| Aviso entrante (`expediente:notificar`) | El gestor notifica cambios de estado, incidencias y resoluciones |

Adaptadores:
- **Simulador (mock):** para desarrollo y pruebas; simula estados, incidencias y resoluciones.
- **Manual:** activo por defecto mientras no haya gestor. No llama a nada: las operaciones se sustituyen por formularios de registro manual (número de expediente, estado, incidencia, resolución).
- **Real:** cuando exista. El mapeo de estados a los normalizados y de prestaciones a códigos del gestor es configuración, no código.

La consulta periódica automática solo se ejecuta con un adaptador que la permita. La frecuencia es configurable.

---

## 5. Permisos

| Acción | `intervencion` (TS) | `tramitacion` | `supervision` | `consulta_basica` |
|---|---|---|---|---|
| Crear solicitud y pasarla a bandeja | ✅ | — | ✅ | — |
| Ver solicitudes de sus personas e historial | ✅ | — | ✅ | — |
| Ver bandeja de su unidad y tomar o soltar solicitudes | — | ✅ | ✅ | — |
| Completar documentación, devolver, enviar | — | ✅ (responsable) | ✅ | — |
| Registrar estado, incidencia o resolución a mano | — | ✅ | ✅ | — |
| «Consultar ahora» | ✅ | ✅ | ✅ | — |
| Registrar prestación directa | ✅ | — | ✅ | — |
| Registrar inicio, suspensión o fin de una prestación | ✅ | ✅ | ✅ | — |
| Anular antes del envío | ✅ (las suyas) | ✅ | ✅ | — |

Tramitación accede a la persona solo a través de solicitudes de su unidad (RN-10), dentro del ámbito de UO (orgánico y funcional). Todo acceso a una solicitud de una persona protegida queda auditado como acceso a sus datos.

---

## 6. Impacto en otros documentos

- **`modulo-prestaciones.md`:** atributos de §2.1 (vía de acceso, unidad tramitadora, formulario, documentos exigidos, código en el gestor). El campo actual que indica si la prestación implica tramitación económica externa se sustituye por la vía de acceso.
- **`modulo-centros.md` §3.3 y §5.4:** `SolicitudServicio` queda sustituida por la Solicitud de prestación con vía de tramitación interna y unidad tramitadora = servicio. Migrar los registros que haya (solo de prueba).
- **`modulo-integraciones.md` §4.3 y §6:** operaciones y adaptadores de §4; adaptador manual por defecto; estados normalizados.
- **`modulo-intervencion.md`:** acción «Solicitar prestación» desde la ficha y desde el objetivo del plan; prestaciones de la persona; apunte automático de resolución; `nuevas_prestaciones` del seguimiento pasa a referenciar solicitudes o prestaciones de la persona.
- **`modulo-documentos.md` §2.1:** vínculo documento–solicitud (ya anotado como pendiente).
- **`modulo-ciudadania.md`:** el resumen de prestaciones de la ficha se alimenta de las prestaciones de la persona; bloque de solicitudes en curso.
- **`modulo-mensajes.md`:** avisos de cambio de estado, incidencia y resolución.
- **`roles-consulta-basica-tramitacion.md` §4:** `tramitacion` deja de estar diferido en lo que toca a este módulo. Quedan resueltas las cuestiones 1 (nota de tramitación: eventos de la solicitud, no apuntes), 2 (entrada de peticiones: bandeja de la unidad tramitadora) y 3 (documentación requerida por prestación). La 4 queda resuelta para las solicitudes (RN-10). Sigue pendiente la 5 (traslados y fusiones).
- **`decisiones-pendientes.md`:** P-01 resuelta; P-03 (`statPrestaciones`) resuelta en diseño.

---

## 7. Decisiones tomadas

| Tema | Decisión |
|---|---|
| Puertas de entrada | Una sola acción; cada prestación define su vía de acceso |
| Asignación de la tramitación | Bandeja de la unidad tramitadora; sugerencia opcional de persona |
| Unidad tramitadora | La indica cada prestación; puede ser el centro de solicitud |
| Parámetros | Formulario configurable por prestación con el mecanismo de las fichas; documentos exigidos por prestación |
| Comunicación con el ciudadano | Del gestor de expedientes. VIDA registra y avisa al trabajador social |
| Actualización del estado | Aviso, consulta periódica, consulta manual y registro manual. VIDA es agnóstico respecto al gestor |
| Gestor de expedientes | No existe hoy: adaptador manual por defecto y simulador para pruebas |
| Concesión e inicio | Dos fechas y dos estados (concedida, activa) |
| Incorporación de la resolución | Automática, con apunte y aviso; sin validación previa |
| Visibilidad de tramitación | Identificación, parámetros, documentos e historial. Ni Historia Social ni apuntes. La posible sensibilidad de los documentos se asume |

## 8. Decisiones pendientes

- **Listas de espera de prestaciones con plazas limitadas**: el modelo de lista de espera de centros existe, pero su gestión completa está pendiente. Afecta al paso de concedida a activa en prestaciones con plaza.
- **Aviso por demora de inicio**: si una prestación concedida no empieza en un plazo configurable por prestación, ¿se avisa al trabajador social?
- **Integración con proveedores** (p. ej. empresa de ayuda a domicilio) para registrar el inicio efectivo automáticamente.
- **Cofinanciación y HSU**: qué solicitudes y resoluciones se comparten con la Comunidad de Madrid.
- **Indicadores** (tiempo hasta envío, hasta resolución y hasta inicio; tasa de concesión): con el módulo de analítica. El historial guarda lo necesario.
