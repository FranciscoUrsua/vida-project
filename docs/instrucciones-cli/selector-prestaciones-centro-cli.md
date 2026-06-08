# Instrucciones CLI — Selector de prestaciones de centro

> Leer `docs/modulo-centros.md` y `docs/modulo-prestaciones.md` antes de actuar.
> Leer también `docs/design-system/README.md` para respetar el tono visual del proyecto.
> Estos ficheros son la fuente de verdad sobre el modelo de datos y las relaciones.

---

## Alcance

Sustituir el `CheckboxList` de prestaciones en `CentroResource` por un botón en la
página de edición del centro que abre un SlideOver con un selector interactivo.
El selector permite filtrar por segmento de población, buscar por texto, y ver el
detalle de cada prestación. Las prestaciones seleccionadas se muestran en un panel
lateral dentro del SlideOver.

Ficheros afectados:
- `app/Filament/Resources/CentroResource.php` — eliminar la sección Prestaciones del formulario
- `app/Filament/Resources/CentroResource/Pages/EditCentro.php` — añadir la Action del SlideOver
- `app/Livewire/Centros/SelectorPrestacionesCentro.php` — componente nuevo
- `resources/views/livewire/centros/selector-prestaciones-centro.blade.php` — vista nueva

No tocar `CreateCentro.php`. La selección de prestaciones solo tiene sentido en edición,
no en la creación inicial del centro.

---

## Paso 1 — Eliminar el CheckboxList de prestaciones de CentroResource

En `app/Filament/Resources/CentroResource.php`, eliminar la `Section` entera de
'Prestaciones' del método `form()`:

```php
// ELIMINAR este bloque completo:
Section::make('Prestaciones')
    ->schema([
        CheckboxList::make('prestaciones')
            ->label('Prestaciones que ofrece')
            ->relationship('prestaciones', 'nombre')
            ->options(fn () => Prestacion::activas()->orderBy('nombre')->pluck('nombre', 'id'))
            ->columns(2),
    ]),
```

Eliminar también el import de `Filament\Forms\Components\CheckboxList` y el de
`Modules\Prestaciones\Models\Prestacion` si ya no se usan en otro lugar del fichero.
Verificar antes de eliminar que no haya otras referencias en el mismo fichero.

---

## Paso 2 — Añadir el Action en EditCentro

Crear o modificar `app/Filament/Resources/CentroResource/Pages/EditCentro.php`:

```php
<?php

namespace App\Filament\Resources\CentroResource\Pages;

use App\Filament\Resources\CentroResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

/**
 * Página de edición de un centro.
 *
 * Añade un Action en la cabecera para gestionar las prestaciones del centro
 * mediante un SlideOver con selector interactivo. La selección de prestaciones
 * se gestiona en el componente Livewire SelectorPrestacionesCentro, no en
 * el formulario principal del centro.
 */
class EditCentro extends EditRecord
{
    protected static string $resource = CentroResource::class;

    /**
     * Actions adicionales en la cabecera de la página de edición.
     * El botón de prestaciones abre un SlideOver con el selector Livewire.
     *
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('gestionarPrestaciones')
                ->label('Prestaciones del centro')
                ->icon('heroicon-o-squares-plus')
                ->color('gray')
                ->slideOver()
                ->modalWidth('4xl')
                ->modalHeading('Prestaciones del centro')
                ->modalDescription(
                    'Selecciona las prestaciones que ofrece este centro. ' .
                    'Los cambios se guardan al pulsar "Guardar selección".'
                )
                ->modalContent(
                    fn () => view(
                        'livewire.centros.selector-prestaciones-centro-modal',
                        ['centroId' => $this->record->id]
                    )
                )
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Cerrar'),
        ];
    }
}
```

El SlideOver usa `->modalSubmitAction(false)` porque el guardado lo gestiona el
propio componente Livewire internamente, no el botón de submit del modal de Filament.
El botón "Cerrar" cierra sin acción adicional.

---

## Paso 3 — Vista contenedora del modal

Crear `resources/views/livewire/centros/selector-prestaciones-centro-modal.blade.php`.

Esta vista es el puente entre el modal de Filament y el componente Livewire. Necesita
existir como fichero Blade independiente porque `modalContent()` de Filament recibe
una `View`, no un componente Livewire directamente.

```blade
<div class="p-1">
    @livewire('centros.selector-prestaciones-centro', ['centroId' => $centroId])
</div>
```

---

## Paso 4 — Componente Livewire SelectorPrestacionesCentro

Crear `app/Livewire/Centros/SelectorPrestacionesCentro.php`.

### Responsabilidades del componente

- Cargar las prestaciones activas del catálogo con sus datos completos.
- Cargar las prestaciones actualmente asociadas al centro.
- Filtrar por segmento de población y por texto de búsqueda.
- Agrupar las prestaciones por `objetivo_general` (nombre del grupo, no código).
- Gestionar la selección/deselección.
- Persistir los cambios en la tabla pivote `centro_prestacion` al pulsar "Guardar".
- Emitir una notificación de éxito o error al guardar.

```php
<?php

namespace App\Livewire\Centros;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Modules\Centro\Models\Centro;
use Modules\Prestaciones\Models\Prestacion;
use Filament\Notifications\Notification;

/**
 * Selector interactivo de prestaciones para un centro.
 *
 * Muestra el catálogo completo de prestaciones activas con filtros por
 * segmento de población y búsqueda por texto. Las prestaciones se agrupan
 * por objetivo general. La selección se persiste en la tabla pivote
 * centro_prestacion al guardar.
 *
 * @property-read \Illuminate\Support\Collection $prestacionesFiltradas
 * @property-read array $segmentosFiltro
 */
class SelectorPrestacionesCentro extends Component
{
    /** @var int ID del centro que se está editando */
    public int $centroId;

    /** @var array<int> IDs de prestaciones actualmente seleccionadas */
    public array $seleccionadas = [];

    /** @var string Texto de búsqueda libre */
    public string $busqueda = '';

    /**
     * Segmento de población activo como filtro.
     * 'todos' significa sin filtro de segmento.
     *
     * @var string
     */
    public string $segmentoActivo = 'todos';

    /** @var int|null ID de la prestación cuya ficha está abierta */
    public ?int $prestacionDetalle = null;

    /**
     * Inicializa el componente cargando las prestaciones ya asociadas al centro.
     *
     * @param int $centroId
     */
    public function mount(int $centroId): void
    {
        $this->centroId = $centroId;

        $centro = Centro::findOrFail($centroId);
        $this->seleccionadas = $centro->prestaciones()->pluck('prestaciones.id')->toArray();
    }

    /**
     * Devuelve las opciones de filtro por segmento de población.
     * Se derivan de los segmentos actualmente asociados al centro,
     * más la opción "Todos" siempre presente.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function segmentosFiltro(): array
    {
        $centro = Centro::with('segmentosPoblacion')->find($this->centroId);

        $segmentos = ['todos' => 'Todos'];

        if ($centro && $centro->segmentosPoblacion->isNotEmpty()) {
            foreach ($centro->segmentosPoblacion as $seg) {
                $segmentos[$seg->id] = $seg->nombre;
            }
        }

        return $segmentos;
    }

    /**
     * Devuelve las prestaciones filtradas y agrupadas por objetivo general.
     * Aplica el filtro de segmento y la búsqueda por texto.
     *
     * @return \Illuminate\Support\Collection<string, \Illuminate\Support\Collection>
     */
    #[Computed]
    public function prestacionesFiltradas(): \Illuminate\Support\Collection
    {
        $query = Prestacion::activas()
            ->with('tiposCentro')
            ->orderBy('objetivo_general')
            ->orderBy('nombre');

        // Filtro por segmento de población
        // La relación entre Prestacion y SegmentoPoblacion se hace a través de
        // prestacion_tipo_centro. Si el segmento activo no es 'todos', filtrar
        // las prestaciones cuyo tipo_centro incluya al menos uno de los tipos
        // del centro para ese segmento.
        // Si el modelo Prestacion tiene relación directa con segmentos de población,
        // usar esa relación. Consultar docs/modulo-prestaciones.md para confirmar.
        // Implementación provisional: filtrar por texto del nombre del segmento
        // en el campo correspondiente de Prestacion si existe, o ignorar el filtro
        // de segmento y emitir un TODO en el código si la relación no está clara.
        if ($this->segmentoActivo !== 'todos') {
            // TODO: ajustar cuando se confirme la relación Prestacion <-> SegmentoPoblacion
            // Por ahora se pasa el filtro de segmento como whereHas si existe la relación,
            // o se omite si no. Ver docs/modulo-prestaciones.md sección de relaciones.
        }

        // Filtro por texto de búsqueda (código o nombre)
        if ($this->busqueda !== '') {
            $busqueda = $this->busqueda;
            $query->where(function ($q) use ($busqueda) {
                $q->where('codigo', 'ilike', "%{$busqueda}%")
                  ->orWhere('nombre', 'ilike', "%{$busqueda}%");
            });
        }

        // Agrupar por nombre del objetivo general (no por código)
        // El nombre del grupo viene del catálogo, no de los dos primeros dígitos del código.
        // Si Prestacion tiene un campo 'objetivo_general_nombre' o una relación con
        // catalogos_sistema, usar ese nombre. Si no, usar el campo 'objetivo_general'
        // como clave de grupo y mostrar el nombre del catálogo si está disponible.
        // Consultar la migración y el modelo de Prestacion antes de asumir el nombre del campo.
        return $query->get()->groupBy(function (Prestacion $p) {
            // Ajustar el nombre del campo según el modelo real
            return $p->objetivo_general_nombre
                ?? $p->objetivo_general
                ?? 'Sin clasificar';
        });
    }

    /**
     * Alterna la selección de una prestación.
     *
     * @param int $prestacionId
     */
    public function togglePrestacion(int $prestacionId): void
    {
        if (in_array($prestacionId, $this->seleccionadas, true)) {
            $this->seleccionadas = array_values(
                array_filter($this->seleccionadas, fn ($id) => $id !== $prestacionId)
            );
        } else {
            $this->seleccionadas[] = $prestacionId;
        }
    }

    /**
     * Elimina una prestación del panel de seleccionadas.
     * Equivalente a togglePrestacion cuando ya está seleccionada.
     *
     * @param int $prestacionId
     */
    public function deseleccionar(int $prestacionId): void
    {
        $this->seleccionadas = array_values(
            array_filter($this->seleccionadas, fn ($id) => $id !== $prestacionId)
        );
    }

    /**
     * Activa el filtro de segmento de población.
     *
     * @param string $segmento
     */
    public function setSegmento(string $segmento): void
    {
        $this->segmentoActivo = $segmento;
    }

    /**
     * Abre la ficha de detalle de una prestación.
     *
     * @param int $prestacionId
     */
    public function verDetalle(int $prestacionId): void
    {
        $this->prestacionDetalle = $prestacionId;
    }

    /**
     * Cierra la ficha de detalle.
     */
    public function cerrarDetalle(): void
    {
        $this->prestacionDetalle = null;
    }

    /**
     * Persiste la selección de prestaciones en la tabla pivote centro_prestacion.
     * Usa sync() para gestionar altas y bajas en una sola operación.
     */
    public function guardar(): void
    {
        $centro = Centro::findOrFail($this->centroId);
        $centro->prestaciones()->sync($this->seleccionadas);

        Notification::make()
            ->title('Prestaciones actualizadas correctamente.')
            ->success()
            ->send();
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.centros.selector-prestaciones-centro');
    }
}
```

### Notas de implementación para el componente

**Campo `objetivo_general_nombre`:** Antes de implementar el agrupamiento, consultar
la migración de `prestaciones` y el modelo `Prestacion` para saber cómo se llama el
campo que contiene el nombre del grupo. Si es una relación con `catalogos_sistema`,
hacer el `with()` correspondiente en la query y acceder al nombre a través de la
relación. No asumir el nombre del campo.

**Filtro por segmento de población:** La relación entre `Prestacion` y
`SegmentoPoblacion` no está completamente clara en la documentación disponible.
Consultar si existe una tabla `prestacion_segmento_poblacion` o si el filtro se
hace a través de `prestacion_tipo_centro`. Si la relación no existe todavía,
omitir el filtro por segmento en esta primera versión y añadir un `// TODO` con
la nota correspondiente al `BACKLOG.md`.

**`sync()` en la relación prestaciones:** Verificar que el modelo `Centro` tiene
definida la relación `prestaciones()` como `BelongsToMany` apuntando a la tabla
`centro_prestacion`. Si la relación no existe, crearla en el modelo antes de
continuar. La tabla pivote `centro_prestacion` está documentada en
`docs/modulo-centros.md` sección 6.

---

## Paso 5 — Vista Blade del componente

Crear `resources/views/livewire/centros/selector-prestaciones-centro.blade.php`.

La vista se divide en dos columnas: catálogo con filtros a la izquierda (2/3 del ancho)
y panel de seleccionadas a la derecha (1/3). En la parte inferior, el botón "Guardar
selección" persiste los cambios.

Seguir el design system del proyecto: colores neutros institucionales, sentence case
en todos los textos, sin gradientes ni efectos visuales. Usar clases de Tailwind
coherentes con el resto de vistas Livewire del proyecto (referencia:
`resources/views/livewire/admin/gestor-unidades-organizativas.blade.php`).

```blade
<div class="flex flex-col gap-4">

    {{-- Filtros --}}
    <div class="flex flex-col gap-3">

        {{-- Búsqueda por texto --}}
        <div class="relative">
            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                <x-heroicon-o-magnifying-glass class="h-4 w-4 text-gray-400" />
            </div>
            <input
                type="text"
                wire:model.live.debounce.300ms="busqueda"
                placeholder="Buscar por código o nombre…"
                class="block w-full rounded-lg border border-gray-300 bg-white py-2 pl-9 pr-3 text-sm
                       text-gray-900 placeholder-gray-400 focus:border-primary-500 focus:outline-none
                       focus:ring-1 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800
                       dark:text-white dark:placeholder-gray-500"
            >
        </div>

        {{-- Filtros por segmento de población --}}
        @if (count($this->segmentosFiltro) > 1)
            <div class="flex flex-wrap gap-2">
                @foreach ($this->segmentosFiltro as $id => $nombre)
                    <button
                        wire:click="setSegmento('{{ $id }}')"
                        class="rounded-full border px-3 py-1 text-xs font-medium transition-colors
                               {{ $segmentoActivo == $id
                                   ? 'border-primary-300 bg-primary-50 text-primary-700 dark:border-primary-700 dark:bg-primary-950 dark:text-primary-300'
                                   : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400' }}"
                    >
                        {{ $nombre }}
                    </button>
                @endforeach
            </div>
        @endif

    </div>

    {{-- Cuerpo: catálogo + seleccionadas --}}
    <div class="grid grid-cols-3 gap-4">

        {{-- Catálogo --}}
        <div class="col-span-2 overflow-y-auto rounded-lg border border-gray-200 dark:border-gray-700"
             style="max-height: 480px;">
            @forelse ($this->prestacionesFiltradas as $grupo => $prestaciones)
                <div class="border-b border-gray-100 last:border-b-0 dark:border-gray-800">

                    {{-- Cabecera de grupo --}}
                    <div class="sticky top-0 bg-gray-50 px-4 py-2 dark:bg-gray-900">
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
                            {{ $grupo }}
                        </p>
                    </div>

                    {{-- Prestaciones del grupo --}}
                    @foreach ($prestaciones as $prestacion)
                        <div class="flex items-start gap-3 border-b border-gray-50 px-4 py-3
                                    last:border-b-0 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-800/50">

                            {{-- Checkbox --}}
                            <div class="pt-0.5">
                                <input
                                    type="checkbox"
                                    wire:click="togglePrestacion({{ $prestacion->id }})"
                                    @checked(in_array($prestacion->id, $seleccionadas))
                                    class="h-4 w-4 rounded border-gray-300 text-primary-600
                                           focus:ring-primary-500 dark:border-gray-600"
                                >
                            </div>

                            {{-- Datos de la prestación --}}
                            <div class="min-w-0 flex-1">
                                <div class="flex items-start gap-2">
                                    <button
                                        wire:click="togglePrestacion({{ $prestacion->id }})"
                                        class="text-left text-sm text-gray-900 hover:text-primary-600
                                               dark:text-white dark:hover:text-primary-400"
                                    >
                                        {{ $prestacion->nombre }}
                                    </button>
                                    {{-- Botón de detalle --}}
                                    <button
                                        wire:click="verDetalle({{ $prestacion->id }})"
                                        class="flex-shrink-0 text-gray-300 hover:text-gray-500
                                               dark:text-gray-600 dark:hover:text-gray-400"
                                        title="Ver detalle"
                                    >
                                        <x-heroicon-o-information-circle class="h-4 w-4" />
                                    </button>
                                </div>
                                <p class="mt-0.5 font-mono text-xs text-gray-400">
                                    {{ $prestacion->codigo }}
                                </p>
                            </div>

                        </div>
                    @endforeach

                </div>
            @empty
                <div class="px-4 py-8 text-center text-sm text-gray-400 dark:text-gray-500">
                    No hay prestaciones que coincidan con los filtros aplicados.
                </div>
            @endforelse
        </div>

        {{-- Panel de seleccionadas --}}
        <div class="flex flex-col gap-2">
            <div class="flex items-center justify-between">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">
                    Seleccionadas
                </p>
                <span class="rounded-full bg-primary-50 px-2 py-0.5 text-xs font-medium
                             text-primary-700 dark:bg-primary-950 dark:text-primary-300">
                    {{ count($seleccionadas) }}
                </span>
            </div>

            <div class="overflow-y-auto rounded-lg border border-gray-200 dark:border-gray-700"
                 style="max-height: 440px;">
                @if (empty($seleccionadas))
                    <p class="px-3 py-6 text-center text-xs text-gray-400 dark:text-gray-500">
                        Ninguna prestación seleccionada
                    </p>
                @else
                    @php
                        // Cargar los modelos de las prestaciones seleccionadas para mostrar su info
                        // Hacerlo en PHP inline para no añadir una propiedad computed extra
                        $modelosSeleccionadas = \Modules\Prestaciones\Models\Prestacion::whereIn('id', $seleccionadas)
                            ->orderBy('nombre')
                            ->get();
                    @endphp
                    @foreach ($modelosSeleccionadas as $pres)
                        <div class="flex items-start gap-2 border-b border-gray-50 px-3 py-2.5
                                    last:border-b-0 dark:border-gray-800">
                            <div class="min-w-0 flex-1">
                                <p class="text-xs text-gray-900 dark:text-white">
                                    {{ $pres->nombre }}
                                </p>
                                <p class="font-mono text-xs text-gray-400">
                                    {{ $pres->codigo }}
                                </p>
                            </div>
                            <button
                                wire:click="deseleccionar({{ $pres->id }})"
                                class="flex-shrink-0 text-gray-300 hover:text-danger-500
                                       dark:text-gray-600"
                                title="Quitar"
                            >
                                <x-heroicon-o-x-mark class="h-4 w-4" />
                            </button>
                        </div>
                    @endforeach
                @endif
            </div>

            {{-- Botón guardar --}}
            <button
                wire:click="guardar"
                wire:loading.attr="disabled"
                class="mt-2 w-full rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium
                       text-white hover:bg-primary-700 disabled:opacity-50
                       dark:bg-primary-500 dark:hover:bg-primary-600"
            >
                <span wire:loading.remove wire:target="guardar">Guardar selección</span>
                <span wire:loading wire:target="guardar">Guardando…</span>
            </button>

        </div>
    </div>

    {{-- Modal de detalle de prestación --}}
    @if ($prestacionDetalle !== null)
        @php $detalle = \Modules\Prestaciones\Models\Prestacion::find($prestacionDetalle) @endphp
        @if ($detalle)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
                <div class="w-full max-w-md rounded-xl border border-gray-200 bg-white p-6
                            shadow-xl dark:border-gray-700 dark:bg-gray-900">

                    <div class="mb-4 flex items-start justify-between gap-3">
                        <div>
                            <p class="font-mono text-xs text-gray-400">{{ $detalle->codigo }}</p>
                            <h3 class="mt-1 text-base font-medium text-gray-900 dark:text-white">
                                {{ $detalle->nombre }}
                            </h3>
                        </div>
                        <button
                            wire:click="cerrarDetalle"
                            class="flex-shrink-0 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                        >
                            <x-heroicon-o-x-mark class="h-5 w-5" />
                        </button>
                    </div>

                    <dl class="space-y-2 text-sm">
                        {{-- Ajustar los campos al modelo real de Prestacion --}}
                        @if ($detalle->descripcion)
                            <div class="flex gap-3">
                                <dt class="w-32 flex-shrink-0 text-gray-400">Descripción</dt>
                                <dd class="text-gray-700 dark:text-gray-300">{{ $detalle->descripcion }}</dd>
                            </div>
                        @endif
                        @if ($detalle->tipo_prestacion)
                            <div class="flex gap-3">
                                <dt class="w-32 flex-shrink-0 text-gray-400">Tipo</dt>
                                <dd class="text-gray-700 dark:text-gray-300">{{ $detalle->tipo_prestacion }}</dd>
                            </div>
                        @endif
                        @if ($detalle->nivel_garantia)
                            <div class="flex gap-3">
                                <dt class="w-32 flex-shrink-0 text-gray-400">Nivel de garantía</dt>
                                <dd class="text-gray-700 dark:text-gray-300">{{ $detalle->nivel_garantia }}</dd>
                            </div>
                        @endif
                    </dl>

                    <div class="mt-5 text-right">
                        <button
                            wire:click="cerrarDetalle"
                            class="rounded-lg border border-gray-200 px-4 py-2 text-sm text-gray-600
                                   hover:bg-gray-50 dark:border-gray-700 dark:text-gray-400
                                   dark:hover:bg-gray-800"
                        >
                            Cerrar
                        </button>
                    </div>

                </div>
            </div>
        @endif
    @endif

</div>
```

**Nota sobre el modal de detalle:** Se implementa con `position: fixed` dentro del
componente Livewire, no con `position: fixed` en el widget standalone. Dentro del
SlideOver de Filament, `fixed` funciona correctamente porque el SlideOver ya ocupa
el viewport completo. No usar la restricción del design system sobre `position: fixed`
que aplica solo a widgets SVG/HTML del visualizador de Claude.

---

## Paso 6 — Registrar el componente Livewire

Verificar que el componente se auto-descubre por convención de Livewire 3 (si el
proyecto usa Livewire 3, el auto-discovery está activo y no hay nada que registrar).
Si el proyecto usa Livewire 2, añadir el registro en el `AppServiceProvider` o en el
provider correspondiente:

```php
Livewire::component('centros.selector-prestaciones-centro', SelectorPrestacionesCentro::class);
```

Verificar qué versión de Livewire usa el proyecto con:
```bash
composer show livewire/livewire | grep versions
```

---

## Paso 7 — Verificación manual

Después de implementar, verificar:

1. El formulario de edición de centro ya no muestra la sección "Prestaciones".
2. La cabecera de la página de edición muestra el botón "Prestaciones del centro".
3. Al pulsar el botón se abre el SlideOver sin errores.
4. El SlideOver muestra el catálogo agrupado por objetivo general.
5. Los filtros de segmento de población muestran los segmentos del centro.
6. La búsqueda por texto filtra correctamente en tiempo real.
7. Al marcar una prestación aparece en el panel de seleccionadas.
8. Al pulsar el icono de información se abre la ficha de detalle.
9. Al pulsar "Guardar selección" se persiste en base de datos y aparece la notificación.
10. Al reabrir el SlideOver, las prestaciones guardadas aparecen ya seleccionadas.
11. El formulario de creación de centro (`CreateCentro`) no muestra ni el checkbox
    ni el botón de prestaciones — la selección de prestaciones solo aplica en edición.

---

## Paso 8 — CHANGELOG y SESSION

Actualizar `CHANGELOG.md`:
- Fecha
- Módulo: `Centro / Prestaciones`
- Cambios: eliminación de `CheckboxList` en `CentroResource`, nuevo `Action` en
  `EditCentro` con SlideOver, nuevo componente Livewire `SelectorPrestacionesCentro`
- Decisiones tomadas no explícitas en estas instrucciones: indicar si el filtro
  por segmento de población se implementó completamente o se dejó como TODO,
  y cómo se resolvió el nombre del campo de agrupación por objetivo general

Añadir a `BACKLOG.md` si el filtro por segmento de población quedó pendiente:
```
**Filtro por segmento en SelectorPrestacionesCentro** — [fecha]
`Módulo: Centro / Prestaciones`
Implementar el filtro por segmento de población en el selector de prestaciones
del centro. Requiere confirmar la relación Prestacion <-> SegmentoPoblacion
en el modelo. Actualmente el filtro de segmento está presente en la UI pero
no aplica ninguna restricción a la query.
```

Actualizar `SESSION.md`.
