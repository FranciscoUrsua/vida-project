<?php

namespace Modules\Documentos\Support;

use Illuminate\Support\Carbon;

/**
 * Variables auxiliares calculadas en el momento de generar el informe: iguales para
 * todos los informes generados a la vez y sin configuración ni BD.
 *
 * Al añadir una variable, añadirla en etiquetas() (catálogo del editor) y en resolver().
 */
class VariablesDinamicas
{
    /**
     * Mapa clave → etiqueta para el catálogo de variables.
     *
     * @return array<string, string>
     */
    public static function etiquetas(): array
    {
        return [
            'fecha_hoy' => 'Sistema — Fecha de hoy (dd/mm/aaaa)',
            'año_actual' => 'Sistema — Año actual',
            'mes_actual' => 'Sistema — Mes actual (nombre)',
        ];
    }

    /**
     * Mapa clave → valor resuelto ahora.
     *
     * @return array<string, string>
     */
    public static function resolver(): array
    {
        $hoy = Carbon::now()->locale('es');

        return [
            'fecha_hoy' => $hoy->format('d/m/Y'),
            'año_actual' => $hoy->format('Y'),
            'mes_actual' => ucfirst($hoy->translatedFormat('F')),
        ];
    }
}
