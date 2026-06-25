# Tests funcionales — UI Agenda supervisor

**Módulo:** `Modules\Agenda`
**Componentes:** `CuadranteSupervisorPage`, `AusenciasSupervisorPage`, `ExcepcionesSupervisorPage`, `EventosSupervisorPage`
**Framework:** PHPUnit + Livewire testing utilities
**Base de datos:** PostgreSQL (`vida_testing`)

---

## Convenciones

- Patrón: **Dado / Cuando / Entonces**.
- Los tests de restricciones de dominio se verifican también en negativo.
- Prefijo de IDs: `TF-AGS` (Agenda — Supervisor).
- Los actores se definen en un trait `AgendaSupervisorTestHelpers` compartido por todos los tests de esta suite.

### Actores reutilizados

```php
$supervisor   // Usuario con rol `supervision`, adscrito al centro de prueba
$profesional1 // TSR activo en el centro, con perfil horario L–V 9:00–14:00
$profesional2 // Educador social activo en el centro
$profesional3 // Psicóloga activa en el centro
$centro       // Centro en modo `estandar`
$horario      // HorarioCentro vigente: L–V 9:00–15:00, atención 9:30–14:00
$cuadrante    // CuadranteMes en estado `borrador` para el mes actual
```

---

## Grupo A — Cuadrante mensual

---

**TF-AGS-01 — El cuadrante en borrador es visible para el supervisor**

- **Dado** `$cuadrante` en estado `borrador` con líneas generadas para los tres profesionales.
- **Cuando** el supervisor accede a `CuadranteSupervisorPage`.
- **Entonces** la vista contiene una fila por cada profesional activo del centro y el estado muestra "Borrador".

---

**TF-AGS-02 — Las franjas de tipo atención se muestran con el color correcto**

- **Dado** `$cuadrante` publicado con una `LineaCuadrante` de `$profesional1` que incluye una franja `{"tipo": "atencion", "inicio": "09:30", "fin": "14:00"}`.
- **Cuando** se renderiza el cuadrante.
- **Entonces** la celda del profesional en ese día contiene un elemento con la clase CSS de atención y el texto "09:30–14:00".

---

**TF-AGS-03 — Un profesional itinerante muestra el nombre del otro centro en los días que no trabaja aquí**

- **Dado** `$profesional_itinerante` con perfil activo en `$centro` los lunes y miércoles, y perfil en `$centro_b` los martes, jueves y viernes.
- **Cuando** se renderiza el cuadrante semanal.
- **Entonces** las celdas del martes, jueves y viernes de ese profesional muestran el nombre de `$centro_b` y no contienen franjas.

---

**TF-AGS-04 — Una celda con línea anulada por excepción se muestra como ausencia**

- **Dado** una `LineaCuadrante` de `$profesional1` con `anulada = true` y `excepcion_id` referenciando una `ExcepcionProfesional` de tipo `baja_medica`.
- **Cuando** se renderiza esa celda.
- **Entonces** la celda muestra el indicador de ausencia (clase CSS de ausencia y texto descriptivo) en lugar de franjas.

---

**TF-AGS-05 — El supervisor puede publicar un cuadrante en borrador**

- **Dado** `$cuadrante` en estado `borrador`.
- **Cuando** el supervisor hace clic en "Publicar cuadrante" y confirma.
- **Entonces** `$cuadrante->fresh()->estado` es `publicado` y se han materializado slots para todos los días laborables del mes.

---

**TF-AGS-06 — No se puede publicar si ya existe un cuadrante publicado para ese mes**

- **Dado** un `CuadranteMes` ya en estado `publicado` para el centro y mes actuales, y un segundo cuadrante en `borrador` para el mismo período.
- **Cuando** el supervisor intenta publicar el segundo cuadrante.
- **Entonces** la interfaz muestra un mensaje de error comprensible y el estado del segundo cuadrante no cambia.

---

**TF-AGS-07 — En modo básico el cuadrante no tiene botón de publicar**

- **Dado** `$centro` con `modo_agenda = basico`.
- **Cuando** el supervisor accede a `CuadranteSupervisorPage`.
- **Entonces** el botón "Publicar cuadrante" no está presente en la topbar.

---

## Grupo B — Ausencias y reasignación

---

**TF-AGS-10 — Un profesional con ausencia sobrevenida aparece en el panel de ausencias**

- **Dado** `$profesional1` con una `ExcepcionProfesional` de tipo `baja_medica` que comienza hoy, y tres citas en estado `cancelada` con `motivo_cancelacion = 'Ausencia del profesional'` para hoy.
- **Cuando** el supervisor accede a `AusenciasSupervisorPage`.
- **Entonces** aparece un panel de alerta para `$profesional1` con las tres citas listadas en estado "Pendiente".

---

**TF-AGS-11 — El badge del sidebar refleja el número de citas pendientes**

- **Dado** el escenario del test anterior (tres citas pendientes).
- **Cuando** se renderiza el sidebar.
- **Entonces** el badge sobre "Ausencias" muestra "3".

---

**TF-AGS-12 — El panel de reasignación muestra los slots de urgencia del día primero**

- **Dado** una cita pendiente de reasignación para hoy, y dos slots en estado `bloqueado_urgencia` de `$profesional2` y `$profesional3` para hoy, más un slot ordinario `disponible` de `$profesional2` por la tarde.
- **Cuando** el supervisor abre el panel de reasignación para esa cita.
- **Entonces** los dos slots de urgencia aparecen antes que el slot ordinario y están marcados con el indicador de urgencia.

---

**TF-AGS-13 — Reasignar una cita ejecuta el servicio y actualiza la interfaz**

- **Dado** una cita en estado `cancelada` por ausencia del profesional, y un slot `bloqueado_urgencia` de `$profesional2`.
- **Cuando** el supervisor selecciona ese slot en el panel de reasignación.
- **Entonces:**
  - Se crea un `ReasignacionCita` vinculando la cita original con el nuevo slot.
  - La cita pasa a estado `confirmada` con `usuario_id = $profesional2->id`.
  - El slot pasa a estado `reservado`.
  - La fila de la cita en la interfaz muestra el estado "Reasignada" con el nombre del nuevo profesional y la hora.
  - Se genera una alerta para `$profesional2`.

---

**TF-AGS-14 — El badge desaparece cuando todas las citas están gestionadas**

- **Dado** un panel de ausencias con dos citas: una reasignada y otra pendiente.
- **Cuando** el supervisor descarta la cita pendiente.
- **Entonces** el badge del sidebar desaparece (o muestra 0) y el panel muestra el estado "todas las citas gestionadas".

---

**TF-AGS-15 — Descartar una cita la deja en estado cancelada con motivo correcto**

- **Dado** una cita pendiente de reasignación.
- **Cuando** el supervisor hace clic en "Descartar".
- **Entonces** la cita permanece en estado `cancelada` con `motivo_cancelacion` que indica descarte por supervisor, y no existe ningún `ReasignacionCita` para esa cita.

---

**TF-AGS-16 — Si no hay slots disponibles hoy, el panel lo indica explícitamente**

- **Dado** una cita pendiente de reasignación, y ningún slot `bloqueado_urgencia` ni `disponible` para el mismo tipo de atención hoy en el centro.
- **Cuando** el supervisor abre el panel de reasignación.
- **Entonces** el panel muestra el estado vacío con un mensaje de que no hay slots disponibles hoy, y no muestra ningún slot para seleccionar.

---

**TF-AGS-17 — Un no-show de ciudadano aparece en la sección correspondiente**

- **Dado** una cita en estado `no_show_ciudadano` para hoy gestionada por `$profesional1`.
- **Cuando** el supervisor accede a `AusenciasSupervisorPage`.
- **Entonces** la cita aparece en la sección "No-shows de hoy" con los botones "Liberar slot" y "Contactar". No aparece en el panel de ausencias sobrevenidas.

---

**TF-AGS-18 — La pantalla de ausencias no muestra citas de días anteriores**

- **Dado** una cita en estado `cancelada` por ausencia de ayer.
- **Cuando** el supervisor accede a `AusenciasSupervisorPage`.
- **Entonces** esa cita no aparece en la pantalla (la vista filtra por fecha = hoy).

---

## Grupo C — Excepciones de profesionales

---

**TF-AGS-20 — El supervisor puede crear una excepción de vacaciones**

- **Dado** `$profesional1` sin excepciones activas.
- **Cuando** el supervisor rellena el formulario con tipo `vacaciones`, fecha inicio 14 julio, fecha fin 1 agosto, y guarda.
- **Entonces** existe una `ExcepcionProfesional` con esos valores y `afecta_disponibilidad = true`, y aparece en la lista de excepciones activas y próximas.

---

**TF-AGS-21 — Una excepción posterior al cuadrante publicado cancela los slots afectados**

- **Dado** el cuadrante del mes actual publicado con slots para `$profesional1` los días 20–31. No hay citas confirmadas en esas fechas.
- **Cuando** el supervisor registra una excepción de `baja_medica` para `$profesional1` a partir del día 20.
- **Entonces:**
  - Las `LineaCuadrante` de los días 20–31 de `$profesional1` tienen `anulada = true`.
  - Los slots `disponible` y `bloqueado_urgencia` de esas fechas pasan a estado `anulado`.
  - Se genera una alerta al supervisor listando las citas confirmadas canceladas (ninguna en este caso, pero el mecanismo se activa).

---

**TF-AGS-22 — Una excepción con citas confirmadas genera alerta con la lista de afectadas**

- **Dado** el cuadrante publicado con dos citas `confirmada` de `$profesional1` los días 22 y 25.
- **Cuando** el supervisor registra una baja médica a partir del día 21.
- **Entonces** se genera una alerta al supervisor con las dos citas confirmadas canceladas, que aparecerá en la pantalla de Ausencias.

---

**TF-AGS-23 — El supervisor puede eliminar una excepción futura**

- **Dado** una `ExcepcionProfesional` de vacaciones con fecha inicio en el futuro.
- **Cuando** el supervisor hace clic en el botón de eliminar de esa excepción y confirma.
- **Entonces** el registro se elimina (soft delete) y desaparece de la lista.

---

**TF-AGS-24 — No se puede guardar una excepción sin fecha de inicio**

- **Dado** el formulario de nueva excepción con todos los campos rellenos excepto la fecha de inicio.
- **Cuando** el supervisor intenta guardar.
- **Entonces** el formulario muestra un error de validación en el campo de fecha de inicio y no se crea ningún registro.

---

**TF-AGS-25 — El selector de profesionales solo muestra profesionales del centro activo**

- **Dado** `$centro` con `$profesional1`, `$profesional2` y `$profesional3` activos, y `$profesional_otro_centro` adscrito a otro centro.
- **Cuando** el supervisor abre el formulario de excepciones.
- **Entonces** el select de profesional contiene exactamente los tres profesionales del centro y no contiene a `$profesional_otro_centro`.

---

## Grupo D — Eventos internos

---

**TF-AGS-30 — El supervisor puede crear un evento que bloquea slots de los convocados**

- **Dado** `$profesional1` con un slot `disponible` a las 10:00 hoy. No hay conflicto con otros eventos.
- **Cuando** el supervisor crea un `EventoAgenda` de reunión de equipo convocando a `$profesional1`, con inicio 10:00 y duración 60 min.
- **Entonces** el slot de las 10:00 de `$profesional1` pasa a estado `bloqueado_evento` y el evento aparece en la lista de eventos próximos.

---

**TF-AGS-31 — Un evento con conflicto de espacio muestra aviso al creador**

- **Dado** un espacio del centro ya reservado para otra actividad el jueves de 10:00 a 12:00.
- **Cuando** el supervisor intenta crear un nuevo evento para el mismo espacio y franja.
- **Entonces** la interfaz muestra un aviso de conflicto de espacio. El supervisor puede confirmar igualmente o cambiar el espacio.

---

**TF-AGS-32 — Un evento aparece en el cuadrante de los profesionales convocados**

- **Dado** un `EventoAgenda` que convoca a `$profesional2` el miércoles de 12:30 a 14:00.
- **Cuando** `$profesional2` accede a su vista de agenda personal.
- **Entonces** el evento aparece en su agenda para ese día y franja.

---

**TF-AGS-33 — Eliminar un evento libera los slots bloqueados**

- **Dado** un `EventoAgenda` que tiene bloqueados dos slots de `$profesional1` (10:00 y 10:45).
- **Cuando** el supervisor elimina el evento.
- **Entonces** los dos slots de `$profesional1` vuelven a estado `disponible`.

---

## Grupo E — Acceso y permisos

---

**TF-AGS-40 — Un profesional sin rol de supervisión no puede acceder a CuadranteSupervisorPage**

- **Dado** `$profesional1` con rol `intervencion` (sin `supervision`).
- **Cuando** intenta acceder directamente a la ruta del cuadrante del supervisor.
- **Entonces** recibe un error 403 o es redirigido a su propia vista de agenda.

---

**TF-AGS-41 — El supervisor solo ve los profesionales de su centro**

- **Dado** `$supervisor` adscrito a `$centro`, y `$profesional_otro` adscrito exclusivamente a `$centro_b`.
- **Cuando** el supervisor accede al cuadrante o al formulario de excepciones.
- **Entonces** `$profesional_otro` no aparece en ninguna lista ni selector.

---

**TF-AGS-42 — El link de configuración del centro apunta a Filament**

- **Dado** cualquier pantalla del módulo de agenda del supervisor.
- **Cuando** se renderiza el sidebar.
- **Entonces** el enlace "Configuración del centro" tiene un `href` que apunta a la ruta de Filament del `HorarioCentroResource` para el centro del supervisor.
