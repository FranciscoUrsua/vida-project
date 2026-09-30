{{-- Motivo obligatorio y botones de una decisión de la bandeja de asignaciones (dentro del form de AsignacionesPage) --}}
<label for="motivo-asignacion" class="form-label mb-0">Motivo</label>
<textarea id="motivo-asignacion" rows="2" wire:model="motivo"
          class="form-control @error('motivo') is-invalid @enderror"></textarea>
@error('motivo') <div class="invalid-feedback">{{ $message }}</div> @enderror
<div class="d-flex gap-2">
    <button type="submit" class="btn btn-primary btn-sm">{{ ['descartar' => 'Confirmar', 'confirmar' => 'Trasladar'][$accion] ?? 'Asignar' }}</button>
    <button type="button" class="btn btn-link btn-sm" wire:click="cancelar">Cancelar</button>
</div>
