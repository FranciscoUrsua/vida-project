# Instrucciones CLI — Fase 6: actividades grupales con inscripción

**Plan:** `docs/plan-cierre-funcional-2026-11.md`, fase 6.
**Diseño funcional:** `docs/modulo-centros.md` §2.6, §2.7, §2.8, §4.8 a §4.10 y §4.13.
**Requisito previo:** fase 2 (centro de trabajo y RN-15: inscribir en actividades exige adscripción al centro) y fase 3 bis (prestaciones de la persona) terminadas.
**Tests:** TF-ACT-01 a TF-ACT-26 (al final).

**Regla de trabajo:** si el código contradice estas instrucciones o aparece un caso no previsto, **detente y pregunta**.

**Antes de empezar:** `CLAUDE.md`, `SESSION.md`, `docs/modulo-centros.md`, la addenda de Tipo de Centro (RN-15, RN-16), `docs/design-system/SKILL.md`, `docs/instrucciones-cli/2026-09-bootstrap-unico.md`.

---

## Hechos que no hay que redescubrir

- Existen `Actividad` (`modo_acceso` `libre`/`prescripcion`/`mixta`, `aforo_total`, `aforo_prescripcion`, `requiere_inscripcion_centro`, `activa`, `fecha_baja`), `SesionActividad` (`estado` `programada`/`celebrada`/`cancelada`, aforos que sobrescriben los de la actividad, `sala_id`), `InscripcionCentro` y `TipoActividad`.
- `Modules/Supervision/app/Http/Livewire/ActividadesPage.php` ya tiene alta y edición de actividades, profesionales asignados y CRUD de sesiones (`nuevaSesion`, `editarSesion`, `guardarSesion`, `eliminarSesion`). **`eliminarSesion()` borra la sesión físicamente.** No hay baja de actividad ni cancelación de sesiones.
- `Prescripcion` ya admite `tipo_destino = sesion_actividad` (`destino_id` → `sesiones_actividad.id`).
- **No existe la inscripción de una persona en una sesión** para el acceso libre, ni control de aforo, ni vista de inscritos.
- La entrada del BACKLOG «Actividades grupales — gestión completa» (2026-06-25) está desfasada: la edición y las sesiones ya existen.

---

## Decisiones de esta fase

⚑ = decisión del analista, pendiente de confirmar. Si no consta en `SESSION.md`, **pregunta antes del paso 2**.

| Id | Decisión |
|---|---|
| F6-1 | La inscripción apunta siempre a una sesión (diseño). ⚑ Por comodidad, «Inscribir en la actividad» inscribe en **todas las sesiones programadas futuras** con plaza; las demás se informan |
| F6-2 | **Acceso libre:** inscribe el personal adscrito al centro de la actividad (`intervencion` o `consulta_basica`, RN-15). **Prescripción:** el flujo existente desde un plan, por cualquier profesional que pueda editar el plan, también desde otro centro; consume el aforo de prescripción. **Mixta:** cada vía con su cupo |
| F6-3 ⚑ | **Aforo completo: se rechaza** la inscripción con mensaje. La lista de espera de actividades queda pendiente, como la de plazas (`modulo-solicitudes-expedientes.md` §8) |
| F6-4 | Si la actividad `requiere_inscripcion_centro` y la persona no está inscrita en el centro, el mismo flujo ofrece inscribirla en el centro (`InscripcionCentro`) antes de inscribirla en la actividad |
| F6-5 | **Nada se borra:** una actividad se da de baja (`activa = false`, `fecha_baja`); una sesión con inscripciones o prescripciones se **cancela** (`estado = cancelada`) y solo se puede borrar si no tiene ninguna. Cancelar una sesión avisa a quien inscribió o prescribió a cada persona |
| F6-6 | La asistencia y los certificados siguen siendo gestión interna del centro, fuera de VIDA (diseño §2.6) |

---

## Fuera de alcance

- Lista de espera de actividades (F6-3).
- Requisitos de acción (p. ej. autorización de los padres para inscribir a una menor): módulo propio posterior. No implementes validaciones de edad aquí.
- Asistencia, certificados y reserva de salas.
- Inscripción hecha por el propio ciudadano.

---

## Paso 0 — Verificación

1. `git pull`; suites de Centro, Supervisión e Intervención. Anota fallos previos.
2. Lista las actividades y sesiones existentes (staging y demo) y si alguna sesión tiene prescripciones.
3. Revisa cómo se crea hoy una prescripción a una sesión (`PrescribirRecursoModal`, `PrescripcionService::crear()`) y si controla el aforo. Informa.

---

## Paso 1 — Modelo

`inscripciones_sesion`:

| Campo | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `sesion_actividad_id` | FK | |
| `ciudadano_id` | FK | Unique `(sesion_actividad_id, ciudadano_id)` entre las no dadas de baja |
| `via` | enum | `libre` / `prescripcion` |
| `prescripcion_id` | FK nullable | Obligatoria si `via = prescripcion` |
| `estado` | enum | `inscrita` / `baja` |
| `inscrita_por_id` | FK users | |
| `fecha_baja` / `motivo_baja` / `baja_por_id` | nullable | Motivo obligatorio en la baja |
| timestamps | | |

Sin borrado: la baja es lógica.

`Actividad` / `SesionActividad`: métodos `aforoEfectivo()` y `aforoPrescripcionEfectivo()` (los de la sesión sobrescriben los de la actividad), `plazasLibres(string $via)` (libre: aforo total − aforo de prescripción − inscritas libres; prescripción: aforo de prescripción − inscritas por prescripción; en `libre` o `prescripcion` puros, todo el aforo es de esa vía; aforo nulo = sin límite).

## Paso 2 — Servicio `InscripcionActividadService`

- `inscribir(SesionActividad, Ciudadano, User $actor): InscripcionSesion` — vía `libre`: la actividad debe admitir acceso libre, la sesión estar `programada` y en el futuro, el actor adscrito al centro (RN-15), haber plaza (con bloqueo de fila para evitar sobreinscripción en concurrencia), y F6-4.
- `inscribirEnActividad(Actividad, Ciudadano, User): ResultadoInscripcion` — F6-1: inscribe en las futuras con plaza y devuelve las no inscritas con su motivo.
- Prescripción a una sesión: al crear la prescripción (`PrescripcionService`), si hay plaza de prescripción se crea la inscripción con `via = prescripcion` y la prescripción pasa a `activa`; si no hay, se rechaza (F6-3). Ajusta `PrescripcionService` sin cambiar el flujo de plazas.
- `darDeBaja(InscripcionSesion, string $motivo, User)` — libera la plaza. La baja de una inscripción por prescripción cancela la prescripción con el mismo motivo.
- Al inscribir (cualquier vía) se crea, si la actividad está vinculada a una prestación del catálogo, una `PrestacionPersona` activa (fase 3 bis); si no hay prestación vinculada, no. Añade `actividades.prestacion_id` (FK nullable) para ello.

## Paso 3 — Ciclo de vida de actividades y sesiones (F6-5)

1. `ActividadesPage`: acción «Dar de baja» (fecha y motivo); las actividades de baja salen del listado por defecto (filtro para verlas) y no admiten inscripciones.
2. Sesiones: «Cancelar» (motivo) en lugar de «Eliminar» cuando tenga inscripciones o prescripciones; «Eliminar» solo sin ninguna. Al cancelar: las inscripciones se dan de baja con el motivo «Sesión cancelada» y se avisa por `AlertaService` a quien inscribió o prescribió a cada persona.
3. «Marcar como celebrada» (sin control de asistencia, F6-6).
4. Vista de inscritos por sesión: persona (con la regla de personas protegidas: identidad solo para quien la gestiona, RN-17), vía, quién inscribió y cuándo; aforo ocupado / total por vía. Exportable no.

## Paso 4 — Interfaz de inscripción

1. **Ficha de la persona** (caja de herramientas): «Inscribir en actividad» para el personal adscrito al centro: selector de actividades activas del centro activo con acceso libre o mixto, sesiones con plazas libres; opción de inscribir en todas las futuras.
2. **Plan** (prescripción): el modal de prescripción a sesión existente muestra las plazas de prescripción disponibles.
3. **Bloque «Actividades»** en la ficha: inscripciones vigentes y pasadas (actividad, centro, sesiones, vía) con «Dar de baja».
4. `consulta_basica` puede inscribir en acceso libre (RN-15, si está adscrita) y ve el bloque «Actividades» de la persona, sin la vía de prescripción ni el plan.

## Paso 5 — Permisos

`InscripcionSesionPolicy`: inscribir (libre) y dar de baja: adscritos al centro de la actividad con `intervencion` o `consulta_basica`, y supervisión del centro. Prescribir: el flujo de prescripción existente. Ver inscritos: personal del centro de la actividad. Todo en el servidor.

---

## Cierre

1. Suites de Centro, Supervisión, Intervención y Ciudadanía; después la suite completa. Sin fallos nuevos. `npm run build` y `ui:auditar` en verde.
2. Documentación: `modulo-centros.md` (§2.6 y §4.9 con la inscripción, la cancelación y el aforo; nueva tabla `inscripciones_sesion`; `actividades.prestacion_id`), `modulo-solicitudes-expedientes.md` §8 (lista de espera de actividades pendiente, junto a la de plazas).
3. CHANGELOG, BACKLOG (cierra «Actividades grupales — gestión completa»; nueva entrada «Lista de espera de actividades»), SESSION, `CLAUDE.md` §6. Push y despliegue en verde.

## Criterios de finalización

1. TF-ACT-01 a 26 en verde, con negativos comprobados.
2. Ninguna sesión con inscripciones se puede borrar; ninguna inscripción supera el aforo, tampoco en concurrencia.
3. Documentación al día y despliegue en verde.

---

# Tests — TF-ACT-01 a TF-ACT-26

**Convenciones:** PHPUnit con `#[Test]`, PostgreSQL (`vida_testing`). Ubicación: `Modules/Centro/tests/Feature/Actividades/` (`AforoTest`, `InscripcionLibreTest`, `InscripcionPrescripcionTest`, `CicloVidaActividadTest`, `PermisosInscripcionTest`) y `Modules/Intervencion/tests/Feature/Actividades/InscripcionFichaTest.php`. `Carbon::setTestNow('2026-11-02')`. **[negativo]** = debe fallar sin la protección.

**Datos:** `CircuitoTestSetup` (fase 3) y:
- `$tallerCiam` en `$ciamVallecas`: `mixta`, aforo total 10, aforo de prescripción 4, `requiere_inscripcion_centro = true`, tres sesiones programadas (9, 16 y 23 de noviembre) y una pasada (26 de octubre). Prestación vinculada `$pTaller`.
- `$charlaCss` en `$cssNorte`: `libre`, aforo nulo, una sesión.
- `$auxVallecas` (`consulta_basica`, adscrita a `$uoCiamVallecas`); `$psicologaVallecas`; `$directoraVallecas`; `$tsNorte` (otro centro, con el PISO de `$ana`).
- `$ana` no inscrita en `$ciamVallecas`.

## Grupo A — Aforo

**TF-ACT-01 — Aforo efectivo**: la sesión del 16 con aforo propio 6 usa 6; las demás, 10. `plazasLibres('libre')` = 6 y `plazasLibres('prescripcion')` = 4 en una sesión vacía de aforo 10.

**TF-ACT-02 — Sin límite**: en `$charlaCss` (aforo nulo) se pueden inscribir 50 personas.

**TF-ACT-03 — Aforo completo** **[negativo]**: con las 6 plazas libres ocupadas, una séptima inscripción libre → rechazada con mensaje; por prescripción aún hay plazas.

**TF-ACT-04 — Concurrencia** **[negativo]**: con una plaza libre, dos inscripciones simultáneas (dos conexiones o transacciones) → solo una se crea.

## Grupo B — Inscripción libre

**TF-ACT-05 — Inscribir**: `$psicologaVallecas` inscribe a una persona ya inscrita en el centro en la sesión del 9 → inscripción `libre`, `inscrita`, con quién y cuándo.

**TF-ACT-06 — Inscripción en el centro (F6-4)**: inscribir a `$ana` en `$tallerCiam` ofrece primero inscribirla en `$ciamVallecas`; aceptado → `InscripcionCentro` activa e inscripción en la sesión; rechazado → no se inscribe.

**TF-ACT-07 — Todas las futuras (F6-1)**: «Inscribir en la actividad» inscribe en las sesiones del 9, 16 y 23, no en la pasada; si la del 16 está llena, informa de ella y se inscribe en las otras dos.

**TF-ACT-08 — Sesión pasada, cancelada o celebrada** **[negativo]**: inscribir en la del 26 de octubre, en una cancelada o en una celebrada → rechazado.

**TF-ACT-09 — Sin duplicados**: inscribir dos veces a la misma persona en la misma sesión → rechazado; tras darla de baja, se puede volver a inscribir.

**TF-ACT-10 — Actividad solo de prescripción**: una actividad `prescripcion` no admite inscripción libre (acción ausente y rechazada en el servidor).

**TF-ACT-11 — Baja de una inscripción**: dar de baja con motivo libera la plaza; sin motivo → rechazado; la inscripción queda con estado `baja`, sin borrarse.

**TF-ACT-12 — Prestación de la persona**: inscribir en `$tallerCiam` crea una `PrestacionPersona` activa de `$pTaller`; en `$charlaCss` (sin prestación), no.

## Grupo C — Prescripción

**TF-ACT-13 — Prescribir desde el plan**: `$tsNorte` prescribe a `$ana` la sesión del 9 desde su PISO → prescripción `activa` e inscripción `prescripcion` enlazada; consume plaza de prescripción.

**TF-ACT-14 — Aforo de prescripción completo** **[negativo]**: con las 4 plazas de prescripción ocupadas, una quinta → rechazada (F6-3), aunque queden plazas libres.

**TF-ACT-15 — Baja de una inscripción por prescripción**: darla de baja cancela la prescripción con el mismo motivo.

**TF-ACT-16 — Prescripción desde otro centro**: `$tsNorte` (adscrito a `$cssNorte`) puede prescribir una sesión de `$ciamVallecas` desde el plan; no puede inscribir por acceso libre en ella (RN-15).

## Grupo D — Ciclo de vida

**TF-ACT-17 — Baja de actividad**: `$directoraVallecas` da de baja `$tallerCiam` → `activa = false`, `fecha_baja`; sale del listado por defecto; no admite inscripciones; sigue existiendo.

**TF-ACT-18 — Cancelar sesión con inscritos** **[negativo]**: la sesión del 9 con tres inscritos no se puede eliminar; se cancela con motivo → `cancelada`, inscripciones dadas de baja con «Sesión cancelada», alertas a quien inscribió cada una.

**TF-ACT-19 — Eliminar sesión vacía**: una sesión sin inscripciones ni prescripciones se puede eliminar.

**TF-ACT-20 — Marcar celebrada**: una sesión pasada se marca como celebrada; no se registra asistencia.

**TF-ACT-21 — Vista de inscritos**: la dirección ve los inscritos de la sesión del 9 con vía, quién inscribió y cuándo, y el aforo ocupado por vía.

## Grupo E — Permisos e interfaz

**TF-ACT-22 — Sin adscripción no se inscribe** **[negativo]**: `$tsNorte` y `$coordIgualdad` intentan inscribir por acceso libre en `$tallerCiam` (acción Livewire y servicio) → rechazado.

**TF-ACT-23 — `consulta_basica` inscribe**: `$auxVallecas` inscribe en una sesión de acceso libre de su centro y ve el bloque «Actividades» de la persona, sin la vía de prescripción ni el plan.

**TF-ACT-24 — Persona protegida en la lista de inscritos**: con `$marta` inscrita, un supervisor que llega a `$ciamVallecas` por una UO superior ve «Persona con protección especial» en la lista; la dirección del centro la ve con identidad.

**TF-ACT-25 — Bloque «Actividades» de la ficha**: muestra las inscripciones vigentes y pasadas de la persona con actividad, centro, sesiones y vía.

**TF-ACT-26 — Selector del centro activo**: el selector de «Inscribir en actividad» solo ofrece actividades activas del centro activo del usuario con acceso libre o mixto y sesiones con plaza.

## Tabla de estado

| Grupo | Tests | Estado |
|---|---|---|
| A — Aforo | TF-ACT-01..04 | |
| B — Inscripción libre | TF-ACT-05..12 | |
| C — Prescripción | TF-ACT-13..16 | |
| D — Ciclo de vida | TF-ACT-17..21 | |
| E — Permisos e interfaz | TF-ACT-22..26 | |
