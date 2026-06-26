<?php

use Illuminate\Support\Facades\Route;
use Modules\Agenda\Livewire\Supervisor\AusenciasSupervisorPage;
use Modules\Agenda\Livewire\Supervisor\CuadranteSupervisorPage;
use Modules\Agenda\Livewire\Supervisor\EventosSupervisorPage;
use Modules\Agenda\Livewire\Supervisor\ExcepcionesSupervisorPage;

/*
|--------------------------------------------------------------------------
| Rutas web del módulo Agenda — supervisor de centro
|--------------------------------------------------------------------------
|
| Protegidas por auth + role:supervision (Spatie).
| La vista por defecto al acceder al módulo es ausencias (pantalla de guardia).
|
*/

Route::middleware(['web', 'auth', 'role:supervision'])
    ->prefix('agenda/supervisor')
    ->name('agenda.supervisor.')
    ->group(function () {
        Route::redirect('/', '/agenda/supervisor/ausencias');
        Route::get('/cuadrante', CuadranteSupervisorPage::class)->name('cuadrante');
        Route::get('/ausencias', AusenciasSupervisorPage::class)->name('ausencias');
        Route::get('/excepciones', ExcepcionesSupervisorPage::class)->name('excepciones');
        Route::get('/eventos', EventosSupervisorPage::class)->name('eventos');
    });
