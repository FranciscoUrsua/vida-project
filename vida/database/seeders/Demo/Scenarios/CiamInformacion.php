<?php

namespace Database\Seeders\Demo\Scenarios;

use App\Models\Ciudadano;
use App\Models\User;
use Database\Seeders\Demo\DemoContextoAditivo;
use Modules\Intervencion\Enums\ClasificacionSia;
use Modules\Intervencion\Enums\UrgenciaSia;
use Modules\Intervencion\Models\SiaContacto;

/**
 * Escenario CIAM: atención puntual de información y orientación.
 *
 * Usa la forma más ligera que ofrece el modelo para una atención informativa:
 * un registro de contacto (SiaContacto) clasificado como información general,
 * con la información prestada y sin abrir historia social (principio 2.1).
 */
class CiamInformacion extends EscenarioCiam
{
    /** Información prestada verosímil en un CIAM (texto no identificativo). */
    private const INFORMACION = [
        'Se informa de las actividades grupales del centro y del procedimiento de inscripción.',
        'Se orienta sobre recursos de empleo del distrito y la Agencia para el Empleo.',
        'Se informa sobre el servicio de asesoría jurídica del centro y cómo pedir cita.',
        'Se facilita información sobre el Ingreso Mínimo Vital y la Renta Mínima de Inserción.',
        'Se informa sobre recursos de conciliación y escuelas infantiles del distrito.',
        'Se orienta sobre cursos de competencias digitales gratuitos.',
    ];

    /**
     * Construye el escenario de atención informativa.
     *
     * @param string $clave Clave lógica de la ciudadana
     * @param Ciudadano $ciudadana Ciudadana del mundo
     * @param User $responsable Profesional que registra el contacto
     * @param DemoContextoAditivo $ctx Contexto del mundo aditivo
     */
    public function construir(string $clave, Ciudadano $ciudadana, User $responsable, DemoContextoAditivo $ctx): void
    {
        $ctx->registrador->obtenerOCrear("{$clave}.contacto_informacion", SiaContacto::class, fn () => SiaContacto::create([
            'ciudadano_id' => $ciudadana->id,
            'auxiliar_id' => $responsable->id,
            'fecha_hora' => today()->subDays(mt_rand(1, 120))->setTime(mt_rand(9, 17), mt_rand(0, 3) * 15),
            'canal' => $ctx->faker->randomElement(['presencial', 'presencial', 'telefonico']),
            'descripcion_demanda' => $ctx->faker->randomElement(self::DEMANDAS),
            'clasificacion' => ClasificacionSia::InformacionGeneral,
            'informacion_prestada' => $ctx->faker->randomElement(self::INFORMACION),
            'urgencia' => UrgenciaSia::Ordinario,
        ]));
    }
}
