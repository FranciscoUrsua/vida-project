<?php

use Illuminate\Support\Facades\Route;
use Modules\Mensajes\Http\Livewire\BandejaAlertasYMensajes;
use Modules\Mensajes\Http\Livewire\ControlAlertasPage;
use Modules\Supervision\Http\Livewire\ActividadesPage;
use Modules\Supervision\Http\Livewire\AprobacionesPage;
use Modules\Supervision\Http\Livewire\AsignacionesPage;
use Modules\Supervision\Http\Livewire\AuditoriaPage;
use Modules\Supervision\Http\Livewire\ConfiguracionCentroPage;
use Modules\Supervision\Http\Livewire\CuadrantePage;
use Modules\Supervision\Http\Livewire\DerivacionesPage;
use Modules\Supervision\Http\Livewire\EquipoPage;
use Modules\Supervision\Http\Livewire\InicioPage;
use Modules\Supervision\Http\Livewire\PlazasPage;
use Modules\Supervision\Http\Livewire\RepartoCasosPage;

/*
|--------------------------------------------------------------------------
| Rutas web del módulo Supervisión — interfaz operativo
|--------------------------------------------------------------------------
|
| Rutas protegidas por auth + permiso `supervision.panel` (R4.4).
| El backoffice Filament (/admin) no usa estas rutas.
|
*/

// Superficie de Supervisión por permiso, no por rol (R4.4)
Route::middleware(['web', 'auth', 'permission:supervision.panel'])->prefix('supervision')->name('supervision.')->group(function () {
    Route::redirect('/', '/supervision/inicio');
    Route::get('/inicio', InicioPage::class)->name('inicio');
    Route::get('/bandeja/{pestana?}', BandejaAlertasYMensajes::class)
        ->whereIn('pestana', ['alertas', 'avisos', 'mensajes'])
        ->name('bandeja');
    Route::get('/control-alertas', ControlAlertasPage::class)->name('control-alertas');
    Route::get('/cuadrante', CuadrantePage::class)->name('cuadrante');
    Route::get('/actividades', ActividadesPage::class)->name('actividades');
    Route::get('/actividades/{id}', ActividadesPage::class)->name('actividades.detalle');
    Route::get('/plazas', PlazasPage::class)->name('plazas');
    Route::get('/equipo', EquipoPage::class)->name('equipo');
    Route::get('/equipo/{profesional}', EquipoPage::class)->name('equipo.profesional');
    Route::get('/asignaciones/reparto/{reparto}', RepartoCasosPage::class)->name('asignaciones.reparto');
    Route::get('/asignaciones/{pestana?}', AsignacionesPage::class)
        ->whereIn('pestana', ['pendientes', 'actividad'])
        ->name('asignaciones');
    Route::get('/derivaciones', DerivacionesPage::class)->name('derivaciones');
    Route::get('/auditoria', AuditoriaPage::class)->name('auditoria');
    Route::get('/aprobaciones', AprobacionesPage::class)->name('aprobaciones');
    Route::get('/configuracion', ConfiguracionCentroPage::class)->name('configuracion');
});
