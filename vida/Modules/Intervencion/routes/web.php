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

Route::middleware(['auth', 'role:intervencion'])->prefix('intervencion')->name('intervencion.')->group(function () {
    Route::get('/', fn () => redirect()->route('intervencion.agenda.index'));
    Route::get('/agenda', AgendaPage::class)->name('agenda.index');
    // Las rutas de Mis casos, Alertas/mensajes y Buscar ciudadano
    // se añadirán en Entrega 2 y 3.
});
