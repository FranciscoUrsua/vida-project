# Instrucciones CLI — Bugfix: TypeError en Builder tras drag-and-drop

> **IMPORTANTE: leer completo antes de abrir cualquier fichero.**
> **Presupuesto máximo: 10k tokens. Si no se resuelve en ese margen, parar y reportar.**

---

## El problema

Después de reordenar una sección por drag-and-drop en la pestaña «Estructura» de
`TipoEscalaResource`, al intentar expandir un bloque aparece:

```
TypeError
vendor/filament/schemas/src/Concerns/HasState.php:536
Filament\Schemas\Schema::getRawState(): Return value must be of type
Illuminate\Contracts\Support\Arrayable|array, int returned
```

El error **no ocurre** si no se ha arrastrado ningún bloque en la sesión.

---

## Causa exacta (diagnóstico ya hecho — no investigar más)

Cuando Filament procesa un drag-and-drop en un `Builder`, recalcula el estado interno
y puede entregar al callback de `dehydrateStateUsing` un array con valores enteros
(índices de reordenación) mezclados con los bloques normales.

El `filter()` actual en `dehydrateStateUsing` no descarta esos enteros porque su
guard solo comprueba `($block['type'] ?? null) === 'seccion'`, pero si `$block` es un
`int`, PHP no lanza error en el filter — lo descarta silenciosamente. El problema
está un paso antes: el `afterStateHydrated` no se re-ejecuta tras el drag, así que
cuando Filament llama a `getRawState()` internamente sobre el estado reordenado,
encuentra un int donde espera un array.

**La corrección son dos cambios puntuales, ambos en el mismo método:**

---

## Fichero a modificar

**Un único fichero:** `app/Filament/Resources/TipoEscalaResource.php`

No abrir ningún otro fichero. No leer vendors. No buscar en otros resources.

---

## Cambio 1 — `afterStateHydrated`: re-normalizar siempre, no solo al cargar

El callback actual solo transforma cuando hay estado inicial. Añadir una guardia
que re-normalice el estado si Filament lo entrega en formato plano (post-drag):

```php
->afterStateHydrated(function (Builder $component, mixed $state): void {
    // Estado vacío o nulo
    if (empty($state)) {
        $component->state([]);
        return;
    }

    // Si ya está en formato Builder (['type'=>..., 'data'=>...]), no transformar
    $first = is_array($state) ? reset($state) : null;
    if (is_array($first) && isset($first['type'])) {
        return; // ya está en formato correcto
    }

    // Viene del modelo: ['secciones' => [...]]
    if (is_array($state) && isset($state['secciones'])) {
        $builderState = collect($state['secciones'])
            ->filter(fn ($s) => is_array($s))   // descartar cualquier no-array
            ->map(fn (array $seccion) => [
                'type' => 'seccion',
                'data' => $seccion,
            ])
            ->values()
            ->all();

        $component->state($builderState);
        return;
    }

    // Fallback: estado inesperado — resetear en lugar de explotar
    $component->state([]);
})
```

## Cambio 2 — `dehydrateStateUsing`: guardia de tipo estricta

Añadir `is_array($block)` como primera comprobación en el `filter` para que cualquier
valor no-array (int, null, string) quede descartado antes de intentar acceder a `['type']`:

```php
->dehydrateStateUsing(function (mixed $state): array {
    if (empty($state) || !is_array($state)) {
        return ['secciones' => []];
    }

    $secciones = collect($state)
        ->filter(fn ($block) => is_array($block) && ($block['type'] ?? null) === 'seccion')
        ->map(function (array $block, int $si) {
            $seccion = $block['data'] ?? [];

            if (!is_array($seccion)) {
                return null; // bloque corrupto — descartar
            }

            $seccion['id']    ??= 'sec_' . ($si + 1);
            $seccion['orden']   = $si + 1;

            $seccion['items'] = collect($seccion['items'] ?? [])
                ->filter(fn ($item) => is_array($item)) // descartar no-arrays
                ->map(function (array $item, int $ii) use ($si) {
                    $item['id']    ??= 'item_' . ($si + 1) . '_' . ($ii + 1);
                    $item['orden']   = $ii + 1;
                    return $item;
                })
                ->values()
                ->all();

            return $seccion;
        })
        ->filter() // eliminar los null del paso anterior
        ->values()
        ->all();

    return ['secciones' => $secciones];
})
```

---

## Verificación

Solo estas acciones, en este orden:

1. Abrir la escala Barthel en Filament.
2. Ir a «Estructura».
3. Arrastrar la sección 2 encima de la sección 1.
4. Hacer clic para expandir cualquier bloque. **No debe aparecer el TypeError.**
5. Guardar. Reabrir. Comprobar que el orden persiste correctamente.
6. Ejecutar tests:

```bash
php artisan test --filter=EscalaTest
```

Los 18 tests deben seguir pasando.

---

## Si el error persiste tras aplicar los cambios

No seguir investigando. Reportar con:
- El estado exacto que llega al callback (añadir `\Log::debug(json_encode($state))` al
  inicio de `dehydrateStateUsing`, reproducir el error, y copiar la línea del log).
- La versión exacta de Filament instalada (`composer show filament/filament | grep versions`).

Esa información permitirá diagnosticar si el problema es una API distinta en la versión
instalada, que requeriría un enfoque diferente.

---

## Cierre

**Commit si los tests pasan:**
```bash
git add app/Filament/Resources/TipoEscalaResource.php
git commit -m "fix(escalas): TypeError en Builder tras drag-and-drop por estado no-array"
git push origin main
```

**CHANGELOG.md** — añadir entrada mínima:
- Bugfix: `TipoEscalaResource` — TypeError al expandir bloque Builder tras drag-and-drop.
  Causa: estado post-drag contenía valores no-array. Corrección: guardias `is_array()`
  en `afterStateHydrated` y `dehydrateStateUsing`.

---

*Bugfix preparado: mayo 2026.*
