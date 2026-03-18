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

---

## 3. Principios de diseño del sistema

### 3.1 Sin valores de negocio hardcodeados

Ningún valor de dominio con posibilidad de evolución debe estar hardcodeado en el código fuente ni en migraciones de base de datos como constante inmutable. Los catálogos, estructuras de valoración, plantillas de planes y clasificaciones deben ser configurables desde backoffice, de forma que la evolución metodológica no requiera trabajo de desarrollo.

Este principio no implica que todo sea configurable: los valores que el propio código necesita conocer para tomar decisiones de lógica de negocio pueden y deben modelarse como enums (ver principio 3.10). El principio aplica a los valores puramente descriptivos o clasificatorios.

### 3.2 Diferimiento explícito sobre ambigüedad

Cuando una decisión de diseño no está madura, se documenta explícitamente como diferida con su justificación, en lugar de resolverla prematuramente o dejarla abierta. Las decisiones diferidas son ciudadanas de primera clase en la documentación.

### 3.3 Separación de dimensiones en permisos

El rol (qué puede hacer un usuario) y la Unidad Organizativa (dónde puede hacerlo) son dimensiones independientes que se evalúan secuencialmente. Un mismo profesional puede tener roles distintos en distintas UO. Esta separación es estructural y no debe colapsarse en una única tabla de asignación.

### 3.4 Evitar enums para valores inestables

Los enums de base de datos se reservan para valores con alta estabilidad estructural. Para clasificaciones con más de dos o tres valores o con posibilidad razonable de evolución, se prefieren catálogos en tabla. Ver principio 3.10 para la distinción completa.

### 3.5 Patrones transversales sobre soluciones por entidad

Los problemas que se repiten en múltiples módulos (versionado histórico, permisos, auditoría) se resuelven con un patrón único aplicado de forma consistente, no módulo a módulo. El patrón de versionado polimórfico mediante snapshots JSON es el ejemplo canónico: se define una vez y se aplica a todas las entidades que necesitan trazabilidad histórica.

### 3.6 Adaptador como patrón por defecto para integraciones

Toda integración con sistemas externos (otras administraciones, proveedores, registros autonómicos) se implementa mediante el patrón adaptador, con un adaptador mock activo por defecto. Esto aísla las dependencias externas y permite desarrollo y pruebas sin conexiones reales.

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

### 3.10 Enums para lógica, catálogos configurables para clasificación

Este principio establece cómo modelar los conjuntos de valores cerrados en función de su naturaleza real.

**Usar enum PHP/migración** cuando:
- El código necesita conocer el valor para tomar una decisión de lógica de negocio (`match`, `if`, filtro con comportamiento diferenciado).
- El conjunto de valores es estructuralmente estable (cambiar uno implica cambiar lógica de código).
- El número de valores es pequeño y semánticamente claro.

Ejemplos en VIDA: `tipo_prestacion` (`servicio` | `economica`), `nivel_garantia` (`garantizada` | `condicionada`), `sexo`, `visibilidad_apunte`.

**Usar tabla `catalogos_sistema`** cuando:
- El valor es puramente descriptivo o clasificatorio: el código nunca toma decisiones basándose en él.
- El valor se usa para poblar selects, filtros de búsqueda o etiquetas en la UI.
- Es razonable que un administrador funcional pueda añadir, renombrar u ordenar valores sin necesidad de un deploy.

Ejemplos en VIDA: `objetivo_general` de prestaciones, `nivel_atencion`, `competencia`, `forma_gestion`, `financiacion`, categorías específicas del catálogo.

**La tabla `catalogos_sistema`** tiene la siguiente estructura:

```
catalogos_sistema
──────────────────────────────────────────
grupo     string  — identificador del catálogo (ej: 'prestacion.objetivo_general')
clave     string  — valor interno usado en la BD y el código (ej: '01')
etiqueta  string  — texto visible en la UI (ej: 'Acceso, información y valoración')
orden     int     — para control de presentación
activo    boolean — baja lógica sin borrado físico
```

**Restricción crítica:** los valores de `catalogos_sistema` **nunca** pueden ser referenciados por nombre en lógica de negocio. Si el código necesita distinguir entre dos valores de un catálogo para hacer algo diferente, ese catálogo debe ser un enum, no una entrada en `catalogos_sistema`. Violar esta restricción introduce bugs silenciosos ante cualquier cambio de etiqueta o clave desde backoffice.

Filament gestiona `catalogos_sistema` como pantalla de configuración general, junto con otros parámetros del sistema (nombre de la entidad, municipio, etc.).

### 3.11 Colectivos protegidos como configuración

Los colectivos con acceso restringido a su expediente (actualmente mujeres víctimas de violencia de género y menores) se gestionan mediante una tabla de configuración, no mediante código hardcodeado. Añadir un nuevo colectivo protegido es una operación de configuración, no de desarrollo. El middleware de autorización consulta dinámicamente esta tabla.

### 3.12 Filament para configuración, Livewire para operación

Filament gestiona las capas de configuración y backoffice: catálogos, plantillas, parámetros del sistema, usuarios y permisos. Livewire gestiona las capas operativas y de supervisión: el trabajo diario de los profesionales con ciudadanos, planes, apuntes y agenda. Esta separación es estructural y no debe mezclarse.

---

## 4. Colectivos con necesidades específicas de diseño

### 4.1 Personas Sin Hogar (PSH)

Las PSH no tienen domicilio fijo, lo que invalida el criterio territorial habitual de asignación de TSR y centro. El sistema debe permitir registrar a una persona sin domicilio y asignarle un centro de referencia por criterios alternativos (centro de acogida, zona de intervención de calle, etc.). Este es un caso de excepción estructural, no un subtipo de ciudadano.

### 4.2 Víctimas de Violencia de Género (VVG)

Las VVG tienen circuito de acceso independiente por razones de seguridad. Su expediente requiere nivel de acceso especial (ver principio 3.11). La integración con ASP se produce cuando la situación está estabilizada, pero el seguimiento especializado continúa en paralelo. La Historia Social es única; el circuito diferenciado afecta al flujo de acceso, no a la estructura del expediente.
