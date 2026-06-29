<div>
    <div class="op-page">
        <div class="d-flex align-items-center gap-2 mb-3">
            <h1>Cuadrante — {{ $centro->nombre }} · {{ \Carbon\Carbon::create($anyo, $mes)->translatedFormat('F Y') }}</h1>
            @if ($cuadrante)
                @if ($cuadrante->estado->value === 'borrador')
                    <span class="badge bg-warning text-dark">Borrador</span>
                @else
                    <span class="badge bg-success">Publicado</span>
                @endif
            @endif
        </div>

        @if ($cuadrante && $cuadrante->estado->value === 'borrador')
            <button wire:click="publicar()" class="btn btn-primary mb-3">Publicar cuadrante</button>
        @endif

        <div class="d-flex gap-2 mb-3">
            <button wire:click="prevSemana" class="btn btn-outline-secondary">&laquo;</button>
            <span class="align-self-center">Semana {{ $semanaActual + 1 }}</span>
            <button wire:click="nextSemana" class="btn btn-outline-secondary">&raquo;</button>
            @foreach (range(0, 4) as $i)
                <button wire:click="goSemana({{ $i }})" class="btn btn-sm {{ $semanaActual === $i ? 'btn-primary' : 'btn-outline-primary' }}">{{ $i + 1 }}</button>
            @endforeach
        </div>

        <table class="table table-bordered">
            <thead>
                <tr>
                    <th style="width:148px">Profesional</th>
                    @foreach ($this->diasSeman as $dia)
                        <th>{{ $dia->translatedFormat('D d') }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach (\Modules\Agenda\Models\PerfilHorarioProfesional::where('centro_id', $centro->id)->where('activo', true)->with('usuario')->get() as $perfil)
                    <tr>
                        <td>{{ $perfil->usuario->name }}</td>
                        @foreach ($this->diasSeman as $dia)
                            @php $celda = $this->getCelda($perfil->usuario_id, $dia); @endphp
                            <td class="{{ $celda['tipo_celda'] === 'excepcion' ? 'bg-light text-muted' : '' }}"
                                @if ($celda['tipo_celda'] === 'excepcion') wire:click="abrirModalExc({{ $perfil->usuario_id }}, {{ $dia->day }})" style="cursor:pointer" @endif
                                @if ($celda['tipo_celda'] === 'normal') wire:click="abrirModalEvento({{ $perfil->usuario_id }}, {{ $dia->day }})" @endif>
                                @if ($celda['tipo_celda'] === 'excepcion')
                                    <span class="badge bg-secondary">{{ $celda['excepcion']?->tipo->label() }}</span>
                                @elseif ($celda['tipo_celda'] === 'normal')
                                    <button wire:click.stop="abrirModalEvento({{ $perfil->usuario_id }}, {{ $dia->day }})" class="btn btn-sm btn-outline-primary">+ Evento</button>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>

        @if ($modalEventoAbierto)
            <div class="modal d-block" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header"><h5 class="modal-title">Añadir evento</h5></div>
                        <div class="modal-body">
                            <div class="mb-2"><label>Título</label><input type="text" wire:model="eventoForm.titulo" class="form-control"></div>
                            <div class="mb-2"><label>Hora inicio</label><input type="time" wire:model="eventoForm.hora_inicio" class="form-control"></div>
                            <div class="mb-2"><label>Hora fin</label><input type="time" wire:model="eventoForm.hora_fin" class="form-control"></div>
                        </div>
                        <div class="modal-footer">
                            <button wire:click="$set('modalEventoAbierto', false)" class="btn btn-outline-secondary">Cancelar</button>
                            <button wire:click="guardarEvento" class="btn btn-primary">Añadir al cuadrante</button>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @if ($modalExcAbierto)
            <div class="modal d-block" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header"><h5 class="modal-title">Detalle excepción</h5></div>
                        <div class="modal-body">
                            <p>Tipo: {{ $excDetalle['tipo'] ?? '' }}</p>
                        </div>
                        <div class="modal-footer">
                            <button wire:click="$set('modalExcAbierto', false)" class="btn btn-outline-secondary">Cerrar</button>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
