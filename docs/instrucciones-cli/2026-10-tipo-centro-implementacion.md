# Instrucciones de implementación — Tipo de Centro

> Incluye la addenda D1 (11 nov 2026): `docs/instrucciones-cli/2026-11-tipo-centro-addenda-d1.md` (D1, D2, D4 y D5). La addenda prevalece donde este texto la contradiga.

Módulos: Centro, Organizacion, Prestaciones, Agenda, Intervencion, Usuarios. Tipo de trabajo: evolución de módulos existentes. Diseño funcional: `docs/modulo-tipo-centro.md`. Tests asociados: `docs/instrucciones-cli/2026-10-tipo-centro-tests.md` (TF-TCE-01 a TF-TCE-59). Orden respecto a Asignación: ver paso 0.2. Esta fase puede ir antes o después de `2026-09-asignacion-implementacion.md`; el paso 0 determina la rama.

## Antes de empezar

Lee, en este orden:

- `CLAUDE.md`, `SESSION.md`, `docs/principios-vida360.md`, `docs/decisiones-tecnicas.md`.
- `docs/modulo-tipo-centro.md` completo. Es el qué y el por qué; este documento es el cómo.
- `docs/modulo-centros.md` (§2, §4.1, §7), `docs/modulo-asignacion.md` (§1, §3, §8), `docs/modulo-agenda.md` (§2.1 y generación de cuadrantes), `docs/modulo-prestaciones.md` (`prestacion_tipo_centro`, catálogos, versionado), `docs/modulo-intervencion.md` (apertura de planes), `docs/modulo-usuarios-permisos.md`.
- El código actual de: Centro, `CentroResource`, `AmbitoTerritorial`, `HorarioCentro`, `HorarioCentroResource`, la generación de cuadrantes y slots de Agenda, Prestacion, `PrestacionResource`, `CatalogoSistema`, el trait Versionable, `UnidadOrganizativa`, `DirectorCentro`, el modelo de plan de intervención y `TipoFicha`.
- `docs/design-system/SKILL.md` y `docs/instrucciones-cli/2026-09-bootstrap-unico.md` antes de cualquier Blade o Filament.
- El documento de tests.

Regla de trabajo: si encuentras un caso no cubierto aquí o un conflicto con el código existente, detente y pregunta. No improvises decisiones de diseño. Lo marcado como fuera de alcance no se implementa.

## 1. Qué cambia

| Hoy | Después de esta fase |
|---|---|
| El tipo de centro es una clave de `catalogos_sistema` (`centro.tipo`) | Entidad `TipoCentro` con UO funcional, configuración y denominaciones con vigencia |
| `prestacion_tipo_centro.tipo_centro` es un varchar | FK `tipo_centro_id` |
| El centro no tiene tipo (o lo tiene como varchar, si asignación está hecha) | `centros.tipo_centro_id` obligatorio e inmutable, y `centro_antecesor_id` |
| `inscripcion_libre`, segmentos de población y prestaciones se configuran por centro | Se configuran en el tipo; el centro las hereda |
| Cada `HorarioCentro` define su horario | Puede heredar el horario del tipo |
| No hay cierres de calendario de centro | Cierres de tipo y de centro, acumulables, usados por Agenda |
| Cualquier tipo de plan en cualquier centro | Solo los tipos de plan del tipo del centro |

Principios que no se negocian:

- Un único punto de lectura. Toda la configuración de un centro que puede venir del tipo se lee a través de `ConfiguracionEfectivaCentro`. Nada fuera de ese servicio lee los campos del tipo o los equivalentes del centro para decidir comportamiento.
- El tipo de un centro no se cambia. Ni por UI, ni por API, ni por código: el modelo lo impide.
- Nada se borra. Tipos y centros se dan de baja. Las denominaciones y horarios se cierran con fecha.
- Ningún cambio de configuración reasigna personas, casos ni citas.
- Permisos en el servidor. Las restricciones del director se validan en policies y servicios, no solo ocultando campos en Filament.

## 2. Fuera de alcance (no construir)

- Subtipos o jerarquía de tipos. Centros con más de un tipo. Entidad "sede".
- Operación de "cambiar el tipo de un centro".
- Traslado en bloque de personas o casos entre centros o tipos.
- Validaciones nuevas de cobertura o solapamiento de áreas. Las de `modulo-asignacion.md` §3.3, si ya existen, no se tocan.
- Canales de cita por tipo. Cargos habituales por tipo. Modelos de informe por tipo.
- Personas protegidas por defecto y derivaciones permitidas en el tipo.
- Cancelación automática de citas o slots por un cierre de calendario.

## Paso 0 — Verificación del entorno

- `git pull origin master` y `php artisan test --filter=Centro`, `--filter=Agenda`, `--filter=Prestacion`, `--filter=Intervencion`. Anota los fallos preexistentes.
- Estado de la fase de asignación. Comprueba si existen `centros.tipo_centro`, `centros.modo_asignacion_referencia`, `centros.ventana_reparto_meses`, `centros.meses_inactividad_caso` y la tabla `asignaciones_centro`.
  - Rama A (asignación implementada): esos campos se migran al tipo (paso 3).
  - Rama B (asignación no implementada): esos campos se crean directamente en el tipo y nunca en centros. Anota en `SESSION.md` y en `BACKLOG.md` que el paso 2 de `2026-09-asignacion-implementacion.md` debe ajustarse antes de ejecutarlo. No lo modifiques tú.
  - Si el estado es parcial (unos campos sí y otros no), detente y pregunta.
- Catálogo `centro.tipo`. Lista sus claves activas e inactivas y todos sus usos (`grep -rn "centro.tipo\|tipo_centro"`). Lista los centros existentes y el tipo que les corresponde según sus seeders o mundos demo. Si algún centro no tiene un tipo deducible sin ambigüedad, detente y pide el mapeo. Si conviven claves equivalentes (p. ej. css y `css_general`), detente y pregunta cuál se conserva.
- UO funcional. Comprueba cómo se modelan las UO (`UnidadOrganizativa`, jerarquía). La UO funcional de cada tipo es un dato que no se deduce: pídela para los datos reales. En seeders y mundos demo, usa la UO raíz del organigrama sembrado y anótalo en el CHANGELOG.
- Lectores a sustituir. Informa, con fichero y línea, de todos los lugares que leen:
  - `centros.inscripcion_libre`;
  - `centro_prestacion` y `centro_segmento_poblacion`;
  - `centros.horario`;
  - los campos de horario de `horarios_centro` (`dias_laborables`, `hora_apertura`, `hora_cierre`, `hora_inicio_atencion`, `hora_fin_atencion`);
  - en rama A, los campos de asignación del punto 2.
- Generación de Agenda. Identifica el punto o puntos donde se generan cuadrantes y se materializan slots a partir del horario del centro. Si hay más de uno y no comparten una función de "horario del día", detente y pregunta antes del paso 5.
- Permisos. Averigua cómo se identifica hoy al administrador del sistema (rol) y al director de un centro (`DirectorCentro` vigente, rol `supervision` del centro, o ambos). Si no hay una forma inequívoca de saber si un usuario es director de un centro concreto, detente y pregunta.
- Catálogos de Intervención. Comprueba cómo se modelan los tipos de plan (catálogo, enum o tabla) y si `TipoFicha` tiene hoy alguna relación con centros. Informa antes del paso 6.
- Calendario. Comprueba que no existe ya una entidad de festivos o cierres de centro. Si existe, detente y pregunta.
- **Integridad UO–centro** (10). Lista los centros cuya UO tenga otro centro, los centros sin UO (externos, si los hay) y las UO de centro con UO hijas. **Si hay alguno de los dos primeros casos entre centros municipales, o alguna UO de centro con hijas, detente e informa.**
- **Consumidores del ámbito** (11). Informa, con fichero y línea, de todos los usos de `User::uoSubtreeIds()`, de los `descendientes`/`descendants` de `UnidadOrganizativa` usados para permisos o filtrado, de `AmbitoUoScope`, de `CiudadanoPolicy` y de los recursos de Filament que filtran por «UO y descendientes».
- **Consumidores del centro del usuario** (12). Informa de todos los usos de `CentroDeUsuario`, de `uosActivas()->first()` y de `Centro::where('unidad_organizativa_id', …)->first()` (Mi equipo, cuadrante, asignación, perfil horario por defecto, citas, bandejas…).
- **Operaciones de atención individual** (13). Lista los puntos de entrada (servicios y acciones Livewire) de: abrir Historia Social, dar/reprogramar/cancelar cita, atender cita, registrar atención, crear apunte, inscribir en actividad. Informa de cuáles comprueban hoy la adscripción a un centro y cuáles no.

## Paso 1 — `TipoCentro` y denominaciones

En el módulo Centro.

### `tipos_centro`

| Campo | Tipo | Notas |
|---|---|---|
| `id` | bigint PK |  |
| `codigo` | `varchar(100)` | Unique. Inmutable una vez creado |
| `nombre` | `varchar(200)` | Caché de la denominación vigente (ver abajo) |
| `siglas` | `varchar(20)` | Caché de la denominación vigente |
| `unidad_organizativa_id` | FK | UO funcional. Obligatoria |
| `inscripcion_libre` | boolean | Por defecto false |
| `modo_asignacion_referencia` | enum `ModoAsignacionReferenciaCentro` | sorteo / `libre_eleccion` / `quien_abre`. Reutiliza el enum de asignación si existe |
| `ventana_reparto_meses` | int | Por defecto 12 |
| `meses_inactividad_caso` | int | Por defecto 6 |
| `permite_restriccion_en_centro` | boolean | Por defecto false |
| `protocolo` | text nullable |  |
| `activo` | boolean |  |
| `fecha_alta` | date |  |
| `fecha_baja` | date nullable |  |
| `timestamps` |  |  |

Sin soft deletes: la baja es lógica (activo, `fecha_baja`).

`TipoCentro` usa el trait Versionable. El modelo lanza `LogicException` si se intenta cambiar codigo en un registro existente, o dar de baja un tipo con centros activos (RN-11).

### `denominaciones`

Tabla polimórfica para tipos y centros.

| Campo | Tipo | Notas |
|---|---|---|
| `id` | bigint PK |  |
| `denominable_type` / `denominable_id` | morph | `TipoCentro` o Centro |
| `nombre` | `varchar(200)` |  |
| `nombre_corto` | `varchar(50)` | Siglas del tipo o nombre corto del centro |
| `vigente_desde` | date |  |
| `vigente_hasta` | date nullable |  |
| `creado_por_id` | FK |  |
| `timestamps` |  |  |

- Validación en el modelo: las vigencias de un mismo denominable no se solapan. Sin update de nombre ni `nombre_corto` en una denominación ya vigente o pasada: se cierra y se crea otra.
- Trait `TieneDenominaciones` para `TipoCentro` y Centro: `denominacionEn(Carbon $fecha)`, `denominacionVigente()`, `programarDenominacion(string $nombre, string $corto, Carbon $desde, User $actor)`. Este último cierra la vigente el día anterior a `$desde` y crea la nueva.
- Caché: `tipos_centro.nombre`/siglas y `centros.nombre`/`nombre_corto` guardan la denominación vigente hoy, porque muchas consultas y pantallas los leen. Se actualizan al crear una denominación con `vigente_desde` <= hoy y con el comando diario denominaciones:aplicar, que aplica las denominaciones programadas que entran en vigor. Regístralo en el scheduler.
- Migración de datos: una denominación inicial por cada tipo y centro existente, con `vigente_desde` = `fecha_alta` (o la fecha de creación del registro si no hay `fecha_alta`).

### Pivotes del tipo

- `tipo_centro_segmento_poblacion`: misma referencia de segmento que usa hoy `centro_segmento_poblacion`.
- `tipo_centro_tipo_plan`: referencia según lo averiguado en el paso 0.8.
- `tipo_centro_tipo_ficha`: `tipo_ficha_id`. Sin filas = todas las fichas disponibles.

Unique en cada pivote.

## Paso 2 — Tipo en centros y prestaciones

### `centros`

- `tipo_centro_id` FK. Se crea nullable, se rellena con el mapeo del paso 0.3 y se pasa a NOT NULL en una segunda migración. El modelo lanza `LogicException` si cambia en un registro existente.
- `centro_antecesor_id` FK nullable a centros.
- No se permite crear un centro en un tipo con activo = false.
- Rama A: después de migrar, `centros.tipo_centro` (varchar) deja de leerse y se elimina (addenda §6.2, ver «Eliminación de lo que pasa al tipo» en el paso 3).
- **Integridad UO–centro (addenda §3.3):** en el modelo `Centro`, no se puede crear ni cambiar un centro municipal a una UO que ya tenga otro centro. En el modelo `UnidadOrganizativa`, no se puede dar `parent_id` = UO de un centro. Las dos reglas, también en base de datos si es posible (índice único parcial en `centros.unidad_organizativa_id`).

### `prestacion_tipo_centro`

- Nueva columna `tipo_centro_id` FK, rellenada por codigo desde `tipo_centro`. Unique (`prestacion_id`, `tipo_centro_id`). Después, NOT NULL.
- La columna varchar deja de leerse y se elimina (paso 3, «Eliminación de lo que pasa al tipo»).
- `PrestacionResource`: la sección Tipos de centro pasa a seleccionar de `tipos_centro` activos. El snapshot de versiones de Prestacion debe seguir incluyendo los tipos asociados (ahora por id y código).

### `asignaciones_centro` (solo rama A)

Añade `tipo_centro_id`, rellénalo desde el varchar y rehace el índice único parcial (`ciudadano_id`, `tipo_centro_id`) WHERE `fecha_fin` IS NULL AND `deleted_at` IS NULL. El varchar deja de leerse y se elimina (paso 3, «Eliminación de lo que pasa al tipo»).

### Catálogo `centro.tipo`

Seeder que crea un `TipoCentro` por cada clave activa del grupo (codigo = clave, nombre = etiqueta) y el tipo ciam si no existe. Después, las entradas del grupo pasan a activo = false. No las borres.

Actualiza los seeders de centros y los mundos demo (incluido "Prueba CIAM") para que creen tipos y asignen `tipo_centro_id`.

## Paso 3 — Configuración que pasa del centro al tipo

| Del centro | Al tipo |
|---|---|
| `inscripcion_libre` | `tipos_centro.inscripcion_libre` |
| `centro_segmento_poblacion` | `tipo_centro_segmento_poblacion` |
| Rama A: `modo_asignacion_referencia`, `ventana_reparto_meses`, `meses_inactividad_caso` | Mismos campos en `tipos_centro` |

Migración de datos (**D2, addenda §6: el tipo manda**): la migración asigna al tipo los valores de esta tabla y escribe en el CHANGELOG, por tipo, **los valores de centro descartados** (centro, propiedad, valor anterior). No aborta por diferencias. Sigue deteniéndose si un centro no tiene tipo deducible (paso 0.3).

| Propiedad | `css_general` | `ciam` | Otros tipos del catálogo |
|---|---|---|---|
| Adscripción | Por domicilio | Libre elección | Por domicilio |
| Modo de referencia | Quien abre | Quien abre | Quien abre |
| Ventana de reparto | 12 meses | 12 meses | 12 meses |
| Meses para caso dormido | 6 | 6 | 6 |
| Segmentos de población | Unión de los de sus centros | Unión de los de sus centros | Unión de los de sus centros |
| Permite restricción en centro | No | No | No |

«Quien abre» es el comportamiento actual de staging: así la migración no cambia cómo funciona nada. El sorteo se activa después desde la ficha del tipo y solo afecta a las historias nuevas (RN-07). Prestaciones: el catálogo del tipo es el de `prestacion_tipo_centro`; lo que haya en `centro_prestacion` se descarta.

Después de migrar:

- Sustituye cada lectura del paso 0.5 por `ConfiguracionEfectivaCentro` (paso 4).
- **Eliminación de lo que pasa al tipo** (sustituye a «pregunta antes de eliminar» en los pasos 2, 3, 5 y 6): en una migración posterior a la de datos, y después de comprobar con `grep` que nada los lee fuera de las migraciones, se eliminan:
  - `centros.inscripcion_libre`, `centros.tipo_centro` (varchar), `centros.modo_asignacion_referencia`, `centros.ventana_reparto_meses`, `centros.meses_inactividad_caso` y `centros.horario` (JSON; pasa a ser un accesor calculado);
  - las tablas `centro_segmento_poblacion` y `centro_prestacion`;
  - las columnas varchar `prestacion_tipo_centro.tipo_centro` y `asignaciones_centro.tipo_centro`.

  El `down()` las recrea vacías. Las entradas del catálogo `centro.tipo` se dejan con `activo = false`.
- **Formularios:** `CentroResource` no muestra campos editables de esas propiedades; las enseña en lectura con el valor del tipo y un enlace a la ficha del tipo. Los servicios rechazan cualquier petición que intente fijarlas en un centro.

## Paso 4 — `ConfiguracionEfectivaCentro`

Servicio en `Modules/Centro/app/Services`/, único punto de lectura de la configuración heredable. Cachea por petición.

| Método | Devuelve | Política |
|---|---|---|
| `tipo(Centro)` | `TipoCentro` |  |
| `uoFuncional(Centro)` | `UnidadOrganizativa` | Solo tipo |
| `segmentos(Centro)` | Collection | Solo tipo |
| `inscripcionLibre(Centro)` | bool | Solo tipo |
| `modoAsignacionReferencia(Centro)` | enum | Solo tipo |
| `ventanaRepartoMeses(Centro)` / `mesesInactividadCaso(Centro)` | int | Solo tipo |
| `horario(Centro, Carbon $fecha)` | DTO `HorarioEfectivo` | Sobrescribible |
| `dia(Centro, Carbon $fecha)` | DTO `DiaCalendario` (abierto, franja de apertura, franja de atención, origen) | Acumulable + horario |
| `protocolo(Centro)` | ?string | Sobrescribible |
| `prestaciones(Centro)` | `Collection<Prestacion>` | Restringible |
| `tiposPlan(Centro)` / `tiposFicha(Centro)` | Collection | Restringible |
| `origen(Centro, string $propiedad)` | 'tipo' / 'centro' | Para la UI |

En rama A, los servicios de asignación (`ResolucionCentroService`, `AsignacionCentroService`, `PoolReferenciaService`, `SorteoReferenciaService`, `ActividadCasosService`, `AsignacionReferenciaService`) pasan a leer de aquí.

## Paso 5 — Horario y calendario

### `horarios_tipo_centro`

| Campo | Tipo |
|---|---|
| `id` | bigint PK |
| `tipo_centro_id` | FK |
| `nombre` | varchar |
| `dias_laborables` | json |
| `hora_apertura` / `hora_cierre` | time |
| `hora_inicio_atencion` / `hora_fin_atencion` | time |
| `vigente_desde` | date |
| `vigente_hasta` | date nullable |
| `timestamps` |  |

Validación: sin solape de vigencias por tipo. Versionado con Versionable.

Horario inicial de cada tipo (addenda §6.1): el más frecuente entre sus centros (en empate, el del centro con menor `id`); si un tipo no tiene centros con horario, L-V 9:00-14:00. Los `HorarioCentro` existentes quedan como horario propio (`hereda_horario_tipo = false`).

### `horarios_centro` (ampliación)

- `hereda_horario_tipo` boolean. Los registros existentes: false (ya tienen su horario). Los nuevos: true por defecto.
- Con `hereda_horario_tipo` = true, los campos de horario del registro quedan a null y no se leen; el resto de campos de agenda se mantienen.
- Volver al valor del tipo = poner `hereda_horario_tipo` = true y vaciar los campos de horario.

`ConfiguracionEfectivaCentro::horario()` toma el `HorarioCentro` vigente del centro en la fecha; si hereda, completa los campos con el `HorarioTipoCentro` vigente en esa fecha. Si el tipo no tiene horario vigente y el centro hereda, lanza una excepción de configuración.

### `cierres_calendario`

| Campo | Tipo | Notas |
|---|---|---|
| `id` | bigint PK |  |
| `titular_type` / `titular_id` | morph | `TipoCentro` o Centro |
| `fecha_desde` / `fecha_hasta` | date |  |
| `tipo` | enum `TipoCierre` | cierre / `horario_reducido` |
| `hora_inicio` / `hora_fin` | time nullable | Obligatorias en `horario_reducido` |
| `motivo` | `varchar(200)` |  |
| `creado_por_id` | FK |  |
| `timestamps` |  |  |

`dia()` aplica: cierre del centro > cierre del tipo > horario efectivo.

### Agenda

- Sustituye las lecturas de los campos de horario de `horarios_centro` (paso 0.5) por `ConfiguracionEfectivaCentro::horario()` y `dia()`.
- La generación de cuadrantes y slots no genera slots en días cerrados y recorta los de días con horario reducido.
- Al guardar un cierre (de tipo o de centro), la acción devuelve el número de citas confirmada afectadas por centro y lo muestra como aviso. No cancela citas ni slots.
- `centros.horario` (JSON) pasa a ser un accesor calculado del horario efectivo; la columna se elimina (paso 3, «Eliminación de lo que pasa al tipo»).

## Paso 6 — Catálogo y restricción

### `centro_exclusiones_catalogo`

| Campo | Tipo | Notas |
|---|---|---|
| `id` | bigint PK |  |
| `centro_id` | FK |  |
| `elemento_tipo` | enum `ElementoCatalogo` | prestacion / `tipo_plan` / `tipo_ficha` |
| `elemento_id` | bigint |  |
| `creado_por_id` | FK |  |
| `timestamps` |  |  |

Unique (`centro_id`, `elemento_tipo`, `elemento_id`). Validación: solo se puede excluir un elemento que esté en el catálogo vigente del tipo del centro, y solo si `permite_restriccion_en_centro` = true.

`ConfiguracionEfectivaCentro`:

- `prestaciones()` = prestaciones activas de `prestacion_tipo_centro` del tipo, menos las exclusiones del centro solo si el tipo permite restricción.
- `tiposPlan()` y `tiposFicha()`: igual. `tiposFicha()` sin filas en el pivote del tipo = todas las fichas activas.

### Consumidores

- Sustituye las lecturas de `centro_prestacion` por `prestaciones()`. La tabla deja de escribirse y se elimina (paso 3, «Eliminación de lo que pasa al tipo»).
- Apertura de plan de intervención: solo se ofrecen y se aceptan (validación en el servicio, no solo en la UI) los tipos de plan de `tiposPlan()` del centro en que se abre.
- Selección de ficha de valoración: solo los de `tiposFicha()` del centro.

## Paso 7 — Evolución y bajas

- `TipoCentroService::darDeBaja(TipoCentro, Carbon $fecha, User)`: rechaza si hay centros activos.
- `CentroService::darDeAlta(array $datos, TipoCentro, ?Centro $antecesor, User)`: rechaza tipos inactivos.
- `CentroService::darDeBaja(Centro, Carbon $fecha, User)`: baja lógica.
- Renombrados: `programarDenominacion()` del paso 1, para tipos y centros.
- No hay método para cambiar el tipo de un centro.

## Paso 8 — Permisos

- `TipoCentroPolicy`: todas las acciones de escritura, solo administrador del sistema (según paso 0.7). Lectura, cualquier usuario con acceso al backoffice.
- `CentroPolicy`:
  - create, darDeBaja, renombrar: solo administrador.
  - update: administrador; o director del centro, limitado a los campos de §9 del diseño funcional (contacto y descripción, horario, cierres propios, protocolo propio y exclusiones si el tipo lo permite).
  - Los servicios validan los campos que cambia el director y rechazan los demás aunque lleguen en la petición.
  - `tipo_centro_id`, `inscripcion_libre` y el resto de propiedades "solo tipo" no son editables desde el centro por nadie.

## Paso 8 bis — Ámbito por las dos jerarquías (addenda §3.4)

1. Servicio `App\Services\AmbitoUsuario` (cache por petición), único punto de cálculo:
   - `uoIds(User): array` — UO de las adscripciones vigentes y sus descendientes orgánicas, **más** las UO de los centros activos cuyo `tipo_centro.unidad_organizativa_id` esté entre esas UO orgánicas.
   - `incluyeCentro(User, Centro): bool`.
   - `centrosEnAmbito(User): Collection`.
2. `User::uoSubtreeIds()` delega en `AmbitoUsuario::uoIds()` (o se sustituye en sus consumidores; elige lo que menos toque y explícalo en el CHANGELOG). **Todos** los consumidores del punto 11 del paso 0 pasan por él. Nadie más calcula ámbitos.
3. Analítica: `UnidadOrganizativa::centrosDependientes(string $jerarquia = 'ambas')` (`organica` / `funcional` / `ambas`), con la misma lógica.
4. Rendimiento: una sola consulta por petición para el ámbito (CTE orgánica + join con `tipos_centro`). Mide el tiempo de una página con scope (p. ej. `MisCasos`) antes y después, y anótalo en el CHANGELOG.

## Paso 8 ter — Centro de trabajo (addenda §3.5)

1. `CentroDeUsuario` pasa a ofrecer:
   - `centros(User): Collection` — centros cuya UO es una adscripción vigente directa del usuario (no descendientes ni ámbito funcional).
   - `centroActivo(User): ?Centro` — el elegido en la sesión, si sigue estando en `centros()`; si no, el del perfil horario del usuario para hoy; si no, el único de `centros()`; si no, `null`.
   - `elegir(User, Centro): void` — guarda la elección en la sesión. Rechaza un centro que no esté en `centros()`.
   - `supervisa(User, Centro): bool` — rol `supervision` y `AmbitoUsuario::incluyeCentro()` (D1.8).
2. Sustituye cada uso del punto 12 del paso 0. En cada uno, si la pantalla u operación tiene un centro de contexto, usa ese; si no, `centroActivo()`. Las pantallas de supervisión que exigen centro (bandeja de asignaciones, reparto, cuadrante, Mi equipo) muestran un selector con `centrosEnAmbito()` cuando el supervisor tiene más de uno.
3. **RN-15 en el servidor:** cada punto de entrada del punto 13 del paso 0 comprueba que el usuario está adscrito al centro de la operación. Si no, rechaza con el mensaje «Para atender a ciudadanos tienes que estar adscrito a un centro.» (o «…a este centro.» si tiene otros). Ocultar botones no basta.
4. **Barra superior** (`operativo-shell`): «Tipo · Centro» del centro activo (siglas del tipo y nombre del centro, por la denominación vigente); selector si `centros()` tiene más de uno; nombre de la UO sin selector si no tiene centros. Sin colores por tipo (aplazado). Componentes y clases según el design system y el catálogo de UI.
5. Perfil horario por defecto (`agenda:horarios-por-defecto`): un perfil por adscripción a centro, no por usuario.

## Paso 8 quater — Personas protegidas (addenda §7.3)

1. `AmbitoUsuario`: nuevo método `gestionaProtegido(User, Ciudadano|HistoriaSocial): bool`. Es verdadero si la UO de la historia está entre las adscripciones **directas** vigentes del usuario.
2. `CiudadanoPolicy` (`view`, `update` y el resto de métodos con nivel 1), `HistoriaSocialPolicy`, `AmbitoUoScope` y `AccesoExpediente`: para personas protegidas, nivel 1 solo con `gestionaProtegido()`; en otro caso, el camino de consulta externa (acceso aprobado vigente). Para personas no protegidas, sin cambios (ámbito de RN-13).
3. Resolución de accesos en `AprobacionesPage`, pestaña «Accesos a expedientes»: aprobar (con fecha de caducidad; por defecto, la configurada) o denegar (con motivo). Solo el resolutor de RN-18; validado en el servidor. Se avisa al solicitante. Todo queda auditado.
4. El aviso de una solicitud nueva va al resolutor de RN-18, no «al supervisor de la UO».
5. Bandeja de asignaciones según RN-19.

Hechos que no hay que redescubrir (addenda §7.2): `CiudadanoPolicy::view()` da nivel 1 si la UO de la historia está en `uoSubtreeIds()`, sea o no persona protegida; `BuscarCiudadanoPage` crea la solicitud (`AccesoProtegido`, `pendiente`) y avisa «al supervisor de la UO»; `AprobacionesPage` tiene la pestaña «Accesos a expedientes» pero no hay acción para aprobar o denegar accesos, y ningún código pone un `AccesoProtegido` en `aprobado`.

## Paso 8 quinquies — Bajas de profesionales (addenda §8.2)

1. Busca todas las vías de borrado de `User` y `Profesional` (acciones de Filament, policies `delete`/`forceDelete`, servicios, comandos) y elimínalas o haz que la policy las deniegue. Sustitúyelas por «Desactivar» donde haya acción de borrado. **Si quitar `SoftDeletes` de los modelos afecta a datos existentes, pregunta antes.**
2. En el servicio que cierra adscripciones (localízalo en el paso 0) y en la desactivación de usuarios, aplica RN-21 y RN-22 en el servidor, con un mensaje que diga cuántos casos y citas quedan, y enlaces a «Repartir casos» y a la gestión de la ausencia.
3. Comprueba que la asignación de referencia queda ligada al centro (historia + centro) y que nada en el cambio de adscripción la toca (RN-23). Si no es así, detente e informa.
4. Cierra en el BACKLOG «Borrar un usuario con casos vigentes» con esta decisión.

## Paso 9 — Interfaz

Antes de cualquier Blade o Filament: design system y `2026-09-bootstrap-unico.md`. Termina con `npm run build` y `php artisan ui:auditar` en verde.

`TipoCentroResource` (Filament, grupo de organización, solo administrador). Secciones:

- Identificación: código (solo lectura tras crear), denominación vigente e historial de denominaciones, acción Programar nueva denominación.
- Dependencia y público: UO funcional, segmentos.
- Catálogo: prestaciones (solo lectura, con enlace a cada `PrestacionResource`), tipos de plan, fichas, indicador de restricción.
- Horario y calendario: horarios con vigencia, cierres comunes.
- Asignación: adscripción, modo de referencia, ventana y meses de inactividad.
- Protocolo.
- Centros del tipo: listado con estado, en lectura.
- `CentroResource`:
  - Tipo de centro: obligatorio al crear, solo lectura después. Centro antecesor opcional.
  - Propiedades "solo tipo" en lectura, con el valor del tipo.
  - Horario y protocolo con insignia Heredado del tipo o Personalizado y acción Volver al valor del tipo.
  - Cierres: los del tipo en lectura y los propios editables.
  - Exclusiones de catálogo solo si el tipo lo permite.
  - Lo que el director no puede editar, deshabilitado.
- `HorarioCentroResource`: interruptor Heredar horario del tipo; con él activo, los campos de horario se muestran en lectura con los valores del tipo.
- Ficha del centro (Livewire, si existe vista pública del centro): denominación vigente, tipo y UO funcional.

## Paso 10 — Tests, documentación y cierre

- Implementa los tests de `docs/instrucciones-cli/2026-10-tipo-centro-tests.md`.
- Ejecuta los filtros de Centro, Agenda, Prestacion, Intervencion y, en rama A, los tests de asignación (TF-ASG); después la suite completa. No des la fase por terminada con fallos nuevos.
- Actualiza los documentos de `modulo-tipo-centro.md` §11, incluidos `modulo-centros.md` §2.2, `modulo-usuarios-permisos.md` («Ámbito por UO») y `propuesta-requisitos-y-tareas-guiadas.md` §3 (addenda §2.7).
- `docs/documentacion-proyecto.md`: secciones de Centros, Agenda y Prestaciones.
- `CHANGELOG.md`, con la rama seguida (A o B), el mapeo de tipos aplicado y las decisiones de implementación que no estaban en estas instrucciones.
- `BACKLOG.md`: decisiones pendientes de `modulo-tipo-centro.md` §13; en rama B, el ajuste pendiente de `2026-09-asignacion-implementacion.md`. Cierra «Perfil horario por defecto: pendientes» en lo que toca a varias UO con varios centros, la decisión D1 del plan, «Bandeja de asignaciones con protegidos» (RN-19) y «Borrar un usuario con casos vigentes» (D5).
- Añade este fichero y el de tests a la tabla de `CLAUDE.md` (sección 6). Actualiza `SESSION.md`.

## Criterios de finalización

- Tests existentes de los módulos afectados sin fallos nuevos.
- TF-TCE-01 a TF-TCE-59 implementados y pasando.
- Suite completa sin fallos nuevos.
- Ninguna lectura de `centros.inscripcion_libre`, `centro_prestacion`, `centro_segmento_poblacion`, `centros.horario` ni de los campos de horario de `horarios_centro` fuera de `ConfiguracionEfectivaCentro` y de las migraciones (verificable con grep; indica el resultado en el CHANGELOG).
- Todos los centros con `tipo_centro_id`; ninguna fila de `prestacion_tipo_centro` sin `tipo_centro_id`.
- Ningún camino de código permite cambiar `centros.tipo_centro_id` ni `tipos_centro.codigo`.
- `ui:auditar` en verde.
- Documentación, CHANGELOG, BACKLOG, `CLAUDE.md` y `SESSION.md` actualizados.
