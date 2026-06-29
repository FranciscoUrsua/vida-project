<div>
    <div class="op-page">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2>Excepciones — {{ $profesional->name }}</h2>
            <button wire:click="abrirModal()" class="btn btn-primary">+ Registrar excepción</button>
        </div>

        @if ($citasAfectadas > 0)
            <div class="alert alert-warning">
                Hay {{ $citasAfectadas }} {{ $citasAfectadas === 1 ? 'cita confirmada' : 'citas confirmadas' }} en este período que serán afectadas.
                <button wire:click="guardar" class="btn btn-sm btn-warning ms-2">Confirmar y guardar</button>
            </div>
        @endif

        <h3>Próximas y en curso</h3>
        <table class="table">
            <thead><tr><th>Tipo</th><th>Período</th><th>Acciones</th></tr></thead>
            <tbody>
                @foreach ($this->proximas as $exc)
                    <tr>
                        <td>{{ $exc->tipo->label() }}</td>
                        <td>{{ $exc->fecha_inicio->format('d/m/Y') }} – {{ $exc->fecha_fin->format('d/m/Y') }}</td>
                        <td>
                            <button wire:click="abrirModal({{ $exc->id }})" class="btn btn-sm btn-outline-secondary">Editar</button>
                            <button wire:click="eliminar({{ $exc->id }})" class="btn btn-sm btn-outline-danger">Eliminar</button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        @if ($modalAbierto)
            <div class="modal d-block" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">{{ $editandoId ? 'Editar excepción' : 'Registrar excepción' }}</h5>
                        </div>
                        <div class="modal-body">
                            @if ($errors->any())
                                <div class="alert alert-danger">
                                    @foreach ($errors->all() as $error) <p>{{ $error }}</p> @endforeach
                                </div>
                            @endif
                            <div class="mb-2">
                                <label>Tipo</label>
                                <select wire:model="form.tipo" class="form-select">
                                    <option value="">— Seleccionar —</option>
                                    @foreach (\Modules\Agenda\Enums\TipoExcepcion::cases() as $t)
                                        <option value="{{ $t->value }}">{{ $t->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-2">
                                <label>Fecha inicio</label>
                                <input type="date" wire:model="form.fecha_inicio" class="form-control">
                            </div>
                            <div class="mb-2">
                                <label>Fecha fin</label>
                                <input type="date" wire:model="form.fecha_fin" class="form-control">
                            </div>
                            <div class="mb-2">
                                <label>
                                    <input type="checkbox" wire:model="form.afecta_disponibilidad"> Afecta a disponibilidad
                                </label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button wire:click="$set('modalAbierto', false)" class="btn btn-outline-secondary">Cancelar</button>
                            <button wire:click="guardar" class="btn btn-primary">Guardar excepción</button>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
