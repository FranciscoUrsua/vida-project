# Actualización de `docs/modulo-intervencion.md` — Sección 4

> **Instrucción para CLI:** reemplazar el contenido de la sección 4 completa
> (`## 4. Entidad: Valoración` hasta el inicio de `## 5. Entidad: Plan de Intervención`)
> con el texto que sigue. No modificar nada fuera de esos límites.

---

## 4. Entidad: Valoración

La valoración recoge el diagnóstico estructurado de la situación del ciudadano. Es una
entidad separada de la entrevista porque tiene su propio ciclo de vida: puede completarse
en varias sesiones, puede revisarse posteriormente y existe en múltiples instancias a lo
largo del tiempo — una por cada momento de recogida de datos.

**No toda entrevista genera valoración.**

### 4.1 Atributos

- `id`
- `historia_id` (FK)
- `entrevista_id` (FK, nullable — la entrevista que la origina)
- `profesional_id` (FK)
- `tipo_valoracion_id` (FK — referencia a la configuración de backoffice)
- `fecha`
- `estado` (enum: `borrador`, `completada`, `revisada`)
- `resumen` (texto libre, opcional — síntesis profesional de la valoración)
- `created_at`, `updated_at`

### 4.2 Sistema configurable de tipos y fichas

El modelo de valoración es completamente configurable desde el backoffice. No está
hardcodeado. Esto permite adoptar nuevas herramientas metodológicas sin desarrollo,
definir valoraciones específicas por servicio especializado, y pilotar nuevas metodologías
en centros o profesionales concretos sin afectar al resto del sistema.

**Tres niveles de configuración:**

*Nivel 1 — Estructura de fichas y campos* (`tipo_ficha`): define qué fichas existen, qué
campos tiene cada ficha, el tipo de cada campo (numérico, texto, select, escala, fecha,
booleano), cuáles son obligatorios y en qué orden aparecen.

*Nivel 2 — Lógica condicional* (dentro del schema JSON de `tipo_ficha`): reglas simples
del tipo `[campo_origen, valor_condición, acción, campo_destino]` que permiten ocultar
secciones irrelevantes según respuestas anteriores.

*Nivel 3 — Composición de valoraciones* (`tipo_valoracion_fichas`): define qué fichas
componen cada tipo de valoración, en qué orden, y cuáles son obligatorias u optativas en
ese contexto.

**Tablas de configuración en backoffice:**

`tipo_valoracion`: `id`, `nombre`, `contexto` (ASP, especializada_mayores,
especializada_familia...), `descripcion`.

`tipo_ficha`: `id`, `nombre`, `descripcion`, `schema` (JSON con definición completa de
campos), `activo`.

`tipo_valoracion_fichas`: tabla pivote con `tipo_valoracion_id`, `tipo_ficha_id`, `orden`,
`obligatoria`.

**Tabla de datos reales:**

`fichas`: ver sección 4.5.

### 4.3 Frontera de la configurabilidad

La configuración de fichas y campos define estructura y presentación. No puede definir
lógica de negocio con consecuencias: qué prestaciones se activan automáticamente, qué
alertas de riesgo se disparan, qué derivaciones son obligatorias. Esa lógica vive en
código, con sus tests, referenciando los tipos de ficha por identificador estable.

### 4.4 Filosofía de captura de datos

El modelo prioriza la calidad de los datos sobre su exhaustividad. Cada ficha tiene un
núcleo estructurado mínimo —los campos verdaderamente necesarios para decisiones o
prestaciones concretas— y un campo `notas` de texto libre para el contexto que no cabe
en estructura.

El profesional trabaja con la estructura de las fichas como guía durante la entrevista y
registra los valores al terminar. El sistema nunca bloquea el avance por campos no
rellenados salvo que sean condición explícita para una prestación.

### 4.5 Entidad: Ficha (instancia de datos de valoración)

La `Ficha` es el registro de datos que un profesional cumplimenta al aplicar un
`TipoFicha` en un momento concreto. Su estructura central es:

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | bigint PK | |
| `valoracion_id` | bigint FK nullable | Valoración a la que pertenece (null si se crea sin valoración formal) |
| `historia_id` | bigint FK | Historia Social — FK directa para facilitar consultas sin pasar por valoración |
| `tipo_ficha_id` | bigint FK | Referencia al tipo (para agrupación y filtrado) |
| `schema_snapshot` | jsonb | **Copia del schema del TipoFicha en el momento de cumplimentación** |
| `datos` | jsonb | Valores recogidos: `{ "campo_id": valor, ... }` |
| `notas` | text nullable | Observaciones libres al pie de la ficha |
| `completada` | boolean | `false` mientras está en borrador |
| `profesional_id` | bigint FK | Quién la cumplimentó |
| `created_at` / `updated_at` | timestamp | |

El modelo `Ficha` usa el trait `Versionable`. Ver sección 4.6.

#### El campo `schema_snapshot`

**Por qué existe:** el schema de un `TipoFicha` puede evolucionar con el tiempo — se
añaden campos, se cambian etiquetas, se modifican opciones de un select. Si una ficha
cumplimentada en 2024 solo almacenara `tipo_ficha_id`, su visualización en 2026 dependería
del schema *actual*, que puede ser diferente. Campos que existían entonces podrían no
existir ahora; campos nuevos aparecerían sin valor con ambigüedad sobre si estaban en cero
o simplemente no se recogían.

`schema_snapshot` resuelve esto: cada ficha es **autocontenida**. Sabe exactamente qué
significaba cada campo en el momento de su creación. El renderizador usa siempre
`schema_snapshot`, no el schema actual del `TipoFicha`.

**Cómo se puebla:** en el método `guardar()` del componente Livewire (y en cualquier
servicio que cree fichas por código), antes de persistir se copia el schema actual del
`TipoFicha` al campo `schema_snapshot`. Es responsabilidad del punto de escritura, no
del modelo (no se hace en `booted()` para no ocultar el comportamiento).

**Comparación entre versiones:** cuando se muestran dos fichas del mismo tipo en fechas
diferentes, el sistema puede detectar:
- Campos presentes en ambas: comparables directamente.
- Campos en la antigua pero no en la nueva: existían cuando se cumplimentó la antigua,
  se eliminaron del tipo después. Se muestran en la vista histórica con la etiqueta
  original y un indicador «campo retirado».
- Campos en la nueva pero no en la antigua: se añadieron después. Se muestran en la
  vista histórica de la antigua como «campo no disponible en esta versión».

**Efecto sobre la inmutabilidad de `TipoFicha`:** con `schema_snapshot`, la restricción de
no poder eliminar campos de un `TipoFicha` con fichas asociadas se suaviza:
- **Eliminar un campo:** permitido. Las fichas antiguas conservan ese campo en su snapshot
  y siguen siendo coherentes. El sistema lo marcará como «campo retirado» al visualizarlas.
- **Cambiar el tipo de un campo:** prohibido. Un campo `texto` que se convierte en `numero`
  haría que los datos existentes (`"clase media-baja"`) fueran ininterpretables como número.
- **Cambiar la etiqueta o descripción de un campo:** permitido. La vista histórica puede
  mostrar opcionalmente la etiqueta antigua desde el snapshot.

### 4.6 Versionado de la `Ficha`

#### Dos actos profesionales distintos

Es fundamental distinguir dos situaciones que tienen tratamiento diferente:

**Corrección de error** — el TSR detecta un dato mal introducido poco después de guardarlo
(mismo día, días siguientes). Es una enmienda al mismo acto de recogida. Aquí aplica
`Versionable`: se modifica la `Ficha` existente y queda trazabilidad completa del cambio
(quién, cuándo, qué había antes). El `schema_snapshot` no cambia en la corrección — sigue
siendo el schema vigente cuando se creó la ficha.

**Nueva valoración** — el TSR abre una ficha para recoger el estado actual del ciudadano
en un momento posterior (revisión del Plan, cambio de situación, inicio de dependencia...).
Aquí **no se modifica la ficha anterior**: se crea una nueva instancia de `Ficha` con el
schema vigente en ese nuevo momento. El histórico es la lista de instancias ordenadas por
fecha, cada una con su propio `schema_snapshot`.

La distinción es: *¿el TSR está corrigiendo lo que registró, o está recogiendo cómo está
el ciudadano ahora?* El primero es una enmienda; el segundo es un nuevo acto profesional.

#### Lo que versiona `Versionable` en `Ficha`

Cada entrada en la tabla `versiones` para una `Ficha` contiene el snapshot completo del
registro antes del cambio, lo que incluye:
- `datos` — los valores que tenía antes de la corrección.
- `schema_snapshot` — el schema que estaba vigente cuando se creó esa ficha.
- `notas`, `completada`, `profesional_id`.

Esto garantiza que cualquier versión histórica de una ficha sea completamente
reconstruible e interpretable, con independencia de cómo haya evolucionado el `TipoFicha`.

#### Flujo de «nueva valoración basada en la anterior»

Cuando el TSR inicia una revisión, el sistema ofrece pre-rellenar la nueva ficha con los
valores de la más reciente del mismo tipo para ese ciudadano. El mecanismo es:

1. Cargar la última `Ficha` del mismo `tipo_ficha_id` para esa `historia_id`.
2. Extraer sus `datos`.
3. Crear una **nueva** `Ficha` con:
   - `schema_snapshot` = schema *actual* del `TipoFicha` (no el de la ficha anterior).
   - `datos` = valores de la ficha anterior, filtrando los campos que ya no existen en el
     schema actual y dejando vacíos los campos nuevos.
4. La ficha anterior no se toca.

Este pre-relleno es una ayuda al TSR, no una copia automática. El profesional revisa y
ajusta los valores antes de guardar.

### 4.7 Visualización histórica de fichas

Al mostrar el historial de fichas de un tipo para un ciudadano, el renderizador:

1. Usa siempre `ficha.schema_snapshot` para interpretar `ficha.datos` — nunca el schema
   actual del `TipoFicha`.
2. Detecta divergencias entre el snapshot de la ficha y el schema actual del tipo,
   marcando campos retirados y campos nuevos (ver sección 4.5).
3. Muestra las versiones `Versionable` de cada ficha solo en el modo «historial de
   cambios», no en la vista principal del historial clínico.

---

### Tests funcionales — Grupo I: Ficha con schema_snapshot y versionado

> Estos tests se añaden al fichero
> `Modules/Intervencion/tests/Feature/FichaVersionadoTest.php` (fichero nuevo).
> Complementan el Grupo H (`TipoFichaTest.php`).

**Convenciones:** PHPUnit + `#[Test]`. PostgreSQL (`vida_testing`). Patrón Dado/Cuando/Entonces.
Negativo obligatorio en tests de restricciones de dominio.

---

**TF-INT-I01 — Al crear una Ficha se guarda automáticamente el schema_snapshot del TipoFicha**

- **Dado** un `TipoFicha` activo con un schema de 2 campos.
- **Cuando** se crea una `Ficha` asociada a ese tipo.
- **Entonces** `$ficha->schema_snapshot` es igual al `$tipoFicha->schema` en el momento
  de la creación; no es `null` ni un array vacío.

---

**TF-INT-I02 — El schema_snapshot no cambia si el TipoFicha se modifica después**

- **Dado** una `Ficha` ya guardada con `schema_snapshot` que contiene los campos A y B.
- **Cuando** se añade el campo C al `TipoFicha` y se guarda el tipo.
- **Entonces** `$ficha->fresh()->schema_snapshot` sigue teniendo solo los campos A y B;
  no contiene C.

---

**TF-INT-I03 — Una corrección sobre una Ficha genera una versión Versionable**

- **Dado** una `Ficha` guardada con `datos['ingresos'] = 800`.
- **Cuando** se actualiza la ficha con `datos['ingresos'] = 950`.
- **Entonces** existe una entrada en `versiones` para esa ficha cuyo snapshot
  contiene `datos['ingresos'] == 800`; la ficha actual tiene `datos['ingresos'] == 950`.
- **Negativo:** si se elimina el trait `Versionable` del modelo `Ficha`, el test debe fallar.

---

**TF-INT-I04 — La versión Versionable incluye el schema_snapshot de la ficha**

- **Dado** una `Ficha` guardada.
- **Cuando** se actualiza cualquier campo de la ficha (por ejemplo, `notas`).
- **Entonces** la entrada en `versiones` contiene un campo `schema_snapshot` no vacío
  dentro de su datos JSON; ese snapshot es el mismo que tenía la ficha al crearse.

---

**TF-INT-I05 — Una nueva valoración crea una Ficha nueva, no modifica la anterior**

- **Dado** una `Ficha` existente (ficha_v1) con `datos['ingresos'] = 800`, asociada a
  una `historia_id` y un `tipo_ficha_id`.
- **Cuando** se crea una segunda `Ficha` (ficha_v2) con los mismos `historia_id` y
  `tipo_ficha_id` pero con `datos['ingresos'] = 1100`.
- **Entonces** existen dos registros en `fichas` con la misma `historia_id` y
  `tipo_ficha_id`; `ficha_v1->datos['ingresos']` sigue siendo `800`;
  `ficha_v2->datos['ingresos']` es `1100`.

---

**TF-INT-I06 — El schema_snapshot de la nueva valoración usa el schema actual del TipoFicha**

- **Dado** un `TipoFicha` original con campos A y B; se crea ficha_v1.
  Después, se añade el campo C al `TipoFicha`.
- **Cuando** se crea ficha_v2 (nueva valoración) para la misma historia.
- **Entonces** `ficha_v1->schema_snapshot` tiene campos A y B (sin C);
  `ficha_v2->schema_snapshot` tiene campos A, B y C.

---

**TF-INT-I07 — Pre-relleno de nueva valoración desde la anterior: campos comunes se copian**

- **Dado** un `TipoFicha` con campos A y B; ficha_v1 con `datos = ['A' => 10, 'B' => 20]`.
- **Cuando** se genera el pre-relleno para una nueva valoración llamando a
  `Ficha::prerellenarDesde(ficha_v1, $tipoFicha)` (o el método/servicio equivalente).
- **Entonces** el array resultante contiene `['A' => 10, 'B' => 20]`.

---

**TF-INT-I08 — Pre-relleno descarta campos del snapshot anterior que ya no existen en el schema actual**

- **Dado** ficha_v1 con `schema_snapshot` que incluye campo C y `datos = ['A' => 1, 'C' => 5]`.
  El `TipoFicha` actual ya no tiene el campo C (fue eliminado después de crear ficha_v1).
- **Cuando** se genera el pre-relleno desde ficha_v1 con el schema actual del tipo.
- **Entonces** el array resultante contiene `['A' => 1]`; no contiene la clave `C`.

---

**TF-INT-I09 — Pre-relleno deja vacíos los campos nuevos del schema actual**

- **Dado** ficha_v1 cuyo `schema_snapshot` no contiene el campo D.
  El `TipoFicha` actual sí tiene el campo D (fue añadido después de crear ficha_v1).
- **Cuando** se genera el pre-relleno desde ficha_v1 con el schema actual del tipo.
- **Entonces** el array resultante contiene la clave `D` con valor `null`.

---

**TF-INT-I10 — Cambiar el tipo de un campo en TipoFicha con fichas asociadas lanza excepción**

- **Dado** un `TipoFicha` con un campo `ingresos` de tipo `numero`; existe una `Ficha`
  asociada.
- **Cuando** se intenta actualizar el `TipoFicha` cambiando `ingresos` a tipo `texto`.
- **Entonces** se lanza `ValidationException`; el `TipoFicha` no se guarda.
- **Negativo:** si se elimina la guardia de inmutabilidad de tipo, el test debe fallar.

---

**TF-INT-I11 — Eliminar un campo de TipoFicha con fichas asociadas está permitido**

- **Dado** un `TipoFicha` con campos A y B; existe una `Ficha` asociada.
- **Cuando** se actualiza el `TipoFicha` eliminando el campo B del schema.
- **Entonces** el `TipoFicha` se guarda sin errores; el schema actual solo contiene A.
  La `Ficha` existente conserva su `schema_snapshot` original con A y B intactos.

> Este test verifica la **inversión de la restricción anterior** (H08 en `TipoFichaTest`).
> El Paso 1 de las instrucciones CLI debe actualizar la validación de `TipoFicha`
> para que la eliminación de campos sea permitida. El test H08 debe actualizarse
> para reflejar el nuevo comportamiento.

---

**TF-INT-I12 — El historial de fichas de un tipo para una historia se ordena por fecha descendente**

- **Dado** tres `Ficha` del mismo `tipo_ficha_id` y `historia_id`, creadas en fechas distintas.
- **Cuando** se consulta `Ficha::historialPara($historiaId, $tipoFichaId)`.
- **Entonces** las fichas se devuelven ordenadas por `created_at` descendente (más reciente primero).
