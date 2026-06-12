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

Las prestaciones económicas y algunas prestaciones de servicio con mayor complejidad administrativa se gestionan a través de un sistema de expedientes administrativos propio del Ayuntamiento. VIDA interactúa con ese sistema para tres operaciones concretas: iniciar una solicitud, consultar el estado de tramitación e incorporar la resolución a la Historia Social. La tramitación interna del expediente es responsabilidad del gestor externo, no de VIDA.

---

## 3. Principios de diseño

### 3.1 Sin valores de negocio hardcodeados

Los catálogos, estructuras de valoración, plantillas de planes y clasificaciones son configurables desde backoffice sin necesidad de desarrollo. Este principio no aplica a valores que el código necesita conocer para tomar decisiones de lógica de negocio — esos van como enums PHP (ver principio 3.10).

### 3.2 Diferimiento explícito sobre ambigüedad

Las decisiones no maduras se documentan explícitamente como diferidas con su justificación, en lugar de resolverlas prematuramente o ignorarlas. Las decisiones diferidas son ciudadanas de primera clase en la documentación: tienen su sección, su justificación y su condición de resolución.

### 3.3 Separación de dimensiones en permisos

El rol (¿qué puede hacer?) y la UO (¿dónde puede hacerlo?) son dimensiones independientes que se evalúan secuencialmente. Un mismo profesional puede tener roles distintos en distintas UO. Esta separación estructural no debe colapsarse en una única dimensión.

### 3.4 El expediente pertenece al ciudadano, no al profesional

El expediente social no es "propiedad" del trabajador social que lo gestiona. Pertenece al ciudadano. Este principio tiene consecuencias concretas sobre el modelo de acceso:

- Cualquier trabajador social identificado en el sistema puede consultar una Historia Social.
- Solo el profesional asignado a una prestación concreta puede editarla.
- El acceso a información no está regulado por departamento, sino por necesidad asistencial. Un profesional de un departamento puede necesitar conocer si existe intervención activa en otro departamento con la misma persona.

La restricción de acceso como mecanismo de "propiedad" del profesional sobre su trabajo es un uso incorrecto del principio de privacidad y no debe estar soportado por el sistema.

### 3.5 Seguridad por responsabilidad, no por obstáculos

El acceso a la información del ciudadano no se protege principalmente mediante barreras de acceso, sino mediante trazabilidad y responsabilidad. El principio rector es: **acceso amplio, auditoría total, visibilidad de la traza**.

**Acceso:** cualquier profesional con rol `intervencion` puede leer cualquier Historia Social, independientemente de su UO. La edición sigue restringida por el binomio rol+UO. El acceso de lectura no requiere justificación previa.

**Trazabilidad:** todas las operaciones sobre datos de ciudadanos quedan registradas, incluidas las de lectura. No existe acceso anónimo ni acceso sin huella en el sistema.

**Visibilidad de la traza con dos niveles:**
- El **TSR responsable** ve proactivamente las operaciones recientes sobre las historias de las que es responsable. El sistema se lo muestra sin que tenga que buscarlo, para que pueda detectar accesos no justificados.
- El **supervisor de UO** puede consultar, bajo demanda, todas las operaciones realizadas sobre ciudadanos relacionados con su UO.

Este modelo convierte la auditoría en un instrumento de *accountability* profesional y cultura organizativa, no solo de cumplimiento normativo. La transparencia interna es la mejor garantía de un uso responsable de la información.

**Excepción — colectivos especialmente protegidos:** este principio no aplica para los colectivos definidos en el principio 3.6. Para ellos, la responsabilidad a posteriori no es suficiente dado el nivel de sensibilidad de los datos: se requiere autorización previa incluso para accesos de lectura.

### 3.6 Auditoría visible, no solo técnica

Todo acceso a un expediente queda registrado: quién ha accedido, cuándo y qué ha hecho (lectura o edición). Este registro no es solo un log técnico interno: el trabajador social de referencia puede consultarlo desde la ficha de la persona. Si detecta un acceso que no comprende, puede preguntar al profesional que lo realizó.

Este mecanismo convierte la auditoría en un instrumento de *accountability* profesional y de cultura organizativa, no solo de cumplimiento normativo. La transparencia interna es la mejor garantía de un uso responsable de la información.

### 3.7 Colectivos especialmente protegidos: configurables, no hardcodeados

Determinados colectivos requieren un nivel de protección adicional en el acceso a su información. Actualmente son las **mujeres víctimas de violencia de género** y los **menores**. El acceso a sus expedientes requiere autorización previa explícita, incluso para operaciones de solo lectura.

El diseño debe implementar este mecanismo de forma **configurable**: una tabla de colectivos protegidos con sus niveles de acceso requeridos, de forma que añadir un nuevo colectivo sea una operación de configuración y no de desarrollo. El middleware de autorización consulta dinámicamente esta tabla. Cualquier implementación que hardcodee los colectivos protegidos debe ser considerada deuda técnica a corregir.

### 3.8 Modelo de planes de intervención

Una Historia Social puede tener varios **Planes de Intervención** activos simultáneamente, cada uno con su responsable y su ciclo de vida independiente:

**Plan de ASP (plan general):** Gestionado por el TSR. Tiene visión global de la situación e incluye todas las derivaciones activas a especializada. Es el mapa completo de la intervención con la persona.

**Plan de especializada (plan específico):** Gestionado por el profesional especializado. Autónomo en contenido y seguimiento, pero visible para el TSR. No editable por el TSR.

Las **derivaciones a especializada son prestaciones**. Se registran en el plan de ASP como una prestación más del catálogo. Esto crea automáticamente el vínculo trazable entre el plan general y el plan específico: el plan de especializada nace como consecuencia de una prestación de derivación en el plan de ASP. Consultando el plan de ASP se puede ver de un vistazo todo lo que está activo con una persona.

### 3.9 Interoperabilidad pragmática

El sistema soporta dos modos de intercambio con sistemas externos —otras administraciones, gestores de expedientes, proveedores de servicios externalizados:

- **API**: cuando el sistema externo está preparado para ello.
- **Importación/exportación de ficheros estructurados**: cuando no lo está.

Ambos modos son realidades permanentes, no soluciones provisionales. El diseño debe tratarlos como ciudadanos de primera clase, no como parches.

### 3.10 La IA asiste, nunca decide

Ningún componente de inteligencia artificial en VIDA puede tomar decisiones sobre personas. La IA puede analizar situaciones, clasificar demandas, sugerir prestaciones adecuadas, detectar patrones o estructurar información no estructurada, pero toda acción con consecuencias para el ciudadano requiere validación explícita de un profesional. El sistema debe hacer visible cuándo una recomendación o clasificación proviene de un componente de IA, para que el profesional pueda valorarla como tal y no asumirla acríticamente.

Este principio aplica a cualquier funcionalidad de IA presente o futura: el asistente del SIA, la estructuración de notas en fichas, la detección de alertas, la sugerencia de prestaciones en planes de intervención, o cualquier otro componente.

### 3.11 Análisis de sesgo obligatorio antes de cualquier implantación de IA

Toda funcionalidad que incorpore inteligencia artificial debe ir precedida de un **análisis de posibles sesgos**, documentado y revisable. Este análisis debe considerar específicamente el impacto sobre los colectivos atendidos: personas con bajo nivel de alfabetización, hablantes no nativos de español, personas mayores, personas con discapacidad, personas en situación de exclusión social.

Una tecnología que produce peores resultados para parte de la población atendida no puede implantarse aunque mejore los resultados medios. La mejora tecnológica no puede traducirse en peor atención para las personas más vulnerables, que son precisamente el público central de los servicios sociales.

### 3.12 Multicanalidad como derecho, no como ventaja

El ciudadano tiene derecho a elegir el canal de comunicación con los servicios sociales. El sistema debe aspirar a soportar múltiples canales —presencial, telefónico, mensajería instantánea, digital— sin que ninguno de ellos ofrezca mejor atención o tiempos de respuesta más rápidos a quienes tienen más competencias tecnológicas. La multicanalidad es una herramienta de equidad, no de eficiencia selectiva.

La apertura de canales digitales no puede convertirse en un carril preferente para ciudadanos con mayores capacidades tecnológicas. Cuando se incorpore un nuevo canal, debe acompañarse de vías de interacción equivalentes y accesibles para personas con menos competencias digitales.

### 3.13 Toda interacción forma parte de la Historia Social, independientemente del canal

Una llamada telefónica, una visita presencial o un mensaje de WhatsApp tienen el mismo valor asistencial que una interacción a través de una plataforma digital. El profesional es responsable de registrar en VIDA las interacciones que se producen por canales no integrados técnicamente. El sistema debe facilitar ese registro —minimizando la fricción para el profesional— y no ignorar lo que no puede capturar automáticamente.

La integración técnica de canales es incremental: las decisiones sobre cada canal (centralización, gestión de identidad, trazabilidad) deben tomarse antes de su implementación. Ningún canal puede implantarse si no garantiza que las interacciones quedan registradas en la Historia Social y que el acceso cumple con los principios de privacidad y auditoría establecidos en este documento.

### 3.14 Separación entre indicadores operativos y analítica de negocio

Los dashboards del sistema (backoffice y front operativo) pueden mostrar indicadores de estado calculados directamente desde la base de datos del sistema mediante consultas simples. Esta práctica es legítima y no constituye una duplicación de la capa analítica.

El criterio que separa ambos dominios es funcional, no técnico:

**Va en el sistema operativo** si el dato responde a una pregunta que requiere una acción inmediata dentro de la propia aplicación: reasignar un caso, aprobar un rol, actuar sobre una lista de espera. El indicador es un acceso rápido, no un fin en sí mismo; debe tener siempre un destino al que lleva dentro de VIDA.

**Va en Power BI / datalake** si el dato responde a una pregunta de planificación, evaluación o rendición de cuentas: evolución temporal, comparativas entre UOs, tiempo medio de espera, perfiles de demanda. Estas preguntas no desencadenan ninguna acción dentro de la aplicación; su destino natural es una reunión, un informe o una decisión de política.

**Test de comprobación**: cuando el usuario ve el número, ¿hay algo en VIDA en lo que pueda hacer clic a continuación? Si sí, el indicador pertenece al sistema. Si la respuesta natural es "voy a comentarlo en el comité" o "voy a exportar esto", pertenece a Power BI.

Este principio protege el backlog: la petición de añadir un indicador al dashboard se evalúa siempre con este criterio, no con criterios de complejidad técnica.

---

## 4. Principios técnicos

### 4.1 Colectivos con necesidades específicas de modelado

**PSH:** las personas sin hogar no tienen domicilio fijo. El sistema permite registro sin domicilio y asignación por criterios alternativos (coordenadas del lugar habitual de pernocta, zona de intervención del equipo de calle). Es una excepción estructural al modelo de ciudadano, no un subtipo: el mismo modelo admite ambas situaciones mediante campos opcionales y nivel de identificación configurable.

**VVG:** las víctimas de violencia de género tienen circuito de acceso independiente. La consulta al padrón **no se lanza** para este colectivo (no basta con ignorar la respuesta — la consulta misma no debe realizarse para no dejar traza en los logs del padrón). El domicilio registrado en VIDA puede diferir intencionadamente del padrón. La Historia Social es única; el circuito diferenciado afecta al flujo de acceso, no a la estructura del expediente.

### 4.2 Sin valores de negocio hardcodeados (técnico)

Complementa el principio 3.1 con la distinción técnica concreta: usar **enum PHP** cuando el código toma decisiones basándose en el valor (`match`, `if`). Usar **`catalogos_sistema`** cuando el valor es puramente descriptivo o clasificatorio y el código no lo referencia directamente. Los valores de `catalogos_sistema` **nunca** pueden referenciarse por nombre en lógica de negocio — si eso ocurre, el valor debe convertirse en enum.

### 4.3 Pasado inmutable

Ningún dato histórico se sobrescribe. Los cambios generan nuevas versiones; los errores se corrigen con trazabilidad explícita (campo `tipo_actualizacion`: `modificacion` vs `correccion`). En cualquier momento debe ser posible reconstruir el estado de cualquier entidad en una fecha pasada.

### 4.4 Todo logado

Toda operación sobre datos de ciudadanos queda registrada con usuario, timestamp, operación y resultado. Esto incluye operaciones de lectura. El log técnico (`audits`) es la fuente de verdad para la auditoría; la traza visible para el TSR y el supervisor es una proyección de ese log orientada al uso profesional.

### 4.5 API First

Todas las entidades del sistema disponen de una API REST completa. La API no es solo para el frontend: es el mecanismo por el que sistemas externos autorizados consultan o actúan sobre VIDA.

La API se estructura en cuatro facetas con contratos diferenciados por audiencia: operacional (proveedores, otras administraciones, sistemas municipales), analítica (datalake, inteligencia de negocio), pública (portal de datos abiertos) y ciudadano (carpeta social ciudadana).

La autorización para sistemas externos opera en dos capas independientes: sistema cliente (OAuth2 client credentials con scopes) y usuario actuante (token que identifica al profesional que realiza la acción, con su rol). Para usuarios externos al Ayuntamiento, el sistema cliente declara el rol del usuario actuante; los scopes del cliente acotan qué roles puede declarar. Sin la capa de usuario actuante, un sistema externo podría actuar sin trazabilidad real.

El compromiso de compatibilidad hacia atrás de la v1 es de mínimo 10 años. Una nueva versión de la API solo se crea si existe una nueva versión de VIDA que la justifique.
Ver docs/api.md para el diseño completo.

### 4.6 Adaptador como patrón por defecto para integraciones

Toda integración con sistemas externos se implementa mediante el patrón adaptador con mock activo por defecto. Ningún módulo funcional conoce la implementación concreta de una integración — solo la interfaz. Cambiar de mock a real es una operación de configuración, no de desarrollo.

### 4.7 Español por defecto

Todo el código, comentarios, nombres de entidades, mensajes de error y documentación están en español. Las excepciones son los nombres de librerías y frameworks externos, y los términos técnicos sin traducción establecida.

### 4.8 Código comentado

PHPDoc obligatorio en todas las cabeceras de clase y método público: descripción, `@param`, `@return`, `@throws` donde aplique. Los comentarios explican el *por qué*, no el *qué*.

### 4.9 Tests automatizados como parte del desarrollo

Los tests no son una fase posterior. Cada módulo tiene su estrategia de testing definida antes de la implementación. Las Policies de autorización tienen cobertura de test obligatoria.

### 4.10 Cifrado en aplicación para datos sensibles

Los datos del ciudadano se cifran en la capa de aplicación antes de persistirse. Las claves residen en el `.env` o en un gestor de secretos externo, nunca en la base de datos. Un acceso directo a la BD sin las claves de aplicación devuelve texto ilegible. Los campos cifrados no son buscables directamente: las búsquedas operan sobre hashes deterministas almacenados en columnas auxiliares.

### 4.11 Abstracción y configuración sobre proliferación de módulos

Un módulo genérico configurable es preferible a múltiples módulos específicos. El módulo de centros gestiona los 15+ tipos de centros municipales mediante configuración, no mediante código separado por tipo. El módulo de planes de intervención gestiona tanto el plan de ASP como los planes de especializada mediante configuración del tipo de plan.

### 4.12 Filament para configuración, Livewire para operación

**Filament** gestiona la capa de configuración y backoffice: catálogos, plantillas, parámetros del sistema, usuarios y permisos. **Livewire** gestiona las capas operativas: el trabajo diario de los profesionales con ciudadanos, planes, apuntes y agenda. Esta separación es estructural y no debe mezclarse.

La separación funcional no implica dos sistemas visuales distintos. Filament y Livewire deben compartir el mismo lenguaje de interfaz, los mismos tokens de diseño y los mismos criterios de interacción. La diferencia entre ambas superficies debe ser de propósito, no de identidad visual ni de calidad de implementación.

### 4.13 Variable sexo en todas las entidades personales

El campo `sexo` se recoge en todas las entidades que representen personas físicas, desde el momento de su alta en el sistema.

### 4.14 Geoposicionamiento en todas las entidades con expresión física

Las coordenadas geográficas (latitud, longitud) se incluyen en todas las entidades que tienen expresión física: ciudadanos (domicilio), unidades de convivencia, centros, servicios. La geocodificación a partir de dirección postal se realiza mediante el adaptador `GeocodificacionInterface`, con mock activo por defecto.

### 4.15 Preferir paquetes consolidados para funcionalidades transversales

Para funcionalidades transversales (roles/permisos, jerarquías, adjuntos, auditoría), se prefieren paquetes consolidados del ecosistema Laravel sobre implementaciones propias. La lógica de dominio específica de VIDA sí se implementa en código propio.

### 4.16 La IA propone, el equipo valida

Las herramientas de IA (Claude CLI, etc.) pueden generar código, tests y documentación, pero cualquier decisión con consecuencias —arquitectónicas, de modelado, de comportamiento— debe ser visible y revisable por el equipo. Ninguna sesión de generación de código cierra sin entrada en el CHANGELOG.md. Las instrucciones enviadas a la IA se conservan en docs/instrucciones-cli/ para que la cadena instrucción → código generado → cambios registrados sea reconstruible en cualquier momento. Ver decisiones-tecnicas.md, sección 6, para los mecanismos concretos.

### 4.17 Anonimización como capacidad transversal

La anonimización y seudonimización no son funcionalidades de un módulo concreto: son una capacidad del sistema que se aplica en cualquier contexto donde el acceso a datos personales completos no sea necesario ni apropiado. Esto incluye la supervisión interna, la extracción analítica, la publicación en portales de datos abiertos y cualquier otro escenario de acceso restringido.

El sistema define tres niveles estándar: seudonimización (reversible, para contextos internos), generalización (irreversible, para analítica interna) y k-anonimato (para datos públicos). La elección del nivel y la configuración concreta de cada campo se gestionan mediante perfiles configurables desde el backoffice, sin necesidad de desarrollo.

La anonimización es parte del cumplimiento del RGPD por diseño (privacy by design): ofrecer el nivel mínimo de datos necesario para cada finalidad no es una restricción, es un principio de diseño. Ver docs/anonimizacion.md.

### 4.18 Sistema unificado de frontend

VIDA utiliza un único sistema de frontend basado en **Tailwind CSS, tokens VIDA y componentes propios reutilizables**. Este sistema aplica tanto a Filament como a las pantallas operativas Livewire.

**Bootstrap, Foundation u otros frameworks visuales generalistas no son la base del producto.** No deben incorporarse en nuevas pantallas ni cargarse por CDN en layouts de aplicación. Cualquier dependencia de este tipo heredada debe considerarse deuda técnica a retirar durante la consolidación del frontend.

**Filament** debe apoyarse en su propio sistema de componentes y en un tema VIDA específico. La personalización de Filament se hará preferentemente mediante sus APIs de configuración, temas y componentes nativos. Los overrides directos sobre clases internas de Filament (`.fi-*`) deben limitarse a ajustes necesarios, estar centralizados en el tema y evitarse como mecanismo habitual de diseño.

**Livewire** debe construir la interfaz operativa con componentes Blade/Livewire reutilizables basados en Tailwind y tokens VIDA: botones, campos de formulario, selectores, badges, paneles, tablas, navegación, modales, estados vacíos y mensajes de validación. Las vistas Livewire no deben depender de clases Bootstrap (`btn`, `row`, `col-*`, `form-control`, etc.) ni de estilos inline salvo para valores dinámicos inevitables.

La aplicación es **desktop-first**: el uso mayoritario se produce en PC y la interfaz debe priorizar densidad, escaneabilidad y eficiencia para trabajo profesional continuado. Esto no exime de soporte responsive: en tablet los layouts deben conservar funcionalidad completa con reorganización razonable, y en móvil deben permitir consulta y operaciones básicas sin roturas visuales ni pérdida de accesibilidad.

El sistema de iconos debe ser único en cada superficie. No se deben mezclar familias de iconos de forma arbitraria. Si se usa Lucide o Blade Icons en Livewire, debe mantenerse esa decisión de forma consistente; Bootstrap Icons no debe introducirse como dependencia paralela salvo decisión técnica documentada.

Regla de implementación: **en Livewire no se usan Bootstrap ni estilos inline estructurales; la UI se construye con componentes VIDA basados en Tailwind. Filament usa su tema VIDA y sus componentes nativos.**

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

*Documento elaborado en fase de diseño del proyecto. Versión inicial: marzo 2026. Actualizado: mayo 2026.*
