# Tests funcionales — UI Agenda Supervisor

**Módulo:** `Modules\Agenda`
**Scope:** Interfaz de supervisión — Filament + Livewire
**Referencia funcional:** `docs/modulo-agenda.md`
**Referencia UI:** `docs/ui-supervisor-agenda.md`

---

## Fixtures compartidos

Todos los tests de este documento asumen los siguientes fixtures disponibles como propiedades del TestCase:

- `$centro` — Centro CSS Retiro, `modo_agenda = estandar`
- `$horario` — HorarioCentro vigente del centro, sin `semana_tipo` configurada inicialmente
- `$supervisor` — Usuario con rol supervisor, asignado al centro
- `$profesionales` — Colección de 3 profesionales asignados al centro
- `$laura` — `$profesionales[0]`, usada como ejemplo de jornada reducida

---

## Grupo 1 — TipoSlot Resource (Filament)

**Requisito de referencia:** § 4.2 — entidad `TipoSlot`; § 3 paso 4 — catálogo de eventos internos del centro.

---

**T-AGS-01 — El supervisor puede listar los tipos de slot de su centro**
Requisito: § 4.2 — relación `TipoSlot` con `HorarioCentro`.
Dado: dos tipos de slot creados para el horario del centro.
Cuando: el supervisor navega a la página de lista del resource `TipoSlot` en Filament.
Entonces: la respuesta tiene estado 200 y la tabla muestra los dos registros.

---

**T-AGS-02 — El supervisor puede crear un tipo de slot válido**
Requisito: § 4.2 — campos requeridos `nombre` y `duracion_minutos`.
Dado: el supervisor autenticado en el formulario de creación.
Cuando: envía `nombre = "Reunión de equipo"`, `duracion_minutos = 60`, `bloquea_todos_convocados = true`, `activo = true`.
Entonces: el registro existe en `tipos_slot` con los valores enviados y sin errores de formulario.

---

**T-AGS-03 — No se puede crear un tipo de slot sin nombre**
Requisito: § 4.2 — `nombre` required.
Dado: el formulario de creación.
Cuando: se envía con `nombre` vacío.
Entonces: el formulario reporta error de validación en el campo `nombre`.

---

**T-AGS-04 — No se puede crear un tipo de slot sin duración**
Requisito: § 4.2 — `duracion_minutos` required.
Dado: el formulario de creación.
Cuando: se envía con `duracion_minutos` nulo.
Entonces: el formulario reporta error de validación en el campo `duracion_minutos`.

---

**T-AGS-05 — Un profesional sin rol supervisor no puede acceder al resource**
Requisito: § 6.1 — acceso restringido a supervisores.
Dado: un usuario con rol profesional autenticado.
Cuando: intenta acceder a la ruta del resource `TipoSlot`.
Entonces: la respuesta tiene estado 403.

---

## Grupo 2 — Semana tipo (SemanaTypoComponent)

**Requisito de referencia:** § 4.1 — campo `semana_tipo` en `HorarioCentro`; § 3 paso 1 — definición de la semana tipo.

---

**T-AGS-06 — La pantalla de semana tipo carga correctamente para el supervisor**
Requisito: § 6.2 — componente Livewire `SemanaTypoComponent`.
Dado: el supervisor autenticado en su centro.
Cuando: monta el componente de semana tipo.
Entonces: el componente carga sin errores y muestra el título "Semana tipo".

---

**T-AGS-07 — Guardar la semana tipo persiste el JSON en HorarioCentro**
Requisito: § 4.1.1 — estructura `semana_tipo` con clave `base` y sobreescrituras por día.
Dado: el componente cargado con una semana válida que incluye clave `base` y sobreescritura para el día 5.
Cuando: se llama a `guardar`.
Entonces: `HorarioCentro.semana_tipo` contiene las claves `base` y `5`, y se emite un toast de confirmación.

---

**T-AGS-08 — Una franja con hora fin anterior a hora inicio es rechazada**
Requisito: § 4.1.1 — validación de coherencia temporal.
Dado: el componente cargado.
Cuando: se intenta guardar una franja con `inicio = "13:00"` y `fin = "09:00"`.
Entonces: el componente reporta error de validación en el campo `fin`.

---

**T-AGS-09 — Los slots estimados se calculan en tiempo real**
Requisito: § 2.6 (ui-supervisor-agenda) — panel de slots estimados.
Dado: una semana con franja de tipo `atencion` de 09:00 a 13:00 en el día base (240 min).
Cuando: se accede a la propiedad calculada de slots estimados.
Entonces: el valor para cualquier día que use el base es `floor(240 / 30)` multiplicado por el número de profesionales del centro.

---

**T-AGS-10 — Copiar un día replica sus franjas en los días destino**
Requisito: § 2.4 (ui-supervisor-agenda) — acción "Copiar día a otros".
Dado: el lunes tiene una franja de atención de 09:00 a 13:00 para todos los profesionales.
Cuando: se llama a `copiarDia` con origen = lunes y destino = [martes, miércoles].
Entonces: martes y miércoles tienen la misma franja de 09:00 a 13:00.

---

**T-AGS-11 — Se muestra aviso si hay un cuadrante en borrador al guardar**
Requisito: § 2.7 (ui-supervisor-agenda) — aviso de borrador en curso.
Dado: existe un `CuadranteMes` en estado `borrador` para el mes siguiente.
Cuando: el supervisor guarda cambios en la semana tipo.
Entonces: la respuesta incluye un aviso mencionando el borrador en curso.

---

## Grupo 3 — Perfil horario del profesional (PerfilHorarioComponent)

**Requisito de referencia:** § 4.3 — entidad `PerfilHorarioProfesional`; § 3 paso 2.

---

**T-AGS-12 — La pestaña carga con los datos del perfil activo actual**
Requisito: § 4.3 — solo un perfil activo por profesional y centro.
Dado: Laura tiene un `PerfilHorarioProfesional` activo con `jornada_semanal_horas = 17.5`.
Cuando: el supervisor monta el componente para Laura.
Entonces: el componente muestra `jornadaSemanal = 17.5` y el horario registrado.

---

**T-AGS-13 — Guardar con la misma fecha de vigencia actualiza el perfil existente**
Requisito: § 4.3 — constraint de unicidad `(usuario_id, centro_id, activo)`.
Dado: Laura tiene un perfil activo vigente desde `2026-01-01`.
Cuando: el supervisor guarda cambios sin modificar `vigente_desde`.
Entonces: sigue existiendo exactamente un perfil activo para Laura en el centro.

---

**T-AGS-14 — Guardar con nueva fecha de vigencia crea un nuevo perfil y cierra el anterior**
Requisito: § 4.3 — versionado de perfiles.
Dado: Laura tiene un perfil activo vigente desde `2026-01-01` y `vigente_hasta = null`.
Cuando: el supervisor guarda con `vigente_desde = "2026-08-01"`.
Entonces: el perfil anterior tiene `vigente_hasta = "2026-07-31"` y existe un nuevo perfil activo con `vigente_desde = "2026-08-01"`.

---

**T-AGS-15 — Desactivar un día lo elimina de los días activos**
Requisito: § 4.2 (ui-supervisor-agenda) — selector de días.
Dado: Laura tiene los cinco días activos.
Cuando: se llama a `toggleDia(5)` (viernes).
Entonces: el conjunto de días activos ya no incluye el 5.

---

**T-AGS-16 — Añadir tarde agrega una franja vespertina al día correspondiente**
Requisito: § 4.2 (ui-supervisor-agenda) — botón "Añadir tarde".
Dado: el componente cargado sin tarde en el martes.
Cuando: se llama a `addTarde(2)`.
Entonces: el martes tiene una franja con `tIni = "15:00"` y `tFin = "19:00"`.

---

## Grupo 4 — Excepciones del profesional (ExcepcionesComponent)

**Requisito de referencia:** § 4.4 — entidad `ExcepcionProfesional`; § 3 paso 3.

---

**T-AGS-17 — Las excepciones futuras aparecen en la sección "Próximas"**
Requisito: § 4.3 (ui-supervisor-agenda) — tabla de próximas excepciones.
Dado: Laura tiene una excepción de tipo `vacaciones` con `fecha_inicio` en 10 días.
Cuando: se monta el componente de excepciones.
Entonces: la colección `proximas` contiene ese registro.

---

**T-AGS-18 — Crear una excepción válida la persiste en base de datos**
Requisito: § 4.4 — campos obligatorios tipo, fecha_inicio, fecha_fin.
Dado: el modal de creación abierto.
Cuando: se envía tipo `formacion`, fecha_inicio `2026-09-10`, fecha_fin `2026-09-11`, `afecta_disponibilidad = true`.
Entonces: el registro existe en `excepciones_profesional` con los valores enviados y el modal se cierra.

---

**T-AGS-19 — Fecha fin anterior a fecha inicio es rechazada**
Requisito: § 4.4 — validación de coherencia temporal.
Dado: el modal de creación abierto.
Cuando: se envía `fecha_inicio = "2026-09-15"` y `fecha_fin = "2026-09-10"`.
Entonces: el componente reporta error de validación en el campo `fecha_fin`.

---

**T-AGS-20 — Se muestra aviso si hay citas confirmadas en el período de la excepción**
Requisito: § 4.3 (ui-supervisor-agenda) — aviso de citas afectadas.
Dado: existe un `Slot` de tipo `cita_ciudadano` con una `Cita` en estado `confirmada` el día `2026-09-10` para Laura.
Cuando: se guarda una excepción con `afecta_disponibilidad = true` que cubre ese día.
Entonces: la respuesta menciona el número de citas que serán afectadas.

---

**T-AGS-21 — Eliminar una excepción futura la borra de base de datos**
Requisito: § 4.4 — gestión del ciclo de vida de excepciones.
Dado: Laura tiene una excepción con `fecha_inicio` en el próximo mes.
Cuando: el supervisor llama a `eliminar` con el id de esa excepción.
Entonces: el registro ya no existe en `excepciones_profesional`.

---

## Grupo 5 — Cuadrante mensual (CuadranteMesComponent)

**Requisito de referencia:** § 4.5 `CuadranteMes`, § 4.6 `LineaCuadrante`, § 4.7 `Slot`; § 3 paso 5.

---

**T-AGS-22 — El cuadrante en borrador muestra el botón de publicación**
Requisito: § 5.2 (ui-supervisor-agenda) — estado borrador.
Dado: existe un `CuadranteMes` en estado `borrador` para julio 2026.
Cuando: el supervisor monta el componente.
Entonces: el componente muestra el badge "Borrador" y el botón "Publicar cuadrante".

---

**T-AGS-23 — Las excepciones del mes aparecen como celdas diferenciadas**
Requisito: § 5.7 (ui-supervisor-agenda) — tipo de celda `excepcion`.
Dado: Laura tiene una excepción de tipo `vacaciones` que cubre el día 21 de julio.
Cuando: el supervisor navega a la semana 4 del cuadrante.
Entonces: `getCelda` para Laura el día 21 devuelve `tipo_celda = "excepcion"` y `excepcion.tipo = "vacaciones"`.

---

**T-AGS-24 — Hacer clic en celda con excepción abre el modal de detalle**
Requisito: § 5.8 (ui-supervisor-agenda) — modal de detalle de excepción.
Dado: Laura tiene una excepción de tipo `formacion` el día 14 de julio.
Cuando: se llama a `abrirModalExc` para Laura el día 14.
Entonces: `modalExcAbierto = true` y `excDetalle.tipo = "formacion"`.

---

**T-AGS-25 — Añadir un evento puntual crea un EventoAgenda con origen director**
Requisito: § 4.8 — `EventoAgenda.origen = "director"`; § 5.8 (ui-supervisor-agenda).
Dado: cuadrante en borrador y el modal de evento abierto para el día 7 de julio.
Cuando: se envía título `"Reunión de coordinación"`, hora_inicio `"15:00"`, hora_fin `"16:00"` y dos profesionales convocados.
Entonces: existe un registro en `eventos_agenda` con `titulo = "Reunión de coordinación"`, `fecha = "2026-07-07"`, `origen = "director"`, y la tabla pivot `evento_usuario` tiene dos registros para ese evento.

---

**T-AGS-26 — Publicar el cuadrante cambia su estado a publicado**
Requisito: § 4.5 — campo `estado`; § 5.10 (ui-supervisor-agenda).
Dado: cuadrante en borrador con semana tipo y perfiles configurados.
Cuando: el supervisor llama a `publicar`.
Entonces: `CuadranteMes.estado = "publicado"`, `publicado_en` no es nulo y `publicado_por_id` corresponde al supervisor.

---

**T-AGS-27 — Publicar el cuadrante materializa slots de cita ciudadana**
Requisito: § 5.3 — `SlotMaterializadorService`; § 1.3 — slots de 30 min.
Dado: cuadrante en borrador, semana tipo con franja de atención de 09:00 a 13:00, y perfiles activos para todos los profesionales del centro.
Cuando: el supervisor publica el cuadrante.
Entonces: existen registros en `slots` de tipo `cita_ciudadano` con estado `disponible` para el centro en julio 2026, y todos tienen una duración de exactamente 30 minutos (`hora_fin - hora_inicio = 30`).

---

**T-AGS-28 — El cuadrante publicado no muestra el botón de publicación**
Requisito: § 5.2 (ui-supervisor-agenda) — estado publicado de solo lectura.
Dado: cuadrante con estado `publicado`.
Cuando: el supervisor monta el componente.
Entonces: el componente muestra el badge "Publicado" y no muestra el botón "Publicar cuadrante".

---

**T-AGS-29 — La navegación entre semanas actualiza los días visibles**
Requisito: § 5.5 (ui-supervisor-agenda) — navegación por semanas.
Dado: cuadrante en borrador para julio 2026 (5 semanas).
Cuando: se llama a `nextSemana` desde la semana 0, luego `prevSemana`, luego `goSemana(3)`.
Entonces: `semanaActual` vale 1, luego 0, luego 3 respectivamente.

---

**T-AGS-30 — Las métricas del cuadrante reflejan las excepciones incorporadas**
Requisito: § 5.4 (ui-supervisor-agenda) — tarjetas de métricas.
Dado: Laura tiene una excepción que cubre 3 días laborables de julio.
Cuando: se accede a la propiedad `metricas` del componente.
Entonces: `metricas.dias_con_excepciones = 3`.

---

## Grupo 6 — Control de acceso

**Requisito de referencia:** § 6 — separación Filament / Livewire; acceso por rol.

---

**T-AGS-31 — Usuario no autenticado es redirigido al login**
Requisito: middleware de autenticación en todas las rutas del módulo.
Dado: ningún usuario autenticado.
Cuando: se accede a la ruta de semana tipo del centro.
Entonces: la respuesta redirige a la ruta de login.

---

**T-AGS-32 — Un profesional sin rol supervisor no puede montar el componente de cuadrante**
Requisito: § 6.2 — acceso restringido a supervisores.
Dado: un profesional del centro autenticado (sin rol supervisor).
Cuando: intenta montar `CuadranteMesComponent`.
Entonces: el componente responde con estado 403.

---

**T-AGS-33 — Un supervisor de otro centro no puede ver el cuadrante**
Requisito: scoping por centro — un supervisor solo gestiona los centros a los que está asignado.
Dado: un supervisor asignado únicamente a un centro diferente.
Cuando: intenta montar `CuadranteMesComponent` con el centro de los fixtures.
Entonces: el componente responde con estado 403.

---

## Resumen

| Grupo | Rango | Tests | Componente |
|---|---|---|---|
| 1 — TipoSlot Resource | T-AGS-01 a 05 | 5 | Filament `TipoSlotResource` |
| 2 — Semana tipo | T-AGS-06 a 11 | 6 | `SemanaTypoComponent` |
| 3 — Perfil horario | T-AGS-12 a 16 | 5 | `PerfilHorarioComponent` |
| 4 — Excepciones | T-AGS-17 a 21 | 5 | `ExcepcionesComponent` |
| 5 — Cuadrante mensual | T-AGS-22 a 30 | 9 | `CuadranteMesComponent` |
| 6 — Control de acceso | T-AGS-31 a 33 | 3 | Todos |
| **Total** | | **33** | |
