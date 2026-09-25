<?php

use Illuminate\Support\Facades\Route;
use Modules\Documentos\Exceptions\IntegridadDocumentoException;
use Modules\Documentos\Models\Documento;
use Modules\Documentos\Services\LecturaDocumentoService;

/*
|--------------------------------------------------------------------------
| Rutas web del módulo Documentos
|--------------------------------------------------------------------------
|
| Única ruta de descarga, protegida por firma temporal. Ningún fichero se sirve
| desde el almacenamiento: se descifra en memoria y se entrega con un nombre
| genérico, nunca el original.
|
| Pendiente (fase 2c): autorización por DocumentoPolicy y registro del acceso
| en auditoría.
|
*/

Route::middleware(['auth', 'signed'])
    ->get('/documentos/{documento}/ver', function (Documento $documento, LecturaDocumentoService $lectura) {
        $version = $documento->versionVigente;

        if ($version === null) {
            abort(404, 'El documento no tiene una versión disponible.');
        }

        try {
            $contenido = $lectura->contenido($version);
        } catch (IntegridadDocumentoException) {
            abort(500, 'No se ha podido recuperar el documento: su integridad no está garantizada.');
        }

        return response($contenido, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$lectura->nombreDescarga($version).'"',
            'Content-Length' => (string) strlen($contenido),
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    })
    ->name('documentos.ver');
