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

## # Tests funcionales: Módulo Prestaciones

## Convenciones del proyecto

- **Framework:** PHPUnit con atributo `#[Test]`. No usar Pest.
- **Base de datos:** PostgreSQL (`vida_testing`). No usar SQLite.
- **Ubicación:** `tests/Feature/Modules/Prestaciones/`
- **Patrón:** Given / When / Then. Cada test describe comportamiento observable desde fuera, no detalles de implementación.
- **Negativo obligatorio:** los tests de restricciones de dominio deben verificarse también en negativo (el test debe fallar si se elimina la validación que protege).
- **Factories:** crear factories para `Prestacion`, `PrestacionTipoCentro` y `CatalogoSistema` si no existen. Para seeders de producción usar `Model::create()` con datos explícitos.

---

## Actores reutilizados

Definir en un `setUp()` base o trait compartido:

- `$admin` — usuario con rol `adm_sistema`, acceso completo al panel Filament.
- `$gestor` — usuario con rol de gestión funcional, puede editar el catálogo pero no configuración del sistema.
- `$profesional` — usuario operativo (trabajador social), acceso solo lectura al catálogo.

---

## Clase 1: `CatalogoSistemaTest`

Valida el modelo `CatalogoSistema` y su uso como fuente de valores para selects.

---

```
se_puede_crear_una_entrada_de_catalogo_con_todos_sus_campos
```
- **Dado** ninguna entrada previa en el grupo `'prestacion.objetivo_general'`.
- **Cuando** se crea una entrada con `grupo = 'prestacion.objetivo_general'`, `clave = '01'`, `etiqueta = 'Acceso, información y valoración'`, `orden = 1`, `activo = true`.
- **Entonces** existe exactamente una fila en `catalogos_sistema` con esos valores.

---

```
no_pueden_existir_dos_entradas_con_el_mismo_grupo_y_clave
```
- **Dado** una entrada con `grupo = 'prestacion.competencia'`, `clave = 'municipal'`.
- **Cuando** se intenta insertar una segunda entrada con el mismo `grupo` y `clave`.
- **Entonces** se lanza una excepción de constraint único de base de datos.

---

```
opciones_para_select_devuelve_array_clave_etiqueta_ordenado
```
- **Dado** tres entradas en `catalogos_sistema` para el grupo `'prestacion.nivel_atencion'` con órdenes 3, 1, 2.
- **Cuando** se llama a `CatalogoSistema::opcionesParaSelect('prestacion.nivel_atencion')`.
- **Entonces** devuelve un array asociativo `[clave => etiqueta]` con exactamente tres entradas, ordenadas por el campo `orden` de menor a mayor.

---

```
opciones_para_select_excluye_entradas_inactivas
```
- **Dado** dos entradas en el mismo grupo: una con `activo = true` y otra con `activo = false`.
- **Cuando** se llama a `CatalogoSistema::opcionesParaSelect()` con ese grupo.
- **Entonces** el array resultante contiene solo la entrada activa.

---

```
opciones_para_select_devuelve_array_vacio_para_grupo_inexistente
```
- **Dado** ninguna entrada en `catalogos_sistema` para el grupo `'grupo.inexistente'`.
- **Cuando** se llama a `CatalogoSistema::opcionesParaSelect('grupo.inexistente')`.
- **Entonces** devuelve un array vacío sin lanzar excepción.

---

```
desactivar_una_entrada_no_la_borra_fisicamente
```
- **Dado** una entrada con `activo = true`.
- **Cuando** se actualiza `activo = false`.
- **Entonces** la fila sigue existiendo en la tabla y puede recuperarse con `CatalogoSistema::withoutGlobalScopes()->find($id)`.

---

## Clase 2: `PrestacionModelTest`

Valida la creación, validación de enums, relaciones y scopes del modelo `Prestacion`.

---

```
se_puede_crear_una_prestacion_con_los_campos_minimos_obligatorios
```
- **Dado** los datos mínimos: `codigo = '010101'`, `nombre = 'Servicio de información'`, `tipo_prestacion = 'servicio'`, `nivel_garantia = 'garantizada'`.
- **Cuando** se llama a `Prestacion::create([...])`.
- **Entonces** existe un registro en `prestaciones` con esos valores y `activa = true` por defecto.

---

```
tipo_prestacion_solo_acepta_valores_del_enum
```
- **Dado** una prestación en construcción.
- **Cuando** se intenta asignar `tipo_prestacion = 'otro_valor'` y se guarda.
- **Entonces** la operación falla con error de base de datos o validación antes de persistir.

---

```
nivel_garantia_solo_acepta_valores_del_enum
```
- **Dado** una prestación en construcción.
- **Cuando** se intenta asignar `nivel_garantia = 'parcial'` y se guarda.
- **Entonces** la operación falla con error de base de datos o validación antes de persistir.

---

```
el_codigo_de_prestacion_es_unico
```
- **Dado** una prestación con `codigo = '010101'` ya persistida.
- **Cuando** se intenta crear otra prestación con el mismo `codigo`.
- **Entonces** se lanza una excepción de constraint único de base de datos.

---

```
poblacion_destinataria_se_almacena_y_recupera_como_array
```
- **Dado** una prestación con `poblacion_destinataria = ['infancia', 'familia']`.
- **Cuando** se recupera con `Prestacion::find($id)`.
- **Entonces** `$prestacion->poblacion_destinataria` es un array PHP con los valores `'infancia'` y `'familia'`.

---

```
modalidades_se_almacena_y_recupera_como_array
```
- **Dado** una prestación con `modalidades = ['presencial', 'telematica']`.
- **Cuando** se recupera con `Prestacion::find($id)`.
- **Entonces** `$prestacion->modalidades` es un array PHP con ambos valores.

---

```
scope_activas_filtra_solo_prestaciones_con_activa_true
```
- **Dado** dos prestaciones: una con `activa = true` y otra con `activa = false`.
- **Cuando** se ejecuta `Prestacion::activas()->get()`.
- **Entonces** el resultado contiene solo la prestación activa.

---

```
scope_de_servicio_filtra_solo_tipo_servicio
```
- **Dado** una prestación de tipo `'servicio'` y otra de tipo `'economica'`.
- **Cuando** se ejecuta `Prestacion::deServicio()->get()`.
- **Entonces** el resultado contiene solo la de tipo `'servicio'`.

---

```
scope_economicas_filtra_solo_tipo_economica
```
- **Dado** una prestación de tipo `'servicio'` y otra de tipo `'economica'`.
- **Cuando** se ejecuta `Prestacion::economicas()->get()`.
- **Entonces** el resultado contiene solo la de tipo `'economica'`.

---

```
la_baja_logica_no_borra_el_registro_fisicamente
```
- **Dado** una prestación activa persistida.
- **Cuando** se actualiza `activa = false`.
- **Entonces** la fila sigue existiendo en la tabla y es recuperable por ID sin soft delete adicional.

> **Nota:** `activa` es un campo de negocio (baja lógica funcional), distinto del soft delete de Eloquent (`deleted_at`). Ambos pueden coexistir: una prestación puede estar inactiva funcionalmente y aun así no estar soft-deleted. Verificar que la relación entre ambos campos es coherente.

---

## Clase 3: `PrestacionTipoCentroTest`

Valida la relación entre prestaciones y tipos de centro.

---

```
una_prestacion_puede_tener_multiples_tipos_de_centro
```
- **Dado** una prestación persistida.
- **Cuando** se crean dos registros en `prestacion_tipo_centro` con `tipo_centro = 'css_general'` y `tipo_centro = 'centro_dia'` para esa prestación.
- **Entonces** `$prestacion->tiposCentro()->count()` devuelve 2.

---

```
no_pueden_existir_dos_registros_con_la_misma_prestacion_y_tipo_centro
```
- **Dado** una prestación con un registro en `prestacion_tipo_centro` para `tipo_centro = 'css_general'`.
- **Cuando** se intenta insertar un segundo registro con la misma `prestacion_id` y el mismo `tipo_centro`.
- **Entonces** se lanza una excepción de constraint único de base de datos.

---

```
eliminar_una_prestacion_elimina_en_cascada_sus_tipos_de_centro
```
- **Dado** una prestación con dos registros en `prestacion_tipo_centro`.
- **Cuando** se elimina la prestación (soft delete).
- **Entonces** los registros asociados en `prestacion_tipo_centro` ya no son accesibles para la prestación eliminada.

> **Aclaración para la implementación:** verificar si el cascadeOnDelete actúa sobre soft deletes o solo sobre borrado físico. Si actúa solo en borrado físico, los registros huérfanos de `prestacion_tipo_centro` deben manejarse de forma explícita (observer o evento del modelo).

---

## Clase 4: `PrestacionVersionadoTest`

Valida que el trait `Versionable` funciona correctamente sobre el modelo `Prestacion`.

---

```
crear_una_prestacion_no_genera_version_inicial
```
- **Dado** ningún registro en `versiones` para Prestacion.
- **Cuando** se crea una prestación nueva con `Prestacion::create([...])`.
- **Entonces** no existe ninguna entrada en `versiones` para ese registro (el trait captura el estado *anterior*, y en la creación no hay estado anterior).

> **Verificar:** revisar el comportamiento actual del trait `Versionable` en el proyecto para `created`. Si el trait sí genera snapshot en created, ajustar este test a la convención del proyecto.

---

```
actualizar_una_prestacion_genera_un_snapshot_en_versiones
```
- **Dado** una prestación con `nombre = 'Nombre original'` persistida.
- **Cuando** se actualiza `nombre = 'Nombre modificado'`.
- **Entonces** existe exactamente un registro en `versiones` con `versionable_type` = clase de `Prestacion`, `versionable_id` = id de la prestación, y el campo `datos` contiene el JSON con `nombre = 'Nombre original'`.

---

```
el_snapshot_contiene_el_estado_completo_anterior_no_solo_el_campo_modificado
```
- **Dado** una prestación con múltiples campos rellenos (al menos `codigo`, `nombre`, `tipo_prestacion`, `nivel_garantia`).
- **Cuando** se actualiza únicamente el campo `nombre`.
- **Entonces** el snapshot en `versiones` contiene todos los campos que tenía la prestación antes del cambio, no solo `nombre`.

---

```
multiples_ediciones_generan_multiples_versiones_ordenadas_cronologicamente
```
- **Dado** una prestación que se edita tres veces consecutivas.
- **Cuando** se recuperan sus versiones con `$prestacion->versions()->orderBy('created_at')->get()`.
- **Entonces** existen tres registros, y el campo `datos` de cada uno refleja el estado anterior a cada edición respectiva.

---

```
se_puede_reconstruir_el_estado_de_una_prestacion_en_una_fecha_pasada
```
- **Dado** una prestación editada en `T1` (estado A → estado B) y luego editada en `T2` (estado B → estado C).
- **Cuando** se consulta la versión vigente en un instante entre `T1` y `T2`.
- **Entonces** el snapshot recuperado corresponde al estado B (el más reciente anterior al instante consultado).

---

```
dar_de_baja_una_prestacion_genera_una_version_con_activa_true_en_el_snapshot
```
- **Dado** una prestación con `activa = true`.
- **Cuando** se actualiza `activa = false`.
- **Entonces** se genera un snapshot en `versiones` que contiene `activa = true` (el estado anterior).

---

## Clase 5: `PrestacionSeederTest`

Valida que los seeders cargan los datos correctamente y son idempotentes.

---

```
el_seeder_de_catalogos_carga_los_ocho_objetivos_generales
```
- **Dado** la tabla `catalogos_sistema` vacía.
- **Cuando** se ejecuta `CatalogosSistemaSeeder`.
- **Entonces** existen exactamente 8 entradas en el grupo `'prestacion.objetivo_general'` con claves del `'01'` al `'08'`.

---

```
el_seeder_de_catalogos_es_idempotente
```
- **Dado** el seeder ya ejecutado una vez.
- **Cuando** se ejecuta `CatalogosSistemaSeeder` una segunda vez.
- **Entonces** el número de entradas en `catalogos_sistema` no cambia (no se duplican registros).

---

```
el_seeder_de_prestaciones_carga_las_112_prestaciones
```
- **Dado** la tabla `prestaciones` vacía y `catalogos_sistema` ya poblada.
- **Cuando** se ejecuta `PrestacionesSeeder`.
- **Entonces** `Prestacion::count()` devuelve 112.

---

```
el_seeder_de_prestaciones_es_idempotente
```
- **Dado** el seeder de prestaciones ya ejecutado una vez.
- **Cuando** se ejecuta `PrestacionesSeeder` una segunda vez.
- **Entonces** `Prestacion::count()` sigue devolviendo 112.

---

```
todas_las_prestaciones_del_seeder_tienen_codigo_nombre_tipo_y_nivel_garantia
```
- **Dado** el seeder de prestaciones ejecutado.
- **Cuando** se consultan todas las prestaciones.
- **Entonces** ninguna prestación tiene `codigo`, `nombre`, `tipo_prestacion` o `nivel_garantia` nulos o vacíos.

---

```
los_codigos_del_seeder_son_unicos
```
- **Dado** el seeder de prestaciones ejecutado.
- **Cuando** se agrupa por `codigo` y se cuenta.
- **Entonces** todos los grupos tienen exactamente un registro (no hay códigos duplicados).

---

## Clase 6: `PrestacionFilamentResourceTest`

Valida el comportamiento del recurso Filament para la gestión del catálogo. Usar `Livewire::test()` o el helper de Filament para simular la interacción con el panel.

---

```
un_admin_puede_listar_prestaciones_en_filament
```
- **Dado** `$admin` autenticado y cinco prestaciones en la base de datos.
- **Cuando** se accede a la página de listado del recurso `PrestacionResource`.
- **Entonces** la respuesta HTTP es 200 y las cinco prestaciones aparecen en la tabla.

---

```
un_admin_puede_crear_una_prestacion_desde_filament
```
- **Dado** `$admin` autenticado y la tabla `prestaciones` vacía.
- **Cuando** se envía el formulario de creación con datos válidos (código, nombre, tipo, nivel de garantía).
- **Entonces** existe un registro en `prestaciones` con los datos enviados.

---

```
el_formulario_de_filament_rechaza_una_prestacion_sin_nombre
```
- **Dado** `$admin` autenticado.
- **Cuando** se envía el formulario de creación sin el campo `nombre`.
- **Entonces** el formulario devuelve un error de validación en el campo `nombre` y no se crea ningún registro.

---

```
el_formulario_de_filament_rechaza_un_codigo_duplicado
```
- **Dado** `$admin` autenticado y una prestación con `codigo = '010101'` ya existente.
- **Cuando** se envía el formulario de creación con `codigo = '010101'`.
- **Entonces** el formulario devuelve error de validación en `codigo` y no se crea un segundo registro.

---

```
un_admin_puede_editar_una_prestacion_desde_filament
```
- **Dado** `$admin` autenticado y una prestación existente con `nombre = 'Nombre original'`.
- **Cuando** se envía el formulario de edición con `nombre = 'Nombre actualizado'`.
- **Entonces** `$prestacion->fresh()->nombre` devuelve `'Nombre actualizado'`.

---

```
editar_desde_filament_genera_una_version_en_versiones
```
- **Dado** `$admin` autenticado y una prestación existente.
- **Cuando** se guarda cualquier cambio desde el formulario de edición de Filament.
- **Entonces** se genera al menos un registro en `versiones` para esa prestación.

---

```
el_listado_de_filament_filtra_correctamente_por_tipo_prestacion
```
- **Dado** `$admin` autenticado, tres prestaciones de tipo `'servicio'` y dos de tipo `'economica'`.
- **Cuando** se aplica el filtro `tipo_prestacion = 'economica'` en el listado.
- **Entonces** el listado muestra exactamente dos prestaciones.

---

```
el_listado_de_filament_filtra_correctamente_por_activa
```
- **Dado** `$admin` autenticado, dos prestaciones con `activa = true` y una con `activa = false`.
- **Cuando** se aplica el filtro `activa = false` en el listado.
- **Entonces** el listado muestra exactamente una prestación.

---

```
el_toggle_de_activa_en_el_listado_cambia_el_estado_de_la_prestacion
```
- **Dado** `$admin` autenticado y una prestación con `activa = true`.
- **Cuando** se activa el toggle inline de `activa` en la fila de esa prestación.
- **Entonces** `$prestacion->fresh()->activa` devuelve `false`.

---

```
la_pestaña_de_historial_muestra_las_versiones_de_una_prestacion
```
- **Dado** `$admin` autenticado y una prestación que ha sido editada dos veces (tiene dos entradas en `versiones`).
- **Cuando** se abre la página de edición y se accede a la pestaña o sección de historial.
- **Entonces** se muestran dos entradas de historial con sus fechas correspondientes.

un_profesional_no_puede_acceder_al_recurso_de_gestion_en_filament
```
- **Dado** `$profesional` autenticado (sin rol de administración).
- **Cuando** se intenta acceder a la URL de gestión del recurso `PrestacionResource` en Filament.
- **Entonces** la respuesta es 403 o redirección a login/unauthorized.

---

## Clase 7: `PrestacionConsultaTest`

Valida el consumo del catálogo desde otros módulos (lectura directa vía modelo Eloquent).

**se_pueden_buscar_prestaciones_por_nombre_parcial**
```
- **Dado** tres prestaciones con nombres `'Servicio de información'`, `'Ayuda de urgencia'`, `'Información y orientación'`.
- **Cuando** se ejecuta `Prestacion::activas()->where('nombre', 'like', '%información%')->get()`.
- **Entonces** el resultado contiene exactamente dos prestaciones.

**se_pueden_filtrar_prestaciones_por_objetivo_general**
```
- **Dado** cuatro prestaciones: dos con `objetivo_general = '01'` y dos con `objetivo_general = '03'`.
- **Cuando** se consulta `Prestacion::activas()->where('objetivo_general', '01')->get()`.
- **Entonces** el resultado contiene exactamente dos prestaciones.

**se_puede_obtener_la_lista_de_tipos_de_centro_de_una_prestacion**
```
- **Dado** una prestación con dos tipos de centro asociados (`'css_general'` y `'centro_dia'`).
- **Cuando** se accede a `$prestacion->tiposCentro`.
- **Entonces** la colección contiene exactamente dos registros con los `tipo_centro` esperados.

**una_prestacion_inactiva_no_aparece_en_consultas_con_scope_activas**
```
- **Dado** una prestación con `activa = false`.
- **Cuando** se ejecuta `Prestacion::activas()->get()`.
- **Entonces** la prestación inactiva no aparece en los resultados.

**una_prestacion_inactiva_es_recuperable_directamente_por_id**
```
- **Dado** una prestación con `activa = false` y `id` conocido.
- **Cuando** se ejecuta `Prestacion::find($id)`.
- **Entonces** se recupera la prestación sin error (la baja lógica no impide la consulta directa).
Este test es importante para garantizar que Intervención puede seguir accediendo a prestaciones históricas dadas de baja al reconstruir un plan antiguo.
