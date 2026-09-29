@php
    use Carbon\Carbon;
    use Modules\Intervencion\Enums\TipoApunte;
    use Modules\Intervencion\Support\Ui\Tonos;

    $ciudadano = $this->ciudadano;
    $piso      = $this->pisoActivo;

    $estadoEtiqueta = match($historia->estado) {
        'en_seguimiento' => 'En seguimiento',
        'cerrada'        => 'Cerrada',
        default          => 'Abierta',
    };

    $herramientas = [
        ['id' => 'entrevista', 'label' => 'Entrevista',  'icon' => 'chat-bubble-left',         'fullpage' => false],
        ['id' => 'anotacion',  'label' => 'Anotación',   'icon' => 'pencil',                   'fullpage' => false],
        ['id' => 'derivacion', 'label' => 'Derivación',  'icon' => 'arrow-right-circle',       'fullpage' => false],
        ['id' => 'gestion',    'label' => 'Gestión',     'icon' => 'share',                    'fullpage' => false],
        ['id' => 'valoracion', 'label' => 'Valoración',  'icon' => 'clipboard-document-check', 'fullpage' => true],
        ['id' => 'escala',     'label' => 'Escala',      'icon' => 'chart-bar',                'fullpage' => true],
        ['id' => 'informes',   'label' => 'Informes',    'icon' => 'document-text',            'fullpage' => true],
    ];
    $historiaAbierta = $historia->estado !== 'cerrada';
@endphp

<div class="op-page op-page--fill">

    {{-- Escribir un mensaje sobre este expediente (panel de redacción global) --}}
    <div class="d-flex justify-content-end px-3 pt-2">
        @include('mensajes::partials.boton-escribir-mensaje', ['tipo' => 'historia', 'id' => $historia->id])
    </div>

    {{-- ------------------------------------------------------------------ --}}
    {{-- Banda del Plan de Intervención — ancho completo                    --}}
    {{-- ------------------------------------------------------------------ --}}
    @if($this->planActivo)
        <div class="d-flex flex-wrap align-items-center gap-3 px-3 py-2 border-bottom bg-primary-subtle small">
            <span class="fw-bold text-primary-emphasis">{{ $this->planNombreCorto }} · {{ $this->planActivo->estado->label() }}</span>
            <span class="text-body-secondary">v{{ $this->planActivo->version }} · desde {{ Carbon::parse($this->planActivo->fecha_inicio)->format('d/m/Y') }}</span>
            <a href="{{ route('intervencion.plan.show', $this->planActivo) }}" wire:navigate
               class="btn btn-sm btn-outline-primary ms-auto">
                Ver {{ $this->planNombreCorto }} →
            </a>
        </div>
    @else
        <div class="d-flex flex-wrap align-items-center gap-3 px-3 py-2 border-bottom bg-body small text-body-secondary">
            Sin {{ $this->planNombreCorto }} activo
            <a href="{{ route('intervencion.plan.crear', ['historia' => $this->historia->id]) }}" wire:navigate
               class="btn btn-sm btn-outline-primary ms-auto">
                + Crear {{ $this->planNombreCorto }}
            </a>
        </div>
    @endif

    {{-- ------------------------------------------------------------------ --}}
    {{-- Banda de recursos prescritos — visible si hay prescripciones activas --}}
    {{-- ------------------------------------------------------------------ --}}
    @if($historiaAbierta && $this->prescripcionesActivas->isNotEmpty())
        <div class="d-flex flex-wrap align-items-center gap-3 px-3 py-2 border-bottom bg-body-tertiary small">
            <span class="fw-bold d-inline-flex align-items-center gap-1">
                <x-heroicon-o-building-storefront class="icon-16" aria-hidden="true"/>
                Recursos prescritos ({{ $this->prescripcionesActivas->count() }})
            </span>
            <div class="d-flex gap-2 flex-wrap">
                @foreach($this->prescripcionesActivas as $presc)
                    <span class="badge bg-secondary">
                        {{ ucfirst(str_replace('_', ' ', $presc->estado)) }}
                        · {{ ucfirst($presc->tipo_destino === 'coleccion_plazas' ? 'Plaza' : 'Actividad') }}
                    </span>
                    <button type="button"
                            wire:click="cancelarPrescripcion({{ $presc->id }})"
                            class="btn btn-sm btn-outline-danger py-0"
                            title="Cancelar prescripción"
                            aria-label="Cancelar prescripción"
                            wire:confirm="¿Cancelar esta prescripción?">
                        <x-heroicon-o-x-mark class="icon-12" aria-hidden="true"/>
                    </button>
                @endforeach
            </div>
        </div>
    @endif

    {{-- ------------------------------------------------------------------ --}}
    {{-- Cabecera: datos del ciudadano (izquierda) + herramientas (derecha) --}}
    {{-- ------------------------------------------------------------------ --}}
    <div class="row g-0 bg-white border-bottom">

        {{-- ZONA SUPERIOR IZQUIERDA — datos del ciudadano + UC colapsable --}}
        <div class="col-4 border-end p-3">

            {{-- Fila superior: retorno + acciones --}}
            <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                <a href="{{ route('intervencion.casos.index') }}"
                   class="link-secondary small text-decoration-none d-inline-flex align-items-center gap-1">
                    <x-heroicon-o-arrow-left class="icon-12" aria-hidden="true"/> Mis casos
                </a>
                <div>
                    @if($ciudadano)
                        <a href="{{ route('ciudadania.ciudadano.ficha', $ciudadano->id) }}"
                           wire:navigate
                           class="btn btn-sm btn-outline-primary">
                            Ficha completa
                        </a>
                    @endif
                    {{-- TODO: menú ⋯ con acciones adicionales del expediente --}}
                </div>
            </div>

            {{-- Nombre completo --}}
            <div class="fs-5 fw-bold mb-1">
                {{ $ciudadano ? ($ciudadano->nombre . ' ' . $ciudadano->apellido1 . ($ciudadano->apellido2 ? ' ' . $ciudadano->apellido2 : '')) : 'Ciudadano #' . $historia->ciudadano_id }}
            </div>

            {{-- HS + UO + Estado HS --}}
            <div class="d-flex flex-wrap align-items-center gap-1 small text-body-secondary mb-1">
                <span>HS #{{ $historia->id }}</span>
                <span aria-hidden="true">·</span>
                @if($this->uoNombre)
                    <span>{{ $this->uoNombre }}</span>
                @else
                    <span>UO #{{ $historia->unidad_organizativa_id }}</span>
                @endif
                <span class="badge rounded-pill {{ Tonos::estadoHistoria($historia->estado)->clasesSuave() }}">
                    Estado HS: {{ $estadoEtiqueta }}
                </span>
            </div>

            {{-- Fecha de nacimiento · edad --}}
            @if($ciudadano?->fecha_nacimiento)
                <div class="small">
                    {{ Carbon::parse($ciudadano->fecha_nacimiento)->format('d/m/Y') }} · {{ Carbon::parse($ciudadano->fecha_nacimiento)->age }} años
                </div>
            @endif

            {{-- Domicilio --}}
            @if($ciudadano?->direccion_texto)
                <div class="small">
                    {{ $ciudadano->direccion_texto }}
                </div>
            @endif

            {{-- Documento · Teléfono · Email --}}
            @php
                $contacto = array_filter([$this->ciudadanoDocumento, $this->ciudadanoTelefono, $this->ciudadanoEmail]);
            @endphp
            @if($contacto)
                <p class="small text-body-secondary mb-1">{{ implode(' · ', $contacto) }}</p>
            @endif

            {{-- Representante (solo si existe relación activa) --}}
            @if($this->representante)
            <div class="d-flex align-items-center gap-2 small mt-1">
                <span class="text-body-secondary">Representante</span>
                <button
                    type="button"
                    wire:click="abrirModalRepresentante"
                    class="btn btn-link btn-sm p-0 fw-semibold text-decoration-none d-inline-flex align-items-center gap-1"
                    title="Ver datos de contacto del representante"
                >
                    {{ $this->representante->nombre }}
                    {{ $this->representante->apellido1 }}
                    {{ $this->representante->apellido2 }}
                    <x-heroicon-o-chevron-right class="icon-12" aria-hidden="true"/>
                </button>
            </div>
            @endif

            {{-- Unidad de convivencia: acordeón de Bootstrap; la apertura la gobierna Livewire (toggleUC) --}}
            <div class="accordion mt-3">
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button type="button" wire:click="toggleUC"
                                class="accordion-button py-2 px-3 small {{ $ucExpandida ? '' : 'collapsed' }}"
                                aria-expanded="{{ $ucExpandida ? 'true' : 'false' }}">
                            <span>
                                Unidad de convivencia
                                @if($this->ucVigente)
                                    <span class="text-body-secondary ms-1">
                                        {{ $this->ucMiembrosActivos->count() }} miembro{{ $this->ucMiembrosActivos->count() !== 1 ? 's' : '' }}
                                    </span>
                                @endif
                            </span>
                        </button>
                    </h2>
                    @if($ucExpandida)
                        <div class="accordion-collapse collapse show">
                            <div class="accordion-body p-2">
                                @if($this->ucVigente)
                                    <ul class="list-unstyled small mb-2">
                                        @foreach($this->ucMiembrosActivos as $ucm)
                                            <li class="d-flex align-items-center gap-1 py-1">
                                                @if($ucm->verificado)
                                                    <x-heroicon-o-shield-check class="icon-14 text-success flex-shrink-0" aria-hidden="true"/>
                                                @else
                                                    <x-heroicon-o-shield-exclamation class="icon-14 text-warning flex-shrink-0" aria-hidden="true"/>
                                                @endif
                                                @if($ucm->ciudadano)
                                                    @php $tipoRelUc = $this->relacionesMiembrosUc->get($ucm->ciudadano_id); @endphp
                                                    <a href="{{ route('ciudadania.ciudadano.ficha', $ucm->ciudadano) }}" class="text-decoration-none">
                                                        {{ $ucm->ciudadano->nombre }} {{ $ucm->ciudadano->apellido1 }}
                                                    </a>
                                                    @if($tipoRelUc)
                                                        <span class="text-body-secondary">{{ $tipoRelUc }}</span>
                                                    @endif
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                @else
                                    <p class="small text-body-secondary fst-italic mb-2">Sin unidad de convivencia registrada.</p>
                                @endif
                                <div class="d-flex flex-wrap gap-2">
                                    {{-- Botón gestionar UC --}}
                                    <button type="button" wire:click="abrirModalUc" class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1" title="Gestionar unidad de convivencia">
                                        <x-heroicon-o-users class="icon-14" aria-hidden="true"/>
                                        Gestionar UC
                                    </button>
                                    {{-- Botón para ver todas las relaciones del ciudadano --}}
                                    <button
                                        type="button"
                                        wire:click="abrirModalRelaciones"
                                        class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1"
                                        title="Ver todas las personas relacionadas"
                                    >
                                        <x-heroicon-o-share class="icon-12" aria-hidden="true"/>
                                        Ver todas las relaciones
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

        </div>

        {{-- ZONA SUPERIOR DERECHA — toolbox de herramientas --}}
        <div class="col-8 p-3">

            <div wire:key="toolbox-grid" class="row row-cols-4 g-2">
                @foreach($herramientas as $h)
                    <div class="col">
                        <button wire:key="tool-{{ $h['id'] }}"
                                type="button"
                                wire:click="seleccionarHerramienta('{{ $h['id'] }}')"
                                @if($herramientaActiva === $h['id']) aria-pressed="true" @endif
                                @class([
                                    'btn w-100 h-100 d-flex flex-column align-items-center justify-content-center gap-1 py-3',
                                    'btn-primary fw-bold' => $herramientaActiva === $h['id'],
                                    'btn-outline-secondary' => $herramientaActiva !== $h['id'],
                                ])>
                            <x-dynamic-component :component="'heroicon-o-' . $h['icon']" class="icon-20" aria-hidden="true"/>
                            <span class="small lh-sm">
                                {{ $h['label'] }}
                                @if($h['fullpage'])
                                    <span class="d-block small fw-normal opacity-75">↗ pantalla completa</span>
                                @endif
                            </span>
                        </button>
                    </div>
                @endforeach

                {{-- Herramienta «Prescribir recurso» — visible solo con Historia Social abierta --}}
                @if($historiaAbierta)
                    <div class="col">
                        <button type="button"
                                wire:click="abrirPrescribirRecurso"
                                class="btn btn-outline-secondary w-100 h-100 d-flex flex-column align-items-center justify-content-center gap-1 py-3">
                            <x-heroicon-o-building-storefront class="icon-20" aria-hidden="true"/>
                            <span class="small lh-sm">Prescribir recurso</span>
                        </button>
                    </div>
                @endif
            </div>

        </div>
    </div>

    {{-- ------------------------------------------------------------------ --}}
    {{-- Cuerpo: línea de tiempo (izquierda) + área de trabajo (derecha)    --}}
    {{-- ------------------------------------------------------------------ --}}
    <div class="row g-0 flex-grow-1 overflow-hidden">

        {{-- ZONA INFERIOR IZQUIERDA — filtros + timeline --}}
        <div class="col-4 h-100 overflow-auto border-end p-3">

            {{-- Filtros del timeline --}}
            <div class="d-flex flex-wrap gap-1 mb-3">
                @foreach([
                    ['todos',      'Todos'],
                    ['plan',       $this->planNombreCorto],
                    ['entrevista', 'Entrevista'],
                    ['anotacion',  'Anotación'],
                    ['derivacion', 'Derivación'],
                    ['gestion',    'Gestión'],
                    ['valoracion', 'Valoración'],
                    ['escala',     'Escala'],
                ] as [$filtroKey, $filtroLabel])
                    @php
                        $esActivo   = $filtroHS === $filtroKey;
                        $esSugerido = ! $esActivo && $filtroSugerido === $filtroKey;
                    @endphp
                    <button type="button" wire:click="setFiltroHS('{{ $filtroKey }}')"
                            class="btn btn-sm {{ $esActivo ? 'btn-primary' : ($esSugerido ? 'btn-outline-primary' : 'btn-outline-secondary') }}">
                        {{ $filtroLabel }}@if($esSugerido)<span class="small opacity-75 ms-1" title="Filtrar por este tipo">↑</span>@endif
                    </button>
                @endforeach
            </div>

            {{-- Timeline de apuntes --}}
            @if($this->apuntesHS->isNotEmpty())
                <div class="list-group list-group-flush">
                    @foreach($this->apuntesHS as $apunte)
                        <div wire:click="verApunte({{ $apunte->id }})"
                             role="button"
                             class="list-group-item list-group-item-action bg-transparent d-flex gap-2 px-2 rounded">
                            <span class="d-inline-block rounded-circle p-1 mt-2 flex-shrink-0 align-self-start {{ $apunte->tipo->tono()->clasesPunto() }}" aria-hidden="true"></span>
                            <div class="flex-grow-1 text-break">
                                <div class="small fw-semibold">
                                    {{ $apunte->tipo->label() }}
                                </div>
                                <div class="small text-body-secondary">
                                    {{ $apunte->fecha->format('d/m/Y') }} · {{ $apunte->autor?->name ?? '' }}
                                </div>
                                @if($apunte->contenido)
                                    <div class="small text-truncate">{{ $apunte->contenido }}</div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="small text-body-secondary text-center py-4 mb-0">
                    Sin registros en la historia social.
                </p>
            @endif

        </div>

        {{-- ZONA INFERIOR DERECHA — área de trabajo activa + estadísticas --}}
        <div class="col-8 h-100 d-flex flex-column">

            {{-- Área de trabajo de la herramienta activa --}}
            <div class="flex-grow-1 overflow-auto p-3">

                @if($herramientaActiva === 'entrevista')
                    <div class="card card-body mb-3">
                        <h3 class="h6 fw-bold mb-3">Registrar entrevista</h3>
                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <label for="entrevista-tipo" class="form-label small fw-semibold mb-1">Tipo</label>
                                <select id="entrevista-tipo" wire:model="formEntrevista.tipo" class="form-select form-select-sm">
                                    <option value="seguimiento">Seguimiento</option>
                                    <option value="inicial">Inicial</option>
                                    <option value="urgencia">Urgencia</option>
                                    <option value="informativa">Informativa</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="entrevista-modalidad" class="form-label small fw-semibold mb-1">Modalidad</label>
                                <select id="entrevista-modalidad" wire:model="formEntrevista.modalidad" class="form-select form-select-sm">
                                    <option value="presencial">Presencial</option>
                                    <option value="telefonica">Telefónica</option>
                                    <option value="videollamada">Videollamada</option>
                                    <option value="domicilio">Domicilio</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="entrevista-notas" class="form-label small fw-semibold mb-1">Notas generales</label>
                            <textarea id="entrevista-notas" wire:model="formEntrevista.notas" rows="3" class="form-control form-control-sm" placeholder="Observaciones de la entrevista..."></textarea>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="entrevista-programar" wire:model="formEntrevista.programar_seguimiento">
                            <label class="form-check-label small" for="entrevista-programar">Programar siguiente seguimiento</label>
                        </div>
                        @if($formEntrevista['programar_seguimiento'])
                            <div class="mb-3">
                                <label for="entrevista-fecha-seguimiento" class="form-label small fw-semibold mb-1">Fecha siguiente seguimiento</label>
                                <input id="entrevista-fecha-seguimiento" type="date" wire:model="formEntrevista.fecha_siguiente_seguimiento" class="form-control form-control-sm w-auto">
                            </div>
                        @endif
                        <div class="d-flex gap-2">
                            <button type="button" wire:click="guardarEntrevista" class="btn btn-primary btn-sm">Guardar entrevista</button>
                            <button type="button" wire:click="cancelarHerramienta" class="btn btn-outline-secondary btn-sm">Cancelar</button>
                        </div>
                    </div>

                @elseif($herramientaActiva === 'anotacion')
                    <div class="card card-body mb-3">
                        <h3 class="h6 fw-bold mb-3">Guardar anotación</h3>
                        <div class="mb-3">
                            <label for="anotacion-contenido" class="visually-hidden">Anotación</label>
                            <textarea id="anotacion-contenido" wire:model="formAnotacion.contenido" rows="4" class="form-control form-control-sm" placeholder="Escribe la anotación..."></textarea>
                        </div>
                        <div class="mb-3">
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" id="anotacion-profesionales" wire:model="formAnotacion.visibilidad" value="profesionales">
                                <label class="form-check-label small" for="anotacion-profesionales">Para profesionales</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" id="anotacion-privada" wire:model="formAnotacion.visibilidad" value="privada">
                                <label class="form-check-label small" for="anotacion-privada">Privada (solo yo)</label>
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" wire:click="guardarAnotacion" class="btn btn-primary btn-sm">Guardar anotación</button>
                            <button type="button" wire:click="cancelarHerramienta" class="btn btn-outline-secondary btn-sm">Cancelar</button>
                        </div>
                    </div>

                @elseif($herramientaActiva === 'derivacion')
                    <div class="card card-body mb-3">
                        <h3 class="h6 fw-bold mb-3">Crear derivación</h3>
                        <div class="mb-3">
                            <label for="derivacion-urgencia" class="form-label small fw-semibold mb-1">Urgencia</label>
                            <select id="derivacion-urgencia" wire:model="formDerivacion.urgencia" class="form-select form-select-sm">
                                <option value="ordinaria">Ordinaria</option>
                                <option value="preferente">Preferente</option>
                                <option value="urgente">Urgente</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="derivacion-motivo" class="form-label small fw-semibold mb-1">Motivo</label>
                            <textarea id="derivacion-motivo" wire:model="formDerivacion.motivo" rows="3" class="form-control form-control-sm" placeholder="Motivo de la derivación..."></textarea>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" wire:click="crearDerivacion" class="btn btn-primary btn-sm">Crear derivación</button>
                            <button type="button" wire:click="cancelarHerramienta" class="btn btn-outline-secondary btn-sm">Cancelar</button>
                        </div>
                    </div>

                @elseif($herramientaActiva === 'gestion')
                    <div class="card card-body mb-3">
                        <h3 class="h6 fw-bold mb-3">Guardar gestión</h3>
                        <div class="mb-3">
                            <label for="gestion-tipo" class="form-label small fw-semibold mb-1">Tipo de gestión</label>
                            <select id="gestion-tipo" wire:model="formGestion.tipo_gestion" class="form-select form-select-sm">
                                <option value="">Selecciona...</option>
                                <option value="coordinacion">Coordinación con otro servicio</option>
                                <option value="tramite">Trámite administrativo</option>
                                <option value="mesa_trabajo">Mesa de trabajo</option>
                                <option value="contacto_familia">Contacto con familia</option>
                                <option value="otro">Otro</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="gestion-interlocutor" class="form-label small fw-semibold mb-1">Recurso / interlocutor</label>
                            <input id="gestion-interlocutor" type="text" wire:model="formGestion.recurso_interlocutor" class="form-control form-control-sm" placeholder="Nombre del recurso o persona...">
                        </div>
                        <div class="mb-3">
                            <label for="gestion-descripcion" class="form-label small fw-semibold mb-1">Descripción</label>
                            <textarea id="gestion-descripcion" wire:model="formGestion.descripcion" rows="3" class="form-control form-control-sm" placeholder="Describe la gestión realizada..."></textarea>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" wire:click="guardarGestion" class="btn btn-primary btn-sm">Guardar gestión</button>
                            <button type="button" wire:click="cancelarHerramienta" class="btn btn-outline-secondary btn-sm">Cancelar</button>
                        </div>
                    </div>

                @elseif($herramientaActiva === 'valoracion')
                    <div class="card card-body mb-3">
                        <h3 class="h6 fw-bold mb-1">Valoración</h3>
                        <p class="small text-body-secondary mb-3">La ficha se abrirá en pantalla completa.</p>
                        <div class="mb-3">
                            <label for="valoracion-tipo-ficha" class="form-label small fw-semibold mb-1">Tipo de ficha</label>
                            <select id="valoracion-tipo-ficha" wire:model.live="formValoracion.tipo_ficha_id" class="form-select form-select-sm">
                                <option value="">Selecciona...</option>
                                @foreach($this->tiposFicha as $tf)
                                    <option value="{{ $tf->id }}">{{ $tf->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="d-flex gap-2">
                            @if($formValoracion['tipo_ficha_id'])
                                <a href="{{ route('intervencion.valoracion.nueva', ['historia' => $historia->id, 'tipo_ficha' => $formValoracion['tipo_ficha_id']]) }}"
                                   wire:navigate
                                   class="btn btn-primary btn-sm">Abrir en pantalla completa</a>
                            @endif
                            <button type="button" wire:click="cancelarHerramienta" class="btn btn-outline-secondary btn-sm">Cancelar</button>
                        </div>
                    </div>

                @elseif($herramientaActiva === 'escala')
                    <div class="card card-body mb-3">
                        <h3 class="h6 fw-bold mb-1">Escala</h3>
                        <p class="small text-body-secondary mb-3">La escala se abrirá en pantalla completa.</p>
                        <div class="mb-3">
                            <label for="escala-instrumento" class="form-label small fw-semibold mb-1">Instrumento</label>
                            <select id="escala-instrumento" wire:model.live="formEscala.tipo_escala_id" class="form-select form-select-sm">
                                <option value="">Selecciona...</option>
                                @foreach($this->tiposEscala as $te)
                                    <option value="{{ $te->id }}">{{ $te->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="d-flex gap-2">
                            @if($formEscala['tipo_escala_id'])
                                <a href="{{ route('intervencion.escala.nueva', ['historia' => $historia->id, 'tipo_escala' => $formEscala['tipo_escala_id']]) }}"
                                   class="btn btn-primary btn-sm">Abrir en pantalla completa</a>
                            @endif
                            <button type="button" wire:click="cancelarHerramienta" class="btn btn-outline-secondary btn-sm">Cancelar</button>
                        </div>
                    </div>

                @elseif($herramientaActiva === 'informes')
                    <div class="card card-body mb-3">
                        <h3 class="h6 fw-bold mb-1">Informes</h3>
                        {{-- TODO: conectar con módulo Documentos cuando implemente la vista de edición --}}
                        <p class="small text-body-secondary mb-2">Módulo de informes en construcción.</p>
                        <div>
                            <button type="button" wire:click="cancelarHerramienta" class="btn btn-outline-secondary btn-sm">Cerrar</button>
                        </div>
                    </div>

                @endif

                {{-- ── Últimos accesos al expediente ──────────────────────── --}}
                <section class="border-top pt-3" aria-labelledby="titulo-accesos">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h3 id="titulo-accesos" class="small text-uppercase fw-bold text-body-secondary mb-0">Últimos accesos</h3>
                        @if($this->puedeVerTodosLosAccesos)
                            {{-- TODO: modal historial completo --}}
                            <a href="#" class="btn btn-link btn-sm text-decoration-none px-0">Ver todo</a>
                        @endif
                    </div>

                    @if($this->accesosRecientes->isNotEmpty())
                        <div class="list-group list-group-flush">
                            @foreach($this->accesosRecientes as $acceso)
                                @php
                                    $esPropio  = $acceso->user_id === Auth::id();
                                    $uoAcceso  = $acceso->contexto['unidad_organizativa_id'] ?? null;
                                    $uoAcceso  = $uoAcceso ?? $acceso->user?->profesional?->unidad_organizativa_id;
                                    $esOtraUo  = $uoAcceso !== null && $uoAcceso !== $historia->unidad_organizativa_id;
                                    $esCambio  = in_array($acceso->accion?->value, ['crear', 'editar', 'eliminar']);
                                    // Otra UO: la lectura es sospechosa; la modificación, una anomalía grave que hay que revisar
                                    $esAnomalo    = $esOtraUo && $esCambio;
                                    $esSospechoso = $esOtraUo && ! $esCambio;
                                    $tipoAcceso   = $esAnomalo ? 'anomalo' : ($esSospechoso ? 'sospechoso' : ($esPropio ? 'propio' : 'normal'));
                                @endphp
                                <div data-acceso="{{ $tipoAcceso }}" @class([
                                    'list-group-item px-2 py-2 small',
                                    'bg-transparent' => ! $esOtraUo,
                                    'opacity-75' => $esPropio,
                                    'bg-warning-subtle rounded' => $esSospechoso,
                                    'bg-danger-subtle border-start border-3 border-danger rounded' => $esAnomalo,
                                ])>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="fw-semibold">
                                            {{ $acceso->user?->profesional?->nombre_completo ?? $acceso->user?->name ?? '—' }}
                                        </span>
                                        @if($esOtraUo)
                                            <span @class(['badge', 'bg-danger-subtle text-danger-emphasis' => $esAnomalo, 'bg-warning-subtle text-warning-emphasis' => ! $esAnomalo]) title="Profesional de otra UO">Otra UO</span>
                                        @endif
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <span @class(['fw-semibold' => $esCambio, 'text-body-secondary' => ! $esCambio])>
                                            {{ $acceso->accion?->etiqueta() ?? '—' }}
                                        </span>
                                        @if($esAnomalo)
                                            <span class="text-danger" title="Modificación desde otra UO — revisar">
                                                <x-heroicon-o-exclamation-triangle class="icon-14" aria-hidden="true"/>
                                                <span class="visually-hidden">Modificación desde otra UO — revisar</span>
                                            </span>
                                        @endif
                                        <span class="text-body-secondary ms-auto">{{ $acceso->created_at->diffForHumans() }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="small text-body-secondary mb-0">Sin accesos registrados.</p>
                    @endif
                </section>

            </div>

            {{-- Barra de estadísticas de contexto --}}
            <div class="d-flex border-top bg-white text-center">
                <div class="flex-fill py-2 border-end">
                    <div class="fw-semibold text-primary">{{ $this->statApuntes }}</div>
                    <div class="small text-body-secondary">Apuntes</div>
                </div>
                <div class="flex-fill py-2 border-end">
                    <div class="fw-semibold text-primary">{{ $this->statPrestaciones ?? '—' }}</div>
                    <div class="small text-body-secondary">Prestaciones activas</div>
                </div>
                <div class="flex-fill py-2">
                    <div class="fw-semibold text-primary">{{ $this->statUltimoContacto ?? '—' }}</div>
                    <div class="small text-body-secondary">Último contacto</div>
                </div>
            </div>

        </div>

    </div>

    {{-- ================================================================== --}}
    {{-- Modal de detalle de apunte — genérico (entrevista, anotación, etc.) --}}
    {{-- ================================================================== --}}
    @if($modalApunteAbierto && ! in_array($modalApunteTipo, ['escala', 'valoracion']))
    <div class="modal fade show d-block"
         wire:click.self="cerrarModalApunte"
         x-data x-on:keydown.escape.window="$wire.cerrarModalApunte()"
         role="dialog" aria-modal="true" aria-label="Detalle del apunte" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 shadow">
                <div class="modal-header gap-2">
                    <span class="fw-semibold">{{ $modalApunteDatos['tipo_label'] ?? '' }}</span>
                    <span class="small text-body-secondary">{{ $modalApunteDatos['fecha'] ?? '' }}</span>
                    <button wire:click="cerrarModalApunte" type="button" class="btn-close" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="small"><strong>Profesional:</strong> {{ $modalApunteDatos['autor'] ?? '—' }}</p>
                    @if($modalApunteDatos['contenido'] ?? null)
                        <div>{!! nl2br(e($modalApunteDatos['contenido'])) !!}</div>
                    @endif
                </div>
                <div class="modal-footer justify-content-between">
                    <span class="small text-body-tertiary">Solo lectura · El pasado es inmutable</span>
                    <button type="button" wire:click="cerrarModalApunte" class="btn btn-outline-secondary btn-sm">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal-backdrop fade show"></div>
    @endif

    {{-- ================================================================== --}}
    {{-- Panel lateral de detalle — escala y valoración (ancho amplio)       --}}
    {{-- ================================================================== --}}
    @if($modalApunteAbierto && in_array($modalApunteTipo, ['escala', 'valoracion']))
    <div class="modal-backdrop fade show"></div>
    <div class="offcanvas offcanvas-end show d-block border-start shadow"
         wire:click.self="cerrarModalApunte"
         x-data x-on:keydown.escape.window="$wire.cerrarModalApunte()"
         role="dialog" aria-modal="true" tabindex="-1">
        <div class="offcanvas-header gap-2">
            <span class="fw-semibold">{{ $modalApunteDatos['tipo_label'] ?? '' }}</span>
            <span class="small text-body-secondary">{{ $modalApunteDatos['fecha'] ?? '' }}</span>
            <button wire:click="cerrarModalApunte" type="button" class="btn-close ms-auto" aria-label="Cerrar"></button>
        </div>
        <div class="offcanvas-body d-flex flex-column gap-3">
            <p class="small mb-0"><strong>Profesional:</strong> {{ $modalApunteDatos['autor'] ?? '—' }}</p>

            @if($modalApunteTipo === 'escala')
                @if($modalApunteDatos['escala_nombre'] ?? null)
                    <h3 class="h6 fw-bold mb-0">{{ $modalApunteDatos['escala_nombre'] }}</h3>
                @endif
                @if(isset($modalApunteDatos['escala_score']))
                    <div class="d-flex align-items-baseline gap-2">
                        <span class="fs-2 fw-bold text-primary">{{ $modalApunteDatos['escala_score'] }}</span>
                        @if($modalApunteDatos['escala_interpretacion'] ?? null)
                            <span class="text-body-secondary">{{ $modalApunteDatos['escala_interpretacion'] }}</span>
                        @endif
                    </div>
                @endif
                @if(! empty($modalApunteDatos['escala_secciones']))
                    <ul class="list-group">
                        @foreach($modalApunteDatos['escala_secciones'] as $sec => $score)
                            <li class="list-group-item d-flex justify-content-between small">
                                <span>{{ $sec }}</span>
                                <span class="fw-semibold">{{ $score }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            @endif

            @if($modalApunteTipo === 'valoracion' && ! empty($modalApunteDatos['ficha_campos']))
                <div class="d-flex flex-column gap-2">
                    @foreach($modalApunteDatos['ficha_campos'] as $campo)
                        <div class="bg-body-tertiary border rounded p-2">
                            <p class="small text-uppercase fw-semibold text-body-secondary mb-1">{{ $campo['etiqueta'] }}</p>
                            @if(filled($campo['valor']))
                                <p class="mb-0">
                                    @if($campo['tipo'] === 'booleano')
                                        {{ $campo['valor'] ? 'Sí' : 'No' }}
                                    @elseif($campo['tipo'] === 'fecha')
                                        {{ \Carbon\Carbon::parse($campo['valor'])->translatedFormat('j M Y') }}
                                    @else
                                        {{ $campo['valor'] }}{{ $campo['unidad'] ? ' '.$campo['unidad'] : '' }}
                                    @endif
                                </p>
                            @else
                                <p class="small text-body-tertiary fst-italic mb-0">Sin respuesta</p>
                            @endif
                        </div>
                    @endforeach
                </div>
                @if($modalApunteDatos['ficha_notas'] ?? null)
                    <div class="border-top pt-2">
                        <p class="small text-uppercase fw-semibold text-body-secondary mb-1">Notas</p>
                        <p class="mb-0">{{ $modalApunteDatos['ficha_notas'] }}</p>
                    </div>
                @endif
            @endif

            @if($modalApunteDatos['contenido'] ?? null)
                <div class="border-top pt-2">{!! nl2br(e($modalApunteDatos['contenido'])) !!}</div>
            @endif
        </div>
        <div class="border-top d-flex align-items-center justify-content-between gap-3 px-4 py-3">
            <span class="small text-body-tertiary">Solo lectura · El pasado es inmutable</span>
            <div class="d-flex align-items-center gap-2 flex-wrap justify-content-end">
                @if(($modalApunteDatos['ficha_url'] ?? null))
                    <a href="{{ $modalApunteDatos['ficha_url'] }}" wire:navigate
                       class="btn btn-link btn-sm text-decoration-none px-0 d-inline-flex align-items-center gap-1">
                        <x-heroicon-o-arrow-top-right-on-square class="icon-14" aria-hidden="true"/>
                        Ver ficha completa
                    </a>
                @endif
                <button type="button" wire:click="cerrarModalApunte" class="btn btn-outline-secondary btn-sm">Cerrar</button>
            </div>
        </div>
    </div>
    @endif

    {{-- ================================================================== --}}
    {{-- MODAL: GESTIÓN DE UNIDAD DE CONVIVENCIA                            --}}
    {{-- ================================================================== --}}
    @if($this->modalUcAbierto)
    <div
        class="modal fade show d-block"
        wire:click.self="cerrarModalUc"
        x-data
        x-on:keydown.escape.window="$wire.cerrarModalUc()"
        role="dialog"
        aria-modal="true"
        aria-labelledby="uc-modal-titulo"
        tabindex="-1"
    >
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
            <div class="modal-content border-0 shadow">

                <div class="modal-header">
                    <h2 id="uc-modal-titulo" class="modal-title fs-6">Unidad de convivencia</h2>
                    <button wire:click="cerrarModalUc" type="button" class="btn-close" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body">

                    @if($ucMensaje)
                    <div class="alert alert-success py-2 small d-flex align-items-center gap-2" role="status" wire:key="uc-mensaje">
                        <x-heroicon-o-check-circle class="icon-14" aria-hidden="true"/>
                        {{ $ucMensaje }}
                    </div>
                    @endif

                    @if(! $this->ucVigente)
                        <div class="text-center py-3">
                            <p>Este ciudadano no tiene unidad de convivencia registrada.</p>
                            <button type="button" wire:click="crearUc" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1">
                                <x-heroicon-o-plus class="icon-14" aria-hidden="true"/>
                                Crear unidad de convivencia
                            </button>
                        </div>

                    @else
                        <section class="mb-4">
                            <h3 class="h6 fw-semibold d-flex align-items-center gap-2">
                                Miembros activos
                                <span class="badge rounded-pill text-bg-secondary">{{ $this->ucMiembrosActivos->count() }}</span>
                            </h3>

                            <ul class="list-group list-group-flush">
                                @forelse($this->ucMiembrosActivos as $miembro)
                                <li class="list-group-item d-flex align-items-center justify-content-between gap-2 px-0" wire:key="miembro-{{ $miembro->id }}">
                                    <div>
                                        @if($miembro->ciudadano)
                                        <a href="{{ route('ciudadania.ciudadano.ficha', $miembro->ciudadano) }}" class="fw-semibold text-decoration-none">
                                            {{ $miembro->ciudadano->nombre }}
                                            {{ $miembro->ciudadano->apellido1 }}
                                            {{ $miembro->ciudadano->apellido2 }}
                                        </a>
                                        @else
                                        <span class="fw-semibold">—</span>
                                        @endif
                                        <div class="small text-body-secondary">
                                            Desde {{ $miembro->fecha_inicio?->format('d/m/Y') }}
                                        </div>
                                    </div>

                                    <div class="d-flex align-items-center gap-2 flex-shrink-0">
                                        @if($miembro->verificado)
                                            <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle d-inline-flex align-items-center gap-1" title="Residencia verificada">
                                                <x-heroicon-o-shield-check class="icon-12" aria-hidden="true"/>
                                                Verificado
                                            </span>
                                        @else
                                            <button
                                                type="button"
                                                wire:click="verificarMiembro({{ $miembro->id }})"
                                                class="btn btn-sm btn-outline-warning d-inline-flex align-items-center gap-1 py-0"
                                                title="Verificar residencia manualmente"
                                            >
                                                <x-heroicon-o-shield-exclamation class="icon-12" aria-hidden="true"/>
                                                Sin verificar
                                            </button>
                                        @endif

                                        @if($ucMiembroParaBaja === $miembro->id)
                                            <span class="d-inline-flex align-items-center gap-1 small">
                                                ¿Confirmar baja?
                                                <button type="button" wire:click="confirmarBajaMiembro" class="btn btn-danger btn-sm">Sí</button>
                                                <button type="button" wire:click="cancelarBajaMiembro" class="btn btn-outline-secondary btn-sm">No</button>
                                            </span>
                                        @else
                                            <button
                                                type="button"
                                                wire:click="iniciarBajaMiembro({{ $miembro->id }})"
                                                class="btn btn-outline-secondary btn-sm"
                                                title="Dar de baja como miembro"
                                                aria-label="Dar de baja como miembro"
                                            >
                                                <x-heroicon-o-user-minus class="icon-14" aria-hidden="true"/>
                                            </button>
                                        @endif
                                    </div>
                                </li>
                                @empty
                                <li class="list-group-item px-0 small text-body-secondary">No hay miembros activos.</li>
                                @endforelse
                            </ul>
                        </section>

                        <section>
                            <h3 class="h6 fw-semibold">Añadir miembro</h3>

                            @if($ucCiudadanoSeleccionado)
                                @php $cSeleccionado = \App\Models\Ciudadano::find($ucCiudadanoSeleccionado); @endphp
                                <div class="alert alert-primary d-flex flex-wrap align-items-center justify-content-between gap-2 small">
                                    <span>
                                        ¿Añadir a <strong>{{ $cSeleccionado?->nombre }} {{ $cSeleccionado?->apellido1 }}</strong> como miembro de esta unidad?
                                    </span>
                                    <div class="d-flex gap-2">
                                        <button type="button" wire:click="confirmarAnadirMiembro" class="btn btn-primary btn-sm">Confirmar</button>
                                        <button type="button" wire:click="cancelarSeleccionUc" class="btn btn-outline-secondary btn-sm">Cancelar</button>
                                    </div>
                                </div>

                            @else
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text" aria-hidden="true">
                                        <x-heroicon-o-magnifying-glass class="icon-14"/>
                                    </span>
                                    <input
                                        type="text"
                                        wire:model.live.debounce.300ms="ucBusqueda"
                                        placeholder="Buscar por nombre…"
                                        aria-label="Buscar ciudadano por nombre"
                                        class="form-control"
                                        autocomplete="off"
                                    />
                                </div>

                                @if($this->ucResultadosBusqueda->isNotEmpty())
                                <div class="list-group mt-2">
                                    @foreach($this->ucResultadosBusqueda as $resultado)
                                    <button
                                        type="button"
                                        wire:click="seleccionarCiudadanoUc({{ $resultado->id }})"
                                        class="list-group-item list-group-item-action d-flex align-items-center justify-content-between small"
                                        wire:key="resultado-{{ $resultado->id }}"
                                    >
                                        <span>
                                            {{ $resultado->nombre }} {{ $resultado->apellido1 }} {{ $resultado->apellido2 }}
                                        </span>
                                        @if(! $resultado->tieneResidenciaVerificada())
                                            <span class="badge bg-warning-subtle text-warning-emphasis">Sin verificar</span>
                                        @endif
                                    </button>
                                    @endforeach
                                </div>
                                @elseif(strlen(trim($ucBusqueda)) >= 2)
                                <p class="small text-body-secondary mt-2 mb-0">
                                    No se encontró ningún ciudadano con ese nombre.
                                    <a href="{{ route('ciudadania.alta') }}">
                                        Dar de alta ciudadano nuevo
                                    </a>
                                </p>
                                @endif
                            @endif
                        </section>
                    @endif

                </div>

                <div class="modal-footer">
                    <button type="button" wire:click="cerrarModalUc" class="btn btn-outline-secondary btn-sm">Cerrar</button>
                </div>

            </div>
        </div>
    </div>
    <div class="modal-backdrop fade show"></div>
    @endif

    {{-- ================================================================== --}}
    {{-- MODAL: DATOS DE CONTACTO DEL REPRESENTANTE                         --}}
    {{-- ================================================================== --}}
    @if($this->modalRepresentanteAbierto && $this->representante)
    <div
        class="modal fade show d-block"
        wire:click.self="cerrarModalRepresentante"
        x-data
        x-on:keydown.escape.window="$wire.cerrarModalRepresentante()"
        role="dialog"
        aria-modal="true"
        aria-labelledby="modal-representante-titulo"
        tabindex="-1"
    >
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content border-0 shadow">

                <div class="modal-header">
                    <h2 id="modal-representante-titulo" class="modal-title fs-6">Representante</h2>
                    <button wire:click="cerrarModalRepresentante" type="button" class="btn-close" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body d-flex flex-column gap-2">
                    <span class="fw-semibold">
                        {{ $this->representante->nombre }}
                        {{ $this->representante->apellido1 }}
                        {{ $this->representante->apellido2 }}
                    </span>

                    @if($this->representante->telefono)
                    <a href="tel:{{ $this->representante->telefono }}" class="d-inline-flex align-items-center gap-1 text-decoration-none">
                        <x-heroicon-o-phone class="icon-14" aria-hidden="true"/>
                        {{ $this->representante->telefono }}
                    </a>
                    @endif

                    @if($this->representante->email)
                    <a href="mailto:{{ $this->representante->email }}" class="d-inline-flex align-items-center gap-1 text-decoration-none">
                        <x-heroicon-o-envelope class="icon-14" aria-hidden="true"/>
                        {{ $this->representante->email }}
                    </a>
                    @endif

                    @if(! $this->representante->telefono && ! $this->representante->email)
                    <span class="small text-body-secondary fst-italic">
                        Sin datos de contacto registrados.
                    </span>
                    @endif

                    <a href="{{ route('ciudadania.ciudadano.ficha', $this->representante->id) }}"
                       class="small d-inline-flex align-items-center gap-1 mt-2" wire:navigate>
                        <x-heroicon-o-arrow-top-right-on-square class="icon-12" aria-hidden="true"/>
                        Ver ficha completa
                    </a>
                </div>

                <div class="modal-footer">
                    <button type="button" wire:click="cerrarModalRepresentante" class="btn btn-outline-secondary btn-sm">
                        Cerrar
                    </button>
                </div>

            </div>
        </div>
    </div>
    <div class="modal-backdrop fade show"></div>
    @endif

    {{-- ================================================================== --}}
    {{-- MODAL: TODAS LAS RELACIONES DEL CIUDADANO                          --}}
    {{-- ================================================================== --}}
    @if($this->modalRelacionesAbierto)
    <div
        class="modal fade show d-block"
        wire:click.self="cerrarModalRelaciones"
        x-data
        x-on:keydown.escape.window="$wire.cerrarModalRelaciones()"
        role="dialog"
        aria-modal="true"
        aria-labelledby="modal-relaciones-titulo"
        tabindex="-1"
    >
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
            <div class="modal-content border-0 shadow">

                <div class="modal-header">
                    <h2 id="modal-relaciones-titulo" class="modal-title fs-6">Personas relacionadas</h2>
                    <button wire:click="cerrarModalRelaciones" type="button" class="btn-close" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body">

                    @forelse($this->relacionesAgrupadas as $slug => $grupo)
                    <section class="mb-3" wire:key="grupo-{{ $slug }}">
                        <h3 class="h6 fw-semibold d-flex align-items-center gap-2">
                            {{ $grupo['etiqueta'] }}
                            <span class="badge rounded-pill text-bg-secondary">
                                {{ $grupo['miembros']->count() }}
                            </span>
                        </h3>

                        <ul class="list-group list-group-flush">
                            @foreach($grupo['miembros'] as $persona)
                            <li class="list-group-item d-flex align-items-center justify-content-between gap-2 px-0" wire:key="rel-{{ $slug }}-{{ $persona->id }}">
                                <div>
                                    <span class="fw-semibold">
                                        {{ $persona->nombre }}
                                        {{ $persona->apellido1 }}
                                        {{ $persona->apellido2 }}
                                    </span>
                                    @if($persona->telefono)
                                    <div class="small text-body-secondary">
                                        {{ $persona->telefono }}
                                    </div>
                                    @endif
                                </div>
                                <a
                                    href="{{ route('ciudadania.ciudadano.ficha', $persona->id) }}"
                                    class="btn btn-outline-secondary btn-sm"
                                    wire:navigate
                                    title="Ver ficha"
                                    aria-label="Ver ficha"
                                >
                                    <x-heroicon-o-arrow-top-right-on-square class="icon-12" aria-hidden="true"/>
                                </a>
                            </li>
                            @endforeach
                        </ul>
                    </section>
                    @empty
                    <div class="text-center py-3">
                        <p>No hay personas relacionadas registradas.</p>
                        <a href="{{ route('ciudadania.ciudadano.ficha', $this->ciudadano->id) }}" wire:navigate>
                            Gestionar relaciones en la ficha del ciudadano
                        </a>
                    </div>
                    @endforelse

                </div>

                <div class="modal-footer justify-content-between">
                    <a href="{{ route('ciudadania.ciudadano.ficha', $this->ciudadano->id) }}"
                       class="small d-inline-flex align-items-center gap-1" wire:navigate>
                        <x-heroicon-o-arrow-top-right-on-square class="icon-12" aria-hidden="true"/>
                        Gestionar relaciones en la ficha
                    </a>
                    <button type="button" wire:click="cerrarModalRelaciones" class="btn btn-outline-secondary btn-sm">
                        Cerrar
                    </button>
                </div>

            </div>
        </div>
    </div>
    <div class="modal-backdrop fade show"></div>
    @endif

    {{-- Modal de prescripción de recurso --}}
    @livewire('intervencion.prescribir-recurso-modal', ['historiaId' => $historia->id], key('prescribir-'.$historia->id))

</div>
