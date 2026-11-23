{{--
    Pantalla de error común (R-03): mensaje en español, botón «Volver al inicio», fecha y hora
    e identificador para encontrar el error en el log. Nunca muestra el mensaje de la excepción
    (puede estar en inglés o llevar detalles internos), trazas ni rutas de fichero.

    Parámetros: $codigo (int), $titulo, $mensaje.

    Con sesión, en los 4xx y fuera de una petición Livewire, se pinta dentro de la superficie
    operativa del usuario. En los 5xx, sin sesión o en una petición Livewire (que la muestra en un
    modal) se pinta en un marco mínimo: así un fallo de la base de datos o del shell no impide
    mostrar el error.
--}}
@php
    $errorId = \App\Support\Errores\IdentificadorError::actual();
    $momento = now()->format('d/m/Y H:i:s');
    // Solo las excepciones con mensaje para el usuario (en español) sustituyen al texto genérico
    if (($exception ?? null) instanceof \App\Exceptions\ActuacionNoPermitida) {
        $mensaje = $exception->getMessage();
    }
    $conSuperficie = $codigo < 500
        && auth()->check()
        && ! request()->hasHeader('X-Livewire')
        && ! request()->is('admin', 'admin/*');
@endphp

@component($conSuperficie ? auth()->user()->layoutOperativo() : 'errors.marco', ['titulo' => $titulo])
        <div class="container py-5">
            <div class="row justify-content-center">
                <div class="col-12 col-md-10 col-lg-7">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body p-4 p-md-5 text-center">
                            <div class="d-flex justify-content-center text-primary mb-3" aria-hidden="true">
                                @if($codigo === 403)
                                    <x-heroicon-o-lock-closed class="icon-40"/>
                                @elseif($codigo >= 500)
                                    <x-heroicon-o-wrench-screwdriver class="icon-40"/>
                                @else
                                    <x-heroicon-o-exclamation-circle class="icon-40"/>
                                @endif
                            </div>

                            <h1 class="h4 fw-bold mb-2">{{ $titulo }}</h1>
                            <p class="text-body-secondary mb-4">{{ $mensaje }}</p>

                            <a href="{{ url('/') }}" class="btn btn-primary">Volver al inicio</a>

                            <dl class="small text-body-secondary border-top mt-4 pt-3 mb-0">
                                <div class="d-flex justify-content-center gap-2">
                                    <dt class="fw-normal">Fecha y hora:</dt>
                                    <dd class="mb-0">{{ $momento }}</dd>
                                </div>
                                <div class="d-flex justify-content-center gap-2">
                                    <dt class="fw-normal">Código del error:</dt>
                                    <dd class="mb-0 font-monospace">{{ $errorId }}</dd>
                                </div>
                            </dl>
                            <p class="small text-body-secondary mt-2 mb-0">
                                Si necesitas ayuda, indica este código a quien te dé soporte.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
@endcomponent
