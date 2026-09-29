<div class="op-page">

    {{-- Toolbar --}}
    <div class="d-flex align-items-center gap-2 px-3 py-2 border-bottom flex-wrap">
        <div class="fw-semibold">
            {{ ucfirst(\Carbon\Carbon::create($this->cuadrante?->anyo ?? now()->year, $this->cuadrante?->mes ?? now()->month, 1)->translatedFormat('F Y')) }}
        </div>
        @if($this->cuadrante)
            <span class="badge {{ $this->cuadrante->estado->tono()->clasesSuave() }}">{{ $this->cuadrante->estado->label() }}</span>
        @endif

        {{-- Selector de vista --}}
        <div class="btn-group btn-group-sm ms-2" role="group" aria-label="Vista">
            <button type="button"
                    class="btn {{ $vistaActiva === 'semana' ? 'btn-primary' : 'btn-outline-secondary' }}"
                    wire:click="$set('vistaActiva', 'semana')">
                Semana
            </button>
            <button type="button"
                    class="btn {{ $vistaActiva === 'mes' ? 'btn-primary' : 'btn-outline-secondary' }}"
                    wire:click="$set('vistaActiva', 'mes')">
                Mes
            </button>
        </div>

        <div class="ms-auto d-flex gap-2">
            @if($this->modoManual && $this->cuadrante?->estado->value === 'borrador')
                <button type="button" class="btn btn-outline-secondary btn-sm"
                        wire:click="regenerar"
                        wire:confirm="¿Regenerar el borrador? Se eliminarán las líneas actuales y se recalcularán desde los perfiles horarios.">
                    <x-heroicon-o-arrow-path class="icon-16 me-1" aria-hidden="true"/>
                    Regenerar
                </button>
                <button type="button" class="btn btn-primary btn-sm"
                        wire:click="publicar"
                        wire:confirm="¿Publicar el cuadrante? Se materializarán los slots y los profesionales podrán ver su agenda.">
                    <x-heroicon-o-check-circle class="icon-16 me-1" aria-hidden="true"/>
                    Publicar cuadrante
                </button>
            @endif
        </div>
    </div>

    {{-- Error de publicación --}}
    @if($errorPublicacion)
    <div class="alert alert-danger mx-3 mt-3 mb-0 d-flex align-items-center gap-2 py-2" role="alert">
        <x-heroicon-o-exclamation-circle class="icon-16 flex-shrink-0" aria-hidden="true"/>
        {{ $errorPublicacion }}
    </div>
    @endif

    {{-- Sin cuadrante --}}
    @if(! $this->cuadrante)
    <x-op.empty icono="calendar-days">No hay cuadrante para este mes. Genera uno desde la configuración.</x-op.empty>
    @else

    {{-- Grid del cuadrante --}}
    <div class="p-3 overflow-auto">
        @php
            $hoy = now()->toDateString();
        @endphp
        <table class="table table-bordered table-sm align-middle text-nowrap mb-0">
            <thead class="table-light">
                <tr>
                    <th class="fw-semibold">Profesional</th>
                    @foreach($this->diasEnVista as $dia)
                    <th class="text-center small {{ $dia->toDateString() === $hoy ? 'table-primary' : '' }}">
                        {{ $dia->translatedFormat('D') }}<br>
                        <span class="fw-bold">{{ $dia->format('d/m') }}</span>
                    </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse($this->profesionales as $prof)
                <tr>
                    <td class="fw-medium small">
                        {{ $prof->profesional?->nombre_completo ?? $prof->email }}
                    </td>
                    @foreach($this->diasEnVista as $dia)
                    @php
                        $linea = $this->lineasIndexadas[$prof->id . '_' . $dia->toDateString()] ?? null;
                    @endphp
                    <td class="{{ $dia->toDateString() === $hoy ? 'bg-primary-subtle bg-opacity-10' : '' }}">
                        @if($linea === null)
                            <span class="text-body-tertiary small">—</span>
                        @elseif($linea->anulada)
                            <span class="badge bg-danger-subtle text-danger-emphasis w-100 text-center">
                                <x-heroicon-s-x-circle class="icon-12 me-1" aria-hidden="true"/>
                                Ausencia
                            </span>
                        @else
                            @foreach($linea->franjas as $franja)
                            <span class="badge {{ \Modules\Agenda\Support\Ui\Tonos::tipoFranja($franja['tipo'] ?? '')?->clasesSuave() ?? 'bg-light text-body-secondary' }} d-block mb-1 text-start">
                                {{ substr($franja['inicio'], 0, 5) }}–{{ substr($franja['fin'], 0, 5) }}
                            </span>
                            @endforeach
                        @endif
                    </td>
                    @endforeach
                </tr>
                @empty
                <tr>
                    <td colspan="{{ count($this->diasEnVista) + 1 }}" class="text-center text-body-secondary small py-4">
                        Sin profesionales con perfil horario activo este mes.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Leyenda --}}
    <div class="px-3 pb-3 d-flex flex-wrap gap-3">
        <span class="badge bg-primary-subtle text-primary-emphasis">Atención ciudadana</span>
        <span class="badge bg-success-subtle text-success-emphasis">Actividad colectiva</span>
        <span class="badge bg-secondary-subtle text-secondary-emphasis">Reserva imprevistos</span>
        <span class="badge bg-danger-subtle text-danger-emphasis">Ausencia</span>
    </div>

    @endif

</div>
