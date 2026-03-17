# Módulo de Centros — VIDA 360
## Documento funcional v1.0 · Marzo 2026

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

### 2.2 Red de centros

Una red de centros es una agrupación de centros que comparten un pool de plazas común. Su propósito es operativo: permite al profesional consultar disponibilidad agregada a nivel de red sin necesidad de revisar cada centro individualmente.

La red no tiene personalidad organizativa propia. No existe un gestor de red: cualquier profesional con perfil de intervención puede consultar disponibilidad y gestionar asignaciones en cualquier centro de la red.

Un ejemplo real es la Red Municipal de Atención a Personas Sin Hogar, que agrupa varios centros de acogida. Al prescribir alojamiento desde un plan de intervención, el profesional ve las plazas libres de toda la red y asigna la más adecuada al perfil de la persona.

Un centro puede pertenecer a varias redes simultáneamente.

### 2.3 Colección de plazas

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

### 2.4 Actividades

Un centro puede organizar actividades: talleres, charlas, seminarios, grupos de apoyo, cursos, etc. Las actividades son independientes de las plazas: un centro puede tener solo plazas, solo actividades, o ambas. Un centro sin plazas puede existir si su función es exclusivamente la organización de actividades.

Cada actividad tiene su propio modo de acceso:
- **Libre**: cualquier ciudadano puede inscribirse directamente.
- **Prescripción**: solo acceden personas derivadas desde un plan de intervención.
- **Mixta**: hay aforo reservado para prescripciones y aforo libre para inscripción directa. El control opera por separado para cada cupo.

Las actividades no tienen periodicidad modelada en el sistema. Se materializan en **sesiones** convocadas de forma explícita. La inscripción apunta siempre a una sesión concreta, no a la actividad en abstracto.

VIDA 360 gestiona las inscripciones y el control de aforo por sesión. Todo lo demás (espacios, recursos, control de asistencia, certificados) corresponde a las herramientas propias del centro.

### 2.5 Inscripción en centro

Algunos centros, como los centros municipales de mayores, requieren que el ciudadano esté registrado en el centro antes de acceder a sus actividades. La inscripción es un registro propio, independiente de cualquier actividad o plaza concreta.

El acceso es voluntario y a iniciativa del ciudadano. El centro puede configurarse de dos formas:
- **Inscripción libre**: el ciudadano elige el centro en el que desea registrarse.
- **Inscripción por domicilio**: el ciudadano debe registrarse en el centro que le corresponde según su domicilio.

La inscripción es indefinida: no tiene fecha de caducidad. La baja es siempre explícita.

Cuando una actividad tiene activo el flag `requiere_inscripcion_centro`, el sistema verifica la existencia de una inscripción activa antes de procesar la inscripción o la prescripción.

### 2.6 Prescripción

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
| direccion | string | |
| codigo_postal | string | |
| distrito_id | FK | Catálogo de distritos municipales |
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

### 3.2 Red

Mecanismo de agrupación de centros para pool de plazas común. Sin personalidad organizativa propia.

| Atributo | Tipo | Notas |
|---|---|---|
| id | PK | |
| nombre | string | |
| nombre_corto | string | |
| descripcion | text | Nullable |
| activa | boolean | |
| fecha_alta | date | |
| fecha_baja | date | Nullable |

Relación N:M con `Centro` via tabla pivote `red_centro`.

### 3.3 ColeccionPlazas

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

### 3.4 Espacio

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

### 3.5 Plaza

Unidad mínima asignable a una persona.

| Atributo | Tipo | Notas |
|---|---|---|
| id | PK | |
| espacio_id | FK | |
| nombre | string | Ej: "Cama 1", "Cama 2" |
| estado | enum | `libre` · `ocupada` · `reservada` · `mantenimiento` |
| activa | boolean | |

El estado se mantiene desnormalizado para consultas rápidas de disponibilidad. La ocupación efectiva se rastrea a través de la `Prescripcion` activa que apunta a la plaza.

### 3.6 Actividad

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

### 3.7 SesionActividad

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

### 3.8 InscripcionCentro

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

### 3.9 DirectorCentro

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

### 3.10 ContactoCentro

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

### 3.11 Prescripcion

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

### 3.12 ListaEspera

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

Los tipos de gestión y los modos de acceso se modelan como enums al ser valores de alta estabilidad estructural.

---

## 6. Relación con otros módulos

- **Módulo de Intervención**: la `Prescripcion` se crea en el contexto de un `PlanIntervencion`. Las plazas y actividades son destinos posibles de una prescripción, al mismo nivel que una derivación a atención especializada.
- **Módulo de Organización (UOs)**: los Centros de Servicios Sociales son también Unidades Organizativas. El módulo de Centros añade los datos operativos que no caben en la estructura de UOs: plazas, actividades, inscripciones, directorio de contactos.
- **Módulo de Agenda**: el horario detallado de centros, la gestión de citas y la disponibilidad en tiempo real se diseñarán conjuntamente con el módulo de Agenda.
- **Módulo de Ciudadanía**: `InscripcionCentro` y `Prescripcion` referencian al ciudadano por su identificador interno.
- **Módulo de Usuarios**: `DirectorCentro` puede referenciar a un `Profesional` del módulo de Usuarios.

---

## 7. Decisiones diferidas con rationale

**Gestión presupuestaria de centros privados puros**: los centros de tipo `privado_puro` (pensiones, hoteles) requieren un modelo de gestión del coste por plaza contratada y su distribución presupuestaria anual entre los recursos habilitados. Se difiere a fase posterior por complejidad y dependencia de procesos administrativos externos.

**Horario detallado y disponibilidad en tiempo real**: el campo `horario` en `Centro` se mantiene como JSON para visualización. La gestión completa de franjas horarias, citas y disponibilidad se abordará en el módulo de Agenda para evitar duplicidad de decisiones de diseño.

**Gestión interna de actividades**: VIDA 360 no gestiona espacios físicos, recursos materiales, ediciones, control de asistencia ni emisión de certificados. Esta funcionalidad corresponde a herramientas especializadas de gestión de centros. El límite del sistema es la inscripción y el control de aforo.

**Portal ciudadano**: la inscripción en centros y en actividades de libre acceso puede tener en el futuro un flujo de autoservicio desde el portal ciudadano. El modelo actual no anticipa este canal pero tampoco lo impide: `InscripcionCentro` no tiene dependencia del canal de creación.

---

## 8. Principios de diseño aplicados

Este módulo aplica los principios transversales de VIDA 360 documentados en `principios.md`. Se destacan los más relevantes:

- **Sin valores de negocio hardcodeados**: tipos de espacio, tipos de actividad, segmentos de población y prestaciones son configurables desde backoffice.
- **Diferimiento explícito sobre ambigüedad**: las funcionalidades no maduras están identificadas y documentadas con su rationale.
- **Separación de responsabilidades**: VIDA 360 gestiona la prescripción y el seguimiento; los sistemas propios de cada centro gestionan su operativa interna.
- **Filament para configuración, Livewire para operación**: el catálogo de centros, tipos y prestaciones se gestiona en la capa Filament. La prescripción, consulta de disponibilidad y gestión de listas de espera se implementan en la capa Livewire.
- **Historial con fecha_inicio/fecha_fin**: los cambios en `DirectorCentro` siguen el patrón de históricos consistente con el resto de módulos del sistema.

---

*Documento elaborado en fase de diseño del proyecto VIDA 360. Versión 1.0 — Marzo 2026.*
