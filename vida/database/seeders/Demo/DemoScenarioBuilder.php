<?php

namespace Database\Seeders\Demo;

use App\Models\Ciudadano;
use App\Models\UnidadOrganizativa;
use App\Models\User;
use Database\Seeders\Demo\Scenarios\TrayectoriaActiva;
use Database\Seeders\Demo\Scenarios\TrayectoriaCerrada;
use Database\Seeders\Demo\Scenarios\TrayectoriaCompleja;
use Database\Seeders\Demo\Scenarios\TrayectoriaNueva;
use Database\Seeders\Demo\Scenarios\TrayectoriaUrgente;

/**
 * Constructor de escenarios de ciudadanos para mundos demo.
 *
 * Recorre la configuración de escenarios del YAML y para cada entrada
 * genera los ciudadanos con datos ficticios (Faker) y construye la
 * trayectoria correspondiente mediante la clase de escenario apropiada.
 *
 * @see DemoWorldLoader
 * @see DemoWorldBuilder
 */
class DemoScenarioBuilder
{
    /** @var array<string, class-string> Mapeo nombre YAML → clase de escenario */
    private const ESCENARIOS = [
        'activa'   => TrayectoriaActiva::class,
        'cerrada'  => TrayectoriaCerrada::class,
        'nueva'    => TrayectoriaNueva::class,
        'urgente'  => TrayectoriaUrgente::class,
        'compleja' => TrayectoriaCompleja::class,
    ];

    /** @var callable|null */
    private mixed $output;

    /**
     * @param callable|null $output Callable para avisos no fatales
     */
    public function __construct(?callable $output = null)
    {
        $this->output = $output;
    }

    /**
     * Construye todos los escenarios de ciudadanos definidos en el YAML.
     *
     * Para cada entrada de escenario:
     * 1. Identifica el profesional y su UO
     * 2. Para cada tipo de trayectoria, crea N ciudadanos con Faker
     * 3. Construye la trayectoria correspondiente
     *
     * @param list<array{profesional: string, ciudadanos: list<array{escenario: string, cantidad: int}>}> $scenariosConfig
     * @param array<string, User>                $profesionales Indexado por login
     * @param array<string, UnidadOrganizativa>  $unidades      Indexado por id YAML
     *
     * @return void
     */
    public function buildScenarios(array $scenariosConfig, array $profesionales, array $unidades): void
    {
        // Mapa login → UO para acceso rápido
        $profesionalUo = $this->resolverUosPorProfesional($profesionales, $unidades);

        foreach ($scenariosConfig as $escenarioConfig) {
            $login = $escenarioConfig['profesional'];
            $tsr   = $profesionales[$login] ?? null;

            if ($tsr === null) {
                $this->warn("  [DemoScenarioBuilder] Profesional '{$login}' no encontrado — escenario omitido.");
                continue;
            }

            $uo = $profesionalUo[$login] ?? null;

            if ($uo === null) {
                $this->warn("  [DemoScenarioBuilder] No se encontró UO para '{$login}' — escenario omitido.");
                continue;
            }

            foreach ($escenarioConfig['ciudadanos'] as $ciudadanoEntry) {
                $nombreEscenario = $ciudadanoEntry['escenario'];
                $cantidad        = $ciudadanoEntry['cantidad'];

                $clase = self::ESCENARIOS[$nombreEscenario] ?? null;

                if ($clase === null) {
                    $this->warn("  [DemoScenarioBuilder] Escenario '{$nombreEscenario}' desconocido — omitido.");
                    continue;
                }

                $this->warn("  Construyendo {$cantidad}x '{$nombreEscenario}' para {$login}...");

                $constructor = new $clase($this->output);

                for ($i = 0; $i < $cantidad; $i++) {
                    $ciudadano = $this->crearCiudadano();
                    $constructor->construir($ciudadano, $tsr, $uo);
                }
            }
        }
    }

    /**
     * Crea un ciudadano con datos ficticios usando Faker (locale es_ES).
     *
     * Los campos de datos personales (nombre, apellidos, fecha_nacimiento, domicilio)
     * se almacenan cifrados automáticamente por el modelo (cast 'encrypted').
     * Se pasan en texto plano — el modelo aplica el cifrado.
     *
     * @return Ciudadano Ciudadano creado y persistido
     */
    private function crearCiudadano(): Ciudadano
    {
        return Ciudadano::withoutGlobalScopes()->create([
            'nombre'             => fake('es_ES')->firstName(),
            'apellido1'          => fake('es_ES')->lastName(),
            'apellido2'          => fake('es_ES')->lastName(),
            'fecha_nacimiento'   => fake()->dateTimeBetween('-80 years', '-18 years')->format('Y-m-d'),
            'sexo'               => fake()->randomElement(['M', 'F']),
            'direccion_texto'    => 'C/ ' . fake('es_ES')->streetName() . ', Madrid',
            'nivel_identificacion' => 'identificado',
            'activo'             => true,
        ]);
    }

    /**
     * Construye el mapa login → UO a partir de las adscripciones en BD.
     *
     * @param array<string, User>                $profesionales
     * @param array<string, UnidadOrganizativa>  $unidades
     *
     * @return array<string, UnidadOrganizativa> Indexado por login
     */
    private function resolverUosPorProfesional(array $profesionales, array $unidades): array
    {
        // Invertir el índice de unidades para poder buscar por ID
        $uosPorId = [];

        foreach ($unidades as $uo) {
            $uosPorId[$uo->id] = $uo;
        }

        $resultado = [];

        foreach ($profesionales as $login => $user) {
            $adscripcion = \App\Models\UsuarioUo::where('usuario_id', $user->id)
                ->whereIn('unidad_organizativa_id', array_keys($uosPorId))
                ->first();

            if ($adscripcion !== null) {
                $resultado[$login] = $uosPorId[$adscripcion->unidad_organizativa_id] ?? null;
            }
        }

        return array_filter($resultado);
    }

    /**
     * Emite un aviso si hay callable de output configurado.
     *
     * @param string $message Mensaje del aviso
     */
    private function warn(string $message): void
    {
        if ($this->output !== null) {
            ($this->output)($message);
        }
    }
}
