{{-- Registro de una ficha de la persona (RegistrarFichaPersonaPage, bloque D de la ficha sociodemográfica) --}}
<div class="op-page">
<div class="row justify-content-center g-0 p-3 p-lg-4">
<div class="col-12 col-lg-9 col-xl-7">

    <a href="{{ $this->volverUrl() }}" wire:navigate
       class="link-secondary small text-decoration-none d-inline-flex align-items-center gap-1 mb-3">
        <x-heroicon-o-arrow-left class="icon-14" aria-hidden="true"/>
        Volver a la ficha de la persona
    </a>

    <header class="mb-3">
        <p class="small text-uppercase fw-semibold text-body-secondary mb-1">
            @if($borradorId)
                Continuar borrador
            @elseif($esActualizacion)
                Actualizar datos
            @else
                Registrar datos
            @endif
        </p>
        <h1 class="h5 fw-bold mb-1">{{ $this->tipoFicha->nombre }}</h1>
        <p class="small text-body-secondary mb-0">{{ $this->ciudadano->nombre_completo }}</p>
    </header>

    <div class="alert alert-info small d-flex gap-2 align-items-start" role="note">
        <x-heroicon-o-information-circle class="icon-16 flex-shrink-0 mt-1" aria-hidden="true"/>
        <div>
            Todos los datos son voluntarios. Si la persona prefiere no responder a una pregunta, marca «No desea contestar».
            @if($esActualizacion)
                Se parte de los datos anteriores: cambia solo lo que haya cambiado. Los anteriores se conservan.
            @endif
        </div>
    </div>

    @if($avisos !== [])
        <div class="alert alert-warning small" role="alert">
            <p class="fw-semibold mb-1">Revisa estos datos: el valor anterior ya no está en la lista y se ha dejado vacío.</p>
            <ul class="mb-0">
                @foreach($avisos as $aviso)
                    <li>{{ $aviso['etiqueta'] }} (antes: {{ $aviso['valor_anterior'] }})</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card card-body">
        @if($this->tipoFicha->descripcion)
            <p class="small text-body-secondary mb-3">{{ $this->tipoFicha->descripcion }}</p>
        @endif

        <x-ficha.campos :campos="$this->campos" prefijo="datos" :no-contesta="$noContesta" />

        <div class="border-top pt-3 mt-2">
            <label for="ficha-persona-notas" class="form-label small fw-semibold mb-1">Notas</label>
            <textarea id="ficha-persona-notas" wire:model.blur="notas" rows="3" class="form-control form-control-sm"></textarea>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2 mt-4">
        <button type="button" wire:click="completar" class="btn btn-primary" wire:loading.attr="disabled">
            Completar
        </button>
        <button type="button" wire:click="guardarBorrador" class="btn btn-outline-primary" wire:loading.attr="disabled">
            Guardar borrador
        </button>
        <a href="{{ $this->volverUrl() }}" wire:navigate class="btn btn-outline-secondary">
            Cancelar
        </a>
    </div>

</div>
</div>
</div>
