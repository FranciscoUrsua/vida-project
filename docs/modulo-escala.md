# Módulo Escalas

> **Estado:** especificado y diseñado. Pendiente de implementación.

Las escalas son instrumentos estandarizados de valoración que el profesional aplica a un ciudadano para obtener una puntuación objetiva sobre un aspecto concreto de su situación (grado de dependencia funcional, riesgo de exclusión, carga del cuidador, etc.). A diferencia de las fichas de valoración —que recogen información heterogénea que el TSR interpreta con criterio profesional—, las escalas tienen una lógica interna predefinida: la puntuación total es la suma de los valores de las respuestas, y su interpretación está embebida en el propio instrumento.

Una escala puede aplicarse de forma autónoma o como insumo para completar una ficha de valoración. La relación entre ambas es de referencia, no de pertenencia estructural.

---

## 1. Diferencia conceptual con las fichas de valoración

| Dimensión | Ficha de valoración | Escala |
|---|---|---|
| Tipo de datos | Heterogéneo (texto, selección, números, fechas) | Siempre numérico |
| Resultado | El profesional interpreta el conjunto | Puntuación total calculada automáticamente |
| Interpretación | Criterio profesional | Embebida en el instrumento (tabla de rangos) |
| Textos de apoyo | Etiquetas e instrucciones de campo | Instrucciones en tres niveles (escala, sección, ítem) |
| Relación con ciudadano | Versionado dentro de una valoración | Serie temporal de pases independientes |
| Configurabilidad | Backoffice (schema JSON de campos) | Backoffice (schema JSON de secciones e ítems) |

La arquitectura de escalas es una entidad de primer nivel, no un subtipo de `TipoFicha`. Esto permite que una escala sea reutilizable en distintos contextos (valoración inicial, seguimiento, pase autónomo) y que su historial de aplicaciones sea consultable independientemente de las valoraciones en las que participó.

---

## 2. Entidad: `TipoEscala`

Define la estructura completa del instrumento. Vive en el backoffice de Filament y es gestionada exclusivamente por administradores. Los profesionales nunca modifican un `TipoEscala`; solo aplican instancias de él.

### 2.1 Tabla `tipo_escalas`

| Columna | Tipo | Descripción |
|---|---|---|
| `id` | bigint PK | |
| `nombre` | varchar(200) | Nombre del instrumento (p.ej. «Escala de Barthel») |
| `codigo` | varchar(50) | Identificador estable para referencias en código (p.ej. `barthel`). Inmutable una vez publicado. |
| `descripcion` | text | Para qué sirve la escala y cuándo aplicarla |
| `instrucciones_aplicacion` | text nullable | Texto completo que se muestra al profesional en pantalla antes de comenzar el pase |
| `confirmar_instrucciones` | boolean | Si `true`, el profesional debe confirmar la lectura antes de avanzar |
| `fuente` | varchar(200) nullable | Referencia bibliográfica del instrumento original |
| `contextos` | jsonb | Array de contextos de aplicación (`dependencia`, `asp`, `insercion`, etc.) |
| `schema` | jsonb | Definición de secciones e ítems (ver sección 2.2) |
| `rangos_interpretacion` | jsonb | Tabla de rangos de score y nota de interpretación (ver sección 2.3) |
| `activa` | boolean | `false` = no aparece en selección para nuevos pases; los pases existentes no se ven afectados |
| `created_at`, `updated_at` | | |

El campo `codigo` sigue la convención de `catalogos_sistema`: nunca se referencia por nombre en lógica de negocio. Si una parte del sistema necesita comportarse de forma diferente según el tipo de escala, ese comportamiento debe implementarse mediante un enum PHP, no comparando contra el `codigo`.

### 2.2 Schema JSON — estructura de secciones e ítems

```json
{
  "secciones": [
    {
      "id": "sec_1",
      "titulo": "Cuidado personal",
      "instrucciones": "Evalúe la realización durante los últimos 7 días. Puntúe lo que el ciudadano HACE, no lo que podría hacer.",
      "orden": 1,
      "items": [
        {
          "id": "item_1_1",
          "texto": "Comer",
          "instrucciones": "Capaz de utilizar cualquier instrumento. Come en un tiempo razonable. No incluye cortar carne ni untar mantequilla.",
          "orden": 1,
          "opciones": [
            { "valor": 0,  "etiqueta": "Dependiente" },
            { "valor": 5,  "etiqueta": "Necesita ayuda" },
            { "valor": 10, "etiqueta": "Independiente" }
          ]
        }
      ]
    }
  ]
}
```

**Niveles de texto explicativo:**

- `instrucciones_aplicacion` (nivel escala): se muestra antes de comenzar el pase, como pantalla de contexto previa a la primera sección.
- `secciones[].instrucciones` (nivel sección): se muestra al entrar en cada sección como nota metodológica. Puede ser `null` si la sección no requiere instrucción específica.
- `items[].instrucciones` (nivel ítem): se muestra junto a la pregunta, siempre visible, como criterio de puntuación. Puede ser `null`.

Los tres niveles son opcionales en el schema, pero el administrador debe poder incluirlos. Su presencia o ausencia no afecta a la validez del instrumento ni al cálculo del score.

**Restricciones del schema:**

- Toda `seccion` debe tener al menos un `item`.
- Todo `item` debe tener al menos dos `opciones`.
- Los `valores` de las opciones son enteros (positivos, negativos o cero). El sistema no impone rango.
- Los `id` de secciones e ítems deben ser únicos dentro del schema. Son estables: no cambiar una vez que existan `PaseEscala` asociados.
- El orden de presentación lo determina el campo `orden`, no la posición en el array.

### 2.3 Schema JSON — rangos de interpretación

```json
{
  "rangos": [
    { "desde": 0,   "hasta": 20,  "etiqueta": "Dependencia total",    "codigo": "total" },
    { "desde": 21,  "hasta": 60,  "etiqueta": "Dependencia severa",   "codigo": "severa" },
    { "desde": 61,  "hasta": 90,  "etiqueta": "Dependencia moderada", "codigo": "moderada" },
    { "desde": 91,  "hasta": 99,  "etiqueta": "Dependencia escasa",   "codigo": "escasa" },
    { "desde": 100, "hasta": 100, "etiqueta": "Independencia",        "codigo": "independiente" }
  ],
  "nota_interpretacion": "Una variación de ±5 puntos no es clínicamente significativa. Valorar siempre la evolución temporal respecto a pases anteriores."
}
```

Los rangos deben cubrir sin huecos el rango completo de scores posibles del instrumento. El sistema valida en backoffice que no existan solapamientos ni huecos antes de publicar el `TipoEscala`. La `nota_interpretacion` se muestra junto al resultado al cerrar el pase; puede ser `null`.

---

## 3. Entidad: `PaseEscala`

Registra la aplicación concreta de un instrumento a un ciudadano en una fecha determinada. Es la unidad de la serie temporal de valoraciones con esa escala. Cada pase es un registro independiente e inmutable una vez en estado `completado`.

### 3.1 Tabla `pases_escala`

| Columna | Tipo | Descripción |
|---|---|---|
| `id` | bigint PK | |
| `tipo_escala_id` | FK → `tipo_escalas.id` | Qué instrumento se aplicó |
| `historia_id` | FK → `historias_sociales.id` | A qué ciudadano (vía Historia Social) |
| `profesional_id` | FK → `usuarios.id` | Quién lo aplicó |
| `fecha` | date | Fecha de aplicación |
| `respuestas` | jsonb | Valores seleccionados por ítem (ver sección 3.2) |
| `score_total` | integer nullable | Suma de todos los valores. Calculado y persistido al completar. |
| `scores_seccion` | jsonb nullable | Score parcial por sección. Calculado y persistido al completar. |
| `interpretacion_codigo` | varchar(50) nullable | El `codigo` del rango que corresponde al `score_total` |
| `notas` | text nullable | Observaciones del profesional sobre las condiciones del pase |
| `estado` | enum | `borrador` / `completado` |
| `ficha_id` | FK nullable | Si el pase se realizó en el contexto de una ficha de valoración |
| `entrevista_id` | FK nullable | Si el pase se realizó en el contexto de una entrevista |
| `created_at`, `updated_at` | | |

**Sobre la inmutabilidad:** el `score_total`, `scores_seccion` e `interpretacion_codigo` se calculan en el momento del cierre y se persisten. No se recalculan en lectura. Si en el futuro se modifica el schema de un `TipoEscala`, los pases anteriores conservan el score calculado con el schema vigente en el momento de su realización. El historial es siempre fiel.

**Sobre los vínculos opcionales:** `ficha_id` y `entrevista_id` son de conveniencia. Un pase puede existir sin estar vinculado a ninguno de los dos (pase autónomo). La relación es de referencia: el `PaseEscala` no depende estructuralmente de la `Ficha` ni de la `Entrevista`.

### 3.2 Estructura de `respuestas`

```json
{
  "sec_1": {
    "item_1_1": 10,
    "item_1_2": 5
  },
  "sec_2": {
    "item_2_1": 0
  }
}
```

Clave de sección → clave de ítem → valor entero elegido. El valor debe corresponder a una de las opciones definidas en el schema del `TipoEscala` vigente en el momento del pase. En estado `borrador` pueden existir ítems sin respuesta; en estado `completado` todos los ítems deben tener respuesta.

### 3.3 Cálculo de scores

El `score_total` es la suma de todos los valores en `respuestas`. El `scores_seccion` es un objeto con el score parcial de cada sección:

```json
{
  "sec_1": 35,
  "sec_2": 15,
  "sec_3": 10
}
```

Ambos se calculan en el modelo `PaseEscala` mediante un método `calcularScores()` invocado en el momento del cierre, antes de persistir. La lógica de cálculo vive en código PHP, no en el JSON del schema.

---

## 4. Relación con el módulo de Intervención

`TipoEscala` es independiente de `TipoFicha`. Sin embargo, el schema de `TipoFicha` puede incluir un campo de tipo `escala_vinculada` que referencia a un `TipoEscala` por su `codigo`. Esto permite al profesional lanzar el pase directamente desde la ficha durante la entrevista y que el resultado quede automáticamente asociado vía `ficha_id`. La relación es de conveniencia operativa; un pase puede realizarse sin pasar por una ficha.

Desde la Historia Social debe ser posible consultar todos los pases de una escala concreta para un ciudadano, ordenados cronológicamente, como serie temporal independiente de las valoraciones en las que participaron.

---

## 5. Backoffice Filament

Se crea un recurso Filament `TipoEscalaResource` con las siguientes pantallas:

**Listado:** tabla con columnas `codigo`, `nombre`, `fuente`, `contextos` (chips), `activa`. Filtros por contexto y estado activo/inactivo. Búsqueda por nombre y código.

**Formulario — pestaña Datos generales:**
- Identificación: `nombre` (requerido), `codigo` (requerido; solo editable si no existen pases asociados), `fuente`, `activa` (toggle), `contextos` (multiselect desde `catalogos_sistema` grupo `escala.contexto`).
- Descripción: campo de texto libre.
- Instrucciones de aplicación: textarea con `confirmar_instrucciones` toggle asociado.

**Formulario — pestaña Estructura:**
- Repeater de secciones. Cada sección contiene: `titulo`, `instrucciones` (textarea opcional, estilo diferenciado), y un repeater de ítems.
- Cada ítem contiene: `texto`, `instrucciones` (textarea opcional, estilo diferenciado del de la sección), y un repeater de opciones (valor entero + etiqueta).
- Controles de reordenación por arrastre en secciones e ítems.
- El backoffice no muestra el JSON en bruto; lo genera internamente a partir de los campos del formulario.
- Una vez que existe al menos un `PaseEscala` asociado, los ítems existentes quedan bloqueados para edición. Solo se pueden añadir nuevas secciones o ítems al final.

**Formulario — pestaña Rangos e interpretación:**
- Tabla editable de rangos: `desde`, `hasta`, `etiqueta`, `codigo`.
- Validación en tiempo real de solapamientos y huecos.
- Campo `nota_interpretacion` (textarea opcional).
- Vista previa del bloque de resultado tal como lo verá el profesional.

**Permisos de aplicación:** cualquier profesional con rol `intervencion` y acceso a la Historia Social puede aplicar cualquier escala activa. No existe restricción adicional por tipo de escala, contexto o UO.

---

## 6. Decisiones de diseño

**Versiones de `TipoEscala`.** No se implementa versionado interno del schema. Si un instrumento cambia sustancialmente, se crea un nuevo `TipoEscala`. Los pases anteriores conservan su referencia al `tipo_escala_id` original y su score calculado. La serie temporal de un ciudadano puede contener pases de versiones distintas del mismo instrumento; el profesional los interpreta con ese conocimiento. Esta decisión evita la complejidad de los snapshots de schema y es coherente con la práctica clínica real, donde un cambio de versión de una escala rompe la comparabilidad histórica de todas formas.

**Scores negativos.** El sistema permite valores de opción negativos. Algunos instrumentos los usan. No se impone restricción de rango.

**Ítems sin respuesta en borrador.** Un pase en estado `borrador` puede tener ítems sin respuesta. El sistema no calcula scores parciales en borrador; el `score_total` es `null` hasta el cierre.

**Relación con `catalogos_sistema`.** El campo `contextos` de `TipoEscala` usa valores del grupo `escala.contexto` de `catalogos_sistema`. Esto permite añadir nuevos contextos desde backoffice sin desarrollo. Los contextos son puramente clasificatorios; ninguna lógica de negocio opera sobre ellos directamente.

**Subescalas con interpretación independiente.** No se implementa en esta fase. La arquitectura soporta `scores_seccion` (puntuación parcial por sección), pero la tabla `rangos_interpretacion` es única por escala. Si en el futuro surge la necesidad, el campo podría extenderse con un subobjeto `por_seccion`. A retomar cuando se identifique un instrumento concreto que lo requiera.

---

## 7. Seeders de instrumentos incluidos

Se incluyen como seeders de fábrica los instrumentos cuya reproducción es libre para uso no comercial en el contexto de un sistema público de servicios sociales. A continuación el análisis de licencias realizado:

### 7.1 Instrumentos incluidos en el seeder

**Índice de Barthel** (`barthel`)
La Maryland State Medical Society es titular del copyright. El instrumento puede usarse libremente para fines no comerciales con la cita correspondiente: *Mahoney FI, Barthel D. "Functional evaluation: the Barthel Index." Maryland State Med Journal 1965;14:56-61.* Un sistema público municipal de servicios sociales sin ánimo de lucro queda dentro de ese uso permitido. Se incluye en el seeder con la cita completa en el campo `fuente`.

**Cuestionario Portátil del Estado Mental de Pfeiffer — SPMSQ** (`pfeiffer_spmsq`)
Publicado en 1975 en el *Journal of the American Geriatrics Society*. El instrumento ha sido reproducido y adaptado ampliamente en la literatura clínica española sin restricciones documentadas para uso clínico no comercial. Se incluye en su adaptación española validada (Martínez de la Iglesia et al., 2001). Fuente: *Pfeiffer E. "A short portable mental status questionnaire for the assessment of organic brain deficit in elderly patients." J Am Geriatr Soc. 1975;23(10):433-41.*

**Escala de Lawton y Brody — AIVD** (`lawton_brody`)
Publicada en 1969 en *The Gerontologist*. Instrumento de dominio público ampliamente reproducido en guías clínicas, protocolos de atención primaria y sistemas de información sanitaria de toda España sin restricción documentada. Fuente: *Lawton MP, Brody EM. "Assessment of older people: self-maintaining and instrumental activities of daily living." Gerontologist. 1969;9(3):179-86.*

### 7.2 Instrumentos excluidos del seeder por restricciones de licencia

**Entrevista de Carga del Cuidador de Zarit — ZBI**
Copyright © 1983 Steven Zarit. Disponible gratuitamente para uso clínico y para investigación académica sin financiación, pero el titular del copyright debe ser reconocido expresamente y la distribución dentro de un producto de software puede requerir permiso explícito. Se excluye del seeder hasta obtener confirmación del titular. Puede añadirse manualmente desde el backoffice.

**GHQ-28 (General Health Questionnaire)**
Copyright de GL Assessment. Requiere licencia de pago para cualquier uso, incluido el clínico. Excluido.

**Escala de Depresión Geriátrica de Yesavage — GDS**
Existen versiones en dominio público (la versión original de 30 ítems de 1983), pero la validación española está sujeta a derechos. La situación jurídica es ambigua; se excluye del seeder hasta aclaración.

**Beck Depression Inventory — BDI-II**
Copyright de Pearson. Requiere pago por cada uso. Excluido.

### 7.3 Contenido del `EscalaSeeder`

El seeder carga los tres instrumentos incluidos con su schema completo en español, sus rangos de interpretación y sus instrucciones de aplicación. Los textos de instrucción por ítem siguen las guías clínicas de uso habitual en los servicios sociales del Ayuntamiento de Madrid.

El seeder está marcado como `idempotente`: si los registros ya existen (por `codigo`), no los duplica ni los sobreescribe. Esto permite ejecutarlo en entornos de actualización sin riesgo.

---

## 8. Decisiones pendientes

- **Zarit ZBI.** Contactar con el titular del copyright (Steven Zarit, Pennsylvania State University) para confirmar si el uso en un sistema público municipal de servicios sociales está cubierto por la excepción de uso clínico no comercial. Si se confirma, añadir al seeder en una iteración posterior.
- **GDS de Yesavage.** Aclarar si la versión original de 30 ítems (1983) está en dominio público y si la traducción española validada es de libre uso en contexto público no comercial.

---

## 9. Tests funcionales

Convención de nomenclatura: `TF-ESC-XX`. Los tests se ejecutan con:

```bash
php artisan test --filter=Escala
```

### Tabla de ejecución

| Test | Descripción | Estado |
|---|---|---|
| TF-ESC-A01 | TipoEscala con schema JSON inválido no puede guardarse | ⬜ |
| TF-ESC-A02 | TipoEscala con rangos solapados no puede publicarse | ⬜ |
| TF-ESC-A03 | TipoEscala con rangos con huecos no puede publicarse | ⬜ |
| TF-ESC-A04 | TipoEscala inactiva no aparece en scope de escalas aplicables | ⬜ |
| TF-ESC-A05 | Codigo de TipoEscala es inmutable si existen pases asociados | ⬜ |
| TF-ESC-A06 | Schema de ítem existente es inmutable si existen pases asociados | ⬜ |
| TF-ESC-B01 | PaseEscala en borrador puede tener ítems sin respuesta | ⬜ |
| TF-ESC-B02 | PaseEscala completado requiere respuesta para todos los ítems | ⬜ |
| TF-ESC-B03 | calcularScores suma correctamente todos los valores de respuestas | ⬜ |
| TF-ESC-B04 | calcularScores produce scores_seccion correctos por sección | ⬜ |
| TF-ESC-B05 | interpretacion_codigo se asigna correctamente según score_total | ⬜ |
| TF-ESC-B06 | Score total de pase completado es inmutable | ⬜ |
| TF-ESC-B07 | PaseEscala sin ficha_id ni entrevista_id es válido | ⬜ |
| TF-ESC-B08 | PaseEscala con ficha_id mantiene referencia al completarse la ficha | ⬜ |
| TF-ESC-C01 | Historia Social devuelve pases de una escala en orden cronológico | ⬜ |
| TF-ESC-C02 | Pases de distintos ciudadanos no se mezclan en el historial | ⬜ |
| TF-ESC-C03 | Seeder produce los tres TipoEscala esperados con schema y rangos válidos | ⬜ |
| TF-ESC-C04 | Seeder es idempotente: segunda ejecución no duplica registros | ⬜ |

---

### Grupo A — Integridad de configuración

**TF-ESC-A01 — TipoEscala con schema JSON inválido no puede guardarse**
- Precondición: ninguna
- Acción: intentar crear `TipoEscala` con `schema = 'esto no es json{'`
- Resultado: falla con error de validación antes de llegar a BD; no se crea ningún registro

**TF-ESC-A02 — TipoEscala con rangos solapados no puede publicarse**
- Precondición: ninguna
- Acción: intentar crear `TipoEscala` con `rangos_interpretacion` donde dos rangos comparten valores (`hasta: 60` y `desde: 55`)
- Resultado: falla con error de validación; el mensaje identifica el solapamiento

**TF-ESC-A03 — TipoEscala con rangos con huecos no puede publicarse**
- Precondición: `TipoEscala` con score mínimo posible 0 y máximo 100
- Acción: intentar publicar con rangos que cubren 0–40 y 60–100, dejando un hueco en 41–59
- Resultado: falla con error de validación; el mensaje identifica el hueco

**TF-ESC-A04 — TipoEscala inactiva no aparece en scope de escalas aplicables**
- Precondición: dos `TipoEscala`: uno con `activa = true`, otro con `activa = false`
- Acción: `TipoEscala::aplicables()->get()`
- Resultado: solo aparece el `TipoEscala` activo; el inactivo no está en la colección

**TF-ESC-A05 — Codigo de TipoEscala es inmutable si existen pases asociados**
- Precondición: `TipoEscala` con código `barthel` y al menos un `PaseEscala` asociado
- Acción: intentar actualizar `codigo = 'barthel_v2'`
- Resultado: falla con error de validación; el `codigo` no cambia en BD

**TF-ESC-A06 — Schema de ítem existente es inmutable si existen pases asociados**
- Precondición: `TipoEscala` con ítem `item_1_1` y al menos un `PaseEscala` que incluye respuesta para ese ítem
- Acción: intentar modificar el `texto` o las `opciones` del ítem `item_1_1` en el schema
- Resultado: falla con error de validación; el schema en BD no cambia

---

### Grupo B — Comportamiento del pase

**TF-ESC-B01 — PaseEscala en borrador puede tener ítems sin respuesta**
- Precondición: `TipoEscala` con 3 ítems
- Acción: crear `PaseEscala` con `estado = borrador` y `respuestas` que solo incluye respuesta para 1 de los 3 ítems
- Resultado: el pase se guarda sin errores; `$pase->score_total` es `null`

**TF-ESC-B02 — PaseEscala completado requiere respuesta para todos los ítems**
- Precondición: `TipoEscala` con 3 ítems
- Acción: intentar cambiar a `estado = completado` un `PaseEscala` con solo 2 de 3 ítems respondidos
- Resultado: falla con error de validación; el estado no cambia

**TF-ESC-B03 — calcularScores suma correctamente todos los valores de respuestas**
- Precondición: `TipoEscala` con dos secciones, tres ítems en total, con opciones de valores 0/5/10
- Acción: crear `PaseEscala` con respuestas `{sec_1: {item_1: 10, item_2: 5}, sec_2: {item_3: 5}}`; llamar a `$pase->calcularScores()`
- Resultado: `$pase->score_total === 20`

**TF-ESC-B04 — calcularScores produce scores_seccion correctos por sección**
- Precondición: misma configuración que TF-ESC-B03
- Acción: llamar a `$pase->calcularScores()`
- Resultado: `$pase->scores_seccion === ['sec_1' => 15, 'sec_2' => 5]`

**TF-ESC-B05 — interpretacion_codigo se asigna correctamente según score_total**
- Precondición: `TipoEscala` con rango `{'desde': 0, 'hasta': 20, 'codigo': 'total'}` y rango `{'desde': 21, 'hasta': 100, 'codigo': 'moderada'}`
- Acción: `PaseEscala` con `score_total = 15`; llamar a `$pase->asignarInterpretacion()`
- Resultado: `$pase->interpretacion_codigo === 'total'`

**TF-ESC-B06 — Score total de pase completado es inmutable**
- Precondición: `PaseEscala` en estado `completado` con `score_total = 65`
- Acción: intentar actualizar `score_total = 80` directamente
- Resultado: el modelo rechaza la modificación; `$pase->fresh()->score_total === 65`

**TF-ESC-B07 — PaseEscala sin ficha_id ni entrevista_id es válido**
- Precondición: `TipoEscala` activa, Historia Social existente, profesional
- Acción: crear `PaseEscala` con `ficha_id = null` y `entrevista_id = null`
- Resultado: el pase se guarda sin errores

**TF-ESC-B08 — PaseEscala con ficha_id mantiene referencia al completarse la ficha**
- Precondición: `Ficha` existente, `PaseEscala` con `ficha_id` apuntando a esa ficha
- Acción: marcar la ficha como completada
- Resultado: `$pase->fresh()->ficha_id` sigue apuntando a la misma ficha; la referencia no se pierde

---

### Grupo C — Historial, consultas y seeder

**TF-ESC-C01 — Historia Social devuelve pases de una escala en orden cronológico**
- Precondición: tres `PaseEscala` completados para el mismo ciudadano y la misma escala, con fechas 2025-01-10, 2025-06-15 y 2026-01-20
- Acción: `$historia->pasesEscala($tipoEscalaId)->orderBy('fecha')->get()`
- Resultado: los pases se devuelven en orden ascendente de fecha; el primero tiene `fecha = 2025-01-10`

**TF-ESC-C02 — Pases de distintos ciudadanos no se mezclan en el historial**
- Precondición: dos ciudadanos, cada uno con dos pases de la misma escala
- Acción: `$historiaCiudadano1->pasesEscala($tipoEscalaId)->get()`
- Resultado: la colección contiene exactamente 2 pases; ninguno pertenece al ciudadano 2

**TF-ESC-C03 — Seeder produce los tres TipoEscala esperados con schema y rangos válidos**
- Precondición: BD limpia
- Acción: ejecutar `EscalaSeeder`
- Resultado: existen exactamente 3 registros en `tipo_escalas` con códigos `barthel`, `pfeiffer_spmsq` y `lawton_brody`; el `schema` de cada uno es un array PHP válido con al menos una sección y un ítem; `rangos_interpretacion` es un array PHP válido con al menos un rango; los tres tienen `activa = true`

**TF-ESC-C04 — Seeder es idempotente: segunda ejecución no duplica registros**
- Precondición: `EscalaSeeder` ya ejecutado una vez
- Acción: ejecutar `EscalaSeeder` una segunda vez
- Resultado: sigue habiendo exactamente 3 registros en `tipo_escalas`; no se crean duplicados

---

*Documento elaborado en fase de diseño. Versión inicial: mayo 2026.*
