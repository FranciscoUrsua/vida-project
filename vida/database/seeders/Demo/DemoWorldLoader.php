<?php

namespace Database\Seeders\Demo;

use Symfony\Component\Yaml\Yaml;

/**
 * Cargador y validador de mundos YAML para entornos de demo.
 *
 * Responsable de leer los ficheros YAML de definición de mundo
 * desde `database/seeders/worlds/` y validar su estructura antes
 * de pasarlos al constructor.
 *
 * @see DemoWorldBuilder
 */
class DemoWorldLoader
{
    /** @var string Directorio base de los mundos YAML */
    private string $worldsDir;

    /** @var callable|null Función para avisos no fatales */
    private mixed $output;

    /**
     * @param string|null $worldsDir Ruta al directorio de mundos (null = default)
     * @param callable|null $output Callable para avisos no fatales (p.ej. $this->warn(...))
     */
    public function __construct(?string $worldsDir = null, ?callable $output = null)
    {
        $this->worldsDir = $worldsDir ?? database_path('seeders/worlds');
        $this->output = $output;
    }

    /**
     * Carga y valida un mundo YAML por nombre.
     *
     * @param string $worldName Nombre del mundo sin extensión (p.ej. 'ci_minimo')
     *
     * @return array{
     *   meta: array{nombre: string, descripcion: string, reset_cada: string},
     *   centros: list<array{id: string, nombre: string, tipo: string, distrito: string, salas?: list<array{id: string, nombre: string, capacidad?: int, accesible?: bool}>}>,
     *   profesionales: list<array{login: string, nombre: string, rol: string, centro: string}>,
     *   escenarios: list<array{profesional: string, ciudadanos: list<array{escenario: string, cantidad: int}>}>,
     *   actividades: list<array{centro: string, tipo: string, nombre: string, modo_acceso: string, profesionales?: list<string>, sesiones?: list<array{fecha: string, hora_inicio: string, estado: string, sala?: string, profesionales?: list<string>}>}>
     * }
     *
     * @throws \InvalidArgumentException Si el fichero no existe o la validación falla
     */
    public function load(string $worldName): array
    {
        $path = "{$this->worldsDir}/{$worldName}.yaml";

        if (! file_exists($path)) {
            throw new \InvalidArgumentException(
                "No existe el fichero de mundo '{$worldName}': {$path}"
            );
        }

        $raw = Yaml::parseFile($path);

        $this->validate($raw, $worldName);

        return [
            'meta' => [
                'nombre' => $raw['meta']['nombre'],
                'descripcion' => $raw['meta']['descripcion'],
                'reset_cada' => $raw['meta']['reset_cada'] ?? 'por demanda',
            ],
            'centros' => $raw['centros'],
            'profesionales' => $raw['profesionales'],
            'escenarios' => $raw['escenarios'] ?? [],
            'actividades' => $raw['actividades'] ?? [],
        ];
    }

    /**
     * Lista los mundos disponibles en el directorio de mundos.
     *
     * @return list<string> Nombres de mundos (sin extensión .yaml)
     */
    public function listWorlds(): array
    {
        if (! is_dir($this->worldsDir)) {
            return [];
        }

        $files = glob("{$this->worldsDir}/*.yaml");

        if ($files === false) {
            return [];
        }

        return array_map(
            fn (string $f) => basename($f, '.yaml'),
            $files
        );
    }

    /**
     * Valida la estructura del array YAML cargado.
     *
     * Valida meta, centros (con salas opcionales), profesionales,
     * escenarios (opcional) y actividades (opcional).
     *
     * @param mixed $raw Datos parseados del YAML
     * @param string $worldName Nombre del mundo (para mensajes de error)
     *
     * @throws \InvalidArgumentException Si alguna validación falla
     */
    private function validate(mixed $raw, string $worldName): void
    {
        if (! is_array($raw)) {
            throw new \InvalidArgumentException(
                "El fichero '{$worldName}.yaml' no es un YAML válido."
            );
        }

        // --- meta ---
        if (empty($raw['meta']['nombre'])) {
            throw new \InvalidArgumentException(
                "[{$worldName}] El campo 'meta.nombre' es obligatorio."
            );
        }

        if (empty($raw['meta']['descripcion'])) {
            throw new \InvalidArgumentException(
                "[{$worldName}] El campo 'meta.descripcion' es obligatorio."
            );
        }

        // --- centros ---
        if (empty($raw['centros']) || ! is_array($raw['centros'])) {
            throw new \InvalidArgumentException(
                "[{$worldName}] La sección 'centros' es obligatoria y debe ser una lista."
            );
        }

        $centroIds = [];
        $salaIds = [];

        foreach ($raw['centros'] as $idx => $centro) {
            $pos = $idx + 1;

            foreach (['id', 'nombre', 'tipo', 'distrito'] as $campo) {
                if (empty($centro[$campo])) {
                    throw new \InvalidArgumentException(
                        "[{$worldName}] Centro #{$pos}: campo '{$campo}' obligatorio."
                    );
                }
            }

            if (! in_array($centro['tipo'], ['asp', 'especializada'], true)) {
                throw new \InvalidArgumentException(
                    "[{$worldName}] Centro #{$pos} (id={$centro['id']}): ".
                    "tipo '{$centro['tipo']}' no permitido. Valores válidos: 'asp', 'especializada'."
                );
            }

            $centroIds[] = $centro['id'];

            // --- salas del centro (opcional) ---
            foreach ($centro['salas'] ?? [] as $sidx => $sala) {
                $spos = $sidx + 1;

                foreach (['id', 'nombre'] as $campo) {
                    if (empty($sala[$campo])) {
                        throw new \InvalidArgumentException(
                            "[{$worldName}] Centro #{$pos} (id={$centro['id']}), sala #{$spos}: campo '{$campo}' obligatorio."
                        );
                    }
                }

                if (isset($sala['capacidad']) && (! is_int($sala['capacidad']) || $sala['capacidad'] < 1)) {
                    throw new \InvalidArgumentException(
                        "[{$worldName}] Centro #{$pos} (id={$centro['id']}), sala #{$spos} (id={$sala['id']}): ".
                        "'capacidad' debe ser un entero >= 1."
                    );
                }

                $salaIds[] = $sala['id'];
            }
        }

        // --- profesionales ---
        if (empty($raw['profesionales']) || ! is_array($raw['profesionales'])) {
            throw new \InvalidArgumentException(
                "[{$worldName}] La sección 'profesionales' es obligatoria y debe ser una lista."
            );
        }

        $rolesValidos = ['supervision', 'intervencion', 'consulta_basica'];
        $profesionalesLogins = [];

        foreach ($raw['profesionales'] as $idx => $prof) {
            $pos = $idx + 1;

            foreach (['login', 'nombre', 'rol', 'centro'] as $campo) {
                if (empty($prof[$campo])) {
                    throw new \InvalidArgumentException(
                        "[{$worldName}] Profesional #{$pos}: campo '{$campo}' obligatorio."
                    );
                }
            }

            if (! in_array($prof['rol'], $rolesValidos, true)) {
                throw new \InvalidArgumentException(
                    "[{$worldName}] Profesional #{$pos} (login={$prof['login']}): ".
                    "rol '{$prof['rol']}' no permitido. Válidos: ".implode(', ', $rolesValidos).'.'
                );
            }

            if (! in_array($prof['centro'], $centroIds, true)) {
                throw new \InvalidArgumentException(
                    "[{$worldName}] Profesional #{$pos} (login={$prof['login']}): ".
                    "centro '{$prof['centro']}' no existe en la sección 'centros'."
                );
            }

            $profesionalesLogins[] = $prof['login'];
        }

        // --- escenarios (opcional) ---
        if (isset($raw['escenarios'])) {
            if (! is_array($raw['escenarios'])) {
                throw new \InvalidArgumentException(
                    "[{$worldName}] La sección 'escenarios' debe ser una lista."
                );
            }

            $loginARol = [];

            foreach ($raw['profesionales'] as $prof) {
                $loginARol[$prof['login']] = $prof['rol'];
            }

            $escenariosValidos = ['activa', 'cerrada', 'nueva', 'urgente', 'compleja'];

            foreach ($raw['escenarios'] as $idx => $escenario) {
                $pos = $idx + 1;

                if (empty($escenario['profesional'])) {
                    throw new \InvalidArgumentException(
                        "[{$worldName}] Escenario #{$pos}: campo 'profesional' obligatorio."
                    );
                }

                $login = $escenario['profesional'];

                if (! in_array($login, $profesionalesLogins, true)) {
                    throw new \InvalidArgumentException(
                        "[{$worldName}] Escenario #{$pos}: profesional '{$login}' ".
                        "no está declarado en la sección 'profesionales'."
                    );
                }

                $rolDelProfesional = $loginARol[$login] ?? null;

                if ($rolDelProfesional !== 'intervencion') {
                    throw new \InvalidArgumentException(
                        "[{$worldName}] Escenario #{$pos}: profesional '{$login}' ".
                        "tiene rol '{$rolDelProfesional}'. Solo profesionales con rol 'intervencion' ".
                        'pueden tener escenarios de ciudadanos.'
                    );
                }

                if (empty($escenario['ciudadanos']) || ! is_array($escenario['ciudadanos'])) {
                    $this->warn("[{$worldName}] Escenario #{$pos} ({$login}): no tiene ciudadanos definidos.");

                    continue;
                }

                foreach ($escenario['ciudadanos'] as $cidx => $ciudadanoEntry) {
                    $cpos = $cidx + 1;

                    if (empty($ciudadanoEntry['escenario'])) {
                        throw new \InvalidArgumentException(
                            "[{$worldName}] Escenario #{$pos} ({$login}), ciudadano #{$cpos}: ".
                            "campo 'escenario' obligatorio."
                        );
                    }

                    if (! in_array($ciudadanoEntry['escenario'], $escenariosValidos, true)) {
                        throw new \InvalidArgumentException(
                            "[{$worldName}] Escenario #{$pos} ({$login}), ciudadano #{$cpos}: ".
                            "escenario '{$ciudadanoEntry['escenario']}' no válido. ".
                            'Válidos: '.implode(', ', $escenariosValidos).'.'
                        );
                    }

                    $cantidad = $ciudadanoEntry['cantidad'] ?? 0;

                    if (! is_int($cantidad) || $cantidad < 1) {
                        throw new \InvalidArgumentException(
                            "[{$worldName}] Escenario #{$pos} ({$login}), ciudadano #{$cpos}: ".
                            "'cantidad' debe ser un entero >= 1."
                        );
                    }
                }
            }
        }

        // --- actividades (opcional) ---
        if (! isset($raw['actividades'])) {
            return;
        }

        if (! is_array($raw['actividades'])) {
            throw new \InvalidArgumentException(
                "[{$worldName}] La sección 'actividades' debe ser una lista."
            );
        }

        $modosAccesoValidos = ['libre', 'prescripcion', 'mixta'];
        $estadosSesionValidos = ['programada', 'celebrada', 'cancelada'];

        foreach ($raw['actividades'] as $idx => $actividad) {
            $pos = $idx + 1;

            foreach (['centro', 'tipo', 'nombre', 'modo_acceso'] as $campo) {
                if (empty($actividad[$campo])) {
                    throw new \InvalidArgumentException(
                        "[{$worldName}] Actividad #{$pos}: campo '{$campo}' obligatorio."
                    );
                }
            }

            if (! in_array($actividad['centro'], $centroIds, true)) {
                throw new \InvalidArgumentException(
                    "[{$worldName}] Actividad #{$pos}: centro '{$actividad['centro']}' no existe en 'centros'."
                );
            }

            if (! in_array($actividad['modo_acceso'], $modosAccesoValidos, true)) {
                throw new \InvalidArgumentException(
                    "[{$worldName}] Actividad #{$pos}: modo_acceso '{$actividad['modo_acceso']}' no válido. ".
                    'Válidos: '.implode(', ', $modosAccesoValidos).'.'
                );
            }

            foreach ($actividad['profesionales'] ?? [] as $login) {
                if (! in_array($login, $profesionalesLogins, true)) {
                    throw new \InvalidArgumentException(
                        "[{$worldName}] Actividad #{$pos}: profesional '{$login}' no declarado en 'profesionales'."
                    );
                }
            }

            foreach ($actividad['sesiones'] ?? [] as $sidx => $sesion) {
                $spos = $sidx + 1;

                foreach (['fecha', 'hora_inicio', 'estado'] as $campo) {
                    if (empty($sesion[$campo])) {
                        throw new \InvalidArgumentException(
                            "[{$worldName}] Actividad #{$pos}, sesión #{$spos}: campo '{$campo}' obligatorio."
                        );
                    }
                }

                if (! preg_match('/^[+-]?\d+d$/', $sesion['fecha'])) {
                    throw new \InvalidArgumentException(
                        "[{$worldName}] Actividad #{$pos}, sesión #{$spos}: ".
                        "fecha '{$sesion['fecha']}' inválida. Formato esperado: '-7d', '0d', '+3d'."
                    );
                }

                if (! in_array($sesion['estado'], $estadosSesionValidos, true)) {
                    throw new \InvalidArgumentException(
                        "[{$worldName}] Actividad #{$pos}, sesión #{$spos}: ".
                        "estado '{$sesion['estado']}' no válido. Válidos: ".implode(', ', $estadosSesionValidos).'.'
                    );
                }

                if (isset($sesion['sala']) && ! in_array($sesion['sala'], $salaIds, true)) {
                    throw new \InvalidArgumentException(
                        "[{$worldName}] Actividad #{$pos}, sesión #{$spos}: ".
                        "sala '{$sesion['sala']}' no declarada en ningún centro."
                    );
                }

                foreach ($sesion['profesionales'] ?? [] as $login) {
                    if (! in_array($login, $profesionalesLogins, true)) {
                        throw new \InvalidArgumentException(
                            "[{$worldName}] Actividad #{$pos}, sesión #{$spos}: ".
                            "profesional '{$login}' no declarado en 'profesionales'."
                        );
                    }
                }
            }
        }
    }

    /**
     * Emite un aviso no fatal si hay un callable de output configurado.
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
