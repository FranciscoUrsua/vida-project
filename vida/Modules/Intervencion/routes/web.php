<?php

use Illuminate\Support\Facades\Route;
use Modules\Intervencion\Http\Livewire\AgendaPage;

/*
|--------------------------------------------------------------------------
| Rutas web del módulo Intervención — interfaz operativo
|--------------------------------------------------------------------------
|
| Rutas protegidas por auth + role:intervencion.
| El backoffice Filament (/admin) no usa estas rutas.
|
*/

Route::middleware(['auth', 'tiene.rol', 'role:intervencion'])->prefix('intervencion')->name('intervencion.')->group(function () {
    Route::get('/', fn () => redirect()->route('intervencion.agenda.index'));
    Route::get('/agenda', AgendaPage::class)->name('agenda.index');
    Route::get('/casos', \Modules\Intervencion\Http\Livewire\MisCasosPage::class)->name('casos.index');
    Route::get('/mensajes', \Modules\Mensajes\Http\Livewire\BuzonPage::class)->name('mensajes.index');
    Route::get('/buscar', \Modules\Intervencion\Http\Livewire\BuscarCiudadanoPage::class)->name('buscar.index');
    Route::get('/ciudadano/{historia}', \Modules\Intervencion\Http\Livewire\CiudadanoPage::class)
        ->name('ciudadano.show')
        ->middleware('can:view,historia');
    Route::get('/ciudadano/{historia}/valoracion', \Modules\Intervencion\Http\Livewire\RegistrarValoracionPage::class)
        ->name('valoracion.nueva');
    Route::get('/ciudadano/{historia}/escala', \Modules\Intervencion\Http\Livewire\RegistrarEscalaPage::class)
        ->name('escala.nueva');
});
