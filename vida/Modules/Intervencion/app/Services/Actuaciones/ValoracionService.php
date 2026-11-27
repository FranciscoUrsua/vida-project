<?php

namespace Modules\Intervencion\Services\Actuaciones;

use App\Support\Fichas\ValidadorDatosFicha;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Intervencion\Enums\TipoApunte;
use Modules\Intervencion\Enums\VisibilidadApunte;
use Modules\Intervencion\Models\Apunte;
use Modules\Intervencion\Models\Ficha;
use Modules\Intervencion\Models\TipoFicha;
use Modules\Intervencion\Services\CajaHerramientasEncargo;

/**
 * Registra fichas de valoración completadas con su apunte `Valoracion` en la Historia
 * Social, desde la Historia o desde un encargo profesional (bloque B de
 * docs/instrucciones-cli/2026-11-encargo-actuaciones.md).
 *
 * Desde un encargo, la ficha tiene que ser de la caja de herramientas de su área (EA-2): se
 * comprueba aquí, en el servidor, aunque el selector ya la filtre. Qué fichas ofrece la
 * Historia en cada centro lo sigue decidiendo la pantalla (catálogo por tipo de centro).
 */
class ValoracionService
{
    /**
     * @param CajaHerramientasEncargo $caja
     */
    public function __construct(private readonly CajaHerramientasEncargo $caja) {}

    /**
     * Guarda la ficha completada (con la copia del schema) y su apunte, juntos.
     *
     * @param ContextoActuacion $contexto Historia, autor, plan, cita y encargo.
     * @param TipoFicha $tipoFicha Tipo de ficha de valoración.
     * @param array<string, mixed> $datos Valores del formulario, por id de campo.
     * @param string|null $notas Notas libres del profesional.
     *
     * @return Ficha
     *
     * @throws DomainException Si no puede actuar (RN-15, EA-4) o la ficha no es del área del encargo.
     * @throws ValidationException Si un campo de catálogo no trae una clave vigente de su grupo.
     */
    public function registrar(ContextoActuacion $contexto, TipoFicha $tipoFicha, array $datos, ?string $notas = null): Ficha
    {
        if ($contexto->encargo !== null && ! $this->caja->ofreceFicha($contexto->encargo, $tipoFicha->id)) {
            throw new DomainException('Esa ficha de valoración no es del área del encargo.');
        }

        $contexto->exigirActuacion();

        // Claves de catálogo vigentes, también ante una petición forjada (SD-4)
        ValidadorDatosFicha::validar($tipoFicha->schema['campos'] ?? [], $datos);

        // Ficha y apunte juntos: el apunte comprueba RN-15 con el centro de la operación y, si
        // lo rechaza, no queda una ficha huérfana (EST-04, ficha 14 de staging)
        return DB::transaction(function () use ($contexto, $tipoFicha, $datos, $notas): Ficha {
            $ficha = Ficha::create([
                'historia_id' => $contexto->historia->id,
                'tipo_ficha_id' => $tipoFicha->id,
                'schema_snapshot' => $tipoFicha->schema,
                'datos' => $datos ?: null,
                'notas' => $notas ?: null,
                'completada' => true,
                'profesional_id' => $contexto->autor->id,
                'derivacion_id' => $contexto->encargo?->id,
            ]);

            Apunte::create($contexto->datosApunte() + [
                'tipo' => TipoApunte::Valoracion,
                'apuntable_type' => Ficha::class,
                'apuntable_id' => $ficha->id,
                'contenido' => $tipoFicha->nombre,
                'visibilidad' => VisibilidadApunte::Profesionales,
            ]);

            return $ficha;
        });
    }
}
