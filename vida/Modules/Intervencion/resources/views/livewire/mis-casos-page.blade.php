@php
    use Carbon\Carbon;
    use Modules\Intervencion\Support\Ui\Tonos;

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


    // Columnas de la tabla: [campo de orden o null si no se ordena, etiqueta]
    $columnas = [
        ['ciudadano', 'Ciudadano/a'],
        ['historia', 'Historia Social'],
        ['seg', 'Próximo seguimiento'],
        [null, 'Planes'],
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
                <select wire:model.live="filtroPlanes" class="form-select form-select-sm" aria-label="Filtrar por planes">
                    <option value="">Todos los planes</option>
                    @foreach($this->tiposPlan as $tipoId => $tipoNombre)
                        <option value="{{ $tipoId }}">Con {{ $tipoNombre }}</option>
                    @endforeach
                    <option value="{{ \Modules\Intervencion\Http\Livewire\MisCasosPage::SIN_PLAN }}">Sin plan en curso</option>
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
                                        <x-op.th-orden :columna="$campo" :actual="$ordenarPor" :direccion="$direccion">{{ $etiqueta }}</x-op.th-orden>
                                    @endif
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($this->casos as $caso)
                                @php
                                    $estado = $estadoSeguimiento($caso->fecha_siguiente_seguimiento);
                                    $persona = $this->personasDelPage[$caso->ciudadano_id] ?? null;
                                    $planes = $this->planesDelPage->get($caso->historia_id, collect());
                                @endphp
                                <tr wire:key="caso-{{ $caso->historia_id }}" role="button" onclick="event.target.closest('a') || (window.location.href='{{ route('intervencion.ciudadano.show', $caso->historia_id) }}')">
                                    <td class="fw-semibold">
                                        @if($persona['url'] ?? null)
                                            <a href="{{ $persona['url'] }}" class="text-decoration-none">{{ $persona['nombre'] }}</a>
                                        @else
                                            {{ $persona['nombre'] ?? '—' }}
                                        @endif
                                    </td>
                                    <td class="font-monospace small">
                                        <a href="{{ route('intervencion.ciudadano.show', $caso->historia_id) }}" class="link-secondary text-decoration-none">
                                            HS-{{ str_pad($caso->historia_id, 6, '0', STR_PAD_LEFT) }}
                                        </a>
                                    </td>
                                    <td>
                                        <span class="badge rounded-pill d-inline-flex align-items-center gap-1 {{ Tonos::estadoSeguimiento($estado)?->clasesSuave() ?? 'text-body-secondary fw-normal' }}">
                                            @if($estado === 'vencido')
                                                <x-heroicon-o-clock class="icon-14" aria-hidden="true"/>
                                            @endif
                                            @if($caso->fecha_siguiente_seguimiento)
                                                {{ Carbon::parse($caso->fecha_siguiente_seguimiento)->format('d/m/Y') }}
                                            @else
                                                Sin programar
                                            @endif
                                        </span>
                                    </td>
                                    <td>
                                        {{-- Todos los planes en curso de la persona, de cualquier centro, por su tipo; el color es su estado --}}
                                        <div class="d-flex flex-wrap gap-1">
                                        @forelse($planes as $plan)
                                            @php
                                                $tituloPlan = collect([$plan->tipoPlan->nombre ?? 'Plan', $plan->estado->label(), $plan->centro->nombre_corto ?? $plan->centro->nombre ?? null])->filter()->implode(' · ');
                                            @endphp
                                            @can('view', $plan)
                                                <a href="{{ route('intervencion.plan.show', $plan->id) }}" class="badge rounded-pill text-decoration-none {{ $plan->estado->tono()->clasesSuave() }}" title="{{ $tituloPlan }}">{{ $plan->tipoPlan->nombre ?? 'Plan' }}</a>
                                            @else
                                                <span class="badge rounded-pill {{ $plan->estado->tono()->clasesSuave() }}" title="{{ $tituloPlan }}">{{ $plan->tipoPlan->nombre ?? 'Plan' }}</span>
                                            @endcan
                                        @empty
                                            <span class="text-body-secondary">—</span>
                                        @endforelse
                                        </div>
                                    </td>
                                    <td class="small text-body-secondary text-nowrap">
                                        {{ Carbon::parse($caso->fecha_inicio)->format('d/m/Y') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <footer class="card-footer">
                    {{-- Con varias páginas, la paginación de Livewire (Bootstrap) ya muestra «Mostrando x a y de N». --}}
                    @if($this->casos->hasPages())
                        {{ $this->casos->onEachSide(1)->links() }}
                    @else
                        <span class="small text-body-secondary">{{ $this->casos->total() }} {{ $this->casos->total() === 1 ? 'caso' : 'casos' }}</span>
                    @endif
                </footer>
            </div>
        @endif
    </section>
</div>
