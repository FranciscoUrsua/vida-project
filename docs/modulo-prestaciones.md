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

## Tests funcionales — Módulo Prestaciones

### Convenciones
**Requisito:** sección del documento de diseño que origina el test.
**Dado:** estado del sistema antes de ejecutar el test (fixtures, usuarios, datos).
**Cuando:** acción que se ejecuta.
**Entonces:** resultado esperado que debe verificarse.
Los tests se agrupan por clase de test. Primero los tests de modelo y catálogo, después los de versionado, después los de seeder, después los de interfaz Filament y finalmente los de consulta desde otros módulos.

### Grupo 1 — CatalogoSistema

Requisito de referencia: § 6 — catálogos del sistema.
Valida el modelo `CatalogoSistema` como fuente de valores para selects en el módulo Prestaciones.


**T-PRE-01 — Crear una entrada de catálogo con todos sus campos**
Requisito: § 6 — modelo CatalogoSistema.
Dado: ninguna entrada previa en el grupo `'prestacion.objetivo_general'`.
Cuando: se crea una entrada con `grupo = 'prestacion.objetivo_general'`, `clave = '01'`, `etiqueta = 'Acceso, información y valoración'`, `orden = 1`, `activo = true`.
Entonces: existe exactamente una fila en `catalogos_sistema` con esos valores.

**T-PRE-02 — Constraint único por grupo+clave**
Requisito: § 6 — integridad del catálogo.
Dado: una entrada con `grupo = 'prestacion.competencia'`, `clave = 'municipal'`.
Cuando: se intenta insertar una segunda entrada con el mismo `grupo` y `clave`.
Entonces: se lanza una excepción de constraint único de base de datos.

**T-PRE-03 — opcionesParaSelect devuelve array ordenado por campo `orden`**
Requisito: § 6 — helper para selects en Filament.
Dado: tres entradas en `catalogos_sistema` para el grupo `'prestacion.nivel_atencion'` con órdenes 3, 1, 2.
Cuando: se llama a `CatalogoSistema::opcionesParaSelect('prestacion.nivel_atencion')`.
Entonces: devuelve un array asociativo `[clave => etiqueta]` con exactamente tres entradas, ordenadas por el campo `orden` de menor a mayor.

**T-PRE-04 — opcionesParaSelect excluye entradas inactivas**
Requisito: § 6 — solo valores activos deben aparecer en selects.
Dado: dos entradas en el mismo grupo: una con `activo = true` y otra con `activo = false`.
Cuando: se llama a `CatalogoSistema::opcionesParaSelect()` con ese grupo.
Entonces: el array resultante contiene solo la entrada activa.

**T-PRE-05 — opcionesParaSelect devuelve array vacío para grupo inexistente**
Requisito: § 6 — ausencia de datos no debe lanzar excepción.
Dado: ninguna entrada en `catalogos_sistema` para el grupo `'grupo.inexistente'`.
Cuando: se llama a `CatalogoSistema::opcionesParaSelect('grupo.inexistente')`.
Entonces: devuelve un array vacío sin lanzar excepción.

**T-PRE-06 — Desactivar una entrada no la borra físicamente**
Requisito: § 6 — las bajas son lógicas.
Dado: una entrada con `activo = true`.
Cuando: se actualiza `activo = false`.
Entonces: la fila sigue existiendo en la tabla y puede recuperarse con `CatalogoSistema::find($id)`.

---

### Grupo 2 — PrestacionModel: creación, validaciones y scopes

Requisito de referencia: § 3, § 4 — modelo de datos y scopes.
Valida la creación, validación de enums, campos JSONB y scopes del modelo `Prestacion`.


**T-PRE-07 — Crear una prestación con los campos mínimos obligatorios**
Requisito: § 3 — campos requeridos del catálogo.
Dado: los datos mínimos: `codigo = '010101'`, `nombre = 'Servicio de información'`, `tipo_prestacion = 'servicio'`, `nivel_garantia = 'garantizada'`.
Cuando: se llama a `Prestacion::create([...])`.
Entonces: existe un registro en `prestaciones` con esos valores y `activa = true` por defecto.

**T-PRE-08 — tipo_prestacion solo acepta valores del enum**
Requisito: § 3 — enum `tipo_prestacion` restringido a `servicio` o `economica`.
Dado: una prestación en construcción.
Cuando: se intenta asignar `tipo_prestacion = 'otro_valor'` y se guarda.
Entonces: la operación falla con error de base de datos.

**T-PRE-09 — nivel_garantia solo acepta valores del enum**
Requisito: § 3 — enum `nivel_garantia` restringido a `garantizada` o `condicionada`.
Dado: una prestación en construcción.
Cuando: se intenta asignar `nivel_garantia = 'parcial'` y se guarda.
Entonces: la operación falla con error de base de datos.

**T-PRE-10 — El código de prestación es único**
Requisito: § 3 — integridad del catálogo oficial.
Dado: una prestación con `codigo = '010101'` ya persistida.
Cuando: se intenta crear otra prestación con el mismo `codigo`.
Entonces: se lanza una excepción de constraint único de base de datos.

**T-PRE-11 — `poblacion_destinataria` se almacena y recupera como array**
Requisito: § 3 — campo JSONB con cast `array`.
Dado: una prestación con `poblacion_destinataria = ['infancia', 'familia']`.
Cuando: se recupera con `Prestacion::find($id)`.
Entonces: `$prestacion->poblacion_destinataria` es un array PHP con los valores `'infancia'` y `'familia'`.

**T-PRE-12 — `modalidades` se almacena y recupera como array**
Requisito: § 3 — campo JSONB con cast `array`.
Dado: una prestación con `modalidades = ['presencial', 'telematica']`.
Cuando: se recupera con `Prestacion::find($id)`.
Entonces: `$prestacion->modalidades` es un array PHP con ambos valores.

**T-PRE-13 — scope `activas` filtra solo prestaciones con `activa = true`**
Requisito: § 4 — scope de filtrado por estado activo.
Dado: dos prestaciones: una con `activa = true` y otra con `activa = false`.
Cuando: se ejecuta `Prestacion::activas()->get()`.
Entonces: el resultado contiene solo la prestación activa.

**T-PRE-14 — scope `deServicio` filtra solo tipo servicio**
Requisito: § 4 — scope de filtrado por tipo de prestación.
Dado: una prestación de tipo `'servicio'` y otra de tipo `'economica'`.
Cuando: se ejecuta `Prestacion::deServicio()->get()`.
Entonces: el resultado contiene solo la de tipo `'servicio'`.

**T-PRE-15 — scope `economicas` filtra solo tipo económica**
Requisito: § 4 — scope de filtrado por tipo de prestación.
Dado: una prestación de tipo `'servicio'` y otra de tipo `'economica'`.
Cuando: se ejecuta `Prestacion::economicas()->get()`.
Entonces: el resultado contiene solo la de tipo `'economica'`.

**T-PRE-16 — La baja lógica (`activa = false`) no borra el registro físicamente**
Requisito: § 3 — distinción entre baja lógica funcional y soft delete de Eloquent.
Dado: una prestación activa persistida.
Cuando: se actualiza `activa = false`.
Entonces: la fila sigue existiendo en la tabla y es recuperable directamente por `id`.
Nota: `activa` es un campo de negocio, distinto del soft delete de Eloquent (`deleted_at`). Ambos pueden coexistir.

---

### Grupo 3 — PrestacionTipoCentro: relación con tipos de centro

Requisito de referencia: § 3 — relación entre prestación y tipos de centro que la ofrecen.
Valida la tabla `prestacion_tipo_centro` y sus constraints.


**T-PRE-17 — Una prestación puede tener múltiples tipos de centro**
Requisito: § 3 — relación `hasMany` con tipos de centro.
Dado: una prestación persistida.
Cuando: se crean dos registros en `prestacion_tipo_centro` con `tipo_centro = 'css_general'` y `'centro_dia'`.
Entonces: `$prestacion->tiposCentro()->count()` devuelve 2.

**T-PRE-18 — No pueden existir dos registros con la misma prestación y tipo de centro**
Requisito: § 3 — constraint único en `prestacion_tipo_centro`.
Dado: una prestación con un registro en `prestacion_tipo_centro` para `tipo_centro = 'css_general'`.
Cuando: se intenta insertar un segundo registro con la misma `prestacion_id` y el mismo `tipo_centro`.
Entonces: se lanza una excepción de constraint único de base de datos.

**T-PRE-19 — Borrar físicamente una prestación elimina en cascada sus tipos de centro**
Requisito: § 3 — integridad referencial.
Dado: una prestación con dos registros en `prestacion_tipo_centro`.
Cuando: se borra físicamente la prestación (`forceDelete()`).
Entonces: los registros asociados en `prestacion_tipo_centro` desaparecen de la tabla (cascade FK).
Nota: el soft delete (Eloquent `delete()`) no dispara el cascade de FK. El borrado en cascada solo actúa sobre borrado físico.

---

### Grupo 4 — PrestacionVersionado: trait Versionable

Requisito de referencia: § 5 — historial de cambios mediante trait `Versionable`.
Valida que el trait genera snapshots correctamente al actualizar el modelo `Prestacion`.


**T-PRE-20 — Crear una prestación no genera versión inicial**
Requisito: § 5 — el trait solo actúa en `updating`, no en `creating`.
Dado: ningún registro en `versiones` para Prestacion.
Cuando: se crea una prestación nueva con `Prestacion::create([...])`.
Entonces: no existe ninguna entrada en `versiones` para ese registro.

**T-PRE-21 — Actualizar una prestación genera un snapshot en `versiones`**
Requisito: § 5 — cada `update` genera una versión del estado anterior.
Dado: una prestación con `nombre = 'Nombre original'` persistida.
Cuando: se actualiza `nombre = 'Nombre modificado'`.
Entonces: existe exactamente un registro en `versiones` con `versionable_type = Prestacion::class`, `versionable_id` correcto, y `datos['nombre'] = 'Nombre original'`.

**T-PRE-22 — El snapshot contiene el estado completo anterior, no solo el campo modificado**
Requisito: § 5 — el snapshot es un dump completo de todos los atributos del modelo antes del cambio.
Dado: una prestación con múltiples campos rellenos (`codigo`, `nombre`, `tipo_prestacion`, `nivel_garantia`).
Cuando: se actualiza únicamente el campo `nombre`.
Entonces: el snapshot en `versiones` contiene todos los campos que tenía la prestación antes del cambio.

**T-PRE-23 — Múltiples ediciones generan múltiples versiones**
Requisito: § 5 — una versión por cada `update`.
Dado: una prestación que se edita tres veces consecutivas.
Cuando: se recuperan sus versiones con `$prestacion->versiones()->orderBy('id')->get()`.
Entonces: existen tres registros, y el campo `datos` de cada uno refleja el estado anterior a cada edición respectiva.

**T-PRE-24 — Se puede reconstruir el estado de una prestación en una fecha pasada**
Requisito: § 5 — el historial de versiones permite reconstruir el estado en cualquier momento.
Dado: una prestación editada en T1 (A → B) y luego en T2 (B → C).
Cuando: se consulta la versión con `created_at <= T1`.
Entonces: el snapshot recuperado corresponde al estado A (capturado en T1 al pasar a B).

**T-PRE-25 — Dar de baja una prestación genera una versión con `activa = true` en el snapshot**
Requisito: § 5 — el snapshot preserva el estado anterior, incluido el campo `activa`.
Dado: una prestación con `activa = true`.
Cuando: se actualiza `activa = false`.
Entonces: el snapshot en `versiones` contiene `activa = true` (el estado previo a la baja).

---

### Grupo 5 — PrestacionSeeder: carga inicial e idempotencia

Requisito de referencia: § 8 — seeders del módulo y carga inicial del catálogo.
Valida que los seeders cargan los datos correctamente y pueden ejecutarse múltiples veces sin duplicados.


**T-PRE-26 — El seeder de catálogos carga los ocho objetivos generales**
Requisito: § 8 — `CatalogosSistemaSeeder` carga el grupo `prestacion.objetivo_general`.
Dado: la tabla `catalogos_sistema` vacía.
Cuando: se ejecuta `CatalogosSistemaSeeder`.
Entonces: existen exactamente 8 entradas en el grupo `'prestacion.objetivo_general'` con claves del `'01'` al `'08'`.

**T-PRE-27 — El seeder de catálogos es idempotente**
Requisito: § 8 — el seeder usa `updateOrCreate` y no duplica registros.
Dado: el seeder ya ejecutado una vez.
Cuando: se ejecuta `CatalogosSistemaSeeder` una segunda vez.
Entonces: el número de entradas en `catalogos_sistema` no cambia.

**T-PRE-28 — El seeder de prestaciones carga las prestaciones del catálogo**
Requisito: § 8 — `PrestacionesSeeder` carga las prestaciones del catálogo oficial.
Dado: la tabla `prestaciones` vacía y `catalogos_sistema` ya poblada.
Cuando: se ejecuta `PrestacionesSeeder`.
Entonces: `Prestacion::count()` devuelve 49 (implementación actual; el catálogo completo comprende 112 prestaciones).

**T-PRE-29 — El seeder de prestaciones es idempotente**
Requisito: § 8 — el seeder usa `updateOrCreate` y no duplica registros.
Dado: el seeder de prestaciones ya ejecutado una vez.
Cuando: se ejecuta `PrestacionesSeeder` una segunda vez.
Entonces: `Prestacion::count()` sigue devolviendo el mismo número.

**T-PRE-30 — Todas las prestaciones del seeder tienen los campos obligatorios**
Requisito: § 3, § 8 — integridad de los datos cargados.
Dado: el seeder de prestaciones ejecutado.
Cuando: se consultan todas las prestaciones.
Entonces: ninguna tiene `codigo`, `nombre`, `tipo_prestacion` o `nivel_garantia` nulos o vacíos.

**T-PRE-31 — Los códigos del seeder son únicos**
Requisito: § 3, § 8 — el campo `codigo` es la clave de negocio del catálogo.
Dado: el seeder de prestaciones ejecutado.
Cuando: se agrupan las prestaciones por `codigo`.
Entonces: todos los grupos tienen exactamente un registro (no hay códigos duplicados).

---

### Grupo 6 — PrestacionFilamentResource: CRUD e interfaz de administración

Requisito de referencia: § 7 — interfaz de gestión del catálogo en Filament.
Valida el ciclo CRUD, validaciones del formulario, filtros de tabla y el toggle de estado.


**T-PRE-32 — Un admin puede listar prestaciones en Filament**
Requisito: § 7 — listado del catálogo en el panel de administración.
Dado: un usuario autenticado y cinco prestaciones en la base de datos.
Cuando: se accede a la página de listado del recurso `PrestacionResource`.
Entonces: las cinco prestaciones aparecen en la tabla.

**T-PRE-33 — El listado filtra correctamente por tipo de prestación**
Requisito: § 7 — filtro por `tipo_prestacion` en la tabla.
Dado: un admin autenticado, tres prestaciones de tipo `'servicio'` y dos de tipo `'economica'`.
Cuando: se aplica el filtro `tipo_prestacion = 'economica'`.
Entonces: el listado muestra exactamente dos prestaciones.

**T-PRE-34 — El listado filtra correctamente por `activa`**
Requisito: § 7 — filtro ternario de estado activo/inactivo.
Dado: un admin autenticado, dos prestaciones con `activa = true` y una con `activa = false`.
Cuando: se aplica el filtro `activa = false`.
Entonces: el listado muestra exactamente una prestación.

**T-PRE-35 — Un admin puede crear una prestación desde Filament**
Requisito: § 7 — formulario de creación.
Dado: un admin autenticado y la tabla `prestaciones` vacía.
Cuando: se envía el formulario de creación con datos válidos (código, nombre, tipo, nivel de garantía).
Entonces: existe un registro en `prestaciones` con los datos enviados.

**T-PRE-36 — El formulario rechaza una prestación sin nombre**
Requisito: § 7 — validación de campo requerido.
Dado: un admin autenticado.
Cuando: se envía el formulario de creación sin el campo `nombre`.
Entonces: el formulario devuelve error de validación en `nombre` y no se crea ningún registro.

**T-PRE-37 — El formulario rechaza un código duplicado**
Requisito: § 7 — validación `unique` en el campo `codigo`.
Dado: un admin autenticado y una prestación con `codigo = '010101'` ya existente.
Cuando: se envía el formulario de creación con `codigo = '010101'`.
Entonces: el formulario devuelve error de validación en `codigo` y no se crea un segundo registro.

**T-PRE-38 — Un admin puede editar una prestación desde Filament**
Requisito: § 7 — formulario de edición.
Dado: un admin autenticado y una prestación con `nombre = 'Nombre original'`.
Cuando: se envía el formulario de edición con `nombre = 'Nombre actualizado'`.
Entonces: `$prestacion->fresh()->nombre` devuelve `'Nombre actualizado'`.

**T-PRE-39 — Editar desde Filament genera una versión en `versiones`**
Requisito: § 5, § 7 — el trait `Versionable` actúa al guardar desde Filament.
Dado: un admin autenticado y una prestación existente.
Cuando: se guarda cualquier cambio desde el formulario de edición.
Entonces: se genera al menos un registro en `versiones` para esa prestación.

**T-PRE-40 — El toggle de `activa` en el listado cambia el estado de la prestación**
Requisito: § 7 — `ToggleColumn` inline en la tabla.
Dado: un admin autenticado y una prestación con `activa = true`.
Cuando: se activa el toggle de `activa` en la fila de esa prestación.
Entonces: `$prestacion->fresh()->activa` devuelve `false`.

**T-PRE-41 — La pestaña de historial muestra las versiones de una prestación** _(pendiente)_
Requisito: § 7 — `VersionesRelationManager` en la página de edición.
Dado: un admin autenticado y una prestación editada dos veces.
Cuando: se abre la página de edición y se accede a la sección de historial.
Entonces: se muestran dos entradas de historial.
Pendiente: requiere testar el `RelationManager` anidado en la página de edición. Se implementará cuando se disponga del patrón de test para RelationManagers en Filament 5.

**T-PRE-42 — Un profesional sin rol admin no puede acceder al recurso en Filament** _(pendiente)_
Requisito: § 7 — control de acceso al catálogo de administración.
Dado: un usuario sin rol de administración.
Cuando: se intenta acceder a la URL de gestión del recurso `PrestacionResource`.
Entonces: la respuesta es 403 o redirección a login.
Pendiente: requiere implementar control de acceso basado en roles en `AdminPanelProvider` o mediante `canViewAny` en `PrestacionResource`.

---

### Grupo 7 — PrestacionConsulta: lectura desde otros módulos

Requisito de referencia: § Consumo desde otros módulos.
Valida el consumo del catálogo vía modelo Eloquent, sin interfaz de usuario.


**T-PRE-43 — Se pueden buscar prestaciones por nombre parcial**
Requisito: consumo desde SIA — búsqueda textual en el catálogo.
Dado: tres prestaciones con nombres `'Servicio de información'`, `'Ayuda de urgencia'`, `'Información y orientación'`.
Cuando: se ejecuta `Prestacion::activas()->where('nombre', 'ilike', '%información%')->get()`.
Entonces: el resultado contiene exactamente dos prestaciones.

**T-PRE-44 — Se pueden filtrar prestaciones por objetivo general**
Requisito: consumo desde SIA — filtrado por área temática.
Dado: cuatro prestaciones: dos con `objetivo_general = '01'` y dos con `objetivo_general = '03'`.
Cuando: se consulta `Prestacion::activas()->where('objetivo_general', '01')->get()`.
Entonces: el resultado contiene exactamente dos prestaciones.

**T-PRE-45 — Se puede obtener la lista de tipos de centro de una prestación**
Requisito: consumo desde Centros — relación `tiposCentro`.
Dado: una prestación con dos tipos de centro asociados (`'css_general'` y `'centro_dia'`).
Cuando: se accede a `$prestacion->tiposCentro`.
Entonces: la colección contiene exactamente dos registros con los `tipo_centro` esperados.

**T-PRE-46 — Una prestación inactiva no aparece en consultas con scope `activas`**
Requisito: consumo — el scope `activas` filtra las dadas de baja.
Dado: una prestación con `activa = false`.
Cuando: se ejecuta `Prestacion::activas()->get()`.
Entonces: la prestación inactiva no aparece en los resultados.

**T-PRE-47 — Una prestación inactiva es recuperable directamente por `id`**
Requisito: consumo desde Intervención — los planes históricos deben poder acceder a prestaciones dadas de baja.
Dado: una prestación con `activa = false` e `id` conocido.
Cuando: se ejecuta `Prestacion::find($id)`.
Entonces: se recupera la prestación sin error (la baja lógica no impide la consulta directa).
