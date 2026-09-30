@php
    use App\Support\Ui\Tono;
    use Carbon\Carbon;
    use Modules\Intervencion\Support\Ui\Tonos;

    $ancla = Carbon::parse($fechaAncla)->locale('es');
    $hoy = today()->toDateString();

    // Leyenda: el color de cada clave sale de Tonos::tipoCita()
    $estiloCita = [
        'entrevista' => ['label' => 'Cita'],
        'seguimiento' => ['label' => 'Seguimiento'],
        'urgencia' => ['label' => 'Urgente'],
        'evento' => ['label' => 'Evento'],
    ];

    $horas = ['08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00'];

    // Destino de una entrada: atender la cita si se puede; si no, su detalle. Los eventos no enlazan.
    $urlEntrada = fn (array $e): ?string => $e['es_cita'] ? ($e['url_atender'] ?? route('agenda.citas.show', $e['id'])) : null;
@endphp

<div class="op-page d-flex flex-column gap-3 p-3">
    <section class="d-flex flex-wrap align-items-end justify-content-between gap-3">
        <div>
            <p class="small text-uppercase fw-semibold text-body-secondary mb-0">Planificacion diaria</p>
            <span class="fs-5 fw-bold">{{ $this->tituloFecha }}</span>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <div class="btn-group btn-group-sm" role="group" aria-label="Navegacion temporal">
                <button wire:click="navegarAnterior" type="button" class="btn btn-outline-secondary" aria-label="Periodo anterior">
                    <x-heroicon-o-chevron-left class="icon-16" aria-hidden="true"/>
                </button>
                <button wire:click="navegarSiguiente" type="button" class="btn btn-outline-secondary" aria-label="Periodo siguiente">
                    <x-heroicon-o-chevron-right class="icon-16" aria-hidden="true"/>
                </button>
                <button wire:click="irAHoy" type="button" class="btn btn-outline-primary">Hoy</button>
            </div>

            <div class="btn-group btn-group-sm" role="group" aria-label="Vista de agenda">
                <button wire:click="setVista('dia')" type="button" class="btn {{ $vista === 'dia' ? 'btn-primary' : 'btn-outline-primary' }}">Dia</button>
                <button wire:click="setVista('semana')" type="button" class="btn {{ $vista === 'semana' ? 'btn-primary' : 'btn-outline-primary' }}">Semana</button>
                <button wire:click="setVista('mes')" type="button" class="btn {{ $vista === 'mes' ? 'btn-primary' : 'btn-outline-primary' }}">Mes</button>
            </div>
        </div>
    </section>

    <section class="row row-cols-2 row-cols-lg-4 g-2" aria-label="Resumen de actividad">
        <div class="col">
            <article class="card card-body py-2 h-100">
                <div @class(['fs-4 fw-bold', 'text-danger' => $this->kpis['alertas_sin_reconocer'] > 0])>{{ $this->kpis['alertas_sin_reconocer'] }}</div>
                <div class="small text-body-secondary">Alertas sin reconocer</div>
            </article>
        </div>
        <div class="col">
            <article class="card card-body py-2 h-100">
                <div @class(['fs-4 fw-bold', 'text-warning' => $this->kpis['seguimientos_vencidos'] > 0])>{{ $this->kpis['seguimientos_vencidos'] }}</div>
                <div class="small text-body-secondary">Seguimientos vencidos</div>
            </article>
        </div>
        <div class="col">
            <article class="card card-body py-2 h-100">
                <div class="fs-4 fw-bold">{{ $this->kpis['citas'] }}</div>
                <div class="small text-body-secondary">
                    @if($vista === 'dia')
                        Citas hoy
                    @elseif($vista === 'semana')
                        Citas esta semana
                    @else
                        Citas este mes
                    @endif
                </div>
            </article>
        </div>
        <div class="col">
            <article class="card card-body py-2 h-100">
                <div class="fs-4 fw-bold">{{ $this->kpis['mensajes_sin_leer'] }}</div>
                <div class="small text-body-secondary">Mensajes sin leer</div>
            </article>
        </div>
    </section>

    @if($aviso)
        <div class="alert alert-success d-flex align-items-center gap-2 py-2 mb-0" role="status">
            {{ $aviso }}
            <button type="button" class="btn-close btn-sm ms-auto" wire:click="$set('aviso', null)" aria-label="Cerrar aviso"></button>
        </div>
    @endif
    @error('agenda')
        <div class="alert alert-danger py-2 mb-0" role="alert">{{ $message }}</div>
    @enderror

    <section class="card flex-grow-1">
        <div class="card-body">
            @if($vista === 'dia')
                <div class="row g-3">
                    @foreach($this->citasDia as $fecha => $citas)
                        @php
                            $col = Carbon::parse($fecha)->locale('es');
                            $esHoy = $fecha === $hoy;
                            $esPasado = $fecha < $hoy;
                        @endphp
                        <section @class(['col', 'opacity-75' => $esPasado])>
                            <header @class(['text-center rounded py-1 mb-2', 'bg-primary-subtle text-primary-emphasis' => $esHoy, 'bg-body-tertiary' => ! $esHoy])>
                                <div class="small text-uppercase">{{ $col->isoFormat('ddd') }}</div>
                                <div class="fs-5 fw-bold">{{ $col->day }}</div>
                            </header>

                            <div class="d-flex flex-column gap-1">
                                @forelse($citas as $entrada)
                                    @php $url = $urlEntrada($entrada); @endphp
                                    <article class="rounded border-start border-3 px-2 py-1 small {{ Tonos::tipoCita($entrada['tipo'])->clasesBloque() }}" wire:key="{{ $entrada['clave'] }}">
                                        <div class="d-flex flex-wrap align-items-center gap-1">
                                            <span class="fw-semibold">{{ $entrada['hora'] }}</span>
                                            @if($entrada['tipo'] === 'urgencia')
                                                <span class="badge {{ Tono::Peligro->clasesSuave() }}">Urgente</span>
                                            @endif
                                            @if($entrada['es_cita'] && $entrada['estado'] !== \Modules\Agenda\Enums\EstadoCita::Confirmada)
                                                <span class="badge {{ $entrada['estado']->tono()->clasesSuave() }}">{{ $entrada['estado']->label() }}</span>
                                            @endif
                                            @if($entrada['es_cita'] && $entrada['pendiente_cierre'])
                                                <span class="badge {{ Tono::Aviso->clasesSuave() }}">Pendiente de cierre</span>
                                            @endif
                                        </div>
                                        @if($url)
                                            <a href="{{ $url }}" wire:navigate class="d-block text-truncate text-body fw-semibold">{{ $entrada['titulo'] }}</a>
                                        @else
                                            <div @class(['text-truncate', 'fst-italic' => $entrada['es_cita'] && $entrada['pendiente_identificar']])>{{ $entrada['titulo'] }}</div>
                                        @endif
                                        @if($entrada['subtitulo'])
                                            <div class="text-body-secondary text-truncate">{{ $entrada['subtitulo'] }}</div>
                                        @endif

                                        @if($entrada['es_cita'])
                                            <div class="d-flex flex-wrap gap-1 mt-1">
                                                @if($entrada['url_atender'])
                                                    <a href="{{ $entrada['url_atender'] }}" wire:navigate class="btn btn-primary btn-sm py-0">Atender</a>
                                                @endif
                                                @if($entrada['puede_incomparecencia'])
                                                    <button type="button" class="btn btn-outline-secondary btn-sm py-0"
                                                            wire:click="marcarIncomparecencia({{ $entrada['id'] }})"
                                                            wire:confirm="¿Marcar que la persona no ha venido a la cita de las {{ $entrada['hora'] }}?">Incomparecencia</button>
                                                @endif
                                                @if($entrada['puede_acompanantes'])
                                                    <button type="button" class="btn btn-outline-secondary btn-sm py-0" wire:click="abrirAccion({{ $entrada['id'] }}, 'acompanantes')">Acompañantes</button>
                                                @endif
                                                @if($entrada['puede_pedir_cambio'])
                                                    <button type="button" class="btn btn-outline-secondary btn-sm py-0" wire:click="abrirAccion({{ $entrada['id'] }}, 'cambio')">Pedir cambio</button>
                                                @endif
                                            </div>
                                        @endif
                                    </article>
                                @empty
                                    <p class="small text-body-secondary text-center mb-0">Sin citas programadas.</p>
                                @endforelse

                                @if(! $esPasado)
                                    @foreach($this->huecosLibres[$fecha] ?? [] as $hora)
                                        <div class="small text-body-tertiary border-bottom py-1">{{ $hora }} <span class="ms-1">Disponible</span></div>
                                    @endforeach
                                @endif
                            </div>
                        </section>
                    @endforeach
                </div>
            @elseif($vista === 'semana')
                @php
                    $diasSemana = array_keys($this->citasSemana);
                    $citasSemana = $this->citasSemana;
                    // Franja base y, además, cualquier hora con citas fuera de ella
                    $horasSemana = collect($horas)
                        ->merge(collect($citasSemana)->flatten(1)->map(fn ($e) => substr($e['hora'], 0, 2).':00'))
                        ->unique()->sort()->values();
                @endphp
                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-top mb-0">
                        <thead>
                            <tr>
                                <th scope="col"><span class="visually-hidden">Hora</span></th>
                                @foreach($diasSemana as $fecha)
                                    @php
                                        $dia = Carbon::parse($fecha)->locale('es');
                                        $esHoy = $fecha === $hoy;
                                    @endphp
                                    <th scope="col" @class(['text-center', 'table-primary' => $esHoy])>
                                        <div class="small text-uppercase fw-normal">{{ $dia->isoFormat('ddd') }}</div>
                                        <div>{{ $dia->day }}</div>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($horasSemana as $hora)
                                <tr>
                                    <th scope="row" class="small fw-normal text-body-secondary text-nowrap">{{ $hora }}</th>
                                    @foreach($diasSemana as $fecha)
                                        @php
                                            $citasHora = collect($citasSemana[$fecha] ?? [])->filter(fn ($c) => substr($c['hora'], 0, 2) === substr($hora, 0, 2))->values();
                                            $esHoy = $fecha === $hoy;
                                        @endphp
                                        <td @class(['table-primary' => $esHoy])>
                                            @foreach($citasHora as $entrada)
                                                @php $url = $urlEntrada($entrada); @endphp
                                                @if($url)
                                                    <a href="{{ $url }}" wire:navigate wire:key="sem-{{ $entrada['clave'] }}" class="d-block rounded border-start border-3 px-1 mb-1 small text-body text-decoration-none text-truncate {{ Tonos::tipoCita($entrada['tipo'])->clasesBloque() }}">{{ $entrada['hora'] }} {{ $entrada['titulo'] }}</a>
                                                @else
                                                    <div wire:key="sem-{{ $entrada['clave'] }}" class="d-block rounded border-start border-3 px-1 mb-1 small text-body text-truncate {{ Tonos::tipoCita($entrada['tipo'])->clasesBloque() }}" title="{{ $entrada['titulo'] }}">{{ $entrada['hora'] }} {{ $entrada['titulo'] }}</div>
                                                @endif
                                            @endforeach
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                @php
                    $inicioMes = Carbon::parse($fechaAncla)->startOfMonth();
                    $finMes = Carbon::parse($fechaAncla)->endOfMonth();
                    $primerLunes = $inicioMes->copy()->startOfWeek();
                    $ultimoDomingo = $finMes->copy()->endOfWeek();
                    $diasCalendario = [];
                    $cur = $primerLunes->copy();
                    while ($cur->lte($ultimoDomingo)) {
                        $diasCalendario[] = $cur->copy();
                        $cur->addDay();
                    }
                    $datosMes = $this->datosMes;
                    $prioridad = ['urgencia' => 0, 'entrevista' => 1, 'seguimiento' => 2, 'evento' => 3];
                @endphp

                <div class="table-responsive">
                    <table class="table table-bordered mb-0">
                        <thead>
                            <tr>
                                @foreach(['Lun', 'Mar', 'Mie', 'Jue', 'Vie', 'Sab', 'Dom'] as $nombreDia)
                                    <th scope="col" class="small text-center text-body-secondary">{{ $nombreDia }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach(array_chunk($diasCalendario, 7) as $semana)
                                <tr>
                                    @foreach($semana as $dia)
                                        @php
                                            $fechaDia = $dia->toDateString();
                                            $esMesActual = $dia->month === $inicioMes->month;
                                            $esHoy = $fechaDia === $hoy;
                                            $numeroDia = (int) $dia->day;
                                            $datosDia = $esMesActual ? ($datosMes[$numeroDia] ?? null) : null;
                                            $tiposDia = $datosDia ? $datosDia['tipos'] : [];
                                            uksort($tiposDia, fn($a, $b) => ($prioridad[$a] ?? 9) <=> ($prioridad[$b] ?? 9));
                                            $visibles = array_slice($tiposDia, 0, 3, true);
                                        @endphp
                                        @if($esMesActual)
                                            <td @class(['p-0', 'table-primary' => $esHoy, 'bg-body-tertiary' => ! $esHoy && $dia->isWeekend()])>
                                                <button type="button" wire:click="irADia('{{ $fechaDia }}')" class="btn w-100 h-100 text-start rounded-0 p-2">
                                                    <div @class(['small', 'fw-bold text-primary' => $esHoy])>{{ $dia->day }}</div>
                                                    <div class="d-flex flex-wrap gap-1 mt-1">
                                                        @foreach($visibles as $tipo => $conteo)
                                                            <span class="badge rounded-pill {{ Tonos::tipoCita($tipo)->clasesSuave() }}">{{ $conteo }}</span>
                                                        @endforeach
                                                    </div>
                                                </button>
                                            </td>
                                        @else
                                            <td class="small text-body-tertiary bg-body-tertiary p-2">{{ $dia->day }}</td>
                                        @endif
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <footer class="card-footer d-flex flex-wrap align-items-center gap-3 small" aria-label="Leyenda de tipos de cita">
            <span class="fw-semibold text-body-secondary">Leyenda</span>
            @foreach($estiloCita as $tipo => $estilos)
                <span class="d-inline-flex align-items-center gap-1">
                    <span class="d-inline-block rounded p-1 {{ Tonos::tipoCita($tipo)->clasesPunto() }}" aria-hidden="true"></span>
                    {{ $estilos['label'] }}
                </span>
            @endforeach
        </footer>
    </section>

    @if($accion && $this->citaEnAccion)
        @php $citaAccion = $this->citaEnAccion; @endphp
        <div class="modal-backdrop fade show"></div>
        <div class="modal fade show d-block"
             wire:click.self="cerrarAccion"
             x-data x-on:keydown.escape.window="$wire.cerrarAccion()"
             role="dialog" aria-modal="true" aria-labelledby="agenda-accion-titulo" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header">
                        <h2 id="agenda-accion-titulo" class="modal-title h6 fw-bold">
                            {{ $accion === 'cambio' ? 'Pedir cambio al supervisor' : 'Acompañantes' }}
                            <span class="d-block small fw-normal text-body-secondary">
                                Cita del {{ $citaAccion->fecha->format('d/m/Y') }} a las {{ substr((string) $citaAccion->hora_inicio, 0, 5) }}
                            </span>
                        </h2>
                        <button type="button" class="btn-close" wire:click="cerrarAccion" aria-label="Cerrar"></button>
                    </div>

                    @if($accion === 'cambio')
                        <div class="modal-body">
                            <p class="small text-body-secondary">Tus citas las mueve quien gestiona las del centro. Tu supervisor recibirá un mensaje con enlace a la cita.</p>
                            <label for="agenda-texto-cambio" class="form-label small fw-semibold">Qué cambio necesitas</label>
                            <textarea id="agenda-texto-cambio" wire:model="textoCambio" rows="3" @class(['form-control', 'is-invalid' => $errors->has('cambio')])></textarea>
                            @error('cambio') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="cerrarAccion">Cancelar</button>
                            <button type="button" class="btn btn-primary btn-sm" wire:click="pedirCambio">Enviar</button>
                        </div>
                    @else
                        <div class="modal-body">
                            @if($citaAccion->acompanantes->isNotEmpty())
                                <ul class="list-unstyled small mb-3">
                                    @foreach($citaAccion->acompanantes as $a)
                                        <li wire:key="acompanante-{{ $a->id }}">{{ $a->nombreVisible() }} · {{ $this->relacionesAcompanante[$a->relacion] ?? $a->relacion }}</li>
                                    @endforeach
                                </ul>
                            @endif

                            <p class="small text-body-secondary">Solo es un registro: no crea citas ni vínculos para quien acompaña.</p>

                            <div class="mb-3">
                                <label for="agenda-acompanante-relacion" class="form-label small fw-semibold">Relación</label>
                                <select id="agenda-acompanante-relacion" wire:model="formAcompanante.relacion" class="form-select form-select-sm">
                                    <option value="">Elige…</option>
                                    @foreach($this->relacionesAcompanante as $clave => $etiqueta)
                                        <option value="{{ $clave }}">{{ $etiqueta }}</option>
                                    @endforeach
                                </select>
                            </div>

                            @if($formAcompanante['ciudadano_id'])
                                <p class="small mb-3">
                                    <span class="fw-semibold">{{ $formAcompanante['ciudadano_nombre'] }}</span>
                                    <button type="button" class="btn btn-link btn-sm" wire:click="quitarAcompananteEnlazado">Cambiar</button>
                                </p>
                            @else
                                <p class="small fw-semibold mb-1">Persona que está en VIDA</p>
                                @include('agenda::livewire.citas.partials.buscar-persona', ['accion' => 'elegirAcompanante', 'etiqueta' => 'Elegir'])
                                <label for="agenda-acompanante-nombre" class="form-label small fw-semibold">O su nombre, si no está</label>
                                <input id="agenda-acompanante-nombre" type="text" wire:model="formAcompanante.nombre" class="form-control form-control-sm">
                            @endif

                            @error('acompanante') <div class="alert alert-danger small py-2 mt-3 mb-0" role="alert">{{ $message }}</div> @enderror
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="cerrarAccion">Cerrar</button>
                            <button type="button" class="btn btn-primary btn-sm" wire:click="guardarAcompanante">Añadir acompañante</button>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
