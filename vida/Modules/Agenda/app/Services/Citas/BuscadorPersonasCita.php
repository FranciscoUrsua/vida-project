<?php

namespace Modules\Agenda\Services\Citas;

use App\Models\Ciudadano;
use App\Models\Scopes\AmbitoUoScope;
use App\Models\User;
use Illuminate\Support\Collection;
use Modules\Ciudadania\Models\CiudadanoIdentificador;

/**
 * Localiza a la persona a la que se da una cita en ventanilla o por teléfono, o
 * a la que corresponde una cita externa pendiente de identificar.
 *
 * Si el término contiene cifras se busca por documento (hash, sin descifrar);
 * si no, por nombre, filtrando en PHP sobre los datos descifrados con el mismo
 * límite que el buscador de ciudadanos (docs/decisiones-tecnicas.md §4.3).
 * No crea personas: si no aparece, se da de alta con el flujo normal.
 *
 * Colectivos protegidos (CLAUDE.md §3): quien no puede ver a la persona no la
 * encuentra por nombre. Por su documento exacto aparece, pero quien pinta el
 * resultado no muestra nombre, documento ni teléfono (visible()): solo que hay
 * una restricción.
 */
class BuscadorPersonasCita
{
    /** Registros que se descifran como máximo en la búsqueda por nombre. */
    private const MAX_DESCIFRADOS = 500;

    /**
     * Personas que coinciden con el término.
     *
     * @param string $termino Documento o parte del nombre (mínimo 2 caracteres).
     * @param User $usuario Quien busca.
     * @param int $limite
     * @return Collection<int, Ciudadano>
     */
    public function buscar(string $termino, User $usuario, int $limite = 20): Collection
    {
        $termino = trim($termino);

        if (mb_strlen($termino) < 2) {
            return collect();
        }

        $consulta = Ciudadano::withoutGlobalScope(AmbitoUoScope::class)->with('documentoVigente')->where('activo', true);

        if (preg_match('/\d/', $termino) === 1) {
            $ids = CiudadanoIdentificador::where('valor_hash', hash('sha256', strtolower($termino)))
                ->whereNull('fecha_fin')
                ->pluck('ciudadano_id');

            return $consulta->whereKey($ids)->limit($limite)->get();
        }

        $buscado = mb_strtolower($termino);

        return $consulta->limit(self::MAX_DESCIFRADOS)->get()
            ->filter(fn (Ciudadano $c) => str_contains(mb_strtolower($c->nombre_completo), $buscado))
            ->reject(fn (Ciudadano $c) => ! $this->visible($c, $usuario))
            ->take($limite)
            ->values();
    }

    /**
     * Si se pueden mostrar los datos personales de la persona: siempre, salvo
     * colectivo protegido que la policy no deja ver al usuario.
     *
     * @param Ciudadano $ciudadano
     * @param User $usuario
     * @return bool
     */
    public function visible(Ciudadano $ciudadano, User $usuario): bool
    {
        return ! $ciudadano->colectivo_extra_protegido || $usuario->can('view', $ciudadano);
    }
}
