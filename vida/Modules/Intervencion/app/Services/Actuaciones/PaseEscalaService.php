<?php

namespace Modules\Intervencion\Services\Actuaciones;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Escalas\Enums\EstadoPase;
use Modules\Escalas\Models\PaseEscala;
use Modules\Escalas\Models\TipoEscala;
use Modules\Intervencion\Enums\TipoApunte;
use Modules\Intervencion\Enums\VisibilidadApunte;
use Modules\Intervencion\Models\Apunte;
use Modules\Intervencion\Services\CajaHerramientasEncargo;

/**
 * Registra pases de escala completados con su apunte `Escala` en la Historia Social, desde
 * la Historia o desde un encargo profesional (bloque B de
 * docs/instrucciones-cli/2026-11-encargo-actuaciones.md).
 *
 * Desde un encargo, la escala tiene que ser de la caja de herramientas de su área (EA-2).
 */
class PaseEscalaService
{
    /**
     * @param CajaHerramientasEncargo $caja
     */
    public function __construct(private readonly CajaHerramientasEncargo $caja) {}

    /**
     * Guarda el pase completado con su puntuación y su apunte, juntos.
     *
     * @param ContextoActuacion $contexto Historia, autor, plan, cita y encargo.
     * @param TipoEscala $tipoEscala Instrumento aplicado.
     * @param array<string, mixed> $respuestas Respuestas por id de ítem.
     *
     * @return PaseEscala
     *
     * @throws DomainException Si no puede actuar (RN-15, EA-4) o la escala no es del área del encargo.
     */
    public function registrar(ContextoActuacion $contexto, TipoEscala $tipoEscala, array $respuestas): PaseEscala
    {
        if ($contexto->encargo !== null && ! $this->caja->ofreceEscala($contexto->encargo, $tipoEscala->id)) {
            throw new DomainException('Esa escala no es del área del encargo.');
        }

        $contexto->exigirActuacion();
        $scoreTotal = $this->puntuacion($tipoEscala->schema, $respuestas);

        // Pase y apunte juntos: si el apunte se rechaza no queda el pase a medias (EST-04)
        return DB::transaction(function () use ($contexto, $tipoEscala, $respuestas, $scoreTotal): PaseEscala {
            $pase = PaseEscala::create([
                'tipo_escala_id' => $tipoEscala->id,
                'historia_id' => $contexto->historia->id,
                'profesional_id' => $contexto->autor->id,
                'fecha' => today()->toDateString(),
                'respuestas' => $respuestas,
                'score_total' => $scoreTotal,
                'estado' => EstadoPase::Completado,
                'derivacion_id' => $contexto->encargo?->id,
            ]);

            Apunte::create($contexto->datosApunte() + [
                'tipo' => TipoApunte::Escala,
                'apuntable_type' => PaseEscala::class,
                'apuntable_id' => $pase->id,
                'contenido' => "{$tipoEscala->nombre}: puntuación {$scoreTotal}",
                'visibilidad' => VisibilidadApunte::Profesionales,
            ]);

            return $pase;
        });
    }

    /**
     * Puntuación total del pase: suma de valor × peso de cada ítem respondido.
     *
     * @param array<string, mixed> $schema Schema del tipo de escala.
     * @param array<string, mixed> $respuestas Respuestas por id de ítem.
     *
     * @return int
     */
    public function puntuacion(array $schema, array $respuestas): int
    {
        $total = 0;
        foreach ($schema['secciones'] ?? [] as $seccion) {
            foreach ($seccion['items'] ?? [] as $item) {
                $valor = (int) ($respuestas[$item['id']] ?? 0);
                $peso = (int) ($item['peso'] ?? 1);
                $total += $valor * $peso;
            }
        }

        return $total;
    }
}
