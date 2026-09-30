# Instrucciones de implementación — Asignación de centro y profesional de referencia

**Módulos:** `Organizacion`, `Centro`, `Intervencion`, `Usuarios`, geocodificación (`app/Services/Geocodificacion`), `Ciudadano` (`app/Models`).
**Tipo de trabajo:** evolución de módulos existentes.
**Diseño funcional:** `docs/modulo-asignacion.md`.
**Tests asociados:** `docs/instrucciones-cli/2026-09-asignacion-tests.md` (TF-ASG-01 a TF-ASG-34).
**Orden respecto a Citas:** esta fase va **antes** que `2026-09-citas-implementacion.md`, que usa la referencia vigente.

---

## Antes de empezar

Lee, en este orden:

1. `CLAUDE.md`, `SESSION.md`, `docs/principios-vida360.md`, `docs/decisiones-tecnicas.md`.
2. `docs/modulo-asignacion.md` completo. Es el **qué** y el **por qué**; este documento es el **cómo**.
3. `docs/geocodificacion.md`, `docs/modulo-centros.md` (§2.3, §4.1, §4.2), `docs/modulo-intervencion.md` (§1.1), `docs/modulo-ciudadania.md` (§6), `docs/front/alta-ciudadano-funcional.md` (§4.4).
4. El código actual de: `AmbitoTerritorial`, `Distrito`, `TieneDireccion`, `ResultadoGeocodificacion`, `MockGeocodificador`, `DireccionObserver`, `NormalizarDireccionJob`, `AsignacionProfesional`, `AperturaHistoriaService`, `PerfilHorarioProfesional`, `ExcepcionProfesional`, `Cargo`.
5. `docs/design-system/SKILL.md` y `docs/instrucciones-cli/2026-09-bootstrap-unico.md` antes de cualquier Blade.
6. El documento de tests.

**Regla de trabajo:** si encuentras un caso no cubierto aquí o un conflicto con el código existente, **detente y pregunta**. No improvises decisiones de diseño. Lo marcado como *fuera de alcance* no se implementa.

---

## 1. Qué cambia

| Hoy | Después de esta fase |
|---|---|
| Solo existen distritos | Distritos, barrios y secciones censales como catálogo |
| La dirección guarda vía, número y coordenadas | Además: NDP, distrito, barrio y sección censal |
| `AmbitoTerritorial` se guarda pero no se consulta | Se usa para resolver el centro de una dirección |
| El ciudadano no tiene centro asignado | `asignaciones_centro`, con historial y modo |
| La referencia es siempre quien abre la historia | Según el modo del centro: sorteo, libre elección o quien abre |
| Reasignación en masa pendiente | Reparto por salida, propuesto y confirmado |

Principios que no se negocian:

- **Nada ambiguo se asigna solo.** Ante la duda, bandeja.
- **Ningún proceso mueve a una persona de centro ni cambia su referencia sin una persona que lo confirme**, salvo la asignación inicial inequívoca.
- **Historial aditivo:** cerrar la asignación vigente y crear otra. Nunca actualizar el profesional o el centro de una asignación existente.
- **Sorteo reproducible y auditable:** cada sorteo guarda candidatos, pesos y resultado.
- **Aleatoriedad inyectable:** el sorteo usa `Random\Randomizer` recibido por inyección, para poder fijar la semilla en los tests.

---

## 2. Fuera de alcance (no construir)

- Consulta espacial y PostGIS. Polígonos GIS en la asignación.
- Asignación geográfica de profesionales dentro del centro.
- Cambio de centro o profesional a petición de la persona. Cupos.
- Asignación automática de personas sin hogar.
- Adaptador real de la BDC (solo se amplía el contrato y el mock).
- Reasignar los casos existentes.

---

## Paso 0 — Verificación del entorno

1. `git pull origin master` y `php artisan test --filter=Centro`, `--filter=Intervencion`, `--filter=Organizacion`. Anota los fallos preexistentes.
2. **Tipo de centro:** la tabla `centros` no tiene tipo; existe la clave de catálogo `centro.tipo` (usada en `prestacion_tipo_centro`). Confirma que no hay otra forma de tipificar centros en el código. Si la hay, detente y pregunta; si no, se añade en el paso 2.
3. **Centro de la apertura de historia:** averigua cómo sabe hoy `AperturaHistoriaService` (o la UI) en qué centro trabaja el profesional que abre la historia y cómo se rellena `historias_sociales.unidad_organizativa_id`. Si no hay forma de conocer el centro, detente y pregunta.
4. **Datos oficiales:** los seeders del paso 1 necesitan dos ficheros CSV (barrios y secciones censales de Madrid) en `Modules/Organizacion/database/data/`. Si no están en el repositorio, **pídelos**. No los descargues ni los inventes.
5. Comprueba qué códigos tiene hoy `distritos.codigo` (formato de dos dígitos, "01" a "21").

---

## Paso 1 — Unidades territoriales

En el módulo `Organizacion`.

### `barrios`

| Campo | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `distrito_id` | FK | |
| `codigo` | varchar(3) | Código municipal completo: distrito + barrio (p. ej. `214`). Unique |
| `codigo_en_distrito` | varchar(2) | Tal como lo devuelve la BDC (p. ej. `4`) |
| `nombre` | varchar(100) | |
| `activo` | boolean | |
| timestamps | | |

Unique `(distrito_id, codigo_en_distrito)`.

### `secciones_censales`

| Campo | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `distrito_id` | FK | |
| `barrio_id` | FK nullable | Si el dato oficial lo permite |
| `codigo_ine` | char(10) | Unique. `28` + `079` + distrito + sección |
| `codigo_en_distrito` | varchar(3) | Tal como lo devuelve la BDC |
| `activa` | boolean | |
| `vigente_desde` / `vigente_hasta` | date nullable | Para revisiones del INE |
| timestamps | | |

Modelos `Barrio` y `SeccionCensal` con relaciones y PHPDoc completo. Método estático `SeccionCensal::codigoIne(string $distrito, string $seccion): string` que construye el código de 10 dígitos (con relleno de ceros).

Seeders idempotentes desde los CSV del paso 0.4. Filament de solo lectura (con activar/desactivar) en el grupo de organización.

---

## Paso 2 — Datos de centro, cargo y ámbito

### `centros`

- `tipo_centro` varchar(100) nullable: clave de `catalogos_sistema`, grupo `centro.tipo`. Añade a ese grupo, si no existen, `css` (centro de servicios sociales) y `ciam`. Rellena los centros de los seeders y del mundo demo "Prueba CIAM".
- `modo_asignacion_referencia` enum `ModoAsignacionReferenciaCentro`: `sorteo` / `libre_eleccion` / `quien_abre`. Por defecto `sorteo`. **Los centros existentes se migran a `quien_abre`** para no cambiar el comportamiento de los datos de prueba; los seeders nuevos usan `sorteo`.
- `ventana_reparto_meses` int, por defecto 12.
- `meses_inactividad_caso` int, por defecto 6.

`CentroResource`: los cuatro campos, en una sección "Asignación".

### `cargos`

`puede_ser_referencia` boolean, por defecto `false`; `true` para el cargo de trabajador/a social en el seeder. Editable en Filament.

### `horarios_centro`

Si la fase de Citas aún no ha añadido `dias_ausencia_prolongada`, añádelo aquí (int, por defecto 15). Si ya existe, reutilízalo.

### `AmbitoTerritorial`

- Los tipos `demarcacion_oficial`, `barrios` y `secciones_censales` apuntan con `referencia_tipo` / `referencia_id` a `Distrito`, `Barrio` y `SeccionCensal`. Conviértelo en una relación `morphTo` real (`referencia()`) y valida que el tipo y la clase coincidan.
- Nueva validación en `validarCoherencia()`: **no se puede guardar** un ámbito si otro centro activo **del mismo `tipo_centro`** y con `inscripcion_libre = false` ya tiene la misma unidad. Mensaje comprensible con el nombre del otro centro.
- Filament: selector de distritos, barrios y secciones filtrable, en lugar de IDs.

---

## Paso 3 — Dirección y geocodificación

### Campos nuevos en las tablas con `TieneDireccion` (`ciudadanos`, `centros`)

| Campo | Tipo |
|---|---|
| `codigo_ndp` | varchar(20) nullable |
| `distrito_codigo` | varchar(2) nullable |
| `barrio_codigo` | varchar(3) nullable (código municipal completo) |
| `seccion_censal_codigo` | char(10) nullable (código INE) |

Índices sobre `seccion_censal_codigo` y `codigo_ndp`. Comprueba si estos campos de `ciudadanos` están bajo el cifrado de datos personales: los códigos territoriales son necesarios para buscar, así que **no se cifran**; si la política actual obliga a cifrar toda la dirección, detente y pregunta.

### `ResultadoGeocodificacion`

Añade `codigoNdp`, `codigoDistrito`, `codigoBarrio`, `seccionCensal`, todos `?string` y en el formato normalizado anterior (el adaptador es quien compone el código INE y el código de barrio completo). Actualiza `fallo()` y el PHPDoc.

### `MockGeocodificador`

Devuelve códigos coherentes con los datos sembrados: una sección censal activa elegida de forma determinista a partir del texto de la dirección (mismo texto, misma sección), con su barrio y distrito. Si la tabla está vacía, devuelve los códigos a null.

### Persistencia

`DireccionObserver` / `NormalizarDireccionJob` guardan los códigos nuevos. Tras normalizar la dirección de un `Ciudadano`, disparan el evento `DireccionCiudadanoNormalizada`, que usa el paso 4.

### Documentación

En `geocodificacion.md` §6: la BDC devuelve coordenadas UTM ETRS89 huso 30N (EPSG:25830) que el adaptador convierte a WGS84, y los códigos que devuelve (NDP, distrito, barrio, sección) y cómo se normalizan.

---

## Paso 4 — Asignación de centro

### `asignaciones_centro`

| Campo | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `ciudadano_id` | FK | |
| `tipo_centro` | varchar(100) | Desnormalizado para la regla "uno vigente por tipo" |
| `centro_id` | FK | |
| `modo` | enum `ModoAsignacionCentro` | `geografico` / `eleccion` / `manual` |
| `seccion_censal_codigo` | char(10) nullable | Con la que se resolvió (modo geográfico) |
| `motivo` | text nullable, `encrypted` | Obligatorio en `manual` |
| `asignado_por_id` | FK nullable | Null si fue automática |
| `fecha_inicio` | date | |
| `fecha_fin` | date nullable | |
| timestamps, soft deletes | | |

Índice único parcial: `(ciudadano_id, tipo_centro) WHERE fecha_fin IS NULL AND deleted_at IS NULL`.

### `ResolucionCentroService`

`resolver(Ciudadano $c, string $tipoCentro): ResultadoResolucionCentro` — DTO con `centro` o `motivoPendiente` (`sin_codigos`, `sin_cobertura`, `ambiguo`). Algoritmo de `modulo-asignacion.md` §3.2. Solo lee; no asigna.

### `AsignacionCentroService`

- `asignarPorDireccion(Ciudadano, string $tipoCentro): ?AsignacionCentro` — si no tiene asignación vigente del tipo y la resolución es inequívoca, crea la asignación `geografico`. Si no, crea o actualiza su entrada en la bandeja.
- `asignarPorEleccion(Ciudadano, Centro, User)` — solo centros con `inscripcion_libre = true`.
- `asignarManual(Ciudadano, Centro, string $motivo, User)` — cierra la vigente del tipo y crea la nueva.
- `evaluarCambioDomicilio(Ciudadano)` — si tiene asignación geográfica vigente y la nueva resolución da otro centro, crea una propuesta de cambio en la bandeja del supervisor del centro actual. No cambia nada.

Listener de `DireccionCiudadanoNormalizada`: para cada tipo de centro con algún centro de adscripción por domicilio, llama a `asignarPorDireccion` o a `evaluarCambioDomicilio` según tenga o no asignación vigente.

### Cobertura

Comando `php artisan centros:comprobar-cobertura {tipo_centro}` y acción en Filament con la misma lógica: secciones censales activas no cubiertas por ningún centro del tipo. Devuelve la lista; no bloquea nada.

---

## Paso 5 — Asignación del profesional de referencia

### `asignaciones_profesional` (ampliación)

| Campo | Tipo | Notas |
|---|---|---|
| `centro_id` | FK nullable | Centro en el que se hizo la asignación. Null en las existentes |
| `origen` | enum `OrigenAsignacionReferencia` | `sorteo` / `eleccion` / `unidad_convivencia` / `reparto` / `manual` / `quien_abre`. Existentes: `quien_abre` |
| `cuenta_en_reparto` | boolean | `true` solo para `sorteo` y `eleccion` |
| `sorteo` | jsonb nullable | Candidatos con peso, esperado y recibido, y resultado |
| `motivo` | text nullable, `encrypted` | Obligatorio en `manual` |
| `asignado_por_id` | FK nullable | |
| `reparto_id` | FK nullable | Ver paso 6 |

Nota: `ModoAsignacionReferenciaCentro` (configuración del centro) y `OrigenAsignacionReferencia` (cómo se hizo cada asignación) son enums distintos a propósito: el segundo tiene más valores.

### `PoolReferenciaService`

`elegibles(Centro $centro, Carbon $fecha): Collection<{usuario, peso}>` — usuarios con `PerfilHorarioProfesional` activo en el centro en esa fecha, cargo con `puede_ser_referencia`, sin `ExcepcionProfesional` con `afecta_disponibilidad` que cubra la fecha y dure más de `dias_ausencia_prolongada`. Peso = `jornada_semanal_horas`.

### `SorteoReferenciaService`

`sortear(Centro $centro, Carbon $fecha): ResultadoSorteo` — algoritmo de `modulo-asignacion.md` §4.2:

- **Recibidas** de cada elegible: asignaciones del centro con `cuenta_en_reparto = true` en la ventana.
- **Esperadas**: para cada una de esas asignaciones, se reparte 1 entre los elegibles *en la fecha de esa asignación* en proporción a su peso (usa el `sorteo` guardado en cada asignación; para las de `eleccion`, calcula el pool de esa fecha y guárdalo también en `sorteo`). Suma por profesional.
- **Candidatos**: esperadas − recibidas > 0. Si ninguno, todos los elegibles.
- **Elección**: ponderada por peso, con el `Randomizer` inyectado.
- Si no hay elegibles, devuelve resultado vacío y no asigna.

### `AsignacionReferenciaService`

`asignarInicial(HistoriaSocial $h, Centro $centro, User $actor, ?User $elegido = null): ?AsignacionProfesional`:

1. Si algún miembro activo de la unidad de convivencia del ciudadano tiene referencia vigente en ese centro → misma referencia, `unidad_convivencia`, no cuenta.
2. Si el centro es `quien_abre` → `$actor`, comportamiento actual.
3. Si es `libre_eleccion` y hay `$elegido` elegible → `eleccion`, cuenta.
4. Si no → `sorteo`. Sin elegibles → sin referencia y a la bandeja.

`cambiarManual(HistoriaSocial, User $nuevo, string $motivo, User $supervisor)` — cierra la vigente y crea `manual`.

### `AperturaHistoriaService::abrir()`

Pasa a: resolver el centro de la apertura (según lo averiguado en el paso 0.3), asegurar la asignación de centro del ciudadano (paso 4) y llamar a `AsignacionReferenciaService::asignarInicial()`. Mantén la firma pública o, si hay que cambiarla, actualiza todos los llamadores.

### Alta de ciudadano

La casilla "abrir historia y quedar como profesional de referencia" solo en centros `quien_abre`. En centros `sorteo` el texto pasa a "abrir historia"; tras abrir, se informa del profesional asignado. En `libre_eleccion`, selector opcional de profesional entre los elegibles.

---

## Paso 6 — Reparto por salida

### `repartos_casos`

| Campo | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `centro_id` | FK | |
| `profesional_origen_id` | FK | |
| `iniciado_por_id` | FK | Supervisor |
| `estado` | enum | `propuesto` / `confirmado` / `descartado` |
| `motivo` | text, `encrypted` | |
| `confirmado_en` | timestamp nullable | |
| timestamps | | |

### `repartos_casos_lineas`

`reparto_id`, `historia_id`, `unidad_convivencia_id` nullable, `con_actividad` boolean, `profesional_destino_id`, `modificada_por_supervisor` boolean.

### `RepartoCasosService`

- `proponer(Centro, User $origen, string $motivo, User $supervisor): RepartoCasos` — agrupa por unidad de convivencia, separa con y sin actividad (paso 7), reparte cada grupo por separado en proporción al peso entre los elegibles distintos de `$origen`, en orden aleatorio (`Randomizer` inyectado). Una unidad de convivencia va entera al mismo destino.
- `modificarLinea(...)`, `descartar(...)`.
- `confirmar(RepartoCasos, User)` — en una transacción: cierra las asignaciones vigentes y crea las nuevas con `origen = reparto`, `reparto_id`, `cuenta_en_reparto = false`. Alerta a cada profesional destino con el número de casos recibidos.

---

## Paso 7 — Actividad de los casos

`ActividadCasosService::resumen(Centro): Collection<{profesional, asignados, con_actividad, dormidos}>` y `conActividad(HistoriaSocial, Centro): bool` — con actividad si tiene plan activo o un apunte en los últimos `meses_inactividad_caso` meses. Consultas agregadas, sin N+1.

---

## Paso 8 — Interfaz

Antes de cualquier Blade: design system y `2026-09-bootstrap-unico.md`. Termina con `npm run build` y `php artisan ui:auditar` en verde.

1. **Bandeja de asignaciones** (Livewire, rol `supervision` del centro): los cuatro bloques de `modulo-asignacion.md` §7, con asignación manual con motivo y confirmación o descarte de propuestas de cambio de centro.
2. **Reparto por salida:** desde la bandeja o desde el equipo del centro. Vista de propuesta agrupada por destino, con los grupos con y sin actividad, edición de destino por línea y confirmación.
3. **Actividad del equipo:** tabla para el supervisor con asignados, con actividad y dormidos por profesional.
4. **Ficha del ciudadano:** centro asignado por tipo y profesional de referencia, con su modo ("por sorteo", "elegido", "por domicilio"…). El historial, desplegable.
5. **Filament:** campos de centro y cargo, selector de ámbitos, catálogo territorial, acción de cobertura.

---

## Paso 9 — Tests, documentación y cierre

1. Implementa los tests de `docs/instrucciones-cli/2026-09-asignacion-tests.md`.
2. Ejecuta los filtros de `Organizacion`, `Centro`, `Intervencion` y los tests de geocodificación y alta de ciudadano; después **la suite completa** (esta fase toca código transversal). No des la fase por terminada con fallos nuevos.
3. Actualiza los documentos listados en `modulo-asignacion.md` §9.
4. `docs/documentacion-proyecto.md`: secciones de Centros, Ciudadanía e Intervención.
5. `CHANGELOG.md`, con las **decisiones de implementación que no estaban en estas instrucciones**.
6. `BACKLOG.md`: las decisiones pendientes de `modulo-asignacion.md` §11 y el adaptador real de la BDC.
7. Añade este fichero y el de tests a la tabla de `CLAUDE.md` (sección 6). Actualiza `SESSION.md`.

## Criterios de finalización

1. Tests existentes de los módulos afectados sin fallos nuevos.
2. TF-ASG-01 a TF-ASG-34 implementados y pasando.
3. Suite completa sin fallos nuevos.
4. Ninguna escritura en `asignaciones_centro` o `asignaciones_profesional` fuera de los servicios de este documento y de `AperturaHistoriaService` (verificable con `grep`; indica el resultado en el CHANGELOG). Los seeders y mundos demo quedan exentos.
5. Ningún `update` del `centro_id` o `profesional_id` de una asignación existente.
6. `ui:auditar` en verde.
7. Documentación, CHANGELOG, BACKLOG, CLAUDE.md y SESSION.md actualizados.
