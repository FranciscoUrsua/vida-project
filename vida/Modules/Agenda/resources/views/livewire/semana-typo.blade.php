<div>
    <div class="op-page">
        <h1>Semana tipo — {{ $centro->nombre }}</h1>

        @if ($avisoBorrador)
            <div class="alert alert-warning">
                Hay un borrador en curso para el mes siguiente. Los cambios se aplicarán si lo regeneras.
            </div>
        @endif

        @if ($estado === 'guardado')
            <div class="alert alert-success">Semana tipo guardada correctamente.</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger">
                @foreach ($errors->all() as $error) <p>{{ $error }}</p> @endforeach
            </div>
        @endif

        <button wire:click="guardar" class="btn btn-primary">Guardar semana tipo</button>
        <button wire:click="$set('semana', [])" class="btn btn-outline-secondary">Descartar cambios</button>
    </div>
</div>
