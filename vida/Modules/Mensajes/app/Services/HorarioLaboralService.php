<?php

namespace Modules\Mensajes\Services;

use App\Models\CatalogoSistema;
use Illuminate\Support\Carbon;

/**
 * Servicio para calcular vencimientos de alertas en horas laborales.
 *
 * Lee el horario laboral por defecto desde catalogos_sistema con la
 * clave 'horario_laboral_defecto'. El valor esperado es un JSON:
 *
 *   {
 *     "inicio": "08:00",
 *     "fin": "17:00",
 *     "dias_semana": [1, 2, 3, 4, 5]   // 1=lunes, 5=viernes
 *   }
 *
 * El algoritmo avanza minuto a minuto contando solo los instantes
 * que caen dentro del horario laboral configurado.
 *
 * // TODO: integrar con módulo Agenda para usar el calendario laboral
 *           oficial cuando esté disponible.
 */
class HorarioLaboralService
{
    /** Plazo de reconocimiento en horas laborales. */
    private const HORAS_PLAZO = 4;

    /** Horario por defecto si no existe entrada en catalogos_sistema. */
    private const HORARIO_DEFECTO = [
        'inicio' => '08:00',
        'fin' => '17:00',
        'dias_semana' => [1, 2, 3, 4, 5],
    ];

    private array $horario;

    /**
     * Carga el horario laboral por defecto al inicializar el servicio.
     *
     * @return void
     */
    public function __construct()
    {
        $this->horario = $this->cargarHorario();
    }

    /**
     * Calcula la fecha de vencimiento en horas laborales.
     *
     * @param Carbon $desde Momento de inicio.
     */
    public function calcularExpiracion(Carbon $desde): Carbon
    {
        $cursor = $desde->copy();
        $minutosPlazo = self::HORAS_PLAZO * 60;
        $minutosAcumulados = 0;

        // Avanzar hasta encontrar un instante dentro del horario laboral
        $cursor = $this->avanzarHastaHorarioLaboral($cursor);

        while ($minutosAcumulados < $minutosPlazo) {
            // Si el cursor está fuera del horario laboral, saltar al inicio del siguiente día laboral
            if (! $this->estaEnHorarioLaboral($cursor)) {
                $cursor = $this->siguienteInicioLaboral($cursor);

                continue;
            }

            // Calcular cuántos minutos quedan en el bloque laboral de hoy
            $finDelDia = $this->finDelDiaLaboral($cursor);
            $minutosRestantes = (int) $cursor->diffInMinutes($finDelDia, false);

            if ($minutosRestantes <= 0) {
                $cursor = $this->siguienteInicioLaboral($cursor);

                continue;
            }

            $minutosNecesarios = $minutosPlazo - $minutosAcumulados;

            if ($minutosRestantes >= $minutosNecesarios) {
                // El vencimiento cae dentro de este bloque laboral
                $cursor->addMinutes($minutosNecesarios);
                $minutosAcumulados = $minutosPlazo;
            } else {
                // Consumir todo el bloque de hoy y continuar al siguiente día
                $minutosAcumulados += $minutosRestantes;
                $cursor = $this->siguienteInicioLaboral($cursor);
            }
        }

        return $cursor;
    }

    // -------------------------------------------------------------------------
    // Métodos privados auxiliares
    // -------------------------------------------------------------------------
    // -------------------------------------------------------------------------
    // Métodos privados auxiliares
    // -------------------------------------------------------------------------

    private function cargarHorario(): array
    {
        $registro = CatalogoSistema::where('grupo', 'sistema.configuracion')
            ->where('clave', 'horario_laboral_defecto')
            ->first();

        if (! $registro || empty($registro->valor)) {
            return self::HORARIO_DEFECTO;
        }

        $datos = json_decode($registro->valor, true);

        return is_array($datos) ? $datos : self::HORARIO_DEFECTO;
    }

    private function estaEnHorarioLaboral(Carbon $momento): bool
    {
        $diaSemana = (int) $momento->dayOfWeekIso; // 1=lunes, 7=domingo

        if (! in_array($diaSemana, $this->horario['dias_semana'], true)) {
            return false;
        }

        $horaInicio = Carbon::createFromFormat('H:i', $this->horario['inicio'], $momento->timezone)
            ->setDate($momento->year, $momento->month, $momento->day);

        $horaFin = Carbon::createFromFormat('H:i', $this->horario['fin'], $momento->timezone)
            ->setDate($momento->year, $momento->month, $momento->day);

        return $momento->greaterThanOrEqualTo($horaInicio)
            && $momento->lessThan($horaFin);
    }

    /**
     * Si el cursor ya está en horario laboral, lo devuelve sin cambios.
     * Si no, lo avanza al inicio del siguiente periodo laboral.
     */
    private function avanzarHastaHorarioLaboral(Carbon $cursor): Carbon
    {
        if ($this->estaEnHorarioLaboral($cursor)) {
            return $cursor;
        }

        return $this->siguienteInicioLaboral($cursor);
    }

    private function finDelDiaLaboral(Carbon $cursor): Carbon
    {
        return Carbon::createFromFormat('H:i', $this->horario['fin'], $cursor->timezone)
            ->setDate($cursor->year, $cursor->month, $cursor->day);
    }

    /**
     * Calcula el primer instante del siguiente día laborable a partir del cursor.
     */
    private function siguienteInicioLaboral(Carbon $cursor): Carbon
    {
        $siguiente = $cursor->copy()->addDay()->startOfDay();

        // Avanzar hasta encontrar un día de la semana laboral
        $intentos = 0;
        while (! in_array((int) $siguiente->dayOfWeekIso, $this->horario['dias_semana'], true)) {
            $siguiente->addDay();
            if (++$intentos > 7) {
                break; // Salvaguarda: no puede haber más de 7 días no laborables seguidos
            }
        }

        [$hora, $minuto] = explode(':', $this->horario['inicio']);

        return $siguiente->setTime((int) $hora, (int) $minuto);
    }
}
