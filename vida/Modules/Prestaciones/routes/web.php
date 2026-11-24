<?php

use Illuminate\Support\Facades\Route;
use Modules\Prestaciones\Http\Livewire\BandejaTramitacionPage;
use Modules\Prestaciones\Http\Livewire\SolicitudPrestacionPage;

/*
|--------------------------------------------------------------------------
| Rutas del módulo Prestaciones (fase 3 bis)
|--------------------------------------------------------------------------
|
| La pantalla de la solicitud la usan el trabajador social, la supervisión y tramitación
| (la policy decide qué ve cada uno). La bandeja es la superficie de tramitación.
*/

Route::middleware(['web', 'auth', 'tiene.rol'])->group(function () {
    Route::get('/prestaciones/solicitudes/{solicitud}', SolicitudPrestacionPage::class)
        ->middleware('role:intervencion|supervision|tramitacion')
        ->name('prestaciones.solicitud');

    Route::get('/tramitacion', BandejaTramitacionPage::class)
        // Superficie de tramitación (R4.4); la supervisión sigue entrando, como hasta ahora
        ->middleware('permission:tramitacion.bandeja|supervision.panel')
        ->name('prestaciones.bandeja');
});
