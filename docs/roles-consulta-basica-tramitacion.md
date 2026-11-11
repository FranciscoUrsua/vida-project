# Roles de atención administrativa: consulta básica y tramitación — VIDA 360

Estado: `consulta_basica` con diseño funcional cerrado, sin implementar. `tramitacion` diferido: capacidades de alto nivel definidas, detalle pendiente. Última revisión: 30 septiembre 2026 Documentos relacionados: `docs/modulo-usuarios-permisos.md` (roles 5 y 7), `docs/modulo-ciudadania.md` (capas de datos, ficha), `docs/modulo-citas.md` (§6 bis y §7), `docs/modulo-documentos.md`.

## 1. Propósito

Hasta ahora la superficie operativa de VIDA se ha diseñado para `intervencion` y `supervision`. Este documento fija qué ve y qué hace el personal que atiende en ventanilla, por teléfono o en la gestión administrativa, para que su interfaz se construya sin exponer contenido de la Historia Social.

Principio de partida: quien informa y cita necesita saber quién es la persona, cómo contactarla, con quién vive, si está siendo atendida y quién la atiende. No necesita saber por qué.

## 2. Ámbito por UO: límite de operación, no de consulta

El ámbito de UO de estos roles limita lo que hacen, no a quién pueden identificar.

- Consulta: pueden buscar a cualquier ciudadano y abrir su ficha (§3), tenga o no Historia Social en su UO. Es necesario para informar a la persona y orientarla al centro que le corresponda.
- Operación: dar, reprogramar o cancelar citas, subir documentos y modificar datos se hace en su UO. Las citas solo en centros de su UO (`modulo-citas.md`, RN-05 ter).
- Auditoría: todo acceso a una ficha queda registrado, como hoy (`AuditarAccesoCiudadano`).

## 3. Consulta básica (`consulta_basica`)

Perfil: ordenanzas, personal de información y control, personal auxiliar de programas temporales de empleo en servicios de gestión directa, personal auxiliar de gestión indirecta.

### 3.1 Qué ve en la ficha del ciudadano

| Bloque | Acceso | Detalle |
|---|---|---|
| Datos de identificación y contacto (Capa 1) | Lectura y edición | Nombre, fecha de nacimiento, sexo, domicilio, teléfono, email, canal preferido, documentos de identidad |
| Unidad de convivencia | Lectura | Convivientes actuales, como ya muestra la ficha en modo solo lectura |
| Relaciones | Lectura y edición | Relaciones vigentes con su tipo, con alta, edición y cierre. Los tipos de relación son datos objetivos (cónyuge, pareja de hecho, hijo/a…) |
| Historia Social | Solo existencia | Si existe, su estado (abierta, en seguimiento, cerrada) y el profesional de referencia, si lo hay. Nada más: ni número de historia, ni fechas, ni planes, ni apuntes, ni timeline |
| Citas | Historial completo | Pasadas y programadas, de cualquier centro, con etiqueta pública del tipo y centro (`modulo-citas.md` §6 bis) |
| Documentos | Solo los suyos del día | Ver §3.3 |
| Prestaciones | Sin acceso | El widget de prestaciones no se muestra |
| Situación social (Capa 2) | Sin acceso | — |
| Accesos recientes al expediente | Sin acceso | Como hoy |

### 3.2 Colectivos especialmente protegidos

Para ciudadanos de colectivos especialmente protegidos, la ficha de consulta básica muestra solo datos objetivos: los mismos bloques de §3.1. No se muestra información que permita deducir la protección ni su motivo.

El banner de colectivo protegido no se muestra a `consulta_basica`. El banner solo aparece a profesionales con rol `intervencion` cuando intentan acceder a datos sensibles (situación social, datos de salud, contenido de la Historia Social). Mostrarlo en la ficha de identificación revelaría por sí mismo que la persona pertenece a un colectivo protegido.

### 3.3 Documentos que sube

Puede subir documentos a la carpeta del ciudadano o a un proceso administrativo de solicitud. No ve la carpeta completa del ciudadano.

- Ve solo los documentos que ha subido él mismo, durante el día natural de la subida. El plazo del día permite corregir errores detectados en el momento o por el profesional en una cita posterior ese mismo día. Pasado ese día, dejan de aparecer en su vista.
- Dentro de ese plazo puede:
  - sustituir el fichero;
  - cambiar los metadatos: tipo de documento, destino (carpeta o solicitud) y personas vinculadas;
  - retirar el documento.
- Retirar es una baja lógica: el documento deja de estar disponible pero queda en el historial con quién lo retiró, cuándo y por qué (principio 4.2). Sustituir el fichero también conserva la versión anterior.
- Las reglas generales de formato y tamaño son las de `modulo-documentos.md` (PDF, tamaño limitado).

### 3.4 Qué hace

- Buscar ciudadanos y abrir su ficha.
- Dar de alta ciudadanos, con el control de duplicados del flujo existente (`AltaCiudadano`).
- Modificar datos de identificación y contacto.
- Crear, editar y cerrar relaciones entre ciudadanos.
- Gestionar citas según `modulo-citas.md` §7: bandeja de citación, dar citas, reprogramar y cancelar (no las propias), identificar citas externas pendientes.
- Subir documentos y gestionar los suyos del día (§3.3).

No edita la unidad de convivencia: su composición pertenece a la pantalla de intervención (`modulo-ciudadania.md` §3.4).

## 4. Tramitación (`tramitacion`) — diferido

Perfil: auxiliares administrativos, administrativos, jefes de negociado.

La implantación se difiere: hay varias cuestiones por definir y otras prioridades. Queda registrado lo decidido y lo pendiente.

### 4.1 Decidido

- Todo lo que ve `consulta_basica` en la ficha (§3.1), y además las capacidades del rol 5 en `modulo-usuarios-permisos.md`.
- Gestiona citas con los mismos permisos que `consulta_basica` (`modulo-citas.md` §7). Recibe los avisos de solicitud creada de su centro.
- Sin acceso a apuntes ni a contenido social o clínico de la Historia Social.

### 4.2 Pendiente

- Nota de tramitación. Las anotaciones sobre una solicitud de servicio pertenecen a Intervención (`modulo-centros.md` §3.3), pero tramitación no tiene acceso a apuntes. Hay que decidir dónde vive la nota de un cambio de estado y quién la ve.
- Entrada de peticiones. Cómo llegan a tramitación las peticiones de apertura, cierre o reactivación de Historia Social y de incorporación a la unidad de convivencia.
- Documentación requerida por prestación. El catálogo de prestaciones no define hoy qué documentos exige cada una.
- Visibilidad de documentos y prestaciones. Qué parte de la carpeta y del resumen de prestaciones ve tramitación.
- Traslados y fusiones. Flujo, aprobación y efectos sobre citas, documentos e Historia Social.

El lienzo de diseño incluye una primera propuesta de pantallas (T1 a T4), que servirá de base cuando se retome.

## 5. Permisos atómicos

Lista orientativa, a cerrar en las instrucciones de implementación (`modulo-usuarios-permisos.md` §4.4 y §6):

| Permiso | Uso | `consulta_basica` | `tramitacion` ⏸ |
|---|---|---|---|
| `ciudadano.ver_ficha` | Abrir la ficha de cualquier ciudadano | ✅ | ✅ |
| `ciudadano.crear` | Alta de ciudadanos con control de duplicados | ✅ | ✅ |
| `ciudadano.editar` | Editar Capa 1 y relaciones | ✅ | ✅ |
| `historia.ver_estado` | Ver existencia, estado y profesional de referencia (nuevo) | ✅ | ✅ |
| `cita.ver_historial` | Historial de citas en la ficha (nuevo) | ✅ | ✅ |
| `documento.subir` | Subir a carpeta o a solicitud | ✅ | ✅ |
| `documento.gestionar_propios` | Ver, sustituir, recatalogar y retirar los propios del día (nuevo) | ✅ | ✅ |

## 6. Impacto en otros documentos

`modulo-usuarios-permisos.md`:

- ROL 5: marcar como implantación diferida y remitir a este documento. Añadir que gestiona citas con los permisos de `modulo-citas.md` §7.
- ROL 7: sustituir las capacidades por §3 de este documento, incluidas el alta de ciudadanos y la edición de relaciones. Aclarar en "Ámbito por UO" que el ámbito limita la operación, no la consulta de la ficha (§2).
- §4.4: añadir los permisos nuevos de §5.

`modulo-ciudadania.md`:

- §2 (Capa 1): ya incluye `consulta_basica` y `tramitacion`. Añadir que la ficha es accesible sin ámbito de UO para estos roles.
- §3.3 (Relaciones): donde dice "rol tramitación incluido", añadir `consulta_basica`.
- Alta de ciudadanos: `consulta_basica` puede dar altas.
- Colectivos protegidos: la ficha no muestra el banner de protegido; el banner es solo para `intervencion` al acceder a datos sensibles.
- Descripción de `FichaCiudadanoPage`: bloques visibles por rol según §3.1. Ocultar el widget de prestaciones para `consulta_basica`. Añadir el bloque de historial de citas y el bloque de estado de Historia Social con profesional de referencia.

`modulo-documentos.md`:

- §1: la disponibilidad "para cualquier profesional con acceso al expediente" tiene la excepción de `consulta_basica` (§3.3).
- §2.1: la entidad Documento necesita soportar retirada lógica (quién, cuándo, motivo), versiones del fichero al sustituir y el vínculo con una solicitud administrativa.

`modulo-citas.md`: actualizado (RN-05 ter, §6 bis, §7, §10).

`docs/design-system/README.md` y `SKILL.md`: la regla "Protected records: show the protected banner; never hide the status" pasa a aplicarse solo a `intervencion` cuando accede a datos sensibles. En las vistas de identificación y contacto, y para roles sin acceso a datos sensibles, el banner no se muestra.

## 7. Decisiones tomadas

| Tema | Decisión |
|---|---|
| Ámbito de UO de `consulta_basica` | Limita la operación; la ficha de cualquier ciudadano es consultable |
| Intervención visible para `consulta_basica` | Existencia y estado de la Historia Social, y profesional de referencia |
| Citas en la ficha | Historial completo, pasadas y programadas, con etiqueta pública del tipo y centro |
| Alta de ciudadanos | `consulta_basica` puede dar altas |
| Relaciones | `consulta_basica` las ve y las edita; son datos objetivos |
| Unidad de convivencia | `consulta_basica` solo la ve |
| Colectivos protegidos | `consulta_basica` ve solo datos objetivos y no ve el banner de protegido |
| Banner de protegido | Solo para `intervencion` al intentar acceder a datos sensibles (sociales, de salud…) |
| Documentos de `consulta_basica` | Solo los suyos, durante el día de la subida: sustituir, recatalogar y retirar |
| `tramitacion` | Gestiona citas. Resto del rol diferido |
