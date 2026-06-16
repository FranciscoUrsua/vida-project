# Instrucciones CLI — Relaciones en UI de Intervención (CiudadanoPage)

## Contexto

El catálogo `tipos_relacion` y el modelo `TipoRelacion` ya existen.
`ciudadano_relaciones` ya existe con el campo `tipo_relacion` (string slug).

Esta tarea añade tres piezas a la columna izquierda de `ciudadano-page.blade.php`,
en este orden de arriba a abajo:

```
[Cabecera ciudadano — ya existe]
  nombre, badges HS+UO, contacto...

[NUEVO] Línea de representante (solo si existe)
  "Representante: Nombre Apellido"  →  abre modal con datos de contacto

[Widget UC — ya existe, ahora enriquecido]
  Miembros con tipo de relación visible
  Botón "Gestionar UC" — ya existe

[NUEVO] Botón "Ver todas las relaciones"
  Abre modal con lista completa de relaciones del ciudadano
```

Todos los cambios son de **solo lectura**. La creación y edición de relaciones
se hace desde `FichaCiudadanoPage` (módulo Ciudadanía), no desde aquí.

---

## Paso 1 — Nuevos computeds en `CiudadanoPage.php`

Añade los siguientes métodos computados al componente:

```php
/**
 * Representante legal/designado del ciudadano, si existe.
 * Busca por implicacion_funcional = 'representante', no por slug.
 */
#[Computed]
public function representante(): ?\Modules\Ciudadania\Models\Ciudadano
{
    $slugsRepresentante = \Modules\Ciudadania\Models\TipoRelacion::where(
        'implicacion_funcional',
        \Modules\Ciudadania\Enums\ImplicacionFuncional::Representante->value
    )->pluck('slug')->toArray();

    if (empty($slugsRepresentante)) {
        return null;
    }

    $relacion = \Modules\Ciudadania\Models\CiudadanoRelacion::where('ciudadano_id', $this->ciudadano->id)
        ->whereIn('tipo_relacion', $slugsRepresentante)
        ->whereNull('fecha_fin')
        ->with('ciudadanoRelacionado')
        ->first();

    return $relacion?->ciudadanoRelacionado;
}

/**
 * Todas las relaciones activas del ciudadano con sus tipos,
 * agrupadas por tipo de relación para el modal completo.
 * Excluye las relaciones cuyo tipo no existe en el catálogo activo.
 */
#[Computed]
public function relacionesAgrupadas(): \Illuminate\Support\Collection
{
    $slugsActivos = \Modules\Ciudadania\Models\TipoRelacion::activos()
        ->pluck('etiqueta', 'slug');

    return \Modules\Ciudadania\Models\CiudadanoRelacion::where('ciudadano_id', $this->ciudadano->id)
        ->whereNull('fecha_fin')
        ->with('ciudadanoRelacionado')
        ->get()
        ->filter(fn ($r) => $slugsActivos->has($r->tipo_relacion))
        ->groupBy('tipo_relacion')
        ->map(fn ($grupo, $slug) => [
            'etiqueta'  => $slugsActivos[$slug],
            'miembros'  => $grupo->map(fn ($r) => $r->ciudadanoRelacionado),
        ]);
}

/**
 * Tipo de relación de cada miembro de la UC respecto al ciudadano titular,
 * indexado por ciudadano_id. Usado para enriquecer el widget UC.
 */
#[Computed]
public function relacionesMiembrosUc(): \Illuminate\Support\Collection
{
    if (! $this->ucVigente) {
        return collect();
    }

    $idsMiembros = $this->ucMiembrosActivos->pluck('ciudadano_id')->toArray();

    if (empty($idsMiembros)) {
        return collect();
    }

    $slugsEtiquetas = \Modules\Ciudadania\Models\TipoRelacion::activos()
        ->pluck('etiqueta', 'slug');

    return \Modules\Ciudadania\Models\CiudadanoRelacion::where('ciudadano_id', $this->ciudadano->id)
        ->whereIn('ciudadano_relacionado_id', $idsMiembros)
        ->whereNull('fecha_fin')
        ->get()
        ->mapWithKeys(fn ($r) => [
            $r->ciudadano_relacionado_id => $slugsEtiquetas[$r->tipo_relacion] ?? null,
        ]);
}
```

Añade también las propiedades para el modal de relaciones completas:

```php
public bool $modalRelacionesAbierto = false;
public bool $modalRepresentanteAbierto = false;
```

Y los métodos de apertura/cierre:

```php
public function abrirModalRelaciones(): void
{
    $this->modalRelacionesAbierto = true;
}

public function cerrarModalRelaciones(): void
{
    $this->modalRelacionesAbierto = false;
}

public function abrirModalRepresentante(): void
{
    $this->modalRepresentanteAbierto = true;
}

public function cerrarModalRepresentante(): void
{
    $this->modalRepresentanteAbierto = false;
}
```

---

## Paso 2 — Cambios en `ciudadano-page.blade.php`

### 2a — Línea de representante

Localiza el bloque de la cabecera del ciudadano en la columna izquierda.
Justo **después** del bloque de contacto (teléfono, domicilio) y **antes**
del widget UC, añade:

```blade
{{-- Representante (solo si existe) --}}
@if($this->representante)
<div class="hs-representante">
    <span class="hs-representante__label">Representante</span>
    <button
        wire:click="abrirModalRepresentante"
        class="hs-representante__nombre"
        title="Ver datos de contacto del representante"
    >
        {{ $this->representante->nombre }}
        {{ $this->representante->apellido1 }}
        {{ $this->representante->apellido2 }}
        <i data-lucide="chevron-right" style="width:12px;height:12px;"></i>
    </button>
</div>
@endif
```

### 2b — Tipo de relación en cada miembro del widget UC

Dentro del widget UC colapsable, localiza el bucle que renderiza cada miembro
activo (el `@foreach` de `$this->ucMiembrosActivos`). En cada fila de miembro,
añade el tipo de relación tras el nombre:

```blade
{{-- Dentro del foreach de miembros de la UC --}}
@php
    $tipoRelacion = $this->relacionesMiembrosUc->get($miembro->ciudadano_id);
@endphp
<span class="uc-widget-miembro__nombre">
    {{ $miembro->ciudadano->nombre }} {{ $miembro->ciudadano->apellido1 }}
</span>
@if($tipoRelacion)
<span class="uc-widget-miembro__relacion">{{ $tipoRelacion }}</span>
@endif
```

### 2c — Botón "Ver todas las relaciones"

Al **pie del widget UC** (después del botón "Gestionar UC" ya existente),
añade:

```blade
{{-- Botón para ver todas las relaciones del ciudadano --}}
<button
    wire:click="abrirModalRelaciones"
    class="uc-widget__ver-relaciones"
    title="Ver todas las personas relacionadas"
>
    <i data-lucide="network" style="width:13px;height:13px;"></i>
    Ver todas las relaciones
</button>
```

---

## Paso 3 — Modal de representante

Añade justo antes del cierre del componente raíz Livewire (al mismo nivel
que el modal de gestión de UC ya existente):

```blade
{{-- ================================================================== --}}
{{-- MODAL: DATOS DE CONTACTO DEL REPRESENTANTE                         --}}
{{-- ================================================================== --}}
@if($this->modalRepresentanteAbierto && $this->representante)
<div
    class="uc-modal-backdrop"
    x-data
    x-on:keydown.escape.window="$wire.cerrarModalRepresentante()"
    role="dialog"
    aria-modal="true"
    aria-labelledby="modal-representante-titulo"
>
    <div class="uc-modal uc-modal--sm">

        <div class="uc-modal__header">
            <h2 id="modal-representante-titulo" class="uc-modal__titulo">
                Representante
            </h2>
            <button wire:click="cerrarModalRepresentante"
                    class="uc-modal__cerrar" aria-label="Cerrar">
                <i data-lucide="x" style="width:18px;height:18px;"></i>
            </button>
        </div>

        <div class="uc-modal__cuerpo">
            <div class="rel-modal__persona">
                <span class="rel-modal__nombre">
                    {{ $this->representante->nombre }}
                    {{ $this->representante->apellido1 }}
                    {{ $this->representante->apellido2 }}
                </span>

                @if($this->representante->telefono)
                <a href="tel:{{ $this->representante->telefono }}"
                   class="rel-modal__dato">
                    <i data-lucide="phone" style="width:13px;height:13px;"></i>
                    {{ $this->representante->telefono }}
                </a>
                @endif

                @if($this->representante->email)
                <a href="mailto:{{ $this->representante->email }}"
                   class="rel-modal__dato">
                    <i data-lucide="mail" style="width:13px;height:13px;"></i>
                    {{ $this->representante->email }}
                </a>
                @endif

                @if(! $this->representante->telefono && ! $this->representante->email)
                <span class="rel-modal__sin-contacto">
                    Sin datos de contacto registrados.
                </span>
                @endif
            </div>

            <div class="rel-modal__pie-accion">
                <a
                    href="{{ route('ciudadania.ciudadano.ficha', $this->representante->id) }}"
                    class="rel-modal__link-ficha"
                    wire:navigate
                >
                    <i data-lucide="external-link" style="width:12px;height:12px;"></i>
                    Ver ficha completa
                </a>
            </div>
        </div>

        <div class="uc-modal__pie">
            <button wire:click="cerrarModalRepresentante" class="uc-btn uc-btn--ghost">
                Cerrar
            </button>
        </div>

    </div>
</div>
@endif
```

---

## Paso 4 — Modal de todas las relaciones

Añade inmediatamente después del modal de representante:

```blade
{{-- ================================================================== --}}
{{-- MODAL: TODAS LAS RELACIONES DEL CIUDADANO                          --}}
{{-- ================================================================== --}}
@if($this->modalRelacionesAbierto)
<div
    class="uc-modal-backdrop"
    x-data
    x-on:keydown.escape.window="$wire.cerrarModalRelaciones()"
    role="dialog"
    aria-modal="true"
    aria-labelledby="modal-relaciones-titulo"
>
    <div class="uc-modal">

        <div class="uc-modal__header">
            <h2 id="modal-relaciones-titulo" class="uc-modal__titulo">
                Personas relacionadas
            </h2>
            <button wire:click="cerrarModalRelaciones"
                    class="uc-modal__cerrar" aria-label="Cerrar">
                <i data-lucide="x" style="width:18px;height:18px;"></i>
            </button>
        </div>

        <div class="uc-modal__cuerpo">

            @forelse($this->relacionesAgrupadas as $slug => $grupo)
            <div class="uc-modal__seccion" wire:key="grupo-{{ $slug }}">
                <h3 class="uc-modal__seccion-titulo">
                    {{ $grupo['etiqueta'] }}
                    <span class="uc-modal__badge">
                        {{ $grupo['miembros']->count() }}
                    </span>
                </h3>

                <ul class="uc-modal__lista">
                    @foreach($grupo['miembros'] as $persona)
                    <li class="uc-modal__miembro" wire:key="rel-{{ $slug }}-{{ $persona->id }}">
                        <div class="uc-modal__miembro-info">
                            <span class="uc-modal__miembro-nombre">
                                {{ $persona->nombre }}
                                {{ $persona->apellido1 }}
                                {{ $persona->apellido2 }}
                            </span>
                            @if($persona->telefono)
                            <span class="uc-modal__miembro-meta">
                                {{ $persona->telefono }}
                            </span>
                            @endif
                        </div>
                        <div class="uc-modal__miembro-acciones">
                            <a
                                href="{{ route('ciudadania.ciudadano.ficha', $persona->id) }}"
                                class="uc-btn uc-btn--ghost-sm"
                                wire:navigate
                                title="Ver ficha"
                            >
                                <i data-lucide="external-link" style="width:12px;height:12px;"></i>
                            </a>
                        </div>
                    </li>
                    @endforeach
                </ul>
            </div>
            @empty
            <div class="uc-modal__vacio">
                <p>No hay personas relacionadas registradas.</p>
                <a
                    href="{{ route('ciudadania.ciudadano.ficha', $this->ciudadano->id) }}"
                    class="uc-modal__alta-link"
                    wire:navigate
                >
                    Gestionar relaciones en la ficha del ciudadano
                </a>
            </div>
            @endforelse

        </div>

        <div class="uc-modal__pie">
            <a
                href="{{ route('ciudadania.ciudadano.ficha', $this->ciudadano->id) }}"
                class="rel-modal__link-ficha"
                wire:navigate
            >
                <i data-lucide="external-link" style="width:12px;height:12px;"></i>
                Gestionar relaciones en la ficha
            </a>
            <button wire:click="cerrarModalRelaciones" class="uc-btn uc-btn--ghost">
                Cerrar
            </button>
        </div>

    </div>
</div>
@endif
```

---

## Paso 5 — CSS en `app-operativo.css`

Añade al final del fichero, tras las clases del modal UC ya existentes:

```css
/* ============================================================
   LÍNEA DE REPRESENTANTE
   ============================================================ */

.hs-representante {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 6px 0;
    border-top: 1px solid var(--color-border);
    border-bottom: 1px solid var(--color-border);
    margin: 8px 0;
}

.hs-representante__label {
    font-size: 11px;
    font-weight: 600;
    color: var(--color-text-secondary);
    text-transform: uppercase;
    letter-spacing: .04em;
    white-space: nowrap;
}

.hs-representante__nombre {
    background: none;
    border: none;
    padding: 0;
    cursor: pointer;
    font-size: 13px;
    font-weight: 500;
    color: var(--color-primary, #2563eb);
    display: inline-flex;
    align-items: center;
    gap: 3px;
    text-align: left;
}

.hs-representante__nombre:hover {
    text-decoration: underline;
}

/* ============================================================
   WIDGET UC — tipo de relación por miembro
   ============================================================ */

.uc-widget-miembro__nombre {
    font-size: 13px;
    color: var(--color-text-primary);
}

.uc-widget-miembro__relacion {
    font-size: 11px;
    color: var(--color-text-secondary);
    background: var(--color-neutral-100, #f3f4f6);
    border-radius: 3px;
    padding: 1px 5px;
    margin-left: 4px;
    white-space: nowrap;
}

/* Botón "Ver todas las relaciones" al pie del widget UC */
.uc-widget__ver-relaciones {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 11px;
    padding: 3px 8px;
    border: none;
    background: none;
    color: var(--color-text-secondary);
    cursor: pointer;
    margin-top: 4px;
}

.uc-widget__ver-relaciones:hover {
    color: var(--color-text-primary);
    text-decoration: underline;
}

/* ============================================================
   MODALES DE RELACIONES — elementos adicionales
   ============================================================ */

.uc-modal--sm {
    width: 360px;
}

.rel-modal__persona {
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding: 4px 0 12px;
}

.rel-modal__nombre {
    font-size: 15px;
    font-weight: 600;
    color: var(--color-text-primary);
}

.rel-modal__dato {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    color: var(--color-primary, #2563eb);
    text-decoration: none;
}

.rel-modal__dato:hover {
    text-decoration: underline;
}

.rel-modal__sin-contacto {
    font-size: 13px;
    color: var(--color-text-secondary);
    font-style: italic;
}

.rel-modal__pie-accion {
    padding-top: 8px;
    border-top: 1px solid var(--color-border);
}

.rel-modal__link-ficha {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 12px;
    color: var(--color-text-secondary);
    text-decoration: none;
}

.rel-modal__link-ficha:hover {
    color: var(--color-text-primary);
    text-decoration: underline;
}

/* Pie del modal de relaciones — flex con espacio entre link y botón */
.uc-modal--relaciones .uc-modal__pie {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
```

---

## Paso 6 — Asegurar la relación `ciudadanoRelacionado` en `CiudadanoRelacion`

Verifica que el modelo `CiudadanoRelacion` tiene la relación `ciudadanoRelacionado`.
Si no existe, añádela:

```php
// En Modules/Ciudadania/app/Models/CiudadanoRelacion.php

public function ciudadanoRelacionado(): \Illuminate\Database\Eloquent\Relations\BelongsTo
{
    return $this->belongsTo(Ciudadano::class, 'ciudadano_relacionado_id');
}
```

---

## Paso 7 — Tests funcionales

Crea `Modules/Intervencion/tests/Feature/Livewire/RelacionesUiTest.php`:

```php
<?php

namespace Modules\Intervencion\Tests\Feature\Livewire;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Ciudadania\Models\Ciudadano;
use Modules\Ciudadania\Models\CiudadanoRelacion;
use Modules\Ciudadania\Models\TipoRelacion;
use Modules\Intervencion\Http\Livewire\CiudadanoPage;
use App\Models\User;
use Tests\TestCase;

/**
 * Tests funcionales de la UI de relaciones en CiudadanoPage.
 * Nomenclatura: TF-LW-REL-XX
 */
class RelacionesUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Modules\Ciudadania\Database\Seeders\TipoRelacionSeeder::class);
    }

    private function montarConCiudadano(?callable $setup = null): array
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $ciudadano = Ciudadano::factory()->create();
        $historia  = \App\Models\HistoriaSocial::factory()->create([
            'ciudadano_id' => $ciudadano->id,
        ]);

        if ($setup) {
            $setup($ciudadano);
        }

        $componente = Livewire::test(CiudadanoPage::class, [
            'historia' => $historia->id,
        ]);

        return [$componente, $ciudadano];
    }

    // TF-LW-REL-01: Sin representante, la línea de representante no aparece en el blade
    public function test_sin_representante_no_muestra_linea(): void
    {
        [$componente] = $this->montarConCiudadano();

        $componente->assertDontSee('Representante');
    }

    // TF-LW-REL-02: Con representante, la línea aparece con su nombre
    public function test_con_representante_muestra_nombre(): void
    {
        [$componente, $ciudadano] = $this->montarConCiudadano(function ($c) {
            $representante = Ciudadano::factory()->create([
                'nombre'    => 'Ana',
                'apellido1' => 'López',
            ]);
            CiudadanoRelacion::create([
                'ciudadano_id'            => $c->id,
                'ciudadano_relacionado_id' => $representante->id,
                'tipo_relacion'           => 'representante',
                'fecha_inicio'            => now()->toDateString(),
            ]);
        });

        $componente->assertSee('Representante');
        $componente->assertSee('López');
    }

    // TF-LW-REL-03: abrirModalRepresentante abre el modal
    public function test_abrir_modal_representante(): void
    {
        [$componente, $ciudadano] = $this->montarConCiudadano(function ($c) {
            $rep = Ciudadano::factory()->create();
            CiudadanoRelacion::create([
                'ciudadano_id'            => $c->id,
                'ciudadano_relacionado_id' => $rep->id,
                'tipo_relacion'           => 'representante',
                'fecha_inicio'            => now()->toDateString(),
            ]);
        });

        $componente
            ->call('abrirModalRepresentante')
            ->assertSet('modalRepresentanteAbierto', true);
    }

    // TF-LW-REL-04: Modal representante muestra teléfono si existe
    public function test_modal_representante_muestra_telefono(): void
    {
        [$componente, $ciudadano] = $this->montarConCiudadano(function ($c) {
            $rep = Ciudadano::factory()->create(['telefono' => '612345678']);
            CiudadanoRelacion::create([
                'ciudadano_id'            => $c->id,
                'ciudadano_relacionado_id' => $rep->id,
                'tipo_relacion'           => 'representante',
                'fecha_inicio'            => now()->toDateString(),
            ]);
        });

        $componente
            ->call('abrirModalRepresentante')
            ->assertSee('612345678');
    }

    // TF-LW-REL-05: cerrarModalRepresentante cierra el modal
    public function test_cerrar_modal_representante(): void
    {
        [$componente] = $this->montarConCiudadano();

        $componente
            ->call('abrirModalRepresentante')
            ->call('cerrarModalRepresentante')
            ->assertSet('modalRepresentanteAbierto', false);
    }

    // TF-LW-REL-06: abrirModalRelaciones abre el modal
    public function test_abrir_modal_relaciones(): void
    {
        [$componente] = $this->montarConCiudadano();

        $componente
            ->call('abrirModalRelaciones')
            ->assertSet('modalRelacionesAbierto', true);
    }

    // TF-LW-REL-07: cerrarModalRelaciones cierra el modal
    public function test_cerrar_modal_relaciones(): void
    {
        [$componente] = $this->montarConCiudadano();

        $componente
            ->call('abrirModalRelaciones')
            ->call('cerrarModalRelaciones')
            ->assertSet('modalRelacionesAbierto', false);
    }

    // TF-LW-REL-08: relacionesAgrupadas agrupa correctamente por tipo
    public function test_relaciones_agrupadas_por_tipo(): void
    {
        [$componente, $ciudadano] = $this->montarConCiudadano(function ($c) {
            $hijo1 = Ciudadano::factory()->create();
            $hijo2 = Ciudadano::factory()->create();
            $conyuge = Ciudadano::factory()->create();

            foreach ([$hijo1, $hijo2] as $hijo) {
                CiudadanoRelacion::create([
                    'ciudadano_id'            => $c->id,
                    'ciudadano_relacionado_id' => $hijo->id,
                    'tipo_relacion'           => 'hijo',
                    'fecha_inicio'            => now()->toDateString(),
                ]);
            }

            CiudadanoRelacion::create([
                'ciudadano_id'            => $c->id,
                'ciudadano_relacionado_id' => $conyuge->id,
                'tipo_relacion'           => 'conyuge',
                'fecha_inicio'            => now()->toDateString(),
            ]);
        });

        $agrupadas = $componente->get('relacionesAgrupadas');

        $this->assertArrayHasKey('hijo', $agrupadas->toArray());
        $this->assertArrayHasKey('conyuge', $agrupadas->toArray());
        $this->assertCount(2, $agrupadas['hijo']['miembros']);
        $this->assertCount(1, $agrupadas['conyuge']['miembros']);
    }

    // TF-LW-REL-09: relacionesAgrupadas excluye relaciones con fecha_fin
    public function test_relaciones_agrupadas_excluye_cerradas(): void
    {
        [$componente, $ciudadano] = $this->montarConCiudadano(function ($c) {
            $exConyuge = Ciudadano::factory()->create();
            CiudadanoRelacion::create([
                'ciudadano_id'            => $c->id,
                'ciudadano_relacionado_id' => $exConyuge->id,
                'tipo_relacion'           => 'conyuge',
                'fecha_inicio'            => '2020-01-01',
                'fecha_fin'               => '2023-06-01', // cerrada
            ]);
        });

        $agrupadas = $componente->get('relacionesAgrupadas');

        $this->assertArrayNotHasKey('conyuge', $agrupadas->toArray());
    }

    // TF-LW-REL-10: relacionesMiembrosUc devuelve el tipo correcto para cada miembro
    public function test_relaciones_miembros_uc_tipo_correcto(): void
    {
        [$componente, $ciudadano] = $this->montarConCiudadano(function ($c) {
            $hijo = Ciudadano::factory()->create();

            // Crear UC y añadir hijo como miembro
            $uc = \Modules\Ciudadania\Models\UnidadConvivencia::factory()->create();
            $uc->agregarMiembro($c->id);
            $uc->agregarMiembro($hijo->id);

            // Registrar relación padre-hijo
            CiudadanoRelacion::create([
                'ciudadano_id'            => $c->id,
                'ciudadano_relacionado_id' => $hijo->id,
                'tipo_relacion'           => 'hijo',
                'fecha_inicio'            => now()->toDateString(),
            ]);
        });

        $relaciones = $componente->get('relacionesMiembrosUc');

        // Debe haber al menos una entrada con etiqueta "Hijo/a"
        $this->assertTrue(
            $relaciones->contains('Hijo/a'),
            'Se esperaba encontrar la etiqueta "Hijo/a" en relacionesMiembrosUc.'
        );
    }

    // TF-LW-REL-11: representante con fecha_fin no aparece
    public function test_representante_cerrado_no_aparece(): void
    {
        [$componente, $ciudadano] = $this->montarConCiudadano(function ($c) {
            $exRep = Ciudadano::factory()->create([
                'nombre'    => 'ExRepresentante',
                'apellido1' => 'Anterior',
            ]);
            CiudadanoRelacion::create([
                'ciudadano_id'            => $c->id,
                'ciudadano_relacionado_id' => $exRep->id,
                'tipo_relacion'           => 'representante',
                'fecha_inicio'            => '2020-01-01',
                'fecha_fin'               => '2023-01-01', // cerrada
            ]);
        });

        $this->assertNull($componente->get('representante'));
        $componente->assertDontSee('ExRepresentante');
    }

    // TF-LW-REL-12: Modal relaciones vacío muestra enlace a ficha del ciudadano
    public function test_modal_relaciones_vacio_muestra_enlace_ficha(): void
    {
        [$componente] = $this->montarConCiudadano();

        $componente
            ->call('abrirModalRelaciones')
            ->assertSee('Gestionar relaciones en la ficha del ciudadano');
    }
}
```

---

## Paso 8 — Actualizar BACKLOG y SESSION

Elimina de `BACKLOG.md` la entrada:

```
**Relaciones en UI de intervención** — 2026-06-16
```

Añade en `BACKLOG.md`:

```markdown
**Gestión de relaciones en FichaCiudadanoPage** — 2026-06-16
`Módulo: Ciudadanía`
Las relaciones se muestran en CiudadanoPage (solo lectura). Pendiente implementar
en FichaCiudadanoPage la gestión completa: crear relación (buscador de ciudadano
+ selección de TipoRelacion del catálogo), cerrar relación (fecha_fin) y ver
historial. El trait TieneRelacionesReciprocas ya gestiona la creación del recíproco.

**Genograma** — 2026-06-16
`Módulo: Ciudadanía / Intervención`
Ver decisiones pendientes en docs/modulo-ciudadania.md sección 8.
Bloqueado hasta definir: tipo_dinamica en ciudadano_relaciones, fecha_fallecimiento
en ciudadanos, y decisión sobre nodos ligeros para personas no registradas.
```

Actualiza `SESSION.md`:
- Tarea completada: UI de relaciones en CiudadanoPage (línea representante,
  tipos en widget UC, modal de todas las relaciones)
- Siguiente paso recomendado: gestión de relaciones en FichaCiudadanoPage
  (creación y cierre de relaciones desde la ficha del ciudadano)

---

## Checklist de verificación

- [ ] `php artisan test --filter=RelacionesUiTest` — 12 tests en verde
- [ ] Suite completa Intervención sin regresiones
- [ ] La línea "Representante" aparece solo cuando hay un representante activo
- [ ] Al pulsar sobre el nombre del representante se abre el modal con sus datos
- [ ] Cada miembro del widget UC muestra su tipo de relación si está registrado
- [ ] El botón "Ver todas las relaciones" abre el modal agrupado por tipo
- [ ] Los modales se cierran con Escape (Alpine.js) y con el botón Cerrar
- [ ] Los iconos Lucide se renderizan tras re-renders de Livewire
- [ ] BACKLOG y SESSION.md actualizados
- [ ] Commit: `feat(intervencion): relaciones en CiudadanoPage — representante, tipos en UC y modal completo`
