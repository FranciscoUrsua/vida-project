# Módulo de Centros — VIDA 360
## Documento funcional v1.1 · Mayo 2026

> **Cambios respecto a v1.0**: se añade la relación de pertenencia del centro a una Unidad Organizativa, el modelo de ámbito territorial, y la entidad Red de Centros como agregador de recursos. Se añade sección de tests funcionales.

---

## 1. Introducción y propósito del módulo

El módulo de Centros de VIDA 360 resuelve dos necesidades complementarias: proporcionar al profesional un catálogo operativo de todos los centros y recursos disponibles en el sistema municipal de servicios sociales, y gestionar la disponibilidad de plazas, la inscripción en centros y la participación en actividades, ya sea por acceso libre o por prescripción desde un plan de intervención.

Un centro, en el contexto de VIDA 360, es cualquier equipamiento con presencia física que ofrece prestaciones sociales a un segmento de la población. Esta definición es amplia de forma deliberada: incluye tanto los Centros de Servicios Sociales de Atención Social Primaria como los centros de acogida, centros de día, pisos tutelados, centros de emergencia o pensiones y hoteles habilitados puntualmente para alojamiento de emergencia.

El módulo no es un gestor de centros en sentido pleno. La gestión operativa interna de cada centro (espacios físicos, control de asistencia, certificados, recursos materiales) corresponde a las herramientas especializadas de cada servicio. VIDA 360 gestiona lo que es relevante para el profesional de servicios sociales: saber qué hay disponible, poder prescribir, y hacer seguimiento de las prescripciones activas.

---

## 2. Conceptos clave

### 2.1 Centro

Un centro es la entidad raíz del módulo. Se define por los siguientes rasgos:

- Tiene un lugar físico con una dirección postal.
- Pertenece a una Unidad Organizativa del sistema.
- Tiene un ámbito territorial de atención que puede coincidir o no con el de su UO.
- Está orientado a un segmento de población definido (puede ser más de uno).
- Ofrece prestaciones concretas del catálogo de prestaciones del sistema.
- Tiene personal profesional asignado (propio o externo).
- Tiene un horario y días de funcionamiento.
- Puede gestionar plazas, actividades, o ambas cosas. También puede ofrecer únicamente atención por cita.

Los centros se clasifican por tipo de gestión:

- **Municipal directo**: personal propio del ayuntamiento.
- **Municipal concertado**: gestionado por una entidad externa por contrato con el ayuntamiento.
- **Privado concertado**: entidad del tercer sector o empresa con acuerdo de derivación.
- **Privado puro**: recursos privados (pensiones, hoteles) habilitados puntualmente para emergencia residencial.

El tipo de gestión determina el nivel de integración en VIDA 360: los centros municipales se gestionan completamente en el sistema; los privados concertados se referencian para derivación y opcionalmente para gestión de ocupación; los privados puros se gestionan en lo relativo a la derivación y al presupuesto asociado (pendiente de diseño en fase posterior).

### 2.2 Pertenencia a Unidad Organizativa

Todo centro pertenece a una Unidad Organizativa. Esta relación es la pertenencia **administrativa**: refleja de quién depende el centro en el organigrama municipal.

La pertenencia a una UO es distinta del ámbito territorial de atención (ver 2.3). Un CSS de Vallecas depende administrativamente de la Junta de Distrito de Puente de Vallecas, pero su ámbito real de atención puede no coincidir exactamente con los límites del distrito.

La FK `unidad_organizativa_id` en `centros` es nullable para permitir el registro de centros externos o privados puros que no forman parte del organigrama municipal.

### 2.3 Ámbito territorial

El ámbito territorial de un centro define la población a la que atiende geográficamente. Es independiente de la dirección postal del centro (dónde está) y de su UO de dependencia (de quién depende).

El ámbito puede expresarse de varias formas, no mutuamente excluyentes:

- **Toda la ciudad**: el centro atiende sin restricción territorial (p. ej., un recurso especializado de referencia municipal).
- **Demarcación oficial**: el centro atiende a ciudadanos de un distrito o conjunto de distritos concretos.
- **Barrios o secciones censales**: dos centros se reparten un distrito por unidades sub-distritales.
- **Polígono GIS**: el ámbito queda definido por una geometría geográfica precisa. Útil para repartos irregulares no alineados con demarcaciones administrativas.

El modelo almacena el ámbito como una colección de registros `AmbitoTerritorial` asociados al centro, cada uno con su tipo y su referencia. Esto permite combinar tipos (p. ej., tres distritos completos más dos barrios concretos de un cuarto distrito).

> **Nota de diseño**: el procesamiento de polígonos GIS requiere la extensión PostGIS en PostgreSQL. Para implementaciones sin PostGIS, el tipo `poligono` se almacena como GeoJSON en un campo `json` sin capacidad de consulta espacial nativa. La consulta espacial avanzada (¿qué centro atiende esta dirección?) se difiere al módulo de Integraciones.

### 2.4 Red de centros

Una red de centros es una agrupación de centros que expone sus plazas de forma agregada. Su propósito es exclusivamente operativo: cuando un profesional no puede asignar a un ciudadano a un centro concreto (por falta de plazas disponibles, por ejemplo), puede operar sobre la red y ver disponibilidad consolidada de todos sus centros miembros.

La red no tiene personalidad organizativa propia, no tiene director, no tiene personal. Es un agregador. Cualquier profesional con perfil de intervención puede operar sobre los recursos de una red igual que sobre los de un centro individual.

Un centro puede pertenecer a varias redes simultáneamente. Las redes no tienen jerarquía entre sí.

Un ejemplo real: la Red Municipal de Atención a Personas Sin Hogar agrupa varios centros de acogida. Al prescribir alojamiento, el profesional puede ver las plazas libres de toda la red en una sola pantalla y asignar la más adecuada al perfil de la persona.

### 2.5 Colección de plazas

Las plazas se agrupan en colecciones dentro de cada centro. Una colección define un tipo homogéneo de plaza y un modo de acceso. Un mismo centro puede tener varias colecciones con tipos y modos de acceso distintos.

Dentro de cada colección, las plazas se organizan en dos niveles:

- **Espacio**: unidad física (dormitorio, habitación, módulo). Tiene tipo, capacidad, atributos de accesibilidad y restricción de género.
- **Plaza**: unidad mínima asignable a una persona. Tiene estado (libre, ocupada, reservada, en mantenimiento).

La plaza es siempre la unidad de asignación a una persona. Para asignación a parejas o familias, el espacio contiene varias plazas y se asignan las necesarias dentro del mismo espacio.

> **Nota de diseño**: Una cama doble se modela como un espacio con dos plazas, no como una plaza con capacidad dos.

Tipos de plaza:
- **Pernocta**: alojamiento con pernoctación. Requiere la jerarquía completa hasta nivel de plaza individual.
- **Día**: atención diurna sin pernoctación. Jerarquía más plana.

Modos de acceso (se definen por colección, no por centro):
- **Libre**: cualquier persona puede solicitar directamente una plaza.
- **Prescripción directa**: requiere prescripción desde un plan de intervención. Asignación inmediata si hay disponibilidad.
- **Prescripción con lista de espera**: igual que el anterior, pero cuando no hay disponibilidad la persona entra en lista de espera.

### 2.6 Actividades

Un centro puede organizar actividades: talleres, charlas, seminarios, grupos de apoyo, cursos, etc. Las actividades son independientes de las plazas: un centro puede tener solo plazas, solo actividades, o ambas. Un centro sin plazas puede existir si su función es exclusivamente la organización de actividades.

Cada actividad tiene su propio modo de acceso:
- **Libre**: cualquier ciudadano puede inscribirse directamente.
- **Prescripción**: solo acceden personas derivadas desde un plan de intervención.
- **Mixta**: hay aforo reservado para prescripciones y aforo libre para inscripción directa. El control opera por separado para cada cupo.

Las actividades no tienen periodicidad modelada en el sistema. Se materializan en **sesiones** convocadas de forma explícita. La inscripción apunta siempre a una sesión concreta, no a la actividad en abstracto.

VIDA 360 gestiona las inscripciones y el control de aforo por sesión. Todo lo demás (espacios, recursos, control de asistencia, certificados) corresponde a las herramientas propias del centro.

### 2.7 Inscripción en centro

Algunos centros, como los centros municipales de mayores, requieren que el ciudadano esté registrado en el centro antes de acceder a sus actividades. La inscripción es un registro propio, independiente de cualquier actividad o plaza concreta.

El acceso es voluntario y a iniciativa del ciudadano. El centro puede configurarse de dos formas:
- **Inscripción libre**: el ciudadano elige el centro en el que desea registrarse.
- **Inscripción por domicilio**: el ciudadano debe registrarse en el centro que le corresponde según su domicilio.

La inscripción es indefinida: no tiene fecha de caducidad. La baja es siempre explícita.

Cuando una actividad tiene activo el flag `requiere_inscripcion_centro`, el sistema verifica la existencia de una inscripción activa antes de procesar la inscripción o la prescripción.

### 2.8 Prescripción

Una prescripción es la indicación, dentro de un plan de intervención, de que una persona acceda a una plaza o a una sesión de actividad concreta. Puede realizarla tanto el profesional de atención primaria como el de atención especializada.

La prescripción no debe confundirse con la derivación. La **derivación** es el traslado de un ciudadano de atención primaria a un programa de atención especializada, gestionada en el módulo de Intervención. La **prescripción** es una acción dentro del plan de intervención que apunta a un recurso concreto (plaza o actividad).

Estados de una prescripción: `pendiente` → `en_lista_espera` → `asignada` → `activa` → `finalizada` / `cancelada`.

Cuando no hay disponibilidad, la persona entra en lista de espera. Cuando se libera una plaza, el sistema genera una alerta al TSR activo del ciudadano en ese momento. El profesional revisa, reclasifica si procede, y confirma o cancela la asignación. **La asignación nunca es automática.**

> **Nota**: Si el TSR de referencia ha cambiado desde la prescripción original, la alerta se envía al TSR activo en el momento en que se libera la plaza, no al profesional que realizó la prescripción.

---

## 3. Modelo de datos

### 3.1 Centro

Entidad raíz del módulo.

| Atributo | Tipo | Notas |
|---|---|---|
| id | PK | |
| nombre | string | |
| nombre_corto | string | Para listados y referencias en la interfaz |
| tipo_gestion | enum | `municipal_directo` · `municipal_concertado` · `privado_concertado` · `privado_puro` |
| unidad_organizativa_id | FK | Nullable. UO a la que pertenece administrativamente |
| direccion | string | |
| codigo_postal | string | |
| coordenadas | point | Nullable. Para geolocalización en mapa |
| telefono | string | |
| email | string | |
| web | string | Nullable |
| horario | json | Solo para visualización. Se desarrollará en el módulo de Agenda |
| inscripcion_libre | boolean | `true` = libre elección · `false` = adscripción por domicilio |
| activo | boolean | |
| fecha_alta | date | |
| fecha_baja | date | Nullable |
| notas | text | Nullable. Información adicional para el profesional |

Relaciones N:M:
- `centro_segmento_poblacion` → catálogo de segmentos de población
- `centro_prestacion` → catálogo de prestaciones
- `red_centro` → redes a las que pertenece el centro

Relaciones 1:N:
- `ambitos_territoriales` → colección de ámbitos territoriales del centro

> **Nota**: el campo `distrito_id` presente en versiones anteriores queda eliminado. El distrito, si aplica, se expresa como un registro `AmbitoTerritorial` de tipo `demarcacion_oficial`. Esta normalización evita la redundancia entre el distrito único y la colección de ámbitos cuando un centro atiende varios distritos.

### 3.2 AmbitoTerritorial

Define el ámbito geográfico de atención de un centro. Un centro puede tener varios registros, combinando tipos distintos.

| Atributo | Tipo | Notas |
|---|---|---|
| id | PK | |
| centro_id | FK | |
| tipo | enum | `ciudad_completa` · `demarcacion_oficial` · `barrios` · `secciones_censales` · `poligono_gis` |
| descripcion | string | Nombre legible del ámbito. Ej: "Distrito de Vallecas" |
| referencia_id | integer | Nullable. ID de la entidad referenciada (distrito, barrio, sección censal) según tipo |
| referencia_tipo | string | Nullable. Nombre de la entidad referenciada. Ej: `Distrito`, `Barrio` |
| geojson | json | Nullable. Solo para tipo `poligono_gis` |

Cuando `tipo = ciudad_completa`, el resto de campos son null: el ámbito es todo el municipio sin restricción. Solo puede existir un registro de este tipo por centro.

### 3.3 Red

Agregador de centros para exposición de pool de plazas común. Sin personalidad organizativa propia.

| Atributo | Tipo | Notas |
|---|---|---|
| id | PK | |
| nombre | string | |
| nombre_corto | string | |
| descripcion | text | Nullable |
| activa | boolean | |
| fecha_alta | date | |
| fecha_baja | date | Nullable |

Relación N:M con `Centro` via tabla pivote `red_centro`. Un centro puede pertenecer a varias redes.

### 3.4 ColeccionPlazas

| Atributo | Tipo | Notas |
|---|---|---|
| id | PK | |
| centro_id | FK | |
| nombre | string | Ej: "Plazas de acogida", "Centro de día" |
| tipo_plaza | enum | `pernocta` · `dia` |
| modo_acceso | enum | `libre` · `prescripcion_directa` · `prescripcion_lista_espera` |
| capacidad | integer | Número total de plazas |
| activa | boolean | |
| fecha_alta | date | |
| fecha_baja | date | Nullable |
| notas | text | Nullable |

### 3.5 Espacio

Unidad física dentro de una colección de plazas.

| Atributo | Tipo | Notas |
|---|---|---|
| id | PK | |
| coleccion_plazas_id | FK | |
| nombre | string | Ej: "Dormitorio 3", "Habitación 12", "Módulo B" |
| tipo_espacio_id | FK | Catálogo backoffice: dormitorio individual, compartido, familiar... |
| capacidad | integer | Número de plazas que contiene |
| planta | string | Nullable |
| accesible | boolean | Adaptado para movilidad reducida |
| genero | enum | `mixto` · `mujeres` · `hombres`. Nullable para centros sin restricción |
| activo | boolean | |
| notas | text | Nullable. Características especiales |

### 3.6 Plaza

Unidad mínima asignable a una persona.

| Atributo | Tipo | Notas |
|---|---|---|
| id | PK | |
| espacio_id | FK | |
| nombre | string | Ej: "Cama 1", "Cama 2" |
| estado | enum | `libre` · `ocupada` · `reservada` · `mantenimiento` |
| activa | boolean | |

El estado se mantiene desnormalizado para consultas rápidas de disponibilidad. La ocupación efectiva se rastrea a través de la `Prescripcion` activa que apunta a la plaza.

### 3.7 Actividad

| Atributo | Tipo | Notas |
|---|---|---|
| id | PK | |
| centro_id | FK | |
| nombre | string | |
| tipo_actividad_id | FK | Catálogo backoffice: taller, charla, seminario, grupo de apoyo... |
| descripcion | text | Nullable |
| modo_acceso | enum | `libre` · `prescripcion` · `mixta` |
| aforo_total | integer | Nullable. Si no hay límite de aforo |
| aforo_prescripcion | integer | Nullable. Solo relevante si `modo_acceso = mixta` |
| requiere_inscripcion_centro | boolean | Si `true`, el ciudadano debe tener `InscripcionCentro` activa |
| activa | boolean | |
| fecha_alta | date | |
| fecha_baja | date | Nullable |
| notas | text | Nullable |

### 3.8 SesionActividad

Materialización concreta de una actividad en el tiempo. El control de aforo opera a nivel de sesión.

| Atributo | Tipo | Notas |
|---|---|---|
| id | PK | |
| actividad_id | FK | |
| fecha | date | |
| hora_inicio | time | |
| hora_fin | time | Nullable |
| aforo_total | integer | Nullable. Sobreescribe el de la actividad si se especifica |
| aforo_prescripcion | integer | Nullable. Sobreescribe el de la actividad si se especifica |
| estado | enum | `programada` · `celebrada` · `cancelada` |
| notas | text | Nullable |

### 3.9 InscripcionCentro

Registro de un ciudadano en un centro, independiente de cualquier plaza o actividad.

| Atributo | Tipo | Notas |
|---|---|---|
| id | PK | |
| ciudadano_id | FK | |
| centro_id | FK | |
| fecha_alta | date | |
| fecha_baja | date | Nullable. Baja siempre explícita |
| motivo_baja | string | Nullable |
| activa | boolean | |
| notas | text | Nullable |

### 3.10 DirectorCentro

Historial de responsables del centro.

| Atributo | Tipo | Notas |
|---|---|---|
| id | PK | |
| centro_id | FK | |
| profesional_id | FK | Nullable. Si es usuario de VIDA 360 |
| nombre | string | Nullable. Si es persona externa |
| telefono | string | Nullable. Si es persona externa |
| email | string | Nullable. Si es persona externa |
| fecha_inicio | date | |
| fecha_fin | date | Nullable. Cargo activo si null |
| notas | text | Nullable |

O bien `profesional_id` tiene valor, o bien los campos de contacto externo — nunca ambos. Validación a nivel de aplicación. El registro activo es el que tiene `fecha_fin = null`.

### 3.11 ContactoCentro

Directorio de personas de contacto operativo del centro sin cuenta en VIDA 360. Puede haber varios activos simultáneamente.

| Atributo | Tipo | Notas |
|---|---|---|
| id | PK | |
| centro_id | FK | |
| nombre | string | |
| rol | string | Ej: "Coordinador", "Trabajador social de referencia" |
| telefono | string | Nullable |
| email | string | Nullable |
| activo | boolean | |
| notas | text | Nullable |

### 3.12 Prescripcion

Vincula un ciudadano, desde un plan de intervención, con una colección de plazas o una sesión de actividad.

| Atributo | Tipo | Notas |
|---|---|---|
| id | PK | |
| profesional_id | FK | Profesional que realiza la prescripción |
| ciudadano_id | FK | |
| plan_intervencion_id | FK | Nullable. Si viene de un plan de intervención activo |
| tipo_destino | enum | `coleccion_plazas` · `sesion_actividad` |
| destino_id | integer | FK polimórfica según `tipo_destino` |
| plaza_id | FK | Nullable. Se asigna cuando hay plaza concreta disponible |
| estado | enum | `pendiente` · `en_lista_espera` · `asignada` · `activa` · `finalizada` · `cancelada` |
| fecha_prescripcion | date | |
| fecha_asignacion | date | Nullable |
| fecha_inicio | date | Nullable. Inicio efectivo de uso |
| fecha_fin | date | Nullable. Fin efectivo o previsto |
| motivo_cancelacion | text | Nullable |
| notas | text | Nullable |

### 3.13 ListaEspera

Posición en lista de espera asociada a una prescripción sin disponibilidad inmediata.

| Atributo | Tipo | Notas |
|---|---|---|
| id | PK | |
| prescripcion_id | FK | |
| coleccion_plazas_id | FK | Nullable. Si la lista opera a nivel de colección |
| red_id | FK | Nullable. Si la lista opera a nivel de red |
| posicion | integer | Posición en la lista. Se recalcula cuando hay movimientos |
| fecha_entrada | datetime | |
| fecha_alerta | datetime | Nullable. Última vez que se notificó al profesional |
| profesional_alerta_id | FK | TSR activo en el momento de generar la alerta |
| estado | enum | `activa` · `asignada` · `cancelada` |

O bien `coleccion_plazas_id` tiene valor, o bien `red_id` — nunca ambos. Cuando se libera una plaza, el sistema notifica al TSR activo del ciudadano, que puede no coincidir con el profesional que realizó la prescripción original.

---

## 4. Tablas pivote y relaciones N:M

| Tabla pivote | Propósito |
|---|---|
| `red_centro` | Vincula redes con centros. Un centro puede pertenecer a varias redes. |
| `centro_segmento_poblacion` | Segmentos de población atendidos por el centro. |
| `centro_prestacion` | Prestaciones ofrecidas por el centro según catálogo transversal. |

---

## 5. Catálogo y configuración backoffice

Las siguientes entidades son configurables desde backoffice (Filament) sin necesidad de desarrollo:

| Entidad catálogo | Descripción |
|---|---|
| `TipoEspacio` | Tipos de espacio físico: dormitorio individual, compartido, habitación doble, módulo familiar, etc. |
| `TipoActividad` | Tipos de actividad: taller, charla, seminario, grupo de apoyo, curso, etc. |
| `SegmentoPoblacion` | Colectivos atendidos: personas sin hogar, mayores, VVG, menores, personas con discapacidad, etc. |
| `Prestacion` | Catálogo transversal compartido con el módulo de Intervención. Una prestación puede estar cubierta por plazas, actividades o simplemente por atención en cita. |

Los tipos de gestión, los modos de acceso y los tipos de ámbito territorial se modelan como enums al ser valores de alta estabilidad estructural.

---

## 6. Relación con otros módulos

- **Módulo de Intervención**: la `Prescripcion` se crea en el contexto de un `PlanIntervencion`. Las plazas y actividades son destinos posibles de una prescripción, al mismo nivel que una derivación a atención especializada.
- **Módulo de Organización (UOs)**: todo centro pertenece a una UO vía `unidad_organizativa_id`. El módulo de Centros añade los datos operativos que no caben en la estructura de UOs: plazas, actividades, inscripciones, ámbito territorial, directorio de contactos.
- **Módulo de Agenda**: el horario detallado de centros, la gestión de citas y la disponibilidad en tiempo real se diseñarán conjuntamente con el módulo de Agenda.
- **Módulo de Ciudadanía**: `InscripcionCentro` y `Prescripcion` referencian al ciudadano por su identificador interno.
- **Módulo de Usuarios**: `DirectorCentro` puede referenciar a un `Profesional` del módulo de Usuarios.
- **Módulo de Integraciones**: la consulta espacial avanzada (qué centro atiende una dirección concreta) se implementará como un servicio en el módulo de Integraciones, consumiendo los registros `AmbitoTerritorial` del centro.

---

## 7. Decisiones diferidas con rationale

**Gestión presupuestaria de centros privados puros**: los centros de tipo `privado_puro` (pensiones, hoteles) requieren un modelo de gestión del coste por plaza contratada y su distribución presupuestaria anual entre los recursos habilitados. Se difiere a fase posterior por complejidad y dependencia de procesos administrativos externos.

**Horario detallado y disponibilidad en tiempo real**: el campo `horario` en `Centro` se mantiene como JSON para visualización. La gestión completa de franjas horarias, citas y disponibilidad se abordará en el módulo de Agenda para evitar duplicidad de decisiones de diseño.

**Gestión interna de actividades**: VIDA 360 no gestiona espacios físicos, recursos materiales, ediciones, control de asistencia ni emisión de certificados. Esta funcionalidad corresponde a herramientas especializadas de gestión de centros. El límite del sistema es la inscripción y el control de aforo.

**Consulta espacial GIS**: los polígonos en `AmbitoTerritorial` se almacenan como GeoJSON. La consulta «qué centro atiende esta dirección» requiere PostGIS o un servicio de geocodificación inversa y se difiere al módulo de Integraciones.

**Portal ciudadano**: la inscripción en centros y en actividades de libre acceso puede tener en el futuro un flujo de autoservicio desde el portal ciudadano. El modelo actual no anticipa este canal pero tampoco lo impide: `InscripcionCentro` no tiene dependencia del canal de creación.

---

## 8. Principios de diseño aplicados

- **Sin valores de negocio hardcodeados**: tipos de espacio, tipos de actividad, segmentos de población y prestaciones son configurables desde backoffice.
- **Diferimiento explícito sobre ambigüedad**: las funcionalidades no maduras están identificadas y documentadas con su rationale.
- **Separación de responsabilidades**: VIDA 360 gestiona la prescripción y el seguimiento; los sistemas propios de cada centro gestionan su operativa interna.
- **Filament para configuración, Livewire para operación**: el catálogo de centros, tipos y prestaciones se gestiona en la capa Filament. La prescripción, consulta de disponibilidad y gestión de listas de espera se implementan en la capa Livewire.
- **Historial con fecha_inicio/fecha_fin**: los cambios en `DirectorCentro` siguen el patrón de históricos consistente con el resto de módulos del sistema.

---

## 9. Tests funcionales

Los tests validan el comportamiento del módulo, no su implementación interna. Se escriben como tests de feature en Laravel (`tests/Feature/Modules/Centro/`), usando la base de datos SQLite en memoria según la configuración del proyecto.

### 9.1 Centro y UO

```
CentroUoTest

- un_centro_puede_pertenecer_a_una_uo
  Dado un centro con unidad_organizativa_id válido
  Cuando se accede a $centro->unidadOrganizativa
  Entonces devuelve la UO correcta

- un_centro_puede_existir_sin_uo
  Dado un centro con unidad_organizativa_id null
  Cuando se guarda
  Entonces no hay error de validación

- la_uo_de_un_centro_puede_cambiarse
  Dado un centro asignado a la UO A
  Cuando se actualiza unidad_organizativa_id a la UO B
  Entonces $centro->fresh()->unidadOrganizativa->id === UO_B
```

### 9.2 Ámbito territorial

```
AmbitoTerritorialTest

- un_centro_puede_tener_ambito_ciudad_completa
  Dado un centro sin ámbitos previos
  Cuando se crea un AmbitoTerritorial con tipo = ciudad_completa
  Entonces $centro->ambitosTeritoriales()->count() === 1

- ciudad_completa_no_puede_coexistir_con_otros_ambitos
  Dado un centro con tipo = ciudad_completa
  Cuando se intenta añadir un segundo ámbito de cualquier tipo
  Entonces se lanza una excepción de validación

- un_centro_puede_tener_multiples_ambitos_de_demarcacion
  Dado un centro sin ámbitos
  Cuando se crean dos AmbitoTerritorial de tipo demarcacion_oficial con distintos referencia_id
  Entonces $centro->ambitosTeritoriales()->count() === 2

- un_centro_puede_combinar_tipos_de_ambito
  Dado un centro sin ámbitos
  Cuando se crea un ámbito demarcacion_oficial y otro barrios
  Entonces ambos se guardan sin error

- un_ambito_tipo_poligono_requiere_geojson
  Dado un AmbitoTerritorial con tipo = poligono_gis y geojson null
  Cuando se intenta guardar
  Entonces se lanza una excepción de validación

- eliminar_un_ambito_no_afecta_al_centro
  Dado un centro con dos ámbitos
  Cuando se elimina uno
  Entonces el centro sigue activo y tiene un ámbito
```

### 9.3 Red de centros

```
RedCentrosTest

- una_red_puede_crearse_sin_centros
  Dado los datos mínimos de una red
  Cuando se guarda
  Entonces $red->exists === true y $red->centros()->count() === 0

- un_centro_puede_unirse_a_una_red
  Dado una red y un centro existentes
  Cuando se ejecuta $red->centros()->attach($centro)
  Entonces $red->centros()->count() === 1

- un_centro_puede_pertenecer_a_varias_redes
  Dado un centro y dos redes
  Cuando el centro se une a ambas redes
  Entonces $centro->redes()->count() === 2

- una_red_agrega_plazas_libres_de_sus_centros
  Dado una red con dos centros, cada uno con una colección de plazas con 3 plazas libres
  Cuando se consulta la disponibilidad agregada de la red
  Entonces el total de plazas libres es 6

- una_red_inactiva_no_aparece_en_consultas_de_disponibilidad
  Dado una red con activa = false
  Cuando se consulta disponibilidad de redes activas
  Entonces la red no aparece en los resultados

- desligar_un_centro_de_una_red_no_elimina_el_centro
  Dado una red con un centro
  Cuando se ejecuta $red->centros()->detach($centro)
  Entonces $red->centros()->count() === 0 y el centro sigue existiendo
```

### 9.4 Colección de plazas y disponibilidad

```
ColeccionPlazasTest

- la_capacidad_refleja_el_total_de_plazas
  Dado una colección con capacidad = 10
  Cuando se crean 10 plazas asociadas
  Entonces $coleccion->plazas()->count() === 10

- plazas_disponibles_excluye_ocupadas_y_en_mantenimiento
  Dado una colección con 5 plazas: 2 libres, 2 ocupadas, 1 en mantenimiento
  Cuando se consulta $coleccion->plazasDisponibles()
  Entonces devuelve 2

- una_coleccion_inactiva_no_ofrece_plazas_disponibles
  Dado una colección con activa = false y 3 plazas libres
  Cuando se consulta disponibilidad
  Entonces devuelve 0
```

### 9.5 Prescripción y lista de espera

```
PrescripcionTest

- una_prescripcion_a_coleccion_con_plaza_libre_queda_asignada
  Dado una colección con al menos una plaza libre
  Cuando se crea una prescripción hacia esa colección
  Entonces el estado es asignada y plaza_id no es null

- una_prescripcion_a_coleccion_sin_plazas_entra_en_lista_de_espera
  Dado una colección con todas las plazas ocupadas
  Cuando se crea una prescripción hacia esa colección con modo lista_espera
  Entonces el estado es en_lista_espera y se crea un registro ListaEspera

- al_liberarse_una_plaza_se_genera_alerta_al_tsr_activo
  Dado una prescripción en lista de espera con TSR A
  Y el TSR activo del ciudadano es ahora B (cambio posterior)
  Cuando se marca una plaza como libre
  Entonces la alerta se envía al TSR B, no al TSR A

- la_asignacion_no_es_automatica_al_liberarse_plaza
  Dado una prescripción en lista de espera
  Cuando se libera una plaza
  Entonces el estado de la prescripción sigue siendo en_lista_espera (no asignada)
  Y existe una alerta pendiente de revisión profesional

- cancelar_una_prescripcion_libera_la_plaza
  Dado una prescripción activa con plaza asignada
  Cuando se cancela la prescripción
  Entonces la plaza vuelve a estado libre
```

### 9.6 Inscripción en centro

```
InscripcionCentroTest

- un_ciudadano_puede_inscribirse_en_un_centro
  Dado un ciudadano y un centro con inscripcion_libre = true
  Cuando se crea una InscripcionCentro
  Entonces $inscripcion->activa === true

- la_baja_de_inscripcion_es_siempre_explicita
  Dado una inscripción activa
  Cuando pasa el tiempo sin ninguna acción
  Entonces la inscripción sigue activa (no caduca)

- dar_de_baja_una_inscripcion_la_desactiva
  Dado una inscripción activa
  Cuando se establece fecha_baja y activa = false
  Entonces $inscripcion->activa === false

- actividad_con_flag_requiere_inscripcion_bloquea_sin_inscripcion
  Dado una actividad con requiere_inscripcion_centro = true
  Y un ciudadano sin InscripcionCentro activa en ese centro
  Cuando se intenta crear una prescripción o inscripción a la actividad
  Entonces se lanza una excepción de validación

- actividad_con_flag_requiere_inscripcion_permite_con_inscripcion_activa
  Dado una actividad con requiere_inscripcion_centro = true
  Y un ciudadano con InscripcionCentro activa en ese centro
  Cuando se crea la prescripción o inscripción
  Entonces se crea correctamente sin error
```

### 9.7 Director del centro

```
DirectorCentroTest

- un_centro_tiene_un_unico_director_activo
  Dado un centro con dos registros DirectorCentro: uno con fecha_fin null y otro con fecha_fin pasada
  Cuando se consulta $centro->directorActivo()
  Entonces devuelve el que tiene fecha_fin null

- al_nombrar_nuevo_director_el_anterior_recibe_fecha_fin
  Dado un centro con director activo A
  Cuando se nombra director B
  Entonces el director A tiene fecha_fin = hoy y el director B tiene fecha_fin null

- director_externo_no_puede_tener_profesional_id
  Dado un DirectorCentro con nombre externo y profesional_id relleno simultáneamente
  Cuando se intenta guardar
  Entonces se lanza una excepción de validación
```

---

*Documento elaborado en fase de diseño del proyecto VIDA 360. Versión 1.1 — Mayo 2026.*
