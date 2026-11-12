<?php

namespace Modules\Centro\Traits;

use App\Models\User;
use DomainException;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Centro\Models\Denominacion;

/**
 * Denominaciones con vigencia para tipos de centro y centros (RN-02).
 *
 * El modelo que lo usa guarda en sus columnas de caché (`columnasCacheDenominacion()`)
 * la denominación vigente hoy, porque muchas consultas y pantallas las leen. La
 * caché se actualiza al crear una denominación ya en vigor y con el comando
 * diario `denominaciones:aplicar`.
 */
trait TieneDenominaciones
{
    /**
     * Columnas del modelo que guardan el nombre y el nombre corto vigentes.
     *
     * @return array{0: string, 1: string}
     */
    abstract public function columnasCacheDenominacion(): array;

    /**
     * Historial de denominaciones del modelo.
     *
     * @return MorphMany<Denominacion, $this>
     */
    public function denominaciones(): MorphMany
    {
        return $this->morphMany(Denominacion::class, 'denominable')->orderBy('vigente_desde');
    }

    /**
     * Denominación vigente en una fecha, o null si no había ninguna.
     *
     * @param Carbon|string $fecha Fecha de referencia.
     *
     * @return Denominacion|null
     */
    public function denominacionEn(Carbon|string $fecha): ?Denominacion
    {
        return $this->denominaciones()->vigentesEn(Carbon::parse($fecha))->first();
    }

    /**
     * Denominación vigente hoy.
     *
     * @return Denominacion|null
     */
    public function denominacionVigente(): ?Denominacion
    {
        return $this->denominacionEn(today());
    }

    /**
     * Programa una nueva denominación desde una fecha: cierra la vigente el día
     * anterior y crea la nueva. Si entra en vigor hoy o antes, actualiza la caché.
     *
     * @param string $nombre Nombre completo.
     * @param string $corto Siglas del tipo o nombre corto del centro.
     * @param Carbon $desde Fecha de entrada en vigor.
     * @param User|null $actor Usuario que la programa.
     *
     * @throws DomainException
     *
     * @return Denominacion
     */
    public function programarDenominacion(string $nombre, string $corto, Carbon $desde, ?User $actor): Denominacion
    {
        return DB::transaction(function () use ($nombre, $corto, $desde, $actor): Denominacion {
            $abierta = $this->denominaciones()->whereNull('vigente_hasta')->lockForUpdate()->first();

            if ($abierta !== null) {
                if ($desde->lte($abierta->vigente_desde)) {
                    throw new DomainException('La nueva denominación debe empezar después de la vigente.');
                }
                $abierta->update(['vigente_hasta' => $desde->copy()->subDay()->toDateString()]);
            }

            $nueva = $this->denominaciones()->create([
                'nombre' => $nombre,
                'nombre_corto' => $corto,
                'vigente_desde' => $desde->toDateString(),
                'creado_por_id' => $actor?->id,
            ]);

            if ($desde->lte(today())) {
                $this->aplicarDenominacionVigente();
            }

            return $nueva;
        });
    }

    /**
     * Copia la denominación vigente hoy a las columnas de caché, si ha cambiado.
     *
     * @return bool Si la caché ha cambiado.
     */
    public function aplicarDenominacionVigente(): bool
    {
        $vigente = $this->denominacionVigente();
        if ($vigente === null) {
            return false;
        }

        [$columnaNombre, $columnaCorto] = $this->columnasCacheDenominacion();
        if ($this->{$columnaNombre} === $vigente->nombre && $this->{$columnaCorto} === $vigente->nombre_corto) {
            return false;
        }

        // Escritura directa: la caché no es un cambio de configuración y no debe
        // generar versión ni pasar por las reglas de inmutabilidad del modelo.
        static::query()->whereKey($this->getKey())->update([
            $columnaNombre => $vigente->nombre,
            $columnaCorto => $vigente->nombre_corto,
        ]);
        $this->{$columnaNombre} = $vigente->nombre;
        $this->{$columnaCorto} = $vigente->nombre_corto;
        $this->syncOriginalAttributes([$columnaNombre, $columnaCorto]);

        return true;
    }
}
