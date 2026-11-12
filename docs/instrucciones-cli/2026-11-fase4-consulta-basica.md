# Instrucciones CLI — Fase 4: atención administrativa (`consulta_basica`) y delta de citas

**Plan:** `docs/plan-cierre-funcional-2026-11.md`, fase 4.
**Diseño funcional:** `docs/roles-consulta-basica-tramitacion.md` §2, §3 y §5; `docs/modulo-citas.md` §6 bis y §7.
**Requisito previo:** fases 2, 3 y 3 bis terminadas (usa `CentroDeUsuario`, `AmbitoUsuario`, RN-17 ampliada y el bloque «Prestaciones» de la ficha, que `consulta_basica` no debe ver).
**Tests:** TF-CBA-01 a TF-CBA-30 (al final).

**Regla de trabajo:** si el código contradice estas instrucciones o aparece un caso no previsto, **detente y pregunta**. Lo marcado como *fuera de alcance* no se toca.

**Antes de empezar:** `CLAUDE.md`, `SESSION.md`, los dos documentos de diseño citados, `docs/modulo-ciudadania.md`, `docs/modulo-documentos.md` §2.1 y §4, `docs/modulo-usuarios-permisos.md` (ROL 7), la addenda de Tipo de Centro (RN-15 a RN-19), `docs/design-system/SKILL.md` (regla del banner de protegido ya corregida) y `docs/instrucciones-cli/2026-09-bootstrap-unico.md`.

---

## Hechos que no hay que redescubrir

- `RolesSeeder` (ROL 7) da a `consulta_basica`: `ciudadano.ver_ficha`, `ciudadano.ver_datos_contacto`, `ciudadano.leer`, `ciudadano.editar`, `atencion.crear`, `atencion.leer`, `citas.solicitar`, `citas.gestionar`. **No tiene** `ciudadano.crear` ni permisos de documentos.
- `FichaCiudadanoPage`: `puedeEditar()` (línea ~253) incluye `consulta_basica`; `puedeEditarRelaciones()` (línea ~400) solo `intervencion` y `tramitacion`. Usa citas solo para vincular el registro de atención (`citaVinculable()`) y para «Dar cita» (`puedeDarCita()`). No tiene bloque de historial de citas ni de estado de la Historia Social.
- **RN-05 ter de citas ya se cumple** (`CitaPolicy::citar()`, `gestionaAjena()`, `SolicitudCitaPolicy`, `CitacionService` con `AlcanceCentro::incluye()`). No requiere cambios; tras la fase 2 debe seguir funcionando con `CentroDeUsuario`.
- La custodia v2 tiene `Documento`, `DocumentoVersion` y `DocumentoVinculo` (polimórfico, con `activo`). `EstadoDocumento`: `pendiente_validacion`, `vigente`, `rechazado`, `destruido`. **No hay retirada lógica** con quién, cuándo y motivo. «Nueva versión» y «Desvincular» de `DocumentosCiudadano` solo aparecen a quien pasa `CiudadanoPolicy::update`.
- `CiudadanoPolicy::view`: nivel 1 por ámbito; fuera de ámbito, consulta externa con la regla de colectivo protegido. Tras la fase 2, las personas protegidas solo con `gestionaProtegido()` o acceso concedido.

---

## Decisión de coherencia (pendiente de confirmar)

⚑ **F4-1 — Personas protegidas y `consulta_basica`.** `roles-consulta-basica-tramitacion.md` §3.2 dice que `consulta_basica` ve los datos objetivos de cualquier persona protegida. La decisión D4 (11 nov) dice que nadie de fuera del centro ve la identidad de una persona protegida sin acceso concedido. Se aplica D4, que es posterior: `consulta_basica` ve los datos objetivos de una persona protegida **solo si está adscrita al centro de su historia o de un plan activo suyo** (`gestionaProtegido()`). Desde otro centro, la ve como cualquier otra persona protegida fuera de ámbito: «Persona con protección especial», sin datos, y puede orientar a la persona a su centro. **Si no consta en `SESSION.md` la confirmación del Delegado Digital, pregunta antes del paso 2.**

---

## Fuera de alcance

- El resto del rol `tramitacion` (traslados y fusiones). Lo de solicitudes ya está en la fase 3 bis; sus permisos `citas.*` se añaden aquí (paso 5).
- Cambiar RN-05 ter de citas.
- Portal del ciudadano.

---

## Paso 0 — Verificación

1. `git pull`; suites de Ciudadanía, Agenda (citas), Documentos, Usuarios y `tests/Feature/Acceso`. Anota fallos previos.
2. Lista los bloques de `FichaCiudadanoPage` y qué condición de rol o permiso decide cada uno (fichero y línea).
3. Lista los lugares donde se muestra el banner de colectivo protegido.
4. Comprueba cómo se sube hoy un documento (`DocumentosCiudadano`, `IngestaDocumentoService`) y qué guarda sobre quién lo subió (`created_by` en `Documento`, autor en `DocumentoVersion`).

---

## Paso 1 — Permisos

1. Permisos nuevos (seeder idempotente): `historia.ver_estado`, `cita.ver_historial`, `documento.subir`, `documento.gestionar_propios`.
2. `RolesSeeder`:
   - `consulta_basica`: añade `ciudadano.crear`, `ciudadano.editar_relaciones` (crea el permiso si no existe; si las relaciones se deciden hoy por `ciudadano.editar`, unifícalo y explícalo), `historia.ver_estado`, `cita.ver_historial`, `documento.subir`, `documento.gestionar_propios`.
   - `tramitacion`: `historia.ver_estado`, `cita.ver_historial`, `citas.solicitar`, `citas.gestionar`, `documento.subir`, `documento.gestionar_propios`.
   - `intervencion` y `supervision`: `historia.ver_estado` y `cita.ver_historial`.
3. Las decisiones de interfaz pasan a preguntar por permisos o policies, no por nombre de rol, en todo lo que toques.

---

## Paso 2 — Ficha del ciudadano por rol

Según `roles-consulta-basica-tramitacion.md` §3.1, con F4-1:

| Bloque | `consulta_basica` / `tramitacion` |
|---|---|
| Identificación y contacto (Capa 1) | Lectura y edición, en su UO (la operación se limita a su UO; la consulta no) |
| Unidad de convivencia | Lectura |
| Relaciones | Lectura y edición (alta, edición, cierre) |
| **Estado de la Historia Social** (nuevo) | Si existe: estado (abierta, en seguimiento, cerrada) y profesional de referencia vigente. Nada más |
| **Historial de citas** (nuevo, paso 3) | Completo |
| Documentos | Solo los propios del día (paso 4) |
| Prestaciones (fase 3 bis) | No se muestra |
| Situación social (Capa 2), accesos recientes | No se muestra |
| Banner de colectivo protegido | No se muestra nunca (`design-system/SKILL.md`) |

1. Componente o bloque «Historia Social» para `historia.ver_estado`: estado y referencia (`AsignacionReferenciaService::vigente()`). Sin número, fechas, planes ni enlace a la historia.
2. Edición de relaciones con `ciudadano.editar_relaciones` y `CiudadanoPolicy` (la regla de UO de la operación aplica).
3. Alta de ciudadanos: el flujo existente (`AltaCiudadano`) con control de duplicados, disponible para `consulta_basica`.
4. Ámbito de UO: la consulta de la ficha está disponible para cualquier persona no protegida; la edición, solo en su UO (`CiudadanoPolicy::update`).
5. Personas protegidas: F4-1.
6. Unidad de convivencia: solo lectura para estos roles (su composición es de Intervención, `modulo-ciudadania.md` §3.4).

---

## Paso 3 — Historial de citas en la ficha (§6 bis de citas)

1. Bloque «Citas» para `cita.ver_historial`, en `FichaCiudadanoPage`: citas pasadas y programadas de la persona en **cualquier centro**.
2. Por cita: fecha y hora, **etiqueta pública** del tipo, centro, profesional, modalidad y estado. Las reprogramadas se agrupan con la que las sustituye (cadena de `cita_anterior_id`).
3. **Nunca** el motivo de la solicitud, el nombre interno del tipo ni la intervención vinculada (comprobado también en el HTML de la respuesta).
4. Acciones sobre las programadas: reprogramar y cancelar las de centros de su UO, salvo las propias (RN-05 bis). Las de otras UO, solo lectura. Reutiliza los flujos y las policies de citas existentes.
5. Paginación o «ver más» si hay muchas; por defecto las 10 más recientes y todas las futuras.

---

## Paso 4 — Documentos propios del día

1. Custodia: retirada lógica de un documento.
   - En `Documento`: `retirado_en`, `retirado_por_id`, `motivo_retirada` (o un estado `retirado` en `EstadoDocumento`, si encaja mejor con la custodia; elige y explícalo). Un documento retirado no aparece en listados ni se sirve, pero queda en el historial y la auditoría.
   - Sustituir el fichero crea una versión nueva (ya existe el mecanismo de versiones).
2. `DocumentoPolicy`:
   - `consulta_basica`/`tramitacion` con `documento.gestionar_propios` ven **solo** los documentos que han subido ellos (`created_by`), y solo durante el **día natural** de la subida (zona horaria de la aplicación).
   - En ese plazo pueden sustituir el fichero, cambiar los metadatos (tipo, personas vinculadas y destino) y retirarlo con motivo.
   - Pasado el día, desaparecen de su vista. No ven la carpeta completa.
3. `DocumentosCiudadano`: para estos roles, la lista muestra solo los propios del día; las acciones son las del punto 2; la subida usa `documento.subir`. El resto de roles, sin cambios.
4. Vincular a una solicitud de prestación: la fase 3 bis ya permite vincular documentos a una solicitud; `tramitacion` lo hace desde la solicitud. `consulta_basica` sube a la carpeta de la persona.

---

## Paso 5 — Citas para `tramitacion`

Los permisos `citas.*` del paso 1 activan para `tramitacion` lo mismo que para `consulta_basica` en citas (bandeja de citación, dar, reprogramar y cancelar en su UO, salvo las propias), según `modulo-citas.md` §7. Avisos de solicitud de cita creada: también a `tramitacion` del centro (§3.1 de citas).

---

## Paso 6 — Superficie de trabajo

Si `consulta_basica` no tiene página de inicio propia, su inicio es la búsqueda de ciudadanos con acceso a la bandeja de citación. `tramitacion` ya tiene su bandeja (fase 3 bis); añade la de citación al menú. Sin pantallas nuevas fuera de esto.

---

## Cierre

1. Suites tocadas y suite completa sin fallos nuevos. `npm run build` y `ui:auditar` en verde.
2. Documentación: `roles-consulta-basica-tramitacion.md` (estado «implementado» para `consulta_basica`, F4-1 en §3.2), `modulo-citas.md` (§6 bis implementado; `tramitacion` con permisos de citas), `modulo-ciudadania.md` (bloques por rol), `modulo-documentos.md` (retirada lógica, documentos propios del día), `modulo-usuarios-permisos.md` (ROL 5 y 7, §4.4).
3. CHANGELOG, BACKLOG (cierra «Citas: delta de la revisión del 30 sep» y «`consulta_basica`: diseño cerrado sin instrucciones»), SESSION, `CLAUDE.md` §6. Push y despliegue en verde.

## Criterios de finalización

1. TF-CBA-01 a 30 en verde, con negativos comprobados.
2. Ninguna decisión de interfaz de estas pantallas por nombre de rol.
3. Documentación al día y despliegue en verde.

---

# Tests — TF-CBA-01 a TF-CBA-30

**Convenciones:** PHPUnit con `#[Test]`, PostgreSQL (`vida_testing`), Dado / Cuando / Entonces. Ubicación: `Modules/Ciudadania/tests/Feature/ConsultaBasica/` (`FichaPorRolTest`, `EstadoHistoriaTest`, `RelacionesAltaTest`, `ProtegidosConsultaBasicaTest`), `Modules/Agenda/tests/Feature/Citas/HistorialFichaTest.php`, `Modules/Documentos/tests/Feature/DocumentosPropiosTest.php`. **[negativo]** = debe fallar sin la protección.

**Datos:** `CircuitoTestSetup` (fase 3) y:
- `$ventanillaNorte` (`consulta_basica`, adscrita a `$uoCssNorte`); `$ventanillaSur` (`consulta_basica`, otro CSS); `$adminNorte` (`tramitacion`, `$uoCssNorte`).
- `$ana` con historia en `$cssNorte`, referencia `$tsNorte`, PISO, una prestación activa, citas: dos pasadas (una completada y otra con incomparecencia), una reprogramada y su sustituta, una futura en `$cssNorte` en la agenda de `$tsNorte`, y una futura en `$ciamVallecas`.
- `$marta` protegida, historia en `$cssNorte`.
- `$pedro`: sin historia.

## Grupo A — Ficha por rol

**TF-CBA-01 — Bloques visibles**: `$ventanillaNorte` ve identificación y contacto, unidad de convivencia, relaciones, estado de la historia, citas y sus documentos del día; no ve prestaciones, situación social ni accesos recientes (comprobado en el HTML).

**TF-CBA-02 — Estado de la historia sin contenido** **[negativo]**: ve «Abierta» y el nombre de `$tsNorte`; la respuesta no contiene número de historia, fechas, planes, apuntes ni enlaces a la historia; las rutas de la historia le devuelven 403.

**TF-CBA-03 — Sin historia**: para `$pedro`, el bloque dice que no tiene Historia Social.

**TF-CBA-04 — Consulta fuera de su UO, operación dentro**: `$ventanillaSur` abre la ficha de `$ana` (no protegida) y la ve; intenta editar el teléfono o una relación → 403.

**TF-CBA-05 — Editar Capa 1 en su UO**: `$ventanillaNorte` cambia el teléfono de `$ana` → guardado y auditado.

**TF-CBA-06 — Relaciones**: `$ventanillaNorte` da de alta, edita y cierra una relación de `$ana` → permitido; no puede modificar la unidad de convivencia (acción no disponible y rechazada en el servidor).

**TF-CBA-07 — Alta de ciudadanos**: `$ventanillaNorte` da de alta a una persona nueva con el control de duplicados; si coincide con `$pedro`, se le ofrece la existente.

**TF-CBA-08 — Sin banner de protegido** **[negativo]**: en la ficha de `$marta` abierta por `$ventanillaNorte`, la respuesta no contiene el banner ni ningún texto que indique la protección.

**TF-CBA-09 — Protegida desde su centro (F4-1)**: `$ventanillaNorte` ve los datos objetivos de `$marta` (identificación, contacto, convivencia, relaciones, citas).

**TF-CBA-10 — Protegida desde otro centro (F4-1)** **[negativo]**: `$ventanillaSur` busca a `$marta` → «Persona con protección especial», sin nombre, documento ni contacto; la ficha no se abre con datos.

**TF-CBA-11 — `tramitacion` ve lo mismo en la ficha**: `$adminNorte` ve los mismos bloques que TF-CBA-01 (más lo suyo de solicitudes en su bandeja, fuera de la ficha).

**TF-CBA-12 — Decisiones por permiso**: quitar `historia.ver_estado` a `$ventanillaNorte` oculta el bloque de historia; dárselo a un rol sin él lo muestra.

## Grupo B — Historial de citas

**TF-CBA-13 — Completo y de cualquier centro**: `$ventanillaNorte` ve las seis citas de `$ana`, incluida la de `$ciamVallecas`, con fecha, etiqueta pública, centro, profesional, modalidad y estado.

**TF-CBA-14 — Nunca lo interno** **[negativo]**: la respuesta no contiene el nombre interno del tipo, el motivo de la solicitud ni la intervención vinculada de ninguna cita.

**TF-CBA-15 — Reprogramadas agrupadas**: la reprogramada aparece agrupada con su sustituta, no como dos citas sueltas.

**TF-CBA-16 — Acciones en su UO**: sobre la futura de `$cssNorte`, `$ventanillaNorte` puede reprogramar y cancelar; sobre la de `$ciamVallecas`, solo ver (acciones ausentes y rechazadas en el servidor).

**TF-CBA-17 — Las propias no** **[negativo]**: un auxiliar con `intervencion` + `consulta_basica` con una cita en su propia agenda la ve y no puede reprogramarla ni cancelarla (RN-05 bis).

**TF-CBA-18 — `intervencion` también ve el historial**: `$tsNorte` ve el bloque (con su vista habitual).

**TF-CBA-19 — Paginación**: con 25 citas pasadas, se muestran las 10 más recientes y todas las futuras; «ver más» carga el resto.

## Grupo C — Documentos propios del día

**TF-CBA-20 — Subir**: `$ventanillaNorte` sube un documento a la carpeta de `$ana` → se ingesta como siempre y queda con `created_by` ella.

**TF-CBA-21 — Solo los suyos** **[negativo]**: con otros documentos en la carpeta de `$ana` (de `$tsNorte` y de `$ventanillaSur`), `$ventanillaNorte` solo ve el suyo; abrir los otros por URL firmada o por id → 403.

**TF-CBA-22 — Solo el día de la subida** **[negativo]**: con `setTestNow` al día siguiente, el suyo ya no aparece ni se puede abrir, sustituir ni retirar.

**TF-CBA-23 — Sustituir el fichero**: el mismo día lo sustituye → versión nueva; la anterior se conserva en el historial.

**TF-CBA-24 — Cambiar metadatos**: cambia el tipo y añade a un miembro de la unidad de convivencia como persona vinculada → guardado; vincular a una persona que no es miembro → rechazado.

**TF-CBA-25 — Retirar**: lo retira con motivo → desaparece de todos los listados y no se sirve; queda con quién, cuándo y motivo, y en la auditoría. Sin motivo → rechazado.

**TF-CBA-26 — Otros roles sin cambios**: `$tsNorte` sigue viendo la carpeta completa (salvo retirados) con sus acciones.

## Grupo D — Citas para `tramitacion` y permisos

**TF-CBA-27 — `tramitacion` gestiona citas**: `$adminNorte` ve la bandeja de citación de `$cssNorte`, da una cita, la reprograma y la cancela; no puede hacerlo en `$ciamVallecas`.

**TF-CBA-28 — Avisos de solicitud a `tramitacion`**: una solicitud de cita nueva en `$cssNorte` avisa también a `$adminNorte`.

**TF-CBA-29 — Seeder de permisos**: tras el seeder, cada rol tiene exactamente los permisos del paso 1; re-ejecutarlo no duplica ni quita los configurados en backoffice.

**TF-CBA-30 — `consulta_basica` no abre la Historia Social** **[negativo]**: rutas de historia, plan, apuntes y valoraciones de `$ana` → 403 para `$ventanillaNorte` y `$adminNorte`.

## Tabla de estado

| Grupo | Tests | Estado |
|---|---|---|
| A — Ficha por rol | TF-CBA-01..12 | |
| B — Historial de citas | TF-CBA-13..19 | |
| C — Documentos propios del día | TF-CBA-20..26 | |
| D — `tramitacion` y permisos | TF-CBA-27..30 | |
