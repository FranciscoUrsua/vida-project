<?php

use Illuminate\Support\Facades\Route;
use Modules\Ciudadania\Http\Livewire\AltaCiudadano;
use Modules\Intervencion\Http\Livewire\BuscarCiudadanoPage;

/*
|--------------------------------------------------------------------------
| Rutas web del módulo Ciudadanía
|--------------------------------------------------------------------------
|
| Rutas accesibles a todos los roles operativos (intervención, supervisión,
| tramitación y consulta básica). El backoffice Filament usa rutas propias.
|
*/

Route::middleware(['web', 'auth', 'tiene.rol', 'role:intervencion|supervision|tramitacion|consulta_basica'])
    ->group(function () {
        Route::get('/ciudadania/buscar', BuscarCiudadanoPage::class)->name('ciudadania.buscar');
        Route::get('/ciudadania/alta', AltaCiudadano::class)->name('ciudadania.alta');

        // Rutas pendientes de implementación — solo para que los redirects de alta
        // apunten a nombres de ruta ya definidos desde el primer día.
        Route::get('/ciudadania/ciudadano/{id}', fn () => abort(501, 'Ficha ciudadano — pendiente'))->name('ciudadania.ciudadano.ficha');
        Route::get('/ciudadania/ciudadano/{id}/nueva-cita', fn () => abort(501, 'Nueva cita — pendiente'))->name('ciudadania.ciudadano.nueva-cita');
    });
