# Principios de Diseño de VIDA 360
## Contexto, marco competencial y decisiones arquitectónicas fundamentales

> Este documento recoge los principios que deben guiar el desarrollo de VIDA 360. No es documentación técnica, sino el marco conceptual que explica *por qué* el sistema está diseñado como está. Debe leerse antes de tomar decisiones de arquitectura o implementación, y actualizarse cuando esas decisiones evolucionen.

---

## 1. Contexto organizativo

### 1.1 La Atención Social Primaria como puerta de entrada universal

El modelo organizativo de los servicios sociales municipales es análogo al sanitario. Toda persona tiene asignado un **centro de servicios sociales** y un **Trabajador Social de Referencia (TSR)** según su dirección postal. El TSR es el gestor global del caso: conoce la situación integral de la persona, coordina las intervenciones activas y decide las derivaciones a atención especializada.

La **Atención Social Primaria (ASP)** es la puerta de entrada universal al sistema. El primer contacto de una persona con los servicios sociales municipales pasa, en la inmensa mayoría de los casos, por ASP.

### 1.2 El Servicio de Información y Asesoramiento (SIA)

Antes de llegar al TSR, la primera recepción la realiza el **SIA**. El profesional del SIA atiende a la persona que llega con una demanda, evalúa su situación de forma preliminar y toma una de dos decisiones:

- **Informar y orientar**: si la demanda corresponde a prestaciones de otra administración o no requiere intervención municipal, proporciona la información necesaria y registra la interacción.
- **Derivar al TSR**: si la persona puede tener derecho a prestaciones municipales, programa una cita con el trabajador social de referencia que le corresponde por domicilio.

El Ayuntamiento de Madrid ha desarrollado un sistema RAG (*Retrieval-Augmented Generation*) que asiste al profesional del SIA en esta primera valoración, consultando la documentación del catálogo de prestaciones. Su uso es optativo y debe integrarse como herramienta de apoyo, no como sustituto del criterio profesional.

### 1.3 Atención Especializada

El TSR de ASP puede derivar a la persona a **atención especializada** cuando detecta necesidades que requieren intervención específica: dependencia, infancia y familia, inserción sociolaboral, salud mental, etc. El profesional especializado trabaja su ámbito de forma autónoma, pero la Historia Social de la persona es única y el TSR mantiene la visión global.

### 1.4 Puertas de entrada alternativas

Existen dos ámbitos con puerta de entrada propia, por razones de seguridad o por las características específicas de la población atendida:

**Violencia de Género (VG):** Dispone de circuito de acceso independiente para proteger a las víctimas. Una vez estabilizada la situación, se produce integración con ASP cuando procede, aunque el seguimiento especializado continúa en paralelo.

**Personas Sin Hogar (PSH):** También tiene puerta de entrada propia. A diferencia de VG, la atención continuada se mantiene predominantemente en el departamento de PSH dadas las características de esta población, aunque existe conexión con ASP.

En ambos casos, la Historia Social de la persona es única en el sistema. La puerta de entrada alternativa no implica un expediente separado, sino un flujo de acceso diferenciado.

---

## 2. Marco competencial

### 2.1 Tres tipos de interacción, no uno solo

VIDA distingue explícitamente entre tres situaciones competenciales, que implican flujos y registros distintos:

**Informar sobre prestaciones ajenas.** Cuando la demanda corresponde a prestaciones de otra administración (Comunidad Autónoma, Estado, Diputación), el profesional proporciona información y orientación. Se registra como prestación de tipo *información* (código 010101 del catálogo), con una observación sobre la información concreta prestada. No se abre Historia Social, pero queda traza de la interacción vinculada al ciudadano.

**Colaborar en tramitaciones compartidas.** Algunas prestaciones implican que el Ayuntamiento realiza ciertos pasos de la tramitación y otra administración realiza otros, habitualmente en el marco de un convenio de colaboración. Esto requiere un mecanismo de intercambio de información entre administraciones. En la práctica, cuando el otro sistema no dispone de API, este intercambio se realiza mediante ficheros estructurados (Excel). Ambos modos —API e importación/exportación de ficheros— son realidades permanentes del ecosistema administrativo, no soluciones provisionales.

**Gestionar íntegramente una prestación municipal.** Cuando la prestación es propia del Ayuntamiento, prestada con recursos propios o contratados, se abre una Historia Social y se establece un Plan de Intervención con sus seguimientos. Este es el núcleo funcional de VIDA.

### 2.2 Prestaciones externalizadas

Muchas prestaciones municipales se prestan a través de empresas o entidades contratadas, que disponen de sus propios sistemas de información. El intercambio de datos con estos proveedores se realiza habitualmente mediante ficheros periódicos (Excel mensual de personas atendidas, para control de facturación). VIDA debe ser capaz de importar estos ficheros, hacer *matching* con ciudadanos existentes en el sistema y registrar la actividad para completar la visión 360.

### 2.3 VIDA no es un gestor de expedientes administrativos

Las prestaciones económicas y otras con regulación específica generan **expedientes administrativos** con fases formales: solicitud, subsanación, aprobación o denegación, notificación, posibles recursos. Esa complejidad la gestiona el sistema de expedientes del ayuntamiento, no VIDA.

VIDA registra la prestación en la Historia Social y mantiene una interfaz con el gestor de expedientes para tres operaciones concretas: iniciar una solicitud, consultar el estado del expediente e incorporar la resolución a la Historia. El desarrollo de esta interfaz es posterior a la funcionalidad principal, pero el diseño debe anticipar estos puntos de integración desde el principio para no crear dependencias que lo compliquen después.

---

## 3. Principios de diseño

### 3.1 Identidad única del ciudadano

Toda persona atendida tiene un registro único en VIDA (`SocialUser`) que actúa como identificador canónico, independientemente de cuántos profesionales, departamentos o prestaciones estén involucrados. Todas las interacciones —Historias, informaciones prestadas, participación en actividades, prestaciones externalizadas— orbitan alrededor de esa identidad única.

La gestión de la identidad es un problema complejo que se aborda específicamente: una misma persona puede acceder al sistema en momentos distintos con pasaporte, NIE o DNI; puede cambiar legalmente de nombre, apellidos o sexo. El mecanismo de *matching* y deduplicación debe contemplar estas casuísticas sin crear duplicidades ni perder trazabilidad.

### 3.2 Historia Social vs. contacto informativo

El umbral para abrir una **Historia Social** es que exista una demanda que requiera valoración, plan o seguimiento municipal. Una consulta informativa no lo justifica.

Todo contacto con el sistema, incluso una simple consulta, queda registrado vinculado al ciudadano. Ese registro de contactos no es una Historia Social: es el historial de interacciones de la persona con los servicios sociales municipales, que en el futuro podrá exponerse a través de APIs hacia la carpeta ciudadana del ayuntamiento.

### 3.3 Carpeta ciudadana: integración diferida

VIDA gestiona su propio registro de interacciones del ciudadano de forma autónoma. No depende de sistemas externos para funcionar. En una fase posterior, expondrá APIs para publicar información y documentos en la carpeta ciudadana del ayuntamiento. El diseño interno debe anticipar esos puntos de publicación —generación de PDFs, estructura de datos exportable— sin crear dependencias prematuras.

### 3.4 El expediente pertenece al ciudadano, no al profesional

El expediente social no es "propiedad" del trabajador social que lo gestiona. Pertenece al ciudadano. Este principio tiene consecuencias concretas sobre el modelo de acceso:

- Cualquier trabajador social identificado en el sistema puede consultar una Historia Social.
- Solo el profesional asignado a una prestación concreta puede editarla.
- El acceso a información no está regulado por departamento, sino por necesidad asistencial. Un profesional de un departamento puede necesitar conocer si existe intervención activa en otro departamento con la misma persona.

La restricción de acceso como mecanismo de "propiedad" del profesional sobre su trabajo es un uso incorrecto del principio de privacidad y no debe estar soportado por el sistema.

### 3.5 Auditoría visible, no solo técnica

Todo acceso a un expediente queda registrado: quién ha accedido, cuándo y qué ha hecho (lectura o edición). Este registro no es solo un log técnico interno: el trabajador social de referencia puede consultarlo desde la ficha de la persona. Si detecta un acceso que no comprende, puede preguntar al profesional que lo realizó.

Este mecanismo convierte la auditoría en un instrumento de *accountability* profesional y de cultura organizativa, no solo de cumplimiento normativo. La transparencia interna es la mejor garantía de un uso responsable de la información.

### 3.6 Colectivos especialmente protegidos: configurables, no hardcodeados

Determinados colectivos requieren un nivel de protección adicional en el acceso a su información. Actualmente son las **mujeres víctimas de violencia de género** y los **menores**. El acceso a sus expedientes requiere un permiso especial.

El diseño debe implementar este mecanismo de forma **configurable**: una tabla de colectivos protegidos con sus niveles de acceso requeridos, de forma que añadir un nuevo colectivo sea una operación de configuración y no de desarrollo. El middleware de autorización consulta dinámicamente esta tabla. Cualquier implementación que hardcodee los colectivos protegidos debe ser considerada deuda técnica a corregir.

### 3.7 Modelo de planes de intervención

Una Historia Social puede tener varios **Planes de Intervención** activos simultáneamente, cada uno con su responsable y su ciclo de vida independiente:

**Plan de ASP (plan general):** Gestionado por el TSR. Tiene visión global de la situación e incluye todas las derivaciones activas a especializada. Es el mapa completo de la intervención con la persona.

**Plan de especializada (plan específico):** Gestionado por el profesional especializado. Autónomo en contenido y seguimiento, pero visible para el TSR. No editable por el TSR.

Las **derivaciones a especializada son prestaciones**. Se registran en el plan de ASP como una prestación más del catálogo. Esto crea automáticamente el vínculo trazable entre el plan general y el plan específico: el plan de especializada nace como consecuencia de una prestación de derivación en el plan de ASP. Consultando el plan de ASP se puede ver de un vistazo todo lo que está activo con una persona.

### 3.8 Interoperabilidad pragmática

El sistema soporta dos modos de intercambio con sistemas externos —otras administraciones, gestores de expedientes, proveedores de servicios externalizados:

- **API**: cuando el sistema externo está preparado para ello.
- **Importación/exportación de ficheros estructurados**: cuando no lo está.

Ambos modos son realidades permanentes, no soluciones provisionales. El diseño debe tratarlos como ciudadanos de primera clase, no como parches.

### 3.9 La IA asiste, nunca decide

Ningún componente de inteligencia artificial en VIDA puede tomar decisiones sobre personas. La IA puede analizar situaciones, clasificar demandas, sugerir prestaciones adecuadas, detectar patrones o estructurar información no estructurada, pero toda acción con consecuencias para el ciudadano requiere validación explícita de un profesional. El sistema debe hacer visible cuándo una recomendación o clasificación proviene de un componente de IA, para que el profesional pueda valorarla como tal y no asumirla acríticamente.

Este principio aplica a cualquier funcionalidad de IA presente o futura: el asistente del SIA, la estructuración de notas en fichas, la detección de alertas, la sugerencia de prestaciones en planes de intervención, o cualquier otro componente.

### 3.10 Análisis de sesgo obligatorio antes de cualquier implantación de IA

Toda funcionalidad que incorpore inteligencia artificial debe ir precedida de un **análisis de posibles sesgos**, documentado y revisable. Este análisis debe considerar específicamente el impacto sobre los colectivos atendidos: personas con bajo nivel de alfabetización, hablantes no nativos de español, personas mayores, personas con discapacidad, personas en situación de exclusión social.

Una tecnología que produce peores resultados para parte de la población atendida no puede implantarse aunque mejore los resultados medios. La mejora tecnológica no puede traducirse en peor atención para las personas más vulnerables, que son precisamente el público central de los servicios sociales.

### 3.11 Multicanalidad como derecho, no como ventaja

El ciudadano tiene derecho a elegir el canal de comunicación con los servicios sociales. El sistema debe aspirar a soportar múltiples canales —presencial, telefónico, mensajería instantánea, digital— sin que ninguno de ellos ofrezca mejor atención o tiempos de respuesta más rápidos a quienes tienen más competencias tecnológicas. La multicanalidad es una herramienta de equidad, no de eficiencia selectiva.

La apertura de canales digitales no puede convertirse en un carril preferente para ciudadanos con mayores capacidades tecnológicas. Cuando se incorpore un nuevo canal, debe acompañarse de vías de interacción equivalentes y accesibles para personas con menos competencias digitales.

### 3.12 Toda interacción forma parte de la Historia Social, independientemente del canal

Una llamada telefónica, una visita presencial o un mensaje de WhatsApp tienen el mismo valor asistencial que una interacción a través de una plataforma digital. El profesional es responsable de registrar en VIDA las interacciones que se producen por canales no integrados técnicamente. El sistema debe facilitar ese registro —minimizando la fricción para el profesional— y no ignorar lo que no puede capturar automáticamente.

La integración técnica de canales es incremental: las decisiones sobre cada canal (centralización, gestión de identidad, trazabilidad) deben tomarse antes de su implementación. Ningún canal puede implantarse si no garantiza que las interacciones quedan registradas en la Historia Social y que el acceso cumple con los principios de privacidad y auditoría establecidos en este documento.

---

## 4. Principios técnicos de desarrollo

### 4.1 API First

Todas las entidades del sistema deben exponer una API REST con operaciones CRUD completas. Los flujos de trabajo principales —apertura de Historia, creación de plan, registro de seguimiento, derivación— deben tener también endpoints API para ser lanzados y consultados programáticamente. El frontend Livewire y cualquier integración externa consumen la misma API, sin atajos internos que la bypaseen.

### 4.2 El pasado es inmutable

El sistema debe ser capaz de conocer el estado de cualquier entidad en cualquier momento del pasado: qué prestaciones recibía un ciudadano hace un año, qué profesional tenía asignado un centro, cuál era el contenido de un plan de intervención en una fecha concreta. Esto se implementa mediante **lectura histórica**, nunca mediante reversión. El pasado no se modifica: los registros históricos son inmutables una vez creados. Cualquier cambio genera un nuevo estado, no sobreescribe el anterior.

### 4.3 Todo logado

Todo acceso y operación sobre las tablas de gestión de servicios sociales queda registrado con: usuario, timestamp, operación realizada y resultado (éxito o fallo). Este log es la base técnica de la auditoría visible descrita en el principio 3.5. Las tablas maestras (catálogos, configuraciones de sistema) quedan excluidas de este requisito.

### 4.4 Español por defecto

El español es el idioma del proyecto en todos sus niveles:

- **Interfaz y datos**: todo lo que percibe el ciudadano o el profesional está en español.
- **Código fuente**: nombres de entidades, variables, métodos, clases, rutas, migraciones y comentarios se escriben en español. Se evitan mezclas de idiomas que dificulten la lectura.
- **Excepciones justificadas**: términos técnicos del framework o la industria sin traducción natural establecida (e.g., `middleware`, `seeder`, `trait`) pueden mantenerse en inglés.

### 4.5 Código comentado y documentado

Todo el código debe comentarse siguiendo los estándares de la industria, compatibles con **PHPDoc**:

- Cabecera de clase: propósito, autor, fecha, referencias relevantes.
- Cabecera de método o función: descripción, parámetros (`@param`), valor de retorno (`@return`), excepciones posibles (`@throws`).
- Comentarios inline para lógica no evidente: el código describe *qué* hace; los comentarios explican *por qué*.

Un código sin comentar no está terminado. Las revisiones deben incluir la calidad de la documentación como criterio.

### 4.6 Tests automatizados como parte del desarrollo

Los tests no son una fase posterior al desarrollo, son parte de él. Cada endpoint de API debe tener tests que verifiquen el comportamiento esperado en casos normales y en casos de error. Los flujos de trabajo principales deben tener tests de integración que los cubran de extremo a extremo.

Laravel ofrece soporte nativo para testing con PHPUnit y Pest. La estrategia concreta de testing —qué se testea, cómo se organiza, qué nivel de cobertura se persigue— se definirá antes de abordar cada módulo funcional, de forma que los tests sean útiles y mantenibles, no una carga burocrática.

### 4.7 Gestión de errores y formato de respuestas API consistente

Todos los endpoints de la API deben devolver respuestas con estructura consistente: código HTTP correcto, cuerpo con formato uniforme, mensaje de error descriptivo cuando corresponda y referencia al recurso implicado. No hay margen para que cada endpoint invente su propio formato de respuesta. Este estándar debe definirse una vez y aplicarse mediante un mecanismo centralizado (handler de excepciones, response macro o similar).

### 4.8 Separación explícita de configuración por entorno

Debe estar documentado y acordado qué tipo de configuración va en cada capa:

- **`.env`**: credenciales, conexiones, valores que varían entre entornos de despliegue.
- **Base de datos** (tablas de configuración): parámetros que varían entre ayuntamientos o que deben ser modificables sin redespliegue (colectivos protegidos, catálogos, umbrales).
- **Código fuente**: constantes que no varían entre instalaciones.

Ningún valor que pueda variar entre ayuntamientos debe estar hardcodeado en el código fuente.

### 4.9 Preferir paquetes consolidados sobre desarrollo propio

El valor diferencial de VIDA está en el modelo de dominio: cómo representa la intervención social, cómo gestiona la Historia, cómo conecta prestaciones con ciudadanos. Todo lo que no es lógica específica de servicios sociales debe resolverse preferentemente con paquetes consolidados del ecosistema Laravel, evitando reinventar soluciones a problemas genéricos ya resueltos.

**Usar paquetes para** funcionalidades transversales: autenticación, autorización, auditoría, versionado histórico, generación de documentos, testing, importación/exportación de ficheros.

**Desarrollar código propio para** la lógica específica del dominio de servicios sociales, donde ningún paquete genérico puede capturar adecuadamente los requisitos: el modelo de Historia Social, el ciclo de intervención, el matching de identidades, los colectivos protegidos configurables.

Antes de adoptar un paquete, evaluar: mantenimiento activo en el repositorio, compatibilidad con la versión de Laravel en uso, licencia compatible con Apache 2.0, y coste de sustitución si el paquete queda abandonado. Un paquete que deja de mantenerse puede convertirse en un problema de seguridad o en un bloqueo para actualizar el framework.

### 4.10 Protección frente al acceso privilegiado no autorizado

El riesgo de acceso indebido a datos sensibles no proviene solo de usuarios externos, sino también de usuarios técnicos internos con privilegios elevados: administradores de base de datos, desarrolladores, personal de operaciones. En un sistema que gestiona datos de colectivos especialmente vulnerables —donde la exposición de una dirección puede tener consecuencias físicas para una persona— este riesgo debe tratarse explícitamente.

Ninguna medida técnica elimina este riesgo por completo si alguien con acceso privilegiado tiene motivación para abusar de él. El objetivo es reducir al mínimo la superficie de ataque, hacer el abuso difícil de ejecutar y prácticamente imposible de ocultar.

Las medidas que se combinan para conseguirlo son:

**Cifrado en reposo para datos sensibles.** Los campos más críticos —datos de contacto y localización de colectivos especialmente protegidos— se cifran a nivel de columna en PostgreSQL mediante `pgcrypto`. Las claves de cifrado no están disponibles en entornos de desarrollo ni accesibles para administradores de base de datos. El descifrado ocurre exclusivamente en la capa de aplicación, donde se aplican las reglas de autorización. Un acceso directo a la base de datos que bypasee la API devuelve texto ilegible.

**Separación estricta de entornos.** Los desarrolladores no tienen acceso directo a la base de datos de producción. Esta restricción es técnica, no solo una política: las credenciales de producción son inaccesibles para el equipo de desarrollo. El entorno de desarrollo trabaja siempre con datos anonimizados o sintéticos, nunca con datos reales de ciudadanos.

**Auditoría de base de datos independiente de la aplicación.** PostgreSQL registra qué usuario de base de datos ejecutó qué consulta y cuándo, con independencia de si el acceso se produjo a través de la API o directamente. Este log es inaccesible para los propios desarrolladores y constituye una segunda capa de trazabilidad que complementa la auditoría de aplicación descrita en el principio 3.5.

**Mínimo privilegio y doble autorización para accesos a producción.** El número de personas con acceso privilegiado a la base de datos de producción debe ser el mínimo operativamente necesario. Todo acceso a producción sigue un procedimiento documentado que requiere doble autorización y queda registrado. La existencia de estos registros, y el hecho de que los implicados saben que existen, tiene un efecto disuasorio que complementa las medidas técnicas.

### 4.11 Abstracción y configuración frente a desarrollos ad hoc

Cuando varias entidades o flujos comparten una estructura común con variantes, se desarrolla un módulo general configurable desde el backoffice en lugar de módulos separados para cada caso. La variación se modela como configuración, no como código.

Ejemplos concretos que aplican este principio:

**Centros:** El Ayuntamiento de Madrid gestiona más de 15 tipos de centros con características distintas — centros de servicios sociales, de mayores, de jóvenes, de atención a la mujer, de día, residenciales. Se desarrolla un único módulo de centros con un sistema de tipos configurables que permite modelar cada variante: atributos específicos, capacidades, servicios asociados, requisitos de personal. Añadir un nuevo tipo de centro es una operación de configuración, no de desarrollo.

**Planes de Intervención:** El PISO de ASP y los planes de especializada comparten la misma estructura fundamental. Las variantes — campos específicos de recogida de información, prestaciones disponibles, flujo de aprobación — se modelan como configuración del tipo de plan. Se desarrolla un único módulo de planes de intervención configurable por tipo.

Este principio aplica de forma general a cualquier entidad del sistema donde se detecte el patrón "varios casos con estructura común y variantes específicas". La regla práctica es: antes de crear un nuevo módulo, verificar si existe uno existente que pueda extenderse mediante configuración.

El límite de este principio es la complejidad de configuración: un módulo genérico no debe volverse tan complejo de configurar que en la práctica resulte inusable. Cuando la variación entre casos es tan profunda que la configuración no la puede absorber sin perder coherencia, el desarrollo específico es la decisión correcta.

### 4.12 Ningún valor de negocio hardcodeado en el código

Los nombres de entidades, valores de parámetros, reglas de negocio, umbrales, categorías y cualquier otro dato que pueda cambiar a lo largo de la vida del sistema deben residir en la base de datos o en configuración, nunca en el código fuente. Un cambio en un valor de negocio no debe requerir la intervención de un desarrollador.

Este principio complementa el 4.11 —que opera a nivel de estructura de módulos— y lo extiende al nivel de los datos concretos. Ejemplos de lo que no debe estar hardcodeado: los estados posibles de una Historia Social, los tipos de documentos requeridos en una prestación, los umbrales de alerta en un seguimiento, las categorías del catálogo de prestaciones, los roles de usuario y sus permisos.

El criterio práctico es: si un responsable funcional del ayuntamiento necesita cambiar este valor, debe poder hacerlo desde el backoffice sin llamar a nadie.

### 4.13 VIDA no es un sistema de analítica, pero debe facilitarla

La analítica de datos —informes estadísticos, cuadros de mando estratégicos, análisis de tendencias— se realiza fuera de VIDA, en las herramientas y procesos que cada organización tenga para ello: extracción de datos, construcción de datasets, volcado a un datalake, generación de informes. No es objeto de VIDA implementar estas funciones.

Sí es objeto de VIDA **hacer que esa analítica sea posible y de calidad**. Para ello, VIDA debe mantener un catálogo de entidades y atributos que documente qué datos están disponibles, cómo están estructurados y qué significan. Este catálogo es la interfaz entre el sistema de gestión y los sistemas de analítica, y debe tratarse como documentación de primer nivel, no como un añadido posterior.

### 4.14 La variable sexo se recoge siempre que sea pertinente

Todas las entidades que representen personas deben incluir la variable sexo cuando sea relevante para la gestión o para el análisis posterior. La desagregación por sexo es un criterio fundamental para los informes estratégicos de servicios sociales —detección de desigualdades, análisis de acceso a prestaciones, evaluación de impacto— y su ausencia en el dato de origen hace imposible recuperarla después.

La variable sexo se recoge conforme a la normativa vigente, respetando las posibilidades de cambio legal de sexo registral contempladas en el principio 3.1.

### 4.15 Geoposicionamiento de todas las entidades con expresión física

Toda entidad que tenga una localización física —centros de servicios sociales, domicilios de ciudadanos, zonas de actuación de equipos— debe incluir coordenadas geográficas desde el momento de su creación. La ciudad es el espacio de trabajo de los servicios sociales municipales, y disponer de esta dimensión espacial desde el principio es crítico para la toma de decisiones estratégicas: distribución territorial de recursos, detección de zonas de alta demanda, planificación de nuevos centros, análisis de accesibilidad.

Añadir el geoposicionamiento a posteriori sobre datos ya existentes es costoso e inexacto. Es un dato que se recoge en el momento de alta de la entidad o no se recoge bien nunca.

---

## 5. Decisiones pendientes de desarrollo

Las siguientes áreas están identificadas como complejas y se abordarán en fases posteriores, una vez consolidada la funcionalidad principal:

- **Matching y deduplicación de identidades**: estrategia para gestionar cambios de documento identificativo, cambios de nombre/sexo y posibles duplicidades.
- **Interfaz con el gestor de expedientes administrativos**: integración para iniciar solicitudes, consultar estados e incorporar resoluciones a la Historia Social.
- **Integración con la carpeta ciudadana del ayuntamiento**: exposición de APIs para publicar información y documentos.
- **Integración con el RAG del SIA**: incorporación de la herramienta de asistencia al profesional del SIA como capa opcional de apoyo.
- **Importación de ficheros de proveedores externos**: módulo de importación con matching automático y resolución manual de conflictos.
- **Integración técnica de canales de comunicación**: definición de arquitectura para cada canal (mensajería, notificaciones, acceso ciudadano), garantizando trazabilidad y cumplimiento de privacidad antes de su implementación.
- **Análisis de sesgo para componentes de IA**: metodología y criterios específicos para evaluar el impacto de cada componente de IA sobre los distintos colectivos atendidos.

---

*Documento elaborado en fase de diseño del proyecto. Versión inicial: marzo 2026.*
