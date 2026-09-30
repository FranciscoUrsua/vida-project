<?php

namespace Modules\Centro\Services\Asignacion;

use App\Models\Ciudadano;
use Illuminate\Support\Collection;
use Modules\Centro\Enums\MotivoAsignacionPendiente;
use Modules\Centro\Models\AmbitoTerritorial;
use Modules\Centro\Models\Centro;
use Modules\Organizacion\Models\Barrio;
use Modules\Organizacion\Models\Distrito;
use Modules\Organizacion\Models\SeccionCensal;

/**
 * Resuelve qué centro de un tipo atiende la dirección de una persona.
 *
 * La geografía se resuelve con los códigos oficiales de la dirección, no con
 * polígonos: se busca el centro cuyo ámbito incluye la sección censal; si no
 * hay, el barrio; si no, el distrito; si no, la ciudad completa. Gana la
 * coincidencia más específica. Solo se asigna lo inequívoco: sin códigos, sin
 * centro o con varios centros en el mismo nivel, el resultado es un motivo
 * para la bandeja (RN-09). Solo lee; no asigna.
 *
 * @see docs/modulo-asignacion.md §3.2
 */
class ResolucionCentroService
{
    /**
     * Resuelve el centro del tipo indicado para la dirección del ciudadano.
     *
     * @param Ciudadano $ciudadano
     * @param string $tipoCentro Clave de `centro.tipo`.
     * @return ResultadoResolucionCentro
     */
    public function resolver(Ciudadano $ciudadano, string $tipoCentro): ResultadoResolucionCentro
    {
        $seccion = $ciudadano->seccion_censal_codigo;

        if ($seccion === null && $ciudadano->barrio_codigo === null && $ciudadano->distrito_codigo === null) {
            return ResultadoResolucionCentro::pendiente(MotivoAsignacionPendiente::SinCodigos);
        }

        $centros = $this->centrosPorDomicilio($tipoCentro)->pluck('id');

        // Niveles de más a menos específico
        $niveles = [
            ['secciones_censales', SeccionCensal::class, $seccion ? SeccionCensal::where('codigo_ine', $seccion)->value('id') : null],
            ['barrios', Barrio::class, $ciudadano->barrio_codigo ? Barrio::where('codigo', $ciudadano->barrio_codigo)->value('id') : null],
            ['demarcacion_oficial', Distrito::class, $ciudadano->distrito_codigo ? Distrito::where('codigo', $ciudadano->distrito_codigo)->value('id') : null],
            ['ciudad_completa', null, null],
        ];

        foreach ($niveles as [$tipoAmbito, $clase, $unidadId]) {
            if ($clase !== null && $unidadId === null) {
                continue;
            }

            $coincidentes = AmbitoTerritorial::query()
                ->whereIn('centro_id', $centros)
                ->where('tipo', $tipoAmbito)
                ->when($clase !== null, fn ($q) => $q->where('referencia_tipo', $clase)->where('referencia_id', $unidadId))
                ->distinct()
                ->pluck('centro_id');

            if ($coincidentes->count() === 1) {
                return ResultadoResolucionCentro::centro(Centro::findOrFail($coincidentes->first()), $seccion);
            }

            if ($coincidentes->count() > 1) {
                return ResultadoResolucionCentro::pendiente(
                    MotivoAsignacionPendiente::Ambiguo,
                    Centro::whereIn('id', $coincidentes)->orderBy('nombre')->get(),
                    $seccion,
                );
            }
        }

        return ResultadoResolucionCentro::pendiente(MotivoAsignacionPendiente::SinCobertura, null, $seccion);
    }

    /**
     * Centros activos del tipo con adscripción por domicilio.
     *
     * @param string $tipoCentro
     * @return Collection<int, Centro>
     */
    public function centrosPorDomicilio(string $tipoCentro): Collection
    {
        return Centro::query()
            ->where('activo', true)
            ->where('inscripcion_libre', false)
            ->where('tipo_centro', $tipoCentro)
            ->get();
    }

    /**
     * Tipos de centro que asignan por domicilio: los que tienen algún centro
     * activo sin libre elección y con ámbito territorial. Un tipo sin ningún
     * ámbito configurado (albergues, centros de día…) no asigna por dirección:
     * si no, toda persona acabaría en la bandeja como «fuera de ámbito».
     *
     * @return list<string>
     */
    public function tiposPorDomicilio(): array
    {
        return Centro::query()
            ->where('activo', true)
            ->where('inscripcion_libre', false)
            ->whereNotNull('tipo_centro')
            ->whereHas('ambitosTeritoriales', fn ($q) => $q->where('tipo', '!=', 'poligono_gis'))
            ->distinct()
            ->orderBy('tipo_centro')
            ->pluck('tipo_centro')
            ->all();
    }

    /**
     * Secciones censales activas que no cubre ningún centro del tipo, a ningún
     * nivel. Sirve para ver los huecos al configurar, no al dar de alta.
     *
     * @param string $tipoCentro
     * @return Collection<int, SeccionCensal>
     */
    public function seccionesSinCobertura(string $tipoCentro): Collection
    {
        $centros = $this->centrosPorDomicilio($tipoCentro)->pluck('id');
        $ambitos = AmbitoTerritorial::whereIn('centro_id', $centros)->get(['tipo', 'referencia_tipo', 'referencia_id']);

        if ($ambitos->contains('tipo', 'ciudad_completa')) {
            return collect();
        }

        $ids = fn (string $clase) => $ambitos->where('referencia_tipo', $clase)->pluck('referencia_id')->all();

        return SeccionCensal::activas()
            ->whereNotIn('id', $ids(SeccionCensal::class))
            ->where(fn ($q) => $q->whereNull('barrio_id')->orWhereNotIn('barrio_id', $ids(Barrio::class)))
            ->whereNotIn('distrito_id', $ids(Distrito::class))
            ->orderBy('codigo_ine')
            ->get();
    }
}
