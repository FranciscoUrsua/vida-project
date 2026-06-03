# Instrucciones CLI — Sistema de entornos de demostración (World Building)

> Leer `docs/demo-worlds.md` íntegramente antes de ejecutar estas instrucciones.
> Ese documento explica las decisiones de diseño, la estructura de los mundos y las
> invariantes que el código debe respetar. Estas instrucciones asumen que ese contexto
> está cargado.

---

## Alcance de esta sesión

Implementar el sistema completo de world-building para entornos de demo:

1. Infraestructura base: loader, builder, verificador de invariantes, comandos Artisan
2. Los cinco escenarios de trayectoria
3. Los cinco mundos YAML iniciales
4. La página de Filament para lanzar mundos desde el panel de administración
5. Registro en `CLAUDE.md` del nuevo fichero de instrucciones

---

## Paso 1 — Estructura de directorios

Crear los directorios necesarios si no existen:

```
database/seeders/demo/
database/seeders/demo/scenarios/
database/seeders/worlds/
```

No crear ningún fichero PHP todavía. Solo los directorios.

---

## Paso 2 — DemoWorldLoader

Crear `database/seeders/demo/DemoWorldLoader.php`.

Responsabilidades:
- Leer y parsear un fichero YAML de `database/seeders/worlds/{nombre}.yaml`.
- Devolver la configuración como array estructurado.
- Validar la coherencia interna del YAML antes de devolver nada. Si la validación falla,
  lanzar `\InvalidArgumentException` con un mensaje descriptivo que identifique exactamente
  qué falla y en qué línea del YAML.

Validaciones obligatorias:
- Todos los `centro` referenciados en `profesionales` existen en la sección `centros`.
- Todos los `profesional` referenciados en `escenarios` existen en la sección `profesionales`.
- Solo profesionales con `rol: intervencion` aparecen en `escenarios`. Si un profesional
  con `rol: consulta_basica` o `rol: supervisor` aparece en escenarios, lanzar excepción
  con el mensaje: `"El profesional '{login}' tiene rol '{rol}' y no puede tener ciudadanos
  asignados directamente. Solo los profesionales con rol 'intervencion' pueden aparecer
  en la sección 'escenarios'."`.
- El escenario `compleja` solo puede asignarse a profesionales cuyo centro sea de
  `tipo: especializada`. Si no, emitir un aviso (no excepción) mediante `$this->warn()`.
- Los campos `meta.nombre`, `meta.descripcion` son obligatorios.
- Cada centro debe tener `id`, `nombre`, `tipo` y `distrito`.
- Cada profesional debe tener `login`, `nombre`, `rol` y `centro`.
- `tipo` de centro solo admite los valores `asp` y `especializada`.
- `rol` de profesional solo admite `supervisor`, `intervencion` y `consulta_basica`.

Método principal: `load(string $worldName): array`. El array devuelto tiene esta forma:

```php
[
    'meta'          => ['nombre' => '...', 'descripcion' => '...', 'reset_cada' => '...'],
    'centros'       => [['id' => 'c1', 'nombre' => '...', 'tipo' => 'asp', 'distrito' => '...'], ...],
    'profesionales' => [['login' => '...', 'nombre' => '...', 'rol' => '...', 'centro' => 'c1'], ...],
    'escenarios'    => [['profesional' => '...', 'ciudadanos' => [['escenario' => '...', 'cantidad' => N], ...]], ...],
]
```

Método auxiliar: `listWorlds(): array` — devuelve los nombres (sin extensión) de todos los
ficheros `.yaml` en `database/seeders/worlds/`. Usado por la página de Filament para
descubrir los mundos disponibles sin configuración adicional.

Dependencia: instalar `symfony/yaml` si no está disponible:
```bash
composer require symfony/yaml
```

---

## Paso 3 — DemoWorldBuilder

Crear `database/seeders/demo/DemoWorldBuilder.php`.

Responsabilidades:
- Recibir la configuración parseada del loader.
- Crear los centros y profesionales definidos en el YAML.
- Devolver los objetos creados (centros y profesionales) indexados por su `id` o `login`
  del YAML, para que el `DemoScenarioBuilder` pueda referenciarlos sin hacer queries.

Comportamiento:
- Usar `Centro::create()` y `User::create()` (o los modelos equivalentes del proyecto).
- Los profesionales se crean con `password: 'Demo1234!'` por defecto. Si el YAML incluye
  un campo `password` para un profesional concreto, usar ese valor.
- Asignar el rol Spatie al profesional según el campo `rol` del YAML, usando el mismo
  sistema de roles que el resto del proyecto (ver `docs/modulo-usuarios-permisos.md`).
- No usar `factory()`. Usar `Model::create()` con datos explícitos para la estructura fija
  (centros, profesionales). Los datos generados aleatoriamente (ciudadanos, historias) sí
  pueden usar factories cuando existan.
- Si un centro o profesional con el mismo identificador de demo ya existe (por un reset
  parcial fallido), actualizar en lugar de crear. Usar `updateOrCreate()` con el `login`
  como clave única para profesionales.

Firma del método principal:

```php
public function build(array $worldConfig): array
// Devuelve ['centros' => [...], 'profesionales' => [...]]
// Indexados por id/login del YAML para referencia rápida
```

---

## Paso 4 — Escenarios de trayectoria

Crear un fichero por escenario en `database/seeders/demo/scenarios/`.

Cada escenario es una clase con un método `construir(Ciudadano $ciudadano, User $tsr): void`.
El ciudadano se crea fuera del escenario (en `DemoScenarioBuilder`) para que el centro
ya esté asignado antes de entrar al escenario. El escenario solo crea lo que viene después
del ciudadano.

**Regla de oro para fechas en todos los escenarios:**
Las fechas nunca se generan con `fake()->dateTimeBetween()` de forma independiente.
Cada fecha se calcula como la fecha anterior más un offset aleatorio dentro de un rango:

```php
$fechaBase = now()->subMonths(fake()->numberBetween(3, 18));
$fechaApertura        = $fechaBase->copy()->addDays(fake()->numberBetween(1, 5));
$fechaPrimeraEntrevista = $fechaApertura->copy()->addDays(fake()->numberBetween(2, 10));
$fechaInicioPlan      = $fechaPrimeraEntrevista->copy()->addDays(fake()->numberBetween(5, 15));
// etc.
```

### 4.1 — TrayectoriaActiva

Fichero: `database/seeders/demo/scenarios/TrayectoriaActiva.php`

Crea en orden:
1. `SiaContacto` con el auxiliar del mismo centro (buscarlo por `rol: consulta_basica` en ese centro).
2. `HistoriaSocial` vinculada al SIA, estado `abierta`, `fecha_apertura` posterior al SIA.
3. `Entrevista` de tipo `inicial`, estado `realizada`, `fecha_hora` posterior a la apertura.
4. `PlanDeIntervencion` de tipo `general_asp`, estado `activo`, `fecha_inicio` posterior a la entrevista.
   `fecha_firma` igual a `fecha_inicio` (el plan activo ya está firmado).
5. Entre 2 y 4 `SeguimientoPlan` con fechas pasadas distribuidas a intervalos de 28-45 días
   desde `fecha_inicio` del plan. Solo crear seguimientos cuya fecha sea anterior a `now()`.
6. Una `Cita` futura (entre 3 y 30 días desde hoy) de tipo `seguimiento`, estado `confirmada`,
   vinculada al ciudadano y al profesional.

### 4.2 — TrayectoriaCerrada

Fichero: `database/seeders/demo/scenarios/TrayectoriaCerrada.php`

Crea en orden:
1. `SiaContacto` (igual que activa).
2. `HistoriaSocial` estado `cerrada`, `fecha_apertura` entre 6 y 24 meses atrás,
   `fecha_cierre` entre 1 y 6 meses atrás.
3. `Entrevista` inicial realizada.
4. `PlanDeIntervencion` estado `cerrado`, `motivo_cierre: objetivos_cumplidos`,
   `fecha_cierre` igual a `fecha_cierre` de la historia.
5. Entre 3 y 6 `SeguimientoPlan` pasados.
6. No crear citas futuras.

### 4.3 — TrayectoriaNueva

Fichero: `database/seeders/demo/scenarios/TrayectoriaNueva.php`

Crea en orden:
1. `SiaContacto` reciente (entre 5 y 20 días atrás).
2. `HistoriaSocial` estado `abierta`, `fecha_apertura` entre 3 y 15 días atrás.
3. `Entrevista` inicial realizada, fecha entre la apertura y hoy.
4. No crear plan ni seguimientos.
5. Opcionalmente (50% de probabilidad) una `Cita` futura de tipo `inicial` estado `confirmada`
   para una segunda entrevista.

### 4.4 — TrayectoriaUrgente

Fichero: `database/seeders/demo/scenarios/TrayectoriaUrgente.php`

Diferencia clave: **no hay SIA previo**. El primer contacto es directamente con el TSR.

Crea en orden:
1. `HistoriaSocial` con `sia_contacto_id = null`, estado `abierta`.
2. `Entrevista` de tipo `urgencia`, estado `realizada`.
3. `PlanDeIntervencion` estado `activo` (los casos urgentes suelen requerir plan inmediato).
4. Entre 1 y 3 `SeguimientoPlan` pasados.
5. Una `Cita` futura de seguimiento.

### 4.5 — TrayectoriaCompleja

Fichero: `database/seeders/demo/scenarios/TrayectoriaCompleja.php`

Extiende la trayectoria activa añadiendo un plan especializado vinculado.

Crea en orden (todo lo de TrayectoriaActiva, más):
1. Reutilizar o heredar la lógica de `TrayectoriaActiva` para crear la base.
2. `PlanDeIntervencion` adicional de tipo `especializado`, con `plan_asp_id` apuntando
   al plan general creado en el paso anterior. Estado `activo`.
3. Entre 1 y 2 `SeguimientoPlan` adicionales para el plan especializado.

Si el centro del profesional no es de tipo `especializada`, lanzar
`\LogicException('TrayectoriaCompleja requiere un profesional de centro especializado.')`.

---

## Paso 5 — DemoScenarioBuilder

Crear `database/seeders/demo/DemoScenarioBuilder.php`.

Responsabilidades:
- Recibir la configuración de escenarios y los profesionales ya creados.
- Para cada entrada de escenarios: crear el número de ciudadanos indicado, asignarlos al
  profesional correspondiente con el mismo `centro_id`, e instanciar el escenario correcto.
- Mapear el nombre de escenario del YAML a la clase PHP correspondiente.

Mapeo de escenarios:

```php
private array $scenarioMap = [
    'activa'   => TrayectoriaActiva::class,
    'cerrada'  => TrayectoriaCerrada::class,
    'nueva'    => TrayectoriaNueva::class,
    'urgente'  => TrayectoriaUrgente::class,
    'compleja' => TrayectoriaCompleja::class,
];
```

El ciudadano se crea con `Ciudadano::create()` usando `fake()` para nombre, apellidos, DNI
(formato válido pero ficticio: `00000001A` a `99999999Z`), fecha de nacimiento y domicilio
en Madrid. El `centro_id` del ciudadano debe ser el mismo que el del profesional asignado.
Esta asignación ocurre aquí, antes de llamar al escenario, garantizando la invariante por
construcción.

```php
public function buildScenarios(array $scenariosConfig, array $profesionales): void
{
    foreach ($scenariosConfig as $entrada) {
        $tsr = $profesionales[$entrada['profesional']];

        foreach ($entrada['ciudadanos'] as $grupo) {
            $scenarioClass = $this->scenarioMap[$grupo['escenario']]
                ?? throw new \InvalidArgumentException("Escenario desconocido: {$grupo['escenario']}");

            for ($i = 0; $i < $grupo['cantidad']; $i++) {
                $ciudadano = Ciudadano::create([
                    'centro_id'   => $tsr->centro_id,  // invariante garantizada aquí
                    'nombre'      => fake('es_ES')->firstName(),
                    'apellidos'   => fake('es_ES')->lastName() . ' ' . fake('es_ES')->lastName(),
                    // ... resto de campos
                ]);

                (new $scenarioClass)->construir($ciudadano, $tsr);
            }
        }
    }
}
```

---

## Paso 6 — DemoInvariantChecker

Crear `database/seeders/demo/DemoInvariantChecker.php`.

Responsabilidades:
- Verificar, sobre los datos ya insertados, que todas las invariantes de dominio se cumplen.
- Devolver un array de violaciones encontradas (array vacío = todo correcto).
- No lanzar excepciones directamente; devolver las violaciones para que el builder decida.

Invariantes a verificar (queries directas a la base de datos):

```php
public function check(): array
{
    $violaciones = [];

    // 1. Ciudadano y TSR en el mismo centro
    $count = DB::table('ciudadanos as c')
        ->join('historias_sociales as h', 'h.ciudadano_id', '=', 'c.id')
        ->join('users as u', 'u.id', '=', 'h.tsr_id')
        ->whereColumn('c.centro_id', '!=', 'u.centro_id')
        ->count();
    if ($count > 0) {
        $violaciones[] = "{$count} ciudadano(s) con TSR de distinto centro.";
    }

    // 2. Planes sin historia social
    $count = DB::table('planes_intervencion')
        ->whereNotIn('historia_id', DB::table('historias_sociales')->pluck('id'))
        ->count();
    if ($count > 0) {
        $violaciones[] = "{$count} plan(es) sin historia social asociada.";
    }

    // 3. Planes especializados sin plan_asp_id
    $count = DB::table('planes_intervencion')
        ->where('tipo', 'especializado')
        ->whereNull('plan_asp_id')
        ->count();
    if ($count > 0) {
        $violaciones[] = "{$count} plan(es) especializado(s) sin plan ASP vinculado.";
    }

    // 4. Citas "realizadas" con fecha futura
    $count = DB::table('citas')
        ->where('estado', 'realizada')
        ->where('fecha_hora', '>', now())
        ->count();
    if ($count > 0) {
        $violaciones[] = "{$count} cita(s) marcada(s) como 'realizada' con fecha futura.";
    }

    // 5. Citas "confirmadas" con fecha pasada
    $count = DB::table('citas')
        ->whereIn('estado', ['confirmada', 'pendiente'])
        ->where('fecha_hora', '<', now())
        ->count();
    if ($count > 0) {
        $violaciones[] = "{$count} cita(s) 'confirmada(s)' o 'pendiente(s)' con fecha pasada.";
    }

    // 6. Historias cerradas con planes activos
    $count = DB::table('historias_sociales as h')
        ->join('planes_intervencion as p', 'p.historia_id', '=', 'h.id')
        ->where('h.estado', 'cerrada')
        ->where('p.estado', 'activo')
        ->count();
    if ($count > 0) {
        $violaciones[] = "{$count} historia(s) cerrada(s) con plan(es) activo(s).";
    }

    return $violaciones;
}
```

Adaptar los nombres de tabla (`historias_sociales`, `planes_intervencion`, `citas`) a los
reales del proyecto consultando las migraciones existentes si hay duda.

---

## Paso 7 — Comando demo:reset

Crear `app/Console/Commands/DemoResetCommand.php`.

```php
php artisan demo:reset --world=nombre_mundo
```

Flujo:
1. Verificar que `APP_ENV !== 'production'`. Si es producción, salir con error y mensaje claro.
2. Si `APP_ENV === 'staging'`, pedir confirmación interactiva (`$this->confirm()`).
3. Cargar y validar el YAML con `DemoWorldLoader`.
4. Mostrar resumen del mundo (nombre, número de centros, profesionales, ciudadanos estimados).
5. Iniciar transacción de base de datos.
6. Truncar las tablas de datos de demo en orden inverso a sus dependencias (ver lista abajo).
7. Instanciar `DemoWorldBuilder` y construir centros y profesionales.
8. Instanciar `DemoScenarioBuilder` y construir ciudadanos y trayectorias.
9. Ejecutar `DemoInvariantChecker::check()`. Si hay violaciones:
   - Hacer rollback de la transacción.
   - Mostrar cada violación como error.
   - Salir con código 1.
10. Commit de la transacción.
11. Mostrar resumen final con tiempo transcurrido.

Tablas a truncar (en este orden para respetar las foreign keys; ajustar si hay tablas
adicionales en el proyecto):

```php
private array $demoTables = [
    'apuntes',
    'decisiones_entrevista',
    'seguimientos_plan',
    'firmas_plan',
    'revisiones_plan',
    'planes_intervencion',
    'valoraciones',
    'fichas',
    'entrevistas',
    'citas',
    'historias_sociales',
    'sia_contactos',
    'ciudadanos',
    // users y centros se tratan con updateOrCreate, no se truncan
];
```

Usar `DB::statement('TRUNCATE TABLE ' . $table . ' CASCADE')` para PostgreSQL.

Si alguna tabla no existe todavía (módulo no implementado), capturar la excepción,
emitir un aviso y continuar. No detener el proceso por tablas ausentes.

---

## Paso 8 — Comando demo:validate

Crear `app/Console/Commands/DemoValidateCommand.php`.

```php
php artisan demo:validate --world=nombre_mundo
```

Solo lee y valida el YAML. No toca la base de datos. Útil para verificar un nuevo
fichero de mundo antes de intentar construirlo.

Muestra:
- Si el YAML es válido: lista de centros, profesionales y escenarios encontrados.
- Si hay errores: lista de problemas con indicación de qué falla.
- Salir con código 0 si válido, código 1 si hay errores.

---

## Paso 9 — Mundos YAML

Crear los cinco ficheros en `database/seeders/worlds/`.

### demo_comercial.yaml

5 centros (3 ASP + 2 especializados), 30 profesionales distribuidos proporcionalmente,
~145 ciudadanos en total. Mix de escenarios: activa (60%), cerrada (25%), nueva (10%),
urgente (3%), compleja (2%).

Centros:
- `c1`: CSS Vallecas (asp, Puente de Vallecas)
- `c2`: CSS Carabanchel (asp, Carabanchel)
- `c3`: CSS Usera (asp, Usera)
- `c4`: Centro de Mayores Hortaleza (especializada, Hortaleza)
- `c5`: Servicio de Familia e Infancia Moncloa (especializada, Moncloa)

Profesionales por centro: 1 supervisor + 4-5 TSR + 1-2 auxiliares.
Los escenarios `compleja` solo en centros `c4` y `c5`.

Credenciales predecibles:
- `supervisor.vallecas@demo.es` / `Demo1234!`
- `tsr1.vallecas@demo.es` / `Demo1234!`
- `tsr2.vallecas@demo.es` / `Demo1234!`
- (patrón: `{rol}.{centro}@demo.es`)

### demo_formacion.yaml

2 centros ASP, 8 profesionales, exactamente 5 ciudadanos por escenario (uno de cada tipo
por TSR). El objetivo es tener un ejemplo didáctico claro de cada trayectoria.

### pruebas_agenda.yaml

3 centros ASP, 20 profesionales (5 TSR + 1 auxiliar + 1 supervisor por centro, más
algunos adicionales), ~120 ciudadanos todos con escenario `activa` para generar máxima
densidad de citas futuras.

### pruebas_permisos.yaml

1 centro ASP. Exactamente 6 profesionales: uno por cada rol posible (supervisor,
intervencion, consulta_basica) más variantes (supervisor de otro centro, TSR inactivo).
10 ciudadanos mínimos para que haya datos contra los que verificar los permisos.
Incluir comentario en el YAML documentando qué accesos deben funcionar y cuáles fallar.

### ci_minimo.yaml

1 centro ASP, 4 profesionales (1 supervisor, 2 TSR, 1 auxiliar), exactamente 1 ciudadano
por cada escenario (5 ciudadanos en total). Debe construirse en menos de 5 segundos.

---

## Paso 10 — Página de Filament

Crear `app/Filament/Pages/DemoWorldsPage.php`.

Requisitos:
- Solo visible si `!app()->isProduction()`. Implementar `canAccess(): bool`.
- Registrar la página en el panel de Filament correspondiente (ver cómo están registradas
  las demás páginas del proyecto para seguir el mismo patrón).
- Mostrar los mundos disponibles leyendo `DemoWorldLoader::listWorlds()`.
- Para cada mundo: nombre, descripción, número de centros/profesionales/ciudadanos estimados
  (parsear el YAML para calcularlos), y un botón "Lanzar mundo".
- El botón lanza una `Action` de Filament que ejecuta el comando `demo:reset` y muestra
  el resultado con una notificación al terminar.
- Mostrar advertencia visible: "Esta acción borrará todos los datos de demo actuales."
- Agrupar la página en el panel bajo una sección "Sistema" o "Administración" según cómo
  esté organizado el panel actual.

Para ejecutar el comando desde la Action:

```php
Action::make('lanzar')
    ->requiresConfirmation()
    ->action(function (string $worldName) {
        Artisan::call('demo:reset', ['--world' => $worldName]);
        $output = Artisan::output();
        Notification::make()
            ->title('Mundo lanzado')
            ->body($output)
            ->success()
            ->send();
    })
```

---

## Paso 11 — Actualizar CLAUDE.md

Añadir esta entrada a la tabla de `docs/instrucciones-cli/` en la sección 6 de `CLAUDE.md`:

```markdown
| `demo-worlds-cli.md` | Sistema de world-building para entornos de demo: infraestructura, escenarios, mundos YAML y página Filament |
```

---

## Paso 12 — Tests

Crear `tests/Feature/Demo/DemoWorldLoaderTest.php`.

Tests mínimos obligatorios:

```
TF-DEMO-01 — El loader carga un YAML válido sin errores
TF-DEMO-02 — El loader rechaza un YAML con un centro inexistente en profesionales
TF-DEMO-03 — El loader rechaza un profesional con rol 'consulta_basica' en escenarios
TF-DEMO-04 — El loader rechaza un profesional con rol 'supervisor' en escenarios
TF-DEMO-05 — El comando demo:reset falla en APP_ENV=production
TF-DEMO-06 — El comando demo:validate devuelve código 0 con un YAML válido
TF-DEMO-07 — El comando demo:validate devuelve código 1 con un YAML inválido
TF-DEMO-08 — demo:reset con ci_minimo crea exactamente 1 ciudadano por escenario
TF-DEMO-09 — demo:reset garantiza que ciudadano.centro_id == tsr.centro_id en todos los registros
TF-DEMO-10 — demo:reset garantiza que ninguna cita 'realizada' tiene fecha futura
TF-DEMO-11 — demo:reset garantiza que ninguna historia cerrada tiene plan activo
TF-DEMO-12 — Ejecutar demo:reset dos veces seguidas produce el mismo estado final (idempotencia)
```

Los tests TF-DEMO-08 a TF-DEMO-12 usan el mundo `ci_minimo` para ser rápidos.
Los tests TF-DEMO-09 a TF-DEMO-11 son verificaciones de invariantes: cada uno debe
**fallar si se elimina la validación que lo protege** (comprobar también en negativo).

---

## Paso 13 — CHANGELOG y SESSION

Al finalizar, actualizar `CHANGELOG.md` con:
- Fecha
- Módulo: `Demo / World Building`
- Lista de ficheros creados
- Decisiones de implementación tomadas que no estaban en estas instrucciones

Actualizar `SESSION.md` con el estado actual y el siguiente paso recomendado.

Si durante la implementación algún modelo no existe todavía (por ejemplo `SiaContacto` o
`SeguimientoPlan`), no crear stubs falsos. En su lugar:
- Documentar el stub en `BACKLOG.md` con la nota "Pendiente de implementación del módulo X".
- Hacer que el escenario correspondiente emita un aviso y omita ese paso en lugar de fallar.
- Registrar qué partes del sistema de demo están incompletas en la entrada de `CHANGELOG.md`.

---

## Notas de implementación

- No inventar nombres de tabla ni de modelo. Consultar las migraciones existentes antes
  de hacer ninguna query.
- Si hay ambigüedad sobre el nombre de un campo (por ejemplo `tsr_id` vs `profesional_id`
  en `historias_sociales`), consultar la migración correspondiente o `docs/modulo-intervencion.md`.
- El sistema de roles usa Spatie Permission. No reinventar la gestión de roles; usar
  `$user->assignRole()` como en el resto del proyecto.
- Las factories de `Ciudadano` y otros modelos pueden no existir todavía. Si no existen,
  crear factories mínimas solo para los modelos que las necesite el sistema de demo,
  en `database/factories/`. No crear factories para entidades que ya tienen seeder de sistema.
- `fake('es_ES')` para datos en español (nombres, apellidos, direcciones de Madrid).
