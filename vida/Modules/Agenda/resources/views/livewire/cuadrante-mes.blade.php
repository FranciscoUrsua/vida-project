<div>
    <div class="op-page">
        <div class="d-flex align-items-center gap-2 px-3 py-2 border-bottom">
            <h1 class="h5 fw-semibold mb-0">
                {{ $centro->nombre }} &middot; {{ \Carbon\Carbon::create($anyo, $mes)->translatedFormat('F Y') }}
            </h1>
            @if ($cuadrante)
                @if ($cuadrante->estado->value === 'borrador')
                    <span class="badge bg-warning text-dark">Borrador</span>
                @else
                    <span class="badge bg-success">Publicado</span>
                @endif
            @endif
            @if ($cuadrante && $cuadrante->estado->value === 'borrador')
                <button wire:click="publicar()" class="btn btn-primary btn-sm ms-auto">
                    Publicar cuadrante
                </button>
            @endif
        </div>

        {{-- Navegación de semanas --}}
        <div class="d-flex align-items-center gap-2 px-3 py-2 border-bottom">
            <button wire:click="prevSemana" class="btn btn-outline-secondary btn-sm">&laquo;</button>
            <span class="small text-body-secondary">Semana {{ $semanaActual + 1 }}</span>
            <button wire:click="nextSemana" class="btn btn-outline-secondary btn-sm">&raquo;</button>
            <div class="d-flex gap-1 ms-2">
                @foreach (range(0, 4) as $i)
                    <button wire:click="goSemana({{ $i }})"
                            class="btn btn-sm {{ $semanaActual === $i ? 'btn-primary' : 'btn-outline-secondary' }}">
                        {{ $i + 1 }}
                    </button>
                @endforeach
            </div>
        </div>

        {{-- Grid del cuadrante --}}
        <div class="p-3 table-responsive">
            <table class="table table-bordered table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="min-width:160px">Profesional</th>
                        @foreach ($this->diasSeman as $dia)
                            <th class="text-center">{{ $dia->translatedFormat('D d') }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach (\Modules\Agenda\Models\PerfilHorarioProfesional::where('centro_id', $centro->id)->where('activo', true)->with('usuario.profesional')->get() as $perfil)
                    <tr>
                        <td class="fw-medium small">
                            {{ $perfil->usuario->profesional?->nombre_completo ?? $perfil->usuario->name }}
                        </td>
                        @foreach ($this->diasSeman as $dia)
                            @php $celda = $this->getCelda($perfil->usuario_id, $dia); @endphp
                            <td class="text-center {{ $celda['tipo_celda'] === 'excepcion' ? 'bg-light' : '' }}"
                                style="vertical-align:middle">
                                @if ($celda['tipo_celda'] === 'excepcion')
                                    <button type="button"
                                            wire:click="abrirModalExc({{ $perfil->usuario_id }}, {{ $dia->day }})"
                                            class="btn btn-sm btn-link p-0 text-decoration-none">
                                        <span class="badge bg-secondary">{{ $celda['excepcion']?->tipo->label() }}</span>
                                    </button>
                                @elseif ($celda['tipo_celda'] === 'normal')
                                    @if(!empty($celda['eventos']))
                                        @foreach($celda['eventos'] as $ev)
                                            <span class="badge bg-primary-subtle text-primary-emphasis small d-block mb-1">{{ $ev->titulo }}</span>
                                        @endforeach
                                    @endif
                                    <button type="button"
                                            wire:click="abrirModalAusencia({{ $perfil->usuario_id }}, {{ $dia->day }})"
                                            class="btn btn-outline-secondary btn-sm py-0 px-1"
                                            title="Registrar ausencia">
                                        <x-heroicon-o-user-minus class="icon-14" aria-hidden="true"/>
                                    </button>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Modal: registrar ausencia --}}
        @if ($modalAusenciaAbierto)
        <div class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true"
             aria-labelledby="modal-ausencia-titulo">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modal-ausencia-titulo">Registrar ausencia</h5>
                        <button type="button" class="btn-close"
                                wire:click="$set('modalAusenciaAbierto', false)" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label" for="aus-tipo">
                                Tipo de ausencia <span class="text-danger">*</span>
                            </label>
                            <select id="aus-tipo"
                                    wire:model="ausenciaForm.tipo"
                                    class="form-select @error('ausenciaForm.tipo') is-invalid @enderror">
                                <option value="">Selecciona un tipo…</option>
                                @foreach(\Modules\Agenda\Enums\TipoExcepcion::cases() as $tipo)
                                    <option value="{{ $tipo->value }}">{{ $tipo->label() }}</option>
                                @endforeach
                            </select>
                            @error('ausenciaForm.tipo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="aus-fin">Fecha fin (si dura varios días)</label>
                            <input id="aus-fin" type="date"
                                   wire:model="ausenciaForm.fecha_fin"
                                   class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="aus-notas">Notas</label>
                            <textarea id="aus-notas" wire:model="ausenciaForm.notas"
                                      class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary btn-sm"
                                wire:click="$set('modalAusenciaAbierto', false)">Cancelar</button>
                        <button type="button" class="btn btn-primary btn-sm"
                                wire:click="registrarAusencia"
                                wire:loading.attr="disabled">
                            <span wire:loading wire:target="registrarAusencia"
                                  class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>
                            Registrar ausencia
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
        @endif

        {{-- Modal: detalle excepción existente --}}
        @if ($modalExcAbierto)
        <div class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true"
             aria-labelledby="modal-exc-titulo">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modal-exc-titulo">Excepción registrada</h5>
                        <button type="button" class="btn-close"
                                wire:click="$set('modalExcAbierto', false)" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-0">
                            Tipo: <strong>{{ \Modules\Agenda\Enums\TipoExcepcion::from($excDetalle['tipo'] ?? '')->label() }}</strong>
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary btn-sm"
                                wire:click="$set('modalExcAbierto', false)">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
        @endif

    </div>
</div>
