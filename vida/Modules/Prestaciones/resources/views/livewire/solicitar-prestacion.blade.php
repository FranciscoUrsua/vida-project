<div @class(['h-100' => $herramienta, 'd-inline-block' => ! $herramienta])>
    @if($herramienta)
        <button type="button" wire:click="abrir"
                class="btn btn-outline-secondary w-100 h-100 d-flex flex-column align-items-center justify-content-center gap-1 py-3">
            <x-heroicon-o-gift class="icon-20" aria-hidden="true"/>
            <span class="small lh-sm">{{ $etiqueta }}</span>
        </button>
    @else
        <button type="button" wire:click="abrir" class="btn btn-outline-secondary btn-sm">
            <x-heroicon-o-plus class="icon-14" aria-hidden="true"/>
            {{ $etiqueta }}
        </button>
    @endif

    @if($abierto)
        <div class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="titulo-solicitar-{{ $this->getId() }}">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header">
                        <h2 id="titulo-solicitar-{{ $this->getId() }}" class="modal-title fs-6">Solicitar prestación</h2>
                        <button wire:click="cerrar" type="button" class="btn-close" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        @error('prestacion') <div class="alert alert-danger py-2 small">{{ $message }}</div> @enderror
                        @if($aviso)
                            <div class="alert alert-info py-2 small">
                                {{ $aviso }}
                                @if($avisoUrl) <a href="{{ $avisoUrl }}" wire:navigate class="alert-link">Ir al plan principal</a> @endif
                            </div>
                        @endif

                        @if($directaId)
                            @php $directa = $this->prestaciones->firstWhere('id', $directaId); @endphp
                            <p class="mb-3">Se registrará <strong>{{ $directa?->nombre }}</strong> como prestada desde hoy.</p>
                            <div class="d-flex gap-2">
                                <button type="button" wire:click="registrarDirecta" class="btn btn-primary btn-sm">Registrar</button>
                                <button type="button" wire:click="$set('directaId', null)" class="btn btn-outline-secondary btn-sm">Volver</button>
                            </div>
                        @elseif($this->centro === null)
                            <x-op.empty icono="building-office">Para solicitar prestaciones tienes que estar trabajando en un centro.</x-op.empty>
                        @else
                            <label for="buscar-prestacion-{{ $this->getId() }}" class="visually-hidden">Buscar prestación</label>
                            <input id="buscar-prestacion-{{ $this->getId() }}" type="search" wire:model.live.debounce.300ms="busqueda"
                                   class="form-control form-control-sm mb-2" placeholder="Buscar prestación…">
                            <div class="list-group list-group-flush">
                                @forelse($this->prestaciones as $prestacion)
                                    <button type="button" wire:key="prest-{{ $prestacion->id }}" wire:click="elegir({{ $prestacion->id }})"
                                            class="list-group-item list-group-item-action d-flex justify-content-between align-items-center gap-2 px-0">
                                        <span class="small">{{ $prestacion->nombre }}</span>
                                        <span class="badge text-bg-light border text-nowrap">{{ $prestacion->via_acceso->label() }}</span>
                                    </button>
                                @empty
                                    <p class="text-body-secondary small mb-0">Ninguna prestación coincide.</p>
                                @endforelse
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    @endif
</div>
