# Módulo Prestaciones

## Propósito

El módulo `Prestaciones` mantiene el **catálogo oficial de prestaciones** del sistema de servicios sociales municipal. Es la fuente de verdad sobre qué prestaciones existen, qué condiciones tienen y cómo se accede a ellas.

Su función en el sistema es exclusivamente de **referencia**: los demás módulos (SIA, Intervención, Centros) consultan el catálogo pero no lo modifican. El mantenimiento del catálogo se realiza íntegramente desde backoffice (Filament).

Lo que este módulo **no hace**:
- No gestiona la tramitación administrativa de prestaciones económicas (responsabilidad de aplicación externa de gestión de expedientes).
- No gestiona plazas, ocupación ni recursos físicos (→ módulo Centros).
- No gestiona la ejecución de prestaciones concedidas ni su seguimiento (→ módulo Intervención y módulo Integraciones para proveedores externos).

---

## Estructura del catálogo

El catálogo de referencia es la **Guía de Prestaciones de Servicios Sociales y Educativos del Ayuntamiento de Madrid** (edición 2024), que recoge 112 prestaciones organizadas en ocho objetivos generales. Aunque VIDA toma este catálogo como punto de partida, el diseño permite que cualquier entidad municipal configure su propio catálogo sin dependencia de la estructura madrileña.

### Jerarquía de códigos

El catálogo se organiza en tres niveles jerárquicos:

```
01          → Objetivo general       (Acceso, información y valoración)
  0101      → Categoría específica   (Información)
    010101  → Prestación concreta    (Servicio de información, valoración, orientación y asesoramiento)
```

El código es un identificador del catálogo de referencia externo, no la clave primaria interna del sistema. Se almacena como dato y puede actualizarse si el catálogo de referencia lo modifica.

---

## Modelo de datos

### Tabla principal: `prestaciones`

```sql
prestaciones
──────────────────────────────────────────────────────────────────
id                          bigint PK
codigo                      varchar(10)     — código jerárquico del catálogo (010101...)
nombre                      varchar(255)
tipo_prestacion             enum('servicio','economica')
nivel_garantia              enum('garantizada','condicionada')
objetivo_general            varchar(10)     — clave de catalogos_sistema (grupo: 'prestacion.objetivo_general')
categoria_especifica        varchar(10)     — clave de catalogos_sistema (grupo: 'prestacion.categoria')
nivel_atencion              varchar(50)     — clave de catalogos_sistema (grupo: 'prestacion.nivel_atencion')
competencia                 varchar(50)     — clave de catalogos_sistema (grupo: 'prestacion.competencia')
forma_gestion               varchar(50)     — clave de catalogos_sistema (grupo: 'prestacion.forma_gestion')
financiacion                varchar(50)     — clave de catalogos_sistema (grupo: 'prestacion.financiacion')
poblacion_destinataria      jsonb           — array de claves de catalogos_sistema
modalidades                 jsonb           — array: ['presencial','telefonica','telematica']
ambito_territorial          varchar(255)
finalidad                   text
descripcion                 text
requisitos                  text
procedimiento               text
compatibilidad              text
condiciones_acceso          text            — criterios de prioridad y derechos específicos
obligaciones                text
aportacion_usuario          varchar(255)    — 'gratuito', importe o descripción libre
plazo_concesion             varchar(255)
duracion_maxima             varchar(255)
proveedor                   varchar(255)
normativa                   text
estándares_calidad          text
informacion_complementaria  varchar(500)    — URL
activa                      boolean default true
created_at
updated_at
```

**Sobre los enums:** `tipo_prestacion` y `nivel_garantia` son enums PHP/migración porque el sistema actúa de forma diferente según su valor: el tipo determina si la prestación implica tramitación económica externa; el nivel de garantía afecta a cómo se presenta la prestación al ciudadano y a su lógica de acceso. Cualquier otro campo clasificatorio usa `catalogos_sistema` (ver principio 3.10).

**Sobre los campos de texto libre:** los campos descriptivos (finalidad, descripción, requisitos, procedimiento, etc.) se almacenan como texto sin estructurar adicional. Su contenido proviene directamente de las fichas del catálogo y puede actualizarse desde Filament. No se normalizan en subtablas porque no existe lógica de negocio que opere sobre su contenido interno.

**Sobre `poblacion_destinataria`:** es multivalor (una prestación puede dirigirse a varios colectivos simultáneamente) y sus valores son puramente clasificatorios, por lo que se almacena como array JSONB. Las claves corresponden a entradas de `catalogos_sistema` con grupo `'prestacion.poblacion'`.

### Tabla de relación con tipos de centro: `prestacion_tipo_centro`

Registra qué tipos de centro pueden prestar cada prestación. Esta información se extrae del campo "Tipo de centro o recurso de atención" de cada ficha del catálogo.

```sql
prestacion_tipo_centro
──────────────────────────────────────────────────────────────────
id              bigint PK
prestacion_id   bigint FK → prestaciones.id
tipo_centro     varchar(100)    — clave de catalogos_sistema (grupo: 'centro.tipo')
```

El módulo Centros consume esta tabla para filtrar qué prestaciones puede ofrecer un centro dado, y el SIA la consume para orientar al ciudadano hacia el recurso adecuado.

---

## Versionado histórico

El catálogo evoluciona: prestaciones que se modifican, se fusionan o se dan de baja. La trazabilidad histórica es imprescindible para que Intervención pueda reconstruir con exactitud cómo era una prestación en el momento en que se incluyó en un plan.

Se aplica la **estrategia transversal de versionado** ya definida en el proyecto: tabla polimórfica `versiones` con snapshots JSON completos.

Cada vez que se guarda un cambio en una prestación desde Filament, se genera automáticamente un snapshot del estado anterior completo antes de aplicar los cambios. El snapshot incluye todos los campos de `prestaciones` y el array de `prestacion_tipo_centro` asociado en ese momento.

```sql
versiones (tabla transversal, ya definida en modulo-usuarios-permisos.md)
──────────────────────────────────────────────────────────────────
id
versionable_type    — 'Prestacion'
versionable_id      — id de la prestación
snapshot            jsonb   — estado completo en el momento del cambio
motivo              varchar(255)   — descripción del cambio (opcional, recomendable)
usuario_id          FK → usuarios.id
created_at
```

**Reconstrucción histórica:** para saber cómo era la prestación X en la fecha Y, se recupera el snapshot cuyo `created_at` es inmediatamente anterior a Y. Si no existe snapshot anterior a Y, el estado actual es el vigente desde el origen.

**Baja lógica:** cuando una prestación se da de baja, `activa` pasa a `false` y se genera un snapshot final. La prestación sigue siendo consultable en su último estado para mantener la coherencia de los planes históricos que la referencian.

---

## Catálogos del sistema (`catalogos_sistema`)

Los campos clasificatorios de las prestaciones se gestionan como entradas de la tabla `catalogos_sistema` (ver principio 3.10). Los grupos relevantes para este módulo son:

| Grupo | Descripción | Valores iniciales (catálogo Madrid 2024) |
|---|---|---|
| `prestacion.objetivo_general` | Los 8 objetivos generales de la Guía | 01 Acceso/información, 02 Inclusión, 03 Familia/infancia, 04 Autonomía, 05 Dependencia, 06 Urgencia/VG, 07 Comunitario, 08 Sociocultural |
| `prestacion.categoria` | Subcategorías dentro de cada objetivo | 0101 Información, 0102 Informes, 0103 Orientación especializada, etc. |
| `prestacion.nivel_atencion` | Dónde se presta | asp, especializada, no_aplica |
| `prestacion.competencia` | Administración competente | municipal, autonomica, estatal, compartida |
| `prestacion.forma_gestion` | Cómo se gestiona | directa, indirecta, mixta |
| `prestacion.financiacion` | Fuente de financiación | local, autonomica, cofinanciada |
| `prestacion.poblacion` | Colectivos destinatarios | poblacion_general, infancia, juventud, familia, exclusion, discapacidad, vg, lgtbi, mayores, dependencia |
| `centro.tipo` | Tipos de centro/recurso | css_general, centro_dia, residencia, servicio_especifico, recurso_educativo, etc. |

Los valores iniciales de cada grupo se cargan mediante seeders al instalar el sistema. Cada entidad puede ampliarlos o ajustar etiquetas desde el backoffice de Filament sin necesidad de desarrollo.

---

## Backoffice Filament

Se crea un recurso Filament `PrestacionResource` con las siguientes pantallas:

**Listado:** tabla con columnas `codigo`, `nombre`, `tipo_prestacion`, `nivel_garantia`, `objetivo_general` (etiqueta desde catálogo), `nivel_atencion`, `activa`. Filtros por objetivo general, tipo, nivel de garantía, nivel de atención y estado activo/inactivo. Búsqueda por código y nombre.

**Formulario de creación/edición:** organizado en secciones:
- *Identificación*: código, nombre, tipo (enum select), nivel de garantía (enum select).
- *Clasificación*: objetivo general, categoría específica, nivel de atención, competencia, forma de gestión, financiación, población destinataria (multiselect), modalidades (multiselect). Todos los selects se poblan desde `catalogos_sistema`.
- *Tipos de centro*: repeater o multiselect de `prestacion_tipo_centro` con los tipos definidos en `catalogos_sistema`.
- *Descripción*: finalidad, descripción, requisitos, procedimiento (campos de texto con editor básico).
- *Condiciones*: condiciones de acceso, obligaciones, compatibilidad con otras prestaciones, aportación del usuario.
- *Gestión y normativa*: plazo de concesión, duración máxima, proveedor, normativa, estándares de calidad.
- *Territorial y acceso*: ámbito territorial, información complementaria (URL).

**Vista de historial:** para cada prestación, pestaña adicional que muestra la lista de versiones con fecha, usuario y motivo del cambio. Permite ver el snapshot completo de cualquier versión anterior en modo lectura.

**Gestión de catálogos:** pantalla de configuración general en Filament que centraliza `catalogos_sistema`. Permite añadir, editar, reordenar y desactivar valores de cualquier grupo. Accesible solo para el rol de administrador funcional. Se integra con la pantalla de configuración general del sistema donde también se gestionan otros parámetros como nombre de la entidad o municipio.

---

## Consumo desde otros módulos

El módulo expone sus datos a través del modelo Eloquent `Prestacion`. No se define una API REST interna específica para este módulo: el acceso desde otros módulos Laravel se realiza directamente mediante el modelo.

**Desde SIA:** el profesional busca prestaciones por nombre, objetivo o colectivo para orientar al ciudadano. La búsqueda opera sobre prestaciones con `activa = true`. Se muestra la información descriptiva de la ficha (finalidad, requisitos, procedimiento, URL).

**Desde Intervención:** al incluir una prestación en un plan, se referencia `prestacion_id`. En el momento de la prescripción se guarda también un snapshot de la prestación en el registro del plan (o se resuelve por consulta a `versiones` usando la fecha del plan). Esto garantiza que el historial del plan siempre refleje la prestación tal como era cuando se prescribió.

**Desde Centros:** el módulo Centros consume `prestacion_tipo_centro` para determinar qué prestaciones puede ofrecer cada centro y gestionar su disponibilidad de plazas.

---

## Decisiones diferidas

**Importación masiva del catálogo:** la carga inicial de las 112 prestaciones de Madrid se puede hacer mediante seeder o mediante una funcionalidad de importación desde Excel/CSV en Filament. Se difiere la decisión sobre la herramienta concreta, pero el seeder de datos iniciales es obligatorio para el despliegue base.

**Relaciones de compatibilidad e incompatibilidad entre prestaciones:** la ficha del catálogo incluye un campo textual de compatibilidad. Si en el futuro se necesita validación automática de incompatibilidades al prescribir (por ejemplo, impedir prescribir dos prestaciones mutuamente excluyentes), se requeriría una tabla `prestacion_incompatibilidades`. Se difiere hasta que Intervención identifique casos concretos que lo justifiquen.

**Vigencia temporal de prestaciones:** el modelo actual no incluye fechas de inicio y fin de vigencia a nivel de registro (solo baja lógica). Si el catálogo evolucionase con prestaciones de vigencia acotada (convocatorias anuales, por ejemplo), se añadirían `vigencia_desde` y `vigencia_hasta`. Se difiere hasta que el caso de uso concreto lo requiera.
