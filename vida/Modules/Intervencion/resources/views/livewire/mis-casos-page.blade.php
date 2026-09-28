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

    $claseSeguimiento = [
        'vencido'    => 'bg-danger-subtle text-danger-emphasis',
        'proximo'    => 'bg-warning-subtle text-warning-emphasis',
        'programado' => 'bg-success-subtle text-success-emphasis',
        'sin'        => 'text-body-secondary fw-normal',
    ];

    $nombrePlan = $this->nombrePlanAsp();

    // Columnas de la tabla: [campo de orden o null si no se ordena, etiqueta]
    $columnas = [
        ['ciudadano', 'Ciudadano/a'],
        ['historia', 'Historia Social'],
        ['seg', 'Proximo seguimiento'],
        [null, $nombrePlan],
        ['esp', 'Especializados'],
        ['inicio', 'Inicio'],
    ];
@endphp

<div class="op-page op-page--fill">
    <section class="bg-white border-bottom px-3 py-3">
        <div class="row g-2 align-items-center">
            <div class="col-12 col-lg-4">
                <div class="input-group input-group-sm">
                    <span class="input-group-text" aria-hidden="true">
                        <x-heroicon-o-magnifying-glass class="icon-14"/>
                    </span>
                    <input
                        wire:model.live.debounce.300ms="busqueda"
                        type="search"
                        class="form-control"
                        placeholder="Buscar por nombre"
                        aria-label="Buscar por nombre"
                        autocomplete="off"
                    >
                </div>
            </div>

            <div class="col-auto">
                <select wire:model.live="filtroSeguimiento" class="form-select form-select-sm" aria-label="Filtrar por seguimiento">
                    <option value="">Todos los seguimientos</option>
                    <option value="vencido">Vencidos</option>
                    <option value="proximo">Proximos (7 dias)</option>
                    <option value="programado">Programados</option>
                    <option value="sin">Sin programar</option>
                </select>
            </div>

            <div class="col-auto">
                <select wire:model.live="filtroPiso" class="form-select form-select-sm" aria-label="Filtrar por {{ $nombrePlan }}">
                    <option value="">Todos los {{ $nombrePlan }}</option>
                    <option value="activo">{{ $nombrePlan }} activo</option>
                    <option value="revision">{{ $nombrePlan }} en revision</option>
                    <option value="sin">Sin {{ $nombrePlan }}</option>
                </select>
            </div>

            <div class="col-auto">
                <select wire:model.live="filtroEsp" class="form-select form-select-sm" aria-label="Filtrar por especializados">
                    <option value="">Con/sin especializados</option>
                    <option value="con">Con derivacion</option>
                    <option value="sin">Sin derivacion</option>
                </select>
            </div>
        </div>
    </section>

    <section class="flex-grow-1 overflow-auto p-3">
        @if($this->casos->isEmpty())
            <p class="text-center text-body-secondary py-5 mb-0">
                No hay casos que coincidan con los filtros seleccionados.
            </p>
        @else
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                @foreach($columnas as [$campo, $etiqueta])
                                    @if($campo === null)
                                        <th scope="col" class="small text-body-secondary">{{ $etiqueta }}</th>
                                    @else
                                        @php $activo = $this->ordenarPor === $campo; @endphp
                                        <th scope="col" @if($activo) aria-sort="{{ $this->direccion === 'asc' ? 'ascending' : 'descending' }}" @endif>
                                            <button wire:click="sortBy('{{ $campo }}')" type="button"
                                                    @class(['btn btn-sm btn-link text-decoration-none p-0', 'fw-bold' => $activo, 'link-secondary' => ! $activo])>
                                                {{ $etiqueta }}
                                                @if($activo)
                                                    <span aria-hidden="true">{{ $this->direccion === 'asc' ? '↑' : '↓' }}</span>
                                                @endif
                                            </button>
                                        </th>
                                    @endif
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($this->casos as $caso)
                                @php
                                    $estado = $estadoSeguimiento($caso->fecha_siguiente_seguimiento);
                                    $nombreCiudadano = $this->ciudadanosDelPage->get($caso->ciudadano_id)?->nombre_completo ?? 'Ciudadano #' . $caso->ciudadano_id;
                                @endphp
                                <tr role="button" onclick="event.target.closest('a') || (window.location.href='{{ route('intervencion.ciudadano.show', $caso->historia_id) }}')">
                                    <td class="fw-semibold">
                                        <a href="{{ route('ciudadania.ciudadano.ficha', $caso->ciudadano_id) }}" class="text-decoration-none">
                                            {{ $nombreCiudadano }}
                                        </a>
                                    </td>
                                    <td class="font-monospace small">
                                        <a href="{{ route('intervencion.ciudadano.show', $caso->historia_id) }}" class="link-secondary text-decoration-none">
                                            HS-{{ str_pad($caso->historia_id, 6, '0', STR_PAD_LEFT) }}
                                        </a>
                                    </td>
                                    <td>
                                        <span class="badge rounded-pill d-inline-flex align-items-center gap-1 {{ $claseSeguimiento[$estado] }}">
                                            @if($estado === 'vencido')
                                                <x-heroicon-o-clock class="icon-13" aria-hidden="true"/>
                                            @endif
                                            @if($caso->fecha_siguiente_seguimiento)
                                                {{ Carbon::parse($caso->fecha_siguiente_seguimiento)->format('d/m/Y') }}
                                            @else
                                                Sin programar
                                            @endif
                                        </span>
                                    </td>
                                    <td>
                                        @if($caso->plan_id === null)
                                            <span class="small text-body-secondary">Sin plan</span>
                                        @elseif($caso->plan_estado === 'activo')
                                            <span class="badge rounded-pill bg-success-subtle text-success-emphasis">Activo</span>
                                        @elseif($caso->plan_estado === 'en_revision')
                                            <span class="badge rounded-pill bg-warning-subtle text-warning-emphasis">En revisión</span>
                                        @else
                                            <span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis">Borrador</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if($caso->planes_esp_count > 0)
                                            <span class="badge rounded-pill bg-primary-subtle text-primary-emphasis">{{ $caso->planes_esp_count }}</span>
                                        @else
                                            <span class="text-body-secondary">—</span>
                                        @endif
                                    </td>
                                    <td class="small text-body-secondary text-nowrap">
                                        {{ Carbon::parse($caso->fecha_inicio)->format('d/m/Y') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <footer class="card-footer d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <span class="small text-body-secondary">{{ $this->casos->firstItem() }}-{{ $this->casos->lastItem() }} de {{ $this->casos->total() }} casos</span>
                    <nav aria-label="Paginacion">
                        <ul class="pagination pagination-sm mb-0">
                            @if($this->casos->onFirstPage())
                                <li class="page-item disabled"><span class="page-link">‹</span></li>
                            @else
                                <li class="page-item"><button wire:click="previousPage" type="button" class="page-link" aria-label="Página anterior">‹</button></li>
                            @endif

                            @foreach(range(1, $this->casos->lastPage()) as $p)
                                <li class="page-item {{ $this->casos->currentPage() === $p ? 'active' : '' }}"><button wire:click="gotoPage({{ $p }})" type="button" class="page-link">{{ $p }}</button></li>
                            @endforeach

                            @if($this->casos->hasMorePages())
                                <li class="page-item"><button wire:click="nextPage" type="button" class="page-link" aria-label="Página siguiente">›</button></li>
                            @else
                                <li class="page-item disabled"><span class="page-link">›</span></li>
                            @endif
                        </ul>
                    </nav>
                </footer>
            </div>
        @endif
    </section>
</div>
