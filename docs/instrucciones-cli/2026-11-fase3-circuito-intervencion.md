# Instrucciones CLI — Fase 3: circuito de intervención completo

**Plan:** `docs/plan-cierre-funcional-2026-11.md`, fase 3.
**Requisito previo:** fase 2 (Tipo de Centro con la addenda D1/D2/D4/D5) terminada y desplegada. Esta fase usa `ConfiguracionEfectivaCentro::tiposPlan()`, `CentroDeUsuario::centroActivo()`, `AmbitoUsuario` y la regla de personas protegidas (RN-17 de la addenda).
**Tests:** TF-CIR-01 a TF-CIR-38 (al final de este documento).

**Alcance:**
- **A.** Planes especializados: creación correcta, centro del plan y acceso del equipo especializado.
- **B.** Derivación a atención especializada.
- **C.** Evaluación de objetivos en el seguimiento.
- **D.** Prescripción de plazas conectada al profesional de referencia.
- **E.** Cambio de profesional de referencia desde la interfaz.

**Regla de trabajo:** si el código contradice estas instrucciones o aparece un caso no previsto, **detente y pregunta**. Lo marcado como *fuera de alcance* no se toca.

**Antes de empezar:** `CLAUDE.md`, `SESSION.md`, `docs/modulo-intervencion.md` (§5, §5.2.1, §6), `docs/modulo-tipo-centro.md`, `docs/instrucciones-cli/2026-11-tipo-centro-addenda-d1.md`, `docs/modulo-asignacion.md` (§4.5), `docs/modulo-centros.md` (§2.8), `docs/modulo-solicitudes-expedientes.md` (RN-01), `docs/glosario.md` («Derivación»), `docs/design-system/SKILL.md` y `docs/instrucciones-cli/2026-09-bootstrap-unico.md`.

---

## Hechos que no hay que redescubrir

- `PlanPage::crearNuevoPlan()` (`Modules/Intervencion/app/Http/Livewire/PlanPage.php:1092`) crea **siempre** `tipo = TipoPlan::GeneralAsp`, aunque el `tipo_plan_id` elegido sea de ámbito `especializado`. `CiudadanoPage::pisoActivo()` y `MisCasos` filtran por `tipo = general_asp`: un PIA creado a mano se trata como el PISO.
- `tipos_plan` tiene `ambito` (`asp` / `especializado`) y `admite_entrada_directa`. `PlanDeIntervencion` tiene `tipo`, `tipo_plan_id`, `plan_asp_id`, `servicio_especializado_id` y `profesional_responsable_id`, pero **no tiene centro ni UO**. `PlanDeIntervencion::verificarOrigenPlanEspecializado()` exige `plan_asp_id` en los especializados salvo entrada directa.
- **La Historia Social es única por persona** (`AperturaHistoriaService::abrir()` devuelve la existente) y su UO es la del centro donde se abrió. `PlanDeIntervencionPolicy` y `CiudadanoPolicy` deciden el nivel 1 por la UO de la historia. **Hoy el personal de un CIAM no puede gestionar un PIA de una persona cuya historia se abrió en un CSS.**
- No existe tabla `derivaciones` ni acción de derivar. El glosario define la derivación como una prestación del catálogo que crea el vínculo entre el plan ASP y el especializado.
- `plan_objetivo_indicadores` tiene `tipo_valoracion`, `valoracion_actual`, `fecha_valoracion` y `seguimiento_id`. `PlanPage` permite valorar indicadores al editar el plan, pero el seguimiento (`CiudadanoPage`, línea ~1183, `SeguimientoPlan::create`) no los evalúa y `valoracion_actual` se sobrescribe sin historial.
- `PrescripcionService` tiene un `tsrResolver` que devuelve siempre `null` y nadie llama a `setTsrResolver()`. `AsignarPlazaModal::alertarTsr()` (línea ~195) solo escribe en el log.
- `AsignacionReferenciaService::cambiarManual()` existe (con motivo y supervisor) pero no tiene interfaz.

---

## Decisiones de diseño de esta fase

Las decisiones con ⚑ las ha tomado el analista por coherencia con lo ya decidido; están pendientes de la confirmación del Delegado Digital. Si al empezar no consta su confirmación en `SESSION.md`, **pregunta antes del bloque A**.

| Id | Decisión |
|---|---|
| F3-1 | `PlanDeIntervencion::tipo` se deriva de `tipos_plan.ambito`: `asp` → `general_asp`; `especializado` → `especializado`. Nadie lo elige a mano |
| F3-2 ⚑ | **Todo plan tiene centro** (`centro_id`): el centro donde se crea, que debe ofrecer su tipo de plan (`tiposPlan()`). El PISO, el del centro de la historia; un especializado, el centro especializado |
| F3-3 ⚑ | **Acceso del equipo del plan.** El personal adscrito al centro de un plan activo tiene, según su rol, nivel 1 sobre ese plan y lectura de la Historia Social completa de la persona (para tener el contexto, como piden los grupos multifuncionales). Solo el responsable del plan y la supervisión de su centro lo editan. El TSR ve el plan especializado, no lo edita (`modulo-intervencion.md` §5.1) |
| F3-4 ⚑ | **Personas protegidas (amplía RN-17 de la addenda).** El ámbito reducido de una persona protegida incluye a los adscritos directamente al centro de su historia **y** a los adscritos al centro de cualquier plan activo suyo. Las UO superiores siguen necesitando acceso concedido |
| F3-5 ⚑ | **La derivación es una prestación con vía de acceso `derivacion`** (amplía `modulo-solicitudes-expedientes.md` RN-01, que tenía cuatro vías). La prestación de derivación indica el tipo de plan especializado que genera |
| F3-6 ⚑ | **Flujo de derivación:** el TSR deriva desde el PISO (y, si quiere, desde un objetivo) eligiendo la prestación de derivación y el centro de destino entre los que ofrecen ese tipo de plan. Cae en la **bandeja de derivaciones del centro de destino**. La dirección o la supervisión del centro de destino la **acepta asignando un profesional responsable** (o la rechaza con motivo). Al aceptarla se crea el plan especializado en borrador, con `plan_asp_id`, centro y responsable |
| F3-7 | Evaluación de objetivos en cada seguimiento, con historial de valoraciones por indicador. El estado del objetivo se propone a partir de la valoración y el profesional lo confirma |
| F3-8 | Al liberarse o asignarse una plaza, la alerta va al TSR vigente (`AsignacionReferenciaService::vigente()`) por `AlertaService` |

---

## Fuera de alcance

- La vía de acceso completa de las prestaciones (directa, tramitación interna, expediente externo): fase 3 bis. Aquí solo se crea el campo `via_acceso` con los valores `derivacion` y `plaza` y el resto queda `null` hasta la fase 3 bis.
- Planes de unidad de convivencia (sin cambios).
- Derivaciones a otros sistemas (sanidad, educación): solo derivación interna a centros de VIDA.
- Traslado de la historia entre centros (sigue `modulo-asignacion.md` RN-10).

---

## Paso 0 — Verificación

1. `git pull`, suites de Intervención, Centro, Ciudadanía, Mensajes y `tests/Feature/Acceso`. Anota los fallos previos.
2. Confirma que existen `ConfiguracionEfectivaCentro::tiposPlan()`, `CentroDeUsuario::centroActivo()`, `AmbitoUsuario::gestionaProtegido()` y `TipoCentro`. **Si falta alguno, detente: la fase 2 no está terminada.**
3. Lista los planes existentes con `tipo` incoherente con el `ambito` de su `tipo_plan_id`, y los que no tienen `tipo_plan_id`. Informa del recuento por caso.
4. Lista todos los lectores de `PlanDeIntervencion::tipo` y de `TipoPlan::GeneralAsp` (fichero y línea).
5. Localiza en `PlanDeIntervencionPolicy`, `CiudadanoPolicy`, `HistoriaSocialPolicy`, `AmbitoUoScope` y `AccesoExpediente` los puntos donde se decide el nivel 1 por la UO de la historia. Informa antes del bloque A.
6. Comprueba cómo se modelan hoy las alertas con enlace a un elemento (`AlertaService::crear()`, campos de origen) para reutilizarlo en derivaciones y plazas.

---

## Bloque A — Planes especializados

### A1. Tipo derivado del tipo de plan
1. `PlanDeIntervencion`: en `saving`, si hay `tipo_plan_id`, `tipo` se fija desde `tipoPlan->ambito`. Un `tipo` que no coincida con el ámbito lanza `DomainException`. Sin `tipo_plan_id` no se puede crear un plan nuevo (los antiguos sin tipo siguen siendo legibles).
2. `PlanPage::crearNuevoPlan()` deja de fijar `tipo`.
3. Migración de datos: corrige `tipo` en los planes con `tipo_plan_id` incoherente (paso 0.3) y anota el recuento en el CHANGELOG. Los planes sin `tipo_plan_id` no se tocan.

### A2. Centro del plan
1. Migración: `planes_intervencion.centro_id` (FK nullable a `centros`). Relleno: para cada plan, el centro de la UO de su historia; si no se puede deducir, `null` y al informe. En un paso posterior, obligatorio para planes nuevos (validación de modelo, no `NOT NULL`, mientras queden antiguos sin centro).
2. Al crear un plan:
   - el centro es el del contexto o el centro activo del usuario (addenda §3.5);
   - el tipo de plan debe estar en `tiposPlan()` de ese centro (validación en el servicio, no solo en el selector);
   - un plan `general_asp` solo puede crearse en el centro de la historia;
   - un `especializado` con entrada directa (`admite_entrada_directa`) puede crearse en cualquier centro que ofrezca su tipo; sin entrada directa, solo nace de una derivación (bloque B).
3. Una historia puede tener a la vez su PISO y varios planes especializados de centros distintos.

### A3. Acceso del equipo del plan (F3-3, F3-4)
1. `AmbitoUsuario`: método `centrosDeLaPersona(Ciudadano): Collection` (centro de la historia + centros de planes activos) y regla de lectura de la historia para el personal adscrito a cualquiera de ellos.
2. `PlanDeIntervencionPolicy`:
   - `view`: el ámbito actual **o** adscripción al centro del plan;
   - `update`: el responsable del plan o la supervisión de su centro; el TSR, solo en planes `general_asp`;
   - las demás acciones de escritura, igual que `update`.
3. `CiudadanoPolicy::view` y `HistoriaSocialPolicy::view`: añaden el acceso de lectura por `centrosDeLaPersona()`. **No dan `update` de la ficha** por esta vía.
4. Personas protegidas: `gestionaProtegido()` pasa a ser verdadero también para los adscritos directos al centro de un plan activo (F3-4).
5. `CiudadanoPage`: `pisoActivo()` sigue buscando `general_asp`. Nuevo bloque «Planes de la persona»: cada plan activo con tipo, centro, responsable, estado y próximo seguimiento, con enlace al plan según permisos.
6. `MisCasos`: el profesional ve también los planes especializados de los que es responsable, identificados por tipo de plan.

---

## Bloque B — Derivación a atención especializada

### B1. Configuración en el catálogo
1. `prestaciones.via_acceso` (enum o catálogo, según el patrón de `tipo_prestacion`; nullable): en esta fase, `plaza` y `derivacion`. La fase 3 bis añade el resto.
2. `prestaciones.tipo_plan_derivacion_id` (FK nullable a `tipos_plan`): obligatorio si `via_acceso = derivacion`, y el tipo de plan debe ser de ámbito `especializado`.
3. `PrestacionResource`: los dos campos, con la validación.
4. Seeder de prueba: una prestación de derivación al tipo `pia`.

### B2. Entidad `Derivacion`
Módulo `Intervencion`.

| Campo | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `historia_id` | FK | |
| `plan_origen_id` | FK | Plan `general_asp` desde el que se deriva |
| `plan_objetivo_id` | FK nullable | Objetivo del PISO, si se deriva desde él |
| `prestacion_id` | FK | Prestación con `via_acceso = derivacion` |
| `tipo_plan_id` | FK | Copia del tipo de plan de la prestación en el momento de derivar |
| `centro_destino_id` | FK | Debe ofrecer `tipo_plan_id` |
| `motivo` | text | Visible para el centro de destino |
| `estado` | enum `EstadoDerivacion` | `pendiente` / `aceptada` / `rechazada` / `anulada` |
| `derivada_por_id` | FK users | |
| `resuelta_por_id` | FK users nullable | |
| `profesional_responsable_id` | FK users nullable | Al aceptar |
| `plan_especializado_id` | FK nullable | Al aceptar |
| `motivo_rechazo` / `motivo_anulacion` | text nullable | Obligatorios en su estado |
| timestamps | | |

Eventos: `derivacion_eventos`, inmutable (trigger que rechaza UPDATE y DELETE, como `cita_eventos`): quién, cuándo, estado antes y después, motivo.

### B3. Servicio `DerivacionService`
- `derivar(PlanDeIntervencion $piso, Prestacion, Centro $destino, string $motivo, ?PlanObjetivo, User $tsr)`: valida que el plan es `general_asp` activo, que el usuario puede editarlo, que la prestación es de derivación, que el destino ofrece el tipo de plan y que no hay otra derivación `pendiente` igual (mismo destino y tipo). Crea la derivación, el evento, un apunte `derivacion` en la historia y una alerta a la dirección y la supervisión del centro de destino.
- `aceptar(Derivacion, User $responsable, User $actor)`: solo dirección o supervisión del centro de destino; el responsable debe estar adscrito a ese centro. Crea el plan especializado en borrador (`tipo_plan_id`, `plan_asp_id`, `centro_id = destino`, responsable). Evento, apunte y alerta al TSR y al responsable.
- `rechazar(Derivacion, string $motivo, User $actor)`: mismo permiso. Evento y alerta al TSR.
- `anular(Derivacion, string $motivo, User $actor)`: solo el TSR que derivó o la supervisión del centro de origen, y solo si está `pendiente`.

### B4. Interfaz
1. **PISO** (`PlanPage`): acción «Derivar a atención especializada», en el plan y en cada objetivo. Modal: prestación de derivación, centro de destino (lista de centros que ofrecen el tipo de plan, con el de la persona del tipo correspondiente primero si tiene asignado), motivo. Sección «Derivaciones» con estado y acceso al historial.
2. **Bandeja de derivaciones** del centro de destino (pantalla de Supervisión, Livewire): pendientes con persona, centro de origen, TSR, tipo de plan, motivo y antigüedad; acciones aceptar (con selector de profesional adscrito al centro) y rechazar. Personas protegidas: identidad visible solo para la supervisión adscrita al centro de destino (RN-19 de la addenda).
3. **Plan especializado:** cabecera con «Nace de la derivación del PISO de [centro]», enlace al PISO para quien pueda verlo.

---

## Bloque C — Evaluación de objetivos en el seguimiento

1. Tabla `plan_indicador_valoraciones` (historial): `plan_objetivo_indicador_id`, `seguimiento_id` nullable, `valoracion`, `comentario` nullable, `profesional_id`, `fecha`, timestamps. Sin update ni delete (corregir = nueva valoración).
2. Al registrar un seguimiento (`CiudadanoPage` y, si existe, el formulario de `PlanPage`):
   - se muestran los objetivos activos del plan con su indicador, la valoración actual y la fecha;
   - el profesional valora cada indicador (opcional por indicador) con los valores de su `tipo_valoracion`;
   - por cada objetivo valorado, se **propone** el estado (`conseguido` → `conseguido`; `en_proceso`/`se_mantiene`/`favorable` → `en_proceso`; `no_conseguido`/`desfavorable`/`no` → sin propuesta) y el profesional lo confirma o lo cambia; `abandonado` siempre a mano.
3. Al guardar: una fila en `plan_indicador_valoraciones` por indicador valorado, `valoracion_actual`, `fecha_valoracion` y `seguimiento_id` actualizados en el indicador, estado del objetivo actualizado. Todo en una transacción con el seguimiento.
4. `objetivos_cumplidos` (texto) se mantiene para notas libres.
5. La ficha del plan muestra la evolución de cada indicador (lista de valoraciones con fecha).
6. Las valoraciones hechas al editar el plan en `PlanPage` también escriben en el historial (sin `seguimiento_id`).

---

## Bloque D — Prescripción de plazas y profesional de referencia

1. `CentroServiceProvider` (o el provider que corresponda): `PrescripcionService::setTsrResolver()` con un resolver que use `AsignacionReferenciaService::vigente()` sobre la historia del ciudadano.
2. `AsignarPlazaModal::alertarTsr()`: sustituye el `Log::info` por una alerta de `AlertaService` al profesional de referencia vigente, con enlace a la prescripción. Sin referencia: alerta a la supervisión del centro de la historia (no solo log).
3. `PrescripcionService::liberarPlaza()`: la alerta al profesional de la lista de espera usa el mismo resolver y `AlertaService`.

---

## Bloque E — Cambio de profesional de referencia

1. En la ficha de la persona (bloque «Centro y referencia») y en Mi equipo / bandeja de asignaciones: acción «Cambiar referencia» para `supervision` del centro de la historia (`CentroDeUsuario::supervisa()`).
2. Modal: profesional nuevo (adscrito al centro y con cargo elegible para referencia), motivo de un catálogo (conflicto, parentesco, incompatibilidad, corrección, otro) y texto si «otro». Llama a `AsignacionReferenciaService::cambiarManual()`.
3. Alertas al profesional saliente y al entrante. Personas protegidas: solo la supervisión adscrita al centro (RN-17/RN-19).
4. Autorización por centro dentro de `AsignacionReferenciaService` y `AsignacionCentroService` (BACKLOG «Asignación de centro y referencia: pendientes»): cada método público comprueba que el actor supervisa el centro.

---

## Cierre

1. Suites de Intervención, Centro, Ciudadanía, Mensajes, Supervisión y `tests/Feature/Acceso`; después la suite completa. Sin fallos nuevos.
2. `npm run build` y `php artisan ui:auditar` en verde.
3. Documentación:
   - `modulo-intervencion.md`: §5.1 (derivación y centro del plan), §5.2 (`centro_id`), §5.2.1 (historial de valoraciones), §6 (evaluación en el seguimiento), nueva sección «Derivación»;
   - `modulo-prestaciones.md`: `via_acceso` y `tipo_plan_derivacion_id`;
   - `modulo-solicitudes-expedientes.md` RN-01: añade la vía `derivacion`;
   - `modulo-asignacion.md` §4.5: interfaz del cambio de referencia;
   - la addenda de Tipo de Centro: RN-17 ampliada (F3-4).
4. CHANGELOG (recuentos de la migración de datos, decisiones no previstas, negativos comprobados), BACKLOG (cierra «La interfaz no permite crear planes especializados», «No existe asociación tipo de plan ↔ centro», «Tabla `derivaciones` inexistente», «Seguimiento del plan — UI», el aviso al TSR tras asignar plaza y el cambio de referencia), SESSION, `CLAUDE.md` §6.
5. Push y despliegue en verde.

## Criterios de finalización

1. TF-CIR-01 a 38 en verde, con los negativos comprobados.
2. Ningún plan nuevo con `tipo` distinto del ámbito de su tipo de plan; ningún lector de `tipo` roto.
3. Un profesional de un CIAM gestiona el PIA de una persona cuya historia es de un CSS, y lee su historia.
4. Ninguna alerta de plazas queda solo en el log.
5. Suite completa sin fallos nuevos, documentación al día, despliegue en verde.

---

# Tests — TF-CIR-01 a TF-CIR-38

**Convenciones:** PHPUnit con `#[Test]`, PostgreSQL (`vida_testing`), Dado / Cuando / Entonces. Ubicación: `Modules/Intervencion/tests/Feature/Circuito/` (`PlanTipoTest`, `PlanCentroTest`, `AccesoPlanTest`, `DerivacionTest`, `BandejaDerivacionesTest`, `EvaluacionObjetivosTest`, `PrescripcionTsrTest`, `CambioReferenciaTest`). Los marcados **[negativo]** deben fallar sin la protección; compruébalo y anótalo.

**Datos (trait `CircuitoTestSetup`):** reutiliza `TipoCentroTestSetup` (`$tipoCss` con PISO, `$tipoCiam` con PIA, `$cssNorte`, `$ciamVallecas`, `$ciamUsera`, sus UO y juntas). Además:
- `$tsNorte` (`intervencion`, adscrita a `$uoCssNorte`, cargo TS) y `$tsNorte2`; `$supNorte` (`supervision`, `$uoCssNorte`).
- `$psicologaVallecas` (`intervencion`, `$uoCiamVallecas`); `$directoraVallecas` (`DirectorCentro` vigente y `supervision` en `$uoCiamVallecas`).
- `$ana`: ciudadana con historia en `$cssNorte`, referencia `$tsNorte`, PISO activo `$pisoAna` con dos objetivos (uno con indicador `conseguido_proceso_no` y otro `si_no`).
- `$marta`: ciudadana **protegida** con historia en `$cssNorte` y PISO activo.
- `$derivPia`: prestación con `via_acceso = derivacion` y `tipo_plan_derivacion_id = pia`.

## Grupo A — Tipo y centro del plan

**TF-CIR-01 — El tipo sale del tipo de plan** **[negativo]**: crear desde `PlanPage` un plan PIA en `$ciamVallecas` → `tipo = especializado`. Crear un PISO en `$cssNorte` → `general_asp`. Forzar `tipo = general_asp` con `tipo_plan_id = pia` → `DomainException`.

**TF-CIR-02 — El PIA no se confunde con el PISO**: con un PIA activo y sin PISO, `pisoActivo()` es `null` y `MisCasos` del responsable muestra el PIA como «PIA», no como PISO.

**TF-CIR-03 — Migración de tipos**: planes con `tipo` incoherente se corrigen; los que no tienen `tipo_plan_id` quedan igual; el recuento coincide con el informe.

**TF-CIR-04 — Tipo de plan que el centro no ofrece** **[negativo]**: crear un PIA en `$cssNorte` o un PISO en `$ciamVallecas`, por servicio y por la acción Livewire → rechazado.

**TF-CIR-05 — El PISO solo en el centro de la historia**: `$psicologaVallecas` intenta crear un PISO para `$ana` → rechazado (además de por el tipo, por no ser el centro de la historia).

**TF-CIR-06 — Entrada directa**: con `pia.admite_entrada_directa = true`, `$psicologaVallecas` crea un PIA para `$ana` sin derivación → se crea con `plan_asp_id = null` y `centro_id = $ciamVallecas`. Con `false` → rechazado.

**TF-CIR-07 — Centro del plan relleno**: tras la migración, los planes existentes tienen el centro de su historia; los no deducibles, `null` y en el informe.

## Grupo B — Acceso del equipo del plan

**TF-CIR-08 — El equipo especializado gestiona su plan** **[negativo]**: con un PIA de `$ana` en `$ciamVallecas` (responsable `$psicologaVallecas`), la psicóloga ve y edita el PIA y lee la historia de `$ana` (apuntes, valoraciones). Sin el PIA, no puede editar nada de `$ana`.

**TF-CIR-09 — Leer no es editar la ficha**: `$psicologaVallecas` no puede editar la ficha de identificación de `$ana` (`CiudadanoPolicy::update`) por tener el PIA.

**TF-CIR-10 — El TSR ve el PIA y no lo edita** **[negativo]**: `$tsNorte` ve el PIA de `$ana`; cualquier escritura (objetivo, seguimiento, cierre) se rechaza en el servidor.

**TF-CIR-11 — Otro profesional del centro especializado**: una trabajadora de `$ciamVallecas` que no es responsable ve el PIA y no lo edita; `$directoraVallecas` lo edita.

**TF-CIR-12 — Plan cerrado, acceso cerrado**: al cerrar el PIA, `$psicologaVallecas` pierde el acceso a la historia de `$ana` (salvo otro motivo de acceso).

**TF-CIR-13 — Persona protegida con plan especializado** **[negativo]**: con un PIA activo de `$marta` en `$ciamVallecas`, `$psicologaVallecas` la ve con identidad (F3-4). `$coordIgualdad` (UO funcional) la sigue viendo como «Persona con protección especial».

**TF-CIR-14 — Bloque «Planes de la persona»**: la ficha de `$ana` lista el PISO y el PIA con tipo, centro, responsable y estado; el enlace al PIA solo aparece a quien puede verlo.

## Grupo C — Derivación

**TF-CIR-15 — Configuración de la prestación de derivación**: guardar una prestación `derivacion` sin tipo de plan, o con un tipo de ámbito `asp` → rechazado.

**TF-CIR-16 — Derivar**: `$tsNorte` deriva `$ana` desde `$pisoAna` a `$ciamVallecas` con `$derivPia` → derivación `pendiente`, evento, apunte `derivacion` en la historia y alerta a `$directoraVallecas`.

**TF-CIR-17 — Destino que no ofrece el tipo** **[negativo]**: derivar a `$cssNorte` con `$derivPia` → rechazado.

**TF-CIR-18 — Solo desde un PISO activo y editable**: derivar desde un PIA, desde un PISO cerrado o por un usuario que no puede editar el PISO → rechazado.

**TF-CIR-19 — Sin duplicados**: segunda derivación igual con otra `pendiente` → rechazada; a otro centro (`$ciamUsera`) → permitida.

**TF-CIR-20 — Aceptar crea el plan**: `$directoraVallecas` acepta asignando a `$psicologaVallecas` → derivación `aceptada`, PIA en borrador con `plan_asp_id = $pisoAna`, `centro_id = $ciamVallecas`, responsable la psicóloga; alertas al TSR y a la responsable; evento.

**TF-CIR-21 — Quién acepta** **[negativo]**: `$psicologaVallecas`, `$tsNorte` y `$coordIgualdad` intentan aceptar → rechazado. Aceptar asignando a un profesional no adscrito a `$ciamVallecas` → rechazado.

**TF-CIR-22 — Rechazar**: `$directoraVallecas` rechaza con motivo → `rechazada`, sin plan, alerta al TSR con el motivo. Sin motivo → rechazado.

**TF-CIR-23 — Anular**: `$tsNorte` anula una pendiente con motivo → `anulada`; anular una aceptada → rechazado; anularla `$tsNorte2` (no derivó y no es supervisor) → rechazado.

**TF-CIR-24 — Historial inmutable** **[negativo]**: UPDATE o DELETE directo sobre `derivacion_eventos` → error de base de datos.

**TF-CIR-25 — Bandeja de derivaciones**: `$directoraVallecas` ve las pendientes de su centro con persona, origen, TSR, tipo de plan, motivo y antigüedad; no ve las de `$ciamUsera`.

**TF-CIR-26 — Bandeja con persona protegida**: derivación de `$marta` a `$ciamVallecas`: `$directoraVallecas` ve la identidad; `$coordIgualdad` (supervisión por ámbito funcional) ve «Persona con protección especial» y no puede aceptar.

**TF-CIR-27 — Derivación desde un objetivo**: derivar desde un objetivo de `$pisoAna` guarda `plan_objetivo_id` y el objetivo muestra la derivación y su estado.

## Grupo D — Evaluación de objetivos

**TF-CIR-28 — Valorar en el seguimiento**: al registrar un seguimiento de `$pisoAna`, valorar el primer indicador `en_proceso` y el segundo `si` → dos filas de historial con `seguimiento_id`; `valoracion_actual` y `fecha_valoracion` actualizados.

**TF-CIR-29 — Propuesta de estado**: valoración `conseguido` propone `conseguido`; el profesional lo cambia a `en_proceso` → el objetivo queda `en_proceso`. `no_conseguido` no propone cambio.

**TF-CIR-30 — Historial, no sobrescritura** **[negativo]**: dos seguimientos con valoraciones distintas → el historial tiene las dos; la ficha muestra la evolución en orden.

**TF-CIR-31 — Valorar es opcional**: un seguimiento sin valoraciones se guarda; los indicadores no cambian.

**TF-CIR-32 — Transacción**: si falla el guardado de una valoración, no se crea el seguimiento ni cambia ningún objetivo.

**TF-CIR-33 — Valorar al editar el plan también deja historial**: valorar en `PlanPage` → fila de historial sin `seguimiento_id`.

## Grupo E — Plazas y referencia

**TF-CIR-34 — Alerta real al asignar plaza** **[negativo]**: asignar plaza a una prescripción de `$ana` → alerta de `AlertaService` a `$tsNorte` con enlace. Sin referencia vigente → alerta a `$supNorte`. En ningún caso solo log.

**TF-CIR-35 — Lista de espera usa el TSR vigente**: tras `cambiarManual` a `$tsNorte2`, liberar una plaza → `profesional_alerta_id = $tsNorte2` y alerta a `$tsNorte2`.

**TF-CIR-36 — Cambiar referencia desde la interfaz**: `$supNorte` cambia la referencia de `$ana` a `$tsNorte2` con motivo → asignación `manual` nueva, la anterior cerrada, alertas a los dos profesionales; no cuenta en el reparto.

**TF-CIR-37 — Quién cambia la referencia** **[negativo]**: `$tsNorte`, `$coordPrimaria` (UO superior) y un supervisor de otro centro intentan cambiarla, por la acción Livewire y por el servicio → rechazado. Un profesional destino no adscrito al centro o sin cargo elegible → rechazado.

**TF-CIR-38 — Referencia de persona protegida**: para `$marta`, solo la supervisión adscrita a `$cssNorte` ve la acción y puede ejecutarla.

## Tabla de estado

| Grupo | Tests | Estado |
|---|---|---|
| A — Tipo y centro del plan | TF-CIR-01..07 | |
| B — Acceso del equipo del plan | TF-CIR-08..14 | |
| C — Derivación | TF-CIR-15..27 | |
| D — Evaluación de objetivos | TF-CIR-28..33 | |
| E — Plazas y referencia | TF-CIR-34..38 | |
