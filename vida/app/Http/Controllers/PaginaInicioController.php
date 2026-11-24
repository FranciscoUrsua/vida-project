<?php

namespace App\Http\Controllers;

use App\Support\Superficie\DestinoInicial;
use App\Support\Superficie\Superficie;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Guarda la preferencia de página de inicio del propio usuario (R4.6).
 *
 * Solo admite «Automática» o una superficie operativa a la que el usuario tiene acceso en este
 * momento; la administración no es elegible. La comprobación es del servidor: el menú de
 * usuario solo ofrece opciones válidas, pero la petición puede llegar sin pasar por él.
 */
class PaginaInicioController extends Controller
{
    /** Valor del formulario que equivale a la página automática (`null` en la BD). */
    public const AUTOMATICA = 'automatica';

    /**
     * Valida y guarda la preferencia del usuario autenticado.
     *
     * @param Request $request
     *
     * @return RedirectResponse
     */
    public function guardar(Request $request): RedirectResponse
    {
        $usuario = $request->user();

        $validas = array_map(fn (Superficie $s) => $s->value, DestinoInicial::opcionesPreferencia($usuario));

        $datos = $request->validate([
            'pagina_inicio' => ['required', 'string', Rule::in([self::AUTOMATICA, ...$validas])],
        ], [
            'pagina_inicio.in' => 'No puedes elegir esa página de inicio.',
        ]);

        $usuario->pagina_inicio = $datos['pagina_inicio'] === self::AUTOMATICA ? null : $datos['pagina_inicio'];
        $usuario->save();

        return back()->with('toast', 'Página de inicio guardada.');
    }
}
