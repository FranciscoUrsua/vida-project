<div class="op-page">
<div class="row justify-content-center g-0 p-3 p-lg-4">
<div class="col-12 col-lg-9 col-xl-7">

    {{-- Indicador de fase --}}
    <ol class="nav nav-pills nav-fill small mb-4" aria-label="Pasos del alta">
        @foreach(['busqueda' => 'Búsqueda previa', 'padron' => 'Verificación padrón', 'formulario' => 'Datos', 'confirmacion' => 'Confirmación'] as $f => $etiqueta)
            <li class="nav-item">
                <span @class(['nav-link fw-semibold', 'active' => $fase === $f, 'disabled' => $fase !== $f])
                      @if($fase === $f) aria-current="step" @endif>
                    {{ $etiqueta }}
                </span>
            </li>
        @endforeach
    </ol>

    {{-- ================================================================== --}}
    {{-- FASE 1: BÚSQUEDA PREVIA                                            --}}
    {{-- ================================================================== --}}
    @if($fase === 'busqueda')

        <h2 class="h5 fw-bold mb-1">Alta de ciudadano/a</h2>
        <p class="text-body-secondary mb-4">
            Antes de registrar a nadie, comprueba que la persona no existe ya en el sistema.
        </p>

        {{-- Búsqueda por documento --}}
        <div class="card mb-3">
            <div class="card-body">
                <h3 class="small text-uppercase fw-bold text-body-secondary mb-3">Buscar por documento</h3>
                <div class="row g-2 align-items-end">
                    <div class="col-auto">
                        <label for="busqueda-tipo-doc" class="form-label small">Tipo</label>
                        <select id="busqueda-tipo-doc" wire:model="busquedaTipoDoc" class="form-select form-select-sm">
                            <option value="nif">NIF/DNI</option>
                            <option value="nie">NIE</option>
                            <option value="pasaporte">Pasaporte</option>
                        </select>
                    </div>
                    <div class="col">
                        <label for="busqueda-valor-doc" class="form-label small">Número de documento</label>
                        <input id="busqueda-valor-doc" wire:model="busquedaValorDoc" type="text"
                               class="form-control form-control-sm font-monospace text-uppercase"
                               placeholder="Ej: 12345678A" autocomplete="off" />
                    </div>
                </div>
            </div>
        </div>

        {{-- Búsqueda por datos personales --}}
        <div class="card mb-3">
            <div class="card-body">
                <h3 class="small text-uppercase fw-bold text-body-secondary mb-3">O buscar por datos personales</h3>
                <div class="row g-2">
                    <div class="col-md-6">
                        <label for="busqueda-nombre" class="form-label small">Nombre</label>
                        <input id="busqueda-nombre" wire:model="busquedaNombre" type="text" class="form-control form-control-sm" autocomplete="off" />
                    </div>
                    <div class="col-md-6">
                        <label for="busqueda-apellido1" class="form-label small">Primer apellido</label>
                        <input id="busqueda-apellido1" wire:model="busquedaApellido1" type="text" class="form-control form-control-sm" autocomplete="off" />
                    </div>
                    <div class="col-md-6">
                        <label for="busqueda-apellido2" class="form-label small">Segundo apellido</label>
                        <input id="busqueda-apellido2" wire:model="busquedaApellido2" type="text" class="form-control form-control-sm" autocomplete="off" />
                    </div>
                    <div class="col-md-6">
                        <label for="busqueda-fecha" class="form-label small">Fecha de nacimiento</label>
                        <input id="busqueda-fecha" wire:model="busquedaFechaNacimiento" type="date" class="form-control form-control-sm" />
                    </div>
                </div>
            </div>
        </div>

        @error('busqueda')
            <div class="alert alert-warning py-2 small">{{ $message }}</div>
        @enderror

        <button type="button" wire:click="buscar" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1">
            <x-heroicon-o-magnifying-glass class="icon-14" aria-hidden="true"/>
            Buscar
        </button>

        {{-- Resultados --}}
        @if($busquedaRealizada)
            <div class="mt-4">
                @if(count($resultadosBusqueda) === 0)
                    <div class="alert alert-secondary small mb-0">
                        No se han encontrado personas con los criterios indicados.
                    </div>
                @else
                    <p class="small text-uppercase fw-bold text-body-secondary mb-2">
                        {{ count($resultadosBusqueda) }} posible{{ count($resultadosBusqueda) !== 1 ? 's coincidencias' : ' coincidencia' }} encontrada{{ count($resultadosBusqueda) !== 1 ? 's' : '' }}
                    </p>
                    @foreach($resultadosBusqueda as $r)
                        <div @class(['card mb-2', 'border-danger' => $r['bloquea']]) wire:key="resultado-{{ $r['ciudadanoId'] }}">
                            <div class="card-body py-2 d-flex align-items-center justify-content-between gap-3">
                                <div>
                                    <div class="fw-semibold">{{ $r['nombreCompleto'] }}</div>
                                    <div class="small text-body-secondary">
                                        @if($r['fechaNacimiento'])
                                            {{ \Carbon\Carbon::parse($r['fechaNacimiento'])->format('d/m/Y') }} ·
                                        @endif
                                        Coincide en: {{ implode(', ', $r['camposCoincidentes']) }}
                                        · Score: {{ number_format($r['score'] * 100, 0) }}%
                                    </div>
                                    @if($r['bloquea'])
                                        <div class="small fw-semibold text-danger mt-1">
                                            Coincidencia muy probable — revisa la ficha antes de continuar
                                        </div>
                                    @endif
                                </div>
                                <button type="button" wire:click="seleccionarExistente({{ $r['ciudadanoId'] }})"
                                        class="btn btn-outline-secondary btn-sm text-nowrap">
                                    Ver ficha
                                </button>
                            </div>
                        </div>
                    @endforeach
                @endif

                {{-- Acción continuar con el alta (solo si no hay bloqueo) --}}
                @php $hayBloqueo = collect($resultadosBusqueda)->contains('bloquea', true); @endphp
                @if(! $hayBloqueo)
                    <div class="card bg-body-tertiary mt-3">
                        <div class="card-body d-flex flex-wrap align-items-center justify-content-center gap-2 text-body-secondary">
                            ¿No está la persona que buscas?
                            <button type="button" wire:click="continuarConNuevoAlta" class="btn btn-primary btn-sm">
                                Dar de alta nueva persona
                            </button>
                        </div>
                    </div>
                @else
                    <div class="alert alert-warning small mt-3 mb-0">
                        Hay una coincidencia casi segura. Revisa la ficha de la persona antes de continuar con el alta.
                    </div>
                @endif
            </div>
        @endif

    {{-- ================================================================== --}}
    {{-- FASE 2: VERIFICACIÓN EN PADRÓN                                     --}}
    {{-- ================================================================== --}}
    @elseif($fase === 'padron')

        <h2 class="h5 fw-bold mb-1">Verificación en el padrón</h2>
        <p class="text-body-secondary mb-4">
            Consulta si la persona está empadronada. Si no lo está, selecciona el motivo para continuar.
        </p>

        @if(! $padronConsultado)
            <button type="button" wire:click="consultarPadron" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1">
                <x-heroicon-o-magnifying-glass class="icon-14" aria-hidden="true"/>
                Consultar padrón
            </button>
        @endif

        @if($padronConsultado && ! $padronEncontrado)
            <div class="card mt-3">
                <div class="card-body">
                    <p class="mb-3">
                        La persona no consta en el padrón municipal. Selecciona el motivo para continuar:
                    </p>

                    @php $esIntervencion = auth()->user()?->hasAnyRole(['intervencion', 'supervision']); @endphp

                    <div class="d-grid gap-2">
                        @if($esIntervencion)
                            <button type="button" wire:click="seleccionarExcepcionPadron('psh')" class="btn btn-outline-secondary text-start">
                                <strong>Persona sin hogar (PSH)</strong> — sin domicilio formal, se usarán coordenadas de pernocta
                            </button>
                            <button type="button" wire:click="seleccionarExcepcionPadron('vvg')" class="btn btn-outline-secondary text-start">
                                <strong>Víctima de violencia de género (VVG)</strong> — domicilio protegido, sin consulta al padrón
                            </button>
                        @else
                            <p class="small text-body-secondary fst-italic mb-0">
                                Las situaciones PSH y VVG requieren intervención de un profesional con rol de intervención.
                            </p>
                        @endif

                        <button type="button" wire:click="seleccionarExcepcionPadron('representante')" class="btn btn-outline-secondary text-start">
                            <strong>Representante</strong> — residente en otro municipio, alta solo para contacto y seguimiento
                        </button>
                        <button type="button" wire:click="seleccionarExcepcionPadron('otra')" class="btn btn-outline-secondary text-start">
                            <strong>Otra excepción</strong> — requiere justificación; queda registrada en auditoría
                        </button>
                    </div>
                </div>
            </div>
        @endif

    {{-- ================================================================== --}}
    {{-- FASE 3: FORMULARIO DE DATOS                                        --}}
    {{-- ================================================================== --}}
    @elseif($fase === 'formulario')

        <h2 class="h5 fw-bold mb-2">Datos del ciudadano/a</h2>
        @if($excepcionPadron)
            <div class="alert alert-warning d-inline-block py-1 px-3 small">
                Alta sin padrón — {{ match($excepcionPadron) { 'psh' => 'Persona sin hogar', 'vvg' => 'VVG', 'representante' => 'Representante', default => 'Otra excepción' } }}
            </div>
        @endif

        <div class="card mb-3">
            <div class="card-body">
                <h3 class="small text-uppercase fw-bold text-body-secondary mb-3">Identificación</h3>

                @if($excepcionPadron === 'psh')
                    <div class="mb-3">
                        <label for="alta-alias" class="form-label small fw-semibold">Alias / nombre operativo <span class="text-danger">*</span></label>
                        <input id="alta-alias" wire:model="alias" type="text" class="form-control form-control-sm @error('alias') is-invalid @enderror"
                               placeholder="Ej: Juan el del cajero de la calle X" />
                        @error('alias') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                @endif

                <div class="row g-2">
                    <div class="col-md-6">
                        <label for="alta-nombre" class="form-label small">Nombre {{ $excepcionPadron !== 'psh' ? '*' : '' }}</label>
                        @if(isset($fuenteCampos['nombre']))
                            <span class="badge bg-success-subtle text-success-emphasis ms-1">padrón</span>
                        @endif
                        <input id="alta-nombre" wire:model="nombre" type="text" class="form-control form-control-sm @error('nombre') is-invalid @enderror" />
                        @error('nombre') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label for="alta-apellido1" class="form-label small">Primer apellido {{ $excepcionPadron !== 'psh' ? '*' : '' }}</label>
                        @if(isset($fuenteCampos['apellido1']))
                            <span class="badge bg-success-subtle text-success-emphasis ms-1">padrón</span>
                        @endif
                        <input id="alta-apellido1" wire:model="apellido1" type="text" class="form-control form-control-sm @error('apellido1') is-invalid @enderror" />
                        @error('apellido1') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label for="alta-apellido2" class="form-label small">Segundo apellido</label>
                        @if(isset($fuenteCampos['apellido2']))
                            <span class="badge bg-success-subtle text-success-emphasis ms-1">padrón</span>
                        @endif
                        <input id="alta-apellido2" wire:model="apellido2" type="text" class="form-control form-control-sm" />
                    </div>
                    <div class="col-md-6">
                        <label for="alta-fecha" class="form-label small">Fecha de nacimiento</label>
                        @if(isset($fuenteCampos['fecha_nacimiento']))
                            <span class="badge bg-success-subtle text-success-emphasis ms-1">padrón</span>
                        @endif
                        <input id="alta-fecha" wire:model="fechaNacimiento" type="date" class="form-control form-control-sm @error('fechaNacimiento') is-invalid @enderror" />
                        @error('fechaNacimiento') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label for="alta-sexo" class="form-label small">Sexo <span class="text-danger">*</span></label>
                        @if(isset($fuenteCampos['sexo']))
                            <span class="badge bg-success-subtle text-success-emphasis ms-1">padrón</span>
                        @endif
                        <select id="alta-sexo" wire:model="sexo" class="form-select form-select-sm @error('sexo') is-invalid @enderror">
                            <option value="">-- Selecciona --</option>
                            @foreach($this->opcionesSexo as $clave => $etiqueta)
                                <option value="{{ $clave }}">{{ $etiqueta }}</option>
                            @endforeach
                        </select>
                        @error('sexo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <h3 class="small text-uppercase fw-bold text-body-secondary mb-3">Documento de identidad</h3>
                <div class="row g-2 align-items-end">
                    <div class="col-auto">
                        <label for="alta-tipo-doc" class="form-label small">Tipo</label>
                        <select id="alta-tipo-doc" wire:model="tipoDocumento" class="form-select form-select-sm">
                            <option value="nif">NIF/DNI</option>
                            <option value="nie">NIE</option>
                            <option value="pasaporte">Pasaporte</option>
                        </select>
                    </div>
                    <div class="col">
                        <label for="alta-valor-doc" class="form-label small">Número</label>
                        <input id="alta-valor-doc" wire:model="valorDocumento" type="text"
                               class="form-control form-control-sm font-monospace text-uppercase" autocomplete="off" />
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <h3 class="small text-uppercase fw-bold text-body-secondary mb-3">Contacto</h3>
                <div class="row g-2">
                    @if($excepcionPadron !== 'psh')
                        <div class="col-12">
                            <label for="alta-direccion" class="form-label small">Domicilio</label>
                            @if(isset($fuenteCampos['direccion_texto']))
                                <span class="badge bg-success-subtle text-success-emphasis ms-1">padrón</span>
                            @endif
                            <input id="alta-direccion" wire:model="direccionTexto" type="text" class="form-control form-control-sm"
                                   placeholder="Texto libre — se normalizará automáticamente" />
                        </div>
                    @endif
                    <div class="col-md-6">
                        <label for="alta-telefono" class="form-label small">Teléfono</label>
                        <input id="alta-telefono" wire:model="telefono" type="text" class="form-control form-control-sm" />
                    </div>
                    <div class="col-md-6">
                        <label for="alta-email" class="form-label small">Correo electrónico</label>
                        <input id="alta-email" wire:model="email" type="email" class="form-control form-control-sm @error('email') is-invalid @enderror" />
                        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>
        </div>

        <button type="button" wire:click="guardar" class="btn btn-primary btn-sm">
            Guardar y continuar
        </button>

    {{-- ================================================================== --}}
    {{-- FASE 4: CONFIRMACIÓN                                               --}}
    {{-- ================================================================== --}}
    @elseif($fase === 'confirmacion')

        <h2 class="h5 fw-bold mb-1">Ciudadano/a registrado/a</h2>
        <p class="text-body-secondary mb-4">
            El alta se ha completado. Antes de terminar, puedes anotar el motivo de la visita y elegir el siguiente paso.
        </p>

        <div class="card mb-3">
            <div class="card-body">
                <h3 class="small text-uppercase fw-bold text-body-secondary mb-2">Resumen</h3>
                <p class="mb-0">
                    <strong>{{ trim("$nombre $apellido1 $apellido2") ?: ($alias ?: '—') }}</strong><br>
                    @if($fechaNacimiento) Nacimiento: {{ \Carbon\Carbon::parse($fechaNacimiento)->format('d/m/Y') }}<br> @endif
                    @if($valorDocumento) Documento: {{ strtoupper($tipoDocumento) }} {{ $valorDocumento }}<br> @endif
                    Nivel de identificación:
                    <span class="fw-semibold">{{ match($nombre || $valorDocumento) { true => ($valorDocumento ? 'identificado' : 'probable'), default => 'no identificado' } }}</span>
                    @if($excepcionPadron)
                        <br>Contexto: {{ match($excepcionPadron) { 'psh' => 'PSH', 'vvg' => 'VVG', 'representante' => 'Representante', default => 'Otra excepción' } }}
                    @endif
                </p>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <label for="alta-primera-demanda" class="form-label fw-semibold mb-1">Primera demanda (opcional)</label>
                <p class="small text-body-secondary mb-2">Motivo de la visita en las propias palabras del ciudadano/a. No es una valoración profesional.</p>
                <textarea id="alta-primera-demanda" wire:model="primeraDemanda" rows="3" class="form-control form-control-sm"
                          placeholder="Ej: «Vengo porque me han dicho que puedo pedir ayuda para pagar el alquiler»"></textarea>
            </div>
        </div>

        @if($this->puedeAbrirHistoria)
            <div class="card mb-3">
                <div class="card-body">
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" role="switch" id="alta-abrir-historia" wire:model.live="abrirHistoria">
                        <label class="form-check-label fw-semibold" for="alta-abrir-historia">
                            {{ $this->referenciaQuienAbre ? 'Abrir la historia social y quedar como profesional de referencia' : 'Abrir la historia social' }}
                        </label>
                    </div>
                    @if($this->referenciaQuienAbre)
                        <p class="small text-body-secondary mb-0 mt-1">El caso aparecerá en «Mis casos». Desmárcalo si la persona solo necesita información o una gestión puntual.</p>
                    @else
                        <p class="small text-body-secondary mb-0 mt-1">El centro asigna el profesional de referencia; al confirmar verás quién es. Desmárcalo si la persona solo necesita información o una gestión puntual.</p>
                    @endif

                    @if($abrirHistoria && $this->profesionalesElegibles !== [])
                        <div class="mt-3">
                            <label for="alta-referencia-elegida" class="form-label small fw-semibold mb-1">Profesional que elige la persona (opcional)</label>
                            <select id="alta-referencia-elegida" wire:model="referenciaElegidaId" class="form-select form-select-sm">
                                <option value="">Sin preferencia: se sortea</option>
                                @foreach($this->profesionalesElegibles as $id => $nombre)
                                    <option value="{{ $id }}">{{ $nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        <fieldset class="card mb-3">
            <div class="card-body">
                <legend class="fs-6 fw-semibold mb-3">¿Qué hacemos a continuación?</legend>
                <div class="form-check">
                    <input class="form-check-input" type="radio" id="accion-ficha" wire:model="accionPostAlta" value="ficha">
                    <label class="form-check-label" for="accion-ficha">Ir a la ficha del ciudadano/a</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" id="accion-cita" wire:model="accionPostAlta" value="cita">
                    <label class="form-check-label" for="accion-cita">Crear una cita ahora</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" id="accion-solo-alta" wire:model="accionPostAlta" value="solo_alta">
                    <label class="form-check-label" for="accion-solo-alta">Solo guardar y volver a la búsqueda</label>
                </div>
            </div>
        </fieldset>

        <button type="button" wire:click="confirmarAlta" class="btn btn-primary btn-sm">
            Confirmar y terminar
        </button>

    @endif

</div>
</div>
</div>
