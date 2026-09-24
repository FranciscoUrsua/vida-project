<?php

namespace Database\Seeders\Demo;

use App\Models\Ciudadano;
use App\Models\UnidadOrganizativa;
use App\Models\User;
use Faker\Factory as FakerFactory;
use Faker\Generator;
use Illuminate\Support\Carbon;
use Modules\Centro\Models\Centro;
use Modules\Centro\Models\InscripcionCentro;
use Modules\Centro\Models\Prescripcion;
use Modules\Centro\Models\SesionActividad;
use Modules\Intervencion\Models\TipoPlan;

/**
 * Contexto compartido por los escenarios de un mundo demo aditivo.
 *
 * Reúne lo que los escenarios necesitan sin consultar la BD: registrador
 * (idempotencia), centro y UO referenciados, tipo de plan, profesionales y
 * sesiones de actividad del mundo, y un generador Faker es_ES.
 *
 * Las decisiones estructurales (nº de seguimientos, participación en actividades,
 * qué actividades...) se toman con decidir(), que es una función pura de
 * (etiqueta, clave, decisión): una segunda carga toma exactamente las mismas y
 * encuentra ya registradas todas las entidades, sin crear ninguna nueva. No se usa
 * mt_rand para ellas porque en la segunda carga no se ejecutan los cierres de
 * creación y la secuencia de números aleatorios se desplazaría.
 */
class DemoContextoAditivo
{
    /** @var Generator Faker con locale es_ES, re-sembrado por ciudadana */
    public readonly Generator $faker;

    /**
     * @param DemoRegistrador $registrador Registro de lo creado por el mundo
     * @param Centro $centro Centro referenciado donde se atiende a las ciudadanas
     * @param UnidadOrganizativa $uo UO del centro (ámbito de las historias sociales)
     * @param TipoPlan|null $tipoPlan Tipo de plan de los escenarios con plan
     * @param array<string, User> $profesionales Usuarios profesionales por login
     * @param array<string, list<SesionActividad>> $sesionesPorActividad Sesiones por id de actividad del YAML
     */
    public function __construct(
        public readonly DemoRegistrador $registrador,
        public readonly Centro $centro,
        public readonly UnidadOrganizativa $uo,
        public readonly ?TipoPlan $tipoPlan,
        public readonly array $profesionales,
        public readonly array $sesionesPorActividad,
    ) {
        $this->faker = FakerFactory::create('es_ES');
    }

    /**
     * Fija la semilla del azar para una clave, de modo que cada carga repita las mismas decisiones.
     *
     * Afecta a mt_rand()/rand() y al Faker del contexto.
     *
     * @param string $clave Clave lógica de la ciudadana (p. ej. "usuaria_037")
     */
    public function sembrar(string $clave): void
    {
        $semilla = crc32($this->registrador->etiqueta().':'.$clave);
        mt_srand($semilla);
        $this->faker->seed($semilla);
    }

    /**
     * Decisión estructural determinista: un entero en [min, max] que depende solo de
     * la etiqueta del mundo, la clave de la ciudadana y el nombre de la decisión.
     *
     * @param string $clave Clave lógica de la ciudadana
     * @param string $decision Nombre de la decisión (p. ej. "seguimientos")
     * @param int $min Valor mínimo
     * @param int $max Valor máximo
     */
    public function decidir(string $clave, string $decision, int $min, int $max): int
    {
        return $min + (crc32("{$this->registrador->etiqueta()}:{$clave}:{$decision}") % ($max - $min + 1));
    }

    /**
     * Inscribe a la ciudadana en el centro y en 1 o 2 actividades del mundo.
     *
     * La participación se modela como el resto del sistema: inscripción en el centro
     * (InscripcionCentro) y una Prescripcion por sesión de la actividad. Las sesiones
     * pasadas quedan 'finalizada' y las de hoy o futuras 'activa'.
     *
     * @param string $clave Clave lógica de la ciudadana
     * @param Ciudadano $ciudadana Ciudadana a inscribir
     * @param User $profesional Profesional que prescribe (debe tener Profesional vinculado)
     * @param int $numActividades Número de actividades distintas (se limita a las disponibles)
     */
    public function inscribirEnActividades(string $clave, Ciudadano $ciudadana, User $profesional, int $numActividades): void
    {
        if ($this->sesionesPorActividad === [] || $numActividades < 1) {
            return;
        }

        $this->registrador->obtenerOCrear("{$clave}.inscripcion_centro", InscripcionCentro::class, fn () => InscripcionCentro::create([
            'centro_id' => $this->centro->id,
            'ciudadano_id' => $ciudadana->id,
            'fecha_alta' => today()->subDays(mt_rand(30, 90))->toDateString(),
            'activa' => true,
        ]));

        // Orden determinista por ciudadana: mismas actividades en cada carga.
        $idsActividad = array_keys($this->sesionesPorActividad);
        usort($idsActividad, fn (string $a, string $b) => $this->decidir($clave, "actividad.{$a}", 0, PHP_INT_MAX - 1)
            <=> $this->decidir($clave, "actividad.{$b}", 0, PHP_INT_MAX - 1));
        $elegidas = array_slice($idsActividad, 0, $numActividades);

        foreach ($elegidas as $idActividad) {
            foreach ($this->sesionesPorActividad[$idActividad] as $n => $sesion) {
                $fecha = Carbon::parse($sesion->fecha);
                $pasada = $fecha->lt(today());

                $this->registrador->obtenerOCrear(
                    "{$clave}.prescripcion.{$idActividad}.".($n + 1),
                    Prescripcion::class,
                    fn () => Prescripcion::create([
                        'profesional_id' => $profesional->profesional_id,
                        'ciudadano_id' => $ciudadana->id,
                        'tipo_destino' => 'sesion_actividad',
                        'destino_id' => $sesion->id,
                        'estado' => $pasada ? 'finalizada' : 'activa',
                        'fecha_prescripcion' => $fecha->copy()->subDays(mt_rand(3, 10))->min(today())->toDateString(),
                        'fecha_inicio' => $fecha->toDateString(),
                        'fecha_fin' => $pasada ? $fecha->toDateString() : null,
                    ])
                );
            }
        }
    }
}
