# Instrucciones CLI — Usabilidad de la Historia Social (ronda 1)

**Origen:** revisión del Delegado Digital el 27 nov 2026 en staging, Historia Social de la persona 906 (`/intervencion/ciudadano/906`) con `ts1.ciam` (intervención).
**Estado:** U1–U5 confirmadas por el Delegado Digital el 27 nov 2026 (incluidas U2.1, U2.2 y U3.3, que salieron del análisis del código). **Hecha el 27 nov 2026** (pasos 0 a 5; en el paso 0.4 se confirmó auditar los cuatro grupos del inventario).
**Requisito previo:** `master` en `0ed7950` o posterior.
**Tests:** TF-USA-01 a TF-USA-16 (al final).

**Regla de trabajo:** si el código contradice estas instrucciones o aparece un caso no previsto, **detente y pregunta**. Lo marcado como *fuera de alcance* no se toca. Sin funcionalidad nueva fuera de lo que se lista.

**Antes de empezar:** `CLAUDE.md`, `SESSION.md`, `docs/modulo-auditoria.md`, `docs/modulo-intervencion.md`, `docs/instrucciones-cli/2026-11-correcciones-staging-ronda1.md` (R1: RN-15 en toda actuación), `docs/instrucciones-cli/2026-11-fase3-circuito-intervencion.md` (A1.5, A3.5), `docs/design-system/SKILL.md`.

**Archivos principales:**
- `Modules/Intervencion/app/Http/Livewire/CiudadanoPage.php` y `resources/views/livewire/ciudadano-page.blade.php`
- `Modules/Intervencion/app/Http/Livewire/PlanPage.php` (`tiposPlanes()`, `tiposAbribles()`)
- `Modules/Centro/app/Models/Prescripcion.php`
- `app/Models/Audit.php`, `app/Enums/AccionAuditEnum.php`, `app/Queries/AccesosExpedienteQuery.php`, `app/Services/AuditService.php`, `app/Traits/Auditable.php`
- `app/Models/User.php` (`nombreVisible()`)

---

## Lo que se ha visto (diagnóstico previo)

| Id | Síntoma | Causa en el código |
|---|---|---|
| **U1** | «+ Abrir plan» aparece aunque la persona ya tenga su plan, y lleva a una pantalla sin ningún tipo de plan que elegir | El botón solo depende de `$modoLectura`. Los tipos que se pueden abrir se calculan en `PlanPage::tiposAbribles()` (sin los tipos que ya tienen un plan vigente, sin el principal fuera del centro de la historia y sin los especializados sin entrada directa), pero la Historia Social no los consulta |
| **U2** | «Recursos prescritos» muestra «Activa · Actividad», sin decir qué recurso es | La banda pinta `estado` y `tipo_destino` sin cargar la prestación ni el destino |
| **U2** | Cancelar una prescripción no comprueba permisos | `CiudadanoPage::cancelarPrescripcion()` no llama a `exigirActuacion()` ni a una policy: cualquiera que abra la Historia Social puede cancelar (también en modo lectura, si se lanza la acción a mano). Contradice R1 |
| **U3** | Tras cancelar, «Últimos accesos» solo dice «Consultó el expediente» | `Prescripcion` no usa `Auditable`: la cancelación **no queda registrada en la auditoría**. Lo único registrado es la consulta de la página. Además, el widget solo enseña la etiqueta genérica de la acción (`AccionAuditEnum::etiqueta()`) y «Ver todo» es un enlace muerto (`href="#"`, `TODO`) |
| **U4** | El autor de una anotación en el timeline aparece con su correo | La vista usa `$apunte->autor?->name`, y `User::booted()` siempre rellena `name` con el email. El widget de accesos usa el nombre del Profesional, por eso no coinciden. El mismo error está en 4 sitios más (ver paso 4) |
| **U5** | Las estadísticas «Apuntes», «Prestaciones activas» y «Último contacto» no aportan en esta pantalla | Barra de estadísticas al pie de la columna derecha (`statApuntes`, `statPrestaciones`, `statUltimoContacto`) |

---

## Decisiones (confirmadas el 27 nov 2026)

| Id | Decisión |
|---|---|
| **U1** | «+ Abrir plan» solo se muestra si hay al menos un tipo de plan que el usuario pueda abrir para esa persona en su centro activo. La regla es la misma de A1.5 y se calcula en un solo sitio, que usan tanto la Historia Social como la pantalla de creación. Con PIA vigente y sin otro tipo abrible, el botón no aparece. Si en ese centro se pudiera abrir otro tipo (p. ej. un especializado con entrada directa), el botón sí aparece y la pantalla de creación solo ofrece ese tipo |
| **U2** | Cada prescripción se presenta con el nombre del recurso y abre una **modal de resumen**: prestación, destino (nombre de la actividad o de la colección de plazas, y su centro), plaza asignada, estado, posición en la lista de espera si la hay, fechas (prescripción, asignación, inicio y fin), plan y compromiso vinculados (enlace al plan si el usuario lo puede ver), profesional que prescribió y notas |
| **U2.1** | La cancelación sale de la banda y pasa a la modal: botón «Cancelar prescripción» con **motivo obligatorio** (texto libre, mínimo 10 caracteres). El motivo sustituye al texto fijo «Cancelada por el profesional de referencia.» |
| **U2.2** | Cancelar una prescripción es una actuación: exige la policy del recurso **y** RN-15 (`exigirActuacion()`), tanto al mostrar el botón como al ejecutar la acción. En modo lectura la modal se abre sin el botón |
| **U3** | **Toda modificación del expediente queda en la auditoría con un resumen legible**, y el widget la muestra así: «Canceló la prescripción de “Taller de memoria”», «Registró una anotación», «Creó el plan PIA (borrador)»… La consulta sigue siendo «Consultó el expediente». Cada entrada del widget abre un **drawer lateral** con el detalle: quién (nombre y UO), cuándo (fecha y hora exactas), qué acción, sobre qué registro (con enlace si el usuario puede verlo) y los cambios campo a campo (antes → después, con nombres de campo en español) |
| **U3.1** | El resumen se guarda al escribir la auditoría (`contexto['resumen']`), no se recalcula al leerla: los registros de auditoría son inmutables y el texto tiene que seguir valiendo aunque el recurso cambie o se borre después |
| **U3.2** | El drawer respeta la visibilidad actual: quien solo ve sus propios accesos (`AccesosExpedienteQuery::paraUsuario`) solo abre los suyos. En los cambios campo a campo no se muestra el contenido de apuntes privados de otro profesional ni campos excluidos de la auditoría (`camposAuditables()`) |
| **U3.3** | «Ver todo» deja de ser un enlace muerto: abre el mismo drawer con el historial completo de accesos visibles para el usuario, paginado, con filtro «Solo modificaciones». Cada fila abre su detalle |
| **U4** | En toda la interfaz, el nombre de un usuario se muestra con `User::nombreVisible()`. `name` no se usa para mostrar personas |
| **U5** | Se quita la barra de estadísticas de la Historia Social. La pantalla «Resumen de la persona» queda en el BACKLOG (ver Cierre) |

Anota U1–U5 en `SESSION.md` al empezar.

---

## Fuera de alcance

- Pantalla «Resumen de la persona» (solo entrada en el BACKLOG).
- Rediseñar `PrescribirRecursoModal` (sigue en el BACKLOG).
- Agrupar consultas repetidas en el widget de accesos.
- Auditoría retroactiva: las cancelaciones anteriores a este encargo no se reconstruyen.
- Cambiar los datos de staging.

---

## Paso 0 — Verificación

1. `git pull` y suite completa. Anota los fallos previos.
2. En staging, persona 906: lista sus planes vigentes y, para `ts1.ciam` en su centro activo, qué devuelve hoy `tiposAbribles()`. Confirma que la lista está vacía.
3. Busca en `audits` las entradas de la persona 906 del 27 nov y confirma que no hay ningún registro de la cancelación de prescripciones.
4. **Inventario de modificaciones del expediente sin auditar:** lista los modelos que se crean, editan o cancelan desde la Historia Social y sus pantallas hijas (apuntes, valoraciones, escalas, planes, seguimientos, derivaciones, solicitudes de prestación, prescripciones, inscripciones, documentos, informes, unidad de convivencia, relaciones, representante, citas). Para cada uno: si usa `Auditable`, si registra `ciudadano_id` y si su acción pasa por un servicio o se hace directamente en el componente. **Entrega este inventario y espera confirmación antes del paso 3** si hay más de tres modelos sin auditar.
5. Lista los usos de `->name` para mostrar personas (`grep -rnE "(autor|user|creador|responsable|profesional)\??->name\b"`), sin contar los tests.

## Paso 1 — «+ Abrir plan» (U1)

1. Saca `PlanPage::tiposAbribles()` a un servicio del módulo (p. ej. `Modules/Intervencion/app/Services/Planes/TiposPlanAbribles.php`, con `para(HistoriaSocial, Centro): Collection`), incluido el filtro previo `ConfiguracionEfectivaCentro::tiposPlan($centro)`. `PlanPage::tiposPlanes()` lo usa sin cambiar su comportamiento.
2. `CiudadanoPage`: nuevo computed `puedeAbrirPlan` = no está en modo lectura, hay centro activo y el servicio devuelve al menos un tipo. El botón solo se muestra si es `true`.
3. Si alguien llega a `/intervencion/plan/crear?historia=…` sin tipos abribles (enlace antiguo, otra pestaña), se mantiene el mensaje actual y se añade el enlace «Volver a la Historia Social».

## Paso 2 — Prescripciones: resumen y cancelación (U2, U2.1, U2.2)

1. `prescripcionesActivas` carga, con eager loading, la prestación, el destino (actividad o colección de plazas, según `tipo_destino`), la plaza, la lista de espera, el plan, el compromiso y el profesional. Si `destino_id` es polimórfico de hecho pero no hay relación definida, añádela en `Prescripcion` (`destino()`) y explícalo.
2. Banda: cada elemento muestra «*Nombre del recurso* · *Estado*» (p. ej. «Taller de memoria · Activa») como botón que abre la modal. Se quita el botón de cancelar de la banda.
3. Modal de resumen con los datos de U2, con el patrón de modal del design system (como la modal de detalle de apunte). Accesible: foco al abrir, cierre con Esc, título asociado.
4. Cancelación según U2.1 y U2.2: botón en el pie de la modal, abre un formulario con el motivo, valida en el servidor y confirma. La lógica (liberar la plaza, cancelar la lista de espera, cambiar el estado y guardar el motivo) pasa a un servicio (p. ej. `CancelarPrescripcion`) dentro de una transacción. El componente solo llama al servicio.
5. Comprueba la policy de `Prescripcion` (créala si no existe, con `cancel`): el usuario puede actuar sobre la Historia Social y el centro de la operación es suyo (RN-15).

## Paso 3 — Auditoría con resumen y drawer (U3)

1. `Prescripcion` pasa a ser auditable (`Auditable` + `AuditableModel`), con `ciudadano_id`. Haz lo mismo con los modelos del inventario del paso 0.4 que confirme el Delegado Digital.
2. Resumen legible: un único sitio que lo construye al escribir la auditoría (p. ej. un método `resumenAuditoria(AccionAuditEnum $accion, array $antes, array $despues): ?string` en el contrato `AuditableModel`, con una implementación por defecto genérica «Modificó *etiqueta del modelo*»). Se guarda en `contexto['resumen']`. Casos mínimos:
   - Prescripción: «Prescribió “X”», «Canceló la prescripción de “X”» (cuando cambia a `cancelada`), «Modificó la prescripción de “X”».
   - Apunte: «Registró una anotación / entrevista / gestión…» (según `TipoApunte`), sin el contenido.
   - Plan: «Creó el plan *tipo* (borrador)», «Activó el plan *tipo*», «Modificó el plan *tipo*».
   - Valoración y escala: «Registró la valoración *tipo de ficha*», «Aplicó la escala *nombre*».
   - Documento: «Subió el documento *tipo*», «Eliminó el documento *tipo*».
   Para las cancelaciones, el motivo va en `datos_despues`, no en el resumen.
3. Etiquetas de campo: un archivo de idioma (`lang/es/auditoria.php`, clave `campos`) con los nombres en español de los campos auditados. Si un campo no tiene etiqueta, no se muestra en el drawer y se registra un aviso en el log de desarrollo (no en producción).
4. Widget «Últimos accesos»: la segunda línea muestra `contexto['resumen']` si existe, o la etiqueta de la acción si no. Las modificaciones se ven en negrita (como ahora). Cada entrada es un botón que abre el drawer.
5. Drawer lateral (offcanvas a la derecha, del design system) con el detalle de U3 y las reglas de U3.2. Un componente Livewire propio (p. ej. `DetalleAccesoDrawer`) que recibe el id de auditoría y vuelve a comprobar en el servidor que el usuario puede verlo con `AccesosExpedienteQuery`.
6. «Ver todo» según U3.3, en el mismo drawer: lista paginada (20 por página) con el filtro «Solo modificaciones»; al pulsar una fila se muestra su detalle con «← Volver a la lista».
7. Mantén los avisos actuales de «Otra UO» (sospechoso y anómalo) en el widget y añádelos al drawer.

## Paso 4 — Nombre del profesional (U4)

1. Sustituye `->name` por `->nombreVisible()` en todos los sitios donde se muestra una persona. Como mínimo:
   - `ciudadano-page.blade.php` línea ~393 (timeline) y ~690 (accesos: `profesional?->nombre_completo ?? name` → `nombreVisible()`).
   - `CiudadanoPage::verApunte()` (modal de detalle de apunte).
   - `encargos-profesionales.blade.php`.
   - `informes-historial.blade.php`.
   - Los que salgan en el paso 0.5.
2. Comprueba que se cargan `autor.profesional` / `user.profesional` con eager loading donde haga falta, para no añadir consultas N+1.
3. Test de arquitectura: falla si una vista Blade de `Modules/*/resources/views` contiene `->name }}` aplicado a `autor`, `user`, `creador`, `responsable` o `profesional`.

## Paso 5 — Quitar la barra de estadísticas (U5)

1. Quita la barra «Apuntes / Prestaciones activas / Último contacto» de `ciudadano-page.blade.php`.
2. Quita `statApuntes`, `statPrestaciones` y `statUltimoContacto` de `CiudadanoPage` si no se usan en otro sitio, y sus tests.
3. Comprueba que la columna derecha se ve bien sin la barra (sin hueco al pie).

---

## Cierre

1. Suites tocadas y suite completa sin fallos nuevos. `npm run build` y `ui:auditar` en verde.
2. Documentación:
   - `modulo-auditoria.md`: resumen legible en `contexto['resumen']` (U3.1), etiquetas de campo, drawer de detalle y reglas de visibilidad (U3.2), «Ver todo».
   - `modulo-intervencion.md`: regla del botón «Abrir plan» (U1) y modal de prescripción con cancelación motivada (U2).
   - `modulo-prestaciones.md` (o el del módulo Centro, donde esté documentada la prescripción): cancelación con motivo obligatorio y RN-15.
   - `docs/decisiones-pendientes.md`: U1–U5 como decididas.
3. BACKLOG, entradas nuevas:
   - **Pantalla «Resumen de la persona».** Vista de un vistazo de la situación de la persona, con acceso directo a la ficha de datos, la Historia Social y los planes. Candidatos a mostrar: planes vigentes y próximo seguimiento, recursos prescritos, últimas actuaciones, próxima cita, alertas. Pendiente de diseño. Origen: revisión de usabilidad del 27 nov 2026 (se quitaron de la Historia Social las estadísticas «Apuntes», «Prestaciones activas» y «Último contacto»).
   - **Auditoría del resto de modificaciones**, si en el paso 0.4 se dejó alguna fuera.
4. CHANGELOG y SESSION (con U1–U5). Push y despliegue en verde.
5. Informa de qué hay que repetir en staging con `ts1.ciam` sobre la persona 906.

## Criterios de finalización

1. TF-USA-01 a 16 en verde, con los negativos comprobados.
2. En staging, persona 906 con `ts1.ciam`: no aparece «+ Abrir plan»; las prescripciones muestran su nombre y abren la modal; al cancelar una, «Últimos accesos» muestra «Canceló la prescripción de “…”» y el drawer enseña el motivo; el autor de una anotación nueva aparece con su nombre.
3. Documentación al día y despliegue en verde.

---

# Tests — TF-USA-01 a TF-USA-16

**Convenciones:** PHPUnit con `#[Test]`, PostgreSQL (`vida_testing`), Dado / Cuando / Entonces. Datos: `CircuitoTestSetup` y `TipoCentroTestSetup`. Ubicación junto a los tests del módulo afectado. **[negativo]** = debe fallar sin la protección.

## Grupo A — Abrir plan (U1)

**TF-USA-01 — Con plan vigente, sin botón** **[negativo]**: persona con un plan principal vigente y ningún otro tipo abrible en el centro → el HTML de la Historia Social no contiene «Abrir plan».

**TF-USA-02 — Sin plan, con botón**: persona sin planes, en el centro de su historia → aparece «Abrir plan».

**TF-USA-03 — Otro tipo abrible**: persona con plan principal vigente y un tipo especializado con entrada directa en el centro → aparece «Abrir plan» y la pantalla de creación solo ofrece ese tipo.

**TF-USA-04 — Misma regla en los dos sitios**: para un conjunto de casos (data provider), `TiposPlanAbribles` y `PlanPage::tiposPlanes()` devuelven los mismos tipos.

## Grupo B — Prescripciones (U2)

**TF-USA-05 — Nombre en la banda**: una prescripción a una actividad muestra el nombre de la actividad y su estado.

**TF-USA-06 — Modal de resumen**: al abrirla se ven la prestación, el destino, el estado, las fechas, el plan vinculado y el profesional que prescribió.

**TF-USA-07 — Motivo obligatorio** **[negativo]**: cancelar sin motivo o con menos de 10 caracteres → error de validación y la prescripción sigue activa.

**TF-USA-08 — Cancelación completa**: con motivo → estado `cancelada`, motivo guardado, plaza liberada y lista de espera cancelada, todo en una transacción (si falla un paso, no cambia nada).

**TF-USA-09 — Sin permiso no cancela** **[negativo]**: un usuario sin centro (R1) o en modo lectura que llama directamente a la acción de cancelar → rechazado; la prescripción sigue activa. La modal se abre sin el botón.

## Grupo C — Auditoría y drawer (U3)

**TF-USA-10 — La cancelación se audita**: tras cancelar hay un registro `editar` sobre la `Prescripcion` con `ciudadano_id`, `contexto['resumen']` = «Canceló la prescripción de “X”» y el motivo en `datos_despues`.

**TF-USA-11 — Widget con resumen**: «Últimos accesos» muestra el resumen de la cancelación, no «Consultó el expediente».

**TF-USA-12 — Detalle en el drawer**: abrir la entrada muestra quién, cuándo, la acción, el registro y el cambio de estado «Activa → Cancelada» con etiquetas en español.

**TF-USA-13 — Visibilidad del drawer** **[negativo]**: un usuario que solo ve sus propios accesos no puede abrir el detalle de una entrada de otro profesional, ni siquiera pasando el id directamente al componente.

**TF-USA-14 — Ver todo**: el enlace abre la lista paginada; con «Solo modificaciones» desaparecen las consultas.

## Grupo D — Nombre y estadísticas (U4, U5)

**TF-USA-15 — Autor con nombre** **[negativo]**: una anotación de un profesional con Profesional vinculado muestra su nombre completo en el timeline y en la modal de detalle, y en ningún caso su email. Test de arquitectura del paso 4.3 en verde.

**TF-USA-16 — Sin barra de estadísticas**: el HTML de la Historia Social no contiene «Prestaciones activas» ni «Último contacto».

## Tabla de estado

| Grupo | Tests | Estado |
|---|---|---|
| A — Abrir plan | TF-USA-01..04 | ✅ 27 nov (`Usabilidad/AbrirPlanTest`) |
| B — Prescripciones | TF-USA-05..09 | ✅ 27 nov (`Usabilidad/PrescripcionesHistoriaTest`) |
| C — Auditoría y drawer | TF-USA-10..14 | ✅ 27 nov (`Usabilidad/AuditoriaAccesosTest`) |
| D — Nombre y estadísticas | TF-USA-15..16 | ✅ 27 nov (`Usabilidad/NombreYEstadisticasTest`) |
