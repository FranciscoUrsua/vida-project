<div style="max-width: 720px; margin: 0 auto; padding: 1.5rem;">

    {{-- Indicador de fase --}}
    <div style="display: flex; gap: 0; margin-bottom: 2rem; border: 1px solid var(--color-ink-200); border-radius: 8px; overflow: hidden; font-size: 0.78rem;">
        @foreach(['busqueda' => 'Búsqueda previa', 'padron' => 'Verificación padrón', 'formulario' => 'Datos', 'confirmacion' => 'Confirmación'] as $f => $etiqueta)
            <div style="flex: 1; padding: 0.5rem 0.75rem; text-align: center; font-weight: 600;
                background: {{ $fase === $f ? 'var(--color-primary)' : 'var(--color-bg-50)' }};
                color: {{ $fase === $f ? '#fff' : 'var(--color-ink-400)' }};
                border-right: 1px solid var(--color-ink-200);">
                {{ $etiqueta }}
            </div>
        @endforeach
    </div>

    {{-- ================================================================== --}}
    {{-- FASE 1: BÚSQUEDA PREVIA                                            --}}
    {{-- ================================================================== --}}
    @if($fase === 'busqueda')

        <h2 style="font-size: 1.05rem; font-weight: 700; color: var(--color-ink-900); margin: 0 0 0.25rem;">Alta de ciudadano/a</h2>
        <p style="font-size: 0.85rem; color: var(--color-ink-600); margin: 0 0 1.5rem;">
            Antes de registrar a nadie, comprueba que la persona no existe ya en el sistema.
        </p>

        {{-- Búsqueda por documento --}}
        <div style="background: #fff; border: 1px solid var(--color-ink-200); border-radius: 8px; padding: 1.25rem; margin-bottom: 1rem;">
            <p style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-ink-500); margin: 0 0 0.75rem;">Buscar por documento</p>
            <div style="display: flex; gap: 0.5rem; align-items: flex-end;">
                <div>
                    <label style="font-size: 0.78rem; color: var(--color-ink-600); display: block; margin-bottom: 0.25rem;">Tipo</label>
                    <select wire:model="busquedaTipoDoc" class="form-select form-select-sm" style="width: 120px; font-size: 0.82rem;">
                        <option value="nif">NIF/DNI</option>
                        <option value="nie">NIE</option>
                        <option value="pasaporte">Pasaporte</option>
                    </select>
                </div>
                <div style="flex: 1;">
                    <label style="font-size: 0.78rem; color: var(--color-ink-600); display: block; margin-bottom: 0.25rem;">Número de documento</label>
                    <input wire:model="busquedaValorDoc" type="text" class="form-control form-control-sm"
                           placeholder="Ej: 12345678A"
                           style="font-family: 'JetBrains Mono', monospace; font-size: 0.85rem; text-transform: uppercase;"
                           autocomplete="off" />
                </div>
            </div>
        </div>

        {{-- Búsqueda por datos personales --}}
        <div style="background: #fff; border: 1px solid var(--color-ink-200); border-radius: 8px; padding: 1.25rem; margin-bottom: 1rem;">
            <p style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-ink-500); margin: 0 0 0.75rem;">O buscar por datos personales</p>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem 0.75rem;">
                <div>
                    <label style="font-size: 0.78rem; color: var(--color-ink-600); display: block; margin-bottom: 0.25rem;">Nombre</label>
                    <input wire:model="busquedaNombre" type="text" class="form-control form-control-sm" style="font-size: 0.85rem;" autocomplete="off" />
                </div>
                <div>
                    <label style="font-size: 0.78rem; color: var(--color-ink-600); display: block; margin-bottom: 0.25rem;">Primer apellido</label>
                    <input wire:model="busquedaApellido1" type="text" class="form-control form-control-sm" style="font-size: 0.85rem;" autocomplete="off" />
                </div>
                <div>
                    <label style="font-size: 0.78rem; color: var(--color-ink-600); display: block; margin-bottom: 0.25rem;">Segundo apellido</label>
                    <input wire:model="busquedaApellido2" type="text" class="form-control form-control-sm" style="font-size: 0.85rem;" autocomplete="off" />
                </div>
                <div>
                    <label style="font-size: 0.78rem; color: var(--color-ink-600); display: block; margin-bottom: 0.25rem;">Fecha de nacimiento</label>
                    <input wire:model="busquedaFechaNacimiento" type="date" class="form-control form-control-sm" style="font-size: 0.85rem;" />
                </div>
            </div>
        </div>

        @error('busqueda')
            <div class="alert alert-warning py-2" style="font-size: 0.82rem;">{{ $message }}</div>
        @enderror

        <button wire:click="buscar" class="btn btn-primary btn-sm" style="font-size: 0.85rem;">
            <i data-lucide="search" style="width:14px;height:14px;vertical-align:-2px;" aria-hidden="true"></i>
            Buscar
        </button>

        {{-- Resultados --}}
        @if($busquedaRealizada)
            <div style="margin-top: 1.5rem;">
                @if(count($resultadosBusqueda) === 0)
                    <div style="padding: 0.75rem 1rem; background: var(--color-bg-50); border: 1px solid var(--color-ink-200); border-radius: 8px; font-size: 0.85rem; color: var(--color-ink-600);">
                        No se han encontrado personas con los criterios indicados.
                    </div>
                @else
                    <p style="font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-ink-500); margin: 0 0 0.5rem;">
                        {{ count($resultadosBusqueda) }} posible{{ count($resultadosBusqueda) !== 1 ? 's coincidencias' : ' coincidencia' }} encontrada{{ count($resultadosBusqueda) !== 1 ? 's' : '' }}
                    </p>
                    @foreach($resultadosBusqueda as $r)
                        <div style="background: #fff; border: 1px solid {{ $r['bloquea'] ? 'var(--color-danger, #c0392b)' : 'var(--color-ink-200)' }}; border-radius: 8px; padding: 0.85rem 1rem; margin-bottom: 0.5rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem;">
                            <div>
                                <div style="font-weight: 600; font-size: 0.9rem; color: var(--color-ink-900);">{{ $r['nombreCompleto'] }}</div>
                                <div style="font-size: 0.75rem; color: var(--color-ink-500); margin-top: 0.15rem;">
                                    @if($r['fechaNacimiento'])
                                        {{ \Carbon\Carbon::parse($r['fechaNacimiento'])->format('d/m/Y') }} ·
                                    @endif
                                    Coincide en: {{ implode(', ', $r['camposCoincidentes']) }}
                                    · Score: {{ number_format($r['score'] * 100, 0) }}%
                                </div>
                                @if($r['bloquea'])
                                    <div style="margin-top: 0.3rem; font-size: 0.75rem; color: var(--color-danger, #c0392b); font-weight: 600;">
                                        Coincidencia muy probable — revisa la ficha antes de continuar
                                    </div>
                                @endif
                            </div>
                            <button wire:click="seleccionarExistente({{ $r['ciudadanoId'] }})"
                                    class="btn btn-outline-secondary btn-sm" style="font-size: 0.78rem; white-space: nowrap;">
                                Ver ficha
                            </button>
                        </div>
                    @endforeach
                @endif

                {{-- Acción continuar con el alta (solo si no hay bloqueo) --}}
                @php $hayBloqueo = collect($resultadosBusqueda)->contains('bloquea', true); @endphp
                @if(!$hayBloqueo)
                    <div style="margin-top: 1.25rem; padding: 1rem; border: 1px dashed var(--color-ink-300); border-radius: 8px; text-align: center; font-size: 0.85rem; color: var(--color-ink-600);">
                        ¿No está la persona que buscas?
                        <button wire:click="continuarConNuevoAlta"
                                class="btn btn-sm"
                                style="margin-left: 0.75rem; font-size: 0.82rem; background: var(--color-primary); color: #fff; border: none; padding: 0.3rem 0.9rem; border-radius: 6px;">
                            Dar de alta nueva persona
                        </button>
                    </div>
                @else
                    <div style="margin-top: 1rem; padding: 0.75rem 1rem; background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; font-size: 0.82rem; color: #7d5000;">
                        Hay una coincidencia casi segura. Revisa la ficha de la persona antes de continuar con el alta.
                    </div>
                @endif
            </div>
        @endif

    {{-- ================================================================== --}}
    {{-- FASE 2: VERIFICACIÓN EN PADRÓN                                     --}}
    {{-- ================================================================== --}}
    @elseif($fase === 'padron')

        <h2 style="font-size: 1.05rem; font-weight: 700; color: var(--color-ink-900); margin: 0 0 0.25rem;">Verificación en el padrón</h2>
        <p style="font-size: 0.85rem; color: var(--color-ink-600); margin: 0 0 1.5rem;">
            Consulta si la persona está empadronada. Si no lo está, selecciona el motivo para continuar.
        </p>

        @if(!$padronConsultado)
            <button wire:click="consultarPadron" class="btn btn-primary btn-sm" style="font-size: 0.85rem;">
                <i data-lucide="search" style="width:14px;height:14px;vertical-align:-2px;" aria-hidden="true"></i>
                Consultar padrón
            </button>
        @endif

        @if($padronConsultado && !$padronEncontrado)
            <div style="background: #fff; border: 1px solid var(--color-ink-200); border-radius: 8px; padding: 1.25rem; margin-top: 1rem;">
                <p style="font-size: 0.85rem; color: var(--color-ink-700); margin: 0 0 1rem;">
                    La persona no consta en el padrón municipal. Selecciona el motivo para continuar:
                </p>

                @php $esIntervencion = auth()->user()?->hasAnyRole(['intervencion', 'supervision']); @endphp

                <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                    @if($esIntervencion)
                        <button wire:click="seleccionarExcepcionPadron('psh')"
                                class="btn btn-outline-secondary btn-sm text-start" style="font-size: 0.85rem; padding: 0.6rem 1rem;">
                            <strong>Persona sin hogar (PSH)</strong> — sin domicilio formal, se usarán coordenadas de pernocta
                        </button>
                        <button wire:click="seleccionarExcepcionPadron('vvg')"
                                class="btn btn-outline-secondary btn-sm text-start" style="font-size: 0.85rem; padding: 0.6rem 1rem;">
                            <strong>Víctima de violencia de género (VVG)</strong> — domicilio protegido, sin consulta al padrón
                        </button>
                    @else
                        <p style="font-size: 0.78rem; color: var(--color-ink-500); font-style: italic;">
                            Las situaciones PSH y VVG requieren intervención de un profesional con rol de intervención.
                        </p>
                    @endif

                    <button wire:click="seleccionarExcepcionPadron('representante')"
                            class="btn btn-outline-secondary btn-sm text-start" style="font-size: 0.85rem; padding: 0.6rem 1rem;">
                        <strong>Representante</strong> — residente en otro municipio, alta solo para contacto y seguimiento
                    </button>
                    <button wire:click="seleccionarExcepcionPadron('otra')"
                            class="btn btn-outline-secondary btn-sm text-start" style="font-size: 0.85rem; padding: 0.6rem 1rem;">
                        <strong>Otra excepción</strong> — requiere justificación; queda registrada en auditoría
                    </button>
                </div>
            </div>
        @endif

    {{-- ================================================================== --}}
    {{-- FASE 3: FORMULARIO DE DATOS                                        --}}
    {{-- ================================================================== --}}
    @elseif($fase === 'formulario')

        <h2 style="font-size: 1.05rem; font-weight: 700; color: var(--color-ink-900); margin: 0 0 0.25rem;">Datos del ciudadano/a</h2>
        @if($excepcionPadron)
            <div style="margin-bottom: 1rem; padding: 0.5rem 0.85rem; background: #fff3cd; border: 1px solid #ffc107; border-radius: 6px; font-size: 0.8rem; color: #7d5000; display: inline-block;">
                Alta sin padrón — {{ match($excepcionPadron) { 'psh' => 'Persona sin hogar', 'vvg' => 'VVG', 'representante' => 'Representante', default => 'Otra excepción' } }}
            </div>
        @endif

        <div style="background: #fff; border: 1px solid var(--color-ink-200); border-radius: 8px; padding: 1.25rem; margin-bottom: 1rem;">
            <p style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-ink-500); margin: 0 0 0.75rem;">Identificación</p>

            @if($excepcionPadron === 'psh')
                <div class="mb-3">
                    <label style="font-size: 0.82rem; color: var(--color-ink-700); font-weight: 600;">Alias / nombre operativo <span style="color: var(--color-danger, #c0392b);">*</span></label>
                    <input wire:model="alias" type="text" class="form-control form-control-sm @error('alias') is-invalid @enderror"
                           placeholder="Ej: Juan el del cajero de la calle X" style="font-size: 0.85rem;" />
                    @error('alias') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            @endif

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem 0.75rem;">
                <div>
                    <label style="font-size: 0.82rem; color: var(--color-ink-700);">Nombre {{ $excepcionPadron !== 'psh' ? '*' : '' }}</label>
                    @if(isset($fuenteCampos['nombre']))
                        <span style="font-size: 0.7rem; background: #d4edda; color: #155724; border-radius: 4px; padding: 1px 5px; margin-left: 4px;">padrón</span>
                    @endif
                    <input wire:model="nombre" type="text" class="form-control form-control-sm @error('nombre') is-invalid @enderror" style="font-size: 0.85rem;" />
                    @error('nombre') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label style="font-size: 0.82rem; color: var(--color-ink-700);">Primer apellido {{ $excepcionPadron !== 'psh' ? '*' : '' }}</label>
                    @if(isset($fuenteCampos['apellido1']))
                        <span style="font-size: 0.7rem; background: #d4edda; color: #155724; border-radius: 4px; padding: 1px 5px; margin-left: 4px;">padrón</span>
                    @endif
                    <input wire:model="apellido1" type="text" class="form-control form-control-sm @error('apellido1') is-invalid @enderror" style="font-size: 0.85rem;" />
                    @error('apellido1') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label style="font-size: 0.82rem; color: var(--color-ink-700);">Segundo apellido</label>
                    @if(isset($fuenteCampos['apellido2']))
                        <span style="font-size: 0.7rem; background: #d4edda; color: #155724; border-radius: 4px; padding: 1px 5px; margin-left: 4px;">padrón</span>
                    @endif
                    <input wire:model="apellido2" type="text" class="form-control form-control-sm" style="font-size: 0.85rem;" />
                </div>
                <div>
                    <label style="font-size: 0.82rem; color: var(--color-ink-700);">Fecha de nacimiento</label>
                    @if(isset($fuenteCampos['fecha_nacimiento']))
                        <span style="font-size: 0.7rem; background: #d4edda; color: #155724; border-radius: 4px; padding: 1px 5px; margin-left: 4px;">padrón</span>
                    @endif
                    <input wire:model="fechaNacimiento" type="date" class="form-control form-control-sm @error('fechaNacimiento') is-invalid @enderror" style="font-size: 0.85rem;" />
                    @error('fechaNacimiento') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label style="font-size: 0.82rem; color: var(--color-ink-700);">Sexo <span style="color: var(--color-danger, #c0392b);">*</span></label>
                    @if(isset($fuenteCampos['sexo']))
                        <span style="font-size: 0.7rem; background: #d4edda; color: #155724; border-radius: 4px; padding: 1px 5px; margin-left: 4px;">padrón</span>
                    @endif
                    <select wire:model="sexo" class="form-select form-select-sm @error('sexo') is-invalid @enderror" style="font-size: 0.85rem;">
                        <option value="">-- Selecciona --</option>
                        <option value="M">Masculino</option>
                        <option value="F">Femenino</option>
                        <option value="D">No especificado</option>
                    </select>
                    @error('sexo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>

        <div style="background: #fff; border: 1px solid var(--color-ink-200); border-radius: 8px; padding: 1.25rem; margin-bottom: 1rem;">
            <p style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-ink-500); margin: 0 0 0.75rem;">Documento de identidad</p>
            <div style="display: flex; gap: 0.5rem; align-items: flex-end;">
                <div>
                    <label style="font-size: 0.78rem; color: var(--color-ink-600); display: block; margin-bottom: 0.25rem;">Tipo</label>
                    <select wire:model="tipoDocumento" class="form-select form-select-sm" style="width: 120px; font-size: 0.82rem;">
                        <option value="nif">NIF/DNI</option>
                        <option value="nie">NIE</option>
                        <option value="pasaporte">Pasaporte</option>
                    </select>
                </div>
                <div style="flex: 1;">
                    <label style="font-size: 0.78rem; color: var(--color-ink-600); display: block; margin-bottom: 0.25rem;">Número</label>
                    <input wire:model="valorDocumento" type="text" class="form-control form-control-sm"
                           style="font-family: 'JetBrains Mono', monospace; font-size: 0.85rem; text-transform: uppercase;"
                           autocomplete="off" />
                </div>
            </div>
        </div>

        <div style="background: #fff; border: 1px solid var(--color-ink-200); border-radius: 8px; padding: 1.25rem; margin-bottom: 1.25rem;">
            <p style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-ink-500); margin: 0 0 0.75rem;">Contacto</p>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem 0.75rem;">
                @if($excepcionPadron !== 'psh')
                    <div class="col-span-2" style="grid-column: 1 / -1;">
                        <label style="font-size: 0.82rem; color: var(--color-ink-700);">Domicilio</label>
                        @if(isset($fuenteCampos['direccion_texto']))
                            <span style="font-size: 0.7rem; background: #d4edda; color: #155724; border-radius: 4px; padding: 1px 5px; margin-left: 4px;">padrón</span>
                        @endif
                        <input wire:model="direccionTexto" type="text" class="form-control form-control-sm"
                               placeholder="Texto libre — se normalizará automáticamente"
                               style="font-size: 0.85rem;" />
                    </div>
                @endif
                <div>
                    <label style="font-size: 0.82rem; color: var(--color-ink-700);">Teléfono</label>
                    <input wire:model="telefono" type="text" class="form-control form-control-sm" style="font-size: 0.85rem;" />
                </div>
                <div>
                    <label style="font-size: 0.82rem; color: var(--color-ink-700);">Correo electrónico</label>
                    <input wire:model="email" type="email" class="form-control form-control-sm @error('email') is-invalid @enderror" style="font-size: 0.85rem;" />
                    @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>

        <div style="display: flex; gap: 0.75rem;">
            <button wire:click="guardar" class="btn btn-primary btn-sm" style="font-size: 0.85rem;">
                Guardar y continuar
            </button>
        </div>

    {{-- ================================================================== --}}
    {{-- FASE 4: CONFIRMACIÓN                                               --}}
    {{-- ================================================================== --}}
    @elseif($fase === 'confirmacion')

        <h2 style="font-size: 1.05rem; font-weight: 700; color: var(--color-ink-900); margin: 0 0 0.25rem;">Ciudadano/a registrado/a</h2>
        <p style="font-size: 0.85rem; color: var(--color-ink-600); margin: 0 0 1.5rem;">
            El alta se ha completado. Antes de terminar, puedes anotar el motivo de la visita y elegir el siguiente paso.
        </p>

        <div style="background: #fff; border: 1px solid var(--color-ink-200); border-radius: 8px; padding: 1.25rem; margin-bottom: 1rem;">
            <p style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-ink-500); margin: 0 0 0.5rem;">Resumen</p>
            <div style="font-size: 0.88rem; color: var(--color-ink-800); line-height: 1.7;">
                <strong>{{ trim("$nombre $apellido1 $apellido2") ?: ($alias ?: '—') }}</strong><br>
                @if($fechaNacimiento) Nacimiento: {{ \Carbon\Carbon::parse($fechaNacimiento)->format('d/m/Y') }}<br> @endif
                @if($valorDocumento) Documento: {{ strtoupper($tipoDocumento) }} {{ $valorDocumento }}<br> @endif
                Nivel de identificación:
                <span style="font-weight: 600;">{{ match($nombre || $valorDocumento) { true => ($valorDocumento ? 'identificado' : 'probable'), default => 'no identificado' } }}</span>
                @if($excepcionPadron)
                    <br>Contexto: {{ match($excepcionPadron) { 'psh' => 'PSH', 'vvg' => 'VVG', 'representante' => 'Representante', default => 'Otra excepción' } }}
                @endif
            </div>
        </div>

        <div style="background: #fff; border: 1px solid var(--color-ink-200); border-radius: 8px; padding: 1.25rem; margin-bottom: 1rem;">
            <label style="font-size: 0.82rem; color: var(--color-ink-700); font-weight: 600; display: block; margin-bottom: 0.35rem;">Primera demanda (opcional)</label>
            <p style="font-size: 0.78rem; color: var(--color-ink-500); margin: 0 0 0.5rem;">Motivo de la visita en las propias palabras del ciudadano/a. No es una valoración profesional.</p>
            <textarea wire:model="primeraDemanda" rows="3" class="form-control form-control-sm"
                      placeholder="Ej: «Vengo porque me han dicho que puedo pedir ayuda para pagar el alquiler»"
                      style="font-size: 0.85rem; resize: vertical;"></textarea>
        </div>

        <div style="background: #fff; border: 1px solid var(--color-ink-200); border-radius: 8px; padding: 1.25rem; margin-bottom: 1.25rem;">
            <p style="font-size: 0.82rem; font-weight: 600; color: var(--color-ink-700); margin: 0 0 0.75rem;">¿Qué hacemos a continuación?</p>
            <div style="display: flex; flex-direction: column; gap: 0.4rem; font-size: 0.85rem;">
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                    <input type="radio" wire:model="accionPostAlta" value="ficha"> Ir a la ficha del ciudadano/a
                </label>
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                    <input type="radio" wire:model="accionPostAlta" value="cita"> Crear una cita ahora
                </label>
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                    <input type="radio" wire:model="accionPostAlta" value="solo_alta"> Solo guardar y volver a la búsqueda
                </label>
            </div>
        </div>

        <button wire:click="confirmarAlta" class="btn btn-primary btn-sm" style="font-size: 0.85rem;">
            Confirmar y terminar
        </button>

    @endif

</div>
