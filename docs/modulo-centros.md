# Módulo de Centros y Servicios — VIDA 360
## Documento funcional v1.3 · Junio 2026

> **Cambios respecto a v1.2**: se añaden `slug` y `activo` a `TipoActividad`. Se incorpora
> la entidad `Sala` como espacio funcional de un centro, independiente de la jerarquía de
> plazas (`ColeccionPlazas → Espacio`). Se añade `sala_id` en `SesionActividad`.
> Se actualizan la sección de catálogo backoffice y las decisiones diferidas.

---

## 1. Introducción y propósito del módulo

El módulo gestiona los dos tipos de recursos que un profesional puede asignar a un ciudadano desde un plan de intervención: **centros** y **servicios**.

Un **centro** es un equipamiento físico que atiende presencialmente al ciudadano — ofrece plazas, organiza actividades, tiene horario y personal en un lugar concreto.

Un **servicio** es un nodo de tramitación — gestiona prestaciones administrativas que se resuelven como expedientes, dentro o fuera de VIDA 360, sin atención presencial directa en el recurso. El ciudadano no acude al servicio; el servicio tramita en su nombre.

Ambos comparten su presencia en el catálogo de recursos y su papel como destino de una acción dentro del plan de intervención, pero su naturaleza operativa es distinta y se modelan por separado.

El módulo no gestiona la operativa interna de centros (espacios, asistencia, certificados) ni la tramitación interna de servicios (expedientes, resoluciones). VIDA 360 gestiona lo relevante para el profesional: qué hay disponible, cómo asignarlo, y cómo hacer seguimiento.

---

## 2. Conceptos clave — Centros

### 2.1 Centro

Un centro es la entidad raíz de la parte de equipamientos del módulo. Se define por los siguientes rasgos:

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

- **Toda la ciudad**: el centro atiende sin restricción territorial.
- **Demarcación oficial**: el centro atiende a ciudadanos de un distrito o conjunto de distritos concretos.
- **Barrios o secciones censales**: dos centros se reparten un distrito por unidades sub-distritales.
- **Polígono GIS**: el ámbito queda definido por una geometría geográfica precisa.

El modelo almacena el ámbito como una colección de registros `AmbitoTerritorial` asociados al centro. Esto permite combinar tipos (p. ej., tres distritos completos más dos barrios concretos de un cuarto distrito).

> **Nota de diseño**: la consulta espacial avanzada (qué centro atiende esta dirección) se difiere al módulo de Integraciones. Los polígonos GIS se almacenan como GeoJSON sin capacidad de consulta espacial nativa hasta entonces.

### 2.4 Red de centros

Una red de centros agrupa centros que exponen sus plazas de forma consolidada. Su propósito es operativo: permite al profesional ver disponibilidad agregada de todos sus centros miembros sin revisarlos individualmente.

La red no tiene personalidad organizativa propia ni personal asignado. Es un agregador. Un centro puede pertenecer a varias redes simultáneamente.

### 2.5 Colección de plazas

Las plazas se agrupan en colecciones dentro de cada centro por tipo y modo de acceso. Un mismo centro puede tener varias colecciones.

Jerarquía dentro de una colección:
- **Espacio**: unidad física (dormitorio, habitación, módulo). Tipo, capacidad, accesibilidad, restricción de género.
- **Plaza**: unidad mínima asignable a una persona. Una cama doble es un espacio con dos plazas, no una plaza con capacidad dos.

Tipos de plaza: `pernocta` · `dia`.

Modos de acceso (por colección, no por centro): `libre` · `prescripcion_directa` · `prescripcion_lista_espera`.

### 2.6 Actividades

Un centro puede organizar actividades (talleres, charlas, seminarios, grupos de apoyo, cursos). Son independientes de las plazas; un centro puede tener solo actividades.

Modos de acceso: `libre` · `prescripcion` · `mixta`.

Las actividades se materializan en **sesiones** convocadas explícitamente. La inscripción apunta siempre a una sesión concreta. VIDA 360 gestiona inscripciones y control de aforo por sesión; la gestión operativa interna (asistencia, certificados) corresponde al centro.

Cada sesión puede asociarse a una **sala** del centro donde se celebra. La sala es informativa: VIDA 360 no gestiona disponibilidad ni conflictos de reserva (esto corresponde al módulo de Agenda).

### 2.7 Inscripción en centro

Algunos centros requieren que el ciudadano esté registrado antes de acceder a sus actividades. La inscripción es indefinida y la baja es siempre explícita. El centro se configura como inscripción libre o por adscripción territorial.

### 2.8 Prescripción de centros

Una prescripción vincula a un ciudadano con una colección de plazas o una sesión de actividad desde un plan de intervención. Puede realizarla el profesional de primaria o el de especializada.

Estados: `pendiente` → `en_lista_espera` → `asignada` → `activa` → `finalizada` / `cancelada`.

Cuando no hay disponibilidad, la persona entra en lista de espera. Al liberarse una plaza, el sistema genera una alerta al TSR activo del ciudadano en ese momento. **La asignación nunca es automática.**

---

## 3. Conceptos clave — Servicios

### 3.1 Servicio

Un servicio es un nodo de tramitación de prestaciones. A diferencia del centro, no tiene presencia física relevante para el ciudadano: la atención no se produce en el recurso sino que el servicio gestiona el expediente en nombre del ciudadano.

Características definitorias:

- Pertenece a una UO (**obligatorio**, no nullable). Su dirección de referencia es la de su UO.
- Tramita una o más prestaciones del catálogo. El conjunto de prestaciones que tramita un servicio es su razón de ser.
- Tiene profesionales asignados, pero sin gestión de agenda ni control de carga de trabajo en VIDA 360.
- Tiene un responsable con un cargo definido a nivel de servicio (ver 3.2).
- No tiene plazas, espacios, actividades, ni inscripciones de ciudadanos.

Cuando un TSR asigna desde un plan de intervención una prestación que corresponde a un servicio, se genera una `SolicitudServicio` dirigida a ese servicio. El responsable del servicio ve la solicitud en su bandeja. La tramitación posterior puede ocurrir dentro de VIDA 360 (como expediente administrativo) o fuera (derivación a otra administración); en ambos casos el seguimiento corresponde al módulo de Intervención, no a este módulo.

### 3.2 Responsable del servicio

Cada servicio tiene un cargo definido a nivel de servicio: el nombre del cargo es un atributo del servicio, no del profesional que lo ocupa. El cargo pertenece al servicio; el profesional lo asume al ser nombrado responsable y lo deja al cesar.

Ejemplo: "Jefe de Servicio de Ayuda a Domicilio" es el cargo del Servicio de Ayuda a Domicilio. Quien ocupe ese servicio ostentará ese título mientras lo dirija.

El historial de responsables sigue el patrón de `DirectorCentro`: registro con `fecha_inicio` y `fecha_fin`, siendo el activo el que tiene `fecha_fin = null`. A diferencia de `DirectorCentro`, el responsable del servicio es siempre un profesional de VIDA 360 (no hay figura de contacto externo).

### 3.3 SolicitudServicio

Cuando un TSR prescribe una prestación asociada a un servicio, se genera automáticamente una `SolicitudServicio`. Esta entidad:

- Referencia al ciudadano, al plan de intervención, a la prestación solicitada y al servicio destinatario.
- Tiene un estado que refleja el avance de la tramitación.
- Es visible para el responsable del servicio y para los profesionales asignados al mismo.
- Las anotaciones de seguimiento (actualizaciones de estado, notas de tramitación) **no pertenecen a este módulo**: son hechos de la historia social del ciudadano y se gestionan en el módulo de Intervención. Una anotación de un profesional del servicio sobre una solicitud genera una alerta al TSR vía módulo de Mensajes.

Estados de `SolicitudServicio`: `pendiente` · `en_tramite` · `resuelta` · `denegada` · `derivada_externa`.

> **Nota de diseño**: la distinción entre tramitación interna (expediente en VIDA) y tramitación externa (derivación a otra administración) no afecta al modelo de `SolicitudServicio` en este módulo. Ambos casos producen el mismo objeto; la diferencia se refleja en el estado y en las anotaciones del módulo de Intervención.

---

## 4. Modelo de datos — Centros

### 4.1 Centro

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
| horario | json | Solo para visualización. Gestión completa en módulo de Agenda |
| inscripcion_libre | boolean | `true` = libre elección · `false` = adscripción por domicilio |
| activo | boolean | |
| fecha_alta | date | |
| fecha_baja | date | Nullable |
| notas | text | Nullable |

Relaciones N:M: `centro_segmento_poblacion` · `centro_prestacion` · `red_centro`.
Relaciones 1:N: `ambitos_territoriales` · `colecciones_plazas` · `actividades` · `directores` · `contactos` · `inscripciones`.

> El campo `distrito_id` presente en versiones anteriores queda eliminado. El distrito se expresa como `AmbitoTerritorial` de tipo `demarcacion_oficial`.

### 4.2 AmbitoTerritorial

| Atributo | Tipo | Notas |
|---|---|---|
| id | PK | |
| centro_id | FK | |
| tipo | enum | `ciudad_completa` · `demarcacion_oficial` · `barrios` · `secciones_censales` · `poligono_gis` |
| descripcion | string | Nombre legible. Ej: "Distrito de Vallecas" |
| referencia_id | integer | Nullable. ID de la entidad referenciada según tipo |
| referencia_tipo | string | Nullable. Ej: `Distrito`, `Barrio` |
| geojson | json | Nullable. Solo para tipo `poligono_gis` |

Reglas: `ciudad_completa` no puede coexistir con otros ámbitos del mismo centro. `poligono_gis` requiere `geojson`.

### 4.3 Red

| Atributo | Tipo | Notas |
|---|---|---|
| id | PK | |
| nombre | string | |
| nombre_corto | string | |
| descripcion | text | Nullable |
| activa | boolean | |
| fecha_alta | date | |
| fecha_baja | date | Nullable |

Relación N:M con `Centro` via `red_centro`.

### 4.4 ColeccionPlazas

| Atributo | Tipo | Notas |
|---|---|---|
| id | PK | |
| centro_id | FK | |
| nombre | string | |
| tipo_plaza | enum | `pernocta` · `dia` |
| modo_acceso | enum | `libre` · `prescripcion_directa` · `prescripcion_lista_espera` |
| capacidad | integer | |
| activa | boolean | |
| fecha_alta | date | |
| fecha_baja | date | Nullable |
| notas | text | Nullable |

### 4.5 Espacio

| Atributo | Tipo | Notas |
|---|---|---|
| id | PK | |
| coleccion_plazas_id | FK | |
| nombre | string | |
| tipo_espacio_id | FK | Catálogo backoffice |
| capacidad | integer | |
| planta | string | Nullable |
| accesible | boolean | |
| genero | enum | `mixto` · `mujeres` · `hombres`. Nullable |
| activo | boolean | |
| notas | text | Nullable |

### 4.6 Plaza

| Atributo | Tipo | Notas |
|---|---|---|
| id | PK | |
| espacio_id | FK | |
| nombre | string | |
| estado | enum | `libre` · `ocupada` · `reservada` · `mantenimiento` |
| activa | boolean | |

Estado desnormalizado para consultas rápidas. La ocupación efectiva se rastrea via `Prescripcion` activa.

### 4.7 Sala

Espacio funcional de un centro (aula, sala de reuniones, despacho, polivalente...).
Es una entidad distinta de `Espacio`, que pertenece a la jerarquía de alojamiento
(`ColeccionPlazas → Espacio → Plaza`). Las salas no tienen plazas asignables; se
referencian desde las sesiones de actividad como dato informativo de ubicación.

| Atributo     | Tipo    | Notas                         |
|--------------|---------|-------------------------------|
| id           | PK      |                               |
| centro_id    | FK      |                               |
| nombre       | string  |                               |
| descripcion  | text    | Nullable                      |
| capacidad    | integer | Nullable. Personas, no plazas |
| accesible    | boolean | default false                 |
| activa       | boolean | default true                  |
| notas        | text    | Nullable                      |

### 4.8 Actividad

| Atributo | Tipo | Notas |
|---|---|---|
| id | PK | |
| centro_id | FK | |
| nombre | string | |
| tipo_actividad_id | FK | Catálogo backoffice |
| descripcion | text | Nullable |
| modo_acceso | enum | `libre` · `prescripcion` · `mixta` |
| aforo_total | integer | Nullable |
| aforo_prescripcion | integer | Nullable. Solo si `modo_acceso = mixta` |
| requiere_inscripcion_centro | boolean | |
| activa | boolean | |
| fecha_alta | date | |
| fecha_baja | date | Nullable |
| notas | text | Nullable |

### 4.9 SesionActividad

| Atributo | Tipo | Notas |
|---|---|---|
| id | PK | |
| actividad_id | FK | |
| fecha | date | |
| hora_inicio | time | |
| hora_fin | time | Nullable |
| aforo_total | integer | Nullable. Sobreescribe el de la actividad |
| aforo_prescripcion | integer | Nullable. Sobreescribe el de la actividad |
| estado  | enum | `programada` · `celebrada` · `cancelada`                 |
| sala_id | FK   | Nullable. FK → salas.id. Sala donde se celebra la sesión |
| notas   | text | Nullable                                                 |

### 4.10 InscripcionCentro

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

### 4.11 DirectorCentro

| Atributo | Tipo | Notas |
|---|---|---|
| id | PK | |
| centro_id | FK | |
| profesional_id | FK | Nullable. Si es usuario de VIDA 360 |
| nombre | string | Nullable. Si es persona externa |
| telefono | string | Nullable. Si es persona externa |
| email | string | Nullable. Si es persona externa |
| fecha_inicio | date | |
| fecha_fin | date | Nullable. Activo si null |
| notas | text | Nullable |

`profesional_id` y campos de contacto externo son mutuamente excluyentes. Validación a nivel de aplicación.

### 4.12 ContactoCentro

| Atributo | Tipo | Notas |
|---|---|---|
| id | PK | |
| centro_id | FK | |
| nombre | string | |
| rol | string | |
| telefono | string | Nullable |
| email | string | Nullable |
| activo | boolean | |
| notas | text | Nullable |

### 4.13 Prescripcion

| Atributo | Tipo | Notas |
|---|---|---|
| id | PK | |
| profesional_id | FK | |
| ciudadano_id | FK | |
| plan_intervencion_id | FK | Nullable |
| tipo_destino | enum | `coleccion_plazas` · `sesion_actividad` |
| destino_id | integer | FK polimórfica según `tipo_destino` |
| plaza_id | FK | Nullable. Plaza concreta asignada |
| estado | enum | `pendiente` · `en_lista_espera` · `asignada` · `activa` · `finalizada` · `cancelada` |
| fecha_prescripcion | date | |
| fecha_asignacion | date | Nullable |
| fecha_inicio | date | Nullable |
| fecha_fin | date | Nullable |
| motivo_cancelacion | text | Nullable |
| notas | text | Nullable |

### 4.14 ListaEspera

| Atributo | Tipo | Notas |
|---|---|---|
| id | PK | |
| prescripcion_id | FK | |
| coleccion_plazas_id | FK | Nullable. Lista a nivel de colección |
| red_id | FK | Nullable. Lista a nivel de red |
| posicion | integer | Se recalcula ante movimientos |
| fecha_entrada | datetime | |
| fecha_alerta | datetime | Nullable |
| profesional_alerta_id | FK | TSR activo en el momento de la alerta |
| estado | enum | `activa` · `asignada` · `cancelada` |

`coleccion_plazas_id` y `red_id` son mutuamente excluyentes.

---

## 5. Modelo de datos — Servicios

### 5.1 Servicio

| Atributo | Tipo | Notas |
|---|---|---|
| id | PK | |
| nombre | string | |
| nombre_corto | string | |
| unidad_organizativa_id | FK | Obligatorio. No nullable |
| cargo_nombre | string | Nombre del cargo del responsable. Ej: "Jefe de Servicio de Ayuda a Domicilio" |
| descripcion | text | Nullable |
| activo | boolean | |
| fecha_alta | date | |
| fecha_baja | date | Nullable |
| notas | text | Nullable |

Relaciones N:M: `servicio_prestacion` → catálogo de prestaciones.
Relaciones 1:N: `responsables` · `profesionales` · `solicitudes`.

La dirección de referencia del servicio se obtiene de su UO. No tiene dirección propia.

### 5.2 ResponsableServicio

Historial de responsables del servicio. El cargo es fijo en el servicio; lo que cambia es el profesional que lo ocupa.

| Atributo | Tipo | Notas |
|---|---|---|
| id | PK | |
| servicio_id | FK | |
| profesional_id | FK | Siempre un profesional de VIDA 360. No nullable |
| fecha_inicio | date | |
| fecha_fin | date | Nullable. Activo si null |
| notas | text | Nullable |

El registro activo es el que tiene `fecha_fin = null`. Solo puede haber un responsable activo por servicio. Al nombrar uno nuevo, el anterior recibe `fecha_fin = hoy`.

A diferencia de `DirectorCentro`, no existe la figura de responsable externo: el responsable de un servicio es siempre un profesional con cuenta en VIDA 360.

### 5.3 ProfesionalServicio (tabla pivote)

Profesionales asignados al servicio. Sin atributos de carga ni agenda.

| Atributo | Tipo | Notas |
|---|---|---|
| servicio_id | FK | |
| profesional_id | FK | |
| fecha_alta | date | |
| fecha_baja | date | Nullable |

Un profesional puede estar asignado a varios servicios simultáneamente.

### 5.4 SolicitudServicio

Generada automáticamente cuando un TSR prescribe desde un plan de intervención una prestación asociada a un servicio.

| Atributo | Tipo | Notas |
|---|---|---|
| id | PK | |
| servicio_id | FK | |
| ciudadano_id | FK | |
| profesional_id | FK | TSR que genera la solicitud |
| plan_intervencion_id | FK | Nullable |
| prestacion_id | FK | Prestación solicitada |
| estado | enum | `pendiente` · `en_tramite` · `resuelta` · `denegada` · `derivada_externa` |
| fecha_solicitud | date | |
| fecha_resolucion | date | Nullable |
| notas | text | Nullable. Nota inicial del TSR |

Las anotaciones de seguimiento posteriores (del TSR o de los profesionales del servicio) pertenecen al módulo de Intervención como hechos de la historia social del ciudadano. Una anotación de un profesional del servicio genera una alerta al TSR vía módulo de Mensajes.

---

## 6. Tablas pivote y relaciones N:M

| Tabla pivote | Propósito |
|---|---|
| `red_centro` | Vincula redes con centros. |
| `centro_segmento_poblacion` | Segmentos de población atendidos por el centro. |
| `centro_prestacion` | Prestaciones ofrecidas por el centro. |
| `servicio_prestacion` | Prestaciones tramitadas por el servicio. Referencia simple al catálogo. |

---

## 7. Catálogo y configuración backoffice

| Entidad catálogo | Descripción |
|---|---|
| `TipoEspacio` | Tipos de espacio físico: dormitorio individual, compartido, familiar... |
| `TipoActividad` | Tipos de actividad: taller, charla, seminario, grupo de apoyo... Campos: `id`, `nombre`, `slug` (único, obligatorio), `descripcion`, `activo`. |
| `SegmentoPoblacion` | Colectivos atendidos: PSH, mayores, VVG, menores, discapacidad... |
| `Prestacion` | Catálogo transversal. Compartido con módulo de Intervención y módulo de Prestaciones. |

Gestionables desde Filament (backoffice): `Centro`, `Red`, `ColeccionPlazas`, `Sala`, `Servicio`, y todos los catálogos anteriores.

---

## 8. Relación con otros módulos

- **Módulo de Intervención**: `Prescripcion` y `SolicitudServicio` se crean en el contexto de un `PlanIntervencion`. Las anotaciones de seguimiento sobre solicitudes de servicio son hechos de la historia social y pertenecen a Intervención.
- **Módulo de Organización (UOs)**: centros y servicios pertenecen a UOs. Los servicios heredan la dirección de su UO.
- **Módulo de Mensajes**: las anotaciones de profesionales de servicio sobre solicitudes generan alertas al TSR vía este módulo.
- **Módulo de Agenda**: además del horario detallado de centros y la gestión de citas, la gestión de disponibilidad de `Sala` (detección de conflictos de reserva) se diseñará en este módulo.
- **Módulo de Ciudadanía**: `InscripcionCentro`, `Prescripcion` y `SolicitudServicio` referencian al ciudadano.
- **Módulo de Usuarios**: `DirectorCentro`, `ResponsableServicio` y `ProfesionalServicio` referencian a `Profesional`.
- **Módulo de Integraciones**: consulta espacial avanzada sobre `AmbitoTerritorial`; tramitación externa de solicitudes de servicio.
- **Módulo de Prestaciones**: `servicio_prestacion` referencia el catálogo. Los atributos de cada prestación (plazos, requisitos, documentación) se enriquecen en ese módulo.

---

## 9. Decisiones diferidas con rationale

**Gestión presupuestaria de centros privados puros**: coste por plaza contratada y distribución presupuestaria anual. Pendiente de diseño por dependencia de procesos administrativos externos.

**Horario detallado**: el campo `horario` en `Centro` es JSON para visualización. La gestión completa va en el módulo de Agenda.

**Gestión interna de actividades**: asistencia y certificados corresponden a herramientas especializadas del centro. VIDA 360 gestiona inscripciones, control de aforo y referencia de sala por sesión.

**Disponibilidad de salas**: VIDA 360 almacena la sala asociada a cada sesión como dato informativo, pero no gestiona disponibilidad ni detecta conflictos de reserva entre sesiones que usen la misma sala. Esta funcionalidad corresponde al módulo de Agenda. La validación de que el aforo de la sala sea suficiente para el número de inscritos queda a criterio del profesional que programa la sesión.

**Consulta espacial GIS**: los polígonos en `AmbitoTerritorial` se almacenan como GeoJSON. La consulta «qué centro atiende esta dirección» se difiere al módulo de Integraciones.

**Tramitación interna vs. externa de solicitudes de servicio**: ambos casos producen el mismo objeto `SolicitudServicio`. La diferencia se refleja en el estado y en las anotaciones del módulo de Intervención. El mecanismo de integración con sistemas externos de tramitación corresponde al módulo de Integraciones.

**Carga de trabajo y asignación en servicios**: en esta versión VIDA 360 no gestiona la distribución de solicitudes entre los profesionales del servicio ni su carga de trabajo. El responsable del servicio ve la bandeja y gestiona la asignación fuera del sistema.

**Portal ciudadano**: inscripciones en centros y actividades de libre acceso podrían tener flujo de autoservicio en el futuro. El modelo actual no lo impide.

---

## 10. Principios de diseño aplicados

- **Sin valores de negocio hardcodeados**: tipos de espacio, actividad, segmentos y prestaciones son configurables desde backoffice.
- **Separación de responsabilidades**: las anotaciones sobre solicitudes de servicio pertenecen a Intervención (son hechos de la historia social), no a este módulo.
- **Entidades paralelas con naturaleza distinta**: `Centro` y `Servicio` son recursos del mismo catálogo pero con modelos operativos diferentes. No se fuerza una jerarquía artificial entre ellos.
- **Filament para configuración, Livewire para operación**.
- **Historial con fecha_inicio/fecha_fin**: patrón consistente en `DirectorCentro` y `ResponsableServicio`.

---

## 11. Tests funcionales

Los tests validan comportamiento, no implementación interna. Se escriben como tests de feature en Laravel (`tests/Feature/Modules/Centro/`), usando **PostgreSQL** (base de datos configurada en `.env.testing`). Usan el trait `RefreshDatabase`.

### 11.1 Centro y UO

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

### 11.2 Ámbito territorial

```
AmbitoTerritorialTest

- un_centro_puede_tener_ambito_ciudad_completa
  Dado un centro sin ámbitos previos
  Cuando se crea un AmbitoTerritorial con tipo = ciudad_completa
  Entonces $centro->ambitosTeritoriales()->count() === 1

- ciudad_completa_no_puede_coexistir_con_otros_ambitos
  Dado un centro con tipo = ciudad_completa
  Cuando se intenta añadir un segundo ámbito de cualquier tipo
  Entonces se lanza InvalidArgumentException

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
  Entonces se lanza InvalidArgumentException

- eliminar_un_ambito_no_afecta_al_centro
  Dado un centro con dos ámbitos
  Cuando se elimina uno
  Entonces el centro sigue activo y tiene un ámbito
```

### 11.3 Red de centros

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
  Cuando el centro se une a ambas
  Entonces $centro->redes()->count() === 2

- una_red_agrega_plazas_libres_de_sus_centros
  Dado una red con dos centros, cada uno con 3 plazas libres
  Cuando se consulta la disponibilidad agregada de la red
  Entonces el total es 6

- una_red_inactiva_no_aparece_en_consultas_de_disponibilidad
  Dado una red con activa = false
  Cuando se consulta disponibilidad de redes activas
  Entonces la red no aparece

- desligar_un_centro_de_una_red_no_elimina_el_centro
  Dado una red con un centro
  Cuando se ejecuta $red->centros()->detach($centro)
  Entonces el centro sigue existiendo
```

### 11.4 Colección de plazas y disponibilidad

```
ColeccionPlazasTest

- la_capacidad_refleja_el_total_de_plazas
  Dado una colección con capacidad = 10 y 10 plazas asociadas
  Entonces $coleccion->plazas()->count() === 10

- plazas_disponibles_excluye_ocupadas_y_en_mantenimiento
  Dado una colección con 2 libres, 2 ocupadas, 1 en mantenimiento
  Cuando se consulta $coleccion->plazasDisponibles()
  Entonces devuelve 2

- una_coleccion_inactiva_no_ofrece_plazas_disponibles
  Dado una colección con activa = false y 3 plazas libres
  Cuando se consulta disponibilidad
  Entonces devuelve 0
```

### 11.5 Prescripción y lista de espera

```
PrescripcionTest

- una_prescripcion_a_coleccion_con_plaza_libre_queda_asignada
  Dado una colección con al menos una plaza libre
  Cuando se crea una prescripción
  Entonces estado = asignada y plaza_id != null

- una_prescripcion_a_coleccion_sin_plazas_entra_en_lista_de_espera
  Dado una colección con todas las plazas ocupadas
  Cuando se crea una prescripción con modo lista_espera
  Entonces estado = en_lista_espera y existe registro ListaEspera

- al_liberarse_una_plaza_se_genera_alerta_al_tsr_activo
  Dado una prescripción en lista de espera con TSR A
  Y el TSR activo del ciudadano es ahora B
  Cuando se libera una plaza
  Entonces la alerta se envía a B, no a A

- la_asignacion_no_es_automatica_al_liberarse_plaza
  Dado una prescripción en lista de espera
  Cuando se libera una plaza
  Entonces estado sigue siendo en_lista_espera
  Y existe una alerta pendiente de revisión

- cancelar_una_prescripcion_libera_la_plaza
  Dado una prescripción activa con plaza asignada
  Cuando se cancela
  Entonces la plaza vuelve a estado libre
```

### 11.6 Inscripción en centro

```
InscripcionCentroTest

- un_ciudadano_puede_inscribirse_en_un_centro
  Dado un ciudadano y un centro con inscripcion_libre = true
  Cuando se crea una InscripcionCentro
  Entonces $inscripcion->activa === true

- la_baja_de_inscripcion_es_siempre_explicita
  Dado una inscripción activa sin acción posterior
  Entonces la inscripción sigue activa (no caduca)

- dar_de_baja_una_inscripcion_la_desactiva
  Dado una inscripción activa
  Cuando se establece fecha_baja y activa = false
  Entonces $inscripcion->activa === false

- actividad_con_flag_requiere_inscripcion_bloquea_sin_inscripcion
  Dado una actividad con requiere_inscripcion_centro = true
  Y un ciudadano sin InscripcionCentro activa
  Cuando se intenta prescribir o inscribir
  Entonces se lanza InvalidArgumentException

- actividad_con_flag_requiere_inscripcion_permite_con_inscripcion_activa
  Dado una actividad con requiere_inscripcion_centro = true
  Y un ciudadano con InscripcionCentro activa
  Cuando se crea la prescripción
  Entonces se crea sin error
```

### 11.7 Director del centro

```
DirectorCentroTest

- un_centro_tiene_un_unico_director_activo
  Dado dos DirectorCentro: uno con fecha_fin null y otro con fecha_fin pasada
  Cuando se consulta $centro->directorActivo()
  Entonces devuelve el que tiene fecha_fin null

- al_nombrar_nuevo_director_el_anterior_recibe_fecha_fin
  Dado un centro con director activo A
  Cuando se nombra director B
  Entonces A tiene fecha_fin = hoy y B tiene fecha_fin null

- director_externo_no_puede_tener_profesional_id
  Dado un DirectorCentro con nombre externo y profesional_id relleno
  Cuando se intenta guardar
  Entonces se lanza InvalidArgumentException
```

### 11.8 Servicio

```
ServicioTest

- un_servicio_requiere_uo
  Dado un Servicio sin unidad_organizativa_id
  Cuando se intenta guardar
  Entonces se lanza un error de validación

- un_servicio_tiene_cargo_definido
  Dado un Servicio con cargo_nombre = "Jefe de Servicio de Ayuda a Domicilio"
  Cuando se accede a $servicio->cargo_nombre
  Entonces devuelve el valor correcto

- un_servicio_puede_tener_multiples_prestaciones
  Dado un Servicio y tres Prestacion del catálogo
  Cuando se asocian las tres al servicio
  Entonces $servicio->prestaciones()->count() === 3

- un_servicio_no_tiene_plazas_ni_actividades
  Dado un Servicio creado correctamente
  Entonces no tiene método coleccionesPlazas ni actividades

- un_profesional_puede_asignarse_a_varios_servicios
  Dado un Profesional y dos Servicios
  Cuando se asigna el profesional a ambos servicios
  Entonces el profesional aparece en $servicioA->profesionales y en $servicioB->profesionales
```

### 11.9 Responsable de servicio

```
ResponsableServicioTest

- un_servicio_tiene_un_unico_responsable_activo
  Dado dos ResponsableServicio para el mismo servicio:
    uno con fecha_fin null y otro con fecha_fin pasada
  Cuando se consulta $servicio->responsableActivo()
  Entonces devuelve el que tiene fecha_fin null

- al_nombrar_nuevo_responsable_el_anterior_recibe_fecha_fin
  Dado un servicio con responsable activo A
  Cuando se nombra responsable B mediante $servicio->nombrarResponsable($profesionalB)
  Entonces A tiene fecha_fin = hoy y B tiene fecha_fin null

- el_responsable_de_servicio_siempre_es_profesional_vida360
  Dado un ResponsableServicio con profesional_id null
  Cuando se intenta guardar
  Entonces se lanza un error de validación

- el_cargo_del_responsable_se_toma_del_servicio
  Dado un Servicio con cargo_nombre = "Jefe de Departamento X"
  Y un ResponsableServicio activo
  Cuando se consulta el cargo del responsable
  Entonces devuelve "Jefe de Departamento X" (del servicio, no del profesional)
```

### 11.10 Solicitud de servicio

```
SolicitudServicioTest

- prescribir_prestacion_de_servicio_genera_solicitud
  Dado un Servicio que tramita la Prestacion P
  Y un TSR con un plan de intervención activo para un ciudadano
  Cuando el TSR prescribe la Prestacion P
  Entonces se crea una SolicitudServicio con estado = pendiente
    dirigida al servicio correcto

- la_solicitud_referencia_prestacion_ciudadano_y_profesional
  Dado una SolicitudServicio creada
  Cuando se accede a sus relaciones
  Entonces $solicitud->prestacion, $solicitud->ciudadano y $solicitud->profesional
    devuelven los objetos correctos

- el_estado_de_una_solicitud_puede_actualizarse
  Dado una SolicitudServicio con estado = pendiente
  Cuando se actualiza a en_tramite
  Entonces $solicitud->fresh()->estado === 'en_tramite'

- una_solicitud_resuelta_registra_fecha_resolucion
  Dado una SolicitudServicio pendiente
  Cuando se actualiza a resuelta
  Entonces $solicitud->fecha_resolucion === today()

- cancelar_plan_no_elimina_solicitudes_existentes
  Dado un plan de intervención con una SolicitudServicio asociada
  Cuando se cancela el plan
  Entonces la solicitud sigue existiendo con su estado actual
```

---

*Documento elaborado en fase de diseño del proyecto VIDA 360. Versión 1.3 — Junio 2026.*
