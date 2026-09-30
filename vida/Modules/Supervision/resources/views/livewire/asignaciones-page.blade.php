{{-- Bandeja de asignaciones del centro y actividad del equipo (docs/modulo-asignacion.md §6 y §7) --}}
<div class="op-page">

    <ul class="nav nav-tabs px-3 pt-2">
        <li class="nav-item">
            <a href="{{ route('supervision.asignaciones', 'pendientes') }}" wire:navigate
               class="nav-link {{ $pestana === 'pendientes' ? 'active' : '' }}"
               @if($pestana === 'pendientes') aria-current="page" @endif>
                Pendientes
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ route('supervision.asignaciones', 'actividad') }}" wire:navigate
               class="nav-link {{ $pestana === 'actividad' ? 'active' : '' }}"
               @if($pestana === 'actividad') aria-current="page" @endif>
                Actividad del equipo
            </a>
        </li>
    </ul>

    <section class="p-3">

        @if($aviso)
            <div class="alert alert-success d-flex align-items-start gap-2" role="status">
                <x-heroicon-o-check-circle class="icon-20 flex-shrink-0" aria-hidden="true"/>
                <span>{{ $aviso }}</span>
            </div>
        @endif

        @if($this->centro === null)
            <x-op.empty icono="building-office">No supervisas ningún centro: la bandeja de asignaciones es de la supervisión de cada centro.</x-op.empty>

        @elseif($pestana === 'actividad')
            <p class="text-body-secondary">
                Casos con actividad: con plan activo o con algún apunte en los últimos {{ $this->centro->meses_inactividad_caso }} meses.
                Es información para el equipo: no cambia el sorteo ni cierra historias.
            </p>

            @if($this->actividad->isEmpty())
                <x-op.empty icono="users">Nadie del centro tiene casos asignados.</x-op.empty>
            @else
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">Profesional</th>
                                <th scope="col" class="text-end">Asignados</th>
                                <th scope="col" class="text-end">Con actividad</th>
                                <th scope="col" class="text-end">Dormidos</th>
                                <th scope="col" class="text-end"><span class="visually-hidden">Acciones</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($this->actividad as $fila)
                                <tr wire:key="actividad-{{ $fila['profesional']->id }}">
                                    <td class="fw-medium">{{ $fila['profesional']->nombre_completo }}</td>
                                    <td class="text-end">{{ $fila['asignados'] }}</td>
                                    <td class="text-end">{{ $fila['con_actividad'] }}</td>
                                    <td class="text-end">{{ $fila['dormidos'] }}</td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-outline-secondary btn-sm"
                                                wire:click="iniciarReparto({{ $fila['profesional']->id }})">
                                            Repartir sus casos
                                        </button>
                                    </td>
                                </tr>
                                @if($repartoOrigenId === $fila['profesional']->id)
                                    <tr wire:key="reparto-form-{{ $fila['profesional']->id }}">
                                        <td colspan="5" class="bg-body-tertiary">
                                            <form wire:submit="proponerReparto" class="d-flex flex-column gap-2">
                                                <label for="motivo-reparto" class="form-label mb-0">
                                                    Motivo del reparto de los casos de {{ $fila['profesional']->nombre_completo }}
                                                </label>
                                                <textarea id="motivo-reparto" rows="2" wire:model="motivoReparto"
                                                          class="form-control @error('motivoReparto') is-invalid @enderror"></textarea>
                                                @error('motivoReparto') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                                <p class="small text-body-secondary mb-0">
                                                    Se prepara una propuesta para que la revises. Ninguna asignación cambia hasta que la confirmes.
                                                </p>
                                                <div class="d-flex gap-2">
                                                    <button type="submit" class="btn btn-primary btn-sm">Preparar propuesta</button>
                                                    <button type="button" class="btn btn-link btn-sm" wire:click="cancelar">Cancelar</button>
                                                </div>
                                            </form>
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

        @else
            @php $tiposCentro = $this->tiposCentro; @endphp

            {{-- Repartos por salida pendientes de confirmar --}}
            @if($this->repartos->isNotEmpty())
                <div class="card mb-3">
                    <div class="card-header d-flex align-items-center gap-2">
                        <x-heroicon-o-arrows-right-left class="icon-16" aria-hidden="true"/>
                        <h2 class="h6 fw-semibold mb-0">Repartos por confirmar</h2>
                        <span class="badge rounded-pill text-bg-secondary">{{ $this->repartos->count() }}</span>
                    </div>
                    <div class="list-group list-group-flush">
                        @foreach($this->repartos as $reparto)
                            <div class="list-group-item d-flex align-items-center justify-content-between gap-2" wire:key="reparto-{{ $reparto->id }}">
                                <div>
                                    <div class="fw-medium">Casos de {{ $reparto->profesionalOrigen->nombre_completo }}</div>
                                    <div class="small text-body-secondary">{{ $reparto->lineas_count }} casos · propuesto el {{ $reparto->created_at->format('d/m/Y') }}</div>
                                </div>
                                <a href="{{ route('supervision.asignaciones.reparto', $reparto) }}" wire:navigate class="btn btn-outline-primary btn-sm">Revisar</a>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Personas sin centro --}}
            <div class="card mb-3">
                <div class="card-header d-flex align-items-center gap-2">
                    <x-heroicon-o-map-pin class="icon-16" aria-hidden="true"/>
                    <h2 class="h6 fw-semibold mb-0">Personas sin centro</h2>
                    <span class="badge rounded-pill text-bg-secondary">{{ $this->sinCentro->count() }}</span>
                </div>
                @if($this->sinCentro->isEmpty())
                    <div class="card-body"><x-op.empty icono="check-circle">No hay personas sin centro.</x-op.empty></div>
                @else
                    <div class="list-group list-group-flush">
                        @foreach($this->sinCentro as $pendiente)
                            <div class="list-group-item" wire:key="pendiente-{{ $pendiente->id }}">
                                <div class="d-flex align-items-start justify-content-between gap-2">
                                    <div>
                                        <a href="{{ route('ciudadania.ciudadano.ficha', $pendiente->ciudadano_id) }}" wire:navigate class="fw-medium">{{ $pendiente->ciudadano?->nombre_completo }}</a>
                                        <div class="small text-body-secondary">
                                            {{ $tiposCentro[$pendiente->tipo_centro] ?? $pendiente->tipo_centro }} · desde el {{ $pendiente->created_at->format('d/m/Y') }}
                                        </div>
                                    </div>
                                    @if($pendiente->motivo)
                                        <span class="badge {{ $pendiente->motivo->tono()->clasesSuave() }}">{{ $pendiente->motivo->label() }}</span>
                                    @endif
                                </div>

                                @if($resolviendoId === $pendiente->id)
                                    <form wire:submit="resolver" class="d-flex flex-column gap-2 mt-3">
                                        @if($accion === 'asignar')
                                            <label for="centro-elegido" class="form-label mb-0">Centro</label>
                                            <select id="centro-elegido" wire:model="centroElegidoId" class="form-select">
                                                <option value="">Elige un centro</option>
                                                @foreach($this->centrosDelTipo($pendiente->tipo_centro) as $opcion)
                                                    <option value="{{ $opcion->id }}">{{ $opcion->nombre }}</option>
                                                @endforeach
                                            </select>
                                        @endif
                                        @include('supervision::livewire.partials.motivo-asignacion')
                                    </form>
                                @else
                                    <div class="d-flex gap-2 mt-2">
                                        <button type="button" class="btn btn-outline-primary btn-sm" wire:click="iniciar({{ $pendiente->id }}, 'asignar')">Asignar centro</button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="iniciar({{ $pendiente->id }}, 'descartar')">Descartar</button>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Historias sin profesional de referencia --}}
            <div class="card mb-3">
                <div class="card-header d-flex align-items-center gap-2">
                    <x-heroicon-o-user-circle class="icon-16" aria-hidden="true"/>
                    <h2 class="h6 fw-semibold mb-0">Historias sin profesional de referencia</h2>
                    <span class="badge rounded-pill text-bg-secondary">{{ $this->sinReferencia->count() }}</span>
                </div>
                @if($this->sinReferencia->isEmpty())
                    <div class="card-body"><x-op.empty icono="check-circle">Todas las historias tienen profesional de referencia.</x-op.empty></div>
                @else
                    <div class="list-group list-group-flush">
                        @foreach($this->sinReferencia as $pendiente)
                            <div class="list-group-item" wire:key="pendiente-{{ $pendiente->id }}">
                                <div class="d-flex align-items-start justify-content-between gap-2">
                                    <div>
                                        <a href="{{ route('ciudadania.ciudadano.ficha', $pendiente->ciudadano_id) }}" wire:navigate class="fw-medium">{{ $pendiente->ciudadano?->nombre_completo }}</a>
                                        <div class="small text-body-secondary">Historia abierta el {{ $pendiente->created_at->format('d/m/Y') }}</div>
                                    </div>
                                    @if($pendiente->motivo)
                                        <span class="badge {{ $pendiente->motivo->tono()->clasesSuave() }}">{{ $pendiente->motivo->label() }}</span>
                                    @endif
                                </div>

                                @if($resolviendoId === $pendiente->id)
                                    <form wire:submit="resolver" class="d-flex flex-column gap-2 mt-3">
                                        <label for="profesional-elegido" class="form-label mb-0">Profesional de referencia</label>
                                        <select id="profesional-elegido" wire:model="profesionalElegidoId" class="form-select">
                                            <option value="">Elige un profesional</option>
                                            @foreach($this->profesionalesReferencia as $opcion)
                                                <option value="{{ $opcion->id }}">{{ $opcion->nombre_completo }}</option>
                                            @endforeach
                                        </select>
                                        @include('supervision::livewire.partials.motivo-asignacion')
                                    </form>
                                @else
                                    <div class="d-flex gap-2 mt-2">
                                        <button type="button" class="btn btn-outline-primary btn-sm" wire:click="iniciar({{ $pendiente->id }}, 'asignar')">Asignar referencia</button>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Propuestas de cambio de centro por domicilio --}}
            <div class="card mb-3">
                <div class="card-header d-flex align-items-center gap-2">
                    <x-heroicon-o-home-modern class="icon-16" aria-hidden="true"/>
                    <h2 class="h6 fw-semibold mb-0">Cambios de domicilio</h2>
                    <span class="badge rounded-pill text-bg-secondary">{{ $this->cambiosDomicilio->count() }}</span>
                </div>
                @if($this->cambiosDomicilio->isEmpty())
                    <div class="card-body"><x-op.empty icono="check-circle">No hay propuestas de cambio de centro.</x-op.empty></div>
                @else
                    <div class="list-group list-group-flush">
                        @foreach($this->cambiosDomicilio as $pendiente)
                            <div class="list-group-item" wire:key="pendiente-{{ $pendiente->id }}">
                                <a href="{{ route('ciudadania.ciudadano.ficha', $pendiente->ciudadano_id) }}" wire:navigate class="fw-medium">{{ $pendiente->ciudadano?->nombre_completo }}</a>
                                <div class="small text-body-secondary">
                                    Su nuevo domicilio corresponde a {{ $pendiente->centroPropuesto?->nombre }}. Sigue en {{ $pendiente->centro?->nombre }} hasta que decidas.
                                </div>

                                @if($resolviendoId === $pendiente->id)
                                    <form wire:submit="resolver" class="d-flex flex-column gap-2 mt-3">
                                        @include('supervision::livewire.partials.motivo-asignacion')
                                    </form>
                                @else
                                    <div class="d-flex gap-2 mt-2">
                                        <button type="button" class="btn btn-outline-primary btn-sm" wire:click="iniciar({{ $pendiente->id }}, 'confirmar')">Trasladar a {{ $pendiente->centroPropuesto?->nombre }}</button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="iniciar({{ $pendiente->id }}, 'descartar')">Mantener en su centro</button>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

    </section>

</div>
