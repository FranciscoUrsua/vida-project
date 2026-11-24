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


    $nombrePlan = $this->nombrePlanAsp();

    // Columnas de la tabla: [campo de orden o null si no se ordena, etiqueta]
    $columnas = [
        ['ciudadano', 'Ciudadano/a'],
        ['historia', 'Historia Social'],
        ['seg', 'Próximo seguimiento'],
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
                                        <x-op.th-orden :columna="$campo" :actual="$ordenarPor" :direccion="$direccion">{{ $etiqueta }}</x-op.th-orden>
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

    {{-- Planes no principales de los que soy responsable (fase 3, A3.6) --}}
    @if($this->otrosPlanes->isNotEmpty())
        <section class="px-3 pb-3" aria-labelledby="titulo-otros-planes">
            <div class="card">
                <header class="card-header bg-transparent">
                    <h2 id="titulo-otros-planes" class="h6 fw-bold mb-0">Otros planes de los que soy responsable</h2>
                </header>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <x-op.th-orden columna="persona" accion="ordenarOtros" :actual="$ordenOtros" :direccion="$direccionOtros">Ciudadano/a</x-op.th-orden>
                                <th scope="col">Plan</th>
                                <th scope="col">Centro</th>
                                <x-op.th-orden columna="estado" accion="ordenarOtros" :actual="$ordenOtros" :direccion="$direccionOtros">Estado</x-op.th-orden>
                                <x-op.th-orden columna="seguimiento" accion="ordenarOtros" :actual="$ordenOtros" :direccion="$direccionOtros">Próximo seguimiento</x-op.th-orden>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($this->otrosPlanes as $fila)
                                <tr wire:key="otro-plan-{{ $fila['id'] }}">
                                    <td><a href="{{ route('intervencion.plan.show', $fila['id']) }}" wire:navigate>{{ $fila['persona'] }}</a></td>
                                    <td>{{ $fila['tipo'] }}</td>
                                    <td class="text-body-secondary">{{ $fila['centro'] }}</td>
                                    <td>{{ $fila['estado'] }}</td>
                                    <td class="text-body-secondary">{{ $fila['proximo_seguimiento'] ? Carbon::parse($fila['proximo_seguimiento'])->format('d/m/Y') : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    @endif
</div>
