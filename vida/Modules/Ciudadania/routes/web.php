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

Route::middleware(['web', 'auth', 'tiene.rol', 'role:intervencion|supervision|tramitacion|consulta_basica', 'audit.ciudadano'])
    ->group(function () {
        Route::get('/ciudadania/buscar', BuscarCiudadanoPage::class)->name('ciudadania.buscar');
        Route::get('/ciudadania/alta', AltaCiudadano::class)->name('ciudadania.alta');

        // Tras el alta, «dar cita» lleva a la cita directa de Agenda con la persona elegida
        Route::get('/ciudadania/ciudadano/{ciudadano}/nueva-cita', fn (int $ciudadano) => redirect()->route('agenda.citas.nueva', ['ciudadano' => $ciudadano]))
            ->name('ciudadania.ciudadano.nueva-cita');
    });

// La ficha autoriza y audita en AccesoExpediente (policy, rol operativo y colectivo
// protegido): sin middleware de rol ni audit.ciudadano, para que también el intento
// denegado quede en audits y no haya filas duplicadas.
Route::middleware(['web', 'auth', 'tiene.rol'])
    ->get('/ciudadania/ciudadano/{ciudadano}', FichaCiudadanoPage::class)
    ->name('ciudadania.ciudadano.ficha');
