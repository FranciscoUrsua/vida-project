<?php

namespace Modules\Intervencion\Support\Ui;

use App\Support\Ui\Tono;

/**
 * Tono de color de los valores de Intervención que no tienen enum propio
 * (estado de la historia, nivel de acceso en la búsqueda, seguimiento,
 * actuaciones y tipos de cita). Las clases salen de {@see Tono}.
 */
final class Tonos
{
    /**
     * Badge de estado de la historia social.
     *
     * @param string $estado Valor de `historias_sociales.estado`.
     * @return Tono
     */
    public static function estadoHistoria(string $estado): Tono
    {
        return match ($estado) {
            'en_seguimiento' => Tono::Exito,
            'cerrada' => Tono::Neutro,
            default => Tono::Primario,
        };
    }

    /**
     * Punto de nivel de un resultado de la búsqueda de ciudadanos.
     *
     * El nivel 3 (colectivo especialmente protegido) usa el color `protected`:
     * avisa de que el acceso requiere solicitud a la UO responsable.
     *
     * @param int $nivel 1 = HS en tu UO, 2 = HS en otra UO, 3 = colectivo protegido.
     * @return Tono
     */
    public static function nivelBusqueda(int $nivel): Tono
    {
        return match ($nivel) {
            3 => Tono::Protegido,
            2 => Tono::Aviso,
            default => Tono::Exito,
        };
    }

    /**
     * Badge del próximo seguimiento de un caso; `null` si no hay ninguno.
     *
     * @param string $estado vencido|proximo|programado|sin
     * @return Tono|null
     */
    public static function estadoSeguimiento(string $estado): ?Tono
    {
        return match ($estado) {
            'vencido' => Tono::Peligro,
            'proximo' => Tono::Aviso,
            'programado' => Tono::Exito,
            default => null,
        };
    }

    /**
     * Badge de estado de una actuación del plan.
     *
     * @param string $estado Estado de la actuación.
     * @return Tono
     */
    public static function estadoActuacion(string $estado): Tono
    {
        return match ($estado) {
            'pendiente' => Tono::Aviso,
            'en_proceso', 'en_curso' => Tono::Primario,
            'conseguido', 'completada' => Tono::Exito,
            default => Tono::Neutro,
        };
    }

    /**
     * Color de una cita de la agenda según su tipo.
     *
     * @param string $tipo entrevista|seguimiento|urgencia|evento
     * @return Tono
     */
    public static function tipoCita(string $tipo): Tono
    {
        return match ($tipo) {
            'entrevista' => Tono::Primario,
            'seguimiento' => Tono::Exito,
            'urgencia' => Tono::Peligro,
            default => Tono::Neutro,
        };
    }
}
