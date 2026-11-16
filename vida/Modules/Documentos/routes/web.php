<?php

use Illuminate\Support\Facades\Route;
use Modules\Documentos\Http\Controllers\DocumentoController;
use Modules\Documentos\Http\Controllers\InformePdfController;
use Modules\Documentos\Http\Livewire\NuevoInformeWizard;

/*
|--------------------------------------------------------------------------
| Rutas web del módulo Documentos
|--------------------------------------------------------------------------
|
| Únicas salidas de documentos custodiados, con sesión y firma temporal. El
| controlador autoriza (DocumentoPolicy), descifra en memoria, audita (ver o
| exportar) y entrega el PDF con un nombre genérico, nunca el original. Ninguna
| ruta sirve el disco de documentos.
|
*/

Route::middleware(['web', 'auth', 'signed'])->group(function (): void {
    Route::get('/documentos/{documento}/ver', [DocumentoController::class, 'ver'])->name('documentos.ver');
    Route::get('/documentos/{documento}/descargar', [DocumentoController::class, 'descargar'])->name('documentos.descargar');
});

/*
| Informes profesionales (fase 5). El PDF de un borrador (vista previa y PDF para
| firmar) solo para su autor: InformePdfController autoriza con InformePolicy y audita.
| El PDF firmado sale de la custodia (documentos.ver).
*/
Route::middleware(['web', 'auth'])->group(function (): void {
    Route::get('/informes/{informe}/vista-previa', [InformePdfController::class, 'vistaPrevia'])->name('documentos.informes.vista-previa');
    Route::get('/informes/{informe}/para-firmar', [InformePdfController::class, 'paraFirmar'])->name('documentos.informes.para-firmar');
});

// Asistente de informes en la interfaz operativa de Intervención (mismo grupo de middleware).
Route::middleware(['web', 'auth', 'tiene.rol', 'role:intervencion'])->prefix('intervencion')->name('intervencion.informes.')->group(function (): void {
    Route::get('/ciudadano/{historia}/informe/nuevo', NuevoInformeWizard::class)->name('nuevo');
    Route::get('/informe/{informe}', NuevoInformeWizard::class)->name('editar');
});
