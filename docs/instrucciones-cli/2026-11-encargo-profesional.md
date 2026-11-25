# Instrucciones CLI — Encargo profesional y superficie de `consulta_profesional`

**Diseño:** `docs/rol-consulta-profesional.md` (cerrado el 23 nov 2026). Este documento lo concreta y resuelve sus pendientes (§11) con las decisiones del 25 nov 2026.
**Requisito previo:** fase 3 (derivaciones, `DerivacionService`, bandeja de derivaciones) y R4 (superficie operativa única, `Superficie`, `DestinoInicial`, `RegistroMenu`).
**Tests:** TF-ENC-01 a TF-ENC-40 (al final de este documento).

**Alcance:**
- **A.** El encargo como modalidad de derivación: modelo, estados, servicio e historial.
- **B.** Interfaz del TSR (crear desde el plan principal o la Historia Social) y bandeja del centro de destino.
- **C.** Superficie «Mis derivaciones», ámbito del rol (ficha, buscador) y permisos.
- **D.** Documentos del encargo y personas protegidas.
- **E.** Agenda y citas del profesional de encargo.
- **F.** Mensajes desde el encargo y centro activo por la hora.

**Regla de trabajo:** si el código contradice estas instrucciones o aparece un caso no previsto, **detente y pregunta**. Lo marcado como *fuera de alcance* no se toca.

**Antes de empezar:** `CLAUDE.md`, `SESSION.md`, `docs/rol-consulta-profesional.md`, `docs/instrucciones-cli/2026-11-fase3-circuito-intervencion.md` (bloque B), `docs/modulo-usuarios-permisos.md` (rol 6, §2.7 bis), `docs/instrucciones-cli/2026-11-tipo-centro-addenda-d1.md` (RN-15 a RN-19), `docs/modulo-documentos.md` §2.1, `docs/design-system/SKILL.md` y `docs/instrucciones-cli/2026-09-bootstrap-unico.md`.

---

## Hechos que no hay que redescubrir

- `derivaciones` (fase 3) exige `plan_origen_id`, `prestacion_id` y `tipo_plan_id`; estados `pendiente/aceptada/rechazada/anulada` con `CHECK`; `derivacion_eventos` es inmutable por trigger. `DerivacionService` es el único punto de escritura; `aceptar()` abre siempre un plan especializado y exige `plan.editar` al responsable. `resuelveEn()` = `supervision` adscrita al centro.
- `Modules/Supervision/.../DerivacionesPage` es la bandeja del centro de destino; enmascara a las personas protegidas salvo para quien resuelve (RN-19).
- `FichaCiudadanoPage::mount()` exige uno de los roles `intervencion`, `tramitacion`, `consulta_basica`, `supervision`: **`consulta_profesional` no abre ninguna ficha** (causa del 403 de `edu.css` en staging).
- `CiudadanoPolicy::view()`: con `ciudadano.leer`, cualquier persona no protegida es visible (nivel 2, consulta libre). `consulta_profesional` tiene hoy `ciudadano.leer`, `historia.leer`, `apunte.leer`, `apunte.leer_propio`, `apunte.crear`, `plan.leer` y `documento.ver_carpeta`: todo eso choca con el diseño y se retira (C1).
- Al no tener `historia.leer`, el acceso del equipo del plan (F3-3) no da al rol lectura de la Historia: queda resuelto «por permiso», como pide el diseño (§10).
- `DocumentoPolicy` admite vías adicionales etiquetadas (`DocumentoPolicy::ETIQUETA_ACCESOS`, ejemplo: `AccesoDocumentosTramitacion`). `documento_vinculos` es polimórfico.
- `TipoApunte` no tiene `CHECK` en base de datos.
- El perfil horario admite franjas por día y un perfil por centro; `CentroDeUsuario::centroActivo()` elige el primer perfil con franjas hoy, sin mirar la hora.
- `Superficie` tiene `Supervision`, `Agenda`, `Tramitacion`, `Citacion`, `Buscador`; prioridad Supervisión → Agenda → Tramitación → Citación.

---

## Decisiones

Diseño del 23 nov 2026 y pendientes resueltos por el desarrollador el 25 nov 2026.

| Id | Decisión |
|---|---|
| EP-1 | **El encargo es una modalidad de derivación** (`derivaciones.modalidad = encargo`), sin plan especializado ni prestación ni tipo de plan. Reutiliza servicio, eventos, alertas y bandeja |
| EP-2 | **Origen:** el TSR crea el encargo desde el **plan principal** activo (opcionalmente desde un objetivo) **o desde la Historia Social** sin plan. Quien lo crea debe poder editar el plan principal o gestionar la historia (`update`), y RN-15 en el centro del plan o de la historia |
| EP-3 | **Destino: solo centros.** La dirección o la supervisión adscrita al centro de destino lo acepta asignando un profesional adscrito al centro con `derivacion.registrar_resultado`, o lo rechaza con motivo |
| EP-4 | **Estados:** `pendiente` → `aceptada` (en curso) → `cerrada` con resultado; `rechazada` y `anulada` como hoy. Solo el profesional asignado cierra, con resultado obligatorio y RN-15 en el centro del encargo |
| EP-5 | **Resultado:** al cerrar se crea un apunte `encargo` en la Historia Social, visible para los profesionales (intervención); alerta al TSR que encargó. El profesional del encargo no lee apuntes |
| EP-6 | **Ámbito del rol:** sus ciudadanos asignados = personas de los encargos en los que es o ha sido el profesional asignado, con histórico. Ficha en lectura (identificación, contacto, unidad de convivencia, relaciones); el buscador solo devuelve esas personas |
| EP-7 | **Personas protegidas:** identidad visible mientras el encargo está **aceptado**; cerrado, aparece **enmascarada** en su histórico con «Solicitar acceso» (RN-18) |
| EP-8 | **Documentos:** ve los que el TSR vincula al encargo y los que sube él sobre las personas de sus encargos; sube sin el límite del día; van a la carpeta de la persona y los ve quien tiene acceso a ella |
| EP-9 | **Citas: ambos.** El propio profesional cita desde el encargo y la ventanilla del centro también, en los huecos de su agenda |
| EP-10 | **Página de inicio «Mis derivaciones»**, después de Agenda en la prioridad; Mensajes en el menú |

---

## Fuera de alcance

- Encargos a servicios (`Servicio`) u otros sistemas (sanidad, justicia).
- Reasignar un encargo aceptado a otro profesional (si hace falta, se anula y se encarga de nuevo: BACKLOG).
- Informes generados por el profesional de encargo (solo sube documentos).

---

## Paso 0 — Verificación

1. `git pull`, revisión de la suite nocturna y los ficheros de test de derivaciones (`Modules/Intervencion/tests/Feature/Circuito/DerivacionTest.php`, `BandejaDerivacionesTest.php`).
2. Lista los usuarios con `consulta_profesional` en la BD compartida y sus adscripciones (para el CHANGELOG).
3. Confirma que no hay filas en `derivaciones` con datos que la migración A1 pueda romper.

## Bloque A — Modelo y servicio

### A1. Migración
- `derivaciones.modalidad` (`plan_especializado` | `encargo`, por defecto `plan_especializado`, `CHECK`).
- `plan_origen_id`, `prestacion_id`, `tipo_plan_id` pasan a nullable; `CHECK`: en `plan_especializado` son obligatorios `prestacion_id`, `tipo_plan_id` y `plan_origen_id`.
- `estado` admite `cerrada`.
- `resultado` (text, cifrado en el modelo), `cerrada_en`, `cerrada_por_id`.

### A2. Modelo y enum
- `ModalidadDerivacion` (enum: el código decide por él). `EstadoDerivacion::Cerrada` con etiqueta y tono.
- `Derivacion`: `esEncargo()`, scopes `encargos()` y `deProfesional(User)`.
- `TipoApunte::Encargo`.

### A3. Servicio `EncargoService` (módulo Intervención)
- `encargar(HistoriaSocial, ?PlanDeIntervencion $principal, ?PlanObjetivo, Centro $destino, string $motivo, list<int> $documentoIds, User $actor)`: EP-2; no puede haber otro encargo `pendiente` o `aceptada` de la misma historia al mismo centro; vincula los documentos (deben ser de la carpeta de la persona y visibles para el actor); evento, apunte `derivacion` y alerta a la supervisión del destino (sin nombrar a la persona).
- `aceptar(Derivacion, User $profesional, User $actor)`: `DerivacionService::resuelveEn()`; profesional adscrito al destino con `derivacion.registrar_resultado`. Evento, alertas al TSR y al profesional.
- `cerrar(Derivacion, string $resultado, User $actor)`: EP-4 y EP-5.
- `rechazar` y `anular`: los de `DerivacionService` valen para las dos modalidades (`aceptar` de `DerivacionService` rechaza un encargo y viceversa).

## Bloque B — Interfaz del TSR y bandeja

1. Componente `EncargosProfesionales` (Intervención): lista de encargos de la historia (estado, centro, profesional, resultado si está cerrada, historial) y modal «Encargo profesional»: centro de destino (centros con algún profesional con `derivacion.registrar_resultado`), motivo y contexto, documentos de la carpeta de la persona para adjuntar. Se incrusta en la Historia Social (herramienta «Encargo profesional») y en la sección de derivaciones del plan principal (con objetivo opcional).
2. `DerivacionesPage`: columna/tipo «Encargo profesional»; al aceptar un encargo, la lista de profesionales es la de `derivacion.registrar_resultado`.

## Bloque C — Superficie, ámbito y permisos

1. Permisos (`PermisosSeeder`, `RolesSeeder` y migración de datos): nuevos `derivacion.ver_asignadas`, `derivacion.registrar_resultado`, `ciudadano.leer_por_encargo`. `consulta_profesional` queda con `ciudadano.ver_ficha`, `ciudadano.leer_por_encargo`, `derivacion.ver_asignadas`, `derivacion.registrar_resultado`, `documento.subir` (y, en el bloque E, `agenda.ver_propia` y los de citas). Pierde `ciudadano.leer`, `historia.leer`, `apunte.*`, `plan.leer` y `documento.ver_carpeta`.
2. `EncargosDeUsuario` (servicio): ids de personas de sus encargos (aceptados o cerrados; solo aceptados para protegidas).
3. `CiudadanoPolicy::view`: con `ciudadano.leer_por_encargo`, ve a las personas de `EncargosDeUsuario` (EP-6, EP-7). No da `update`.
4. `FichaCiudadanoPage`: la abre también `consulta_profesional` (la policy decide); bloques en lectura según EP-6.
5. Buscador: ruta propia accesible con `ciudadano.buscar` o `derivacion.ver_asignadas`; sin `ciudadano.buscar`, los resultados se limitan a `EncargosDeUsuario`.
6. `Superficie::Encargos` («Mis derivaciones», `derivacion.ver_asignadas`), prioridad Supervisión → Agenda → Encargos → Tramitación → Citación; entrada en el menú «Mi trabajo».
7. Páginas `MisEncargosPage` (activos e histórico: persona, centro, TSR, motivo, estado, antigüedad) y `EncargoPage` (detalle, documentos del TSR, subir documento, registrar resultado y cerrar). Personas protegidas según EP-7.

## Bloque D — Documentos y protegidas

1. `AccesoDocumentosEncargo` (vía adicional de `DocumentoPolicy`): documentos vinculados a un encargo del usuario y documentos subidos por él vinculados a una persona de sus encargos.
2. Subida desde `EncargoPage`: el documento se vincula a la persona y al encargo.
3. Histórico con persona protegida cerrada: nombre enmascarado (`PresentacionPersona`), sin enlace, con «Solicitar acceso».

## Bloque E — Agenda y citas

1. `consulta_profesional` recibe `agenda.ver_propia`, `citas.solicitar` y `citas.atender`. «Mis casos» y «Recursos» dejan de mostrarse por `agenda.ver_propia` sola: exigen además `plan.leer`.
2. Cita desde el encargo (tipo de cita y huecos del profesional en el centro del encargo). La ventanilla del centro cita en sus huecos con la bandeja de citación existente.
3. Atender una cita de una persona de un encargo lleva al encargo, no a la Historia Social.

## Bloque F — Mensajes y centro activo

1. Mensajes en el menú para `derivacion.ver_asignadas`; contexto `encargo` en el panel de redacción, con el TSR como destinatario sugerido.
2. `centroActivo()`: con varios perfiles con franjas hoy, el de la franja que contiene la hora actual.

---

## Cierre

1. Ficheros de test tocados; suite completa en GitHub tras el último push.
2. `npm run build` y `php artisan ui:auditar` en verde.
3. Documentación: `rol-consulta-profesional.md` (estado: implementado; §11 resuelto), `modulo-usuarios-permisos.md` (rol 6), `modulo-intervencion.md` (sección Derivación: modalidad encargo), `modulo-documentos.md`, addenda RN-17 (encargo aceptado), `CLAUDE.md` §6.
4. CHANGELOG, BACKLOG, SESSION. Push.

---

# Tests — TF-ENC-01 a TF-ENC-40

**Ubicación:** `Modules/Intervencion/tests/Feature/Encargo/`. Datos: `CircuitoTestSetup` más `$abogadaVallecas` (`consulta_profesional`, adscrita a `$ciamVallecas`) y `$educadorMulti` (`consulta_profesional`, adscrito a `$cssNorte` y `$ciamVallecas`). **[negativo]**: debe fallar sin la protección.

## Grupo A — Modelo y servicio
- **TF-ENC-01** Encargar desde la Historia Social: `$tsNorte` encarga para `$ana` a `$ciamVallecas` → `modalidad = encargo`, `pendiente`, sin plan ni prestación; evento, apunte `derivacion`, alerta a la supervisión del destino sin el nombre de la persona.
- **TF-ENC-02** Encargar desde el plan principal y desde un objetivo: guarda `plan_origen_id` y `plan_objetivo_id`.
- **TF-ENC-03** [negativo] Quién encarga: un usuario que no gestiona la historia ni edita el plan principal, o sin centro (RN-15) → rechazado.
- **TF-ENC-04** Sin duplicados: otro encargo pendiente o aceptado de la misma historia al mismo centro → rechazado; a otro centro → permitido; tras cerrarse → permitido.
- **TF-ENC-05** Motivo obligatorio.
- **TF-ENC-06** Aceptar: `$directoraVallecas` asigna a `$abogadaVallecas` → `aceptada`, sin plan especializado, `profesional_responsable_id`; alertas al TSR y a la abogada.
- **TF-ENC-07** [negativo] Quién acepta y a quién: la abogada, el TSR o la supervisión de UO superior no aceptan; asignar a alguien no adscrito o sin `derivacion.registrar_resultado` → rechazado.
- **TF-ENC-08** [negativo] Las modalidades no se cruzan: `DerivacionService::aceptar()` sobre un encargo y `EncargoService::aceptar()` sobre una derivación de plan → rechazado.
- **TF-ENC-09** Cerrar: la abogada cierra con resultado → `cerrada`, `resultado`, `cerrada_en`; apunte `encargo` visible para profesionales; alerta al TSR.
- **TF-ENC-10** [negativo] Quién cierra: otro profesional, sin resultado, un encargo pendiente o la abogada sin adscripción al centro (RN-15) → rechazado.
- **TF-ENC-11** Rechazar y anular un encargo pendiente funcionan como en la fase 3; anular uno aceptado → rechazado.
- **TF-ENC-12** [negativo] El `CHECK` exige plan, prestación y tipo en las derivaciones de plan especializado.
- **TF-ENC-13** Documentos al encargar: se vinculan los de la carpeta de la persona; uno de otra persona → rechazado.

## Grupo B — Interfaz del TSR y bandeja
- **TF-ENC-14** Herramienta «Encargo profesional» en la Historia Social: crea el encargo y lo lista con su estado.
- **TF-ENC-15** El plan principal ofrece el encargo y lo lista en su sección.
- **TF-ENC-16** Centros de destino: solo los que tienen algún profesional con `derivacion.registrar_resultado`.
- **TF-ENC-17** Bandeja: el encargo aparece como «Encargo profesional»; al aceptar, los profesionales ofrecidos son los de encargo del centro.
- **TF-ENC-18** El TSR ve el resultado del encargo cerrado en la lista y en el timeline.

## Grupo C — Superficie, ámbito y permisos
- **TF-ENC-19** Permisos del rol tras la migración: tiene los del bloque C1 y no tiene `ciudadano.leer`, `historia.leer`, `apunte.leer`, `plan.leer` ni `documento.ver_carpeta`.
- **TF-ENC-20** Página de inicio: `consulta_profesional` entra en «Mis derivaciones»; con `intervencion` además, en Agenda.
- **TF-ENC-21** Menú: «Mis derivaciones» y «Buscador»; sin «Mis casos», «Alta» ni Supervisión.
- **TF-ENC-22** Mis derivaciones: activos e histórico propios; no los de otro profesional.
- **TF-ENC-23** [negativo] Ficha: la abogada abre la de `$ana` (encargo aceptado o cerrado); la de otra persona → 403 auditado.
- **TF-ENC-24** [negativo] Ficha en lectura: sin edición, sin Historia Social, sin prestaciones; `update` denegado.
- **TF-ENC-25** [negativo] Sin Historia Social: la abogada no abre la Historia ni el plan de `$ana`, aunque esté adscrita al centro del plan (F3-3 por permiso).
- **TF-ENC-26** Buscador: solo devuelve personas de sus encargos.
- **TF-ENC-27** Pendiente no da acceso: con el encargo aún pendiente, la abogada no ve a `$ana`.
- **TF-ENC-28** EncargoPage: detalle con motivo, TSR, documentos adjuntos; registrar resultado cierra el encargo.
- **TF-ENC-29** [negativo] EncargoPage de otro profesional → 403.

## Grupo D — Documentos y protegidas
- **TF-ENC-30** [negativo] Documentos: la abogada ve los vinculados al encargo y los suyos; no otros de la carpeta de `$ana`.
- **TF-ENC-31** Sube un documento desde el encargo: queda en la carpeta de `$ana` y vinculado al encargo; el TSR lo ve; la abogada lo sigue viendo otro día.
- **TF-ENC-32** [negativo] Protegida con encargo aceptado: la abogada ve a `$marta` con identidad y su ficha.
- **TF-ENC-33** [negativo] Protegida con encargo cerrado: en el histórico aparece «Persona con protección especial», sin enlace; la ficha → 403.

## Grupo E — Agenda y citas
- **TF-ENC-34** `consulta_profesional` ve su agenda; «Mis casos» y «Recursos» no aparecen.
- **TF-ENC-35** Cita desde el encargo en un hueco suyo del centro del encargo.
- **TF-ENC-36** La ventanilla del centro cita a la persona en un hueco del profesional.
- **TF-ENC-37** Atender la cita lleva al encargo.

## Grupo F — Mensajes y centro activo
- **TF-ENC-38** Mensajes en el menú; «Escribir al TSR» desde el encargo abre el panel con el TSR sugerido y el contexto del encargo.
- **TF-ENC-39** Centro activo por la hora: con perfil de mañana en un centro y de tarde en otro, a las 10:00 el de la mañana y a las 17:00 el de la tarde.
- **TF-ENC-40** Sin franja a esa hora, el primer perfil con franjas hoy (comportamiento anterior).

## Tabla de estado

| Grupo | Tests | Estado |
|---|---|---|
| A — Modelo y servicio | TF-ENC-01..13 | Hecho (25 nov) |
| B — Interfaz del TSR y bandeja | TF-ENC-14..18 | Hecho (25 nov), con 14 bis y 15 bis |
| C — Superficie, ámbito y permisos | TF-ENC-19..29 | Hecho (25 nov), con 19 bis y 20 bis |
| D — Documentos y protegidas | TF-ENC-30..33 | Hecho (25 nov), con 31 bis y 33 bis |
| E — Agenda y citas | TF-ENC-34..37 | Pendiente |
| F — Mensajes y centro activo | TF-ENC-38..40 | Pendiente |
