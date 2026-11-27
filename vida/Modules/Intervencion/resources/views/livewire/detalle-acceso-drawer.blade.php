{{-- Drawer de «Últimos accesos» (U3): detalle de un acceso o historial completo (U3.3) --}}
<div>
    @if($abierto)
    <div class="offcanvas offcanvas-end show" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="titulo-drawer-accesos"
         x-data x-init="$nextTick(() => $el.focus())" x-on:keydown.escape.window="$wire.cerrar()">
        <div class="offcanvas-header border-bottom">
            <h2 class="offcanvas-title h6 fw-semibold" id="titulo-drawer-accesos">
                {{ $modo === 'lista' ? 'Historial de accesos' : 'Detalle del acceso' }}
            </h2>
            <button type="button" class="btn-close" wire:click="cerrar" aria-label="Cerrar"></button>
        </div>
        <div class="offcanvas-body small">
            @if($modo === 'lista')
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" role="switch" id="solo-modificaciones" wire:model.live="soloModificaciones">
                    <label class="form-check-label" for="solo-modificaciones">Solo modificaciones</label>
                </div>

                @if($this->lista->isEmpty())
                    <p class="text-body-secondary mb-0">Sin accesos registrados.</p>
                @else
                    <div class="list-group list-group-flush mb-3">
                        @foreach($this->lista as $fila)
                            @php $clase = \App\Support\Auditoria\ClasificacionAcceso::de($fila, $this->historia); @endphp
                            <button type="button" wire:click="abrirDetalle({{ $fila->id }})" data-acceso="{{ $clase->tipo() }}" @class([
                                'list-group-item list-group-item-action px-2 py-2 text-start',
                                'bg-warning-subtle' => $clase->esSospechoso(),
                                'bg-danger-subtle border-start border-3 border-danger' => $clase->esAnomalo(),
                            ])>
                                <span class="d-flex align-items-center gap-2">
                                    <span class="fw-semibold">{{ $fila->user?->nombreVisible() ?? '—' }}</span>
                                    @if($clase->esOtraUo)
                                        <span @class(['badge', 'bg-danger-subtle text-danger-emphasis' => $clase->esAnomalo(), 'bg-warning-subtle text-warning-emphasis' => ! $clase->esAnomalo()])>Otra UO</span>
                                    @endif
                                    <span class="text-body-secondary ms-auto">{{ $fila->created_at->format('d/m/Y H:i') }}</span>
                                </span>
                                <span @class(['d-block', 'fw-semibold' => $clase->esCambio, 'text-body-secondary' => ! $clase->esCambio])>{{ \App\Support\Auditoria\ClasificacionAcceso::texto($fila) }}</span>
                            </button>
                        @endforeach
                    </div>
                    {{ $this->lista->links() }}
                @endif
            @elseif($acceso = $this->acceso)
                @php $clase = \App\Support\Auditoria\ClasificacionAcceso::de($acceso, $this->historia); @endphp
                @if($desdeLista)
                    <button type="button" wire:click="volver" class="btn btn-link btn-sm px-0 mb-2 d-inline-flex align-items-center gap-1">
                        <x-heroicon-o-arrow-left class="icon-12" aria-hidden="true"/> Volver a la lista
                    </button>
                @endif

                @if($clase->esAnomalo())
                    <div class="alert alert-danger py-2" role="alert">Modificación desde otra UO: revísala.</div>
                @elseif($clase->esSospechoso())
                    <div class="alert alert-warning py-2" role="status">Consulta desde otra UO.</div>
                @endif

                <dl class="row mb-0">
                    <dt class="col-4 text-body-secondary fw-normal">Quién</dt>
                    <dd class="col-8">
                        {{ $acceso->user?->nombreVisible() ?? '—' }}
                        @if($uo = $this->uoDe($acceso))
                            <span class="d-block text-body-secondary">{{ $uo }}</span>
                        @endif
                    </dd>
                    <dt class="col-4 text-body-secondary fw-normal">Cuándo</dt>
                    <dd class="col-8">{{ $acceso->created_at->format('d/m/Y H:i:s') }}</dd>
                    <dt class="col-4 text-body-secondary fw-normal">Acción</dt>
                    <dd class="col-8">{{ \App\Support\Auditoria\ClasificacionAcceso::texto($acceso) }}</dd>
                    <dt class="col-4 text-body-secondary fw-normal">Registro</dt>
                    <dd class="col-8">
                        @if($enlace = $this->enlaceRegistro($acceso))
                            <a href="{{ $enlace }}" wire:navigate>{{ $this->nombreRegistro($acceso) }}</a>
                        @else
                            {{ $this->nombreRegistro($acceso) }}
                        @endif
                    </dd>
                </dl>

                @if($this->cambios !== [])
                    <h3 class="h6 fw-semibold border-top pt-3 mt-3">Cambios</h3>
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">Campo</th>
                                <th scope="col">Antes</th>
                                <th scope="col">Después</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($this->cambios as $cambio)
                                <tr>
                                    <th scope="row" class="fw-normal">{{ $cambio['etiqueta'] }}</th>
                                    @if($cambio['protegido'])
                                        <td colspan="2" class="text-body-secondary">Cambió; el contenido no se muestra</td>
                                    @else
                                        <td>{{ $cambio['antes'] }}</td>
                                        <td>{{ $cambio['despues'] }}</td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            @endif
        </div>
    </div>
    <div class="offcanvas-backdrop fade show" wire:click="cerrar"></div>
    @endif
</div>
