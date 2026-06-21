@php
    use Carbon\Carbon;

    $hoy = today();

    $estadoSeguimiento = function (?string $fecha) use ($hoy): string {
        if ($fecha === null) {
            return 'sin';
        }

        $f = Carbon::parse($fecha);
        if ($f->lt($hoy)) {
            return 'vencido';
        }
        if ($f->between($hoy, $hoy->copy()->addDays(7))) {
            return 'proximo';
        }

        return 'programado';
    };

    $nombrePlan = $this->nombrePlanAsp();
@endphp

<div class="cases-screen">
    <section class="cases-screen__toolbar">
        <div class="cases-screen__heading">
            <h1 class="cases-screen__title">Intervención - Mis casos</h1>
        </div>

        <div class="cases-screen__filters">
            <label class="cases-screen__search" aria-label="Buscar por nombre">
                <i data-lucide="search" class="icon-14 cases-screen__search-icon" aria-hidden="true"></i>
                <input
                    wire:model.live.debounce.300ms="busqueda"
                    type="search"
                    class="form-control cases-screen__search-input"
                    placeholder="Buscar por nombre"
                    autocomplete="off"
                >
            </label>

            <select wire:model.live="filtroSeguimiento" class="form-select cases-screen__filter">
                <option value="">Todos los seguimientos</option>
                <option value="vencido">Vencidos</option>
                <option value="proximo">Proximos (7 dias)</option>
                <option value="programado">Programados</option>
                <option value="sin">Sin programar</option>
            </select>

            <select wire:model.live="filtroPiso" class="form-select cases-screen__filter">
                <option value="">Todos los {{ $nombrePlan }}</option>
                <option value="activo">{{ $nombrePlan }} activo</option>
                <option value="revision">{{ $nombrePlan }} en revision</option>
                <option value="sin">Sin {{ $nombrePlan }}</option>
            </select>

            <select wire:model.live="filtroEsp" class="form-select cases-screen__filter">
                <option value="">Con/sin especializados</option>
                <option value="con">Con derivacion</option>
                <option value="sin">Sin derivacion</option>
            </select>
        </div>
    </section>

    <section class="cases-screen__surface">
        <div class="cases-screen__content">
            @if($this->casos->isEmpty())
                <div class="cases-screen__empty">
                    No hay casos que coincidan con los filtros seleccionados.
                </div>
            @else
                @php
                    $ordenarPor = $this->ordenarPor;
                    $direccion = $this->direccion;

                    $th = function (string $campo, string $label) use ($ordenarPor, $direccion): string {
                        $activo = $ordenarPor === $campo;
                        $flecha = $activo ? ($direccion === 'asc' ? '↑' : '↓') : '';
                        $clase = $activo ? 'cases-screen__sort cases-screen__sort--active' : 'cases-screen__sort';

                        return '<th class="cases-screen__th">'
                            . '<button wire:click="sortBy(\'' . $campo . '\')" type="button" class="' . $clase . '">'
                            . e($label)
                            . ($flecha ? '<span class="cases-screen__sort-arrow">' . $flecha . '</span>' : '')
                            . '</button>'
                            . '</th>';
                    };

                    $thStatic = fn (string $label): string =>
                        '<th class="cases-screen__th cases-screen__th--static">' . e($label) . '</th>';
                @endphp

                <div class="cases-screen__table-wrap">
                    <table class="cases-screen__table">
                        <thead>
                            <tr class="cases-screen__head-row">
                                {!! $th('ciudadano', 'Ciudadano/a') !!}
                                {!! $th('historia', 'Historia Social') !!}
                                {!! $th('seg', 'Proximo seguimiento') !!}
                                {!! $thStatic($nombrePlan) !!}
                                {!! $th('esp', 'Especializados') !!}
                                {!! $th('inicio', 'Inicio') !!}
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($this->casos as $caso)
                                @php
                                    $estado = $estadoSeguimiento($caso->fecha_siguiente_seguimiento);
                                    $nombreCiudadano = $this->ciudadanosDelPage->get($caso->ciudadano_id)?->nombre_completo ?? 'Ciudadano #' . $caso->ciudadano_id;
                                @endphp
                                <tr class="cases-screen__row" onclick="event.target.closest('a') || (window.location.href='{{ route('intervencion.ciudadano.show', $caso->historia_id) }}')">
                                    <td class="cases-screen__cell cases-screen__cell--strong">
                                        <a href="{{ route('ciudadania.ciudadano.ficha', $caso->ciudadano_id) }}" class="cases-screen__primary-link">
                                            {{ $nombreCiudadano }}
                                        </a>
                                    </td>
                                    <td class="cases-screen__cell cases-screen__cell--mono">
                                        <a href="{{ route('intervencion.ciudadano.show', $caso->historia_id) }}" class="cases-screen__secondary-link">
                                            HS-{{ str_pad($caso->historia_id, 6, '0', STR_PAD_LEFT) }}
                                        </a>
                                    </td>
                                    <td class="cases-screen__cell">
                                        <span class="cases-screen__status-chip cases-screen__status-chip--{{ $estado }}">
                                            @if($estado === 'vencido')
                                                <i data-lucide="clock" class="icon-13" aria-hidden="true"></i>
                                            @endif
                                            @if($caso->fecha_siguiente_seguimiento)
                                                {{ Carbon::parse($caso->fecha_siguiente_seguimiento)->format('d/m/Y') }}
                                            @else
                                                Sin programar
                                            @endif
                                        </span>
                                    </td>
                                    <td class="cases-screen__cell">
                                        <span class="cases-screen__pill cases-screen__pill--success">Activo</span>
                                    </td>
                                    <td class="cases-screen__cell cases-screen__cell--center">
                                        @if($caso->planes_esp_count > 0)
                                            <span class="cases-screen__pill cases-screen__pill--primary">{{ $caso->planes_esp_count }}</span>
                                        @else
                                            <span class="cases-screen__muted">—</span>
                                        @endif
                                    </td>
                                    <td class="cases-screen__cell cases-screen__cell--date">
                                        {{ Carbon::parse($caso->fecha_inicio)->format('d/m/Y') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <footer class="cases-screen__footer">
                    <span class="cases-screen__count">{{ $this->casos->firstItem() }}-{{ $this->casos->lastItem() }} de {{ $this->casos->total() }} casos</span>
                    <div class="cases-screen__pager" aria-label="Paginacion">
                        @if($this->casos->onFirstPage())
                            <span class="cases-screen__pager-item cases-screen__pager-item--disabled">‹</span>
                        @else
                            <button wire:click="previousPage" type="button" class="cases-screen__pager-item">‹</button>
                        @endif

                        @foreach(range(1, $this->casos->lastPage()) as $p)
                            <button wire:click="gotoPage({{ $p }})" type="button" class="cases-screen__pager-item {{ $this->casos->currentPage() === $p ? 'cases-screen__pager-item--active' : '' }}">
                                {{ $p }}
                            </button>
                        @endforeach

                        @if($this->casos->hasMorePages())
                            <button wire:click="nextPage" type="button" class="cases-screen__pager-item">›</button>
                        @else
                            <span class="cases-screen__pager-item cases-screen__pager-item--disabled">›</span>
                        @endif
                    </div>
                </footer>
            @endif
        </div>
    </section>
</div>
