{{-- Cambio de la propia contraseña (App\Livewire\CambiarContrasena) --}}
<div class="op-page">
<div class="row justify-content-center g-0 p-3 p-lg-4">
<div class="col-12 col-md-8 col-lg-6 col-xl-5">

    <h1 class="h5 fw-bold mb-1">Cambiar contraseña</h1>
    <p class="small text-body-secondary mb-3">
        Al menos 12 caracteres, con letras y números. Al cambiarla se cierran las sesiones que tengas abiertas en otros equipos.
    </p>

    @if($cambiada)
        <div class="alert alert-success small d-flex gap-2 align-items-center" role="status">
            <x-heroicon-o-check-circle class="icon-16 flex-shrink-0" aria-hidden="true"/>
            Contraseña cambiada.
        </div>
    @endif

    <form wire:submit="guardar" class="card card-body" novalidate>
        <div class="mb-3">
            <label for="contrasena-actual" class="form-label small fw-semibold">Contraseña actual</label>
            <input id="contrasena-actual" type="password" wire:model="actual" autocomplete="current-password"
                   @class(['form-control', 'is-invalid' => $errors->has('actual')])>
            @error('actual')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="contrasena-nueva" class="form-label small fw-semibold">Contraseña nueva</label>
            <input id="contrasena-nueva" type="password" wire:model="nueva" autocomplete="new-password"
                   @class(['form-control', 'is-invalid' => $errors->has('nueva')])>
            @error('nueva')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="contrasena-confirmacion" class="form-label small fw-semibold">Repite la contraseña nueva</label>
            <input id="contrasena-confirmacion" type="password" wire:model="nuevaConfirmacion" autocomplete="new-password"
                   class="form-control">
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">Cambiar contraseña</button>
            <a href="{{ route('inicio') }}" class="btn btn-outline-secondary">Volver</a>
        </div>
    </form>

</div>
</div>
</div>
