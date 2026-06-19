# Instrucciones CLI — `PlanPage` Livewire

## Contexto

El modelo completo del plan ya existe (`plan-intervencion-completo.md`).
La firma está actualizada a booleanos (`modulo-intervencion-firma-update.md`).
El documento de diseño es `docs/front/ui-intervencion-plan.md`.

Esta tarea implementa el componente Livewire `PlanPage` y su vista Blade.

---

## Paso 1 — Ruta

En `Modules/Intervencion/routes/web.php` añade:

```php
use Modules\Intervencion\Http\Livewire\PlanPage;

Route::middleware(['auth', 'primer.acceso'])->group(function () {
    Route::get('/intervencion/plan/crear', PlanPage::class)
        ->name('plan.crear');
    Route::get('/intervencion/plan/{plan}', PlanPage::class)
        ->name('plan.show');
});
```

---

## Paso 2 — Componente `PlanPage`

Crea `Modules/Intervencion/app/Http/Livewire/PlanPage.php`:

```php
<?php

namespace Modules\Intervencion\Http\Livewire;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Modules\Intervencion\Models\PlanDeIntervencion;
use Modules\Intervencion\Models\FirmaPlan;
use Modules\Intervencion\Models\PlanCambio;
use Modules\Intervencion\Services\PlanPdfService;
use Modules\Ciudadania\Models\TipoRelacion;
use Modules\Ciudadania\Enums\ImplicacionFuncional;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class PlanPage extends Component
{
    use AuthorizesRequests;

    // --- Parámetros de ruta ---
    public ?int $planId = null;        // null = crear nuevo
    public ?int $historiaId = null;    // para crear
    public ?int $ucId = null;          // para crear desde UC

    // --- Plan cargado ---
    public ?PlanDeIntervencion $plan = null;

    // --- Estado del drawer ---
    public bool $drawerAbierto = false;
    public array $fichasSeleccionadas = [];   // ids de fichas marcadas en el drawer
    public string $drawerFiltroTipo = 'todas';
    public string $drawerFiltroFecha = 'todas';

    // --- Modal de motivo ---
    public bool $modalMotivoAbierto = false;
    public string $motivoTexto = '';
    public string $motivoAccionPendiente = '';  // identificador de la acción en cola
    public array $motivoAccionParams = [];       // parámetros de la acción en cola

    // --- Edición inline de diagnóstico ---
    public string $diagnosticoTexto = '';

    // --- Edición de seguimiento ---
    public string $periodicidadSeguimiento = 'trimestral';
    public string $observacionesSeguimiento = '';

    // --- Firmas ---
    public bool $profesionalFirmado = false;
    public bool $ciudadanoFirmado = false;
    public ?string $fechaFirmaPresencial = null;

    // --- Feedback ---
    public string $mensajeExito = '';

    public function mount(?PlanDeIntervencion $plan = null, ?int $historia = null, ?int $uc = null): void
    {
        if ($plan && $plan->exists) {
            $this->authorize('view', $plan);
            $this->plan = $plan;
            $this->planId = $plan->id;
            $this->diagnosticoTexto = $plan->diagnostico_social ?? '';
            $this->periodicidadSeguimiento = $plan->periodicidad_seguimiento ?? 'trimestral';

            // Cargar estado de firmas
            $firma = FirmaPlan::where('plan_id', $plan->id)
                ->where('version', $plan->version)
                ->first();
            if ($firma) {
                $this->profesionalFirmado = $firma->profesional_firmado;
                $this->ciudadanoFirmado   = $firma->ciudadano_firmado;
                $this->fechaFirmaPresencial = $firma->fecha_firma?->format('Y-m-d');
                $this->observacionesSeguimiento = $firma->observaciones_seguimiento ?? '';
            }

            // Fichas ya incluidas en el diagnóstico
            $this->fichasSeleccionadas = $plan->fichasDiagnostico()
                ->pluck('ficha_id')
                ->toArray();
        } else {
            // Modo creación
            $this->historiaId = $historia;
            $this->ucId = $uc;
        }
    }

    // =========================================================
    // COMPUTEDS
    // =========================================================

    #[Computed]
    public function ciudadano(): ?\Modules\Ciudadania\Models\Ciudadano
    {
        return $this->plan?->historia?->ciudadano;
    }

    #[Computed]
    public function ucVigente(): ?\Modules\Ciudadania\Models\UnidadConvivencia
    {
        return $this->plan?->unidadConvivencia
            ?? $this->ciudadano?->unidadesConvivenciaActivas()->first();
    }

    #[Computed]
    public function miembrosUc(): \Illuminate\Support\Collection
    {
        if (! $this->ucVigente) return collect();

        $slugsEtiquetas = TipoRelacion::activos()->pluck('etiqueta', 'slug');

        return $this->ucVigente->miembrosActivos()
            ->with('ciudadano')
            ->get()
            ->map(function ($m) use ($slugsEtiquetas) {
                $relacion = \Modules\Ciudadania\Models\CiudadanoRelacion::where(
                    'ciudadano_id', $this->ciudadano?->id
                )->where('ciudadano_relacionado_id', $m->ciudadano_id)
                 ->whereNull('fecha_fin')
                 ->value('tipo_relacion');

                return [
                    'ciudadano'   => $m->ciudadano,
                    'relacion'    => $relacion ? ($slugsEtiquetas[$relacion] ?? null) : null,
                    'verificado'  => $m->verificado,
                ];
            });
    }

    #[Computed]
    public function fichasDiagnostico(): \Illuminate\Support\Collection
    {
        if (! $this->plan) return collect();
        return $this->plan->fichasDiagnostico()->with('ficha.tipoFicha')->get();
    }

    #[Computed]
    public function objetivosGenerales(): \Illuminate\Support\Collection
    {
        if (! $this->plan) return collect();
        return $this->plan->objetivosGenerales()
            ->with('objetivosEspecificos')
            ->get();
    }

    #[Computed]
    public function actuacionesAyuntamiento(): \Illuminate\Support\Collection
    {
        if (! $this->plan) return collect();
        return $this->plan->actuacionesAyuntamiento()
            ->with(['prestacion', 'responsable'])
            ->get();
    }

    #[Computed]
    public function actuacionesCiudadano(): \Illuminate\Support\Collection
    {
        if (! $this->plan) return collect();
        return $this->plan->actuacionesCiudadano()
            ->with('prestacion')
            ->get();
    }

    #[Computed]
    public function participantes(): \Illuminate\Support\Collection
    {
        if (! $this->plan) return collect();
        return $this->plan->participantes()
            ->with(['profesional', 'servicio'])
            ->get();
    }

    #[Computed]
    public function puedeActivarse(): bool
    {
        return $this->profesionalFirmado && $this->ciudadanoFirmado
            && $this->plan?->estado === 'borrador';
    }

    #[Computed]
    public function planFirmado(): bool
    {
        return $this->plan?->estado === 'activo';
    }

    #[Computed]
    public function valoracionesTimeline(): \Illuminate\Support\Collection
    {
        if (! $this->plan) return collect();

        $query = \Modules\Intervencion\Models\Valoracion::where(
            'historia_id', $this->plan->historia_id
        )->with(['fichas.tipoFicha']);

        if ($this->drawerFiltroFecha === 'mes') {
            $query->where('created_at', '>=', now()->subMonth());
        } elseif ($this->drawerFiltroFecha === 'anio') {
            $query->where('created_at', '>=', now()->subYear());
        }

        return $query->orderByDesc('created_at')->get();
    }

    #[Computed]
    public function planNombreCorto(): string
    {
        // Reutiliza el computed de CiudadanoPage si está disponible,
        // o lee directamente de la UO del profesional autenticado
        return auth()->user()?->unidadOrganizativa?->plan_nombre_corto ?? 'Plan';
    }

    // =========================================================
    // ACCIONES — DRAWER
    // =========================================================

    public function abrirDrawer(): void
    {
        $this->drawerAbierto = true;
    }

    public function cerrarDrawer(): void
    {
        $this->drawerAbierto = false;
    }

    public function aplicarSeleccionFichas(array $fichasNuevas): void
    {
        if (! $this->plan) return;

        $cambios = array_diff($fichasNuevas, $this->fichasSeleccionadas)
                 + array_diff($this->fichasSeleccionadas, $fichasNuevas);

        if (empty($cambios)) {
            $this->cerrarDrawer();
            return;
        }

        if ($this->planFirmado) {
            $this->encolarAccion('aplicarFichas', ['fichas' => $fichasNuevas]);
            $this->cerrarDrawer();
            return;
        }

        $this->_aplicarFichas($fichasNuevas);
        $this->cerrarDrawer();
        unset($this->fichasDiagnostico);
    }

    private function _aplicarFichas(array $fichas): void
    {
        // Eliminar las que ya no están seleccionadas
        $eliminar = array_diff($this->fichasSeleccionadas, $fichas);
        if ($eliminar) {
            $this->plan->fichasDiagnostico()
                ->whereIn('ficha_id', $eliminar)
                ->delete();
        }

        // Añadir las nuevas
        $anadir = array_diff($fichas, $this->fichasSeleccionadas);
        foreach ($anadir as $fichaId) {
            $this->plan->fichasDiagnostico()->firstOrCreate(['ficha_id' => $fichaId]);
        }

        $this->fichasSeleccionadas = $fichas;
        unset($this->fichasDiagnostico);
    }

    public function eliminarFichaDiagnostico(int $fichaId): void
    {
        if (! $this->plan) return;

        if ($this->planFirmado) {
            $this->encolarAccion('eliminarFicha', ['ficha_id' => $fichaId]);
            return;
        }

        $this->plan->fichasDiagnostico()->where('ficha_id', $fichaId)->delete();
        $this->fichasSeleccionadas = array_values(
            array_filter($this->fichasSeleccionadas, fn ($id) => $id !== $fichaId)
        );
        unset($this->fichasDiagnostico);
    }

    // =========================================================
    // ACCIONES — DIAGNÓSTICO TEXTO
    // =========================================================

    public function guardarDiagnostico(): void
    {
        if (! $this->plan) return;

        if ($this->planFirmado && $this->diagnosticoTexto !== $this->plan->diagnostico_social) {
            $this->encolarAccion('guardarDiagnostico', []);
            return;
        }

        $this->plan->update(['diagnostico_social' => $this->diagnosticoTexto]);
        $this->mensajeExito = 'Diagnóstico guardado.';
        unset($this->plan);
    }

    // =========================================================
    // ACCIONES — SEGUIMIENTO Y FIRMAS
    // =========================================================

    public function guardarSeguimiento(): void
    {
        if (! $this->plan) return;

        if ($this->planFirmado) {
            $this->encolarAccion('guardarSeguimiento', []);
            return;
        }

        $this->plan->update([
            'periodicidad_seguimiento' => $this->periodicidadSeguimiento,
        ]);

        // observaciones_seguimiento vive en firmas_plan
        $this->_actualizarOServicioFirma(['observaciones_seguimiento' => $this->observacionesSeguimiento]);
        $this->mensajeExito = 'Condiciones de seguimiento guardadas.';
    }

    public function marcarFirmaProfesional(bool $valor): void
    {
        if (! $this->plan || $this->plan->estado === 'cerrado') return;

        $this->profesionalFirmado = $valor;
        $this->_actualizarOServicioFirma([
            'profesional_firmado'    => $valor,
            'profesional_firmado_en' => $valor ? now() : null,
        ]);
    }

    public function marcarFirmaCiudadano(bool $valor): void
    {
        if (! $this->plan || $this->plan->estado === 'cerrado') return;

        $this->ciudadanoFirmado = $valor;
        $this->_actualizarOServicioFirma([
            'ciudadano_firmado'    => $valor,
            'ciudadano_firmado_en' => $valor ? now() : null,
        ]);
    }

    public function guardarFechaFirma(): void
    {
        if (! $this->plan) return;
        $this->_actualizarOServicioFirma(['fecha_firma' => $this->fechaFirmaPresencial]);
    }

    private function _actualizarOServicioFirma(array $datos): void
    {
        FirmaPlan::updateOrCreate(
            ['plan_id' => $this->plan->id, 'version' => $this->plan->version],
            array_merge(['metodo_firma' => 'manuscrita'], $datos)
        );
    }

    public function activarPlan(): void
    {
        if (! $this->plan || ! $this->puedeActivarse) return;
        $this->authorize('update', $this->plan);

        $this->plan->update(['estado' => 'activo', 'fecha_firma' => $this->fechaFirmaPresencial ?? now()->toDateString()]);
        $this->mensajeExito = 'Plan activado correctamente.';
        unset($this->plan);
    }

    // =========================================================
    // ACCIONES — MODAL DE MOTIVO
    // =========================================================

    private function encolarAccion(string $accion, array $params): void
    {
        $this->motivoAccionPendiente = $accion;
        $this->motivoAccionParams    = $params;
        $this->motivoTexto           = '';
        $this->modalMotivoAbierto    = true;
    }

    public function confirmarCambioConMotivo(): void
    {
        if (empty(trim($this->motivoTexto))) return;
        if (! $this->plan) return;

        // Registrar cambio antes de aplicarlo
        $this->plan->registrarCambio(
            auth()->id(),
            trim($this->motivoTexto),
            'discrecional'
        );

        // Ejecutar la acción pendiente
        match ($this->motivoAccionPendiente) {
            'eliminarFicha'      => $this->_eliminarFichaDirecto($this->motivoAccionParams['ficha_id']),
            'aplicarFichas'      => $this->_aplicarFichas($this->motivoAccionParams['fichas']),
            'guardarDiagnostico' => $this->plan->update(['diagnostico_social' => $this->diagnosticoTexto]),
            'guardarSeguimiento' => $this->_guardarSeguimientoDirecto(),
            default              => null,
        };

        $this->modalMotivoAbierto    = false;
        $this->motivoAccionPendiente = '';
        $this->motivoAccionParams    = [];
        $this->motivoTexto           = '';
        $this->mensajeExito          = 'Cambio registrado.';
        unset($this->plan, $this->fichasDiagnostico);
    }

    public function cancelarCambio(): void
    {
        $this->modalMotivoAbierto    = false;
        $this->motivoAccionPendiente = '';
        $this->motivoAccionParams    = [];
        $this->motivoTexto           = '';

        // Revertir estado local si es necesario
        if ($this->motivoAccionPendiente === 'guardarDiagnostico') {
            $this->diagnosticoTexto = $this->plan?->diagnostico_social ?? '';
        }
    }

    private function _eliminarFichaDirecto(int $fichaId): void
    {
        $this->plan->fichasDiagnostico()->where('ficha_id', $fichaId)->delete();
        $this->fichasSeleccionadas = array_values(
            array_filter($this->fichasSeleccionadas, fn ($id) => $id !== $fichaId)
        );
    }

    private function _guardarSeguimientoDirecto(): void
    {
        $this->plan->update(['periodicidad_seguimiento' => $this->periodicidadSeguimiento]);
        $this->_actualizarOServicioFirma(['observaciones_seguimiento' => $this->observacionesSeguimiento]);
    }

    // =========================================================
    // GENERACIÓN PDF
    // =========================================================

    public function generarPdf(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        if (! $this->plan) abort(404);
        $this->authorize('view', $this->plan);

        $service = app(PlanPdfService::class);
        return response()->streamDownload(
            fn () => print($service->generar($this->plan)),
            $service->nombre($this->plan),
            ['Content-Type' => 'application/pdf']
        );
    }

    // =========================================================
    // RENDER
    // =========================================================

    public function render(): \Illuminate\View\View
    {
        return view('intervencion::livewire.plan-page')
            ->layout('layouts.operativo');
    }
}
```

---

## Paso 3 — Tabla `plan_fichas_diagnostico`

El componente referencia `$plan->fichasDiagnostico()`, que necesita su
propia tabla pivote. Crea la migración:

`Modules/Intervencion/database/migrations/2026_06_16_000015_create_plan_fichas_diagnostico_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_fichas_diagnostico', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')
                  ->constrained('planes_intervencion')
                  ->cascadeOnDelete();
            $table->foreignId('ficha_id')
                  ->constrained('fichas')
                  ->cascadeOnDelete();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();

            $table->unique(['plan_id', 'ficha_id']);
            $table->index('plan_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_fichas_diagnostico');
    }
};
```

Añade la relación en `PlanDeIntervencion`:

```php
public function fichasDiagnostico(): HasMany
{
    return $this->hasMany(
        \Modules\Intervencion\Models\PlanFichaDiagnostico::class,
        'plan_id'
    )->orderBy('orden');
}
```

Crea el modelo `PlanFichaDiagnostico`:

```php
<?php

namespace Modules\Intervencion\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanFichaDiagnostico extends Model
{
    protected $table = 'plan_fichas_diagnostico';

    protected $fillable = ['plan_id', 'ficha_id', 'orden'];

    public function ficha(): BelongsTo
    {
        return $this->belongsTo(\Modules\Intervencion\Models\Ficha::class, 'ficha_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PlanDeIntervencion::class, 'plan_id');
    }
}
```

---

## Paso 4 — Vista Blade `plan-page.blade.php`

Crea `Modules/Intervencion/resources/views/livewire/plan-page.blade.php`:

```blade
<div
    class="plan-layout"
    x-data
    x-on:keydown.escape.window="
        $wire.drawerAbierto && $wire.cerrarDrawer();
        $wire.modalMotivoAbierto && $wire.cancelarCambio();
    "
>

{{-- ============================================================
     BANDA DE CONTEXTO (sticky)
     ============================================================ --}}
<div class="plan-topbar">
    <a href="{{ route('ciudadania.intervencion', $this->plan?->historia_id) }}"
       wire:navigate
       class="plan-topbar__back">
        <i data-lucide="arrow-left" style="width:13px;height:13px"></i>
        Intervención
    </a>

    <div class="plan-topbar__citizen">
        <span class="plan-topbar__name">
            {{ $this->ciudadano?->nombre_completo ?? '—' }}
        </span>
        <span class="plan-topbar__meta">
            {{ $this->plan?->tipoPlan?->nombre ?? 'Plan de intervención' }}
        </span>
    </div>

    <div class="plan-topbar__badges">
        @if($this->plan)
        <span class="plan-badge plan-badge--{{ $this->plan->estado }}">
            {{ ucfirst($this->plan->estado) }}
        </span>
        <span class="plan-badge plan-badge--version">v{{ $this->plan->version }}</span>
        @endif
    </div>

    <div class="plan-topbar__actions">
        @if($this->plan)
        <button wire:click="generarPdf" class="plan-btn">
            <i data-lucide="file-down" style="width:13px;height:13px"></i>
            Generar PDF
        </button>
        @endif

        @if($this->plan?->estado === 'borrador')
        <button
            wire:click="activarPlan"
            class="plan-btn plan-btn--primary"
            @if(! $this->puedeActivarse) disabled title="Marca ambas firmas para activar" @endif
        >
            <i data-lucide="check" style="width:13px;height:13px"></i>
            Activar plan
        </button>
        @endif

        @if($this->plan?->estado === 'activo')
        <button class="plan-btn">
            <i data-lucide="x-circle" style="width:13px;height:13px"></i>
            Cerrar plan
        </button>
        @endif
    </div>
</div>

{{-- Mensaje de éxito --}}
@if($mensajeExito)
<div class="plan-exito" x-init="setTimeout(() => $wire.set('mensajeExito', ''), 3000)">
    <i data-lucide="check-circle" style="width:13px;height:13px"></i>
    {{ $mensajeExito }}
</div>
@endif

{{-- ============================================================
     CUERPO + ÍNDICE
     ============================================================ --}}
<div class="plan-body-wrap">
<div class="plan-body">

    {{-- SECCIÓN 0: Datos de la persona --}}
    <div class="plan-section" id="ps-datos">
        <div class="plan-section__header">
            <div class="plan-section__title">
                <i data-lucide="user" style="width:15px;height:15px"></i>
                Datos de la persona
            </div>
            <span class="plan-readonly-badge">Solo lectura · Historia Social</span>
        </div>
        <div class="plan-section__body">
            <div class="plan-citizen-grid">
                <div class="plan-citizen-field">
                    <div class="plan-citizen-label">Nombre completo</div>
                    <div class="plan-citizen-value">{{ $this->ciudadano?->nombre_completo }}</div>
                </div>
                <div class="plan-citizen-field">
                    <div class="plan-citizen-label">Fecha de nacimiento</div>
                    <div class="plan-citizen-value">
                        {{ $this->ciudadano?->fecha_nacimiento?->format('d/m/Y') }}
                    </div>
                </div>
                <div class="plan-citizen-field">
                    <div class="plan-citizen-label">Documento</div>
                    <div class="plan-citizen-value">{{ $this->ciudadano?->documentoPrincipal() }}</div>
                </div>
                <div class="plan-citizen-field">
                    <div class="plan-citizen-label">Domicilio</div>
                    <div class="plan-citizen-value">{{ $this->ciudadano?->domicilio }}</div>
                </div>
            </div>

            @if($this->miembrosUc->isNotEmpty())
            <div class="plan-uc-members">
                <div class="plan-uc-label">Unidad de convivencia</div>
                @foreach($this->miembrosUc as $m)
                <span class="plan-member-pill">
                    {{ $m['ciudadano']->nombre_completo }}
                    @if($m['relacion'])
                    <span class="plan-member-relacion">{{ $m['relacion'] }}</span>
                    @endif
                </span>
                @endforeach
            </div>
            @endif
        </div>
    </div>

    {{-- SECCIÓN 1: Diagnóstico social --}}
    <div class="plan-section" id="ps-diagnostico">
        <div class="plan-section__header">
            <div class="plan-section__title">
                <i data-lucide="file-text" style="width:15px;height:15px"></i>
                Diagnóstico social
            </div>
            <button wire:click="abrirDrawer" class="plan-btn">
                <i data-lucide="database" style="width:13px;height:13px"></i>
                Añadir fichas
            </button>
        </div>
        <div class="plan-section__body">

            {{-- Bloque A: Evidencia de fichas --}}
            <div class="plan-evidencia">
                @forelse($this->fichasDiagnostico as $pfd)
                <div class="plan-ficha-card" wire:key="pfd-{{ $pfd->id }}"
                     x-data="{ expandida: false }">
                    <div class="plan-ficha-header">
                        <div class="plan-ficha-title" @click="expandida = !expandida" style="cursor:pointer">
                            <i data-lucide="lock" style="width:11px;height:11px;opacity:.5"></i>
                            {{ $pfd->ficha?->tipoFicha?->nombre ?? 'Ficha' }}
                            <span class="plan-ficha-date">
                                {{ $pfd->ficha?->created_at?->format('d/m/Y') }}
                            </span>
                            <i data-lucide="chevron-down" style="width:12px;height:12px"
                               x-bind:style="expandida ? 'transform:rotate(180deg)' : ''"></i>
                        </div>
                        <button
                            wire:click="eliminarFichaDiagnostico({{ $pfd->ficha_id }})"
                            class="plan-ficha-remove"
                            title="Eliminar del diagnóstico"
                        >
                            <i data-lucide="x" style="width:12px;height:12px"></i>
                        </button>
                    </div>
                    <div class="plan-ficha-content" x-show="expandida" x-cloak>
                        {{-- Renderiza el contenido de la ficha --}}
                        @php $datos = $pfd->ficha?->datos ?? [] @endphp
                        @forelse($datos as $campo => $valor)
                        <div class="plan-ficha-campo">
                            <span class="plan-ficha-campo-label">{{ $campo }}</span>
                            <span class="plan-ficha-campo-valor">{{ is_array($valor) ? implode(', ', $valor) : $valor }}</span>
                        </div>
                        @empty
                        <span class="plan-ficha-vacia">Sin datos registrados.</span>
                        @endforelse
                    </div>
                </div>
                @empty
                <div class="plan-evidencia-vacia">
                    Ninguna ficha añadida aún.
                    <button wire:click="abrirDrawer" class="plan-link">Añadir fichas del historial</button>
                </div>
                @endforelse

                @if($this->fichasDiagnostico->isNotEmpty())
                <button wire:click="abrirDrawer" class="plan-add-ficha-btn">
                    <i data-lucide="plus" style="width:13px;height:13px"></i>
                    Añadir otra ficha
                </button>
                @endif
            </div>

            {{-- Bloque B: Síntesis profesional --}}
            <div class="plan-sintesis">
                <div class="plan-sintesis-label">
                    <i data-lucide="pencil" style="width:13px;height:13px"></i>
                    Síntesis profesional
                </div>
                <div class="plan-editor-toolbar">
                    <button class="plan-tb-btn" onclick="document.execCommand('bold')"
                            title="Negrita"><strong>B</strong></button>
                    <button class="plan-tb-btn" onclick="document.execCommand('italic')"
                            title="Cursiva"><em>I</em></button>
                    <button class="plan-tb-btn" onclick="document.execCommand('insertUnorderedList')"
                            title="Lista">
                        <i data-lucide="list" style="width:13px;height:13px"></i>
                    </button>
                </div>
                <div
                    class="plan-editor-area"
                    contenteditable="{{ $this->plan?->estado !== 'cerrado' ? 'true' : 'false' }}"
                    x-data
                    x-on:blur="$wire.set('diagnosticoTexto', $el.innerHTML); $wire.guardarDiagnostico()"
                >{{ $diagnosticoTexto }}</div>
            </div>

        </div>
    </div>

    {{-- SECCIÓN 2: Objetivos --}}
    <div class="plan-section" id="ps-objetivos">
        <div class="plan-section__header">
            <div class="plan-section__title">
                <i data-lucide="target" style="width:15px;height:15px"></i>
                Objetivos
            </div>
            <button class="plan-btn">
                <i data-lucide="plus" style="width:13px;height:13px"></i>
                Añadir objetivo
            </button>
        </div>
        <div class="plan-section__body">
            @if($this->objetivosGenerales->isEmpty())
            <div class="plan-vacio">Ningún objetivo definido aún.</div>
            @else
            <div class="plan-obj-grid">
                @foreach($this->objetivosGenerales as $og)
                <div class="plan-obj-general" wire:key="og-{{ $og->id }}">
                    <div class="plan-obj-texto">{{ $og->texto }}</div>
                    @if($og->objetivosEspecificos->isNotEmpty())
                    <ul class="plan-obj-especificos">
                        @foreach($og->objetivosEspecificos as $oe)
                        <li wire:key="oe-{{ $oe->id }}">{{ $oe->texto }}</li>
                        @endforeach
                    </ul>
                    @endif
                    <div class="plan-obj-footer">
                        <span class="plan-estado-badge plan-estado-{{ $og->estado }}">
                            {{ ucfirst(str_replace('_', ' ', $og->estado)) }}
                        </span>
                        <button class="plan-tb-btn">
                            <i data-lucide="edit" style="width:13px;height:13px"></i>
                        </button>
                    </div>
                </div>
                @endforeach
            </div>
            @endif
        </div>
    </div>

    {{-- SECCIÓN 3: Compromisos del Ayuntamiento --}}
    <div class="plan-section" id="ps-ayto">
        <div class="plan-section__header">
            <div class="plan-section__title">
                <i data-lucide="building" style="width:15px;height:15px"></i>
                Compromisos del Ayuntamiento
            </div>
            <button class="plan-btn">
                <i data-lucide="plus" style="width:13px;height:13px"></i>
                Añadir
            </button>
        </div>
        <div class="plan-section__body plan-section__body--no-pad">
            @if($this->actuacionesAyuntamiento->isEmpty())
            <div class="plan-vacio" style="padding:16px">Ninguna actuación definida.</div>
            @else
            <table class="plan-table">
                <thead>
                    <tr>
                        <th>Prestación</th>
                        <th>Concreción</th>
                        <th>Responsable</th>
                        <th>Inicio previsto</th>
                        <th>Estado</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($this->actuacionesAyuntamiento as $act)
                    <tr wire:key="aact-{{ $act->id }}">
                        <td>
                            <div class="plan-prestacion-name">{{ $act->prestacion->nombre }}</div>
                            <div class="plan-prestacion-code">{{ $act->prestacion->codigo }}</div>
                        </td>
                        <td class="plan-td-secondary">{{ $act->descripcion_especifica ?? '—' }}</td>
                        <td>
                            @if($act->responsable)
                            <div class="plan-avatar-sm">{{ substr($act->responsable->name, 0, 2) }}</div>
                            @else —
                            @endif
                        </td>
                        <td class="plan-td-secondary">{{ $act->fecha_inicio_prevista?->format('d/m/Y') ?? '—' }}</td>
                        <td><span class="plan-estado-badge plan-estado-{{ $act->estado }}">{{ ucfirst($act->estado) }}</span></td>
                        <td><button class="plan-tb-btn"><i data-lucide="edit" style="width:13px;height:13px"></i></button></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
        </div>
    </div>

    {{-- SECCIÓN 4: Compromisos del ciudadano --}}
    <div class="plan-section" id="ps-ciudadano">
        <div class="plan-section__header">
            <div class="plan-section__title">
                <i data-lucide="user-check" style="width:15px;height:15px"></i>
                Compromisos de la persona
            </div>
            <button class="plan-btn">
                <i data-lucide="plus" style="width:13px;height:13px"></i>
                Añadir
            </button>
        </div>
        <div class="plan-section__body">
            @if($this->actuacionesCiudadano->isEmpty())
            <div class="plan-vacio">Ningún compromiso definido.</div>
            @else
            <div class="plan-comp-list">
                @foreach($this->actuacionesCiudadano as $act)
                <div class="plan-comp-item" wire:key="aciu-{{ $act->id }}">
                    <i data-lucide="circle-check" style="width:14px;height:14px;flex-shrink:0;margin-top:1px"></i>
                    <div>
                        <div>{{ $act->descripcion }}</div>
                        @if($act->prestacion)
                        <span class="plan-prestacion-pill">{{ $act->prestacion->nombre }}</span>
                        @endif
                    </div>
                    <button class="plan-tb-btn" style="margin-left:auto">
                        <i data-lucide="edit" style="width:13px;height:13px"></i>
                    </button>
                </div>
                @endforeach
            </div>
            @endif
        </div>
    </div>

    {{-- SECCIÓN 5: Participantes --}}
    <div class="plan-section" id="ps-participantes">
        <div class="plan-section__header">
            <div class="plan-section__title">
                <i data-lucide="users" style="width:15px;height:15px"></i>
                Profesionales participantes
            </div>
            <button class="plan-btn">
                <i data-lucide="plus" style="width:13px;height:13px"></i>
                Añadir
            </button>
        </div>
        <div class="plan-section__body">
            <div class="plan-part-list">
                @foreach($this->participantes as $p)
                <div class="plan-part-row" wire:key="part-{{ $p->id }}">
                    <div class="plan-part-avatar">{{ substr($p->profesional->name, 0, 2) }}</div>
                    <div class="plan-part-info">
                        <div class="plan-part-name">{{ $p->profesional->name }}</div>
                        <div class="plan-part-rol">
                            {{ $p->rol_en_plan }}
                            @if($p->servicio) · {{ $p->servicio->nombre }} @endif
                        </div>
                    </div>
                    @if($p->user_id === $this->plan?->profesional_responsable_id)
                    <span class="plan-badge-responsable">Responsable</span>
                    @else
                    <button class="plan-tb-btn">
                        <i data-lucide="x" style="width:13px;height:13px"></i>
                    </button>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- SECCIÓN 6: Seguimiento y firmas --}}
    <div class="plan-section" id="ps-firmas">
        <div class="plan-section__header">
            <div class="plan-section__title">
                <i data-lucide="writing" style="width:15px;height:15px"></i>
                Seguimiento y firmas
            </div>
        </div>
        <div class="plan-section__body">

            {{-- Condiciones de seguimiento --}}
            <div class="plan-seguimiento">
                <div class="plan-seguimiento-title">Condiciones de seguimiento</div>
                <div class="plan-seguimiento-fields">
                    <div class="plan-field">
                        <label class="plan-label">Frecuencia de seguimiento</label>
                        <select
                            wire:model.live="periodicidadSeguimiento"
                            wire:change="guardarSeguimiento"
                            class="plan-select"
                        >
                            <option value="bimensual">Bimensual</option>
                            <option value="trimestral">Trimestral</option>
                            <option value="cuatrimestral">Cuatrimestral</option>
                            <option value="semestral">Semestral</option>
                        </select>
                    </div>
                    <div class="plan-field plan-field--full">
                        <label class="plan-label">Observaciones sobre el seguimiento</label>
                        <textarea
                            wire:model.lazy="observacionesSeguimiento"
                            wire:change="guardarSeguimiento"
                            class="plan-textarea"
                            rows="2"
                            placeholder="Acuerdos sobre el seguimiento, condiciones especiales…"
                        ></textarea>
                    </div>
                </div>
            </div>

            <div class="plan-firmas-divider"></div>

            {{-- Firmas --}}
            <div class="plan-firmas-grid">
                <div class="plan-firma-card">
                    <div class="plan-firma-quien">{{ $this->plan?->profesionalResponsable?->name }}</div>
                    <div class="plan-firma-rol">Profesional responsable</div>
                    <label class="plan-firma-check">
                        <input
                            type="checkbox"
                            wire:model.live="profesionalFirmado"
                            wire:change="marcarFirmaProfesional($event.target.checked)"
                            @if($this->plan?->estado === 'cerrado') disabled @endif
                        >
                        Ha firmado en papel
                    </label>
                    @if($profesionalFirmado)
                    <div class="plan-firma-fecha-reg">
                        Registrado: {{ \Modules\Intervencion\Models\FirmaPlan::where('plan_id', $this->plan->id)->where('version', $this->plan->version)->value('profesional_firmado_en')?->format('d/m/Y H:i') }}
                    </div>
                    @endif
                </div>

                <div class="plan-firma-card">
                    <div class="plan-firma-quien">{{ $this->ciudadano?->nombre_completo }}</div>
                    <div class="plan-firma-rol">Persona interesada</div>
                    <label class="plan-firma-check">
                        <input
                            type="checkbox"
                            wire:model.live="ciudadanoFirmado"
                            wire:change="marcarFirmaCiudadano($event.target.checked)"
                            @if($this->plan?->estado === 'cerrado') disabled @endif
                        >
                        Ha firmado en papel
                    </label>
                    @if($ciudadanoFirmado)
                    <div class="plan-firma-fecha-reg">
                        Registrado: {{ \Modules\Intervencion\Models\FirmaPlan::where('plan_id', $this->plan->id)->where('version', $this->plan->version)->value('ciudadano_firmado_en')?->format('d/m/Y H:i') }}
                    </div>
                    @endif
                </div>
            </div>

            {{-- Fecha de firma presencial --}}
            <div class="plan-field" style="margin-top:12px; max-width:200px">
                <label class="plan-label">Fecha de la firma presencial</label>
                <input
                    type="date"
                    wire:model.lazy="fechaFirmaPresencial"
                    wire:change="guardarFechaFirma"
                    class="plan-input"
                >
            </div>

            {{-- Estado de activación --}}
            @if($this->puedeActivarse)
            <div class="plan-firma-lista-ok">
                <i data-lucide="check-circle" style="width:14px;height:14px"></i>
                Ambas partes han firmado. El plan puede activarse desde el botón superior.
            </div>
            @endif

            <div class="plan-firma-nota">
                Una vez activado el plan, cualquier cambio requerirá indicar el motivo.
                El PDF puede generarse en cualquier momento desde el botón superior.
            </div>

        </div>
    </div>

</div>{{-- fin plan-body --}}

{{-- ÍNDICE LATERAL --}}
<nav class="plan-index" aria-label="Secciones del plan">
    <div class="plan-index-label">Secciones</div>
    <a href="#ps-datos"         class="plan-index-item"><span class="plan-index-dot plan-index-dot--done"></span> Datos</a>
    <a href="#ps-diagnostico"   class="plan-index-item"><span class="plan-index-dot plan-index-dot--current"></span> Diagnóstico</a>
    <a href="#ps-objetivos"     class="plan-index-item"><span class="plan-index-dot"></span> Objetivos</a>
    <a href="#ps-ayto"          class="plan-index-item"><span class="plan-index-dot"></span> Ayuntamiento</a>
    <a href="#ps-ciudadano"     class="plan-index-item"><span class="plan-index-dot"></span> Ciudadano</a>
    <a href="#ps-participantes" class="plan-index-item"><span class="plan-index-dot"></span> Participantes</a>
    <a href="#ps-firmas"        class="plan-index-item"><span class="plan-index-dot"></span> Firmas</a>

    <div class="plan-index-meta">
        <div class="plan-index-meta-label">Seguimiento</div>
        <div class="plan-index-meta-value">{{ ucfirst($periodicidadSeguimiento) }}</div>
    </div>
</nav>
</div>{{-- fin plan-body-wrap --}}

{{-- ============================================================
     DRAWER DEL HISTORIAL
     ============================================================ --}}
@if($drawerAbierto)
<div class="plan-drawer-overlay" wire:click="cerrarDrawer">
    <div class="plan-drawer" wire:click.stop x-data="{ seleccion: @entangle('fichasSeleccionadas') }">
        <div class="plan-drawer-header">
            <div class="plan-drawer-title">Historia social — fichas</div>
            <button wire:click="cerrarDrawer" aria-label="Cerrar">
                <i data-lucide="x" style="width:16px;height:16px"></i>
            </button>
        </div>

        <div class="plan-drawer-filters">
            <button wire:click="$set('drawerFiltroFecha','todas')"
                class="plan-chip {{ $drawerFiltroFecha === 'todas' ? 'plan-chip--on' : '' }}">Todas</button>
            <button wire:click="$set('drawerFiltroFecha','mes')"
                class="plan-chip {{ $drawerFiltroFecha === 'mes' ? 'plan-chip--on' : '' }}">Último mes</button>
            <button wire:click="$set('drawerFiltroFecha','anio')"
                class="plan-chip {{ $drawerFiltroFecha === 'anio' ? 'plan-chip--on' : '' }}">Último año</button>
        </div>

        <div class="plan-drawer-body">
            @forelse($this->valoracionesTimeline as $val)
            <div class="plan-drawer-val" wire:key="val-{{ $val->id }}">
                <div class="plan-drawer-val-header">
                    {{ $val->tipoValoracion?->nombre ?? 'Valoración' }}
                    <span class="plan-drawer-val-date">{{ $val->created_at->format('d/m/Y') }}</span>
                </div>
                @foreach($val->fichas as $ficha)
                <div class="plan-drawer-ficha" wire:key="df-{{ $ficha->id }}">
                    <input
                        type="checkbox"
                        id="df{{ $ficha->id }}"
                        value="{{ $ficha->id }}"
                        x-model="seleccion"
                    >
                    <label for="df{{ $ficha->id }}">{{ $ficha->tipoFicha?->nombre ?? 'Ficha' }}</label>
                    @if(in_array($ficha->id, $fichasSeleccionadas))
                    <span class="plan-chip plan-chip--on" style="font-size:10px;padding:1px 6px">Añadida</span>
                    @endif
                </div>
                @endforeach
            </div>
            @empty
            <div class="plan-vacio" style="padding:16px">No hay valoraciones en el historial.</div>
            @endforelse
        </div>

        <div class="plan-drawer-footer">
            <button wire:click="cerrarDrawer" class="plan-btn">Cancelar</button>
            <button
                x-on:click="$wire.aplicarSeleccionFichas(seleccion)"
                class="plan-btn plan-btn--primary"
            >
                <i data-lucide="check" style="width:13px;height:13px"></i>
                Aplicar selección
            </button>
        </div>
    </div>
</div>
@endif

{{-- ============================================================
     MODAL DE MOTIVO OBLIGATORIO
     ============================================================ --}}
@if($modalMotivoAbierto)
<div class="plan-modal-overlay">
    <div class="plan-modal">
        <div class="plan-modal-title">Cambio en plan firmado</div>
        <div class="plan-modal-sub">
            Para realizar este cambio en un plan activo, indica el motivo.
            Quedará registrado en el historial del plan.
        </div>
        <textarea
            wire:model="motivoTexto"
            class="plan-textarea"
            rows="3"
            placeholder="ej: se actualizó la ficha de vivienda tras visita domiciliaria…"
            autofocus
        ></textarea>
        <div class="plan-modal-footer">
            <button wire:click="cancelarCambio" class="plan-btn">Cancelar</button>
            <button
                wire:click="confirmarCambioConMotivo"
                class="plan-btn plan-btn--primary"
                @if(empty(trim($motivoTexto))) disabled @endif
            >
                <i data-lucide="check" style="width:13px;height:13px"></i>
                Confirmar cambio
            </button>
        </div>
    </div>
</div>
@endif

</div>{{-- fin plan-layout --}}
```

---

## Paso 5 — CSS en `app-operativo.css`

Añade al final del fichero:

```css
/* ============================================================
   PLAN PAGE — layout
   ============================================================ */

.plan-layout { display: flex; flex-direction: column; min-height: 100vh; }

.plan-topbar {
    display: flex; align-items: center; gap: 12px;
    padding: 10px 20px;
    background: var(--color-surface);
    border-bottom: 1px solid var(--color-border);
    position: sticky; top: 0; z-index: 10;
}
.plan-topbar__back {
    display: flex; align-items: center; gap: 4px;
    font-size: 12px; color: var(--color-text-secondary);
    padding: 4px 8px; border-radius: 6px;
    border: 1px solid var(--color-border); text-decoration: none;
    white-space: nowrap;
}
.plan-topbar__back:hover { background: var(--color-surface-alt); }
.plan-topbar__citizen { flex: 1; min-width: 0; }
.plan-topbar__name { font-size: 14px; font-weight: 600; color: var(--color-text-primary); display: block; }
.plan-topbar__meta { font-size: 11px; color: var(--color-text-secondary); }
.plan-topbar__badges { display: flex; gap: 6px; align-items: center; }
.plan-topbar__actions { display: flex; gap: 6px; }

.plan-body-wrap {
    display: flex; flex: 1; gap: 0;
    padding: 20px 24px 20px 20px;
    align-items: flex-start;
}
.plan-body {
    flex: 1; display: flex; flex-direction: column; gap: 14px; min-width: 0;
}

/* ============================================================
   BADGES Y BOTONES
   ============================================================ */

.plan-badge {
    font-size: 11px; padding: 2px 8px; border-radius: 99px; font-weight: 500;
}
.plan-badge--borrador  { background: #FAEEDA; color: #633806; }
.plan-badge--activo    { background: #EAF3DE; color: #27500A; }
.plan-badge--en_revision { background: #E6F1FB; color: #0C447C; }
.plan-badge--cerrado   { background: var(--color-neutral-100); color: var(--color-text-secondary); }
.plan-badge--version   { background: var(--color-surface-alt); color: var(--color-text-secondary); border: 1px solid var(--color-border); }
.plan-badge-responsable { background: #EAF3DE; color: #27500A; font-size: 10px; padding: 2px 7px; border-radius: 99px; }

.plan-btn {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: 12px; padding: 5px 10px;
    border-radius: 6px; border: 1px solid var(--color-border);
    background: var(--color-surface); color: var(--color-text-primary);
    cursor: pointer; white-space: nowrap;
}
.plan-btn:hover { background: var(--color-surface-alt); }
.plan-btn:disabled { opacity: .45; cursor: not-allowed; }
.plan-btn--primary { background: var(--color-text-primary); color: var(--color-surface); border-color: var(--color-text-primary); }
.plan-btn--primary:hover { opacity: .85; }
.plan-btn--primary:disabled { opacity: .4; }
.plan-tb-btn { background: none; border: none; cursor: pointer; padding: 2px 4px; border-radius: 4px; color: var(--color-text-secondary); display: flex; align-items: center; }
.plan-tb-btn:hover { background: var(--color-surface-alt); color: var(--color-text-primary); }

/* ============================================================
   SECCIONES
   ============================================================ */

.plan-section {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: 8px; overflow: hidden;
}
.plan-section__header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 11px 16px;
    border-bottom: 1px solid var(--color-border);
}
.plan-section__title {
    display: flex; align-items: center; gap: 7px;
    font-size: 13px; font-weight: 600; color: var(--color-text-primary);
}
.plan-section__body { padding: 16px; }
.plan-section__body--no-pad { padding: 0; }
.plan-readonly-badge { font-size: 10px; color: var(--color-text-secondary); background: var(--color-surface-alt); padding: 2px 7px; border-radius: 99px; border: 1px solid var(--color-border); }

/* ============================================================
   DATOS DE LA PERSONA
   ============================================================ */

.plan-citizen-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; background: var(--color-surface-alt); border-radius: 6px; padding: 12px 14px; }
.plan-citizen-field { display: flex; flex-direction: column; gap: 2px; }
.plan-citizen-label { font-size: 10px; color: var(--color-text-secondary); text-transform: uppercase; letter-spacing: .05em; }
.plan-citizen-value { font-size: 13px; color: var(--color-text-primary); }
.plan-uc-members { margin-top: 10px; padding-top: 10px; border-top: 1px solid var(--color-border); }
.plan-uc-label { font-size: 10px; color: var(--color-text-secondary); text-transform: uppercase; letter-spacing: .05em; margin-bottom: 6px; }
.plan-member-pill { display: inline-flex; align-items: center; gap: 4px; font-size: 11px; padding: 2px 8px; background: var(--color-surface); border: 1px solid var(--color-border); border-radius: 99px; margin: 2px; color: var(--color-text-primary); }
.plan-member-relacion { color: var(--color-text-secondary); }

/* ============================================================
   DIAGNÓSTICO
   ============================================================ */

.plan-evidencia { display: flex; flex-direction: column; gap: 6px; margin-bottom: 16px; }
.plan-ficha-card { border: 1px solid var(--color-border); border-radius: 6px; overflow: hidden; }
.plan-ficha-header { display: flex; align-items: center; justify-content: space-between; padding: 7px 12px; background: var(--color-surface-alt); }
.plan-ficha-title { display: flex; align-items: center; gap: 5px; font-size: 12px; font-weight: 500; color: var(--color-text-primary); flex: 1; }
.plan-ficha-date { font-size: 11px; color: var(--color-text-secondary); font-weight: 400; }
.plan-ficha-remove { background: none; border: none; cursor: pointer; color: var(--color-text-secondary); padding: 2px; border-radius: 3px; display: flex; }
.plan-ficha-remove:hover { color: var(--color-danger); background: var(--color-danger-bg); }
.plan-ficha-content { padding: 10px 12px; }
.plan-ficha-campo { display: flex; gap: 8px; font-size: 12px; margin-bottom: 3px; }
.plan-ficha-campo-label { font-weight: 600; color: var(--color-text-secondary); min-width: 120px; }
.plan-ficha-campo-valor { color: var(--color-text-primary); }
.plan-ficha-vacia { font-size: 12px; color: var(--color-text-secondary); font-style: italic; }
.plan-add-ficha-btn { display: flex; align-items: center; justify-content: center; gap: 6px; padding: 7px; border: 1px dashed var(--color-border); border-radius: 6px; font-size: 12px; color: var(--color-text-secondary); cursor: pointer; background: transparent; width: 100%; }
.plan-add-ficha-btn:hover { background: var(--color-surface-alt); }
.plan-evidencia-vacia { font-size: 13px; color: var(--color-text-secondary); padding: 8px 0; }
.plan-link { background: none; border: none; color: var(--color-primary); cursor: pointer; font-size: 13px; padding: 0; text-decoration: underline; }

.plan-sintesis { }
.plan-sintesis-label { display: flex; align-items: center; gap: 5px; font-size: 11px; color: var(--color-text-secondary); margin-bottom: 6px; }
.plan-editor-toolbar { display: flex; gap: 2px; padding: 4px 6px; border: 1px solid var(--color-border); border-bottom: none; border-radius: 6px 6px 0 0; background: var(--color-surface-alt); }
.plan-editor-area { border: 1px solid var(--color-border); border-radius: 0 0 6px 6px; padding: 10px 12px; font-size: 13px; color: var(--color-text-primary); line-height: 1.7; min-height: 80px; outline: none; }
.plan-editor-area:focus { border-color: var(--color-primary); }

/* ============================================================
   OBJETIVOS
   ============================================================ */

.plan-obj-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.plan-obj-general { border: 1px solid var(--color-border); border-radius: 6px; padding: 10px 12px; }
.plan-obj-texto { font-size: 13px; color: var(--color-text-primary); margin-bottom: 8px; line-height: 1.5; font-weight: 500; }
.plan-obj-especificos { list-style: none; display: flex; flex-direction: column; gap: 4px; padding: 0; }
.plan-obj-especificos li { font-size: 12px; color: var(--color-text-secondary); padding: 3px 8px; background: var(--color-surface-alt); border-radius: 4px; }
.plan-obj-especificos li::before { content: '— '; }
.plan-obj-footer { display: flex; align-items: center; justify-content: space-between; margin-top: 8px; padding-top: 8px; border-top: 1px solid var(--color-border); }

.plan-estado-badge { font-size: 10px; padding: 2px 7px; border-radius: 99px; }
.plan-estado-pendiente   { background: #FAEEDA; color: #633806; }
.plan-estado-en_proceso  { background: #E6F1FB; color: #0C447C; }
.plan-estado-conseguido  { background: #EAF3DE; color: #27500A; }
.plan-estado-abandonado  { background: var(--color-neutral-100); color: var(--color-text-secondary); }
.plan-estado-en_curso    { background: #E6F1FB; color: #0C447C; }
.plan-estado-completada  { background: #EAF3DE; color: #27500A; }
.plan-estado-cancelada   { background: var(--color-neutral-100); color: var(--color-text-secondary); }

/* ============================================================
   TABLA ACTUACIONES AYTO
   ============================================================ */

.plan-table { width: 100%; border-collapse: collapse; font-size: 12px; }
.plan-table th { font-size: 10px; font-weight: 600; color: var(--color-text-secondary); text-transform: uppercase; letter-spacing: .05em; padding: 7px 12px; border-bottom: 1px solid var(--color-border); text-align: left; background: var(--color-surface-alt); }
.plan-table td { padding: 9px 12px; border-bottom: 1px solid var(--color-border); vertical-align: top; color: var(--color-text-primary); }
.plan-table tr:last-child td { border-bottom: none; }
.plan-prestacion-name { font-weight: 500; }
.plan-prestacion-code { font-size: 10px; color: var(--color-text-secondary); margin-top: 1px; font-family: var(--font-mono); }
.plan-prestacion-pill { font-size: 10px; color: var(--color-primary); background: var(--color-primary-bg, #E6F1FB); padding: 1px 6px; border-radius: 99px; margin-top: 3px; display: inline-block; }
.plan-td-secondary { color: var(--color-text-secondary); }
.plan-avatar-sm { width: 24px; height: 24px; border-radius: 50%; background: var(--color-surface-alt); border: 1px solid var(--color-border); display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight: 600; color: var(--color-text-secondary); }

/* ============================================================
   COMPROMISOS CIUDADANO
   ============================================================ */

.plan-comp-list { display: flex; flex-direction: column; gap: 6px; }
.plan-comp-item { display: flex; align-items: flex-start; gap: 8px; padding: 8px 12px; background: var(--color-surface-alt); border-radius: 6px; font-size: 13px; color: var(--color-text-primary); }

/* ============================================================
   PARTICIPANTES
   ============================================================ */

.plan-part-list { display: flex; flex-direction: column; gap: 6px; }
.plan-part-row { display: flex; align-items: center; gap: 10px; padding: 8px 12px; border: 1px solid var(--color-border); border-radius: 6px; }
.plan-part-avatar { width: 30px; height: 30px; border-radius: 50%; background: var(--color-surface-alt); border: 1px solid var(--color-border); display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 600; color: var(--color-text-secondary); flex-shrink: 0; }
.plan-part-info { flex: 1; }
.plan-part-name { font-size: 13px; font-weight: 500; color: var(--color-text-primary); }
.plan-part-rol { font-size: 11px; color: var(--color-text-secondary); }

/* ============================================================
   SEGUIMIENTO Y FIRMAS
   ============================================================ */

.plan-seguimiento-title { font-size: 12px; font-weight: 600; color: var(--color-text-secondary); text-transform: uppercase; letter-spacing: .05em; margin-bottom: 10px; }
.plan-seguimiento-fields { display: grid; grid-template-columns: 200px 1fr; gap: 12px; }
.plan-field { display: flex; flex-direction: column; gap: 4px; }
.plan-field--full { grid-column: 1 / -1; }
.plan-label { font-size: 11px; color: var(--color-text-secondary); font-weight: 500; }
.plan-select { padding: 6px 10px; border: 1px solid var(--color-border); border-radius: 6px; font-size: 13px; background: var(--color-surface); color: var(--color-text-primary); }
.plan-textarea { padding: 7px 10px; border: 1px solid var(--color-border); border-radius: 6px; font-size: 13px; background: var(--color-surface); color: var(--color-text-primary); resize: vertical; width: 100%; box-sizing: border-box; }
.plan-input { padding: 6px 10px; border: 1px solid var(--color-border); border-radius: 6px; font-size: 13px; background: var(--color-surface); color: var(--color-text-primary); width: 100%; }

.plan-firmas-divider { height: 1px; background: var(--color-border); margin: 16px 0; }
.plan-firmas-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.plan-firma-card { border: 1px solid var(--color-border); border-radius: 6px; padding: 14px; }
.plan-firma-quien { font-size: 13px; font-weight: 600; color: var(--color-text-primary); margin-bottom: 2px; }
.plan-firma-rol { font-size: 11px; color: var(--color-text-secondary); margin-bottom: 10px; }
.plan-firma-check { display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--color-text-primary); cursor: pointer; }
.plan-firma-fecha-reg { font-size: 11px; color: var(--color-text-secondary); margin-top: 6px; }
.plan-firma-lista-ok { display: flex; align-items: center; gap: 6px; font-size: 12px; color: var(--color-success); background: var(--color-success-bg); padding: 8px 12px; border-radius: 6px; margin-top: 12px; }
.plan-firma-nota { font-size: 11px; color: var(--color-text-secondary); margin-top: 10px; line-height: 1.5; }

/* ============================================================
   ÍNDICE LATERAL
   ============================================================ */

.plan-index { width: 140px; flex-shrink: 0; padding: 0 0 0 16px; position: sticky; top: 68px; display: flex; flex-direction: column; gap: 2px; }
.plan-index-label { font-size: 10px; font-weight: 600; color: var(--color-text-secondary); text-transform: uppercase; letter-spacing: .06em; margin-bottom: 6px; padding: 0 8px; }
.plan-index-item { display: flex; align-items: center; gap: 7px; font-size: 12px; color: var(--color-text-secondary); padding: 5px 8px; border-radius: 6px; text-decoration: none; }
.plan-index-item:hover { background: var(--color-surface-alt); color: var(--color-text-primary); }
.plan-index-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--color-border); flex-shrink: 0; }
.plan-index-dot--done    { background: #639922; }
.plan-index-dot--current { background: #378ADD; }
.plan-index-meta { margin-top: 16px; padding: 0 8px; }
.plan-index-meta-label { font-size: 10px; color: var(--color-text-secondary); text-transform: uppercase; letter-spacing: .05em; margin-bottom: 4px; }
.plan-index-meta-value { font-size: 12px; font-weight: 600; color: var(--color-text-primary); }

/* ============================================================
   DRAWER
   ============================================================ */

.plan-drawer-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.4); z-index: 40; display: flex; justify-content: flex-end; }
.plan-drawer { width: 380px; background: var(--color-surface); border-left: 1px solid var(--color-border); display: flex; flex-direction: column; height: 100%; }
.plan-drawer-header { display: flex; align-items: center; justify-content: space-between; padding: 14px 16px; border-bottom: 1px solid var(--color-border); }
.plan-drawer-title { font-size: 14px; font-weight: 600; color: var(--color-text-primary); }
.plan-drawer-filters { display: flex; gap: 6px; flex-wrap: wrap; padding: 10px 16px; border-bottom: 1px solid var(--color-border); }
.plan-chip { font-size: 11px; padding: 3px 9px; border: 1px solid var(--color-border); border-radius: 99px; cursor: pointer; color: var(--color-text-secondary); background: var(--color-surface); }
.plan-chip--on { background: var(--color-surface-alt); color: var(--color-text-primary); border-color: var(--color-border-secondary); }
.plan-drawer-body { flex: 1; overflow-y: auto; padding: 12px 16px; display: flex; flex-direction: column; gap: 8px; }
.plan-drawer-val { border: 1px solid var(--color-border); border-radius: 6px; overflow: hidden; }
.plan-drawer-val-header { display: flex; align-items: center; justify-content: space-between; padding: 8px 12px; background: var(--color-surface-alt); font-size: 12px; font-weight: 600; color: var(--color-text-primary); }
.plan-drawer-val-date { font-size: 11px; color: var(--color-text-secondary); font-weight: 400; }
.plan-drawer-ficha { display: flex; align-items: center; gap: 8px; padding: 7px 12px; border-top: 1px solid var(--color-border); font-size: 12px; color: var(--color-text-primary); }
.plan-drawer-ficha input { cursor: pointer; }
.plan-drawer-ficha label { flex: 1; cursor: pointer; }
.plan-drawer-footer { padding: 12px 16px; border-top: 1px solid var(--color-border); display: flex; gap: 8px; justify-content: flex-end; }

/* ============================================================
   MODAL DE MOTIVO
   ============================================================ */

.plan-modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.45); z-index: 50; display: flex; align-items: center; justify-content: center; }
.plan-modal { background: var(--color-surface); border: 1px solid var(--color-border); border-radius: 10px; padding: 20px; width: 360px; display: flex; flex-direction: column; gap: 10px; }
.plan-modal-title { font-size: 15px; font-weight: 600; color: var(--color-text-primary); }
.plan-modal-sub { font-size: 13px; color: var(--color-text-secondary); line-height: 1.5; }
.plan-modal-footer { display: flex; justify-content: flex-end; gap: 8px; }

/* ============================================================
   FEEDBACK Y MISC
   ============================================================ */

.plan-exito { display: flex; align-items: center; gap: 6px; padding: 8px 20px; background: var(--color-success-bg); color: var(--color-success); font-size: 12px; border-bottom: 1px solid var(--color-border); }
.plan-vacio { font-size: 13px; color: var(--color-text-secondary); font-style: italic; }
```

---

## Paso 6 — Enlace "Ver plan" desde `CiudadanoPage`

En `CiudadanoPage.php`, añade el computed:

```php
#[Computed]
public function planActivo(): ?\Modules\Intervencion\Models\PlanDeIntervencion
{
    return \Modules\Intervencion\Models\PlanDeIntervencion::where(
        'historia_id', $this->historia->id
    )->whereIn('estado', ['borrador', 'activo', 'en_revision'])
     ->latest()
     ->first();
}
```

En `ciudadano-page.blade.php`, en la zona de acciones del plan, reemplaza
el botón de plan existente por:

```blade
@if($this->planActivo)
<a
    href="{{ route('plan.show', $this->planActivo) }}"
    wire:navigate
    class="hs-action-btn"
>
    <i data-lucide="file-text" style="width:14px;height:14px"></i>
    Ver {{ $this->planNombreCorto }}
</a>
@else
<a
    href="{{ route('plan.crear', ['historia' => $this->historia->id]) }}"
    wire:navigate
    class="hs-action-btn hs-action-btn--primary"
>
    <i data-lucide="plus" style="width:14px;height:14px"></i>
    Crear {{ $this->planNombreCorto }}
</a>
@endif
```

---

## Paso 7 — Tests funcionales

Crea `Modules/Intervencion/tests/Feature/Livewire/PlanPageTest.php`:

```php
<?php

namespace Modules\Intervencion\Tests\Feature\Livewire;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Intervencion\Http\Livewire\PlanPage;
use Modules\Intervencion\Models\PlanDeIntervencion;
use Modules\Intervencion\Models\FirmaPlan;
use Modules\Intervencion\Models\TipoPlan;
use Modules\Intervencion\Models\PlanFichaDiagnostico;
use Modules\Intervencion\Models\Ficha;
use App\Models\User;
use Tests\TestCase;

/**
 * Tests funcionales de PlanPage.
 * Nomenclatura: TF-PP-XX
 */
class PlanPageTest extends TestCase
{
    use RefreshDatabase;

    private function montarPlan(string $estado = 'borrador'): array
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->seed(\Modules\Intervencion\Database\Seeders\TipoPlanSeeder::class);

        $plan = PlanDeIntervencion::factory()->create([
            'estado'   => $estado,
            'version'  => 1,
            'tipo_plan_id' => TipoPlan::first()->id,
            'profesional_responsable_id' => $user->id,
        ]);

        return [$user, $plan];
    }

    // TF-PP-01: La página monta correctamente con un plan existente
    public function test_monta_con_plan_existente(): void
    {
        [, $plan] = $this->montarPlan();
        Livewire::test(PlanPage::class, ['plan' => $plan])
            ->assertOk();
    }

    // TF-PP-02: puedeActivarse es false sin firmas
    public function test_no_puede_activarse_sin_firmas(): void
    {
        [, $plan] = $this->montarPlan();
        Livewire::test(PlanPage::class, ['plan' => $plan])
            ->assertSet('puedeActivarse', false);
    }

    // TF-PP-03: puedeActivarse es true con ambas firmas marcadas
    public function test_puede_activarse_con_ambas_firmas(): void
    {
        [$user, $plan] = $this->montarPlan();
        FirmaPlan::create([
            'plan_id' => $plan->id, 'version' => 1,
            'profesional_firmado' => true,
            'profesional_firmado_en' => now(),
            'ciudadano_firmado' => true,
            'ciudadano_firmado_en' => now(),
            'metodo_firma' => 'manuscrita',
        ]);

        Livewire::test(PlanPage::class, ['plan' => $plan->fresh()])
            ->assertSet('profesionalFirmado', true)
            ->assertSet('ciudadanoFirmado', true);
    }

    // TF-PP-04: marcarFirmaProfesional crea/actualiza registro en firmas_plan
    public function test_marcar_firma_profesional(): void
    {
        [, $plan] = $this->montarPlan();

        Livewire::test(PlanPage::class, ['plan' => $plan])
            ->call('marcarFirmaProfesional', true);

        $this->assertDatabaseHas('firmas_plan', [
            'plan_id' => $plan->id,
            'version' => 1,
            'profesional_firmado' => true,
        ]);
    }

    // TF-PP-05: activarPlan cambia estado a activo
    public function test_activar_plan(): void
    {
        [$user, $plan] = $this->montarPlan();
        FirmaPlan::create([
            'plan_id' => $plan->id, 'version' => 1,
            'profesional_firmado' => true,
            'profesional_firmado_en' => now(),
            'ciudadano_firmado' => true,
            'ciudadano_firmado_en' => now(),
            'metodo_firma' => 'manuscrita',
        ]);

        Livewire::test(PlanPage::class, ['plan' => $plan->fresh()])
            ->call('activarPlan');

        $this->assertEquals('activo', $plan->fresh()->estado);
    }

    // TF-PP-06: guardarDiagnostico en borrador guarda sin modal
    public function test_guardar_diagnostico_borrador(): void
    {
        [, $plan] = $this->montarPlan();

        Livewire::test(PlanPage::class, ['plan' => $plan])
            ->set('diagnosticoTexto', 'Situación de vulnerabilidad económica')
            ->call('guardarDiagnostico')
            ->assertSet('modalMotivoAbierto', false);

        $this->assertEquals(
            'Situación de vulnerabilidad económica',
            $plan->fresh()->diagnostico_social
        );
    }

    // TF-PP-07: guardarDiagnostico en plan activo abre modal de motivo
    public function test_guardar_diagnostico_activo_pide_motivo(): void
    {
        [, $plan] = $this->montarPlan('activo');

        Livewire::test(PlanPage::class, ['plan' => $plan])
            ->set('diagnosticoTexto', 'Texto actualizado')
            ->call('guardarDiagnostico')
            ->assertSet('modalMotivoAbierto', true);
    }

    // TF-PP-08: confirmarCambioConMotivo sin texto no ejecuta el cambio
    public function test_motivo_vacio_no_confirma(): void
    {
        [, $plan] = $this->montarPlan('activo');

        Livewire::test(PlanPage::class, ['plan' => $plan])
            ->set('diagnosticoTexto', 'Texto nuevo')
            ->call('guardarDiagnostico')
            ->set('motivoTexto', '')
            ->call('confirmarCambioConMotivo')
            ->assertSet('modalMotivoAbierto', true);
    }

    // TF-PP-09: confirmarCambioConMotivo con texto registra cambio y cierra modal
    public function test_motivo_con_texto_registra_cambio(): void
    {
        [, $plan] = $this->montarPlan('activo');

        Livewire::test(PlanPage::class, ['plan' => $plan])
            ->set('diagnosticoTexto', 'Texto actualizado')
            ->call('guardarDiagnostico')
            ->set('motivoTexto', 'Actualización tras revisión')
            ->call('confirmarCambioConMotivo')
            ->assertSet('modalMotivoAbierto', false);

        $this->assertDatabaseHas('plan_cambios', [
            'plan_id' => $plan->id,
            'motivo'  => 'Actualización tras revisión',
        ]);
    }

    // TF-PP-10: eliminarFichaDiagnostico en borrador elimina sin modal
    public function test_eliminar_ficha_borrador_sin_modal(): void
    {
        [, $plan] = $this->montarPlan();
        $ficha = Ficha::factory()->create();
        PlanFichaDiagnostico::create(['plan_id' => $plan->id, 'ficha_id' => $ficha->id]);

        Livewire::test(PlanPage::class, ['plan' => $plan])
            ->call('eliminarFichaDiagnostico', $ficha->id)
            ->assertSet('modalMotivoAbierto', false);

        $this->assertDatabaseMissing('plan_fichas_diagnostico', [
            'plan_id'  => $plan->id,
            'ficha_id' => $ficha->id,
        ]);
    }

    // TF-PP-11: eliminarFichaDiagnostico en plan activo pide motivo
    public function test_eliminar_ficha_activo_pide_motivo(): void
    {
        [, $plan] = $this->montarPlan('activo');
        $ficha = Ficha::factory()->create();
        PlanFichaDiagnostico::create(['plan_id' => $plan->id, 'ficha_id' => $ficha->id]);

        Livewire::test(PlanPage::class, ['plan' => $plan])
            ->call('eliminarFichaDiagnostico', $ficha->id)
            ->assertSet('modalMotivoAbierto', true);
    }

    // TF-PP-12: guardarSeguimiento persiste periodicidad
    public function test_guardar_seguimiento(): void
    {
        [, $plan] = $this->montarPlan();

        Livewire::test(PlanPage::class, ['plan' => $plan])
            ->set('periodicidadSeguimiento', 'semestral')
            ->call('guardarSeguimiento');

        $this->assertEquals('semestral', $plan->fresh()->periodicidad_seguimiento);
    }

    // TF-PP-13: cancelarCambio cierra modal sin persistir
    public function test_cancelar_cambio_no_persiste(): void
    {
        [, $plan] = $this->montarPlan('activo');
        $textoOriginal = $plan->diagnostico_social;

        Livewire::test(PlanPage::class, ['plan' => $plan])
            ->set('diagnosticoTexto', 'Texto que no debe guardarse')
            ->call('guardarDiagnostico')
            ->call('cancelarCambio')
            ->assertSet('modalMotivoAbierto', false);

        $this->assertEquals($textoOriginal, $plan->fresh()->diagnostico_social);
    }
}
```

---

## Paso 8 — Copiar `ui-intervencion-plan.md` al repositorio

```bash
cp /mnt/user-data/outputs/ui-intervencion-plan.md docs/front/ui-intervencion-plan.md
```

---

## Checklist de verificación

- [ ] `php artisan migrate` — migración `plan_fichas_diagnostico` ejecutada
- [ ] `php artisan test --filter=PlanPageTest` — 13 tests en verde
- [ ] Tests anteriores TF-PLAN y TF-INT sin regresiones
- [ ] La ruta `/intervencion/plan/{plan}` renderiza la página
- [ ] El botón "Ver Plan" en `CiudadanoPage` navega a `PlanPage`
- [ ] El drawer se abre y cierra con botón y con Escape
- [ ] El modal de motivo aparece al intentar modificar un plan activo
- [ ] El modal no aparece al modificar un plan en borrador
- [ ] `Activar plan` queda deshabilitado hasta marcar ambas firmas
- [ ] Al activar, el plan pasa a estado `activo` en BD
- [ ] El PDF se descarga al pulsar "Generar PDF"
- [ ] Los iconos Lucide se renderizan tras re-renders de Livewire
- [ ] `docs/front/ui-intervencion-plan.md` copiado al repositorio
- [ ] BACKLOG: eliminar entrada "UI del Plan de Intervención en CiudadanoPage"
- [ ] SESSION.md actualizado
- [ ] Commit: `feat(intervencion): PlanPage — UI completa del plan de intervención`
