# Módulo: Ciudadanía — VIDA 360

Ciudadano en VIDA 360 es toda persona que interactúa con el sistema, independientemente de la intensidad de esa interacción. Incluye desde quien se inscribe puntualmente en una actividad de un centro hasta quien tiene un expediente de intervención social activo con TSR asignado. El registro en VIDA genera siempre un identificador único interno, pero no implica necesariamente la apertura de una
situación social ni de una Historia Social — esas capas se activan por decisión profesional explícita en contextos predefinidos.

El ciudadano es la unidad de continuidad del sistema: una misma persona puede ser primero asistente ocasional a actividades de un Centro, después beneficiaria de prestaciones y más delante titular de un expediente de intervención activo. El identificador nunca cambia; las capas de datos que se activan sobre él sí pueden cambiar a lo largo del tiempo.
---

## 1. Principios rectores del módulo

**Identidad única:** cada persona tiene un único registro en VIDA, independientemente de cuántos servicios utilice, cuántos profesionales la atiendan o cuántos sistemas legacy hayan gestionado sus datos anteriormente. El ciudadano es el libro; los servicios, los capítulos escritos por distintos autores.

**El pasado es inmutable:** ningún dato histórico se sobrescribe. Los cambios generan nuevas versiones; los errores se corrigen con trazabilidad explícita. En cualquier momento es posible reconstruir la situación de una persona en una fecha concreta.

**Cifrado en aplicación:** todos los datos del ciudadano se cifran en la capa de aplicación antes de persistirse. Las claves no residen en la base de datos. Un acceso directo a la BD sin las claves de aplicación devuelve texto ilegible.

**Separación de capas de datos:** los datos del ciudadano se organizan en capas con distintos niveles de acceso. Ver sección 2.

**Sin valores hardcodeados:** los tipos de documento, tipos de relación, contextos de alta, requisitos de campos y colectivos de excepción son configurables desde el backoffice.

---

## 1.1 Espectro de registro: del ciudadano ocasional al expediente activo

No todo ciudadano registrado en VIDA tiene el mismo perfil de datos ni el mismo recorrido. El campo `contexto_alta` determina qué datos son obligatorios en el momento del registro, qué capas se activan automáticamente y qué operaciones son posibles sobre ese registro.

Se definen los siguientes contextos de alta, configurables desde el backoffice:

| Contexto | Quién lo usa | Datos mínimos requeridos | Situación social (Capa 2) | Historia social (Capa 3) |
|---|---|---|---|---|
| `actividad_centro` | Supervisor, TSR, auxiliar | Nombre + contacto básico | No se crea | No se crea |
| `representante` | TSR, tramitación | Nombre + documento | No se crea | No se crea |
| `asp_primera_atencion` | TSR, tramitación | Nombre + domicilio + contacto | Se crea al abrir Historia | Se crea por el TSR |
| `equipo_calle_psh` | TSR de calle | Alias + coordenadas | Se crea al abrir Historia | Se crea por el TSR |
| `circuito_vvg` | TSR, tramitación VVG | Nombre + contacto seguro | Se crea al abrir Historia | Se crea por el TSR |

**Reglas estructurales:**

- El registro de un ciudadano **nunca abre una Historia Social de forma automática**. La apertura de Historia es siempre un acto profesional explícito del TSR, en los contextos que lo permiten (`asp_primera_atencion`, `equipo_calle_psh`, `circuito_vvg`).
- Un ciudadano registrado en contexto `actividad_centro` **computa en las estadísticas de personas atendidas** del centro, aunque no tenga Historia Social.
- Su actividad en el sistema (inscripciones, asistencias) queda registrada y es accesible si posteriormente se abre una Historia Social, aportando contexto longitudinal al profesional.
- El caso paradigmático es el de una persona mayor que accede a un Centro de Mayores para actividades (contexto `actividad_centro`, sin Historia), y que con el tiempo puede desarrollar necesidades de dependencia que justifican la apertura de Historia. El identificador de ciudadano es el mismo en todo momento; la actividad acumulada —incluyendo inscripciones y asistencias anteriores— es visible para el TSR cuando se abre la Historia.

**Sobre la Capa 2:** la situación social no se crea en el momento del alta en todos los contextos. En `actividad_centro` y `representante`, el ciudadano existe en la Capa 1 (identificación y contacto) sin Capa 2. La situación social se crea cuando un TSR inicia el proceso de valoración, típicamente en el contexto de apertura de Historia.

---

## 2. Capas de datos del ciudadano

Los datos del ciudadano se organizan en tres capas con distintos niveles de acceso y distintas entidades en el modelo:

### Capa 1 — Identificación y contacto (cabecera)
Nombre, fecha de nacimiento, sexo, domicilio, teléfono, email, documentos de identidad. Accesible para un conjunto amplio de roles: `tramitacion`, `consulta_basica`, `intervencion` y superiores. Es la capa que permite citar a una persona, verificar su identidad o localizarla.

### Capa 2 — Situación social
Situación familiar, económica, laboral, de vivienda, de salud relevante para la intervención. La situación social refleja el estado del mundo en un momento dado. Es versionada: cada cambio genera una nueva versión con fecha de inicio, y la anterior recibe fecha de fin. 

**No existe para todos los ciudadanos registrados:** solo se crea cuando un TSR inicia un proceso de intervención. Accesible para roles de intervención y superiores.

> **Nota terminológica:** esta capa no es "la ficha del ciudadano". Los datos de identificación y contacto (nombre, domicilio, teléfono) viven en la Capa 1, en
> la tabla `ciudadanos`, y no tienen nombre de capa propio en la UI — se muestran directamente sin etiqueta. El término "Ficha Social" se reserva para su acepción
> legal (Decreto 51/2023); ver `docs/glosario.md`.

### Capa 3 — Historia social
El conjunto de sucesos: entrevistas, valoraciones, apuntes, planes, seguimientos, derivaciones. La historia recoge eventos, no estados. Con las restricciones adicionales de colectivos especialmente protegidos definidas en `docs/modulo-usuarios-permisos.md`. Accesible para roles de intervención y superiores, con las restricciones de colectivos protegidos.

**Sobre la activación de capas según el contexto de alta:** No todos los ciudadanos registrados tienen las tres capas activas. La Capa 1 (identificación) existe para cualquier ciudadano desde el momento del registro. La Capa 2 (situación social) y la Capa 3 (historia social) solo se crean en contextos de intervención activa. Ver sección 1.1 para los contextos de alta y sus implicaciones.

---

## 3. Modelo de datos

### 3.1 Ciudadano (Capa 1)

Entidad central del sistema. Contiene únicamente los datos de identificación y contacto.

```
ciudadanos
- id (autoincremental — identificador único interno, nunca cambia)
- alias (string nullable — nombre operativo para PSH u otros casos sin identificación formal)
- nombre (string encriptado)
- apellido1 (string encriptado)
- apellido2 (string nullable encriptado)
- fecha_nacimiento (date encriptada)
- sexo (string — configurable, no enum cerrado)
- domicilio (text encriptado nullable)
- latitud (decimal nullable)
- longitud (decimal nullable)
- telefono (string encriptado nullable)
- email (string encriptado nullable)
- nivel_identificacion (string: identificado / probable / no_identificado)
- contexto_alta (string — contexto en el que se creó el registro; determina los datos mínimos requeridos y las capas activables. Valores predefinidos: `actividad_centro` / `representante` / `asp_primera_atencion` / `equipo_calle_psh` / `circuito_vvg`. Catálogo ampliable desde el backoffice)
- activo (boolean default true)
- timestamps
- softDeletes
```

**Sobre el campo `alias`:** para personas sin hogar u otros casos sin identificación formal, el alias es el identificador operativo que usan los profesionales ("Juan que duerme en el cajero de la calle X"). No es el nombre legal. Cuando se obtiene la identidad real, el alias se mantiene como referencia histórica pero el registro se enriquece con los datos formales.

**Sobre `nivel_identificacion`:** refleja la confianza en la identidad del registro. `identificado` significa que se ha verificado al menos un documento oficial. `probable` significa que hay datos suficientes para operar pero sin verificación documental. `no_identificado` es el nivel mínimo para PSH sin documentación.

**Sobre coordenadas:** el domicilio siempre incluye coordenadas geográficas (principio 4.15). Para PSH, las coordenadas del lugar habitual de pernocta sustituyen al domicilio.

### 3.2 Identificadores complementarios

```
ciudadano_identificadores
- id
- ciudadano_id (FK)
- tipo (string — configurable: nif / nie / pasaporte / ni_hsu_cm / id_civis / id_legacy_otro...)
- valor (string encriptado)
- fecha_inicio (date)
- fecha_fin (date nullable)
- verificado (boolean default false)
- fuente (string: manual / padron / ciudadano360 / importacion / interoperabilidad)
- observaciones (text nullable)
- timestamps
```

El catálogo de tipos de identificador es configurable desde el backoffice. Un NIF verificado por un profesional que ha visto el documento físico tiene distinto nivel de confianza que un NIF importado de un Excel de un proveedor externo. El campo `verificado` recoge esta distinción.

El historial completo de documentos de identidad queda preservado: cuando alguien pasa de pasaporte a NIE y después a DNI, los tres registros permanecen con sus fechas de vigencia.

### 3.3 Relaciones entre ciudadanos

```
ciudadano_relaciones
- id
- ciudadano_id (FK)
- ciudadano_relacionado_id (FK)
- tipo_relacion (string — configurable desde backoffice)
- fecha_inicio (date)
- fecha_fin (date nullable)
- observaciones (text nullable)
- timestamps
```

El catálogo de `tipos_relacion` es configurable desde el backoffice. Cada tipo define su recíproco mediante el campo `tipo_reciproco`: cuando se crea la relación "A es padre de B", el sistema genera automáticamente "B es hijo de A". Las relaciones simétricas (cónyuge, pareja_de_hecho) se reciprocan con el mismo tipo.

Ejemplos del catálogo inicial: cónyuge, pareja_de_hecho, hijo/a, padre/madre, hermano/a, tutor_legal, tutelado, cuidador_principal, persona_cuidada, acogedor, acogido, representante, representado.

La reciprocidad automática la gestiona el trait `TieneRelacionesReciprocas` aplicado al modelo `CiudadanoRelacion`. El trait intercepta la creación de una relación y genera el registro inverso en la misma transacción. Si se elimina o cierra (fecha_fin) una relación, el trait aplica el mismo cambio al registro recíproco.

Esta tabla es la fuente única de verdad sobre el vínculo entre dos ciudadanos. No existe ningún otro lugar en el modelo donde se registre el rol de una persona respecto a otra.

### 3.4 Unidad de convivencia

La unidad de convivencia tiene identidad propia porque es la unidad de referencia
para el cálculo de prestaciones económicas y para la intervención familiar. No es
simplemente un grupo de relaciones — es una entidad con domicilio, fechas de
vigencia y composición propia.

**Ubicación en el código:** `Modules\Ciudadania`. La UC no tiene módulo propio;
su ciclo de vida siempre se origina desde un ciudadano.

```

unidades_convivencia
- id
- domicilio (text encriptado)
- latitud (decimal nullable)
- longitud (decimal nullable)
- fecha_constitucion (date)
- fecha_disolucion (date nullable)
- observaciones (text nullable)
- timestamps
- softDeletes

unidad_convivencia_miembros
- id
- unidad_convivencia_id (FK)
- ciudadano_id (FK)
- fecha_inicio (date)
- fecha_fin (date nullable)
- fuente (enum: manual / padron / importacion)
- verificado (boolean default false)
- verificado_por (FK a users, nullable)
- verificado_en (timestamp nullable)
- timestamps

```

**Sobre cuándo crear una unidad de convivencia:** un ciudadano se da de alta
siempre sin unidad de convivencia. La unidad se crea únicamente cuando es
relevante modelar la convivencia:

- Al dar de alta a un conviviente para vincularlo al caso de otro ciudadano.
- Al tramitar una prestación económica que requiere conocer la composición e
  ingresos del hogar.
- Cuando la intervención es de carácter familiar, no individual.

**Sobre los miembros de la UC:** todo miembro es un ciudadano de pleno derecho
en el sistema. Cuando el TSR añade un conviviente durante el flujo de intervención,
ese alta pasa por el mismo motor de deduplicación y la misma consulta al padrón
que cualquier otro alta. El contexto de alta puede preseleccionar el domicilio de
la UC y la relación con el ciudadano de referencia para agilizar el formulario,
pero no omite ninguna garantía de calidad de datos.

Un miembro de la UC puede no tener Historia Social ni Plan de Intervención propio.
Su presencia en la unidad puede ser relevante únicamente para el cálculo de
ingresos o la valoración de la situación familiar del ciudadano titular del caso.

**Sobre el campo `verificado`:** indica si se ha verificado la residencia del
ciudadano en el municipio, necesaria para ser perceptor de prestaciones municipales.
La verificación se produce normalmente durante el alta mediante consulta al padrón.
En casos tasados (VVG, PSH sin documentación), el TSR puede marcar la verificación
manualmente; en ese caso se registra `verificado_por` y `verificado_en` para
trazabilidad. Un ciudadano sin `verificado = true` en su membresía activa no puede
ser titular de prestaciones económicas municipales — esta restricción se evalúa en
código, no en configuración.

**Sobre el rol dentro de la unidad:** la unidad de convivencia no registra el rol
de cada miembro. Quién es hijo de quién, quién es tutor de quién, se lee de
`ciudadano_relaciones`. Cuando se añade un miembro a una unidad de convivencia, el
profesional debe asegurarse de que la relación entre ese ciudadano y los demás
miembros existe en la tabla de relaciones; si no existe, la crea en ese momento.

**Sobre la titularidad y los planes:** no existe un "titular de la unidad". Los
Planes de Intervención pueden asignarse a una persona concreta (ciudadano individual)
o a la UC como entidad (intervención familiar). Las prestaciones económicas se
asignan siempre a personas concretas. Ver `docs/modulo-intervencion.md`, sección 5,
para el modelo de `PlanDeIntervencion` y la restricción de que exactamente uno de
`ciudadano_id` o `unidad_convivencia_id` debe estar presente.

Un ciudadano puede pertenecer a más de una unidad de convivencia a lo largo del
tiempo, y excepcionalmente a más de una simultáneamente (menores con custodia
compartida en dos domicilios).

Los miembros importados desde el padrón se marcan con `fuente: padron` y
`verificado: false` hasta confirmación del profesional.

### 3.5 Situación social (Capa 2)

Los registros de situación social reflejan el estado del mundo en un momento dado. Son versionados: cada cambio genera una nueva versión.

> **Clase PHP:** `CiudadanoSituacion` (renombrada desde `CiudadanoFicha`). La tabla en base de datos mantiene el nombre `ciudadano_fichas` por compatibilidad con migraciones existentes, pero toda referencia en código nuevo debe usar `CiudadanoSituacion`.

```
ciudadano_fichas
- id
- ciudadano_id (FK)
- tipo_ficha (string configurable: economica / familiar / vivienda / salud / laboral...)
- datos (jsonb encriptado — estructura variable según tipo de ficha)
- version (integer)
- fecha_inicio (date)
- fecha_fin (date nullable)
- profesional_id (FK — quién registró esta versión)
- tipo_actualizacion (string: modificacion / correccion)
- motivo_correccion (text nullable — obligatorio si tipo_actualizacion = correccion)
- timestamps
```

**Sobre el campo `tipo_actualizacion`:** distingue dos naturalezas de cambio:
- `modificacion`: la situación real ha cambiado. El dato anterior era correcto en su momento y se preserva como versión histórica válida.
- `correccion`: el dato anterior era incorrecto. Se preserva con una marca de error y la referencia a quién lo corrigió, cuándo y por qué, pero no se trata como historial válido de la situación real.

**Sobre la estructura de `datos`:** cada tipo de ficha tiene su esquema propio almacenado en el backoffice. El campo `jsonb` permite flexibilidad sin proliferación de tablas. Los esquemas de ficha son configurables y versionables.

---

## 4. Flujo de alta de ciudadano

El alta de un ciudadano sigue un flujo estructurado que garantiza la unicidad del registro y la calidad de los datos.

```
1. El profesional selecciona el contexto de alta
   (CSS general / equipo de calle PSH / recurso VVG / urgencia / importación...)
   El contexto determina qué campos son obligatorios y qué excepciones aplican.

2. Consulta al padrón (según contexto)
   - Contexto VVG → la consulta NO se realiza (protección de datos)
   - Contexto PSH → la consulta se realiza si hay documento; si no, se omite
   - Resto → consulta obligatoria
   Si el padrón devuelve datos, se precargan con fuente: padron / verificado: false
   El profesional verifica y corrige si es necesario.

3. Consulta a Ciudadano360 (opcional, según contexto)
   Precarga datos de contacto electrónico si están disponibles.
   Marcados con fuente: ciudadano360 / verificado: false.

4. Normalización de datos introducidos
   Aplicación de reglas de normalización antes del matching.

5. Búsqueda de duplicados en VIDA
   El sistema ejecuta el motor de matching con los datos disponibles.
   Ver sección 5.

6. Resolución de duplicados (si los hay)
   - Sin coincidencias → continuar al paso 7
   - Coincidencias probables → el profesional revisa y decide
   - Coincidencia casi segura → el sistema bloquea y fuerza resolución

7. Creación del registro
   Se crea el ciudadano con su id interno.
   Se registran los identificadores complementarios disponibles.
   Se crea la unidad de convivencia si procede.
   Todo queda en la auditoría.
```

---

## 5. Motor de matching e identidades

### 5.1 Normalización de entrada

Antes de cualquier búsqueda de duplicados, los datos identificativos se normalizan:

- Nombres propios completos, sin abreviaturas ("María", no "Mª"; "José", no "J.")
- Documentos de identidad en formato canónico (NIF con letra verificada, NIE con formato X-NNNNNNN-L)
- Teléfonos con prefijo internacional, sin espacios ni guiones
- Emails en minúsculas
- Fechas en formato ISO

La normalización la ejecuta la clase `NormalizadorCiudadano` en la capa de aplicación, invocada antes de cualquier inserción o actualización.

### 5.2 Algoritmo de matching

El motor de matching combina campos con distinto peso para calcular un score de similitud:

| Condición | Peso |
|---|---|
| Documento de identidad exacto | Muy alto — bloquea creación |
| Fecha de nacimiento + apellidos similares | Alto |
| Teléfono o email exacto | Alto |
| Nombre similar + fecha de nacimiento | Medio |
| Solo nombre similar | Bajo |

La similitud de cadenas de texto (para nombres con erratas o variaciones) se calcula con algoritmos **Jaro-Winkler** o **Levenshtein**, disponibles en PHP y en PostgreSQL. No se usa IA para este proceso: los algoritmos deterministas son suficientes y más auditables.

**Para ciudadanos PSH sin documentación**, el matching se basa en:
- Proximidad geográfica del lugar de pernocta
- Similitud del alias / descriptor
- Rango de edad compatible
- Fecha aproximada de primer contacto con el sistema

En este caso el score es inevitablemente más subjetivo. El sistema presenta candidatos y el profesional del equipo de calle toma la decisión final.

### 5.3 Umbrales de actuación

Los umbrales son configurables desde el backoffice:

- **Score bajo el umbral mínimo:** no hay duplicados probables. Se continúa el alta.
- **Score entre umbral mínimo y máximo:** coincidencias probables. Se muestra al profesional la lista de candidatos con indicación del motivo de similitud. El profesional decide si es la misma persona o una nueva.
- **Score sobre el umbral máximo** (o documento idéntico): coincidencia casi segura. El sistema bloquea la creación y fuerza la resolución manual antes de continuar.

El profesional siempre ve el motivo de la similitud (qué campos coinciden), no solo el score numérico.

### 5.4 Fusión de registros duplicados

Cuando se detecta que dos registros corresponden a la misma persona — en el alta o posteriormente — el sistema ejecuta una fusión:

1. Se designa el registro canónico (habitualmente el más antiguo o el más completo).
2. Todos los identificadores complementarios del registro secundario se transfieren al canónico.
3. Todas las relaciones (historias, fichas, unidades de convivencia, planes) se reasignan al canónico.
4. El registro secundario se marca como `fusionado`, con referencia al canónico, fecha y responsable. No se elimina.
5. La operación completa queda en la auditoría con justificación obligatoria.

La fusión es reversible en caso de error. Está restringida a usuarios con el permiso `ciudadano.fusionar`, que se asignará a roles de tramitación avanzada o supervisión.

---

## 6. Casos especiales

### 6.1 Personas sin hogar (PSH)

Las PSH pueden carecer de documentación oficial e incluso de nombre conocido. El sistema admite registros con nivel de identificación `no_identificado` cuando el contexto de alta es `equipo_calle_psh` u otro contexto con ese permiso habilitado.

El registro mínimo viable para una PSH:
- Id interno (siempre)
- Alias o descriptor (texto libre)
- Estimación de edad o rango
- Coordenadas del lugar habitual de pernocta (equivalente funcional al domicilio)
- Equipo de calle responsable (derivado de las coordenadas y la zonificación)
- Fecha de primer contacto

Cuando la persona obtiene documentación o se confirma su identidad, el registro existente se enriquece sin crear uno nuevo. Si posteriormente se detecta que ya existía un registro con su identidad real, se ejecuta la fusión.

### 6.2 Mujeres víctimas de violencia de género (VVG)

Dos protecciones específicas en el alta:

**No consulta al padrón:** la consulta al API del padrón no se lanza para ciudadanas en contexto VVG o marcadas como pertenecientes a ese colectivo. No es suficiente con ignorar la respuesta — la consulta misma no se realiza para no dejar traza en los logs del padrón.

**Domicilio protegido:** el domicilio registrado en VIDA puede ser diferente al del padrón (por ejemplo, un recurso de acogida). Esta diferencia es intencionada y está protegida. El domicilio real no se comparte con otros sistemas mediante sincronización automática.

### 6.3 Ciudadanos sin empadronamiento

Para ciudadanos que no aparecen en el padrón por razones distintas a las anteriores (ciudadanos de otros municipios en urgencia, solicitantes de asilo en proceso, menores en acogida), el alta sin padrón requiere autorización explícita del usuario con permisos suficientes y justificación que queda en la auditoría. No es una excepción estructural como PSH o VVG — es una excepción puntual gestionada caso a caso.

---

## 7. Implementación técnica

### 7.1 Cifrado en aplicación

Se utiliza cifrado en la capa de aplicación para todos los campos sensibles del ciudadano. Las claves de cifrado residen en el `.env` o en un gestor de secretos externo, nunca en la base de datos.

Paquete recomendado: `spatie/laravel-model-encryption` o implementación equivalente. Todos los campos marcados como `encriptado` en el modelo de datos se cifran automáticamente al persistir y se descifran al recuperar, de forma transparente para el código consumidor.

Los campos cifrados no son buscables directamente. Las búsquedas de duplicados operan sobre hashes deterministas de los campos clave (documento de identidad, email, teléfono) almacenados en columnas adicionales no cifradas. El hash permite la búsqueda sin exponer el dato.

### 7.2 Versionado

El versionado de la situación social sigue el patrón de filas con `fecha_inicio` /`fecha_fin`. La versión activa es la que tiene `fecha_fin` null. Las consultas históricas filtran por fecha.

Para los datos de la tabla `ciudadanos` (Capa 1), los cambios se registran en una tabla de auditoría `ciudadanos_auditoria` con el valor anterior, el nuevo valor, el tipo de cambio (modificacion / correccion), el usuario y el timestamp.

### 7.3 Integración con el módulo de Integraciones

El flujo de alta consume las integraciones de padrón y Ciudadano360 a través de sus interfaces, nunca directamente. Ver `docs/modulo-integraciones.md`.

En entornos de desarrollo y pruebas, los adaptadores mock devuelven datos ficticios coherentes que permiten ejercitar el flujo completo sin conexión a sistemas externos.

### 7.4 Referencias de código

*(Se completará a medida que avance la implementación)*

- `Modules\Ciudadania\Models\Ciudadano`
- `Modules\Ciudadania\Models\CiudadanoIdentificador`
- `Modules\Ciudadania\Models\CiudadanoRelacion`
- `Modules\Ciudadania\Models\UnidadConvivencia`
- `Modules\Ciudadania\Models\UnidadConvivenciaMiembro`
- `Modules\Ciudadania\Models\CiudadanoSituacion`  ← renombrada desde CiudadanoFicha
- `Modules\Ciudadania\Services\NormalizadorCiudadano`
- `Modules\Ciudadania\Services\MotorMatching`
- `Modules\Ciudadania\Services\FusionCiudadanos`
- `Modules\Ciudadania\Livewire\AltaCiudadano`
- `Modules\Ciudadania\Traits\TieneRelacionesReciprocas`

---

## 8. Decisiones pendientes

- **Esquemas de situación social por tipo:** definir la estructura de datos de cada tipo de situación (económica, familiar, de vivienda, de salud, laboral) antes de implementar el módulo de intervención.
- **Umbrales del motor de matching:** calibrar los umbrales de score una vez disponibles datos reales de prueba.
- **Reversión de fusiones:** definir el proceso de reversión de una fusión errónea y quién puede autorizarla.
- **Acceso ciudadano a sus propios datos:** cuando se implemente la carpeta ciudadana y el rol 0, revisar qué datos de Capa 1 y Capa 2 son visibles para el propio ciudadano.
- **Sincronización con HSU-CM:** definir qué datos se sincronizan con la Historia Social Única de la Comunidad de Madrid y con qué frecuencia, cuando esa integración esté disponible.
- **Genograma:** se prevé incorporar en el futuro una representación gráfica 
  de las relaciones entre ciudadanos a partir de `ciudadano_relaciones` y 
  `unidad_convivencia_miembros`. El modelo actual es compatible con esta 
  funcionalidad. Decisiones pendientes antes de implementar:
  - Añadir `tipo_dinamica` (opcional) en `ciudadano_relaciones` para representar 
    la calidad del vínculo (conflictivo, distante, fusionado…).
  - Añadir `fecha_fallecimiento` en `ciudadanos`.
  - Valorar nodos "ligeros" para personas significativas no registradas como 
    ciudadanos activos (fallecidos, no residentes…).
  - Definir si se implementa un genograma clásico (solo personas y relaciones) 
    o un ecomapa que incluya nodos no personales como prestaciones, centros o 
    recursos comunitarios, y en ese caso qué entidades se representan y con qué 
    propósito.

---

*Documento elaborado en fase de diseño del proyecto. Versión inicial: marzo 2026.*
