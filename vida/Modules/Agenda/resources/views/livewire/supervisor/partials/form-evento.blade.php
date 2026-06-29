{{--
    Partial reutilizable para crear y editar EventoAgenda.
    Variables esperadas:
      $prefix     — prefijo del modelo Livewire ('form' o 'formEdicion')
      $accion     — método Livewire a llamar al enviar ('crear' o 'actualizar')
      $labelBoton — texto del botón de envío
--}}
<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label" for="{{ $prefix }}-nombre">Nombre <span class="text-danger">*</span></label>
        <input id="{{ $prefix }}-nombre" type="text"
               class="form-control form-control-sm @error($prefix . '.nombre') is-invalid @enderror"
               wire:model="{{ $prefix }}.nombre" maxlength="200">
        @error($prefix . '.nombre') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-3">
        <label class="form-label" for="{{ $prefix }}-tipo">Tipo <span class="text-danger">*</span></label>
        <select id="{{ $prefix }}-tipo"
                class="form-select form-select-sm @error($prefix . '.tipo_evento') is-invalid @enderror"
                wire:model="{{ $prefix }}.tipo_evento">
            <option value="">Selecciona…</option>
            <option value="sesion_interna">Sesión interna</option>
            <option value="actividad_colectiva">Actividad colectiva</option>
            <option value="coordinacion">Coordinación</option>
        </select>
        @error($prefix . '.tipo_evento') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-3">
        <label class="form-label" for="{{ $prefix }}-espacio">Espacio</label>
        <select id="{{ $prefix }}-espacio"
                class="form-select form-select-sm @error($prefix . '.espacio_id') is-invalid @enderror"
                wire:model="{{ $prefix }}.espacio_id">
            <option value="">Sin espacio</option>
            @foreach($this->espaciosDelCentro as $espacio)
                <option value="{{ $espacio->id }}">{{ $espacio->nombre }}</option>
            @endforeach
        </select>
        @error($prefix . '.espacio_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-3">
        <label class="form-label" for="{{ $prefix }}-fecha">Fecha <span class="text-danger">*</span></label>
        <input id="{{ $prefix }}-fecha" type="date"
               class="form-control form-control-sm @error($prefix . '.fecha') is-invalid @enderror"
               wire:model="{{ $prefix }}.fecha">
        @error($prefix . '.fecha') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-3">
        <label class="form-label" for="{{ $prefix }}-inicio">Hora inicio <span class="text-danger">*</span></label>
        <input id="{{ $prefix }}-inicio" type="time"
               class="form-control form-control-sm @error($prefix . '.hora_inicio') is-invalid @enderror"
               wire:model="{{ $prefix }}.hora_inicio">
        @error($prefix . '.hora_inicio') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-3">
        <label class="form-label" for="{{ $prefix }}-duracion">Duración (min) <span class="text-danger">*</span></label>
        <input id="{{ $prefix }}-duracion" type="number" min="5" max="480"
               class="form-control form-control-sm @error($prefix . '.duracion_minutos') is-invalid @enderror"
               wire:model="{{ $prefix }}.duracion_minutos" placeholder="60">
        @error($prefix . '.duracion_minutos') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-12">
        <label class="form-label">Profesionales convocados</label>
        <div class="d-flex flex-wrap gap-2">
            @forelse($this->profesionalesDelCentro as $prof)
            @if($prof->usuario)
            <div class="form-check">
                <input class="form-check-input" type="checkbox"
                       id="{{ $prefix }}-prof-{{ $prof->usuario->id }}"
                       value="{{ $prof->usuario->id }}"
                       wire:model="{{ $prefix }}.profesionales_ids">
                <label class="form-check-label small" for="{{ $prefix }}-prof-{{ $prof->usuario->id }}">
                    {{ $prof->nombre_completo }}
                </label>
            </div>
            @endif
            @empty
            <p class="text-body-secondary small mb-0">No hay profesionales en tu unidad organizativa.</p>
            @endforelse
        </div>
    </div>

    <div class="col-12 d-flex gap-2">
        <button type="button"
                class="btn btn-primary btn-sm"
                wire:click="{{ $accion }}"
                wire:loading.attr="disabled">
            <span wire:loading wire:target="{{ $accion }}"
                  class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>
            {{ $labelBoton }}
        </button>
        @if($accion === 'crear')
        <button type="button"
                class="btn btn-outline-secondary btn-sm"
                wire:click="$set('mostrarFormulario', false)">
            Cancelar
        </button>
        @else
        <button type="button"
                class="btn btn-outline-secondary btn-sm"
                wire:click="cerrarEdicion">
            Cancelar
        </button>
        @endif
    </div>
</div>
