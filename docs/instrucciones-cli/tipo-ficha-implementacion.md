# Instrucciones CLI — Fichas de Valoración (`TipoFichaResource`)

> Fichero: `docs/instrucciones-cli/tipo-ficha-implementacion.md`
> Módulo afectado: `Modules/Intervencion` · Filament: `app/Filament/Resources/`
> Tests: `TF-INT-H01` a `TF-INT-H10`

---

## Contexto

El módulo Intervención ya tiene la migración y el modelo `TipoFicha` (tabla `tipo_fichas`,
campo `schema` JSON). Lo que se implementa en esta sesión es:

1. Completar el modelo `TipoFicha` con validación de schema, scopes e inmutabilidad.
2. Crear `TipoFichaResource` en Filament (grupo «Informes y Plantillas», sort 3).
3. Ajustar el sort de `TipoEscalaResource` de 5 a 4 (Informe y Documento están ocultos).
4. Crear `IntervencionFichaSeeder` con tres fichas de ejemplo.
5. Añadir los tests TF-INT-H01 a TF-INT-H10 al fichero de tests de Intervención.

Leer `docs/modulo-intervencion.md` íntegramente antes de tocar cualquier fichero.

---

## Estructura del schema JSON de `TipoFicha`

El campo `schema` es un objeto JSON con una única clave raíz `campos`, que contiene
un array de objetos campo. Esta estructura es el contrato estable que consume el
renderizador Livewire; no modificarla sin actualizar ambos lados.

```json
{
  "campos": [
    {
      "id": "ingresos_mensuales",
      "tipo": "numero",
      "etiqueta": "Ingresos mensuales del hogar",
      "descripcion": "Suma de todos los ingresos netos mensuales del hogar",
      "unidad": "€",
      "obligatorio": true,
      "orden": 1
    },
    {
      "id": "situacion_vivienda",
      "tipo": "select",
      "etiqueta": "Situación de la vivienda",
      "descripcion": null,
      "opciones": ["Propiedad", "Alquiler", "Cedida", "Precaria", "Sin vivienda"],
      "obligatorio": false,
      "orden": 2
    },
    {
      "id": "escala_barthel",
      "tipo": "escala",
      "etiqueta": "Resultado Barthel",
      "descripcion": "Puntuación total del último pase Barthel",
      "tipo_escala_id": 1,
      "obligatorio": false,
      "orden": 3
    },
    {
      "id": "observaciones",
      "tipo": "texto",
      "etiqueta": "Observaciones",
      "descripcion": null,
      "obligatorio": false,
      "orden": 4
    }
  ]
}
```

**Tipos de campo válidos:** `texto`, `numero`, `select`, `booleano`, `fecha`, `escala`.

Campos obligatorios en todos los tipos: `id`, `tipo`, `etiqueta`, `obligatorio`, `orden`.
Campos opcionales comunes: `descripcion`.
Campos específicos por tipo:
- `numero` → `unidad` (string nullable)
- `select` → `opciones` (array de strings, mínimo 2 elementos)
- `escala` → `tipo_escala_id` (integer, FK a `tipo_escalas`)

---

## Paso 1 — Completar el modelo `TipoFicha`

Fichero: `Modules/Intervencion/app/Models/TipoFicha.php`

El modelo ya existe. Añadir o completar:

**Casts:**
```php
protected $casts = [
    'schema' => 'array',
    'activo' => 'boolean',
];
```

**Constante de tipos válidos** (usada tanto en validación como en el Resource):
```php
public const TIPOS_CAMPO = ['texto', 'numero', 'select', 'booleano', 'fecha', 'escala'];
```

**Scopes:**
```php
/** Fichas activas disponibles para componer valoraciones. */
public function scopeActivos(Builder $query): Builder
{
    return $query->where('activo', true);
}
```

**Método de estado:**
```php
/**
 * Indica si esta ficha ya tiene instancias reales de datos (fichas cumplimentadas).
 * Cuando es true, los ids y tipos de campos existentes son inmutables.
 */
public function tieneFichasAsociadas(): bool
{
    return $this->fichas()->exists();
}
```

**Validación del schema en `booted()`:**

```php
protected static function booted(): void
{
    static::saving(function (TipoFicha $ficha): void {
        $ficha->validarSchema();
    });
}

/**
 * Valida la estructura del schema JSON antes de persistir.
 * Lanza ValidationException si el schema no cumple el contrato.
 *
 * @throws \Illuminate\Validation\ValidationException
 */
public function validarSchema(): void
{
    $schema = $this->schema;

    if (! is_array($schema) || ! isset($schema['campos']) || ! is_array($schema['campos'])) {
        throw ValidationException::withMessages([
            'schema' => 'El schema debe ser un objeto con la clave "campos" (array).',
        ]);
    }

    $idsVistos = [];

    foreach ($schema['campos'] as $i => $campo) {
        $prefijo = "schema.campos.{$i}";

        foreach (['id', 'tipo', 'etiqueta', 'obligatorio', 'orden'] as $requerido) {
            if (! isset($campo[$requerido])) {
                throw ValidationException::withMessages([
                    $prefijo => "El campo [{$i}] no tiene el atributo obligatorio '{$requerido}'.",
                ]);
            }
        }

        if (! in_array($campo['tipo'], self::TIPOS_CAMPO, true)) {
            throw ValidationException::withMessages([
                "{$prefijo}.tipo" => "Tipo '{$campo['tipo']}' no válido en campo [{$i}].",
            ]);
        }

        if ($campo['tipo'] === 'select') {
            if (empty($campo['opciones']) || ! is_array($campo['opciones']) || count($campo['opciones']) < 2) {
                throw ValidationException::withMessages([
                    "{$prefijo}.opciones" => "El campo select [{$i}] debe tener al menos 2 opciones.",
                ]);
            }
        }

        if ($campo['tipo'] === 'escala') {
            if (empty($campo['tipo_escala_id'])) {
                throw ValidationException::withMessages([
                    "{$prefijo}.tipo_escala_id" => "El campo escala [{$i}] requiere 'tipo_escala_id'.",
                ]);
            }
        }

        if (in_array($campo['id'], $idsVistos, true)) {
            throw ValidationException::withMessages([
                "{$prefijo}.id" => "El id '{$campo['id']}' está duplicado en el schema.",
            ]);
        }

        $idsVistos[] = $campo['id'];
    }

    // Inmutabilidad: si ya hay fichas asociadas, no se pueden eliminar ni cambiar tipo
    // de campos existentes. Solo se pueden añadir campos nuevos.
    if ($this->exists && $this->tieneFichasAsociadas()) {
        $schemaOriginal = TipoFicha::find($this->id)?->schema ?? ['campos' => []];
        $idsOriginales  = collect($schemaOriginal['campos'])->pluck('tipo', 'id')->all();

        foreach ($idsOriginales as $id => $tipo) {
            $campoActual = collect($schema['campos'])->firstWhere('id', $id);

            if ($campoActual === null) {
                throw ValidationException::withMessages([
                    'schema' => "No se puede eliminar el campo '{$id}': ya existen fichas cumplimentadas.",
                ]);
            }

            if ($campoActual['tipo'] !== $tipo) {
                throw ValidationException::withMessages([
                    'schema' => "No se puede cambiar el tipo del campo '{$id}': ya existen fichas cumplimentadas.",
                ]);
            }
        }
    }
}
```

---

## Paso 2 — Crear `TipoFichaResource`

Fichero: `app/Filament/Resources/TipoFichaResource.php`

### Cabecera y navegación

```php
protected static ?string $model = TipoFicha::class;
protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
protected static ?string $navigationGroup = 'Informes y Plantillas';
protected static ?string $navigationLabel = 'Fichas de Valoración';
protected static ?string $modelLabel = 'Ficha de Valoración';
protected static ?string $pluralModelLabel = 'Fichas de Valoración';
protected static ?int $navigationSort = 3;
```

### Tabla (método `table()`)

Columnas:
- `nombre` — searchable, sortable
- `descripcion` — limit(80), label «Contexto y uso»
- columna calculada «Campos» — `getStateUsing(fn ($record) => count($record->schema['campos'] ?? []))`
- `activo` — badge booleano (verde/gris)
- `updated_at` — label «Última modificación», since

Filtros:
- `TernaryFilter::make('activo')` — Todas / Activas / Inactivas

Acciones de tabla: `EditAction`, `DeleteAction` (solo si `! $record->tieneFichasAsociadas()`).

### Formulario (método `form()`)

El formulario tiene **dos pestañas**: «Datos generales» y «Campos de la ficha».

#### Pestaña 1 — Datos generales

```php
TextInput::make('nombre')
    ->required()
    ->maxLength(200)
    ->label('Nombre de la ficha'),

Textarea::make('descripcion')
    ->label('Contexto y uso')
    ->helperText('Explica cuándo usar esta ficha y qué información recoge.')
    ->rows(4),

Toggle::make('activo')
    ->label('Activa')
    ->default(true),
```

#### Pestaña 2 — Campos de la ficha

Esta pestaña contiene un `Builder` de Filament con un bloque por tipo de campo.
Seguir exactamente el mismo patrón que `TipoEscalaResource`: `afterStateHydrated`
y `dehydrateStateUsing` para convertir entre el schema del modelo y la estructura
interna del Builder.

**`afterStateHydrated`:** convierte `['campos' => [...]]` a array de bloques Builder
`[['type' => 'texto', 'data' => [...]], ...]`. Si el estado ya está en formato Builder
(post-drag), no transforma.

**`dehydrateStateUsing`:** reconstruye `['campos' => [...]]` a partir de los bloques.
Genera `id` automático desde la etiqueta (snake_case + sufijo numérico si colisión)
si el campo no tiene `id`. Recalcula `orden` por posición (1-based).

**Aviso de inmutabilidad:** al inicio del Builder, cuando `$record?->tieneFichasAsociadas()`,
mostrar un `Placeholder` con texto: «Esta ficha tiene datos cumplimentados. Puedes añadir
nuevos campos, pero no puedes eliminar ni cambiar el tipo de los existentes.»

**Bloques del Builder:**

Todos los bloques comparten estos campos base:
```php
// Campo id: generado automáticamente, oculto al usuario
// Campo etiqueta
TextInput::make('etiqueta')
    ->label('Etiqueta visible al profesional')
    ->required(),

// Campo descripcion
Textarea::make('descripcion')
    ->label('Texto de ayuda (opcional)')
    ->rows(2)
    ->nullable(),

// Campo obligatorio
Toggle::make('obligatorio')
    ->label('Campo obligatorio')
    ->default(false),
```

Campos adicionales por bloque:

**Bloque `texto`** — Sin campos adicionales.
Label colapsado: `fn ($state) => 'Texto libre: ' . ($state['etiqueta'] ?? '—')`

**Bloque `numero`** — Añade:
```php
TextInput::make('unidad')
    ->label('Unidad (ej: €, m², km)')
    ->nullable(),
```
Label colapsado: `fn ($state) => 'Número: ' . ($state['etiqueta'] ?? '—')`

**Bloque `select`** — Añade:
```php
Repeater::make('opciones')
    ->label('Opciones del desplegable')
    ->simple(TextInput::make('valor')->required())
    ->minItems(2)
    ->reorderable()
    ->cloneable()
    ->helperText('Mínimo 2 opciones.'),
```
Label colapsado: `fn ($state) => 'Selección: ' . ($state['etiqueta'] ?? '—')`

**Bloque `booleano`** — Sin campos adicionales.
Label colapsado: `fn ($state) => 'Sí/No: ' . ($state['etiqueta'] ?? '—')`

**Bloque `fecha`** — Sin campos adicionales.
Label colapsado: `fn ($state) => 'Fecha: ' . ($state['etiqueta'] ?? '—')`

**Bloque `escala`** — Añade:
```php
Select::make('tipo_escala_id')
    ->label('Escala del catálogo')
    ->options(fn () => \Modules\Escalas\Models\TipoEscala::pluck('nombre', 'id'))
    ->searchable()
    ->required()
    ->helperText('Solo se capturará la puntuación total. El pase completo se realiza en el módulo Escalas.'),
```
Label colapsado: `fn ($state) => 'Escala: ' . ($state['etiqueta'] ?? '—')`

**Configuración del Builder:**
```php
Builder::make('schema')
    ->label('Campos de la ficha')
    ->blocks([...])
    ->collapsed()
    ->collapsible()
    ->cloneable()
    ->reorderable()
    ->blockNumbers(false)
    ->afterStateHydrated(function ($component, mixed $state) { ... })
    ->dehydrateStateUsing(function (mixed $state): array { ... })
```

---

## Paso 3 — Ajustar sort de `TipoEscalaResource`

Fichero: `app/Filament/Resources/TipoEscalaResource.php`

Cambiar:
```php
protected static ?int $navigationSort = 5;
```
Por:
```php
protected static ?int $navigationSort = 4;
```

`InformeResource` y `DocumentoResource` ya están ocultos (`->hidden()` o equivalente).
No tocarlos. Solo ajustar el sort de TipoEscala para que quede después de TipoFicha (3).

---

## Paso 4 — Seeder con tres fichas de ejemplo

Fichero: `Modules/Intervencion/database/seeders/IntervencionFichaSeeder.php`

Usar `updateOrCreate(['nombre' => ...])` para que sea idempotente.

**Ficha 1 — «Situación económica»**

Campos: `ingresos_mensuales` (numero, €, obligatorio), `num_convivientes` (numero,
obligatorio), `fuente_ingresos` (select: «Trabajo», «Pensión contributiva», «Pensión
no contributiva», «Prestación desempleo», «Ayudas sociales», «Sin ingresos»,
«Otros»), `deudas_relevantes` (booleano), `detalle_deudas` (texto, opcional).

**Ficha 2 — «Situación de vivienda»**

Campos: `regimen_tenencia` (select: «Propiedad sin hipoteca», «Propiedad con
hipoteca», «Alquiler», «Cedida», «Realojo temporal», «Sin vivienda»), `metros_cuadrados`
(numero, m²), `num_habitaciones` (numero), `estado_conservacion` (select: «Bueno»,
«Deteriorado», «Deficiente», «Infravivienda»), `barreras_arquitectonicas` (booleano),
`observaciones_vivienda` (texto).

**Ficha 3 — «Valoración social libre»**

Un único campo: `valoracion_libre` (texto, obligatorio). Descripción de ficha:
«Ficha de uso general para registrar una valoración narrativa sin estructura preestablecida.»

Registrar el seeder en `IntervencionSeeder` o en `DatabaseSeeder` según la convención
existente en el módulo.

---

## Paso 5 — Tests funcionales TF-INT-H01 a TF-INT-H10

Añadir al fichero de tests existente del módulo Intervención
(`Modules/Intervencion/tests/Feature/IntervencionTest.php`) el nuevo grupo H.

Convenciones:
- Framework: PHPUnit con `#[Test]`. No usar Pest.
- Base de datos: PostgreSQL (`vida_testing`).
- Patrón: Dado / Cuando / Entonces.

---

### Grupo H — Configuración de `TipoFicha`

---

**TF-INT-H01 — TipoFicha con schema válido se guarda correctamente**

- **Dado** ninguna ficha existente.
- **Cuando** se crea un `TipoFicha` con `nombre = 'Test'` y un schema con un campo
  de tipo `texto` con todos los atributos requeridos (`id`, `tipo`, `etiqueta`,
  `obligatorio`, `orden`).
- **Entonces** la ficha se persiste sin errores y `TipoFicha::count()` es 1.

---

**TF-INT-H02 — TipoFicha sin clave `campos` en schema lanza ValidationException**

- **Dado** ninguna ficha existente.
- **Cuando** se intenta crear un `TipoFicha` con `schema = ['otra_clave' => []]`.
- **Entonces** se lanza `\Illuminate\Validation\ValidationException` antes de llegar a BD.
- **Negativo:** si se elimina la validación de `booted()`, el test debe fallar.

---

**TF-INT-H03 — TipoFicha con tipo de campo inválido lanza ValidationException**

- **Dado** ninguna ficha existente.
- **Cuando** se intenta crear un `TipoFicha` con un campo de tipo `'desplegable'`
  (tipo no incluido en `TipoFicha::TIPOS_CAMPO`).
- **Entonces** se lanza `ValidationException`.

---

**TF-INT-H04 — Campo `select` sin opciones lanza ValidationException**

- **Dado** ninguna ficha existente.
- **Cuando** se intenta crear un `TipoFicha` con un campo de tipo `select` sin
  la clave `opciones` (o con array vacío).
- **Entonces** se lanza `ValidationException`.

---

**TF-INT-H05 — Campo `select` con menos de 2 opciones lanza ValidationException**

- **Dado** ninguna ficha existente.
- **Cuando** se intenta crear un `TipoFicha` con un campo de tipo `select` con
  `opciones = ['Solo una']`.
- **Entonces** se lanza `ValidationException`.

---

**TF-INT-H06 — Campo `escala` sin `tipo_escala_id` lanza ValidationException**

- **Dado** ninguna ficha existente.
- **Cuando** se intenta crear un `TipoFicha` con un campo de tipo `escala` sin
  la clave `tipo_escala_id`.
- **Entonces** se lanza `ValidationException`.

---

**TF-INT-H07 — IDs duplicados en el schema lanzan ValidationException**

- **Dado** ninguna ficha existente.
- **Cuando** se intenta crear un `TipoFicha` con dos campos que tienen el mismo `id`.
- **Entonces** se lanza `ValidationException`.

---

**TF-INT-H08 — No se puede eliminar un campo de una ficha con datos asociados**

- **Dado** un `TipoFicha` con dos campos (`campo_a` y `campo_b`) y una `Ficha`
  cumplimentada que referencia ese tipo.
- **Cuando** se intenta actualizar el `TipoFicha` con un schema que contiene
  solo `campo_a` (eliminando `campo_b`).
- **Entonces** se lanza `ValidationException` con mensaje que menciona `campo_b`.
- **Negativo:** si se elimina la guard de inmutabilidad, el test debe fallar.

---

**TF-INT-H09 — No se puede cambiar el tipo de un campo con datos asociados**

- **Dado** un `TipoFicha` con un campo `campo_a` de tipo `texto` y una `Ficha`
  cumplimentada.
- **Cuando** se intenta actualizar el `TipoFicha` cambiando `campo_a` a tipo `numero`.
- **Entonces** se lanza `ValidationException` con mensaje que menciona `campo_a`.

---

**TF-INT-H10 — Se puede añadir un campo nuevo a una ficha con datos asociados**

- **Dado** un `TipoFicha` con un campo `campo_a` de tipo `texto` y una `Ficha`
  cumplimentada.
- **Cuando** se actualiza el `TipoFicha` añadiendo un segundo campo `campo_b`
  (manteniendo `campo_a` intacto).
- **Entonces** la actualización se guarda sin errores y el schema tiene 2 campos.

---

## Verificación final

Ejecutar únicamente los tests del grupo H:

```bash
php artisan test --filter=IntervencionTest
```

Los 35 tests existentes (TF-INT-A a G) deben seguir pasando. Los 10 nuevos
(TF-INT-H01 a H10) deben pasar todos.

Actualizar `CHANGELOG.md`, `SESSION.md` y hacer commit:

```
feat(intervencion): TipoFichaResource — Fichas de Valoración en Filament
```

Registrar en `CLAUDE.md` el nuevo fichero de instrucciones en la tabla del apartado 6:

```
| `tipo-ficha-implementacion.md` | TipoFichaResource: creador de fichas de valoración + 10 tests (TF-INT-H01 a H10) |
```
