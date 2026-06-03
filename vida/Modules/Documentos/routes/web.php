<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Modules\Documentos\Models\Documento;

/*
|--------------------------------------------------------------------------
| Rutas web del módulo Documentos
|--------------------------------------------------------------------------
|
| Únicamente la ruta de descarga protegida por firma temporal.
| Todos los accesos a ficheros pasan por esta ruta — nunca por rutas públicas.
|
*/

Route::middleware(['auth', 'signed'])
    ->get('/documentos/{documento}/ver', function (Documento $documento) {
        $disco = Storage::disk($documento->disco);

        if (! $disco->exists($documento->ruta_almacenamiento)) {
            abort(404, 'El fichero no está disponible.');
        }

        $contenido = $disco->get($documento->ruta_almacenamiento);

        // Verificación de integridad en cada descarga
        if (hash('sha256', $contenido) !== $documento->hash_sha256) {
            abort(500, 'Error de integridad: el fichero ha sido alterado.');
        }

        return response($contenido, 200, [
            'Content-Type' => $documento->mime_type,
            'Content-Disposition' => 'inline; filename="'.rawurlencode($documento->nombre_original).'"',
            'Content-Length' => $documento->tamano_bytes,
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    })
    ->name('documentos.ver');
