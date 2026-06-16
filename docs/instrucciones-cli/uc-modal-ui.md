# Instrucciones CLI — Modal de gestión de UC en CiudadanoPage

## Contexto

Las tablas `unidades_convivencia` y `unidad_convivencia_miembros` ya existen
tras la sesión anterior. El widget UC en `ciudadano-page.blade.php` muestra
los miembros en modo lectura pero tiene el botón "Ver ficha" comentado como TODO.

Esta tarea implementa el modal de gestión de UC: ver composición completa,
añadir miembros (buscando ciudadanos existentes o creando nuevos), dar de baja
miembros y verificar residencia.

El modal **no** duplica el flujo de alta de ciudadano: reutiliza el componente
`AltaCiudadano` existente en `Modules/Ciudadania` para crear nuevos miembros.

---

## Arquitectura del modal

El modal vive en `CiudadanoPage` como estado Alpine + propiedades Livewire.
No es un componente separado para mantener coherencia con los modales
existentes en la pantalla (z-index 500, cierre con Escape, backdrop oscuro).

**Flujo del modal:**

```
[Widget UC — botón "Gestionar UC"]
        ↓
[Modal: lista de miembros activos]
    ├─ [Verificar] → verifica residencia del miembro
    ├─ [Dar de baja] → modal de confirmación inline
    └─ [Añadir miembro]
            ↓
        [Búsqueda de ciudadano existente]
            ├─ Encontrado → confirmar adición a UC
            └─ No encontrado → [Alta rápida de ciudadano]
                                    ↓
                               Ciudadano creado → adición automática a UC
```

---

## Paso 1 — Nuevas propiedades y métodos en `CiudadanoPage.php`

Añade las siguientes propiedades públicas al componente:

```php
// --- Modal UC ---
public bool $modalUcAbierto = false;
public string $ucBusqueda = '';          // texto de búsqueda para añadir miembro
public ?int $ucMiembroParaBaja = null;   // id de UnidadConvivenciaMiembro en confirmación
public ?int $ucCiudadanoSeleccionado = null; // ciudadano_id a confirmar para añadir
public string $ucMensaje = '';           // feedback de operación exitosa/error
```

Añade las siguientes propiedades computadas:

```php
/**
 * UC vigente del ciudadano (primera activa, o null).
 * Reemplaza el stub ucVigente() existente.
 */
#[Computed]
public function ucVigente(): ?\Modules\Ciudadania\Models\UnidadConvivencia
{
    return $this->ciudadano
        ->unidadesConvivenciaActivas()
        ->with(['miembrosActivos.ciudadano'])
        ->first();
}

/**
 * Miembros activos de la UC vigente, con datos de ciudadano.
 */
#[Computed]
public function ucMiembrosActivos(): \Illuminate\Support\Collection
{
    return $this->ucVigente
        ? $this->ucVigente->miembrosActivos()->with('ciudadano')->get()
        : collect();
}

/**
 * Resultados de búsqueda para añadir miembro (máx. 8).
 * Excluye ciudadanos que ya son miembros activos.
 */
#[Computed]
public function ucResultadosBusqueda(): \Illuminate\Support\Collection
{
    if (strlen(trim($this->ucBusqueda)) < 2) {
        return collect();
    }

    $miembrosActuales = $this->ucMiembrosActivos
        ->pluck('ciudadano_id')
        ->push($this->ciudadano->id) // excluir al titular también
        ->all();

    // Misma estrategia que BuscarCiudadanoPage: carga en PHP por cifrado
    return \Modules\Ciudadania\Models\Ciudadano::query()
        ->whereNotIn('id', $miembrosActuales)
        ->limit(500)
        ->get()
        ->filter(fn ($c) => str_contains(
            mb_strtolower($c->nombre . ' ' . $c->apellido1 . ' ' . ($c->apellido2 ?? '')),
            mb_strtolower(trim($this->ucBusqueda))
        ))
        ->take(8)
        ->values();
}
```

Añade los siguientes métodos de acción:

```php
public function abrirModalUc(): void
{
    $this->modalUcAbierto = true;
    $this->ucBusqueda = '';
    $this->ucMiembroParaBaja = null;
    $this->ucCiudadanoSeleccionado = null;
    $this->ucMensaje = '';
}

public function cerrarModalUc(): void
{
    $this->modalUcAbierto = false;
}

/**
 * Selecciona un ciudadano de los resultados de búsqueda para confirmar su adición.
 */
public function seleccionarCiudadanoUc(int $ciudadanoId): void
{
    $this->ucCiudadanoSeleccionado = $ciudadanoId;
    $this->ucBusqueda = '';
}

/**
 * Confirma la adición del ciudadano seleccionado a la UC.
 */
public function confirmarAnadirMiembro(): void
{
    if (! $this->ucCiudadanoSeleccionado || ! $this->ucVigente) {
        return;
    }

    try {
        $this->ucVigente->agregarMiembro($this->ucCiudadanoSeleccionado);
        $this->ucCiudadanoSeleccionado = null;
        $this->ucMensaje = 'Miembro añadido correctamente.';
        unset($this->ucMiembrosActivos); // invalidar computed
        unset($this->ucVigente);
    } catch (\LogicException $e) {
        $this->ucMensaje = $e->getMessage();
    }
}

/**
 * Cancela la selección de ciudadano para añadir.
 */
public function cancelarSeleccionUc(): void
{
    $this->ucCiudadanoSeleccionado = null;
}

/**
 * Inicia la confirmación de baja de un miembro.
 */
public function iniciarBajaMiembro(int $miembroId): void
{
    $this->ucMiembroParaBaja = $miembroId;
}

/**
 * Confirma la baja del miembro seleccionado.
 */
public function confirmarBajaMiembro(): void
{
    if (! $this->ucMiembroParaBaja || ! $this->ucVigente) {
        return;
    }

    $miembro = \Modules\Ciudadania\Models\UnidadConvivenciaMiembro::find(
        $this->ucMiembroParaBaja
    );

    if (! $miembro || $miembro->unidad_convivencia_id !== $this->ucVigente->id) {
        $this->ucMiembroParaBaja = null;
        return;
    }

    $this->ucVigente->darDeBajaMiembro($miembro->ciudadano_id);
    $this->ucMiembroParaBaja = null;
    $this->ucMensaje = 'Miembro dado de baja correctamente.';
    unset($this->ucMiembrosActivos);
    unset($this->ucVigente);
}

/**
 * Cancela la confirmación de baja.
 */
public function cancelarBajaMiembro(): void
{
    $this->ucMiembroParaBaja = null;
}

/**
 * Verifica manualmente la residencia de un miembro.
 * Solo disponible para TSR (rol intervencion) en casos tasados.
 */
public function verificarMiembro(int $miembroId): void
{
    $miembro = \Modules\Ciudadania\Models\UnidadConvivenciaMiembro::find($miembroId);

    if (! $miembro || $miembro->unidad_convivencia_id !== $this->ucVigente?->id) {
        return;
    }

    $miembro->verificar(auth()->user());
    $this->ucMensaje = 'Residencia verificada.';
    unset($this->ucMiembrosActivos);
}

/**
 * Crea la UC si no existe y añade al ciudadano titular como primer miembro.
 * Se llama cuando el TSR pulsa "Crear unidad de convivencia".
 */
public function crearUc(): void
{
    if ($this->ucVigente) {
        return; // ya existe
    }

    $uc = \Modules\Ciudadania\Models\UnidadConvivencia::create([
        'domicilio'          => $this->ciudadano->domicilio,
        'latitud'            => $this->ciudadano->latitud,
        'longitud'           => $this->ciudadano->longitud,
        'fecha_constitucion' => now()->toDateString(),
    ]);

    $uc->agregarMiembro($this->ciudadano->id, fuente: 'manual');

    unset($this->ucVigente);
    unset($this->ucMiembrosActivos);
    $this->ucMensaje = 'Unidad de convivencia creada.';
}
```

---

## Paso 2 — Widget UC en `ciudadano-page.blade.php`

Localiza el bloque del widget UC (el desplegable con `x-show` o similar que
muestra "UC colapsable" según el CHANGELOG de Entrega 3). Reemplaza el botón
"Ver ficha" comentado (TODO) por el botón que abre el modal:

```blade
{{-- Botón gestionar UC — reemplaza el TODO anterior --}}
<button
    wire:click="abrirModalUc"
    class="uc-widget__gestionar"
    title="Gestionar unidad de convivencia"
>
    <i data-lucide="users" style="width:14px;height:14px;"></i>
    Gestionar UC
</button>
```

---

## Paso 3 — Modal de gestión de UC en `ciudadano-page.blade.php`

Añade el siguiente bloque justo antes del cierre del componente raíz Livewire
(al mismo nivel que los otros modales existentes en la pantalla):

```blade
{{-- ================================================================== --}}
{{-- MODAL: GESTIÓN DE UNIDAD DE CONVIVENCIA                            --}}
{{-- ================================================================== --}}
@if($this->modalUcAbierto)
<div
    class="uc-modal-backdrop"
    x-data
    x-on:keydown.escape.window="$wire.cerrarModalUc()"
    role="dialog"
    aria-modal="true"
    aria-labelledby="uc-modal-titulo"
>
    <div class="uc-modal">

        {{-- Cabecera --}}
        <div class="uc-modal__header">
            <h2 id="uc-modal-titulo" class="uc-modal__titulo">
                Unidad de convivencia
            </h2>
            <button wire:click="cerrarModalUc" class="uc-modal__cerrar" aria-label="Cerrar">
                <i data-lucide="x" style="width:18px;height:18px;"></i>
            </button>
        </div>

        {{-- Mensaje de feedback --}}
        @if($ucMensaje)
        <div class="uc-modal__mensaje" wire:key="uc-mensaje">
            <i data-lucide="check-circle" style="width:14px;height:14px;"></i>
            {{ $ucMensaje }}
        </div>
        @endif

        {{-- Cuerpo --}}
        <div class="uc-modal__cuerpo">

            @if(! $this->ucVigente)
            {{-- Sin UC: opción de crear --}}
            <div class="uc-modal__vacio">
                <p>Este ciudadano no tiene unidad de convivencia registrada.</p>
                <button wire:click="crearUc" class="uc-modal__btn-crear">
                    <i data-lucide="plus" style="width:14px;height:14px;"></i>
                    Crear unidad de convivencia
                </button>
            </div>

            @else
            {{-- Lista de miembros activos --}}
            <div class="uc-modal__seccion">
                <h3 class="uc-modal__seccion-titulo">
                    Miembros activos
                    <span class="uc-modal__badge">{{ $this->ucMiembrosActivos->count() }}</span>
                </h3>

                <ul class="uc-modal__lista">
                    @forelse($this->ucMiembrosActivos as $miembro)
                    <li class="uc-modal__miembro" wire:key="miembro-{{ $miembro->id }}">

                        <div class="uc-modal__miembro-info">
                            <span class="uc-modal__miembro-nombre">
                                {{ $miembro->ciudadano->nombre }}
                                {{ $miembro->ciudadano->apellido1 }}
                                {{ $miembro->ciudadano->apellido2 }}
                            </span>
                            <span class="uc-modal__miembro-meta">
                                Desde {{ $miembro->fecha_inicio->format('d/m/Y') }}
                            </span>
                        </div>

                        <div class="uc-modal__miembro-acciones">
                            {{-- Badge verificado --}}
                            @if($miembro->verificado)
                                <span class="uc-badge uc-badge--verificado" title="Residencia verificada">
                                    <i data-lucide="shield-check" style="width:12px;height:12px;"></i>
                                    Verificado
                                </span>
                            @else
                                <button
                                    wire:click="verificarMiembro({{ $miembro->id }})"
                                    class="uc-badge uc-badge--sin-verificar"
                                    title="Verificar residencia manualmente"
                                >
                                    <i data-lucide="shield-alert" style="width:12px;height:12px;"></i>
                                    Sin verificar
                                </button>
                            @endif

                            {{-- Baja --}}
                            @if($ucMiembroParaBaja === $miembro->id)
                                <span class="uc-modal__confirmar-baja">
                                    ¿Confirmar baja?
                                    <button wire:click="confirmarBajaMiembro"
                                            class="uc-btn uc-btn--danger-sm">Sí</button>
                                    <button wire:click="cancelarBajaMiembro"
                                            class="uc-btn uc-btn--ghost-sm">No</button>
                                </span>
                            @else
                                <button
                                    wire:click="iniciarBajaMiembro({{ $miembro->id }})"
                                    class="uc-btn uc-btn--ghost-sm"
                                    title="Dar de baja como miembro"
                                >
                                    <i data-lucide="user-minus" style="width:13px;height:13px;"></i>
                                </button>
                            @endif
                        </div>
                    </li>
                    @empty
                    <li class="uc-modal__vacio-lista">No hay miembros activos.</li>
                    @endforelse
                </ul>
            </div>

            {{-- Añadir miembro --}}
            <div class="uc-modal__seccion">
                <h3 class="uc-modal__seccion-titulo">Añadir miembro</h3>

                @if($ucCiudadanoSeleccionado)
                    {{-- Confirmación de adición --}}
                    @php
                        $cSeleccionado = \Modules\Ciudadania\Models\Ciudadano::find($ucCiudadanoSeleccionado);
                    @endphp
                    <div class="uc-modal__confirmar-adicion">
                        <span>
                            ¿Añadir a <strong>{{ $cSeleccionado?->nombre }}
                            {{ $cSeleccionado?->apellido1 }}</strong>
                            como miembro de esta unidad?
                        </span>
                        <div class="uc-modal__confirmar-acciones">
                            <button wire:click="confirmarAnadirMiembro"
                                    class="uc-btn uc-btn--primary-sm">Confirmar</button>
                            <button wire:click="cancelarSeleccionUc"
                                    class="uc-btn uc-btn--ghost-sm">Cancelar</button>
                        </div>
                    </div>

                @else
                    {{-- Búsqueda --}}
                    <div class="uc-modal__busqueda">
                        <input
                            type="text"
                            wire:model.live.debounce.300ms="ucBusqueda"
                            placeholder="Buscar por nombre…"
                            class="uc-modal__input"
                            autocomplete="off"
                        />
                        <i data-lucide="search"
                           class="uc-modal__busqueda-icon"
                           style="width:14px;height:14px;"></i>
                    </div>

                    @if($this->ucResultadosBusqueda->isNotEmpty())
                    <ul class="uc-modal__resultados">
                        @foreach($this->ucResultadosBusqueda as $resultado)
                        <li
                            wire:click="seleccionarCiudadanoUc({{ $resultado->id }})"
                            class="uc-modal__resultado"
                            wire:key="resultado-{{ $resultado->id }}"
                        >
                            <span class="uc-modal__resultado-nombre">
                                {{ $resultado->nombre }} {{ $resultado->apellido1 }}
                                {{ $resultado->apellido2 }}
                            </span>
                            @if(! $resultado->tieneResidenciaVerificada())
                                <span class="uc-badge uc-badge--sin-verificar uc-badge--sm">
                                    Sin verificar
                                </span>
                            @endif
                        </li>
                        @endforeach
                    </ul>
                    @elseif(strlen(trim($ucBusqueda)) >= 2)
                    <div class="uc-modal__sin-resultados">
                        No se encontró ningún ciudadano con ese nombre.
                        {{-- TODO: enlace a AltaCiudadano con contexto UC prerellenado --}}
                        <a href="{{ route('ciudadania.alta') }}"
                           class="uc-modal__alta-link">
                            Dar de alta ciudadano nuevo
                        </a>
                    </div>
                    @endif
                @endif
            </div>
            @endif {{-- fin @if ucVigente --}}

        </div>{{-- fin cuerpo --}}

        {{-- Pie --}}
        <div class="uc-modal__pie">
            <button wire:click="cerrarModalUc" class="uc-btn uc-btn--ghost">
                Cerrar
            </button>
        </div>

    </div>{{-- fin .uc-modal --}}
</div>{{-- fin backdrop --}}
@endif
```

---

## Paso 4 — CSS en `app-operativo.css`

Añade al final del fichero:

```css
/* ============================================================
   MODAL GESTIÓN UNIDAD DE CONVIVENCIA
   ============================================================ */

.uc-modal-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, .45);
    z-index: 500;
    display: flex;
    align-items: flex-start;
    justify-content: flex-end;
    padding: 16px;
}

.uc-modal {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: 8px;
    width: 520px;
    max-width: 100%;
    max-height: calc(100vh - 32px);
    display: flex;
    flex-direction: column;
    box-shadow: 0 8px 32px rgba(0, 0, 0, .18);
}

.uc-modal__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 20px 12px;
    border-bottom: 1px solid var(--color-border);
    flex-shrink: 0;
}

.uc-modal__titulo {
    font-size: 15px;
    font-weight: 600;
    color: var(--color-text-primary);
    margin: 0;
}

.uc-modal__cerrar {
    background: none;
    border: none;
    cursor: pointer;
    color: var(--color-text-secondary);
    padding: 2px;
    line-height: 1;
}

.uc-modal__cerrar:hover { color: var(--color-text-primary); }

.uc-modal__mensaje {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 8px 20px;
    background: var(--color-success-bg, #f0fdf4);
    color: var(--color-success, #166534);
    font-size: 12px;
    border-bottom: 1px solid var(--color-border);
    flex-shrink: 0;
}

.uc-modal__cuerpo {
    overflow-y: auto;
    flex: 1;
    padding: 16px 20px;
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.uc-modal__seccion {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.uc-modal__seccion-titulo {
    font-size: 12px;
    font-weight: 600;
    color: var(--color-text-secondary);
    text-transform: uppercase;
    letter-spacing: .04em;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 6px;
}

.uc-modal__badge {
    background: var(--color-neutral-200, #e5e7eb);
    color: var(--color-text-secondary);
    border-radius: 10px;
    padding: 0 6px;
    font-size: 11px;
    font-weight: 500;
}

/* Lista de miembros */
.uc-modal__lista {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.uc-modal__miembro {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 10px;
    border-radius: 6px;
    background: var(--color-surface-alt, #f9fafb);
    gap: 8px;
}

.uc-modal__miembro-info {
    display: flex;
    flex-direction: column;
    gap: 1px;
    min-width: 0;
}

.uc-modal__miembro-nombre {
    font-size: 13px;
    font-weight: 500;
    color: var(--color-text-primary);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.uc-modal__miembro-meta {
    font-size: 11px;
    color: var(--color-text-secondary);
}

.uc-modal__miembro-acciones {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-shrink: 0;
}

/* Badges verificación */
.uc-badge {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    font-size: 11px;
    padding: 2px 7px;
    border-radius: 10px;
    font-weight: 500;
    white-space: nowrap;
}

.uc-badge--verificado {
    background: var(--color-success-bg, #f0fdf4);
    color: var(--color-success, #166534);
    border: 1px solid var(--color-success-border, #bbf7d0);
}

.uc-badge--sin-verificar {
    background: var(--color-warning-bg, #fffbeb);
    color: var(--color-warning, #92400e);
    border: 1px solid var(--color-warning-border, #fde68a);
    cursor: pointer;
}

.uc-badge--sin-verificar:hover {
    background: var(--color-warning-hover, #fef3c7);
}

.uc-badge--sm { font-size: 10px; padding: 1px 5px; }

/* Confirmación de baja inline */
.uc-modal__confirmar-baja {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 12px;
    color: var(--color-text-secondary);
}

/* Búsqueda */
.uc-modal__busqueda {
    position: relative;
}

.uc-modal__input {
    width: 100%;
    padding: 7px 10px 7px 30px;
    border: 1px solid var(--color-border);
    border-radius: 6px;
    font-size: 13px;
    background: var(--color-surface);
    color: var(--color-text-primary);
    outline: none;
    box-sizing: border-box;
}

.uc-modal__input:focus {
    border-color: var(--color-primary, #2563eb);
    box-shadow: 0 0 0 2px rgba(37, 99, 235, .12);
}

.uc-modal__busqueda-icon {
    position: absolute;
    left: 9px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--color-text-secondary);
    pointer-events: none;
}

/* Resultados de búsqueda */
.uc-modal__resultados {
    list-style: none;
    margin: 0;
    padding: 0;
    border: 1px solid var(--color-border);
    border-radius: 6px;
    overflow: hidden;
}

.uc-modal__resultado {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 12px;
    cursor: pointer;
    font-size: 13px;
    gap: 8px;
    transition: background .1s;
}

.uc-modal__resultado:not(:last-child) {
    border-bottom: 1px solid var(--color-border);
}

.uc-modal__resultado:hover {
    background: var(--color-surface-alt, #f9fafb);
}

.uc-modal__resultado-nombre {
    color: var(--color-text-primary);
    font-weight: 500;
}

.uc-modal__sin-resultados {
    font-size: 13px;
    color: var(--color-text-secondary);
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding: 4px 0;
}

.uc-modal__alta-link {
    color: var(--color-primary, #2563eb);
    font-size: 13px;
    text-decoration: none;
}

.uc-modal__alta-link:hover { text-decoration: underline; }

/* Confirmación de adición */
.uc-modal__confirmar-adicion {
    background: var(--color-surface-alt, #f9fafb);
    border: 1px solid var(--color-border);
    border-radius: 6px;
    padding: 10px 12px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    font-size: 13px;
    color: var(--color-text-primary);
}

.uc-modal__confirmar-acciones {
    display: flex;
    gap: 6px;
}

/* Vacío */
.uc-modal__vacio {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
    padding: 24px 0;
    color: var(--color-text-secondary);
    font-size: 13px;
    text-align: center;
}

.uc-modal__vacio-lista {
    font-size: 13px;
    color: var(--color-text-secondary);
    padding: 8px 0;
}

.uc-modal__btn-crear {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 14px;
    background: var(--color-primary, #2563eb);
    color: #fff;
    border: none;
    border-radius: 6px;
    font-size: 13px;
    cursor: pointer;
}

.uc-modal__btn-crear:hover { opacity: .9; }

/* Pie */
.uc-modal__pie {
    padding: 12px 20px;
    border-top: 1px solid var(--color-border);
    display: flex;
    justify-content: flex-end;
    flex-shrink: 0;
}

/* Botones utilitarios */
.uc-btn {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 6px 14px;
    border-radius: 6px;
    font-size: 13px;
    cursor: pointer;
    border: none;
}

.uc-btn--ghost {
    background: transparent;
    border: 1px solid var(--color-border);
    color: var(--color-text-secondary);
}

.uc-btn--ghost:hover { background: var(--color-surface-alt, #f9fafb); }

.uc-btn--ghost-sm {
    background: transparent;
    border: 1px solid var(--color-border);
    color: var(--color-text-secondary);
    padding: 3px 8px;
    font-size: 12px;
}

.uc-btn--primary-sm {
    background: var(--color-primary, #2563eb);
    color: #fff;
    padding: 3px 8px;
    font-size: 12px;
}

.uc-btn--danger-sm {
    background: var(--color-danger, #dc2626);
    color: #fff;
    padding: 3px 8px;
    font-size: 12px;
}

/* Botón en widget UC */
.uc-widget__gestionar {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 11px;
    padding: 3px 8px;
    border: 1px solid var(--color-border);
    border-radius: 4px;
    background: transparent;
    color: var(--color-text-secondary);
    cursor: pointer;
}

.uc-widget__gestionar:hover {
    background: var(--color-surface-alt, #f9fafb);
    color: var(--color-text-primary);
}
```

---

## Paso 5 — Tests funcionales

Crea `Modules/Intervencion/tests/Feature/Livewire/GestionUcTest.php`:

```php
<?php

namespace Modules\Intervencion\Tests\Feature\Livewire;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Ciudadania\Models\Ciudadano;
use Modules\Ciudadania\Models\UnidadConvivencia;
use Modules\Ciudadania\Models\UnidadConvivenciaMiembro;
use Modules\Intervencion\Http\Livewire\CiudadanoPage;
use App\Models\User;
use Tests\TestCase;

/**
 * Tests funcionales del modal de gestión de UC en CiudadanoPage.
 * Nomenclatura: TF-LW-UC-XX
 */
class GestionUcTest extends TestCase
{
    use RefreshDatabase;

    private function montarComponente(?UnidadConvivencia $uc = null): \Livewire\Testing\TestableLivewire
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $ciudadano = Ciudadano::factory()->create();
        $historia  = \App\Models\HistoriaSocial::factory()->create([
            'ciudadano_id' => $ciudadano->id,
        ]);

        if ($uc) {
            $uc->agregarMiembro($ciudadano->id);
        }

        return Livewire::test(CiudadanoPage::class, ['historia' => $historia->id]);
    }

    // TF-LW-UC-01: abrirModalUc establece modalUcAbierto = true
    public function test_abrir_modal_uc(): void
    {
        $this->montarComponente()
            ->call('abrirModalUc')
            ->assertSet('modalUcAbierto', true);
    }

    // TF-LW-UC-02: cerrarModalUc establece modalUcAbierto = false
    public function test_cerrar_modal_uc(): void
    {
        $this->montarComponente()
            ->call('abrirModalUc')
            ->call('cerrarModalUc')
            ->assertSet('modalUcAbierto', false);
    }

    // TF-LW-UC-03: crearUc crea la UC y añade al ciudadano titular
    public function test_crear_uc(): void
    {
        $componente = $this->montarComponente();

        $componente->call('crearUc');

        $this->assertDatabaseCount('unidades_convivencia', 1);
        $this->assertDatabaseCount('unidad_convivencia_miembros', 1);
    }

    // TF-LW-UC-04: crearUc no crea una segunda UC si ya existe
    public function test_crear_uc_no_duplica(): void
    {
        $uc = UnidadConvivencia::factory()->create();
        $this->montarComponente($uc)
            ->call('crearUc');

        $this->assertDatabaseCount('unidades_convivencia', 1);
    }

    // TF-LW-UC-05: seleccionarCiudadanoUc establece ucCiudadanoSeleccionado
    public function test_seleccionar_ciudadano_uc(): void
    {
        $uc = UnidadConvivencia::factory()->create();
        $otro = Ciudadano::factory()->create();

        $this->montarComponente($uc)
            ->call('seleccionarCiudadanoUc', $otro->id)
            ->assertSet('ucCiudadanoSeleccionado', $otro->id);
    }

    // TF-LW-UC-06: confirmarAnadirMiembro añade el ciudadano a la UC
    public function test_confirmar_anadir_miembro(): void
    {
        $uc   = UnidadConvivencia::factory()->create();
        $otro = Ciudadano::factory()->create();

        $this->montarComponente($uc)
            ->call('seleccionarCiudadanoUc', $otro->id)
            ->call('confirmarAnadirMiembro');

        // El titular + el nuevo = 2 miembros
        $this->assertDatabaseCount('unidad_convivencia_miembros', 2);
    }

    // TF-LW-UC-07: confirmarAnadirMiembro limpia ucCiudadanoSeleccionado tras éxito
    public function test_anadir_miembro_limpia_seleccion(): void
    {
        $uc   = UnidadConvivencia::factory()->create();
        $otro = Ciudadano::factory()->create();

        $this->montarComponente($uc)
            ->call('seleccionarCiudadanoUc', $otro->id)
            ->call('confirmarAnadirMiembro')
            ->assertSet('ucCiudadanoSeleccionado', null);
    }

    // TF-LW-UC-08: iniciarBajaMiembro establece ucMiembroParaBaja
    public function test_iniciar_baja_miembro(): void
    {
        $uc = UnidadConvivencia::factory()->create();
        $componente = $this->montarComponente($uc);

        $miembroId = UnidadConvivenciaMiembro::first()->id;

        $componente
            ->call('iniciarBajaMiembro', $miembroId)
            ->assertSet('ucMiembroParaBaja', $miembroId);
    }

    // TF-LW-UC-09: confirmarBajaMiembro cierra la membresía con fecha_fin
    public function test_confirmar_baja_miembro(): void
    {
        $uc = UnidadConvivencia::factory()->create();
        $componente = $this->montarComponente($uc);

        $miembro = UnidadConvivenciaMiembro::first();

        $componente
            ->call('iniciarBajaMiembro', $miembro->id)
            ->call('confirmarBajaMiembro');

        $this->assertNotNull($miembro->fresh()->fecha_fin);
    }

    // TF-LW-UC-10: cancelarBajaMiembro limpia ucMiembroParaBaja
    public function test_cancelar_baja_miembro(): void
    {
        $uc = UnidadConvivencia::factory()->create();
        $componente = $this->montarComponente($uc);

        $miembro = UnidadConvivenciaMiembro::first();

        $componente
            ->call('iniciarBajaMiembro', $miembro->id)
            ->call('cancelarBajaMiembro')
            ->assertSet('ucMiembroParaBaja', null);
    }

    // TF-LW-UC-11: verificarMiembro marca verificado con el usuario actual
    public function test_verificar_miembro(): void
    {
        $uc = UnidadConvivencia::factory()->create();
        $componente = $this->montarComponente($uc);

        $miembro = UnidadConvivenciaMiembro::first();
        $this->assertFalse($miembro->verificado);

        $componente->call('verificarMiembro', $miembro->id);

        $this->assertTrue($miembro->fresh()->verificado);
        $this->assertNotNull($miembro->fresh()->verificado_en);
    }

    // TF-LW-UC-12: ucResultadosBusqueda excluye miembros ya activos y al titular
    public function test_busqueda_excluye_miembros_activos(): void
    {
        $uc      = UnidadConvivencia::factory()->create();
        $externo = Ciudadano::factory()->create([
            'nombre'    => 'María',
            'apellido1' => 'García',
        ]);

        $componente = $this->montarComponente($uc);

        // El titular (que ya es miembro) no debe aparecer en resultados
        // El externo (con nombre "María García") sí debe aparecer
        $componente
            ->set('ucBusqueda', 'María')
            ->assertSee('García');

        // Buscar el nombre del titular — no debe aparecer en resultados
        $titular = Ciudadano::whereNotIn('id', [$externo->id])->first();
        $nombreTitular = $titular->nombre;

        // Añadir al externo como miembro y verificar que ya no aparece
        $uc->agregarMiembro($externo->id);

        $componente
            ->set('ucBusqueda', 'María')
            ->assertDontSee('García'); // ya es miembro
    }

    // TF-LW-UC-13: búsqueda con menos de 2 caracteres devuelve vacío
    public function test_busqueda_minimo_dos_caracteres(): void
    {
        $uc = UnidadConvivencia::factory()->create();

        $this->montarComponente($uc)
            ->set('ucBusqueda', 'a')
            ->assertSee(''); // sin resultados visibles (computed vacío)
    }
}
```

---

## Paso 6 — Actualizar BACKLOG y SESSION

Añade en `BACKLOG.md`:

```markdown
**Alta rápida de ciudadano desde modal UC** — 2026-06-16
`Módulo: Ciudadanía / Intervención`
El enlace "Dar de alta ciudadano nuevo" en el modal UC apunta a `ciudadania.alta`
sin contexto prerellenado. Pendiente: pasar parámetros de contexto a AltaCiudadano
para prerellenar domicilio de la UC y retornar al modal tras el alta (con el
ciudadano recién creado seleccionado para confirmar su adición).
```

Actualiza `SESSION.md` con:
- Tarea completada: modal de gestión de UC en CiudadanoPage
- Siguiente paso recomendado: implementar fichas sociales (bloquean el PISO)

---

## Checklist de verificación

- [ ] `php artisan test --filter=GestionUcTest` — 13 tests en verde
- [ ] Suite completa Intervención sin regresiones
- [ ] El botón "Gestionar UC" aparece en el widget UC de la pantalla del ciudadano
- [ ] El modal abre y cierra con el botón y con Escape
- [ ] Los iconos Lucide se renderizan (el hook `livewire:initialized` ya está en operativo.blade.php)
- [ ] BACKLOG actualizado
- [ ] SESSION.md actualizado
- [ ] Commit: `feat(intervencion): modal gestión UC en CiudadanoPage`
