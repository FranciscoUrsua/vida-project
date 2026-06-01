@php
    use Carbon\Carbon;
    use App\Models\CatalogoSistema;
    use Modules\Intervencion\Enums\TipoApunte;

    $ciudadano = $this->ciudadano;
    $piso      = $this->pisoActivo;
    $nombrePlan = CatalogoSistema::valor('nombre_plan_asp', 'PISO');

    $badgeEstado = [
        'abierta'        => ['bg' => '#EEEDFE', 'color' => '#3C3489', 'label' => 'Abierta'],
        'en_seguimiento' => ['bg' => '#E1F5EE', 'color' => '#0F6E56', 'label' => 'En seguimiento'],
        'cerrada'        => ['bg' => '#F3F4F6', 'color' => '#6B7280', 'label' => 'Cerrada'],
    ];
    $badge = $badgeEstado[$historia->estado] ?? $badgeEstado['abierta'];

    $coloresTipo = [
        'plan_intervencion'   => '#854F0B',
        'entrevista'          => '#534AB7',
        'valoracion'          => '#1D9E75',
        'escala'              => '#7F77DD',
        'derivacion'          => '#0F6E56',
        'anotacion'           => '#888780',
        'gestion_coordinacion'=> '#888780',
        'seguimiento'         => '#534AB7',
        'documento'           => '#888780',
    ];

    $herramientas = [
        ['id' => 'entrevista',  'label' => 'Entrevista',         'icon' => 'bi-chat-square-text',     'fullpage' => false],
        ['id' => 'anotacion',   'label' => 'Anotación',          'icon' => 'bi-pencil',               'fullpage' => false],
        ['id' => 'derivacion',  'label' => 'Derivación',         'icon' => 'bi-arrow-right-circle',   'fullpage' => false],
        ['id' => 'gestion',     'label' => 'Gestión',            'icon' => 'bi-diagram-3',            'fullpage' => false],
        ['id' => 'valoracion',  'label' => 'Valoración',         'icon' => 'bi-clipboard-check',      'fullpage' => true],
        ['id' => 'escala',      'label' => 'Escala',             'icon' => 'bi-bar-chart-line',       'fullpage' => true],
        ['id' => 'informes',    'label' => 'Informes',           'icon' => 'bi-file-earmark-text',    'fullpage' => true],
    ];
@endphp

<div style="display: flex; flex-direction: column; height: 100vh; overflow: hidden;">

    {{-- ------------------------------------------------------------------ --}}
    {{-- Barra superior (breadcrumb)                                         --}}
    {{-- ------------------------------------------------------------------ --}}
    <div style="background: #fff; border-bottom: 1px solid #E5E3F5; padding: 0.65rem 1.25rem; display: flex; align-items: center; gap: 0.75rem; flex-shrink: 0;">
        <a href="{{ route('intervencion.casos.index') }}" style="font-size: 0.82rem; color: #534AB7; text-decoration: none; display: flex; align-items: center; gap: 0.3rem;">
            <i class="bi bi-arrow-left"></i> Mis casos
        </a>
        <span style="color: #D1D5DB;">·</span>
        <span style="font-size: 0.95rem; font-weight: 700; color: #1D160E;">
            {{ $ciudadano ? ($ciudadano->nombre . ' ' . $ciudadano->apellido1) : 'Historia #' . $historia->id }}
        </span>
        <span style="background: {{ $badge['bg'] }}; color: {{ $badge['color'] }}; padding: 0.15rem 0.55rem; border-radius: 99px; font-size: 0.72rem; font-weight: 600;">
            {{ $badge['label'] }}
        </span>
    </div>

    {{-- ------------------------------------------------------------------ --}}
    {{-- Cuerpo de dos columnas                                              --}}
    {{-- ------------------------------------------------------------------ --}}
    <div style="flex: 1; display: flex; overflow: hidden;">

        {{-- Columna izquierda (280px) --}}
        <div style="width: 280px; flex-shrink: 0; border-right: 1px solid #E5E3F5; overflow-y: auto; background: #FAFAFA; padding: 0.75rem;">

            {{-- Datos del ciudadano --}}
            <div style="margin-bottom: 1rem;">
                <div style="display: flex; align-items: center; gap: 0.6rem; margin-bottom: 0.5rem;">
                    @if($ciudadano)
                        <x-avatar :usuario="(object)['name' => $ciudadano->nombre . ' ' . $ciudadano->apellido1, 'id' => $historia->ciudadano_id]" />
                    @endif
                    <div>
                        <div style="font-size: 0.85rem; font-weight: 700; color: #1D160E;">
                            {{ $ciudadano ? ($ciudadano->nombre . ' ' . $ciudadano->apellido1 . ($ciudadano->apellido2 ? ' ' . $ciudadano->apellido2 : '')) : 'Ciudadano #' . $historia->ciudadano_id }}
                        </div>
                        @if($ciudadano?->fecha_nacimiento)
                            <div style="font-size: 0.72rem; color: #6B7280;">
                                {{ Carbon::parse($ciudadano->fecha_nacimiento)->age }} años
                            </div>
                        @endif
                    </div>
                </div>
                <div style="font-size: 0.75rem; color: #6B7280; line-height: 1.7;">
                    <div>HS apertura: {{ $historia->created_at->format('d/m/Y') }}</div>
                    <div>UO: #{{ $historia->unidad_organizativa_id }}</div>
                </div>
            </div>

            {{-- Unidad de convivencia --}}
            <div style="margin-bottom: 1rem; border: 1px solid #E5E3F5; border-radius: 8px; overflow: hidden;">
                <button wire:click="toggleUC"
                        style="width: 100%; background: #fff; border: none; padding: 0.5rem 0.75rem; text-align: left; font-size: 0.78rem; font-weight: 600; color: #374151; cursor: pointer; display: flex; justify-content: space-between; align-items: center;">
                    <span>Unidad de convivencia</span>
                    <i class="bi bi-chevron-{{ $ucExpandida ? 'up' : 'down' }}" style="font-size: 0.7rem;"></i>
                </button>
                @if($ucExpandida)
                    <div style="padding: 0.5rem 0.75rem; font-size: 0.75rem; color: #9CA3AF; border-top: 1px solid #F3F4F6;">
                        {{-- TODO: implementar cuando exista la tabla unidades_convivencia --}}
                        Sin datos de unidad de convivencia disponibles.
                    </div>
                @endif
            </div>

            {{-- Filtros del timeline --}}
            <div style="display: flex; gap: 0.3rem; margin-bottom: 0.75rem; flex-wrap: wrap;">
                @foreach([['todos', 'Todos'], ['plan', $nombrePlan], ['entrevista', 'Entrevistas']] as [$key, $label])
                    <button wire:click="setFiltroHS('{{ $key }}')"
                            style="font-size: 0.72rem; padding: 0.2rem 0.6rem; border-radius: 99px; border: 1px solid {{ $filtroHS === $key ? '#534AB7' : '#E5E3F5' }}; background: {{ $filtroHS === $key ? '#EEEDFE' : '#fff' }}; color: {{ $filtroHS === $key ? '#3C3489' : '#6B7280' }}; cursor: pointer; font-weight: {{ $filtroHS === $key ? '600' : '400' }};">
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            {{-- Timeline de apuntes --}}
            @forelse($this->apuntesHS as $apunte)
                @php
                    $colorPunto = $coloresTipo[$apunte->tipo->value] ?? '#888780';
                    $expandido  = $apuntesExpandidos[$apunte->id] ?? false;
                @endphp
                <div wire:click="toggleApunte({{ $apunte->id }})"
                     style="display: flex; gap: 0.5rem; align-items: flex-start; margin-bottom: 0.6rem; cursor: pointer; padding: 0.4rem 0.5rem; border-radius: 6px; background: {{ $expandido ? '#FBFAFF' : 'transparent' }}; transition: background 0.1s;"
                     onmouseover="this.style.background='#F8F7FF'"
                     onmouseout="this.style.background='{{ $expandido ? '#FBFAFF' : 'transparent' }}'">
                    <span style="width: 8px; height: 8px; border-radius: 50%; background: {{ $colorPunto }}; flex-shrink: 0; margin-top: 5px;"></span>
                    <div style="flex: 1; min-width: 0;">
                        <div style="font-size: 0.78rem; font-weight: 600; color: #1D160E; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            {{ $apunte->tipo->label() }}
                        </div>
                        <div style="font-size: 0.7rem; color: #9CA3AF;">
                            {{ $apunte->fecha->format('d/m/Y') }} · {{ $apunte->autor->name ?? '' }}
                        </div>
                        @if($expandido && $apunte->contenido)
                            <div style="font-size: 0.78rem; color: #374151; margin-top: 0.3rem; white-space: pre-wrap; line-height: 1.5;">{{ $apunte->contenido }}</div>
                        @endif
                    </div>
                </div>
            @empty
                <div style="font-size: 0.78rem; color: #9CA3AF; text-align: center; padding: 1rem 0;">
                    Sin registros en la historia social.
                </div>
            @endforelse

        </div>

        {{-- Columna derecha --}}
        <div style="flex: 1; display: flex; flex-direction: column; overflow: hidden; min-width: 0;">

            {{-- Banda PISO activo --}}
            @if($piso)
                <div style="background: #EEEDFE; border-bottom: 1px solid #C5C0F5; padding: 0.5rem 1.25rem; display: flex; align-items: center; gap: 1rem; flex-shrink: 0; font-size: 0.8rem;">
                    <span style="font-weight: 600; color: #3C3489;">{{ $nombrePlan }} activo</span>
                    <span style="color: #6B7280;">v{{ $piso->version }} · desde {{ Carbon::parse($piso->fecha_inicio)->format('d/m/Y') }}</span>
                    {{-- TODO: Entrega 4 — route('intervencion.piso.show', $piso->id) --}}
                    <a href="#" style="margin-left: auto; font-size: 0.78rem; color: #534AB7; text-decoration: none; font-weight: 600;">Ver {{ $nombrePlan }} →</a>
                </div>
            @else
                <div style="background: #F9F8FF; border-bottom: 1px solid #E5E3F5; padding: 0.45rem 1.25rem; font-size: 0.78rem; color: #9CA3AF; flex-shrink: 0;">
                    Sin {{ $nombrePlan }} activo
                </div>
            @endif

            {{-- Área de herramientas --}}
            <div style="flex: 1; overflow-y: auto; padding: 1rem 1.25rem;">

                {{-- Cuadrícula de herramientas --}}
                <div style="display: grid; grid-template-columns: repeat({{ $herramientaActiva ? '7' : '4' }}, 1fr); gap: 0.5rem; margin-bottom: {{ $herramientaActiva ? '1rem' : '0' }};">
                    @foreach($herramientas as $h)
                        <button wire:click="seleccionarHerramienta('{{ $h['id'] }}')"
                                style="background: {{ $herramientaActiva === $h['id'] ? '#EEEDFE' : '#fff' }}; border: 1px solid {{ $herramientaActiva === $h['id'] ? '#534AB7' : '#E5E3F5' }}; border-radius: 8px; padding: {{ $herramientaActiva ? '0.35rem 0.5rem' : '0.75rem 0.5rem' }}; cursor: pointer; text-align: center; transition: all 0.1s;">
                            <i class="bi {{ $h['icon'] }}" style="font-size: {{ $herramientaActiva ? '0.9rem' : '1.2rem' }}; color: {{ $herramientaActiva === $h['id'] ? '#534AB7' : '#6B7280' }}; display: block; margin-bottom: {{ $herramientaActiva ? '0' : '0.3rem' }};"></i>
                            <span style="font-size: 0.7rem; color: {{ $herramientaActiva === $h['id'] ? '#3C3489' : '#374151' }}; font-weight: {{ $herramientaActiva === $h['id'] ? '600' : '400' }}; display: {{ $herramientaActiva ? 'none' : 'block' }};">
                                {{ $h['label'] }}
                                @if($h['fullpage'])
                                    <span style="display: block; font-size: 0.62rem; color: #9CA3AF;">↗ pantalla completa</span>
                                @endif
                            </span>
                        </button>
                    @endforeach
                </div>

                {{-- Formulario de la herramienta activa --}}
                @if($herramientaActiva === 'entrevista')
                    <div style="background: #fff; border: 1px solid #E5E3F5; border-radius: 8px; padding: 1rem;">
                        <h3 style="font-size: 0.9rem; font-weight: 700; margin: 0 0 0.75rem; color: #1D160E;">Registrar entrevista</h3>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 0.75rem;">
                            <div>
                                <label style="font-size: 0.78rem; font-weight: 600; color: #374151; display: block; margin-bottom: 0.25rem;">Tipo</label>
                                <select wire:model="formEntrevista.tipo" class="form-select form-select-sm">
                                    <option value="seguimiento">Seguimiento</option>
                                    <option value="inicial">Inicial</option>
                                    <option value="urgencia">Urgencia</option>
                                    <option value="informativa">Informativa</option>
                                </select>
                            </div>
                            <div>
                                <label style="font-size: 0.78rem; font-weight: 600; color: #374151; display: block; margin-bottom: 0.25rem;">Modalidad</label>
                                <select wire:model="formEntrevista.modalidad" class="form-select form-select-sm">
                                    <option value="presencial">Presencial</option>
                                    <option value="telefonica">Telefónica</option>
                                    <option value="videollamada">Videollamada</option>
                                    <option value="domicilio">Domicilio</option>
                                </select>
                            </div>
                        </div>
                        <div style="margin-bottom: 0.75rem;">
                            <label style="font-size: 0.78rem; font-weight: 600; color: #374151; display: block; margin-bottom: 0.25rem;">Notas generales</label>
                            <textarea wire:model="formEntrevista.notas" rows="3" class="form-control form-control-sm" placeholder="Observaciones de la entrevista..."></textarea>
                        </div>
                        <div style="display: flex; gap: 1rem; margin-bottom: 0.75rem; font-size: 0.8rem;">
                            <label style="display: flex; align-items: center; gap: 0.3rem; cursor: pointer;">
                                <input type="checkbox" wire:model="formEntrevista.programar_seguimiento"> Programar siguiente seguimiento
                            </label>
                        </div>
                        @if($formEntrevista['programar_seguimiento'])
                            <div style="margin-bottom: 0.75rem;">
                                <label style="font-size: 0.78rem; font-weight: 600; color: #374151; display: block; margin-bottom: 0.25rem;">Fecha siguiente seguimiento</label>
                                <input type="date" wire:model="formEntrevista.fecha_siguiente_seguimiento" class="form-control form-control-sm">
                            </div>
                        @endif
                        <div style="display: flex; gap: 0.5rem;">
                            <button wire:click="guardarEntrevista" class="btn btn-primary btn-sm" style="font-size: 0.8rem;">Guardar entrevista</button>
                            <button wire:click="cancelarHerramienta" class="btn btn-outline-secondary btn-sm" style="font-size: 0.8rem;">Cancelar</button>
                        </div>
                    </div>

                @elseif($herramientaActiva === 'anotacion')
                    <div style="background: #fff; border: 1px solid #E5E3F5; border-radius: 8px; padding: 1rem;">
                        <h3 style="font-size: 0.9rem; font-weight: 700; margin: 0 0 0.75rem; color: #1D160E;">Guardar anotación</h3>
                        <div style="margin-bottom: 0.75rem;">
                            <textarea wire:model="formAnotacion.contenido" rows="4" class="form-control form-control-sm" placeholder="Escribe la anotación..."></textarea>
                        </div>
                        <div style="margin-bottom: 0.75rem; font-size: 0.8rem;">
                            <label style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.3rem; cursor: pointer;">
                                <input type="radio" wire:model="formAnotacion.visibilidad" value="profesionales"> Para profesionales
                            </label>
                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                <input type="radio" wire:model="formAnotacion.visibilidad" value="privada"> Privada (solo yo)
                            </label>
                        </div>
                        <div style="display: flex; gap: 0.5rem;">
                            <button wire:click="guardarAnotacion" class="btn btn-primary btn-sm" style="font-size: 0.8rem;">Guardar anotación</button>
                            <button wire:click="cancelarHerramienta" class="btn btn-outline-secondary btn-sm" style="font-size: 0.8rem;">Cancelar</button>
                        </div>
                    </div>

                @elseif($herramientaActiva === 'derivacion')
                    <div style="background: #fff; border: 1px solid #E5E3F5; border-radius: 8px; padding: 1rem;">
                        <h3 style="font-size: 0.9rem; font-weight: 700; margin: 0 0 0.75rem; color: #1D160E;">Crear derivación</h3>
                        <div style="margin-bottom: 0.75rem;">
                            <label style="font-size: 0.78rem; font-weight: 600; color: #374151; display: block; margin-bottom: 0.25rem;">Urgencia</label>
                            <select wire:model="formDerivacion.urgencia" class="form-select form-select-sm">
                                <option value="ordinaria">Ordinaria</option>
                                <option value="preferente">Preferente</option>
                                <option value="urgente">Urgente</option>
                            </select>
                        </div>
                        <div style="margin-bottom: 0.75rem;">
                            <label style="font-size: 0.78rem; font-weight: 600; color: #374151; display: block; margin-bottom: 0.25rem;">Motivo</label>
                            <textarea wire:model="formDerivacion.motivo" rows="3" class="form-control form-control-sm" placeholder="Motivo de la derivación..."></textarea>
                        </div>
                        <div style="display: flex; gap: 0.5rem;">
                            <button wire:click="crearDerivacion" class="btn btn-primary btn-sm" style="font-size: 0.8rem;">Crear derivación</button>
                            <button wire:click="cancelarHerramienta" class="btn btn-outline-secondary btn-sm" style="font-size: 0.8rem;">Cancelar</button>
                        </div>
                    </div>

                @elseif($herramientaActiva === 'gestion')
                    <div style="background: #fff; border: 1px solid #E5E3F5; border-radius: 8px; padding: 1rem;">
                        <h3 style="font-size: 0.9rem; font-weight: 700; margin: 0 0 0.75rem; color: #1D160E;">Guardar gestión</h3>
                        <div style="margin-bottom: 0.75rem;">
                            <label style="font-size: 0.78rem; font-weight: 600; color: #374151; display: block; margin-bottom: 0.25rem;">Tipo de gestión</label>
                            <select wire:model="formGestion.tipo_gestion" class="form-select form-select-sm">
                                <option value="">Selecciona...</option>
                                <option value="coordinacion">Coordinación con otro servicio</option>
                                <option value="tramite">Trámite administrativo</option>
                                <option value="mesa_trabajo">Mesa de trabajo</option>
                                <option value="contacto_familia">Contacto con familia</option>
                                <option value="otro">Otro</option>
                            </select>
                        </div>
                        <div style="margin-bottom: 0.75rem;">
                            <label style="font-size: 0.78rem; font-weight: 600; color: #374151; display: block; margin-bottom: 0.25rem;">Recurso / interlocutor</label>
                            <input type="text" wire:model="formGestion.recurso_interlocutor" class="form-control form-control-sm" placeholder="Nombre del recurso o persona...">
                        </div>
                        <div style="margin-bottom: 0.75rem;">
                            <label style="font-size: 0.78rem; font-weight: 600; color: #374151; display: block; margin-bottom: 0.25rem;">Descripción</label>
                            <textarea wire:model="formGestion.descripcion" rows="3" class="form-control form-control-sm" placeholder="Describe la gestión realizada..."></textarea>
                        </div>
                        <div style="display: flex; gap: 0.5rem;">
                            <button wire:click="guardarGestion" class="btn btn-primary btn-sm" style="font-size: 0.8rem;">Guardar gestión</button>
                            <button wire:click="cancelarHerramienta" class="btn btn-outline-secondary btn-sm" style="font-size: 0.8rem;">Cancelar</button>
                        </div>
                    </div>

                @elseif($herramientaActiva === 'valoracion')
                    <div style="background: #fff; border: 1px solid #E5E3F5; border-radius: 8px; padding: 1rem;">
                        <h3 style="font-size: 0.9rem; font-weight: 700; margin: 0 0 0.5rem; color: #1D160E;">Valoración</h3>
                        <p style="font-size: 0.78rem; color: #6B7280; margin: 0 0 0.75rem;">La ficha se abrirá en pantalla completa.</p>
                        <div style="margin-bottom: 0.75rem;">
                            <label style="font-size: 0.78rem; font-weight: 600; color: #374151; display: block; margin-bottom: 0.25rem;">Tipo de ficha</label>
                            <select wire:model="formValoracion.tipo_ficha_id" class="form-select form-select-sm">
                                <option value="">Selecciona...</option>
                                @foreach($this->tiposFicha as $tf)
                                    <option value="{{ $tf->id }}">{{ $tf->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div style="display: flex; gap: 0.5rem;">
                            @if($formValoracion['tipo_ficha_id'])
                                <a href="{{ route('intervencion.valoracion.nueva', ['historia' => $historia->id, 'tipo_ficha' => $formValoracion['tipo_ficha_id']]) }}"
                                   class="btn btn-primary btn-sm" style="font-size: 0.8rem;">Abrir en pantalla completa</a>
                            @endif
                            <button wire:click="cancelarHerramienta" class="btn btn-outline-secondary btn-sm" style="font-size: 0.8rem;">Cancelar</button>
                        </div>
                    </div>

                @elseif($herramientaActiva === 'escala')
                    <div style="background: #fff; border: 1px solid #E5E3F5; border-radius: 8px; padding: 1rem;">
                        <h3 style="font-size: 0.9rem; font-weight: 700; margin: 0 0 0.5rem; color: #1D160E;">Escala</h3>
                        <p style="font-size: 0.78rem; color: #6B7280; margin: 0 0 0.75rem;">La escala se abrirá en pantalla completa.</p>
                        <div style="margin-bottom: 0.75rem;">
                            <label style="font-size: 0.78rem; font-weight: 600; color: #374151; display: block; margin-bottom: 0.25rem;">Instrumento</label>
                            <select wire:model="formEscala.tipo_escala_id" class="form-select form-select-sm">
                                <option value="">Selecciona...</option>
                                @foreach($this->tiposEscala as $te)
                                    <option value="{{ $te->id }}">{{ $te->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div style="display: flex; gap: 0.5rem;">
                            @if($formEscala['tipo_escala_id'])
                                <a href="{{ route('intervencion.escala.nueva', ['historia' => $historia->id, 'tipo_escala' => $formEscala['tipo_escala_id']]) }}"
                                   class="btn btn-primary btn-sm" style="font-size: 0.8rem;">Abrir en pantalla completa</a>
                            @endif
                            <button wire:click="cancelarHerramienta" class="btn btn-outline-secondary btn-sm" style="font-size: 0.8rem;">Cancelar</button>
                        </div>
                    </div>

                @elseif($herramientaActiva === 'informes')
                    <div style="background: #fff; border: 1px solid #E5E3F5; border-radius: 8px; padding: 1rem;">
                        <h3 style="font-size: 0.9rem; font-weight: 700; margin: 0 0 0.5rem; color: #1D160E;">Informes</h3>
                        {{-- TODO: conectar con módulo Documentos cuando implemente la vista de edición --}}
                        <p style="font-size: 0.85rem; color: #6B7280;">Módulo de informes en construcción.</p>
                        <button wire:click="cancelarHerramienta" class="btn btn-outline-secondary btn-sm" style="font-size: 0.8rem; margin-top: 0.5rem;">Cerrar</button>
                    </div>

                @endif

            </div>
        </div>
    </div>

</div>
