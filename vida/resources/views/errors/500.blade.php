{{-- Error 500 propio (R-03) --}}
@include('errors.pantalla', ['codigo' => 500, 'titulo' => 'Se ha producido un error', 'mensaje' => 'Algo ha fallado al procesar la petición. No es culpa tuya; el error ha quedado registrado.'])
