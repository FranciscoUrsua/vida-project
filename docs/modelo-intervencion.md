# Módulo de Intervención — Modelo Funcional
## VIDA 360 · Documento de diseño

> Este documento describe el modelo funcional del núcleo de intervención de VIDA 360: el conjunto de entidades, flujos y decisiones de diseño que soportan el ciclo completo de atención social desde el primer contacto en el SIA hasta el cierre del Plan de Intervención. Debe leerse junto con `principios-vida360.md` y `Arquitectura.md`.

---

## 1. Visión general del flujo de intervención

El ciclo de intervención sigue la siguiente secuencia general, aunque no todos los casos recorren todas las etapas:

1. **Acogida** (SIA o TSR): si el municipio tiene el SIA implementado, el auxiliar atiende la demanda, da de alta o actualiza al ciudadano y gestiona la cita. Si no, el TSR recibe directamente al ciudadano y asume en la primera entrevista las funciones de acogida y clasificación.
2. **Entrevista con el TSR**: el trabajador social de referencia recibe al ciudadano, recoge información y valora la situación.
3. **Valoración**: en función de la complejidad, el TSR realiza una valoración estructurada mediante fichas configurables.
4. **Plan de Intervención (PISO)**: si la situación lo requiere, se elabora un plan con objetivos, prestaciones y compromisos mutuos.
5. **Seguimiento**: el plan se revisa periódicamente mediante entrevistas de seguimiento y apuntes asociados.
6. **Cierre**: el plan se cierra cuando se alcanzan los objetivos, se produce un abandono o cambian las circunstancias.

Este flujo aplica principalmente a ASP. La atención especializada sigue un modelo análogo con sus propias valoraciones y planes, vinculados al plan general del TSR.

---

## 2. Entidad: Registro de contacto SIA

> **El SIA es opcional.** Su presencia en el flujo depende de si el municipio lo tiene implementado como servicio diferenciado. Si no existe SIA, las funciones descritas en esta sección (alta del ciudadano, clasificación de la demanda, determinación de urgencia) recaen en el TSR durante la primera entrevista. El modelo de datos soporta ambos flujos: el campo `sia_contacto_id` en la Historia Social es nullable.

Toda interacción con el SIA queda registrada, independientemente de si genera Historia Social. El auxiliar es el primer actor que opera en VIDA para cada ciudadano.

### 2.1 Responsabilidades del auxiliar en VIDA

- Dar de alta al ciudadano si no existe en el sistema, o actualizar su información si ya existe.
- Registrar la demanda presentada.
- Clasificar la demanda: competencia municipal o competencia de otra administración.
- Si es competencia de otra administración: registrar la información prestada como prestación de tipo *información* (sin abrir Historia Social).
- Si es competencia municipal: determinar el nivel de urgencia y gestionar la cita con el TSR correspondiente.

### 2.2 Niveles de urgencia (determinan el plazo de atención garantizado)

| Nivel | Plazo máximo de atención por el TSR |
|---|---|
| Urgencia | 24 horas |
| Prioritario | 5 días hábiles |
| Ordinario | 15 días hábiles |

### 2.3 Atributos del registro de contacto SIA

- `id`
- `ciudadano_id` (FK a SocialUser)
- `auxiliar_id` (FK al profesional)
- `fecha_hora`
- `canal` (enum: `presencial`, `telefónico`, `digital`)
- `descripcion_demanda` (texto libre)
- `clasificacion` (enum: `competencia_municipal`, `otra_administracion`, `informacion_general`)
- `informacion_prestada` (texto, si clasificacion = `otra_administracion`)
- `urgencia` (enum: `urgencia`, `prioritario`, `ordinario`; nullable si no genera cita)
- `cita_id` (FK, nullable — si genera cita con el TSR)
- `prestaciones_identificadas` (JSON array de IDs del catálogo, provisional, requiere validación del TSR)

### 2.4 Integración con el módulo de citas y agendas

La gestión de citas es competencia de un módulo específico (a desarrollar). El registro SIA genera una cita que se integra con el sistema de citas del Ayuntamiento y con la agenda del TSR. A efectos del modelo de intervención, la cita es una referencia externa que puede o no materializarse en una entrevista.

---

## 3. Entidad: Entrevista

La entrevista es el contenedor de trabajo del profesional durante y después del encuentro con el ciudadano. Es una entidad ligera que actúa como nodo de conexión hacia otras entidades (valoraciones, planes, documentos, seguimientos).

### 3.1 Atributos

- `id`
- `historia_id` (FK — toda entrevista pertenece a una Historia Social)
- `profesional_id` (FK)
- `cita_id` (FK, nullable — las entrevistas no programadas no tienen cita asociada)
- `fecha_hora`
- `modalidad` (enum: `presencial`, `telefónica`, `videollamada`, `domicilio`)
- `tipo` (enum: `inicial`, `seguimiento`, `urgencia`, `informativa`)
- `notas_generales` (texto libre, nullable — observaciones que no corresponden a ninguna ficha concreta: contexto de la sesión, impresiones del profesional, circunstancias relevantes del encuentro)
- `estado` (enum: `programada`, `realizada`, `cancelada`, `no_presentado`)
- `created_at`, `updated_at`

### 3.2 Relaciones

- `hasOne: Valoracion` (opcional — si la entrevista genera valoración)
- `hasOne: SeguimientoPlan` (opcional — si es entrevista de seguimiento de un PISO)
- `belongsTo: PlanDeIntervencion` (opcional, nullable — para entrevistas de seguimiento ya vinculadas a un plan)
- `hasMany: DocumentoAportado`
- `hasMany: DecisionEntrevista`

Las entrevistas sin cita previa son admitidas explícitamente: visitas domiciliarias, contactos urgentes no programados y llamadas telefónicas no tienen cita asociada. El campo `cita_id` es siempre nullable.

### 3.3 Flujo de la UI según tipo de entrevista

El campo `tipo` determina qué propone la interfaz al profesional al finalizar la entrevista:

- **Inicial**: el profesional trabaja directamente sobre las fichas de la valoración durante la entrevista, tomando notas en el campo `notas` de cada ficha. Al terminar, convierte esas notas en los campos estructurados de cada ficha. La entrevista queda vinculada a la Valoración resultante.
- **Seguimiento**: muestra el estado actual del Plan vinculado y propone crear un registro de seguimiento. Las decisiones se registran en `DecisionEntrevista`.
- **Urgencia**: activa un flujo acelerado que puede derivar directamente a propuesta de prestación sin valoración completa.
- **Informativa**: no propone acciones adicionales. Registra el contacto en el historial de la Historia Social.

### 3.4 Notas en fichas vs. notas generales

En las entrevistas de valoración, el espacio principal de captura son los campos `notas` de cada ficha, no el campo `notas_generales` de la entrevista. El profesional conduce la conversación con la estructura de las fichas como guía, anota lo relevante en cada área y después estructura esas notas en los campos de datos de la ficha.

El campo `notas_generales` de la entrevista existe para observaciones transversales que no encajan en ninguna ficha: circunstancias del encuentro, dificultades de comunicación, contexto relevante para interpretar la valoración.

Esta separación refleja el flujo real de trabajo: el TSR no toma notas genéricas y luego las distribuye; piensa y trabaja por áreas desde el inicio de la entrevista.

---

## 4. Entidad: Valoración

La valoración recoge el diagnóstico estructurado de la situación del ciudadano. Es una entidad separada de la entrevista porque tiene su propio ciclo de vida: puede completarse en varias sesiones, puede revisarse posteriormente y puede existir en múltiples versiones a lo largo del tiempo.

**No toda entrevista genera valoración.**

### 4.1 Atributos

- `id`
- `historia_id` (FK)
- `entrevista_id` (FK, nullable — la entrevista que la origina)
- `profesional_id` (FK)
- `tipo_valoracion_id` (FK — referencia a la configuración de backoffice)
- `fecha`
- `estado` (enum: `borrador`, `completada`, `revisada`)
- `resumen` (texto libre, opcional — síntesis profesional de la valoración)
- `created_at`, `updated_at`

### 4.2 Sistema configurable de tipos y fichas

El modelo de valoración es completamente configurable desde el backoffice. No está hardcodeado. Esto permite adoptar nuevas herramientas metodológicas sin desarrollo, definir valoraciones específicas por servicio especializado, y pilotar nuevas metodologías en centros o profesionales concretos sin afectar al resto del sistema.

**Tres niveles de configuración:**

*Nivel 1 — Estructura de fichas y campos* (`tipo_ficha`): define qué fichas existen, qué campos tiene cada ficha, el tipo de cada campo (numérico, texto, select, escala, fecha), cuáles son obligatorios, en qué orden aparecen, y las reglas de visibilidad condicional entre campos.

*Nivel 2 — Lógica condicional* (dentro del schema JSON de `tipo_ficha`): reglas simples del tipo `[campo_origen, valor_condición, acción, campo_destino]` que permiten ocultar secciones irrelevantes según respuestas anteriores.

*Nivel 3 — Composición de valoraciones* (`tipo_valoracion_fichas`): define qué fichas componen cada tipo de valoración, en qué orden, y cuáles son obligatorias u optativas en ese contexto.

**Tablas de configuración en backoffice:**

`tipo_valoracion`: `id`, `nombre`, `contexto` (ASP, especializada_mayores, especializada_familia...), `descripcion`.

`tipo_ficha`: `id`, `nombre`, `descripcion`, `schema` (JSON con definición completa de campos y reglas condicionales).

`tipo_valoracion_fichas`: tabla pivote con `tipo_valoracion_id`, `tipo_ficha_id`, `orden`, `obligatoria`.

**Tabla de datos reales:**

`fichas`: `id`, `valoracion_id`, `tipo_ficha_id`, `datos` (JSON con valores reales), `notas` (texto libre), `completada`.

### 4.3 Frontera de la configurabilidad

La configuración de fichas y campos define estructura y presentación. No puede definir lógica de negocio con consecuencias: qué prestaciones se activan automáticamente, qué alertas de riesgo se disparan, qué derivaciones son obligatorias. Esa lógica vive en código, con sus tests, referenciando los tipos de ficha por identificador estable.

### 4.4 Filosofía de captura de datos

El modelo prioriza la calidad de los datos sobre su exhaustividad. Cada ficha tiene un núcleo estructurado mínimo —los campos verdaderamente necesarios para decisiones o prestaciones concretas— y un campo `notas` de texto libre para el contexto que no cabe en estructura.

El flujo de trabajo es: el profesional toma notas en cada ficha durante la entrevista y las convierte en campos estructurados al terminar. El sistema nunca bloquea el avance por campos no rellenados salvo que sean condición explícita para una prestación.

---

## 5. Entidad: Plan de Intervención (PISO)

El Plan de Intervención es el acuerdo formal entre el profesional y el ciudadano. Recoge los objetivos a alcanzar, las prestaciones comprometidas por el sistema y los compromisos adquiridos por el ciudadano. Requiere firma de ambas partes y es condición necesaria para acceder a determinadas prestaciones.

### 5.1 Tipos de plan

**Plan general (ASP)**: gestionado por el TSR. Tiene visión integral de la situación e incluye todas las derivaciones activas a atención especializada. Es el mapa completo de la intervención con la persona.

**Plan especializado**: gestionado por el profesional de atención especializada. Autónomo en contenido y seguimiento, pero visible para el TSR. El TSR no puede editarlo. Nace como consecuencia de una prestación de derivación en el plan de ASP, lo que crea el vínculo trazable entre ambos niveles.

Una Historia Social puede tener varios planes activos simultáneamente, cada uno con su responsable y su ciclo de vida independiente.

### 5.2 Atributos

- `id`
- `historia_id` (FK)
- `tipo` (enum: `general_asp`, `especializado`)
- `servicio_especializado_id` (FK, nullable)
- `profesional_responsable_id` (FK)
- `plan_asp_id` (FK, nullable, self-referential — para planes especializados, referencia al plan general del que nacen)
- `estado` (enum: `borrador`, `activo`, `en_revision`, `cerrado`)
- `fecha_inicio`
- `fecha_firma` (nullable hasta firma)
- `fecha_cierre` (nullable)
- `motivo_cierre` (enum: `objetivos_cumplidos`, `abandono`, `derivacion`, `fallecimiento`, `otros`)
- `objetivos` (texto libre — fase inicial; se prevé evolución a lista estructurada con indicadores medibles en fases posteriores)
- `version` (integer — control de revisiones)
- `created_at`, `updated_at`

### 5.3 Firma del plan

El plan requiere firma de ambas partes para activarse. La firma se gestiona en una entidad separada `FirmaPlan` para conservar el historial a través de las revisiones:

`FirmaPlan`: `id`, `plan_id`, `version`, `firma_ciudadano` (blob o referencia a archivo), `firma_profesional` (blob o referencia a archivo), `metodo_firma` (enum: `wacom`, `manuscrita_escaneada`, `digital_certificada`), `fecha_firma`.

Cada revisión sustancial del plan que requiera nueva firma genera un nuevo registro `FirmaPlan` manteniendo el historial completo.

### 5.4 Versionado y revisiones

El plan no se edita destructivamente. Cuando se modifica, se incrementa `version` y la versión anterior queda archivada. La tabla `RevisionPlan` registra el historial de modificaciones: `plan_id`, `version_anterior`, `version_nueva`, `profesional_id`, `fecha`, `motivo_revision`, `seguimiento_id` (FK nullable — si la revisión tiene origen en un seguimiento concreto).

### 5.5 Modificación del plan como resultado de un seguimiento

Un seguimiento puede concluir con la decisión de modificar el plan. El flujo es:

1. El TSR cierra el seguimiento y marca que requiere revisión del plan.
2. Se crea una nueva versión del plan (`version` incrementa, la anterior queda archivada) con el `seguimiento_id` registrado en `RevisionPlan` como origen trazable.
3. La nueva versión pasa a estado `borrador` hasta obtener la firma de ambas partes.
4. Se genera un nuevo registro `FirmaPlan` para esta versión. El plan no pasa a `activo` hasta que ambas firmas estén recogidas.

### 5.6 Visibilidad entre niveles

Los profesionales con rol de intervención tienen acceso de lectura a los planes de otros profesionales y servicios sobre el mismo ciudadano. Este principio es operativamente crítico: el TSR necesita saber lo acordado con Infancia, y el trabajador social de Infancia necesita ver si el ciudadano percibe una ayuda de comedor gestionada desde ASP. La restricción de acceso como mecanismo de "propiedad" profesional no está soportada por el sistema (ver `principios-vida360.md`, sección 3.4).

---

## 6. Entidad: SeguimientoPlan

El seguimiento es el registro de una sesión de revisión del Plan de Intervención. Se genera a partir de una entrevista de tipo `seguimiento` y queda vinculado al plan mediante un apunte.

### 6.1 Atributos

- `id`
- `plan_id` (FK)
- `entrevista_id` (FK — la entrevista de seguimiento que lo origina)
- `profesional_id` (FK)
- `fecha`
- `avances` (texto libre — progreso observado respecto a los objetivos del plan)
- `objetivos_cumplidos` (texto libre — objetivos que se consideran alcanzados en este seguimiento)
- `incidencias` (texto libre, nullable — circunstancias relevantes que han afectado al desarrollo del plan)
- `nuevas_prestaciones` (JSON array de IDs del catálogo, nullable — prestaciones iniciadas como resultado de este seguimiento)
- `requiere_revision_plan` (boolean — indica si este seguimiento ha derivado en una modificación del plan)
- `fecha_siguiente_seguimiento` (date, nullable — fijada por el TSR al cerrar el seguimiento)
- `created_at`, `updated_at`

### 6.2 Programación del siguiente seguimiento

Cuando el TSR fija `fecha_siguiente_seguimiento`, el sistema solicita al módulo de Agenda la creación de una cita para esa fecha con el mismo profesional y ciudadano, usando el tipo de slot `entrevista_seguimiento`. La cita se crea en estado `reservado` en el slot correspondiente. Si no existe disponibilidad en esa fecha, el sistema advierte al TSR sin bloquear el guardado del seguimiento.

La integración sigue el mismo mecanismo que cualquier otra cita del sistema: el módulo de Intervención solicita la cita al módulo de Agenda a través de su interfaz interna; no crea registros de agenda directamente.

### 6.3 Nuevas prestaciones en el seguimiento

Las prestaciones iniciadas durante un seguimiento se registran como referencias al catálogo (`nuevas_prestaciones`), no como texto libre, para mantener la trazabilidad analítica. Cada prestación referenciada genera el mismo flujo que una prestación iniciada en cualquier otro contexto: se registra en la Historia Social y, si procede, se vincula al expediente administrativo correspondiente.

---

## 7. Entidad: Apunte

El apunte es el mecanismo de asociación de elementos heterogéneos al Plan de Intervención. Es un nodo de conexión que puede apuntar a entidades muy diversas: entrevistas, documentos, derivaciones, seguimientos o anotaciones sin entidad vinculada.

### 7.1 Atributos

- `id`
- `plan_id` (FK)
- `autor_id` (FK al profesional)
- `fecha`
- `tipo` (enum: `entrevista`, `documento`, `derivacion`, `seguimiento`, `anotacion`)
- `apuntable_type` + `apuntable_id` (polimórfico — la entidad concreta vinculada)
- `contenido` (texto, nullable — para anotaciones sin entidad vinculada)
- `visibilidad` (enum: `privada`, `profesionales`, `ciudadano`)

### 7.2 Tres niveles de visibilidad

**`privada`**: solo visible para el autor. Nunca accesible para otros profesionales, aunque asuman el caso. Es el espacio de trabajo personal del profesional: observaciones preliminares, hipótesis de trabajo, notas de cautela. Siendo esto conocido por todos los profesionales del sistema, cada uno valora conscientemente qué registra como privado y qué registra como visible para quien le suceda. Las anotaciones privadas no se transfieren al cambiar de profesional responsable.

**`profesionales`**: visible para cualquier profesional con acceso a la historia, no para el ciudadano. Es el espacio de trabajo profesional habitual: seguimientos, valoraciones, derivaciones, decisiones técnicas. La mayoría de los apuntes vivirán en este nivel.

**`ciudadano`**: visible también para el ciudadano a través de su carpeta ciudadana (cuando esa integración esté activa). Documentos entregados, acuerdos firmados, prestaciones activas.

### 7.3 Visibilidad ciudadana y derecho de acceso

La visibilidad `profesionales` no equivale a "el ciudadano nunca puede ver esto". Significa que ese contenido no se muestra en la carpeta ciudadana de forma automática. El ciudadano tiene derecho de acceso a su historia social completa si lo solicita formalmente, conforme al RGPD. Ese ejercicio es un proceso administrativo diferente, no una funcionalidad de la plataforma. Esta distinción debe ser conocida por todos los profesionales del sistema.

---

## 8. Decisiones de diseño transversales

### 8.1 Configurabilidad como principio

El modelo de valoración y el modelo de plan no están hardcodeados. Ambos son configurables desde el backoffice para permitir evolución metodológica sin desarrollo. Un sistema hardcodeado para el modelo actual sería deuda técnica desde el primer día.

### 8.2 Coste de la configurabilidad

La configurabilidad tiene un coste real que se asume explícitamente: el frontend debe renderizar formularios dinámicamente a partir de schemas, la validación debe interpretarse en tiempo de ejecución, las migraciones cuando cambia un schema son delicadas, y el backoffice de configuración es en sí mismo una pieza de software no trivial. El retorno —un sistema que sobrevive a cambios metodológicos sin depender del equipo de desarrollo— es positivo en el horizonte temporal de un sistema municipal (10-15 años).

### 8.3 Captura de información: calidad sobre exhaustividad

El sistema prioriza un núcleo mínimo de campos verdaderamente necesarios para decisiones y prestaciones, combinado con notas de texto libre por ficha como espacio de trabajo principal durante la entrevista. El profesional convierte esas notas en estructura al finalizar la sesión. Un JSON con texto rico es recuperable analíticamente; un campo vacío no lo es.

### 8.4 IA como asistente, no como árbitro

Toda funcionalidad de IA en este módulo requiere validación explícita del profesional antes de producir cualquier efecto. El sistema hace siempre visible cuándo una sugerencia proviene de un componente de IA (ver `principios-vida360.md`, sección 3.9).

### 8.5 Integración entre módulos: Intervención y Agenda

El módulo de Intervención no gestiona directamente slots ni citas. Toda solicitud de cita (desde el SIA, desde el cierre de un seguimiento, o desde cualquier otro punto del flujo) se realiza a través de la interfaz del módulo de Agenda. El módulo de Intervención es consumidor de Agenda, nunca escribe en sus tablas directamente. Esto incluye la programación del siguiente seguimiento: `fecha_siguiente_seguimiento` en `SeguimientoPlan` es la intención del profesional; la cita resultante pertenece al módulo de Agenda.

---

## 9. Decisiones pendientes

- **Modelo de objetivos del plan — evolución futura**: en la fase inicial los objetivos son texto libre. Se prevé incorporar en fases posteriores una lista estructurada de objetivos con indicadores medibles, que permita registrar el progreso de forma cuantitativa y determinar el cierre por objetivos cumplidos de manera más precisa.
- **Pilotaje de la Self-Sufficient Matrix (SSM)**: la arquitectura la soporta como un `tipo_ficha` configurable. La decisión de adopción, pilotaje y formación es organizativa, no técnica.
- **Asistencia de IA durante la entrevista**: se ha evaluado la posibilidad de que la IA analice las notas en tiempo real para sugerir preguntas al profesional y proponer estructura de fichas. Descartado para la fase inicial por complejidad de implementación. Los requisitos de diseño están documentados en el historial de decisiones del proyecto. A retomar cuando el flujo base esté consolidado.
- **Transcripción automática de audio**: evaluada y descartada como flujo estándar. Razones principales: riesgo de inhibición de información sensible por parte del ciudadano al saber que se graba, complejidad del consentimiento informado en contextos de vulnerabilidad, dificultades técnicas con personas con problemas de lenguaje (colectivo frecuente), y coste de mantenimiento de un modelo local con calidad suficiente. Puede reconsiderarse como opción voluntaria para tipos específicos de entrevista en fases posteriores.

---

*Documento elaborado en fase de diseño conceptual. Versión inicial: marzo 2026.*
