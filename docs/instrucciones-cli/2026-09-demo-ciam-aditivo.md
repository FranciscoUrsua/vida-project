# Instrucciones CLI — Mundo «Prueba CIAM» en modo aditivo

> Fichero: `docs/instrucciones-cli/2026-09-demo-ciam-aditivo.md`
> Módulos afectados: `database/seeders/Demo/`, `database/seeders/worlds/`, `app/Console/Commands/`,
> `app/Filament/Pages/DemoWorldsPage.php`, `Modules/Intervencion` (tipo de plan y reglas del plan especializado)
> Tests: `TF-DEMO-CIAM-01` a `TF-DEMO-CIAM-16`

---

## Contexto

Empezamos a trabajar con los nuevos **CIAM (Centros Integrales de Atención a la Mujer)**. Para probar
en staging necesitamos un mundo de demo, **«Prueba CIAM»**, que se construye evolucionando el fichero
existente `database/seeders/worlds/demo_ciam.yaml`.

Este mundo tiene una diferencia fundamental con los anteriores: **no se puede borrar nada**. En staging
hay datos reales de prueba que no son nuestros, incluido el propio centro CIAM Puente de Vallecas.
El sistema actual (`demo:reset`) hace `TRUNCATE ... CASCADE` y crea los centros desde cero, así que
hay que añadir un **modo aditivo**.

Lo que se implementa en esta sesión:

1. **Modo aditivo** del sistema de mundos: comando nuevo `demo:load`, sin truncado e idempotente.
2. **Referencias a entidades existentes** en el YAML (centro, salas, tipo de plan, cargos, tipos de
   actividad), en lugar de crearlas.
3. **Etiquetado** de todo lo que crea el mundo con la etiqueta `TEST_CIAM`, para poder localizarlo
   más adelante.
4. **Regla de dominio nueva**: un plan especializado puede existir **sin plan ASP (PISO) previo**
   cuando su tipo de plan lo admite. El CIAM es una puerta alternativa de entrada al sistema.
5. **Escenarios CIAM** nuevos para las usuarias.
6. Reescritura de `demo_ciam.yaml` como mundo aditivo «Prueba CIAM».
7. Tests `TF-DEMO-CIAM-01` a `TF-DEMO-CIAM-16`.

Antes de tocar nada, leer íntegramente:
- `docs/instrucciones-cli/demo-worlds-cli.md`
- `docs/modulo-intervencion.md` (sección 5, Plan de Intervención)
- `docs/modulo-usuarios-permisos.md` (roles y modelo de Profesional)
- `docs/principios-vida360.md`

---

## Fase 0 — Reconocimiento (obligatoria, sin modificar ficheros)

La documentación de referencia es de finales de junio de 2026. El código puede haber cambiado desde
entonces. Antes de escribir código, comprobar lo siguiente e informar del resultado.

**Sistema de mundos:**
1. Estado actual de `DemoWorldLoader`, `DemoWorldBuilder`, `DemoScenarioBuilder`, `DemoActividadBuilder`,
   `DemoInvariantChecker`, `DemoResetCommand`, `DemoValidateCommand` y `DemoWorldsPage`.
2. Contenido actual de `demo_ciam.yaml`.

**Plan de intervención:**
3. Cómo se modela hoy el tipo de plan: tabla `tipos_plan`, modelo `TipoPlan`, enum `tipo`
   (`general_asp` / `especializado`) y relación con `servicio_especializado_id`.
4. Que existe un `TipoPlan` con slug `pia`, y cómo está asociado hoy a centros, UOs o servicios.
5. Dónde se exige hoy que un plan especializado tenga `plan_asp_id`: modelo, servicio, Livewire,
   Policy, `DemoInvariantChecker` o restricción de base de datos.
6. Si hoy existe en la interfaz (`CiudadanoPage` u otra) un flujo para crear un plan especializado
   **sin** derivación desde un plan ASP. Solo verificar e informar; ver «Qué NO hacer».

**Catálogos y datos existentes:**
7. Que existe exactamente un centro llamado «CIAM Puente de Vallecas», y cuál es su UO.
8. Qué cargos existen en el catálogo `cargos` (nombres exactos).
9. Qué tipos de actividad existen (slugs).
10. El significado exacto de los valores del campo `sexo` en ciudadanos y profesionales. Hay que
    confirmar qué valor corresponde a **mujer** antes de generar datos, porque `M` puede significar
    «mujer» o «masculino».

**Si algo no coincide con lo que describen estas instrucciones, parar y consultar antes de continuar.**
No improvisar un diseño alternativo.

---

## Fase 1 — Modo aditivo del sistema de mundos

### 1.1 Declaración en el YAML

Se añaden dos claves de nivel raíz:

```yaml
nombre: "Prueba CIAM"
modo: aditivo          # valores: reset (por defecto, comportamiento actual) | aditivo
etiqueta: TEST_CIAM    # obligatoria si modo = aditivo; [A-Z0-9_]+
```

- Si `modo` no aparece, el mundo es `reset` y todo funciona exactamente como ahora. **Los mundos
  existentes no se modifican.**
- `DemoWorldLoader` valida lo siguiente:
  - `modo` tiene un valor permitido.
  - `etiqueta` es obligatoria en modo aditivo y cumple el formato.
  - Un mundo aditivo no declara en `centros` ningún centro que deba crearse: los centros se
    referencian (ver 1.3).

### 1.2 Comando `demo:load`

```
php artisan demo:load --world=demo_ciam [--dry-run]
```

- Solo acepta mundos con `modo: aditivo`. Con un mundo `reset`, termina con error y sugiere `demo:reset`.
- `demo:reset` rechaza a su vez los mundos `aditivo`, con un mensaje explícito. Es la protección
  principal contra un truncado accidental en staging.
- **Nunca** ejecuta `TRUNCATE`, `DELETE` ni `forceDelete`.
- Todo el proceso corre en una única transacción: si algo falla, no queda nada a medias.
- Con `--dry-run`, resuelve las referencias, calcula qué crearía y qué ya existe, muestra el resumen
  y hace rollback.
- **Protección de entorno:** se niega a ejecutarse si `app()->environment('production')`, igual que
  `DemoWorldsPage`. Staging sí está permitido.
- Al terminar, ejecuta `DemoInvariantChecker` sobre las entidades etiquetadas y muestra el resumen
  (creado / ya existente / referenciado).

### 1.3 Referencias a entidades existentes

Se añade una sección `existentes` al YAML. Cada entrada tiene un id local, que el resto del YAML usa
como si fuera un centro, sala o cargo creado por el mundo:

```yaml
existentes:
  centros:
    - id: ciam_pv
      buscar_por: { nombre: "CIAM Puente de Vallecas" }
  tipos_plan:
    - id: pia
      buscar_por: { slug: pia }
  cargos:
    - id: directora
      buscar_por: { nombre: "Directora de centro" }
      crear_si_no_existe: true
  salas:
    - id: girasol
      centro: ciam_pv
      buscar_por: { nombre: "Sala Girasol" }
      crear_si_no_existe: { capacidad: 15, accesible: true }
  tipos_actividad:
    - id: empoderamiento
      buscar_por: { slug: taller-empoderamiento }
      crear_si_no_existe: { nombre: "Taller de empoderamiento" }
```

Reglas:
- La búsqueda debe devolver **exactamente un** registro. Si devuelve cero y no hay
  `crear_si_no_existe`, o si devuelve más de uno, el comando falla con un mensaje que indica qué
  entrada y por qué.
- Las entidades referenciadas **no se modifican nunca**. En particular, no se toca ningún campo del
  centro CIAM Puente de Vallecas.
- Las entidades creadas mediante `crear_si_no_existe` se etiquetan como cualquier otra (ver Fase 2).
- La resolución de `existentes` se hace en tiempo de ejecución, porque necesita la base de datos.
  El loader valida solo la estructura, igual que ya se hace con los slugs de `TipoActividad`.

### 1.4 Asociación del tipo de plan `pia` con el centro

La asociación del tipo `pia` con el CIAM se hace **con el mecanismo que ya exista hoy**, detectado en
la Fase 0, punto 4. Si el tipo ya está asociado, no se hace nada. Si no existe ningún mecanismo de
asociación entre tipo de plan y centro, UO o servicio, **parar y consultar**. No crear uno nuevo.

### 1.5 `DemoWorldsPage`

- Los mundos aditivos se muestran con una etiqueta visual «Aditivo» y con su etiqueta (`TEST_CIAM`).
- Su acción es «Cargar (aditivo)», con modal de confirmación que muestra el resultado del dry-run.
  No muestran la acción de reset.

---

## Fase 2 — Etiquetado `TEST_CIAM`

Se añade un registro genérico de lo creado por mundos aditivos, sin tocar columnas de las tablas de
dominio:

```
demo_world_registros
- id
- etiqueta           varchar   — p. ej. TEST_CIAM
- clave              varchar   — id lógico del YAML (p. ej. "ts2", "usuaria_037")
- registrable_type   varchar   — morph
- registrable_id     bigint
- created_at
unique (etiqueta, clave, registrable_type)
index (registrable_type, registrable_id)
```

- Modelo `DemoWorldRegistro`, con un helper `DemoWorldRegistro::de(string $etiqueta)` para consultar
  todo lo de una etiqueta.
- **La idempotencia se basa en este registro.** Antes de crear una entidad, el builder busca
  (`etiqueta`, `clave`, `tipo`). Si existe y el registro apuntado sigue existiendo, reutiliza el
  registro y no crea otro. Una segunda ejecución de `demo:load` no debe crear nada nuevo.
- Se registran todas las entidades creadas, incluidas las dependientes: usuarios, profesionales,
  adscripciones, ciudadanas, historias, planes, seguimientos, actividades, sesiones, salas, cargos
  y tipos de actividad creados.
- Las entidades referenciadas y **no** creadas (el centro, el tipo `pia`) **no** se registran.
- La tabla está excluida de `TABLAS_TRUNCAR` de `demo:reset`.

---

## Fase 3 — Plan especializado con entrada directa (sin PISO)

### 3.1 Regla de dominio

El CIAM es una **puerta alternativa de entrada** al sistema de servicios sociales. Sus usuarias pueden
tener o no un PISO previo. Idealmente se integrarán en ASP, pero no es requisito. Por tanto, un plan
PIA de CIAM debe poder crearse sin plan ASP del que derivar.

### 3.2 Implementación

- Nuevo campo `admite_entrada_directa` (boolean, default `false`) en `tipos_plan`.
- Con `admite_entrada_directa = true`, un plan de tipo `especializado` puede tener `plan_asp_id = null`.
- Con `admite_entrada_directa = false`, se mantiene la regla actual: el plan especializado nace de una
  derivación y requiere `plan_asp_id`.
- Aplicar la regla en **todos** los puntos detectados en la Fase 0, punto 5: modelo o servicio,
  Policy o validación, y `DemoInvariantChecker`. El invariante «planes especializados sin
  `plan_asp_id`» pasa a ignorar los tipos con `admite_entrada_directa = true`.
- Si una usuaria con plan PIA de entrada directa recibe después un PISO, el plan PIA **no** se vincula
  retroactivamente. Ambos conviven como planes independientes de la misma Historia Social.
- La migración marca `admite_entrada_directa = true` en el tipo `pia`, solo si existe y solo en ese
  campo. `TipoPlanSeeder` debe respetar el valor: no puede volver a ponerlo a `false` en una
  re-ejecución.
- `TipoPlanResource` en Filament: añadir el toggle «Admite entrada directa (sin plan ASP previo)».

### 3.3 Documentación

- Actualizar `docs/modulo-intervencion.md` §5.1 y §5.2 con la regla y el nuevo campo.
- Actualizar la entrada «Plan Individualizado de Intervención Social» de `docs/glosario.md` con una
  línea sobre la entrada directa.

---

## Fase 4 — Escenarios CIAM

Se crean en `database/seeders/Demo/Scenarios/`. No se modifican los escenarios existentes.

| Escenario | Historia social | Plan | Seguimientos |
|---|---|---|---|
| `CiamPiaActiva` | abierta, UO del CIAM | tipo `pia`, `especializado`, `plan_asp_id = null`, estado `activo` | 2–4 |
| `CiamPiaCerrada` | cerrada | tipo `pia`, estado `cerrado`, `motivo_cierre` repartido entre `consecucion_objetivos` (mayoría) y `fin_intervencion` | 3–6 |
| `CiamParticipanteActividad` | sin plan | — | — |
| `CiamInformacion` | según el modelo actual para atenciones puntuales (ver nota) | — | — |

Reglas comunes:
- El profesional responsable del plan se reparte entre las 3 trabajadoras sociales, la psicóloga,
  la abogada y la directora, con predominio de las trabajadoras sociales.
- **No hay escenario urgente.** Las urgencias por violencia de género se derivan a otros sistemas de
  atención y no se atienden en el CIAM.
- **Ninguna usuaria se marca como víctima de violencia de género**, ni se incluye en colectivo
  especialmente protegido, ni recibe prestaciones de VG. Es una decisión explícita para esta fase.
- Ninguna usuaria tiene PISO en este mundo.
- `CiamParticipanteActividad` inscribe a la ciudadana en una o varias actividades del mundo.
- `CiamInformacion`: usar la forma más ligera que ya exista en el modelo para registrar una atención
  informativa (entrevista o apunte de información). Si no hay una forma clara, parar y consultar.

---

## Fase 5 — `demo_ciam.yaml` como mundo «Prueba CIAM»

Reescribir el fichero con esta estructura. El recuento de usuarias es exacto; las distribuciones
internas son valores de partida.

### 5.1 Cabecera y existentes

- `nombre: "Prueba CIAM"`, `modo: aditivo`, `etiqueta: TEST_CIAM`.
- Centro referenciado: CIAM Puente de Vallecas. El CSS que tenía el `demo_ciam.yaml` anterior
  **desaparece** del mundo.
- Tipo de plan referenciado: `pia`.
- Salas: Sala Girasol y Sala Polivalente, referenciadas y creadas si no existen.

### 5.2 Personal (10 personas, todas mujeres, adscritas a la UO del CIAM)

| Puesto | Cargo (crear si no existe) | Roles | Usuario / correo | Contraseña |
|---|---|---|---|---|
| Directora | Directora de centro | `intervencion`, `supervision`, `adm_usuarios` | `dir.ciam@vida.local` | `dir987` |
| Trabajadora social 1 | Trabajadora social | `intervencion` | `ts1.ciam@vida.local` | `ts1987` |
| Trabajadora social 2 | Trabajadora social | `intervencion` | `ts2.ciam@vida.local` | `ts2987` |
| Trabajadora social 3 | Trabajadora social | `intervencion` | `ts3.ciam@vida.local` | `ts3987` |
| Abogada | Abogada | `intervencion` | `abogada.ciam@vida.local` | `abogada987` |
| Psicóloga | Psicóloga | `intervencion` | `psicologa.ciam@vida.local` | `psicologa987` |
| Administrativa 1 | Administrativa | `tramitacion` | `adm1.ciam@vida.local` | `adm1987` |
| Administrativa 2 | Administrativa | `tramitacion` | `adm2.ciam@vida.local` | `adm2987` |
| Auxiliar de SS 1 | Auxiliar de servicios sociales | `intervencion` | `aux1.ciam@vida.local` | `aux1987` |
| Auxiliar de SS 2 | Auxiliar de servicios sociales | `intervencion` | `aux2.ciam@vida.local` | `aux2987` |

Convención: abreviatura del puesto más número, y el número solo cuando hay más de una persona en el
puesto. La contraseña es el usuario sin el dominio, seguido de `987`.

- Cada fila crea un `Profesional` (sexo = mujer) y un `Usuario` vinculado.
- La asignación de `supervision` a la directora exige aprobación previa en el flujo normal. En la
  carga del mundo se crea ya aprobada, igual que hacen hoy los mundos existentes con roles de
  aprobación. Si hoy no se hace así, consultar.
- Si ya existe un usuario con alguno de esos correos que **no** pertenece a `TEST_CIAM`, el comando
  falla y no lo sobrescribe.
- Las cuentas deben poder iniciar sesión directamente, sin forzar el onboarding de cambio de
  contraseña, si así lo hacen los mundos actuales. Mantener el mismo criterio.

### 5.3 Actividades (sustituyen a las 3 del `demo_ciam` actual)

| Actividad | Tipo (crear si no existe) | Sala | Profesionales | Sesiones |
|---|---|---|---|---|
| Taller de empoderamiento | `taller-empoderamiento` | Girasol | psicóloga, ts1 | 4 (-21d, -14d, -7d, +7d) |
| Formación en competencias digitales | `formacion` | Polivalente | aux1 | 4 (-14d, -7d, 0d, +7d) |
| Taller de búsqueda de empleo | `taller-empleo` | Polivalente | ts2, aux2 | 3 (-10d, -3d, +4d) |
| Sesión informativa sobre derechos | `sesion-informativa` | Polivalente | abogada | 2 (-5d, +9d) |

Antes de crear un tipo de actividad, comprobar si existe uno equivalente con otro slug (Fase 0,
punto 9), y usarlo en ese caso.

### 5.4 Usuarias (100, todas mujeres)

| Escenario | Nº |
|---|---|
| `CiamPiaActiva` | 30 |
| `CiamPiaCerrada` | 20 |
| `CiamParticipanteActividad` | 35 |
| `CiamInformacion` | 15 |

Datos ficticios:
- Faker `es_ES` con nombres de mujer.
- Edades entre 18 y 75 años.
- Direcciones en el distrito de Puente de Vallecas.
- Documentos de identidad con el mismo generador ficticio que ya usan los mundos existentes.
- Las 50 usuarias con plan pueden participar también en alguna actividad. Aproximadamente un tercio
  lo hace.

---

## Tests funcionales

Fichero nuevo: `tests/Feature/Demo/DemoAditivoTest.php`, salvo `TF-DEMO-CIAM-12` a `14`, que van en
`Modules/Intervencion/tests/Feature/PlanEntradaDirectaTest.php`.

Convenciones: PHPUnit con `#[Test]`, PostgreSQL (`vida_testing`), patrón Dado/Cuando/Entonces.
Incluir el caso negativo en los tests de restricciones.

**Modo aditivo:**

- **TF-DEMO-CIAM-01** — El loader rechaza un mundo `aditivo` sin `etiqueta`.
- **TF-DEMO-CIAM-02** — `demo:reset` rechaza un mundo `aditivo` y no ejecuta ningún TRUNCATE.
  Dado un centro preexistente, este sigue existiendo después.
- **TF-DEMO-CIAM-03** — `demo:load` rechaza un mundo `reset`.
- **TF-DEMO-CIAM-04** — `demo:load` no borra datos preexistentes. Dado un conjunto de ciudadanos,
  usuarios y centros previos, el recuento de cada tabla tras la carga es igual o mayor, y todos los
  ids previos siguen existiendo.
- **TF-DEMO-CIAM-05** — `demo:load` es idempotente: una segunda ejecución no crea ningún registro
  nuevo.
- **TF-DEMO-CIAM-06** — `--dry-run` no persiste nada.
- **TF-DEMO-CIAM-07** — `demo:load` se niega a ejecutarse en entorno `production`.

**Referencias y etiquetado:**

- **TF-DEMO-CIAM-08** — Una referencia que no encuentra registro, sin `crear_si_no_existe`, hace
  fallar la carga completa sin dejar datos a medias.
- **TF-DEMO-CIAM-09** — Una referencia ambigua (más de un resultado) hace fallar la carga.
- **TF-DEMO-CIAM-10** — El centro referenciado no se modifica: `updated_at` y todos sus campos
  quedan igual.
- **TF-DEMO-CIAM-11** — Todo lo creado queda registrado en `demo_world_registros` con la etiqueta
  del mundo; nada de lo referenciado aparece registrado.

**Plan con entrada directa:**

- **TF-DEMO-CIAM-12** — Un plan especializado de un tipo con `admite_entrada_directa = true` se crea
  con `plan_asp_id = null`.
- **TF-DEMO-CIAM-13** — Negativo: un plan especializado de un tipo con `admite_entrada_directa = false`
  y `plan_asp_id = null` es rechazado.
- **TF-DEMO-CIAM-14** — `DemoInvariantChecker` no reporta como violación un plan especializado sin
  plan ASP cuando su tipo admite entrada directa.

**Contenido del mundo:**

- **TF-DEMO-CIAM-15** — Tras cargar `demo_ciam`:
  - Hay 10 profesionales y 10 usuarios con etiqueta `TEST_CIAM`, con los correos y roles de la
    tabla 5.2.
  - Hay 100 ciudadanas, todas mujeres.
  - Hay exactamente 50 planes de tipo `pia`: 30 activos y 20 cerrados, todos con
    `plan_asp_id = null`.
- **TF-DEMO-CIAM-16** — Ninguna ciudadana con etiqueta `TEST_CIAM` pertenece a un colectivo
  especialmente protegido ni tiene prestaciones de violencia de género.

---

## Qué NO hacer

- No modificar el comportamiento de `demo:reset` ni de los mundos existentes. La única excepción es
  el rechazo de los mundos aditivos.
- No crear un comando de purga ni de borrado por etiqueta. La etiqueta sirve para localizar; la
  retirada se diseñará aparte.
- No construir en la interfaz un flujo nuevo de creación de planes PIA. Solo informar de si hoy es
  posible (Fase 0, punto 6).
- No crear un mecanismo nuevo de asociación tipo de plan ↔ centro (ver 1.4).
- No marcar a ninguna usuaria como víctima de violencia de género.
- No modificar el centro CIAM Puente de Vallecas ni ninguna entidad referenciada.
- No ejecutar nada contra staging. La carga en staging la hace el equipo.

---

## Criterio de finalización

1. La Fase 0 está documentada en la respuesta de CLI, con las discrepancias encontradas.
2. `php artisan demo:validate demo_ciam` pasa sin errores.
3. En local, `php artisan demo:load --world=demo_ciam --dry-run` muestra el resumen sin persistir nada.
4. En local, `php artisan demo:load --world=demo_ciam` carga el mundo, y una segunda ejecución informa
   de 0 registros creados.
5. `php artisan demo:reset --world=demo_ciam` es rechazado.
6. Los tests `TF-DEMO-CIAM-01` a `TF-DEMO-CIAM-16` están en verde.
7. `php artisan test Modules/Intervencion/tests/` y `php artisan test tests/Feature/Demo/` pasan sin
   regresiones.
8. Se puede iniciar sesión en local con `dir.ciam@vida.local` / `dir987` y con `ts2.ciam@vida.local`
   / `ts2987`, y la trabajadora social ve las usuarias del CIAM.
9. Documentación actualizada:
   - `docs/modulo-intervencion.md` y `docs/glosario.md` (Fase 3.3).
   - `docs/instrucciones-cli/demo-worlds-cli.md`, con una sección nueva sobre el modo aditivo.
   - La tabla de instrucciones de `CLAUDE.md`, con este fichero.
10. Entrada en el CHANGELOG del mes y `SESSION.md` actualizado, con el comando exacto que el equipo
    debe ejecutar en staging: primero `--dry-run` y después la carga real.

---

## Iteraciones en la sesión (2026-09-24)

Correcciones y decisiones indicadas por el desarrollador durante la sesión, en orden:

1. **«No, las instrucciones están mal, puedes trabajar en staging».** Se anula la restricción
   «No ejecutar nada contra staging»: Claude CLI puede desplegar y cargar el mundo en staging.
2. **BD compartida** (hallazgo de la Fase 0): el entorno local y staging usan la misma base de datos
   `vida`. Decisión: tests en `vida_testing`; migración y `demo:load --dry-run` sobre la BD
   compartida; la carga real solo tras confirmación explícita del desarrollador.
3. **Punto 1.4** (no existe mecanismo tipo de plan ↔ centro/UO/servicio): se omite; `pia` ya está
   disponible en todos los centros. Se anota en BACKLOG.
4. **Cargos:** reutilizar los existentes con nombre neutro (`Trabajador/a Social`, `Psicólogo/a`,
   `Abogado/a`, `Administrativo/a`, `Auxiliar de Servicios Sociales`); la directora usa
   `Coordinador/a de Centro`. No se crea ningún cargo.
5. **Tipos de actividad:** crear `taller-empoderamiento`; usar `charla` en lugar de
   `sesion-informativa`; `formacion` y `taller-empleo` ya existen.
6. **Documentos de identidad:** no se generan (los mundos existentes no generan ninguno).
