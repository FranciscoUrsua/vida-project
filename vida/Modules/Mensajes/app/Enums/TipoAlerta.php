<?php

namespace Modules\Mensajes\Enums;

/**
 * Nivel de gravedad de una alerta del sistema.
 *
 * - aviso: notificación informativa. No requiere reconocimiento explícito
 *   ni tiene plazo de vencimiento.
 * - alerta: requiere reconocimiento explícito en un plazo máximo de 4 horas
 *   laborales. Si vence sin ser reconocida, escala al supervisor de la UO.
 */
enum TipoAlerta: string
{
    case Aviso = 'aviso';
    case Alerta = 'alerta';
}
