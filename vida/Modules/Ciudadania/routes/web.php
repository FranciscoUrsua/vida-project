<?php

use Illuminate\Support\Facades\Route;
use Modules\Ciudadania\Http\Livewire\AltaCiudadano;
use Modules\Ciudadania\Http\Livewire\FichaCiudadanoPage;
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
        Route::get('/ciudadania/ciudadano/{ciudadano}', FichaCiudadanoPage::class)->name('ciudadania.ciudadano.ficha');

        // Ruta pendiente de implementación — stub para que los redirects de agenda funcionen.
        Route::get('/ciudadania/ciudadano/{ciudadano}/nueva-cita', fn () => abort(501, 'Nueva cita — pendiente'))->name('ciudadania.ciudadano.nueva-cita');
    });
