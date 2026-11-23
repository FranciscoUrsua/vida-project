{{-- Error 429 propio (R-03) --}}
@include('errors.pantalla', ['codigo' => 429, 'titulo' => 'Demasiadas peticiones', 'mensaje' => 'Has hecho demasiadas peticiones seguidas. Espera un momento e inténtalo de nuevo.'])
