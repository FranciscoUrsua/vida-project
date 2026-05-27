# Instrucciones CLI — Escalas: mejora UX del diseñador en Filament

> Leer este fichero íntegramente antes de tocar cualquier fichero.
> Prerrequisito: `docs/instrucciones-cli/escala-implementacion.md` ya ejecutado y tests pasando.
> Referencia de diseño: `docs/modulo-escala.md`.

---

## Contexto y alcance

La fase 1 del módulo Escalas está implementada y los 18 tests pasan. Esta sesión mejora
exclusivamente la usabilidad de la pestaña «Estructura» del formulario `TipoEscalaResource`
en Filament. No se toca ningún modelo, migración, test ni seeder.

El problema: la pestaña «Estructura» usa tres niveles de `Repeater` anidados
(secciones → ítems → opciones), lo que genera una interfaz visualmente caótica en la que
el administrador pierde el hilo de qué nivel está editando.

La solución: reemplazar el `Repeater` exterior de secciones por el componente `Builder`
nativo de Filament. `Builder` presenta cada sección como un bloque colapsable con header
propio y reordenación por drag-and-drop, manteniendo los `Repeater` interiores para ítems
y opciones donde sí tienen sentido.

**Fichero afectado:** `app/Filament/Resources/TipoEscalaResource.php` únicamente.
**Tests afectados:** ninguno. El cambio es puramente de presentación en Filament.
**Schema JSONB:** no cambia. El `Builder` produce y consume el mismo formato de array que
el `Repeater` anterior. La transformación `afterStateHydrated`/`dehydrateStateUsing` se
ajusta para trabajar con la estructura interna que genera `Builder`.

---

## Cambio único: pestaña «Estructura»

### Antes

```php
Repeater::make('secciones')  // exterior — problemático
    ->schema([
        TextInput::make('titulo'),
        Textarea::make('instrucciones'),
        Repeater::make('items')
            ->schema([
                TextInput::make('texto'),
                Textarea::make('instrucciones'),
                Repeater::make('opciones')
                    ->schema([...]),
            ]),
    ])
```

### Después

Reemplazar el `Repeater` exterior por un `Builder` con un único tipo de bloque `seccion`.
Los `Repeater` interiores de ítems y opciones no cambian.

```php
Builder::make('schema')
    ->label('Secciones e ítems')
    ->blocks([
        Builder\Block::make('seccion')
            ->label(fn (array $state): string =>
                filled($state['titulo'] ?? null)
                    ? $state['titulo']
                    : 'Nueva sección'
            )
            ->icon('heroicon-o-list-bullet')
            ->schema([

                TextInput::make('titulo')
                    ->label('Título de la sección')
                    ->required()
                    ->maxLength(200)
                    ->live(onBlur: true),

                Textarea::make('instrucciones')
                    ->label('Instrucciones de sección')
                    ->hint('Se muestran al profesional al entrar en esta sección. Opcional.')
                    ->rows(3)
                    ->nullable(),

                Repeater::make('items')
                    ->label('Ítems')
                    ->addActionLabel('Añadir ítem')
                    ->collapsible()
                    ->cloneable()
                    ->reorderableWithDragAndDrop()
                    ->itemLabel(fn (array $state): ?string => $state['texto'] ?? null)
                    ->schema([

                        TextInput::make('texto')
                            ->label('Texto del ítem')
                            ->required()
                            ->maxLength(500)
                            ->disabledOn('edit')  // ver nota de inmutabilidad
                            ->live(onBlur: true),

                        Textarea::make('instrucciones')
                            ->label('Instrucciones del ítem')
                            ->hint('Criterio de puntuación visible durante el pase. Opcional.')
                            ->rows(2)
                            ->nullable(),

                        Repeater::make('opciones')
                            ->label('Opciones de respuesta')
                            ->addActionLabel('Añadir opción')
                            ->reorderableWithDragAndDrop(false)
                            ->columns(2)
                            ->schema([
                                TextInput::make('valor')
                                    ->label('Valor numérico')
                                    ->numeric()
                                    ->integer()
                                    ->required(),
                                TextInput::make('etiqueta')
                                    ->label('Etiqueta')
                                    ->required()
                                    ->maxLength(100),
                            ])
                            ->minItems(2)
                            ->disabledOn('edit'),  // ver nota de inmutabilidad
                    ]),
            ]),
    ])
    ->collapsible()
    ->collapsed()
    ->cloneable()
    ->reorderableWithDragAndDrop()
    ->addActionLabel('Añadir sección')
    ->hint('Arrastra para reordenar secciones. Haz clic en el título para expandir.')
```

---

## Transformación del estado (hydration / dehydration)

`Builder` almacena cada bloque como `['type' => 'seccion', 'data' => [...]]`.
El campo `schema` del modelo almacena directamente `['secciones' => [...]]`.
Hay que transformar entre ambas representaciones al cargar y al guardar.

Aplicar en el campo `Builder::make('schema')`:

```php
->afterStateHydrated(function (Builder $component, ?array $state): void {
    if (empty($state)) {
        $component->state([]);
        return;
    }

    // Convertir estructura del modelo a estructura Builder:
    // modelo: ['secciones' => [['id'=>..., 'titulo'=>..., 'items'=>[...]], ...]]
    // Builder: [['type'=>'seccion', 'data'=>['titulo'=>..., 'items'=>[...], 'id'=>...]], ...]
    $builderState = collect($state['secciones'] ?? [])
        ->map(fn (array $seccion) => [
            'type' => 'seccion',
            'data' => $seccion,
        ])
        ->values()
        ->all();

    $component->state($builderState);
})

->dehydrateStateUsing(function (?array $state): array {
    if (empty($state)) {
        return ['secciones' => []];
    }

    // Convertir estructura Builder de vuelta a estructura del modelo
    $secciones = collect($state)
        ->filter(fn (array $block) => ($block['type'] ?? null) === 'seccion')
        ->map(fn (array $block, int $index) => array_merge(
            $block['data'],
            ['orden' => $index + 1]
        ))
        ->values()
        ->all();

    return ['secciones' => $secciones];
})
```

**Generación de IDs de sección e ítem.** Los IDs (`sec_1`, `item_1_1`, etc.) son estables
y necesarios para que el modelo los proteja de modificación cuando existen pases. Al crear
una nueva sección o ítem desde el Builder/Repeater, el ID debe generarse antes de guardar.
Implementar esto en el mismo `dehydrateStateUsing`, recorriendo las secciones e ítems y
asignando un ID si aún no tienen uno:

```php
->dehydrateStateUsing(function (?array $state): array {
    if (empty($state)) {
        return ['secciones' => []];
    }

    $secciones = collect($state)
        ->filter(fn ($block) => ($block['type'] ?? null) === 'seccion')
        ->map(function (array $block, int $si) {
            $seccion = $block['data'];
            $seccion['id']    ??= 'sec_' . ($si + 1);
            $seccion['orden']   = $si + 1;

            $seccion['items'] = collect($seccion['items'] ?? [])
                ->map(function (array $item, int $ii) use ($si) {
                    $item['id']    ??= 'item_' . ($si + 1) . '_' . ($ii + 1);
                    $item['orden']   = $ii + 1;
                    return $item;
                })
                ->values()
                ->all();

            return $seccion;
        })
        ->values()
        ->all();

    return ['secciones' => $secciones];
})
```

---

## Nota sobre `disabledOn('edit')` e inmutabilidad

Las instrucciones originales especificaban deshabilitar ítems y opciones existentes cuando
ya existen pases asociados (`$record->pases()->exists()`). La condición correcta en Filament
es una closure, no `disabledOn('edit')` (que deshabilita siempre en edición, incluso sin pases).

Reemplazar `->disabledOn('edit')` por:

```php
->disabled(fn (?TipoEscala $record): bool =>
    $record !== null && $record->pases()->exists()
)
```

Aplicar tanto al `TextInput::make('texto')` del ítem como al `Repeater::make('opciones')`.

Añadir también un `Placeholder` de aviso cuando se cumple la condición, para que el
administrador entienda por qué no puede editar:

```php
Placeholder::make('aviso_inmutabilidad')
    ->content('Este instrumento tiene pases registrados. Los ítems y opciones existentes no se pueden modificar para preservar la integridad del historial. Solo es posible añadir nuevas secciones o ítems.')
    ->visible(fn (?TipoEscala $record): bool =>
        $record !== null && $record->pases()->exists()
    ),
```

Colocar este `Placeholder` al inicio del bloque del `Builder`, antes de los ítems.

---

## Detalles de UX adicionales

Estos ajustes mejoran la experiencia sin cambiar la lógica:

**Secciones colapsadas por defecto.** `->collapsed()` en el `Builder` hace que al abrir
el formulario de edición de una escala con muchas secciones (como Barthel) el administrador
vea los títulos de todas las secciones en lugar de una pantalla de scroll interminable.
Al hacer clic en el header de una sección se expande.

**Label dinámico de sección.** `->label(fn (array $state) => ...)` hace que el header del
bloque colapsado muestre el título de la sección («Cuidado personal», «Movilidad») en lugar
del genérico «Bloque 1», «Bloque 2». Ya está incluido en el código de arriba.

**Label dinámico de ítem.** `->itemLabel(fn (array $state) => $state['texto'] ?? null)`
en el `Repeater` de ítems hace que cada ítem colapsado muestre el texto de la pregunta.

**Orden visual de las pestañas.** Confirmar que el orden en el formulario es:
1. Datos generales
2. Estructura  ← esta es la que se modifica
3. Rangos e interpretación

No cambiar el orden ni los nombres de las pestañas.

---

## Verificación

No hay tests nuevos para esta sesión. La verificación es manual:

1. Abrir en Filament la escala Barthel (creada por el seeder).
2. Ir a la pestaña «Estructura».
3. Comprobar que aparecen 3 bloques colapsados con los títulos de las secciones.
4. Expandir uno y comprobar que los ítems se ven correctamente con sus opciones.
5. Añadir una nueva sección, añadir un ítem con dos opciones, guardar.
6. Reabrir la escala y comprobar que la nueva sección persiste con su ID asignado.
7. Ejecutar los 18 tests existentes para confirmar que ningún cambio los ha roto:

```bash
php artisan test --filter=EscalaTest
```

Todos deben seguir pasando. Si alguno falla, corregir la implementación (no el test).

---

## Cierre de sesión

Seguir el protocolo estándar de `CLAUDE.md` sección 4.

**CHANGELOG.md** — añadir entrada con:
- Fecha de la sesión
- Módulo: Escalas
- Cambios: pestaña «Estructura» de `TipoEscalaResource` refactorizada de `Repeater` triple
  anidado a `Builder` con `Repeater` interior; transformación hydration/dehydration ajustada;
  condición de inmutabilidad corregida a closure; `Placeholder` de aviso añadido;
  labels dinámicos en secciones e ítems
- Decisiones de implementación tomadas que no estaban en las instrucciones

**SESSION.md** — actualizar con:
- Tarea completada: «Escalas — UX diseñador de secciones mejorada con Builder nativo Filament»
- Siguiente paso recomendado: «Módulo Escalas fase 2 — componente Livewire de aplicación
  del pase desde la Historia Social»

**Commit:**
```bash
git add -A
git commit -m "refactor(escalas): diseñador de secciones con Builder nativo Filament"
git push origin main
```

---

*Instrucciones preparadas: mayo 2026. Prerrequisito: `docs/instrucciones-cli/escala-implementacion.md`.*
