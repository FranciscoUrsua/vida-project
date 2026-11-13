<?php

namespace Modules\Centro\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Centro\Models\Centro;
use Modules\Centro\Models\TipoCentro;
use RuntimeException;

/**
 * Migración de datos de la fase Tipo de Centro (rama A, addenda D2: «el tipo manda»).
 *
 * Crea un tipo por cada clave del catálogo `centro.tipo`, asigna su UO funcional,
 * le da la configuración de la addenda §6.1 y la unión de los segmentos de sus
 * centros, y rellena `tipo_centro_id` en centros, `prestacion_tipo_centro` y
 * `asignaciones_centro`. Los valores de centro que difieren del tipo se descartan
 * y se devuelven en el informe (centro, propiedad, valor anterior); no aborta por
 * diferencias.
 *
 * Se detiene (RuntimeException) si queda un centro sin tipo deducible (paso 0.3).
 *
 * Trabaja con el constructor de consultas, no con modelos: la migración no debe
 * depender de reglas de modelo que cambien después. Es idempotente: no repite
 * tipos ya creados ni denominaciones ya existentes, así que también sirve de
 * seeder de tipos en una base nueva. Cuando las columnas antiguas del centro ya
 * no existen (después de 2026_11_12_100006), solo crea los tipos que falten.
 *
 * Decisiones del desarrollador del 2026-11-12 (ver CHANGELOG-112026.md): mapeo de
 * centros sin tipo, UO del centro de día de Entrevías y UO funcionales por tipo.
 */
class MigracionTipoCentro
{
    /** Siglas iniciales de cada clave del catálogo (editables después en la ficha del tipo). */
    public const SIGLAS = [
        'css_general' => 'CSS',
        'css_especializado' => 'CSSE',
        'centro_dia' => 'CD',
        'residencia_mayores' => 'RM',
        'residencia_disc' => 'RD',
        'albergue' => 'ALB',
        'piso_insercion' => 'PI',
        'centro_vg' => 'CVG',
        'punto_encuentro' => 'PEF',
        'servicio_especifico' => 'SE',
        'recurso_educativo' => 'RE',
        'centro_dia_inf' => 'CDI',
        'ciam' => 'CIAM',
    ];

    /** UO funcional por clave, por nombre de la UO; el resto, UO_FUNCIONAL_POR_DEFECTO. */
    public const UO_FUNCIONAL = [
        'css_general' => 'Departamento de Atención Primaria',
        'ciam' => 'CIAM - Centros Integrales de Atención a la Mujer',
        'centro_vg' => 'Dirección General de Igualdad y Contra la Violencia de Género',
    ];

    public const UO_FUNCIONAL_POR_DEFECTO = 'Dirección General de Servicios Sociales';

    /** Tipo de los centros que no lo tenían, por nombre del centro. */
    public const TIPO_DE_CENTROS_SIN_TIPO = [
        'Centro de Servicios Sociales Entrevías' => 'css_general',
        'Centro de día municipal Entrevías' => 'centro_dia',
    ];

    /** Centros sin UO a los que se crea su UO instrumental (RN-14), con el nombre de su UO padre. */
    public const UO_PARA_CENTROS_SIN_UO = [
        'Centro de día municipal Entrevías' => 'Departamento de Atención Primaria',
    ];

    /** Configuración inicial de los tipos (addenda §6.1); `inscripcion_libre` = libre elección. */
    public const CONFIGURACION = [
        'ciam' => ['inscripcion_libre' => true],
    ];

    /** Configuración común a todos los tipos (addenda §6.1). */
    public const CONFIGURACION_COMUN = [
        'inscripcion_libre' => false,
        'modo_asignacion_referencia' => 'quien_abre',
        'ventana_reparto_meses' => 12,
        'meses_inactividad_caso' => 6,
        'permite_restriccion_en_centro' => false,
    ];

    /**
     * Ejecuta la migración de datos.
     *
     *
     * @return array{tipos: int, descartes: list<array{tipo: string, centro: string, propiedad: string, valor: string}>, uo_creadas: list<string>}
     *
     * @throws RuntimeException Si un centro queda sin tipo deducible.
     */
    public function ejecutar(): array
    {
        $informe = ['tipos' => 0, 'descartes' => [], 'uo_creadas' => []];
        $hoy = Carbon::today()->toDateString();
        // Antes de eliminar la configuración del centro hay que migrarla; después, solo crear tipos
        $conDatosAntiguos = Schema::hasColumn('centros', 'tipo_centro');

        if ($conDatosAntiguos) {
            $this->asignarTipoACentrosSinTipo();
            $informe['uo_creadas'] = $this->crearUoDeCentrosSinUo();
        }

        $claves = DB::table('catalogos_sistema')->where('grupo', 'centro.tipo')->orderBy('orden')->get(['clave', 'etiqueta']);
        $uoRaiz = DB::table('unidades_organizativas')->whereNull('parent_id')->orderBy('id')->value('id');

        foreach ($claves as $clave) {
            if (DB::table('tipos_centro')->where('codigo', $clave->clave)->exists()) {
                continue;
            }

            $uoFuncional = $this->uoPorNombre(self::UO_FUNCIONAL[$clave->clave] ?? self::UO_FUNCIONAL_POR_DEFECTO) ?? $uoRaiz;
            if ($uoFuncional === null) {
                // Base sin organigrama (p. ej. la de tests recién migrada): no hay tipo que crear.
                continue;
            }

            $config = array_merge(self::CONFIGURACION_COMUN, self::CONFIGURACION[$clave->clave] ?? []);
            $tipoId = DB::table('tipos_centro')->insertGetId(array_merge($config, [
                'codigo' => $clave->clave,
                'nombre' => $clave->etiqueta,
                'siglas' => self::SIGLAS[$clave->clave] ?? mb_strtoupper(mb_substr($clave->clave, 0, 20)),
                'unidad_organizativa_id' => $uoFuncional,
                'activo' => true,
                'fecha_alta' => $hoy,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
            $informe['tipos']++;

            DB::table('denominaciones')->insert([
                'denominable_type' => TipoCentro::class,
                'denominable_id' => $tipoId,
                'nombre' => $clave->etiqueta,
                'nombre_corto' => self::SIGLAS[$clave->clave] ?? mb_strtoupper(mb_substr($clave->clave, 0, 20)),
                'vigente_desde' => $hoy,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if (! $conDatosAntiguos) {
                continue;
            }

            $centros = DB::table('centros')->where('tipo_centro', $clave->clave)->get();
            foreach ($centros as $centro) {
                array_push($informe['descartes'], ...$this->descartes($clave->clave, $centro, $config));
            }

            DB::table('centros')->where('tipo_centro', $clave->clave)->whereNull('tipo_centro_id')->update(['tipo_centro_id' => $tipoId]);
            DB::table('prestacion_tipo_centro')->where('tipo_centro', $clave->clave)->whereNull('tipo_centro_id')->update(['tipo_centro_id' => $tipoId]);
            DB::table('asignaciones_centro')->where('tipo_centro', $clave->clave)->whereNull('tipo_centro_id')->update(['tipo_centro_id' => $tipoId]);

            // Público del tipo: la unión de los segmentos de sus centros (addenda §6.1)
            $segmentos = DB::table('centro_segmento_poblacion')
                ->whereIn('centro_id', $centros->pluck('id'))
                ->distinct()
                ->pluck('segmento_poblacion_id');
            foreach ($segmentos as $segmento) {
                DB::table('tipo_centro_segmento_poblacion')->insert(['tipo_centro_id' => $tipoId, 'segmento_poblacion_id' => $segmento]);
            }
        }

        // Prestaciones configuradas centro a centro: se descartan (addenda §6.1)
        foreach (! $conDatosAntiguos ? [] : DB::table('centro_prestacion')->join('centros', 'centros.id', '=', 'centro_prestacion.centro_id')->get(['centros.nombre', 'centros.tipo_centro', 'centro_prestacion.prestacion_id']) as $fila) {
            $informe['descartes'][] = ['tipo' => (string) $fila->tipo_centro, 'centro' => $fila->nombre, 'propiedad' => 'centro_prestacion', 'valor' => (string) $fila->prestacion_id];
        }

        $this->crearDenominacionesDeCentros();

        $sinTipo = DB::table('centros')->whereNull('tipo_centro_id')->whereNull('deleted_at')->pluck('nombre');
        if ($sinTipo->isNotEmpty()) {
            throw new RuntimeException('Centros sin tipo deducible (paso 0.3): '.$sinTipo->implode(', '));
        }

        return $informe;
    }

    /**
     * Pone `tipo_centro` a los centros que no lo tenían, según el mapeo decidido.
     *
     * @return void
     */
    private function asignarTipoACentrosSinTipo(): void
    {
        foreach (self::TIPO_DE_CENTROS_SIN_TIPO as $nombre => $clave) {
            DB::table('centros')->whereNull('tipo_centro')->where('nombre', $nombre)->update(['tipo_centro' => $clave]);
        }
    }

    /**
     * Crea la UO instrumental de los centros sin UO previstos (RN-14).
     *
     * @return list<string> Nombres de las UO creadas.
     */
    private function crearUoDeCentrosSinUo(): array
    {
        $creadas = [];
        foreach (self::UO_PARA_CENTROS_SIN_UO as $nombreCentro => $nombrePadre) {
            $centro = DB::table('centros')->whereNull('unidad_organizativa_id')->where('nombre', $nombreCentro)->first();
            $padre = $this->uoPorNombre($nombrePadre);
            if ($centro === null || $padre === null) {
                continue;
            }

            $uoId = DB::table('unidades_organizativas')->insertGetId([
                'nombre' => $nombreCentro,
                'tipo' => 'centro',
                'parent_id' => $padre,
                'activa' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('centros')->where('id', $centro->id)->update(['unidad_organizativa_id' => $uoId]);
            $creadas[] = $nombreCentro;
        }

        return $creadas;
    }

    /**
     * Denominación inicial de cada centro, vigente desde su alta.
     *
     * @return void
     */
    private function crearDenominacionesDeCentros(): void
    {
        $conDenominacion = DB::table('denominaciones')->where('denominable_type', Centro::class)->pluck('denominable_id');
        foreach (DB::table('centros')->whereNotIn('id', $conDenominacion)->get(['id', 'nombre', 'nombre_corto', 'fecha_alta', 'created_at']) as $centro) {
            DB::table('denominaciones')->insert([
                'denominable_type' => Centro::class,
                'denominable_id' => $centro->id,
                'nombre' => $centro->nombre,
                'nombre_corto' => mb_substr($centro->nombre_corto ?? $centro->nombre, 0, 50),
                'vigente_desde' => $centro->fecha_alta ?? Carbon::parse($centro->created_at)->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Propiedades del centro que difieren de las del tipo y se descartan.
     *
     * @param string $clave Clave del tipo.
     * @param object $centro Fila de `centros`.
     * @param array<string, mixed> $config Configuración del tipo.
     *
     * @return list<array{tipo: string, centro: string, propiedad: string, valor: string}>
     */
    private function descartes(string $clave, object $centro, array $config): array
    {
        $descartes = [];
        foreach (['inscripcion_libre', 'modo_asignacion_referencia', 'ventana_reparto_meses', 'meses_inactividad_caso'] as $propiedad) {
            $delCentro = $centro->{$propiedad};
            if ((string) (is_bool($delCentro) ? (int) $delCentro : $delCentro) !== (string) (is_bool($config[$propiedad]) ? (int) $config[$propiedad] : $config[$propiedad])) {
                $descartes[] = ['tipo' => $clave, 'centro' => $centro->nombre, 'propiedad' => $propiedad, 'valor' => var_export($delCentro, true)];
            }
        }

        return $descartes;
    }

    /**
     * Id de la UO con ese nombre, o null.
     *
     * @param string $nombre Nombre exacto.
     *
     * @return int|null
     */
    private function uoPorNombre(string $nombre): ?int
    {
        $id = DB::table('unidades_organizativas')->where('nombre', $nombre)->orderBy('id')->value('id');

        return $id === null ? null : (int) $id;
    }
}
