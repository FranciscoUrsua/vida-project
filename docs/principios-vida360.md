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

## 4. Decisiones pendientes de desarrollo

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
