# Glosario de términos — VIDA 360

> Este glosario recoge las definiciones de los conceptos clave del dominio de los servicios sociales municipales, con sus implicaciones concretas para el diseño y desarrollo de VIDA 360. Las definiciones de referencia proceden de la Ley 12/2022 de Servicios Sociales de la Comunidad de Madrid, el Decreto 51/2023 (HSU), la Orden 2372/2023 y el Diccionario de Entidades del proyecto de transformación digital del Ayuntamiento de Madrid.
>
> Para cada término se indica: la definición de referencia, las implicaciones para VIDA y las decisiones de modelado que se derivan. Cuando el término tenga correspondencia en el código, se añadirá una referencia al módulo, modelo o controlador correspondiente.
>
> **Nota sobre "módulo":** en este glosario, el término *módulo* designa un paquete de funcionalidad del sistema desde el punto de vista del negocio, no un `Module` de Laravel. La correspondencia con la estructura técnica se indica explícitamente en cada caso.

---

## 1. Ciudadanía

### Ciudadano/a

**Definición de referencia:** Toda persona con vecindad administrativa en la Comunidad de Madrid, o en las circunstancias específicas recogidas en el Art. 4 de la Ley 12/2022 (españoles en el exterior, solicitantes de asilo, menores en tránsito, personas en urgencia social). En el sistema CIVIS del Ayuntamiento de Madrid se denomina *usuario/a del Sistema Público de Servicios Sociales* y dispone de un código NI-HSU-CM.

**Implicaciones para VIDA:** Es la entidad central del sistema. Toda interacción, Historia Social, prestación o acto profesional está vinculada a un ciudadano. Toda persona que interactúa con VIDA 360, independientemente de la intensidad de esa interacción. El registro genera un identificador único permanente. Las capas de datos (situación social, historia social) se activan por decisión profesional
según el contexto de alta; no son consecuencia automática del registro.

**Decisiones de modelado:**
- Entidad `Ciudadano` con identificador único interno, independiente del documento de identidad.
- Soporte para múltiples documentos de identidad a lo largo del tiempo (DNI, NIE, pasaporte) sin perder la trazabilidad. Ver principio 3.1.
- Incluye variable sexo (principio 4.14) y coordenadas de domicilio (principio 4.15).
- Referencia futura: NI-HSU-CM como campo de interoperabilidad con la Comunidad de Madrid.

---

### Representante

**Definición de referencia:** Sujeto que actúa de forma legítima en nombre de otra persona. No es necesariamente un representante legal (con poder notarial o sentencia); puede ser un familiar que gestiona trámites con el consentimiento de la persona.

**Implicaciones para VIDA:** Un ciudadano puede tener uno o varios representantes registrados. El representante no es titular de la Historia Social, pero puede realizar gestiones en nombre del titular.

**Decisiones de modelado:**
- Relación `Ciudadano → Representante` con campo `tipo` (legal / no legal) y fechas de vigencia.
- El acceso del representante al sistema (si se implementa acceso ciudadano) requiere un nivel de permiso específico. Ver principio 3.6 sobre colectivos protegidos.
- Pendiente de definición: límites del representante en situaciones de conflicto familiar.

---

### Unidad de Convivencia

**Definición de referencia:** Conjunto de personas que viven en el mismo domicilio, unidas por vínculo matrimonial, pareja de hecho, o consanguinidad/afinidad/adopción hasta el segundo grado, o en situación de guarda con fines de adopción o acogimiento familiar permanente (Ley IMV). La ordenanza municipal de prestaciones económicas usa el término *unidad familiar* con una definición similar pero no idéntica.

**Implicaciones para VIDA:** La intervención social frecuentemente se dirige a la unidad de convivencia, no solo al individuo. Una Historia Social puede estar vinculada a varios miembros de la misma unidad. Las prestaciones económicas habitualmente se calculan en función de los ingresos y composición de la unidad.

**Decisiones de modelado:**
- Entidad `UnidadDeConvivencia` con miembros (`Ciudadano`) y fechas de vigencia de la composición (la unidad cambia con el tiempo).
- Un `Ciudadano` puede pertenecer a más de una unidad a lo largo del tiempo, y potencialmente a más de una simultáneamente (situaciones de separación, menores con custodia compartida).
- La `HistoriaSocial` puede vincularse a un ciudadano individual o a una unidad de convivencia.

---

## 2. Profesionales y actos profesionales

### Profesional

**Definición de referencia:** Empleado público o trabajador de entidad privada que presta servicios de atención social en el Sistema Público de Servicios Sociales (Art. 30, Ley 12/2022). Incluye tanto personal de atención directa (trabajadores sociales, psicólogos, educadores sociales, auxiliares) como personal de gestión y dirección, tanto interno (funcionarios de distrito o de Área de Gobierno) como externo (personal de entidades contratadas).

**Implicaciones para VIDA:** El profesional es el actor principal del sistema. Sus permisos dependen de su rol, su vinculación orgánica y los ciudadanos/centros que tiene asignados.

**Decisiones de modelado:**
- Entidad `Profesional` con campos de categoría, dependencia orgánica (distrito / Área de Gobierno), tipo de vínculo (interno / externo) y centro de adscripción.
- Los permisos se asignan mediante roles configurables (principio 3.6, 4.15). Ver también `Rol` y `Permiso` en la sección 5.
- Referencia de código: `App\Models\Profesional`, `Modules\Centro\Models\CentroProfesional`.

---

### Profesional de Referencia (TSR — Trabajador Social de Referencia)

**Definición de referencia:** Trabajador social asignado a un ciudadano en Atención Social Primaria, con la finalidad de dar coherencia al itinerario de intervenciones y garantizar el acceso a los servicios y prestaciones que necesite (Art. 5 y 16, Ley 12/2022).

**Implicaciones para VIDA:** Es el eje de la visión 360. Tiene acceso de lectura a toda la Historia Social del ciudadano, incluidos los planes de especializada. Es quien ve el registro de auditoría de accesos (principio 3.5). La asignación se basa en el domicilio del ciudadano.

**Decisiones de modelado:**
- Campo `profesional_referencia_id` en `HistoriaSocial`, con historial de asignaciones (el TSR puede cambiar).
- El TSR tiene permisos de lectura sobre todos los planes vinculados a la Historia, pero solo edición sobre el plan de ASP.

---

### Historia Social (HS / HSU)

**Definición de referencia:** Instrumento en soporte digital que reúne la demanda o demandas del usuario al Sistema Público de Servicios Sociales, así como el registro exhaustivo de sus datos y de su unidad familiar o de convivencia a lo largo de las diferentes etapas de su vida. Incluye datos personales, familiares, sociales, sanitarios, de vivienda, económicos, laborales y educativos, el diagnóstico social, el plan individualizado de intervención, las acciones realizadas, su evolución, seguimiento y evaluación (Art. 4, Decreto 51/2023). A nivel de la Comunidad de Madrid se denomina HSU (Historia Social Única).

**Implicaciones para VIDA:** Es la entidad nuclear del sistema de intervención. Se abre cuando existe una demanda que requiere valoración, plan o seguimiento municipal — no para contactos informativos. Ver principio 3.2.

**Decisiones de modelado:**
- Entidad `HistoriaSocial` vinculada a `Ciudadano`, con estado (abierta / en seguimiento / cerrada), fecha de apertura, centro de referencia y TSR asignado.
- Relación con `PlanDeIntervencion` (varios, de distintos ámbitos), `Apunte` (todos los actos profesionales) y `Ficha Social`.
- El pasado es inmutable: los cambios de estado generan nuevos registros, no sobrescriben los anteriores (principio 4.2).
- Referencia de código: `Modules\Intervencion\Models\Historia`.

---

### Ficha Social

**⚠️ Término con dos acepciones en VIDA 360 — leer con atención.**

---

**Acepción 1 — Término legal (Decreto 51/2023):**
Documento que reúne los datos de identidad, contacto y situación social del ciudadano. En VIDA, esta acepción corresponde a la combinación de la Capa 1 (tabla `ciudadanos`) y la Capa 2 (tabla `ciudadano_fichas`). La "Ficha Social" en sentido legal no tiene entidad propia en el modelo — es una vista conceptual sobre dos capas de datos. Se usa este término cuando se habla del sistema en términos
legales o en comunicación con la Comunidad de Madrid (HSU-CM).

> **Nota:** La definición del Decreto 51/2023 incluye tanto datos de identidad como datos socioeconómicos y familiares. En VIDA estos están en capas separadas con distintos niveles de acceso, lo que es más restrictivo y más correcto que tratar ambas cosas como un bloque único.

---

**Acepción 2 — Instrumento de valoración (módulo Intervención):**
Formulario configurable que el TSR cumplimenta durante una valoración estructurada. En el código se denomina `Ficha` y `TipoFicha`, viven en el módulo Intervención y son completamente distintas de los datos de identidad del ciudadano. Su resultado son los registros de **situación social** (Capa 2).

---

**Regla para evitar confusión en conversaciones técnicas:**

| Concepto | Término correcto en VIDA | Nunca llamar |
|---|---|---|
| Nombre, domicilio, teléfono del ciudadano | "datos de identificación" o simplemente el nombre del campo | "ficha", "ficha del ciudadano" |
| Capa 2 (situación socioeconómica versionada) | "situación social" | "ficha social" (salvo contexto legal) |
| Formulario de valoración del TSR | "ficha de valoración" o "`Ficha` del módulo Intervención" | confundir con los datos de identidad |
| Término legal del Decreto 51/2023 | "Ficha Social (legal)" | aplicar como nombre interno de ninguna clase o tabla |

**Decisiones de modelado:**
- `CiudadanoSituacion` (tabla `ciudadano_fichas`) — situación social versionada. Ver `docs/modulo-ciudadania.md`, sección 3.5.
- Los campos de situación son dinámicos y configurables (principio 4.11).
- Campos sensibles cifrados en reposo (principio 4.10).
- **No todo ciudadano registrado tiene situación social.** Solo existe cuando hay o ha habido un proceso de intervención activo.
---

### Apunte (acto profesional)

**Definición de referencia:** En terminología de la Comunidad de Madrid, todos los actos profesionales registrados en la Historia Social se denominan genéricamente *apuntes*. Comparten una estructura común (quién, cuándo, sobre qué ciudadano, documentos adjuntos, documentos generados) y se especializan en distintos tipos según su naturaleza.

**Implicaciones para VIDA:** Los tipos de apunte son: Valoración, Seguimiento, Anotación, Entrevista, Gestión/Coordinación, Informe Social, Derivación. Deben poder añadirse nuevos tipos sin desarrollo ad hoc (principio 4.11).

**Decisiones de modelado:**
- Entidad base `Apunte` con campos comunes: `profesional_id`, `ciudadano_id`, `historia_id`, `tipo`, `fecha`, `documentos_adjuntos`, `documentos_generados`, `notas`.
- Cada tipo especializa la base con sus atributos propios mediante tabla de extensión o campo JSON de metadatos específicos.
- El catálogo de tipos de apunte es configurable desde el backoffice (principio 4.15).
- Ver definiciones individuales de cada tipo a continuación.

---

### Valoración

**Definición de referencia:** Conjunto de actuaciones profesionales de carácter técnico y/o de gestión que permiten a la ciudadanía ejercer su derecho de acceso a los servicios y prestaciones del Sistema Público de Servicios Sociales para favorecer la inclusión, autonomía y el bienestar social.

**Implicaciones para VIDA:** Es el acto profesional de mayor peso estructural: da lugar al diagnóstico social y fundamenta el Plan de Intervención. Puede ser inicial o de seguimiento.

**Decisiones de modelado:**
- Tipo de `Apunte` con campos adicionales: `tipo_valoracion` (inicial / sucesiva), `diagnostico`, relación con `PlanDeIntervencion` resultante.
- Puede incluir baremos (ver `Baremo`) como instrumentos de objetivación.

---

### Seguimiento

**Definición de referencia:** Recoge valoraciones profesionales acerca de los resultados obtenidos gracias a las intervenciones y sobre la adecuación de las prestaciones a las necesidades de la persona, así como propuestas de finalización, modificación o continuidad. Las valoraciones deben sustentarse en datos objetivos y verificables.

**Implicaciones para VIDA:** Es el mecanismo de revisión periódica del Plan de Intervención. Puede generar alertas si no hay avances.

**Decisiones de modelado:**
- Tipo de `Apunte` vinculado a un `PlanDeIntervencion` específico, con campos: `avances`, `alertas`, `propuesta` (continuar / modificar / cerrar).

---

### Anotación

**Definición de referencia:** Apunte del profesional donde recoge incidencias para tener en cuenta en la siguiente entrevista o gestión, o para informar a otro profesional que atienda al ciudadano posteriormente.

**Implicaciones para VIDA:** Es el tipo de apunte más ligero: no estructura diagnóstico ni plan, solo deja constancia de algo relevante.

**Decisiones de modelado:**
- Tipo de `Apunte` de estructura mínima: texto libre y posibles documentos adjuntos.

---

### Entrevista

**Definición de referencia:** Herramienta de intervención social. Encuentro entre profesional y persona usuaria. Puede ser presencial en el despacho, domiciliaria (visita a domicilio), telefónica o, en el futuro, por videollamada.

**Implicaciones para VIDA:** La entrevista es el acto presencial o remoto más frecuente. Debe poder programarse desde la Agenda y quedar registrada como apunte con su resultado.

**Decisiones de modelado:**
- Tipo de `Apunte` con campos adicionales: `modalidad` (presencial / domicilio / telefónica / videollamada), `duracion`, relación con `Cita` de la Agenda si fue programada.
- La modalidad videollamada está pendiente de implementación (principio 3.12, multicanalidad).

---

### Gestión / Coordinación

**Definición de referencia:** Actuación realizada por un profesional en referencia a un ciudadano/familia, con otro profesional, de cualquier entidad o internamente.

**Implicaciones para VIDA:** Registra la coordinación entre profesionales, tanto interna (entre departamentos del ayuntamiento) como externa (con otras administraciones, entidades del tercer sector, etc.). Es esencial para la visión 360.

**Decisiones de modelado:**
- Tipo de `Apunte` con campos adicionales: `profesional_externo` (si aplica), `entidad_externa`, `resultado`.

---

### Derivación

**Definición de referencia:** Remisión del usuario a otros centros o servicios de atención social, del propio sistema municipal u otros sistemas públicos de protección social. Suele acompañarse de un informe de derivación (informe social breve dirigido a un fin particular).

**Implicaciones para VIDA:** La derivación a especializada es una prestación del catálogo (principio 3.7). Genera el vínculo trazable entre el plan de ASP y el plan de especializada.

**Decisiones de modelado:**
- Tipo de `Apunte` vinculado a una `Prestacion` de tipo derivación del catálogo.
- Puede generar un `InformeSocial` asociado (documento generado).
- Si es derivación interna, crea o vincula el `PlanDeIntervencion` de especializada correspondiente.

---

### Informe Social

**Definición de referencia:** Dictamen técnico que elabora y firma con carácter exclusivo el profesional del Trabajo Social. Recoge la situación objeto, la valoración, un dictamen técnico y una propuesta de intervención profesional.

**Implicaciones para VIDA:** Es un documento generado, no un tipo de apunte en sí mismo, aunque frecuentemente es el resultado documentado de una valoración o derivación.

**Decisiones de modelado:**
- Entidad `Documento` generado, vinculable a cualquier `Apunte` como resultado.
- Generación de PDF desde plantilla configurable (principio 3.3, puntos de publicación).
- Solo puede ser firmado por un Trabajador Social (validación por rol).

---

### Plan Individualizado de Intervención Social (DIS / PISO)

**Definición de referencia:** Instrumento de intervención social elaborado con la participación del usuario, propuesto por el profesional de referencia al equipo del centro para su aprobación en un plazo máximo de diez días (Art. 40, Ley 12/2022). En el Ayuntamiento de Madrid se denomina DIS (Diseño de Intervención Social) en el sistema CIVIS. Incluye diagnóstico social, actuaciones, prestaciones propuestas y compromisos del ciudadano.

**Implicaciones para VIDA:** Existen planes de ASP (gestionados por el TSR, visión global) y planes de especializada (gestionados por el profesional especializado, autónomos pero visibles para el TSR). Una Historia Social puede tener varios planes activos simultáneamente. Ver principio 3.7.

**Decisiones de modelado:**
- Entidad `PlanDeIntervencion` con campos: `ambito` (asp / especializada), `tipo` (configurable desde backoffice), `profesional_id`, `historia_id`, `estado`, `fecha_inicio`, `fecha_fin`.
- Relación many-to-many con `Prestacion` (prestaciones incluidas en el plan).
- Campo `compromisos_ciudadano` para recoger los compromisos bilaterales.
- El tipo de plan es configurable (principio 4.11): el PISO de ASP y los PI de especializada son instancias del mismo módulo con configuración distinta.

---

### Mesa

**Definición de referencia:** Equipo de miembros permanentes e invitados, involucrados en los casos que se valoran, que plantean objetivos comunes y una estrategia de intervención. Actualmente centradas en casos de menores-familia (ETMF, CAF) o mayores vulnerables. Se calendarizan con programación de casos.

**Implicaciones para VIDA:** Es un mecanismo de coordinación multiprofesional sobre casos concretos. Genera acuerdos que deben quedar registrados en la Historia Social.

**Decisiones de modelado:**
- Pendiente de desarrollo. Requiere modelo de convocatoria, participantes, casos tratados y acuerdos adoptados. Los acuerdos se registran como `Apunte` de tipo Gestión/Coordinación en las historias afectadas.

---

### Agenda, Cita

**Definición de referencia:** La **Agenda** es el programa de citas y trabajos del profesional y del centro, con vistas de mes, semana y día, configurable según el tipo de centro. La **Cita** es la reserva de día y hora para la atención a un ciudadano por parte de un profesional.

**Implicaciones para VIDA:** La agenda debe soportar tanto citas individuales (entrevistas) como grupales (talleres, actividades). Su configuración depende del tipo de centro (principio 4.11).

**Decisiones de modelado:**
- Entidades `Agenda` y `Cita`. La agenda tiene configuración de días, duración de citas, modalidades y capacidades según `TipoCentro`.
- Una `Cita` puede generar una `Entrevista` (tipo de `Apunte`) cuando se produce.
- Pendiente: integración con canales de comunicación para confirmaciones y recordatorios (principio 3.11).

---

### Indicador y Baremo

**Definición de referencia:** Un **indicador** es información de riesgo social elaborada a partir de la Historia Social que permite efectuar una intervención personalizada y fomentar la actuación preventiva. Un **baremo** agrupa diversos indicadores para objetivar y ordenar una situación en su conjunto; puede orientarse a un diagnóstico general o regular el acceso a una prestación.

**Implicaciones para VIDA:** Los baremos son el mecanismo de objetivación del acceso a prestaciones. Deben ser configurables por prestación (principio 4.15).

**Decisiones de modelado:**
- Entidad `Baremo` configurable, con sus `Indicadores` asociados y pesos. Vinculable a `Prestacion` para regular el acceso.
- La IA puede asistir en la valoración de indicadores, pero la decisión final es del profesional (principio 3.9).

---

## 3. Recursos

### Centro

**Definición de referencia:** Unidad orgánica y funcional con infraestructura material identificable y funcionamiento autónomo, en la que se realizan prestaciones propias de los servicios sociales. Puede ser residencial o no residencial (Art. 52, Ley 12/2022). Existe edificio físico. Ejemplos: centros de servicios sociales, centros de mayores, centros de día, residencias.

**Implicaciones para VIDA:** El Ayuntamiento de Madrid gestiona más de 15 tipos de centros. Se modela con un único módulo configurable por tipo, no con módulos separados (principio 4.11).

**Decisiones de modelado:**
- Entidad `Centro` con `TipoCentro` configurable desde backoffice.
- Incluye coordenadas geográficas (principio 4.15) y capacidad de plazas.
- Referencia de código: `Modules\Centro\Models\Centro`, `Modules\Centro\Models\TipoCentro`.

---

### Servicio

**Definición de referencia:** Prestación de carácter general o especializado, consistente en la utilización de medios o acciones organizados técnica y funcionalmente. A diferencia del Centro, no precisa infraestructura material propia (Art. 53, Ley 12/2022). Ejemplos: Servicio de Ayuda a Domicilio, educación social en calle.

**Implicaciones para VIDA:** Centro y Servicio son entidades distintas. El Servicio de Ayuda a Domicilio no *es* un centro — se presta desde uno o sin uno. Esta distinción afecta al modelo de prestaciones externalizadas (principio 2.2).

**Decisiones de modelado:**
- Entidad `Servicio` relacionada opcionalmente con `Centro`. Un servicio puede prestarse en varios centros o sin centro fijo.

---

### Prestación

**Definición de referencia:** Instrumento de intervención social del Sistema Público de Servicios Sociales para la atención de necesidades de personas, familias o grupos (Orden 2372/2023, Art. 5). Pueden ser **prestaciones de servicio** (actuaciones profesionales) o **prestaciones económicas** (aportaciones dinerarias). La Cartera de Prestaciones del Área de Gobierno recoge actualmente 111 prestaciones.

**Implicaciones para VIDA:** El catálogo de prestaciones es la columna vertebral del sistema. Las derivaciones son prestaciones. Las prestaciones económicas que generan expedientes administrativos se gestionan en el sistema de expedientes externo, no en VIDA (principio 2.3).

**Decisiones de modelado:**
- Entidad `Prestacion` con código, nombre, descripción, categoría, tipo (servicio / económica), requisitos y baremo asociado si aplica.
- Referencia de código: `App\Models\Prestacion`, `Database\Seeders\PrestacionesSeeder` (catálogo inicial de Madrid).

---

### Plaza

**Definición de referencia:** Asignación personalizada de una prestación en un centro o servicio, sujeta a disponibilidad de vacantes. Puede ser residencial o no residencial. Su gestión implica: número de plazas por centro/servicio, mapeo de plazas en red de centros, estado (libre / ocupada / pre-asignada / baja temporal) y control de facturación en servicios indirectos.

**Implicaciones para VIDA:** La plaza es una entidad gestionable, no solo un atributo del centro. Tiene lógica propia de asignación y lista de espera.

**Decisiones de modelado:**
- Entidad `Plaza` con `estado` configurable, vinculada a `Centro` o `Servicio`, con historial de ocupación.
- La lista de espera para plazas es distinta de la lista de espera para primera cita (ver `ListaDeEspera`).

---

### Lista de Espera

**Definición de referencia:** Conjunto de ciudadanos pendientes de recibir una determinada prestación. Existen dos tipos: (a) referenciada a un estándar de calidad de una prestación garantizada (tiempo de espera superior al marcado en la carta de servicios, no imputable al ciudadano); (b) referenciada a una prestación ya prescrita o concedida, pendiente de asignación/disfrute.

**Implicaciones para VIDA:** Los dos tipos tienen lógica distinta. El primero mide el rendimiento del servicio; el segundo gestiona la asignación efectiva de una prestación concedida.

**Decisiones de modelado:**
- Entidad `ListaDeEspera` con `tipo` (calidad / asignación), vinculada a `Prestacion` y con posición, fecha de entrada y criterio de prioridad (configurable mediante `Baremo`).

---

### Solicitud

**Definición de referencia:** Documento formal que refleja la petición del ciudadano. Algunas prestaciones requieren solicitud formal obligatoria (regulada por ordenanzas); otras admiten solicitud verbal o se inician a instancia del profesional.

**Implicaciones para VIDA:** No todas las prestaciones siguen el mismo flujo de solicitud. Las prestaciones económicas con expediente administrativo inician su tramitación en el sistema externo de expedientes (principio 2.3).

**Decisiones de modelado:**
- La `Solicitud` es configurable por tipo de prestación: puede ser formal (documento), verbal (registrada por el profesional) o de oficio (iniciada por el profesional).

---

### Taller y Actividad

**Definición de referencia:** Un **taller** es una sesión de orientación y apoyo de tipo grupal, en un centro, con personas previamente citadas. Una **actividad** es una iniciativa puntual (lúdica, formativa) que puede ser presencial o telemática y no requiere citación previa de los participantes.

**Implicaciones para VIDA:** Son formas de prestación colectiva que requieren gestión de participantes y registro de asistencia. A diferencia de la entrevista, no se vinculan necesariamente a una Historia Social individual, aunque la participación puede registrarse en ella.

**Decisiones de modelado:**
- Entidades `Taller` y `Actividad` con gestión de participantes, asistencia y vinculación opcional a `HistoriaSocial`.

---

## 4. Unidades gestoras, orgánicas y organizativas

### Distrito

**Definición de referencia:** División territorial del municipio de Madrid. Son 21 distritos (Reglamento Orgánico 6/2021). Constituyen la unidad territorial de referencia para la prestación de servicios sociales de atención primaria: cada ciudadano tiene asignado un centro de servicios sociales según su distrito de residencia.

**Decisiones de modelado:**
- Entidad `Distrito` como tabla maestra configurable. Los 21 distritos de Madrid son los valores iniciales, pero el modelo debe ser adaptable a otros municipios (principio 4.15).
- El `Centro` y el `Ciudadano` (por domicilio) se vinculan a `Distrito`.

---

### Zona

**Definición de referencia:** Agrupación de unidades censales. Es una realidad dinámica: las unidades gestoras pueden reconfigurarla para distribuir la carga de trabajo.

**Implicaciones para VIDA:** La zona es el nivel de granularidad territorial por debajo del distrito. Permite asignar cargas de trabajo entre trabajadores sociales de un mismo centro.

**Decisiones de modelado:**
- Entidad `Zona` como agrupación configurable de unidades censales, vinculada a `Distrito` y a `Profesional` de referencia.

---

### Área de Gobierno

**Definición de referencia:** Unidad orgánica central que agrupa competencias temáticas. El Área de Gobierno de Políticas Sociales, Familia e Igualdad comprende: familia e infancia, servicios sociales, atención a mayores, inmigración, educación y juventud, promoción de la igualdad, violencia de género, diversidad, atención social de emergencia, SAMUR Social, voluntariado y cooperación al desarrollo (Acuerdo de 29 de junio de 2023).

**Implicaciones para VIDA:** El Área de Gobierno es la unidad de referencia para las prestaciones especializadas y los servicios centrales (SAMUR Social, centros de acogida). Su estructura es diferente a la territorial de los distritos.

**Decisiones de modelado:**
- Entidad `AreaDeGobierno` como tabla maestra. Los profesionales pueden tener dependencia orgánica de distrito o de Área de Gobierno.

---

## 5. Semántica del proyecto

### Módulo (funcional)

**Definición en VIDA:** Paquete de funcionalidad del sistema desde el punto de vista del negocio. Agrupa un conjunto coherente de entidades, flujos de trabajo y pantallas relacionados con un ámbito del dominio. No equivale a un `Module` de Laravel, aunque puede corresponderse con uno o varios de ellos.

**Módulos funcionales previstos:** Ciudadanía, Intervención (Historia Social, planes, apuntes), Recursos (centros, servicios, prestaciones, plazas), Agenda, Profesionales, Administración (backoffice, configuración, roles).

---

### Rol y Permiso

**Definición de referencia:** Un **rol** es un conjunto de permisos. Un profesional puede tener más de un rol. Existe un *rol 0* para el ciudadano con acceso a su propia Historia Social. Un **permiso** define una actividad concreta que puede realizar un usuario; se conceden asignando roles.

**Implicaciones para VIDA:** El sistema de roles y permisos es la base del control de acceso. Los colectivos especialmente protegidos añaden una capa adicional sobre el sistema de roles (principio 3.6).

**Decisiones de modelado:**
- Roles y permisos configurables desde backoffice (principio 4.15). Los permisos no están hardcodeados.
- El acceso a información de colectivos protegidos requiere un permiso específico configurable, independiente del rol general.

---

### Traza

**Definición de referencia:** Secuencia de acciones almacenadas en un repositorio (log) que puede ser consultada como histórico.

**Implicaciones para VIDA:** Hay dos niveles de traza: el log técnico de base de datos (principio 4.3) y la auditoría visible para el TSR (principio 3.5). Son complementarios.

---

### HSU (Historia Social Única)

**Definición de referencia:** Denominación oficial en el Decreto 51/2023 de la Comunidad de Madrid para la Historia Social del ciudadano. El código de identificación es el NI-HSU-CM.

**Implicaciones para VIDA:** El NI-HSU-CM es el campo de interoperabilidad con los sistemas de la Comunidad de Madrid. VIDA usa su propio identificador interno, pero debe almacenar y gestionar el NI-HSU-CM para la interoperabilidad.

---

### Interoperabilidad

**Definición de referencia:** Capacidad de los sistemas de información de compartir datos y posibilitar el intercambio de información. Se distinguen tres dimensiones: **organizativa** (colaboración entre entidades y procesos), **semántica** (datos comprensibles e interpretables automáticamente por distintos sistemas) y **técnica** (intercambio efectivo de datos). El **Esquema Nacional de Interoperabilidad (ENI)** y el **Esquema Nacional de Seguridad (ENS)** son los marcos normativos de referencia.

**Implicaciones para VIDA:** La interoperabilidad con la Comunidad de Madrid (HSU), con el gestor de expedientes municipal y con los proveedores externos (Excel) son los tres vectores principales. Ver principio 3.8.

---

*Documento elaborado en fase de diseño del proyecto. Versión inicial: marzo 2026.*
*Pendiente: añadir referencias a módulos, modelos y controladores del código a medida que se implementen.*
