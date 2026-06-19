<?php

use Illuminate\Support\Facades\Route;
use Modules\Intervencion\Http\Livewire\AgendaPage;
use Modules\Intervencion\Http\Livewire\PlanPage;
use Modules\Intervencion\Http\Livewire\BuscarCiudadanoPage;
use Modules\Intervencion\Http\Livewire\CiudadanoPage;
use Modules\Intervencion\Http\Livewire\MisCasosPage;
use Modules\Intervencion\Http\Livewire\RegistrarEscalaPage;
use Modules\Intervencion\Http\Livewire\RegistrarValoracionPage;
use Modules\Intervencion\Http\Livewire\VerFichaPage;
use Modules\Mensajes\Http\Livewire\BuzonPage;

/*
|--------------------------------------------------------------------------
| Rutas web del módulo Intervención — interfaz operativo
|--------------------------------------------------------------------------
|
| Rutas protegidas por auth + role:intervencion.
| El backoffice Filament (/admin) no usa estas rutas.
|
*/

Route::middleware(['web', 'auth', 'primer.acceso'])->group(function () {
    Route::get('/intervencion/plan/crear', PlanPage::class)->name('plan.crear');
    Route::get('/intervencion/plan/{plan}', PlanPage::class)->name('plan.show');
});

Route::middleware(['web', 'auth', 'tiene.rol', 'role:intervencion'])->prefix('intervencion')->name('intervencion.')->group(function () {
    Route::get('/', fn () => redirect()->route('intervencion.agenda.index'));
    Route::get('/agenda', AgendaPage::class)->name('agenda.index');
    Route::get('/casos', MisCasosPage::class)->name('casos.index');
    Route::get('/mensajes', BuzonPage::class)->name('mensajes.index');
    Route::get('/buscar', BuscarCiudadanoPage::class)->name('buscar.index');
    Route::get('/ciudadano/{historia}', CiudadanoPage::class)
        ->name('ciudadano.show')
        ->middleware('can:view,historia');
    Route::get('/ciudadano/{historia}/valoracion', RegistrarValoracionPage::class)
        ->name('valoracion.nueva');
    Route::get('/ciudadano/{historia}/ficha/{ficha}', VerFichaPage::class)
        ->name('ficha.show');
    Route::get('/ciudadano/{historia}/escala', RegistrarEscalaPage::class)
        ->name('escala.nueva');
});
