# Tests funcionales — Citas

**TF-CIT-01 a TF-CIT-43**, más la revisión de PF-05.1, PF-05.5 y PF-06.2 de `docs/modulo-agenda.md`.
Diseño funcional: `docs/modulo-citas.md`.
Instrucciones de implementación: `docs/instrucciones-cli/2026-09-citas-implementacion.md`.

> Especificaciones de comportamiento, no código. Claude CLI implementa cada test siguiendo los patrones del proyecto. Al terminar, actualizar la tabla de estado del final y la de `docs/modulo-agenda.md`.

---

## Convenciones

- **Framework:** PHPUnit con atributo `#[Test]`. No usar Pest.
- **Base de datos:** PostgreSQL (`vida_testing`). No usar SQLite.
- **Ubicación:** `Modules/Agenda/tests/Feature/Citas/`, un fichero por grupo (`TiposCitaTest`, `SolicitudCitaTest`, `BusquedaHuecosTest`, `CitacionTest`, `PermisosCitaTest`, `AtencionCitaTest`, `CierreCitaJobTest`, `CanalExternoCitaTest`, `IntegridadCitaTest`). Los tests de timeline, en el módulo Intervención.
- **Patrón:** Dado / Cuando / Entonces.
- **Negativo obligatorio:** cada test de restricción debe fallar si se elimina la validación que protege. Compruébalo al menos una vez por grupo comentando temporalmente la protección, y anótalo en el CHANGELOG. Los marcados **[negativo]** son los críticos.
- **Fechas:** `Carbon::setTestNow()` fijo (un martes laborable) en todos los tests.
- **Mensajes y alertas:** se comprueban contra las tablas del Módulo Mensajes, no con fakes de notificación.
- **Canal externo:** adaptador mock; se verifica lo que registra.

## Actores y datos reutilizados

Definir en un trait `CitasTestSetup`:

- `$centro` — modo de agenda `estandar`, `dias_ausencia_prolongada = 15`, `plazos_urgencia = {"ordinaria": 20, "preferente": 7, "urgente": 2}`, `dias_aviso_cierre_supervisor = 3`.
- `$admin` — `adm_sistema`.
- `$supervisor` — `supervision` del centro.
- `$consulta` — solo `consulta_basica` del centro.
- `$auxiliar` — `intervencion` y `consulta_basica` del centro, con slots propios.
- `$tsr` y `$tsr2` — solo `intervencion`, mismo perfil, con slots en el centro.
- `$tsrOtroPerfil` — `intervencion`, otro perfil, con slots.
- `$maria` — ciudadana con Historia Social en el centro y `$tsr` como referencia.
- `$juan` — ciudadano sin Historia Social.
- `$tipoSeguimiento` — tipo de cita, `nombre = "Seguimiento PIA violencia de género"`, `etiqueta_publica = "Entrevista"`, `herramienta = entrevista_seguimiento`, compatible con `$slotEntrevista`.
- `$tipoInformacion` — `herramienta = atencion`, `requiere_historia_social = false`.
- `$slotEntrevista` / `$slotGrupal` — tipos de slot; solo el primero compatible con `$tipoSeguimiento`.
- Slots materializados para las próximas tres semanas, con un slot `bloqueado_urgencia` por profesional y día.

---

## Grupo A — Tipos de cita

**TF-CIT-01 — Solo administración gestiona tipos de cita**
- **Dado** `$admin` y `$tsr`.
- **Cuando** cada uno intenta crear un tipo de cita en Filament.
- **Entonces** `$admin` lo crea (con entrada de auditoría y versión); `$tsr` recibe acceso denegado y no se crea nada.

**TF-CIT-02 — El código es inmutable si hay citas del tipo**
- **Dado** `$tipoSeguimiento` con una cita.
- **Cuando** se intenta cambiar su `codigo`.
- **Entonces** se rechaza. **Negativo:** en un tipo sin citas sí se permite.

**TF-CIT-03 — Nombre interno y etiqueta pública según rol**
- **Dado** `$tipoSeguimiento`.
- **Cuando** se resuelve el nombre para `$consulta`, `$tsr` y `$supervisor`.
- **Entonces** `$consulta` obtiene "Entrevista"; `$tsr` y `$supervisor`, el nombre interno. **[negativo]**

---

## Grupo B — Solicitudes y bandeja

**TF-CIT-04 — Intervención crea una solicitud**
- **Dado** `$tsr` autenticado.
- **Cuando** crea una solicitud para `$maria`, tipo `$tipoSeguimiento`, urgencia `ordinaria`, destino `referencia`, con motivo.
- **Entonces** existe en estado `pendiente` en la bandeja de `$centro`, con evento `solicitud_creada` (actor `$tsr`) y alerta a `$consulta` y `$auxiliar`. El motivo está cifrado en base de datos.

**TF-CIT-05 — Fecha límite calculada por urgencia**
- **Dado** una solicitud `preferente` sin `no_despues_de`.
- **Cuando** se crea.
- **Entonces** `no_despues_de` es la fecha 7 días laborables después de hoy según el horario del centro (salta fines de semana).

**TF-CIT-06 — Destino "profesional concreto" exige profesional**
- **Dado** una solicitud con `destino = profesional_concreto` y sin `profesional_destino_id`.
- **Cuando** se intenta crear.
- **Entonces** se rechaza y no se crea nada.

**TF-CIT-07 — Una solicitud solo la toma una persona**
- **Dado** una solicitud `pendiente`.
- **Cuando** `$consulta` y `$auxiliar` intentan tomarla a la vez (dos llamadas consecutivas sobre el mismo registro).
- **Entonces** la primera la pasa a `en_gestion`; la segunda falla. Hay un único evento `solicitud_tomada`.

**TF-CIT-08 — Desistir exige motivo y es final**
- **Dado** una solicitud `en_gestion`.
- **Cuando** se intenta desistir sin motivo, después con motivo, y después volver a tomarla.
- **Entonces** lo primero se rechaza; lo segundo la deja `desistida` con evento; lo tercero se rechaza. No hay contador ni límite de intentos.

**TF-CIT-09 — La bandeja no expone el motivo ni el nombre interno**
- **Dado** la solicitud de TF-CIT-04.
- **Cuando** `$consulta` abre la bandeja.
- **Entonces** ve ciudadana, "Entrevista", urgencia, destino y observaciones para quien cita; el HTML no contiene el motivo ni "violencia de género". **[negativo]**

---

## Grupo C — Búsqueda de huecos

**TF-CIT-10 — Profesional concreto**
- **Dado** una solicitud con destino `$tsr2`.
- **Cuando** se buscan huecos.
- **Entonces** todas las propuestas son de `$tsr2`, con `modo_asignacion = profesional_concreto`.

**TF-CIT-11 — Referencia disponible va primero**
- **Dado** una solicitud de `$maria` con destino `referencia`; `$tsr` tiene hueco dentro de la ventana, más tarde que `$tsr2`.
- **Cuando** se buscan huecos.
- **Entonces** las primeras propuestas son de `$tsr` con `modo_asignacion = referencia`.

**TF-CIT-12 — Ausencia prolongada de la referencia**
- **Dado** `$tsr` con una baja registrada de 20 días que cubre la ventana.
- **Cuando** se buscan huecos para `$maria` con destino `referencia`.
- **Entonces** las propuestas son de `$tsr2` con `modo_asignacion = sustituto`, y la referencia de `$maria` sigue siendo `$tsr`. Nunca se proponen profesionales de otro perfil.

**TF-CIT-13 — Ausencia corta no deriva a sustitutos**
- **Dado** `$tsr` con 3 días de ausencia y hueco dentro de la ventana fuera de esos días.
- **Cuando** se buscan huecos con destino `referencia`.
- **Entonces** hay propuestas de `$tsr` con `modo_asignacion = referencia` y ninguna con `sustituto`.

**TF-CIT-14 — Urgencia ordinaria no consume slots de urgencia**
- **Dado** una solicitud `ordinaria`.
- **Cuando** se buscan huecos.
- **Entonces** ninguna propuesta es un slot `bloqueado_urgencia`. **[negativo]**

**TF-CIT-15 — Urgencia urgente puede consumir slots de urgencia**
- **Dado** una solicitud `urgente` y solo un slot `bloqueado_urgencia` libre en la ventana.
- **Cuando** se busca y se cita en ese slot.
- **Entonces** la cita se crea, el slot pasa a `reservado` y el supervisor recibe alerta.

**TF-CIT-16 — Tipos de slot incompatibles quedan fuera**
- **Dado** `$tsr2` con solo slots `$slotGrupal` libres.
- **Cuando** se buscan huecos para `$tipoSeguimiento`.
- **Entonces** no se propone ningún slot de `$tsr2`.

**TF-CIT-17 — Sin huecos**
- **Dado** que no hay slots compatibles en la ventana.
- **Cuando** se buscan huecos.
- **Entonces** el resultado es vacío y la solicitud no cambia de estado.

---

## Grupo D — Dar, reprogramar y cancelar

**TF-CIT-18 — Citar desde una solicitud**
- **Dado** una solicitud `en_gestion` y una propuesta de hueco.
- **Cuando** `$consulta` cita.
- **Entonces** la cita queda `confirmada` con solicitud, tipo, modalidad y `modo_asignacion`; el slot queda `reservado`; la solicitud queda `citada` con `resuelta_en`; hay evento `cita_creada`.

**TF-CIT-19 — Cita directa en ventanilla es atómica**
- **Dado** `$consulta` en el formulario de cita directa para `$juan`.
- **Cuando** confirma con un slot válido, y en un segundo caso con un slot que otro usuario acaba de reservar.
- **Entonces** en el primero existen solicitud (`citada`) y cita; en el segundo no existe ninguna de las dos.

**TF-CIT-20 — Reprogramar crea una cita nueva**
- **Dado** una cita `confirmada` para mañana.
- **Cuando** `$consulta` la reprograma a otro slot a petición del ciudadano.
- **Entonces** la original queda `reprogramada` y su slot vuelve a `disponible`; la nueva está `confirmada`, con `cita_anterior_id` y la misma solicitud; ambas tienen evento `cita_reprogramada` con `pedido_por = ciudadano`.

**TF-CIT-21 — Una cita reprogramada no se reprograma otra vez**
- **Dado** la cita original de TF-CIT-20.
- **Cuando** se intenta reprogramar de nuevo.
- **Entonces** se rechaza. La cadena solo crece desde la cita vigente. Tras tres reprogramaciones sucesivas, la cadena se reconstruye completa desde la última.

**TF-CIT-22 — Cancelar exige motivo y quién lo pide**
- **Dado** una cita `confirmada`.
- **Cuando** se intenta cancelar sin motivo, y después con motivo y `pedido_por = centro`.
- **Entonces** lo primero se rechaza; lo segundo la cancela con evento y slot liberado según la hora.

**TF-CIT-23 — Cancelar abriendo una solicitud nueva**
- **Dado** una cita `confirmada`.
- **Cuando** se cancela con la opción de abrir solicitud; en otro caso, sin ella.
- **Entonces** en el primero existe una solicitud `pendiente` con mismo tipo, urgencia y destino y `solicitud_anterior_id`; en el segundo no se crea ninguna.

**TF-CIT-24 — Cambios sobre citas externas se notifican**
- **Dado** una cita con `origen = api_externa`.
- **Cuando** se reprograma y, en otro caso, se cancela.
- **Entonces** el adaptador mock registra la notificación saliente y hay evento `notificacion_externa_enviada`.

---

## Grupo E — Permisos

**TF-CIT-25 — Intervención no da citas** **[negativo]**
- **Dado** `$tsr` autenticado.
- **Cuando** intenta citar, directamente o desde una solicitud.
- **Entonces** recibe 403 y no se crea cita, solicitud ni evento.

**TF-CIT-26 — Un profesional no reprograma ni cancela sus citas** **[negativo]**
- **Dado** una cita en la agenda de `$tsr`.
- **Cuando** `$tsr` intenta reprogramarla y cancelarla.
- **Entonces** ambas acciones reciben 403. La agenda de `$tsr` no muestra esas acciones.

**TF-CIT-27 — Auxiliar con doble rol: cita en su agenda, pero no cancela las suyas** **[negativo]**
- **Dado** `$auxiliar`.
- **Cuando** cita a `$juan` en un slot propio; después intenta cancelar esa cita; después cancela una cita de `$tsr2`.
- **Entonces** la primera y la tercera acción se permiten; la segunda recibe 403.

**TF-CIT-28 — Pedir cambio al supervisor**
- **Dado** una cita en la agenda de `$tsr`.
- **Cuando** `$tsr` pide un cambio con un texto.
- **Entonces** `$supervisor` tiene un mensaje de `$tsr` con enlace a la cita, y la cita tiene evento `cambio_solicitado`. La cita no cambia.

---

## Grupo F — Atención y cierre

**TF-CIT-29 — El apunte completa la cita**
- **Dado** una cita `confirmada` de `$maria` con `$tsr`, hoy.
- **Cuando** `$tsr` registra una entrevista con esa cita.
- **Entonces** el apunte tiene `cita_id`; la cita queda `completada` con `completada_en`; hay un evento `cita_completada`. No existe acción "completar" en la interfaz.

**TF-CIT-30 — Un segundo apunte no duplica el cierre**
- **Dado** la cita completada de TF-CIT-29.
- **Cuando** `$tsr` registra una valoración vinculada a la misma cita.
- **Entonces** el apunte se vincula; la cita no cambia y sigue habiendo un solo `cita_completada`.

**TF-CIT-31 — No se vincula un apunte a una incomparecencia** **[negativo]**
- **Dado** una cita en `no_show_ciudadano`.
- **Cuando** `$tsr` intenta vincular un apunte; después `$supervisor` lo hace con motivo.
- **Entonces** lo primero se rechaza; lo segundo se permite.

**TF-CIT-32 — La cita debe ser del mismo ciudadano**
- **Dado** una cita de `$juan`.
- **Cuando** se intenta vincular a un apunte de `$maria`.
- **Entonces** se rechaza.

**TF-CIT-33 — El registro de atención completa la cita**
- **Dado** una cita de `$juan` (sin Historia Social) con `$tipoInformacion`.
- **Cuando** se crea el registro de atención vinculado.
- **Entonces** la cita queda `completada`.

**TF-CIT-34 — Propuesta de vinculación desde la ficha**
- **Dado** una cita de `$maria` con `$tsr` hoy.
- **Cuando** `$tsr` abre una herramienta desde `CiudadanoPage` sin pasar por la agenda.
- **Entonces** aparece la casilla de vinculación marcada. Sin cita hoy, no aparece.

**TF-CIT-35 — Incomparecencia**
- **Dado** una cita `confirmada` de `$maria` con `$tsr`.
- **Cuando** `$tsr` marca la incomparecencia.
- **Entonces** la cita queda `no_show_ciudadano` con evento, y el timeline de `$maria` no muestra nada nuevo.

**TF-CIT-36 — Acompañantes**
- **Dado** una cita atendida de `$maria`.
- **Cuando** `$tsr` registra un familiar sin ficha en VIDA y a `$juan` como miembro de la unidad de convivencia.
- **Entonces** existen dos acompañantes; el nombre del familiar está cifrado en base de datos; hay evento `acompanantes_registrados`. No se crea ninguna cita para `$juan`.

---

## Grupo G — Citas sin cerrar

**TF-CIT-37 — Pendiente de cierre**
- **Dado** una cita `confirmada` de esta mañana sin apunte ni incomparecencia.
- **Cuando** se ejecutan `CitaCierreJob` y después `SlotExpirationJob`.
- **Entonces** la cita sigue `confirmada` con `pendiente_cierre = true`, evento y alerta a `$tsr`; su slot **no** pasa a `no_ocupado`. **[negativo]**

**TF-CIT-38 — Aviso al supervisor una sola vez**
- **Dado** una cita pendiente de cierre desde hace 4 días laborables.
- **Cuando** `CitaCierreJob` se ejecuta dos días seguidos.
- **Entonces** `$supervisor` recibe una sola alerta. La cita sigue sin cerrarse.

---

## Grupo H — Canal externo

**TF-CIT-39 — Idempotencia**
- **Dado** una cita externa con `referencia_externa = CP-123`.
- **Cuando** llega otra petición con la misma referencia.
- **Entonces** se devuelve la cita existente y no se crea otra ni se escribe evento.

**TF-CIT-40 — Persona no identificada**
- **Dado** una petición externa con un documento que no existe en VIDA.
- **Cuando** se recibe.
- **Entonces** la cita se crea con `ciudadano_id` nulo, datos de identificación cifrados y visible en la bandeja como pendiente de identificar. No se crea ningún ciudadano.

**TF-CIT-41 — Identificación posterior**
- **Dado** la cita de TF-CIT-40.
- **Cuando** `$consulta` la asocia a un ciudadano tras buscarlo o darlo de alta.
- **Entonces** la cita tiene `ciudadano_id` y evento `ciudadano_identificado`.

---

## Grupo I — Integridad, eventos de agenda y timeline

**TF-CIT-42 — El historial es inmutable** **[negativo]**
- **Dado** un `CitaEvento`.
- **Cuando** se intenta modificar y borrar desde el modelo y con una sentencia SQL directa; y se ejecuta la purga de `audits` con una fecha que lo incluiría.
- **Entonces** las cuatro operaciones fallan o no lo afectan; el evento sigue intacto.

**TF-CIT-43 — Eventos de agenda y timeline**
- **Dado** una mesa de caso que referencia a `$maria` y un apunte de coordinación enlazado a ella; y un apunte de entrevista vinculado a una cita reprogramada una vez.
- **Cuando** `$consulta` ve el evento en la agenda y `$tsr` ve el timeline de `$maria`.
- **Entonces** `$consulta` ve el título y el tipo del evento, pero no a `$maria`. En el timeline, el resumen de ambas tarjetas es igual que sin cita; al desplegarlas aparecen las secciones *Coordinación* y *Cita* (origen, fechas, una reprogramación, acompañantes).

---

## Revisión de tests de Agenda

**PF-05.1 — Creación de cita desde canal interno.** Pasa a: `$consulta` o `$supervisor` crean la cita a través de `CitacionService`; la cita queda `confirmada`, `origen = interno`, con solicitud, y el slot `reservado`. Un usuario solo con `intervencion` recibe 403 (cubierto también por TF-CIT-25).

**PF-05.5 — Marcado de cita como completada.** Pasa a: la cita se completa al vincular un apunte (TF-CIT-29). Se elimina la acción manual y la creación de apunte automático por `genera_apunte_automatico`.

**PF-06.2 — Cancelación anticipada por el ciudadano.** Pasa a: la registra `$consulta` o `$supervisor` con `pedido_por = ciudadano`; el profesional de la cita no puede hacerlo (TF-CIT-26).

---

## Tabla de estado (rellenar al implementar)

| Grupo | Tests | Estado |
|---|---|---|
| A — Tipos de cita | TF-CIT-01..03 | ✅ `TiposCitaTest` |
| B — Solicitudes y bandeja | TF-CIT-04..09 | ✅ `SolicitudCitaTest`; 09 en `InterfazCitasTest` |
| C — Búsqueda de huecos | TF-CIT-10..17 | ✅ `BusquedaHuecosTest` |
| D — Dar, reprogramar, cancelar | TF-CIT-18..24 | ✅ `CitacionTest` |
| E — Permisos | TF-CIT-25..28 | ✅ `PermisosCitaTest` |
| F — Atención y cierre | TF-CIT-29..36 | ✅ `AtencionCitaTest`; 34 en `InterfazCitasTest` |
| G — Citas sin cerrar | TF-CIT-37..38 | ✅ `CierreCitaJobTest` |
| H — Canal externo | TF-CIT-39..41 | ✅ `CanalExternoCitaTest` |
| I — Integridad y timeline | TF-CIT-42..43 | ✅ 42 en `IntegridadCitaTest`; 43 en `InterfazCitasTest` (agenda y ficha) |
| Revisión Agenda | PF-05.1, PF-05.5, PF-06.2 | ✅ `Citas/RevisionAgendaTest` (los originales se retiran) |
