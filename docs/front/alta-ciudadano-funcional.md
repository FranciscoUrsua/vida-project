# Alta de ciudadano — Especificación funcional

**Proyecto:** VIDA 360  
**Módulo:** Ciudadanía  
**Versión:** 1.0  
**Fecha:** junio 2026  
**Estado:** Aprobado para implementación

---

## 1. Propósito

Este documento especifica el flujo y comportamiento del formulario de alta de ciudadano en VIDA 360. El alta es el acto por el que una persona queda identificada unívocamente en el sistema, con un identificador interno que no cambia y que acumula toda su actividad con los servicios sociales municipales, desde las interacciones más ligeras (inscripción en actividades de centro) hasta las más complejas (historia social, plan de intervención, prestaciones económicas).

El alta no abre historia social, no genera valoraciones ni implica ningún derecho. Solo establece que esta persona existe en el sistema y quién es.

---

## 2. Alcance y roles

Pueden ejecutar el alta los usuarios con cualquiera de los siguientes roles:

- `intervencion` — trabajadores sociales y otros profesionales de intervención directa
- `supervision` — directivos y responsables de gestión
- `tramitacion` — administrativos y auxiliares administrativos
- `consulta_basica` — ordenanzas, personal de información, personal auxiliar

Los roles `intervencion` y `supervision` tienen capacidades adicionales en el paso de verificación de padrón (ver sección 4.3).

---

## 3. Principios de diseño

**Identidad única.** El identificador interno del ciudadano nunca cambia. Si alguien registrado como beneficiario de actividades en un centro de mayores desarrolla posteriormente necesidades de intervención social, se trabaja sobre el mismo registro: la historia acumulada es visible para el profesional que abre la historia social.

**Flujo único para todos los contextos.** El formulario es el mismo independientemente del tipo de servicio (ASP, centros de mayores, PSH, VVG, representantes). Lo que varía es lo que sucede después del alta. El contexto queda determinado por el centro o servicio desde el que opera el profesional, no por una elección en el formulario.

**Búsqueda obligatoria antes del alta.** No se puede acceder al formulario de alta sin haber realizado primero una búsqueda en el sistema. Esto protege la unicidad del registro y evita duplicados.

**Padrón como fuente de verdad, con excepciones estructurales.** Para la mayoría de ciudadanos, el empadronamiento es requisito para ser beneficiario de servicios sociales municipales. Hay tres excepciones estructurales (PSH, VVG, representantes) y una excepción puntual gestionada caso a caso.

---

## 4. Flujo de alta

El flujo se organiza en cuatro fases secuenciales. Solo se avanza cuando la fase activa está completada. No hay navegación libre entre fases.

### 4.1 Fase 1 — Búsqueda previa

Antes de registrar a nadie, el profesional busca si la persona ya existe en el sistema.

**Búsqueda por documento de identidad (preferente).** El profesional selecciona el tipo de documento (DNI, NIE, pasaporte) e introduce el valor. El sistema normaliza el documento a formato canónico y busca por hash determinista en `ciudadano_identificadores`. Este es el método más fiable y el que debe intentarse primero.

**Búsqueda por datos personales (alternativa).** Si no hay documento disponible, el profesional introduce apellido 1, apellido 2 (opcional) y fecha de nacimiento. El sistema aplica búsqueda aproximada con Jaro-Winkler sobre los registros existentes. Este método puede producir falsos positivos y requiere que el profesional revise los resultados con más cuidado.

**Resultados.** El sistema muestra los candidatos encontrados ordenados por similitud, indicando para cada uno qué campos coinciden (no solo el score numérico). El profesional puede ver la ficha de cada candidato antes de decidir.

Hay tres posibles desenlaces:

- **Coincidencia casi segura** (documento idéntico u otro indicador de alta confianza): el sistema bloquea el avance y fuerza la revisión. El profesional debe confirmar que es la misma persona o justificar por qué no lo es.
- **Coincidencias probables** (similitud media-alta): el sistema muestra los candidatos y permite al profesional continuar con el alta después de revisarlos. La decisión queda registrada en auditoría.
- **Sin coincidencias**: el sistema permite avanzar directamente.

El botón "dar de alta nueva persona" solo aparece después de realizar la búsqueda, y su posición en la interfaz lo convierte en la última opción, no en el camino de menor resistencia.

### 4.2 Fase 2 — Verificación en padrón

Una vez confirmado que la persona no existe en el sistema, el siguiente paso es verificar su empadronamiento.

**Flujo normal.** Si se dispone de documento de identidad, el sistema consulta automáticamente el API del padrón municipal. Si la persona está empadronada, se precargan los datos devueltos (nombre, apellidos, fecha de nacimiento, domicilio) marcados visualmente como "procedentes del padrón" para que el profesional los verifique antes de guardar.

**Sin resultado en el padrón.** Si la consulta no devuelve resultado, el profesional debe seleccionar obligatoriamente una razón para poder continuar. La razón determina el contexto del alta y tiene implicaciones en los pasos siguientes.

Las opciones disponibles varían según el rol:

| Excepción | Roles que la ven | Implicación |
|---|---|---|
| Persona sin hogar (PSH) | `intervencion`, `supervision` | Nombre y apellidos pasan a ser opcionales; alias requerido; domicilio sustituido por coordenadas de pernocta |
| Víctima de violencia de género (VVG) | `intervencion`, `supervision` | El padrón no se ha consultado ni se consultará; domicilio protegido puede diferir del padrón |
| Representante del ciudadano | Todos los roles | Residente en otro municipio; alta solo para contacto y seguimiento, sin relación de beneficiario |
| Otra excepción | Todos los roles | Requiere justificación libre; queda registrada en auditoría; puede requerir autorización del supervisor |

Para roles sin `intervencion` ni `supervision`, las opciones PSH y VVG no se muestran, con una nota explicativa de que esas situaciones requieren intervención profesional. El servidor también rechaza la selección de esas opciones por cualquier rol no autorizado, independientemente de lo que muestre la interfaz.

**Caso especial VVG.** La consulta al padrón **no se realiza en ningún momento** para ciudadanas en circuito VVG, ni siquiera para ignorar la respuesta. No debe quedar ninguna traza en los logs del padrón. Esta restricción se evalúa antes de lanzar cualquier llamada HTTP.

### 4.3 Fase 3 — Formulario de datos

El formulario recoge tres bloques de información: identificación, contacto y primera demanda.

**Identificación.**

| Campo | Obligatorio | Notas |
|---|---|---|
| Nombre | Sí (no en PSH) | |
| Apellido 1 | Sí (no en PSH) | |
| Apellido 2 | No | |
| Fecha de nacimiento | No | Anterior a hoy |
| Sexo | Sí | Configurable desde backoffice, no enum cerrado |
| Alias / nombre operativo | Solo en PSH | Identificador operativo para personas sin documentación formal |

**Documentos de identidad.** En el alta se recoge un único documento, en orden de preferencia DNI > NIE > Pasaporte. Los documentos adicionales (p.ej. el pasaporte de quien posteriormente obtiene el NIE) se incorporan desde la ficha del ciudadano sin borrar el anterior, para mantener la trazabilidad de búsquedas retrospectivas: alguien dado de alta con pasaporte marroquí debe ser encontrable por ese pasaporte años después de tener DNI.

**Contacto.**

| Campo | Obligatorio | Notas |
|---|---|---|
| Domicilio | No (no en PSH) | Texto libre; se normaliza y geocodifica automáticamente |
| Teléfono | No | |
| Correo electrónico | No | |

El domicilio se introduce en formato libre. El sistema lo normaliza a dirección canónica (tipo de vía, nombre de vía, número, complementos, código postal) y lanza la geocodificación mediante `GeocodificadorService`. Si el geocodificador falla, el alta no se bloquea: la dirección queda pendiente de normalización y un job asíncrono (`NormalizarDireccionJob`) reintentará en cola `low`.

Para PSH, el domicilio no aplica. En su lugar se capturan las coordenadas del lugar habitual de pernocta, que son el equivalente funcional al domicilio para este colectivo.

Los campos precargados desde el padrón se muestran con indicación visual de su procedencia. El profesional debe revisarlos y puede modificarlos antes de guardar.

**Primera demanda.** Texto libre, opcional. Recoge la expresión del ciudadano sobre el motivo de su visita, en sus propias palabras. No es una valoración profesional, no genera derechos y no implica que la demanda sea procedente. Se registra como dato del momento del alta en el campo `primera_demanda` de la tabla `ciudadanos` (Capa 1).

Este campo tiene valor en todos los contextos. En servicios de atención social primaria, orienta la derivación y la asignación de cita. En centros de mayores o juveniles, puede orientar la programación de actividades ("quiero hacer algo para estar activo", "mi vecina me dijo que las clases de baile son muy divertidas").

### 4.4 Fase 4 — Confirmación y continuación

Antes de guardar, el sistema muestra un resumen del ciudadano a registrar: nombre, documento, fecha de nacimiento, nivel de identificación y cualquier indicador de excepción (sin padrón — PSH, sin padrón — VVG, etc.).

El profesional elige qué hacer a continuación:

- **Crear cita**: redirige al formulario de nueva cita para este ciudadano.
- **Ir a la ficha**: redirige a la ficha del ciudadano recién creado.
- **Solo guardar**: guarda y vuelve a la pantalla de búsqueda.

La cita con el trabajador social y la valoración son eventos conceptualmente distintos del alta. Pueden ocurrir en el mismo momento y con el mismo profesional, pero no son parte del alta. Si el ciudadano se ha acercado al centro sin cita y está siendo atendido en ese momento, puede pasar directamente del alta a la valoración sin necesidad de cita previa.

---

## 5. Nivel de identificación

El campo `nivel_identificacion` del registro de ciudadano refleja la confianza en la identidad del registro:

- `identificado`: se ha verificado al menos un documento oficial.
- `probable`: hay datos suficientes para operar (nombre + fecha de nacimiento) pero sin verificación documental.
- `no_identificado`: nivel mínimo, solo posible en contexto PSH sin documentación.

---

## 6. Motor de detección de duplicados

El motor de matching se ejecuta en dos momentos durante el alta:

**Primera pasada** — al ejecutar la búsqueda en la fase 1, sobre el documento o los datos introducidos por el profesional.

**Segunda pasada** — al guardar el formulario (fase 3), sobre los datos normalizados completos. Es posible que los datos del formulario (nombre completo, fecha de nacimiento, teléfono) revelen una coincidencia que no apareció en la búsqueda inicial por documento. Si la segunda pasada detecta una coincidencia casi segura, bloquea el guardado y muestra los candidatos. Si detecta coincidencias probables ya vistas en la primera pasada, registra en auditoría la decisión del profesional de continuar.

El profesional siempre ve el motivo de la similitud (qué campos coinciden), no solo un score numérico.

---

## 7. Datos no incluidos en el alta

El formulario de alta **no recoge**:

- Valoraciones sociales, económicas, familiares o de vivienda
- Documentos adjuntos
- Unidad de convivencia (se gestiona desde la ficha)
- Historia social (se abre desde la ficha, por un TSR, cuando procede)
- Prestaciones o solicitudes
- Citas (son un evento posterior, aunque puedan gestionarse en el mismo momento)

---

## 8. Auditoría

Todas las altas quedan registradas en `ciudadanos_auditoria` con el usuario que realizó el alta, la fecha y hora, y los valores de cada campo en el momento de la creación.

Las excepciones de padrón de tipo "otra" quedan registradas con la justificación introducida por el profesional.

Las decisiones de continuar con el alta pese a coincidencias probables detectadas por el motor de matching quedan registradas con los candidatos que se mostraron y la decisión tomada.

---

## 9. Relación con otros módulos y eventos

| Evento posterior | Quién lo gestiona | Cuándo ocurre |
|---|---|---|
| Apertura de historia social | TSR con rol `intervencion` | Cuando hay necesidad de intervención activa |
| Primera cita | Cualquier rol habilitado | Puede ser inmediatamente después del alta |
| Valoración | TSR con rol `intervencion` | Tras la primera entrevista |
| Inscripción en actividad de centro | Personal del centro | Alta completada, contexto `actividad_centro` |
| Incorporación a unidad de convivencia | Tramitación o intervención | Desde la ficha del ciudadano |

---

## 10. Decisiones pendientes de fases posteriores

- **Umbrales del motor de matching**: calibrar los valores concretos de score una vez disponibles datos reales de prueba. Los umbrales son configurables desde el backoffice sin necesidad de desarrollo.
- **Sincronización con HSU-CM**: cuando esté disponible la integración con la Historia Social Única de la Comunidad de Madrid, revisar qué datos del alta se sincronizan y con qué frecuencia.
- **Acceso del ciudadano a sus propios datos**: cuando se implemente la carpeta ciudadana (rol 0), definir qué campos del alta son visibles para el propio ciudadano.
