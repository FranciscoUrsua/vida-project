# Instrucciones CLI — Módulo Escalas: implementación y tests

> Leer este fichero íntegramente antes de escribir cualquier línea de código.
> Referencia de diseño: `docs/modulo-escala.md`.

---

## Contexto

Este fichero implementa el Módulo Escalas de VIDA 360: instrumentos estandarizados de valoración (Barthel, Pfeiffer, Lawton-Brody) que el profesional aplica a un ciudadano para obtener una puntuación objetiva. El módulo tiene dos entidades principales: `TipoEscala` (configuración, en Filament) y `PaseEscala` (aplicación al ciudadano, pendiente de superficie Livewire).

Esta sesión cubre únicamente la **fase 1**: modelos, migraciones, enums, scopes, factories, seeder y tests funcionales. El recurso Filament (`TipoEscalaResource`) se implementa también en esta sesión. La superficie Livewire de aplicación (`PaseEscala` desde la Historia Social) se aborda en una sesión posterior.

---

## Orden de implementación

Seguir este orden. No adelantar pasos.

1. Migración `tipo_escalas`
2. Migración `pases_escala`
3. Enum `EstadoPase`
4. Modelo `TipoEscala` con scopes y validaciones
5. Modelo `PaseEscala` con métodos de cálculo
6. Relación `HistoriaSocial → PaseEscala`
7. Factories
8. Seeder `EscalaSeeder`
9. Recurso Filament `TipoEscalaResource`
10. Fichero de tests `EscalaTest`
11. Ejecutar tests y corregir hasta pasar los 18
12. Cierre de sesión (CHANGELOG, BACKLOG, SESSION, commit)

---

## 1. Migración `tipo_escalas`

Ubicación: `Modules/Escalas/database/migrations/YYYY_MM_DD_000001_create_tipo_escalas_table.php`

```php
Schema::create('tipo_escalas', function (Blueprint $table) {
    $table->id();
    $table->string('nombre', 200);
    $table->string('codigo', 50)->unique();
    $table->text('descripcion')->nullable();
    $table->text('instrucciones_aplicacion')->nullable();
    $table->boolean('confirmar_instrucciones')->default(false);
    $table->string('fuente', 200)->nullable();
    $table->jsonb('contextos')->default('[]');
    $table->jsonb('schema');
    $table->jsonb('rangos_interpretacion');
    $table->boolean('activa')->default(true);
    $table->timestamps();
});
```

Índice adicional: `$table->index('activa');`

---

## 2. Migración `pases_escala`

Ubicación: `Modules/Escalas/database/migrations/YYYY_MM_DD_000002_create_pases_escala_table.php`

```php
Schema::create('pases_escala', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tipo_escala_id')->constrained('tipo_escalas');
    $table->foreignId('historia_id')->constrained('historias_sociales');
    $table->foreignId('profesional_id')->constrained('usuarios');
    $table->date('fecha');
    $table->jsonb('respuestas')->default('{}');
    $table->integer('score_total')->nullable();
    $table->jsonb('scores_seccion')->nullable();
    $table->string('interpretacion_codigo', 50)->nullable();
    $table->text('notas')->nullable();
    $table->string('estado', 20)->default('borrador');
    $table->foreignId('ficha_id')->nullable()->constrained('fichas');
    $table->foreignId('entrevista_id')->nullable()->constrained('entrevistas');
    $table->timestamps();
});
```

Índices adicionales:
```php
$table->index(['historia_id', 'tipo_escala_id', 'fecha']);
$table->index('estado');
```

---

## 3. Enum `EstadoPase`

Ubicación: `Modules/Escalas/app/Enums/EstadoPase.php`

```php
enum EstadoPase: string
{
    case Borrador   = 'borrador';
    case Completado = 'completado';
}
```

El código toma decisiones sobre este valor (impedir modificación de scores en `completado`, requerir todas las respuestas antes de completar), por lo que es enum PHP y no catálogo.

---

## 4. Modelo `TipoEscala`

Ubicación: `Modules/Escalas/app/Models/TipoEscala.php`

**Casts obligatorios:**
```php
protected $casts = [
    'schema'                => 'array',
    'rangos_interpretacion' => 'array',
    'contextos'             => 'array',
    'activa'                => 'boolean',
    'confirmar_instrucciones' => 'boolean',
];
```

**Scopes:**
- `scopeAplicables(Builder $query)`: filtra por `activa = true`. Es el scope que usan la superficie Livewire y los selects de Filament para mostrar instrumentos disponibles.

**Validación del schema al guardar** — implementar mediante el evento `saving` del modelo o un observer `TipoEscalaObserver`:

- El campo `schema` debe ser un array PHP válido con al menos una clave `secciones`.
- Cada sección debe tener al menos un ítem.
- Cada ítem debe tener al menos dos opciones.
- Si alguna condición falla, lanzar `\InvalidArgumentException` con mensaje descriptivo en español.

**Validación de rangos al guardar** — en el mismo observer o evento:

- El campo `rangos_interpretacion` debe contener la clave `rangos` como array no vacío.
- No deben existir solapamientos entre rangos: para cada par de rangos adyacentes, `rangos[i].hasta + 1 === rangos[i+1].desde`.
- Si hay huecos o solapamientos, lanzar `\InvalidArgumentException` con mensaje que identifique el rango conflictivo.

**Inmutabilidad del `codigo` y del schema con pases existentes** — en el mismo observer, evento `updating`:

- Si el modelo tiene pases asociados (`$this->pases()->exists()`), impedir la modificación del campo `codigo` lanzando `\LogicException`.
- Si el modelo tiene pases asociados, impedir la modificación de ítems ya existentes en el schema: comparar los `id` de todos los ítems del schema actual con los del schema nuevo; si algún `id` existente ha desaparecido o tiene sus `opciones` modificadas, lanzar `\LogicException`. Añadir nuevas secciones o ítems sí está permitido.

**Relación:**
```php
public function pases(): HasMany
{
    return $this->hasMany(PaseEscala::class, 'tipo_escala_id');
}
```

---

## 5. Modelo `PaseEscala`

Ubicación: `Modules/Escalas/app/Models/PaseEscala.php`

**Casts obligatorios:**
```php
protected $casts = [
    'respuestas'     => 'array',
    'scores_seccion' => 'array',
    'fecha'          => 'date',
    'estado'         => EstadoPase::class,
];
```

**Inmutabilidad del score en `completado`** — implementar en el evento `updating`:

```php
// Si el pase está completado, los campos de score son inmutables
if ($this->getOriginal('estado') === EstadoPase::Completado->value) {
    foreach (['score_total', 'scores_seccion', 'interpretacion_codigo'] as $campo) {
        if ($this->isDirty($campo)) {
            throw new \LogicException(
                "El campo {$campo} es inmutable en un pase completado."
            );
        }
    }
}
```

**Método `calcularScores(): void`**

Suma todos los valores enteros del array `respuestas` (estructura `sec_id => [item_id => valor]`). Popula `score_total` con la suma global y `scores_seccion` con el subtotal de cada sección. No persiste; el llamador es responsable de llamar a `save()` después.

```php
public function calcularScores(): void
{
    $total = 0;
    $porSeccion = [];

    foreach ($this->respuestas as $secId => $items) {
        $subtotal = array_sum($items);
        $porSeccion[$secId] = $subtotal;
        $total += $subtotal;
    }

    $this->score_total    = $total;
    $this->scores_seccion = $porSeccion;
}
```

**Método `asignarInterpretacion(): void`**

Busca en `tipoEscala->rangos_interpretacion['rangos']` el rango cuyo `desde <= score_total <= hasta` y asigna su `codigo` a `interpretacion_codigo`. Si no encuentra ningún rango, deja `interpretacion_codigo` como `null` y registra un warning en el log. No persiste.

**Método `completar(): void`**

Orquesta el cierre del pase:
1. Verificar que todos los ítems del schema tienen respuesta en `respuestas`. Si falta alguno, lanzar `\LogicException` con la lista de ítems sin respuesta.
2. Llamar a `calcularScores()`.
3. Llamar a `asignarInterpretacion()`.
4. Establecer `estado = EstadoPase::Completado`.
5. Llamar a `save()`.

**Relaciones:**
```php
public function tipoEscala(): BelongsTo
{
    return $this->belongsTo(TipoEscala::class, 'tipo_escala_id');
}

public function historia(): BelongsTo
{
    return $this->belongsTo(HistoriaSocial::class, 'historia_id');
}

public function profesional(): BelongsTo
{
    return $this->belongsTo(Usuario::class, 'profesional_id');
}
```

---

## 6. Relación en `HistoriaSocial`

Añadir al modelo `HistoriaSocial` (en `Modules/Intervencion/app/Models/HistoriaSocial.php`):

```php
/**
 * Pases de escala registrados en esta historia social.
 * Filtrar por tipo_escala_id para obtener la serie temporal de un instrumento concreto.
 */
public function pasesEscala(): HasMany
{
    return $this->hasMany(PaseEscala::class, 'historia_id');
}
```

---

## 7. Factories

### `TipoEscalaFactory`

Ubicación: `Modules/Escalas/database/factories/TipoEscalaFactory.php`

El factory debe generar un schema mínimo válido con una sección y dos ítems, cada uno con tres opciones (valores 0, 5, 10), y una tabla de rangos que cubra 0–20 (bajo) y 21–100 (alto). Esto garantiza que todos los tests que crean un `TipoEscala` de fábrica tengan un instrumento funcional sin configuración adicional.

```php
public function definition(): array
{
    return [
        'nombre'  => fake()->words(3, true),
        'codigo'  => fake()->unique()->slug(2),
        'descripcion' => fake()->sentence(),
        'instrucciones_aplicacion' => fake()->paragraph(),
        'confirmar_instrucciones'  => false,
        'fuente'  => null,
        'contextos' => ['dependencia'],
        'activa'  => true,
        'schema'  => [
            'secciones' => [
                [
                    'id'     => 'sec_1',
                    'titulo' => 'Sección de prueba',
                    'instrucciones' => null,
                    'orden'  => 1,
                    'items'  => [
                        [
                            'id'      => 'item_1_1',
                            'texto'   => 'Ítem uno',
                            'instrucciones' => null,
                            'orden'   => 1,
                            'opciones' => [
                                ['valor' => 0,  'etiqueta' => 'Bajo'],
                                ['valor' => 5,  'etiqueta' => 'Medio'],
                                ['valor' => 10, 'etiqueta' => 'Alto'],
                            ],
                        ],
                        [
                            'id'      => 'item_1_2',
                            'texto'   => 'Ítem dos',
                            'instrucciones' => null,
                            'orden'   => 2,
                            'opciones' => [
                                ['valor' => 0,  'etiqueta' => 'Bajo'],
                                ['valor' => 5,  'etiqueta' => 'Medio'],
                                ['valor' => 10, 'etiqueta' => 'Alto'],
                            ],
                        ],
                    ],
                ],
            ],
        ],
        'rangos_interpretacion' => [
            'rangos' => [
                ['desde' => 0,  'hasta' => 10, 'etiqueta' => 'Bajo',  'codigo' => 'bajo'],
                ['desde' => 11, 'hasta' => 20, 'etiqueta' => 'Alto',  'codigo' => 'alto'],
            ],
            'nota_interpretacion' => null,
        ],
    ];
}

/** Estado: inactiva */
public function inactiva(): static
{
    return $this->state(['activa' => false]);
}
```

### `PaseEscalaFactory`

Ubicación: `Modules/Escalas/database/factories/PaseEscalaFactory.php`

```php
public function definition(): array
{
    return [
        'tipo_escala_id'  => TipoEscala::factory(),
        'historia_id'     => HistoriaSocial::factory(),
        'profesional_id'  => Usuario::factory(),
        'fecha'           => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
        'respuestas'      => [
            'sec_1' => ['item_1_1' => 5, 'item_1_2' => 10],
        ],
        'score_total'     => null,
        'scores_seccion'  => null,
        'interpretacion_codigo' => null,
        'notas'           => null,
        'estado'          => EstadoPase::Borrador,
        'ficha_id'        => null,
        'entrevista_id'   => null,
    ];
}

/** Estado: completado con scores calculados */
public function completado(): static
{
    return $this->state([
        'estado'          => EstadoPase::Completado,
        'score_total'     => 15,
        'scores_seccion'  => ['sec_1' => 15],
        'interpretacion_codigo' => 'alto',
    ]);
}
```

---

## 8. Seeder `EscalaSeeder`

Ubicación: `Modules/Escalas/database/seeders/EscalaSeeder.php`

El seeder carga los tres instrumentos de libre uso identificados en `docs/modulo-escala.md` sección 7. Usar `updateOrCreate` con `codigo` como clave de búsqueda para garantizar idempotencia.

### 8.1 Índice de Barthel (`barthel`)

10 ítems agrupados en una sección. Valores: 0/5/10 o 0/5/10/15 según ítem. Score total 0–100. Rangos: total (0–20), severa (21–60), moderada (61–90), escasa (91–99), independiente (100).

Ítems (en español, con sus opciones):

| Ítem | Opciones (valor: etiqueta) |
|---|---|
| Comer | 0: Dependiente · 5: Necesita ayuda · 10: Independiente |
| Lavarse / ducharse | 0: Dependiente · 5: Independiente |
| Vestirse | 0: Dependiente · 5: Necesita ayuda · 10: Independiente |
| Arreglarse | 0: Dependiente · 5: Independiente |
| Deposición | 0: Incontinente · 5: Accidente ocasional · 10: Continente |
| Micción | 0: Incontinente · 5: Accidente ocasional · 10: Continente |
| Usar el retrete | 0: Dependiente · 5: Necesita ayuda · 10: Independiente |
| Trasladarse sillón/cama | 0: Dependiente · 5: Ayuda importante · 10: Mínima ayuda · 15: Independiente |
| Deambulación | 0: Dependiente · 5: Independiente en silla de ruedas · 10: Camina con ayuda · 15: Independiente |
| Subir y bajar escaleras | 0: Dependiente · 5: Necesita ayuda · 10: Independiente |

Incluir instrucciones de aplicación: *«Evalúe la situación de los últimos 7-10 días. Puntúe lo que el ciudadano HACE realmente, no lo que podría hacer. Si existe duda entre dos puntuaciones, elija la inferior.»*

Fuente: `Mahoney FI, Barthel D. "Functional evaluation: the Barthel Index." Maryland State Med Journal 1965;14:56-61. Uso no comercial permitido con esta cita.`

### 8.2 Cuestionario Portátil del Estado Mental de Pfeiffer — SPMSQ (`pfeiffer_spmsq`)

10 preguntas de respuesta correcta/incorrecta. Errores como puntuación (score = número de respuestas incorrectas). Rangos: intacto (0–2), deterioro leve (3–4), deterioro moderado (5–7), deterioro grave (8–10).

Preguntas (en español):

1. ¿Cuál es la fecha de hoy? (día, mes, año)
2. ¿Qué día de la semana es hoy?
3. ¿Cuál es el nombre de este lugar?
4. ¿Cuál es su número de teléfono? (o dirección si no tiene teléfono)
5. ¿Cuántos años tiene?
6. ¿Cuándo nació usted?
7. ¿Quién es el presidente del gobierno actualmente?
8. ¿Quién fue el presidente del gobierno anterior?
9. ¿Cuál es el primer apellido de su madre?
10. Reste de 3 en 3 desde 20 (o desde 30 en versión alternativa)

Opciones por ítem: `0: Correcta · 1: Incorrecta`. El score total es la suma de errores (máx. 10).

Nota de corrección: se permite 1 error adicional si el ciudadano no ha recibido educación primaria; se exige 1 error menos si tiene estudios superiores.

Incluir esta nota en `instrucciones_aplicacion` del instrumento.

Fuente: `Pfeiffer E. "A short portable mental status questionnaire for the assessment of organic brain deficit in elderly patients." J Am Geriatr Soc. 1975;23(10):433-41. Adaptación española: Martínez de la Iglesia J et al. Rev Esp Geriatr Gerontol. 2001;36(2):92-8.`

### 8.3 Escala de Lawton y Brody — AIVD (`lawton_brody`)

8 ítems de actividades instrumentales. Cada ítem puntúa 0 o 1 (0 = dependiente, 1 = independiente). Score total 0–8. Rangos: dependencia total (0–1), dependencia severa (2–3), dependencia moderada (4–5), dependencia leve (6–7), independencia (8).

Ítems:

| Ítem | Opciones |
|---|---|
| Usar el teléfono | 0: Incapaz · 1: Capaz |
| Hacer compras | 0: Incapaz · 1: Capaz |
| Preparar la comida | 0: Incapaz · 1: Capaz |
| Cuidado del hogar | 0: Incapaz · 1: Capaz |
| Lavado de ropa | 0: Incapaz · 1: Capaz |
| Uso de medios de transporte | 0: Incapaz · 1: Capaz |
| Control de medicación | 0: Incapaz · 1: Capaz |
| Manejo de dinero | 0: Incapaz · 1: Capaz |

Incluir instrucción: *«Los ítems en los que la persona no ha participado nunca (p.ej. preparar la comida en personas que nunca lo han hecho) deben excluirse del cómputo. En ese caso el score máximo posible se reduce en 1 por ítem excluido. El profesional debe registrar en el campo de notas del pase qué ítems han sido excluidos y el motivo.»*

Fuente: `Lawton MP, Brody EM. "Assessment of older people: self-maintaining and instrumental activities of daily living." Gerontologist. 1969;9(3):179-86. Dominio público.`

---

## 9. Recurso Filament `TipoEscalaResource`

Ubicación: `app/Filament/Resources/TipoEscalaResource.php` (siguiendo la convención centralizada del proyecto).

### 9.1 Tabla (listado)

Columnas: `codigo` (con badge), `nombre`, `fuente` (truncada a 60 chars), `activa` (ToggleColumn inline).

Filtros: por `activa` (ternario), por `contextos` (multiselect desde `catalogos_sistema` grupo `escala.contexto`).

Búsqueda global sobre `nombre` y `codigo`.

Acciones de fila: editar, desactivar (que establece `activa = false` sin borrar).

### 9.2 Formulario (crear / editar)

Organizado en tres pestañas (`Tabs`):

**Pestaña «Datos generales»:**
- `nombre` — TextInput, requerido.
- `codigo` — TextInput, requerido. Deshabilitar (`disabled()`) si `$record->pases()->exists()`. Incluir `helperText('Identificador estable. No se puede modificar una vez que existen pases asociados.')`.
- `fuente` — TextInput, opcional.
- `activa` — Toggle.
- `confirmar_instrucciones` — Toggle con label «Requiere confirmación de lectura antes del pase».
- `contextos` — CheckboxList o MultiSelect desde `CatalogoSistema::opcionesParaSelect('escala.contexto')`.
- `descripcion` — Textarea.
- `instrucciones_aplicacion` — Textarea, rows 6.

**Pestaña «Estructura»:**

Repeater `secciones` (extraído del campo `schema`). Cada sección tiene:
- `titulo` — TextInput, requerido.
- `instrucciones` — Textarea opcional, con `hint('Se muestra al profesional al entrar en esta sección.')`.
- Repeater `items`. Cada ítem tiene:
  - `texto` — TextInput, requerido. Deshabilitar si el ítem ya tiene `id` y existen pases.
  - `instrucciones` — Textarea opcional.
  - Repeater `opciones`. Cada opción tiene:
    - `valor` — TextInput numérico, requerido.
    - `etiqueta` — TextInput, requerido.
    - Deshabilitar toda la fila de opciones si el ítem ya existe en pases.

El repeater construye y descompone el campo `schema` JSONB. Usar `afterStateHydrated` y `dehydrateStateUsing` en el campo `schema` para transformar entre el array JSONB y la estructura del repeater.

**Pestaña «Rangos e interpretación»:**

Repeater para `rangos_interpretacion.rangos`. Cada rango tiene:
- `desde` — TextInput numérico, requerido.
- `hasta` — TextInput numérico, requerido.
- `etiqueta` — TextInput, requerido.
- `codigo` — TextInput, requerido.

Campo `nota_interpretacion` — Textarea opcional.

Misma técnica de `afterStateHydrated` / `dehydrateStateUsing` para componer y descomponer el JSONB.

### 9.3 Navegación

Grupo de navegación: `Configuración`. Icono: `heroicon-o-clipboard-list`. Orden: después de `TipoFichaResource`.

---

## 10. Tests funcionales

Ubicación: `Modules/Escalas/tests/Feature/EscalaTest.php`

**Convenciones:**
- PHPUnit con atributo `#[Test]`. No usar Pest.
- Base de datos: PostgreSQL (`vida_testing`). No usar SQLite. Trait `RefreshDatabase`.
- Patrón: Dado / Cuando / Entonces en comentarios del test.
- Negativo obligatorio en los tests de restricciones de dominio (A05, A06, B02, B06).

**Actores reutilizados** — definir en `setUp()` o trait:
- `$profesional` — usuario con rol `intervencion`.
- `$admin` — usuario con rol `adm_sistema`.
- `$historia` — `HistoriaSocial` con ciudadano asociado.

---

### TF-ESC-A01 — TipoEscala con schema JSON inválido no puede guardarse

```
Dado: ningún tipo de escala existente.
Cuando: se intenta crear un TipoEscala con schema = 'esto no es json{'.
Entonces: se lanza InvalidArgumentException antes de llegar a BD; no existe ningún registro en tipo_escalas.
```

Verificar también en negativo: si se elimina la validación del observer, el test debe fallar.

---

### TF-ESC-A02 — TipoEscala con rangos solapados no puede guardarse

```
Dado: ningún tipo de escala existente.
Cuando: se intenta crear un TipoEscala con rangos_interpretacion donde el rango A tiene hasta=60
        y el rango B tiene desde=55.
Entonces: se lanza InvalidArgumentException con mensaje que identifica el solapamiento;
          no existe ningún registro en tipo_escalas.
```

---

### TF-ESC-A03 — TipoEscala con rangos con huecos no puede guardarse

```
Dado: un TipoEscala con schema que produce scores de 0 a 20.
Cuando: se intenta guardar con rangos que cubren 0–8 y 11–20, dejando hueco en 9–10.
Entonces: se lanza InvalidArgumentException con mensaje que identifica el hueco;
          el registro no se guarda.
```

---

### TF-ESC-A04 — TipoEscala inactiva no aparece en scope aplicables

```
Dado: un TipoEscala con activa=true y otro con activa=false.
Cuando: se llama a TipoEscala::aplicables()->get().
Entonces: la colección contiene exactamente 1 registro; el de activa=false no está.
```

---

### TF-ESC-A05 — Codigo de TipoEscala es inmutable si existen pases asociados

```
Dado: un TipoEscala con codigo='barthel' y un PaseEscala asociado a él.
Cuando: se intenta actualizar el codigo a 'barthel_v2'.
Entonces: se lanza LogicException; $tipoEscala->fresh()->codigo sigue siendo 'barthel'.
```

Verificar también en negativo: si se elimina la protección en el observer, el test debe fallar.

---

### TF-ESC-A06 — Schema de ítem existente es inmutable si existen pases asociados

```
Dado: un TipoEscala con ítem id='item_1_1' y un PaseEscala con respuesta para ese ítem.
Cuando: se intenta modificar las opciones del ítem 'item_1_1' en el schema.
Entonces: se lanza LogicException; el schema en BD no cambia.
```

Verificar también en negativo.

---

### TF-ESC-B01 — PaseEscala en borrador puede tener ítems sin respuesta

```
Dado: un TipoEscala con 2 ítems.
Cuando: se crea un PaseEscala con estado=borrador y respuestas que incluyen solo 1 ítem.
Entonces: el pase se guarda sin errores; $pase->score_total es null.
```

---

### TF-ESC-B02 — PaseEscala completado requiere respuesta para todos los ítems

```
Dado: un TipoEscala con 2 ítems; un PaseEscala con estado=borrador y solo 1 respuesta.
Cuando: se llama a $pase->completar().
Entonces: se lanza LogicException; $pase->fresh()->estado sigue siendo borrador.
```

Verificar también en negativo.

---

### TF-ESC-B03 — calcularScores suma correctamente todos los valores

```
Dado: TipoEscala con 2 ítems en sec_1 (valores posibles 0/5/10).
Cuando: se crea PaseEscala con respuestas {sec_1: {item_1_1: 10, item_1_2: 5}};
        se llama a $pase->calcularScores().
Entonces: $pase->score_total === 15.
```

---

### TF-ESC-B04 — calcularScores produce scores_seccion correctos por sección

```
Dado: misma configuración que TF-ESC-B03 pero con dos secciones:
      sec_1 con ítem_1_1=10 e item_1_2=5; sec_2 con item_2_1=0.
Cuando: se llama a $pase->calcularScores().
Entonces: $pase->scores_seccion === ['sec_1' => 15, 'sec_2' => 0].
```

---

### TF-ESC-B05 — interpretacion_codigo se asigna correctamente según score_total

```
Dado: TipoEscala con rangos [0–10: 'bajo', 11–20: 'alto']; PaseEscala con score_total=15
      (asignado directamente, sin llamar a completar).
Cuando: se llama a $pase->asignarInterpretacion().
Entonces: $pase->interpretacion_codigo === 'alto'.
```

---

### TF-ESC-B06 — Score total de pase completado es inmutable

```
Dado: PaseEscala en estado completado con score_total=15.
Cuando: se intenta actualizar score_total=80 directamente sobre el modelo.
Entonces: se lanza LogicException; $pase->fresh()->score_total === 15.
```

Verificar también en negativo.

---

### TF-ESC-B07 — PaseEscala sin ficha_id ni entrevista_id es válido

```
Dado: TipoEscala activa, HistoriaSocial existente, profesional existente.
Cuando: se crea PaseEscala con ficha_id=null y entrevista_id=null.
Entonces: el pase se guarda sin errores; ambos campos son null en BD.
```

---

### TF-ESC-B08 — PaseEscala con ficha_id mantiene referencia al completarse la ficha

```
Dado: Ficha existente; PaseEscala con ficha_id apuntando a esa ficha.
Cuando: se marca la ficha como completada ($ficha->update(['completada' => true])).
Entonces: $pase->fresh()->ficha_id apunta a la misma ficha; la referencia no se pierde.
```

---

### TF-ESC-C01 — Historia Social devuelve pases en orden cronológico

```
Dado: tres PaseEscala completados para el mismo ciudadano y la misma escala,
      con fechas 2025-01-10, 2025-06-15 y 2026-01-20.
Cuando: $historia->pasesEscala()->where('tipo_escala_id', $escala->id)->orderBy('fecha')->get().
Entonces: el primer elemento tiene fecha 2025-01-10; el último 2026-01-20.
```

---

### TF-ESC-C02 — Pases de distintos ciudadanos no se mezclan

```
Dado: dos HistoriaSocial distintas, cada una con 2 pases de la misma escala.
Cuando: $historia1->pasesEscala()->get().
Entonces: la colección contiene exactamente 2 pases; ninguno tiene historia_id distinto al de $historia1.
```

---

### TF-ESC-C03 — Seeder produce los tres TipoEscala esperados

```
Dado: BD limpia (solo las tablas de estructura existentes).
Cuando: se ejecuta EscalaSeeder.
Entonces:
  - TipoEscala::count() === 3.
  - Existen registros con codigos 'barthel', 'pfeiffer_spmsq' y 'lawton_brody'.
  - Cada uno tiene activa=true.
  - El schema de cada uno es un array con al menos una sección y un ítem.
  - Los rangos_interpretacion de cada uno son un array con al menos un rango.
```

---

### TF-ESC-C04 — Seeder es idempotente

```
Dado: EscalaSeeder ya ejecutado una vez.
Cuando: se ejecuta EscalaSeeder una segunda vez.
Entonces: TipoEscala::count() sigue siendo 3; no hay registros duplicados por codigo.
```

---

## 11. Ejecución de los tests

```bash
php artisan test --filter=EscalaTest
```

Todos los tests deben pasar antes de cerrar la sesión. Si algún test falla, corregir la implementación (no el test) hasta que pase. La única excepción es un test de negativo que detecte una protección ausente: en ese caso implementar la protección.

---

## 12. Cierre de sesión

Seguir el protocolo estándar de `CLAUDE.md` sección 4:

**CHANGELOG.md** — añadir entrada con:
- Fecha de la sesión
- Módulo: Escalas
- Cambios: migraciones creadas, enum `EstadoPase`, modelos `TipoEscala` y `PaseEscala` con observers, relación añadida a `HistoriaSocial`, factories, `EscalaSeeder` con Barthel + Pfeiffer + Lawton-Brody, `TipoEscalaResource` en Filament, 18 tests funcionales pasando
- Decisiones de implementación tomadas que no estaban explícitamente en las instrucciones

**BACKLOG.md** — añadir si durante la sesión han surgido:
- Dependencias de integración con Livewire para la superficie de pase
- Cualquier ajuste al modelado identificado durante la implementación

**SESSION.md** — actualizar con:
- Tarea completada: «Módulo Escalas fase 1 — modelos, seeder, Filament, 18 tests pasando»
- Siguiente paso recomendado: «Módulo Escalas fase 2 — componente Livewire de aplicación del pase desde la Historia Social»

**Commit:**
```bash
git add -A
git commit -m "feat(escalas): módulo escalas fase 1 — modelos, seeder, filament, tests"
git push origin main
```

---

*Instrucciones preparadas: mayo 2026. Referencia: `docs/modulo-escala.md`.*
