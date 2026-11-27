<?php

namespace Modules\Intervencion\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Intervencion\Models\TipoFicha;

/**
 * Ficha inicial «Datos sociodemográficos» (SD-12 y bloque E de
 * docs/instrucciones-cli/2026-11-ficha-sociodemografica.md): tipo de ficha de la persona con
 * `codigo = sociodemografica` y diez campos, todos voluntarios (SD-9).
 *
 * Idempotente por `codigo`: si el tipo ya existe (aunque esté desactivado) **no** se
 * toca, porque su schema puede haberse editado en el backoffice. La llama la migración de datos
 * (para que llegue a cada entorno con el despliegue, decisiones técnicas §16) y DatabaseSeeder.
 *
 * Sin lógica condicional: el schema no la admite (`anio_llegada_espana` lo explica en su ayuda;
 * BACKLOG). El año de llegada tampoco se limita a cuatro cifras: el tipo `numero` no tiene
 * mínimo ni máximo.
 */
class FichaSociodemograficaSeeder extends Seeder
{
    /** Código estable de la ficha (SD-6). */
    public const CODIGO = 'sociodemografica';

    /**
     * Crea el tipo de ficha si no existe.
     *
     * @return void
     */
    public function run(): void
    {
        if (TipoFicha::withoutGlobalScopes()->where('codigo', self::CODIGO)->exists()) {
            return;
        }

        TipoFicha::create([
            'nombre' => 'Datos sociodemográficos',
            'codigo' => self::CODIGO,
            'descripcion' => 'Origen, idioma, estudios, situación laboral e ingresos de la persona. Todos los datos son voluntarios.',
            'uso' => 'persona',
            'activo' => true,
            'schema' => ['campos' => self::campos()],
        ]);
    }

    /**
     * Campos de la ficha, en su orden.
     *
     * @return list<array<string, mixed>>
     */
    public static function campos(): array
    {
        $definicion = [
            ['pais_nacimiento', 'catalogo', 'País de nacimiento', ['catalogo' => 'comun.pais']],
            ['nacionalidad', 'catalogo', 'Nacionalidad', ['catalogo' => 'comun.pais']],
            ['segunda_nacionalidad', 'catalogo', 'Segunda nacionalidad', ['catalogo' => 'comun.pais']],
            ['anio_llegada_espana', 'numero', 'Año de llegada a España', ['descripcion' => 'Año con cuatro cifras. Solo si nació fuera de España.']],
            ['idioma_principal', 'catalogo', 'Idioma principal', ['catalogo' => 'comun.idioma']],
            ['nivel_estudios', 'catalogo', 'Estudios terminados de mayor nivel', ['catalogo' => 'comun.nivel_estudios']],
            ['situacion_laboral', 'catalogo', 'Situación laboral', ['catalogo' => 'comun.situacion_laboral']],
            ['ingresos_netos_mensuales', 'numero', 'Ingresos netos mensuales', ['unidad' => '€', 'descripcion' => 'Ingresos propios netos de un mes normal.']],
            ['numero_pagas', 'numero', 'Número de pagas', ['descripcion' => 'Pagas al año (12, 14…).']],
            ['fecha_referencia_ingresos', 'fecha', 'Fecha de referencia de los ingresos', ['descripcion' => 'Mes o fecha a la que corresponden los ingresos.']],
        ];

        $campos = [];
        foreach ($definicion as $i => [$id, $tipo, $etiqueta, $extra]) {
            $campos[] = ['id' => $id, 'tipo' => $tipo, 'etiqueta' => $etiqueta, 'obligatorio' => false, 'orden' => $i + 1] + $extra;
        }

        return $campos;
    }
}
