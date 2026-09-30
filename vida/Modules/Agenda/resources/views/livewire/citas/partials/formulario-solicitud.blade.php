{{-- Campos de una solicitud de cita (trait FormularioSolicitudCita). Parámetros: $prefijo (ids únicos), $conMotivo (solo quien accede a la Historia Social escribe el motivo profesional). Los errores llegan con la clave del servicio. --}}
@php
    use Modules\Agenda\Enums\DestinoCita;
    use Modules\Agenda\Enums\UrgenciaCita;
@endphp
<div class="row g-2">
    <div class="col-md-6">
        <label for="{{ $prefijo }}-tipo" class="form-label small fw-semibold mb-1">Tipo de cita</label>
        <select id="{{ $prefijo }}-tipo" wire:model="formSolicitud.tipo_cita_id" class="form-select form-select-sm">
            <option value="">Elige…</option>
            @foreach($this->opcionesTiposCita as $id => $nombre)
                <option value="{{ $id }}">{{ $nombre }}</option>
            @endforeach
        </select>
        @error('tipo_cita_id') <div class="small text-danger">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6">
        <label for="{{ $prefijo }}-urgencia" class="form-label small fw-semibold mb-1">Urgencia</label>
        <select id="{{ $prefijo }}-urgencia" wire:model="formSolicitud.urgencia" class="form-select form-select-sm">
            @foreach(UrgenciaCita::cases() as $urgencia)
                <option value="{{ $urgencia->value }}">{{ $urgencia->label() }}</option>
            @endforeach
        </select>
        @error('urgencia') <div class="small text-danger">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6">
        <label for="{{ $prefijo }}-destino" class="form-label small fw-semibold mb-1">Para</label>
        <select id="{{ $prefijo }}-destino" wire:model.live="formSolicitud.destino" class="form-select form-select-sm">
            @foreach(DestinoCita::cases() as $destino)
                <option value="{{ $destino->value }}">{{ $destino->label() }}</option>
            @endforeach
        </select>
        @error('destino') <div class="small text-danger">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6">
        @if($formSolicitud['destino'] === DestinoCita::ProfesionalConcreto->value)
            <label for="{{ $prefijo }}-profesional" class="form-label small fw-semibold mb-1">Profesional</label>
            <select id="{{ $prefijo }}-profesional" wire:model="formSolicitud.profesional_destino_id" class="form-select form-select-sm">
                <option value="">Elige…</option>
                @foreach($this->opcionesProfesionalesCita as $id => $nombre)
                    <option value="{{ $id }}">{{ $nombre }}</option>
                @endforeach
            </select>
            @error('profesional_destino_id') <div class="small text-danger">{{ $message }}</div> @enderror
        @elseif(in_array($formSolicitud['destino'], [DestinoCita::Servicio->value, DestinoCita::PrimerLibre->value], true))
            <label for="{{ $prefijo }}-perfil" class="form-label small fw-semibold mb-1">
                Perfil{{ $formSolicitud['destino'] === DestinoCita::PrimerLibre->value ? ' (opcional)' : '' }}
            </label>
            <select id="{{ $prefijo }}-perfil" wire:model="formSolicitud.servicio_destino" class="form-select form-select-sm">
                <option value="">{{ $formSolicitud['destino'] === DestinoCita::PrimerLibre->value ? 'Cualquiera' : 'Elige…' }}</option>
                @foreach($this->opcionesPerfilesCita as $slug => $nombre)
                    <option value="{{ $slug }}">{{ $nombre }}</option>
                @endforeach
            </select>
            @error('servicio_destino') <div class="small text-danger">{{ $message }}</div> @enderror
        @endif
    </div>
    <div class="col-md-6">
        <label for="{{ $prefijo }}-desde" class="form-label small fw-semibold mb-1">No antes de (opcional)</label>
        <input id="{{ $prefijo }}-desde" type="date" wire:model="formSolicitud.no_antes_de" class="form-control form-control-sm">
        @error('no_antes_de') <div class="small text-danger">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6">
        <label for="{{ $prefijo }}-hasta" class="form-label small fw-semibold mb-1">No después de (opcional)</label>
        <input id="{{ $prefijo }}-hasta" type="date" wire:model="formSolicitud.no_despues_de" class="form-control form-control-sm">
        <div class="form-text">Si se deja vacío, el plazo de la urgencia.</div>
        @error('no_despues_de') <div class="small text-danger">{{ $message }}</div> @enderror
    </div>
    @if($conMotivo)
        <div class="col-12">
            <label for="{{ $prefijo }}-motivo" class="form-label small fw-semibold mb-1">Motivo profesional</label>
            <textarea id="{{ $prefijo }}-motivo" wire:model="formSolicitud.motivo" rows="2" class="form-control form-control-sm" maxlength="2000"></textarea>
            <div class="form-text">Solo lo ven los roles con acceso a la Historia Social.</div>
            @error('motivo') <div class="small text-danger">{{ $message }}</div> @enderror
        </div>
    @endif
    <div class="col-12">
        <label for="{{ $prefijo }}-observaciones" class="form-label small fw-semibold mb-1">Observaciones para quien cita</label>
        <input id="{{ $prefijo }}-observaciones" type="text" wire:model="formSolicitud.observaciones_citacion" class="form-control form-control-sm" maxlength="500" placeholder="Sin datos sensibles: «mejor por la tarde», «llamar al móvil»">
        @error('observaciones_citacion') <div class="small text-danger">{{ $message }}</div> @enderror
    </div>
</div>
