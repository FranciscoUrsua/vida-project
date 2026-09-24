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
 * Un mundo tiene uno de dos modos (clave raíz `modo`):
 * - reset (por defecto): se construye desde cero con `demo:reset`, que trunca las tablas.
 * - aditivo: se carga con `demo:load` sobre datos existentes, sin borrar nada. Requiere
 *   `etiqueta` y referencia centros, salas, cargos, tipos de plan y tipos de actividad
 *   existentes en la sección `existentes` en lugar de crearlos.
 *
 * La resolución de `existentes` contra la BD se hace en tiempo de ejecución
 * (DemoReferenciaResolver); aquí solo se valida la estructura.
 *
 * @see DemoWorldBuilder
 * @see DemoMundoAditivoBuilder
 */
class DemoWorldLoader
{
    /** Modo por defecto: el mundo se construye desde cero con demo:reset. */
    public const MODO_RESET = 'reset';

    /** Modo aditivo: el mundo se añade a los datos existentes con demo:load. */
    public const MODO_ADITIVO = 'aditivo';

    /** Secciones admitidas en 'existentes', en orden de resolución. */
    public const SECCIONES_EXISTENTES = ['centros', 'tipos_plan', 'cargos', 'salas', 'tipos_actividad'];

    /** Escenarios de ciudadanas admitidos en mundos aditivos. */
    public const ESCENARIOS_ADITIVOS = [
        'ciam_pia_activa',
        'ciam_pia_cerrada',
        'ciam_participante_actividad',
        'ciam_informacion',
    ];

    /** Roles asignables a profesionales de mundos aditivos (adm_sistema excluido a propósito). */
    public const ROLES_ADITIVOS = [
        'supervision',
        'adm_usuarios',
        'intervencion',
        'tramitacion',
        'consulta_profesional',
        'consulta_basica',
    ];

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
     *   modo: string,
     *   etiqueta: string|null,
     *   existentes: array<string, list<array<string, mixed>>>,
     *   tipo_plan: string|null,
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

        $modo = $this->validarModo($raw, $worldName);

        if ($modo === self::MODO_ADITIVO) {
            $this->validateAditivo($raw, $worldName);
        } else {
            $this->validate($raw, $worldName);
        }

        return [
            'meta' => [
                'nombre' => $raw['meta']['nombre'],
                'descripcion' => $raw['meta']['descripcion'],
                'reset_cada' => $raw['meta']['reset_cada'] ?? 'por demanda',
            ],
            'modo' => $modo,
            'etiqueta' => $raw['etiqueta'] ?? null,
            'existentes' => $raw['existentes'] ?? [],
            'tipo_plan' => $raw['tipo_plan'] ?? null,
            'centros' => $raw['centros'] ?? [],
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
     * Valida la clave raíz 'modo' y, en modo aditivo, la 'etiqueta'.
     *
     * @param mixed $raw Datos parseados del YAML
     * @param string $worldName Nombre del mundo (para mensajes de error)
     *
     * @return string Modo del mundo (reset | aditivo)
     *
     * @throws \InvalidArgumentException Si el modo o la etiqueta no son válidos
     */
    private function validarModo(mixed $raw, string $worldName): string
    {
        if (! is_array($raw)) {
            throw new \InvalidArgumentException(
                "El fichero '{$worldName}.yaml' no es un YAML válido."
            );
        }

        $modo = $raw['modo'] ?? self::MODO_RESET;

        if (! in_array($modo, [self::MODO_RESET, self::MODO_ADITIVO], true)) {
            throw new \InvalidArgumentException(
                "[{$worldName}] modo '{$modo}' no permitido. Valores válidos: 'reset', 'aditivo'."
            );
        }

        if ($modo === self::MODO_ADITIVO) {
            $etiqueta = $raw['etiqueta'] ?? null;

            if (empty($etiqueta)) {
                throw new \InvalidArgumentException(
                    "[{$worldName}] La clave 'etiqueta' es obligatoria en un mundo con modo 'aditivo'."
                );
            }

            if (! is_string($etiqueta) || ! preg_match('/^[A-Z0-9_]+$/', $etiqueta)) {
                throw new \InvalidArgumentException(
                    "[{$worldName}] etiqueta '".(is_scalar($etiqueta) ? $etiqueta : '?')."' no válida. ".
                    'Formato: [A-Z0-9_]+ (p. ej. TEST_CIAM).'
                );
            }
        }

        return $modo;
    }

    /**
     * Valida la estructura de un mundo aditivo.
     *
     * Un mundo aditivo no crea centros: los referencia en 'existentes'. Valida meta,
     * existentes, profesionales (con varios roles y cargo referenciado), escenarios
     * aditivos y actividades. No consulta la base de datos.
     *
     * @param array<string, mixed> $raw Datos parseados del YAML
     * @param string $worldName Nombre del mundo (para mensajes de error)
     *
     * @throws \InvalidArgumentException Si alguna validación falla
     */
    private function validateAditivo(array $raw, string $worldName): void
    {
        if (empty($raw['meta']['nombre']) || empty($raw['meta']['descripcion'])) {
            throw new \InvalidArgumentException(
                "[{$worldName}] Los campos 'meta.nombre' y 'meta.descripcion' son obligatorios."
            );
        }

        if (! empty($raw['centros'])) {
            throw new \InvalidArgumentException(
                "[{$worldName}] Un mundo aditivo no puede declarar centros a crear en 'centros'. ".
                "Los centros se referencian en 'existentes.centros'."
            );
        }

        $ids = $this->validarExistentes($raw['existentes'] ?? null, $worldName);

        // --- profesionales ---
        if (empty($raw['profesionales']) || ! is_array($raw['profesionales'])) {
            throw new \InvalidArgumentException(
                "[{$worldName}] La sección 'profesionales' es obligatoria y debe ser una lista."
            );
        }

        $rolesPorLogin = [];

        foreach ($raw['profesionales'] as $idx => $prof) {
            $pos = $idx + 1;

            foreach (['login', 'nombre', 'apellido1', 'centro', 'cargo', 'roles'] as $campo) {
                if (empty($prof[$campo])) {
                    throw new \InvalidArgumentException(
                        "[{$worldName}] Profesional #{$pos}: campo '{$campo}' obligatorio."
                    );
                }
            }

            $login = $prof['login'];

            if (isset($rolesPorLogin[$login])) {
                throw new \InvalidArgumentException(
                    "[{$worldName}] Profesional #{$pos}: login '{$login}' duplicado."
                );
            }

            if (! in_array($prof['centro'], $ids['centros'], true)) {
                throw new \InvalidArgumentException(
                    "[{$worldName}] Profesional #{$pos} ({$login}): centro '{$prof['centro']}' ".
                    "no está declarado en 'existentes.centros'."
                );
            }

            if (! in_array($prof['cargo'], $ids['cargos'], true)) {
                throw new \InvalidArgumentException(
                    "[{$worldName}] Profesional #{$pos} ({$login}): cargo '{$prof['cargo']}' ".
                    "no está declarado en 'existentes.cargos'."
                );
            }

            if (! is_array($prof['roles'])) {
                throw new \InvalidArgumentException(
                    "[{$worldName}] Profesional #{$pos} ({$login}): 'roles' debe ser una lista."
                );
            }

            foreach ($prof['roles'] as $rol) {
                if (! in_array($rol, self::ROLES_ADITIVOS, true)) {
                    throw new \InvalidArgumentException(
                        "[{$worldName}] Profesional #{$pos} ({$login}): rol '{$rol}' no permitido. ".
                        'Válidos: '.implode(', ', self::ROLES_ADITIVOS).'.'
                    );
                }
            }

            if (isset($prof['password']) && (! is_string($prof['password']) || $prof['password'] === '')) {
                throw new \InvalidArgumentException(
                    "[{$worldName}] Profesional #{$pos} ({$login}): 'password' debe ser un texto no vacío."
                );
            }

            $rolesPorLogin[$login] = $prof['roles'];
        }

        // --- escenarios ---
        foreach ($raw['escenarios'] ?? [] as $idx => $escenario) {
            $pos = $idx + 1;
            $login = $escenario['profesional'] ?? null;

            if (empty($login) || ! isset($rolesPorLogin[$login])) {
                throw new \InvalidArgumentException(
                    "[{$worldName}] Escenario #{$pos}: profesional '".($login ?? '')."' ".
                    "no está declarado en la sección 'profesionales'."
                );
            }

            if (! in_array('intervencion', $rolesPorLogin[$login], true)) {
                throw new \InvalidArgumentException(
                    "[{$worldName}] Escenario #{$pos}: el profesional '{$login}' no tiene rol 'intervencion'. ".
                    "Solo los profesionales con rol 'intervencion' pueden tener ciudadanos asignados."
                );
            }

            if (empty($escenario['ciudadanos']) || ! is_array($escenario['ciudadanos'])) {
                throw new \InvalidArgumentException(
                    "[{$worldName}] Escenario #{$pos} ({$login}): la lista 'ciudadanos' es obligatoria."
                );
            }

            foreach ($escenario['ciudadanos'] as $cidx => $entrada) {
                $cpos = $cidx + 1;
                $nombre = $entrada['escenario'] ?? null;

                if (! in_array($nombre, self::ESCENARIOS_ADITIVOS, true)) {
                    throw new \InvalidArgumentException(
                        "[{$worldName}] Escenario #{$pos} ({$login}), ciudadano #{$cpos}: ".
                        "escenario '".($nombre ?? '')."' no válido en un mundo aditivo. ".
                        'Válidos: '.implode(', ', self::ESCENARIOS_ADITIVOS).'.'
                    );
                }

                $cantidad = $entrada['cantidad'] ?? 0;

                if (! is_int($cantidad) || $cantidad < 1) {
                    throw new \InvalidArgumentException(
                        "[{$worldName}] Escenario #{$pos} ({$login}), ciudadano #{$cpos}: ".
                        "'cantidad' debe ser un entero >= 1."
                    );
                }
            }
        }

        // --- tipo de plan de los escenarios con plan ---
        if (! empty($raw['escenarios']) && empty($raw['tipo_plan'])) {
            throw new \InvalidArgumentException(
                "[{$worldName}] La clave 'tipo_plan' (id de 'existentes.tipos_plan') es obligatoria si hay escenarios."
            );
        }

        if (! empty($raw['tipo_plan']) && ! in_array($raw['tipo_plan'], $ids['tipos_plan'], true)) {
            throw new \InvalidArgumentException(
                "[{$worldName}] tipo_plan '{$raw['tipo_plan']}' no está declarado en 'existentes.tipos_plan'."
            );
        }

        // --- actividades ---
        $claves = [];
        $estadosSesionValidos = ['programada', 'celebrada', 'cancelada'];

        foreach ($raw['actividades'] ?? [] as $idx => $actividad) {
            $pos = $idx + 1;

            foreach (['id', 'centro', 'tipo', 'nombre', 'modo_acceso'] as $campo) {
                if (empty($actividad[$campo])) {
                    throw new \InvalidArgumentException(
                        "[{$worldName}] Actividad #{$pos}: campo '{$campo}' obligatorio."
                    );
                }
            }

            if (in_array($actividad['id'], $claves, true)) {
                throw new \InvalidArgumentException(
                    "[{$worldName}] Actividad #{$pos}: id '{$actividad['id']}' duplicado."
                );
            }

            $claves[] = $actividad['id'];

            if (! in_array($actividad['centro'], $ids['centros'], true)) {
                throw new \InvalidArgumentException(
                    "[{$worldName}] Actividad #{$pos}: centro '{$actividad['centro']}' no está declarado en 'existentes.centros'."
                );
            }

            if (! in_array($actividad['tipo'], $ids['tipos_actividad'], true)) {
                throw new \InvalidArgumentException(
                    "[{$worldName}] Actividad #{$pos}: tipo '{$actividad['tipo']}' no está declarado en 'existentes.tipos_actividad'."
                );
            }

            if (! in_array($actividad['modo_acceso'], ['libre', 'prescripcion', 'mixta'], true)) {
                throw new \InvalidArgumentException(
                    "[{$worldName}] Actividad #{$pos}: modo_acceso '{$actividad['modo_acceso']}' no válido."
                );
            }

            $logins = array_merge($actividad['profesionales'] ?? [], ...array_map(
                fn (array $sesion) => $sesion['profesionales'] ?? [],
                $actividad['sesiones'] ?? []
            ));

            foreach ($logins as $login) {
                if (! isset($rolesPorLogin[$login])) {
                    throw new \InvalidArgumentException(
                        "[{$worldName}] Actividad #{$pos}: profesional '{$login}' no declarado en 'profesionales'."
                    );
                }
            }

            foreach ($actividad['sesiones'] ?? [] as $sidx => $sesion) {
                $spos = $sidx + 1;

                foreach (['fecha', 'hora_inicio'] as $campo) {
                    if (empty($sesion[$campo])) {
                        throw new \InvalidArgumentException(
                            "[{$worldName}] Actividad #{$pos}, sesión #{$spos}: campo '{$campo}' obligatorio."
                        );
                    }
                }

                if (! preg_match('/^[+-]?\d+d$/', $sesion['fecha'])) {
                    throw new \InvalidArgumentException(
                        "[{$worldName}] Actividad #{$pos}, sesión #{$spos}: fecha '{$sesion['fecha']}' inválida. ".
                        "Formato esperado: '-7d', '0d', '+3d'."
                    );
                }

                // En mundos aditivos el estado es opcional: se deduce de la fecha al cargar.
                if (isset($sesion['estado']) && ! in_array($sesion['estado'], $estadosSesionValidos, true)) {
                    throw new \InvalidArgumentException(
                        "[{$worldName}] Actividad #{$pos}, sesión #{$spos}: estado '{$sesion['estado']}' no válido."
                    );
                }

                if (isset($sesion['sala']) && ! in_array($sesion['sala'], $ids['salas'], true)) {
                    throw new \InvalidArgumentException(
                        "[{$worldName}] Actividad #{$pos}, sesión #{$spos}: sala '{$sesion['sala']}' ".
                        "no está declarada en 'existentes.salas'."
                    );
                }
            }
        }
    }

    /**
     * Valida la estructura de la sección 'existentes' y devuelve los ids declarados por sección.
     *
     * Cada entrada necesita 'id' y 'buscar_por' (mapa campo → valor no vacío).
     * 'crear_si_no_existe' es opcional: true o un mapa de atributos adicionales.
     * Las salas indican además el 'centro' (id de existentes.centros) en el que se buscan.
     *
     * @param mixed $existentes Sección 'existentes' del YAML
     * @param string $worldName Nombre del mundo (para mensajes de error)
     *
     * @return array<string, list<string>> Ids locales declarados, indexados por sección
     *
     * @throws \InvalidArgumentException Si la estructura no es válida
     */
    private function validarExistentes(mixed $existentes, string $worldName): array
    {
        if (empty($existentes['centros']) || ! is_array($existentes)) {
            throw new \InvalidArgumentException(
                "[{$worldName}] Un mundo aditivo debe referenciar al menos un centro en 'existentes.centros'."
            );
        }

        foreach (array_keys($existentes) as $seccion) {
            if (! in_array($seccion, self::SECCIONES_EXISTENTES, true)) {
                throw new \InvalidArgumentException(
                    "[{$worldName}] Sección 'existentes.{$seccion}' no soportada. ".
                    'Válidas: '.implode(', ', self::SECCIONES_EXISTENTES).'.'
                );
            }
        }

        $ids = array_fill_keys(self::SECCIONES_EXISTENTES, []);
        $todos = [];

        foreach (self::SECCIONES_EXISTENTES as $seccion) {
            foreach ($existentes[$seccion] ?? [] as $idx => $entrada) {
                $pos = $idx + 1;
                $ref = "existentes.{$seccion} #{$pos}";

                if (empty($entrada['id']) || ! is_string($entrada['id'])) {
                    throw new \InvalidArgumentException("[{$worldName}] {$ref}: campo 'id' obligatorio.");
                }

                if (in_array($entrada['id'], $todos, true)) {
                    throw new \InvalidArgumentException(
                        "[{$worldName}] {$ref}: id '{$entrada['id']}' duplicado en 'existentes'."
                    );
                }

                if (empty($entrada['buscar_por']) || ! is_array($entrada['buscar_por'])) {
                    throw new \InvalidArgumentException(
                        "[{$worldName}] {$ref} ({$entrada['id']}): 'buscar_por' obligatorio (mapa campo: valor)."
                    );
                }

                foreach ($entrada['buscar_por'] as $campo => $valor) {
                    if (! is_string($campo) || ! preg_match('/^[a-z_]+$/', $campo) || ! is_scalar($valor) || $valor === '') {
                        throw new \InvalidArgumentException(
                            "[{$worldName}] {$ref} ({$entrada['id']}): 'buscar_por' contiene un criterio no válido."
                        );
                    }
                }

                $crear = $entrada['crear_si_no_existe'] ?? false;

                if (! is_bool($crear) && ! is_array($crear)) {
                    throw new \InvalidArgumentException(
                        "[{$worldName}] {$ref} ({$entrada['id']}): 'crear_si_no_existe' debe ser true/false o un mapa de atributos."
                    );
                }

                // Centros y tipos de plan son configuración de otros equipos: solo se referencian.
                if (in_array($seccion, ['centros', 'tipos_plan'], true) && $crear !== false) {
                    throw new \InvalidArgumentException(
                        "[{$worldName}] {$ref} ({$entrada['id']}): un mundo aditivo no puede crear {$seccion}, solo referenciarlos."
                    );
                }

                if ($seccion === 'salas' && ! in_array($entrada['centro'] ?? null, $ids['centros'], true)) {
                    throw new \InvalidArgumentException(
                        "[{$worldName}] {$ref} ({$entrada['id']}): 'centro' debe ser un id de 'existentes.centros'."
                    );
                }

                $ids[$seccion][] = $entrada['id'];
                $todos[] = $entrada['id'];
            }
        }

        return $ids;
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
