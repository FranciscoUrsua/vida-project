<?php

use Illuminate\Support\Facades\Route;
use Modules\Supervision\Http\Livewire\ActividadesPage;
use Modules\Supervision\Http\Livewire\AprobacionesPage;
use Modules\Supervision\Http\Livewire\AuditoriaPage;
use Modules\Supervision\Http\Livewire\ConfiguracionCentroPage;
use Modules\Supervision\Http\Livewire\CuadrantePage;
use Modules\Supervision\Http\Livewire\EquipoPage;
use Modules\Supervision\Http\Livewire\InicioPage;
use Modules\Supervision\Http\Livewire\PlazasPage;

/*
|--------------------------------------------------------------------------
| Rutas web del módulo Supervisión — interfaz operativo
|--------------------------------------------------------------------------
|
| Rutas protegidas por auth + role:supervision.
| El backoffice Filament (/admin) no usa estas rutas.
|
*/

Route::middleware(['web', 'auth', 'role:supervision'])->prefix('supervision')->name('supervision.')->group(function () {
    Route::redirect('/', '/supervision/inicio');
    Route::get('/inicio', InicioPage::class)->name('inicio');
    Route::get('/cuadrante', CuadrantePage::class)->name('cuadrante');
    Route::get('/actividades', ActividadesPage::class)->name('actividades');
    Route::get('/actividades/{id}', ActividadesPage::class)->name('actividades.detalle');
    Route::get('/plazas', PlazasPage::class)->name('plazas');
    Route::get('/equipo', EquipoPage::class)->name('equipo');
    Route::get('/equipo/{profesional}', EquipoPage::class)->name('equipo.profesional');
    Route::get('/auditoria', AuditoriaPage::class)->name('auditoria');
    Route::get('/aprobaciones', AprobacionesPage::class)->name('aprobaciones');
    Route::get('/configuracion', ConfiguracionCentroPage::class)->name('configuracion');
});
