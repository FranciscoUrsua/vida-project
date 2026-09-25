<?php

use Illuminate\Support\Facades\Route;
use Modules\Documentos\Http\Controllers\DocumentoController;

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
