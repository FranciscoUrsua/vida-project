# Módulo: Mensajería y Alertas

> Documento de diseño funcional y modelo de datos. Forma parte de la serie de documentos de módulo de VIDA 360.
> Versión inicial: marzo 2026.

---

## 1. Propósito y alcance

Este módulo cubre dos necesidades diferenciadas que comparten infraestructura pero tienen ciclos de vida y reglas de negocio distintos:

**Sistema de alertas:** canal unidireccional generado por la propia aplicación para comunicar a los profesionales eventos que requieren su atención. Las alertas pueden dirigirse a un usuario concreto o a todos los usuarios con un rol determinado en una Unidad Organizativa (UO). Tienen dos niveles de gravedad con comportamientos distintos.

**Mensajería interna:** canal bidireccional entre profesionales del sistema, diseñado para evitar que información sensible sobre ciudadanos circule por canales externos (correo electrónico, Teams, WhatsApp u otros). Los mensajes son siempre uno a uno. Las conversaciones se organizan en hilos y pueden vincularse voluntariamente a la Historia Social de un ciudadano.

Lo que este módulo **no** cubre:

- Comunicación con ciudadanos. Ese canal pertenece al módulo de Integraciones y a la futura carpeta ciudadana.
- Mensajes broadcast a grupos de usuarios. Las alertas del sistema pueden dirigirse a un rol+UO, pero la mensajería entre profesionales es estrictamente individual.
- Delegación de mensajería por ausencia (vacaciones, baja). Se abordará en una fase posterior, en coordinación con el módulo de Agenda.
- Ejecución de la lógica de horario laboral para el cálculo de vencimientos. Esta lógica depende del módulo de Agenda; hasta su disponibilidad se usará un horario por defecto configurable desde backoffice.

---

## 2. Sistema de alertas

### 2.1 Niveles de gravedad

El sistema distingue dos niveles:

**Aviso (`aviso`):** notificación informativa. El profesional puede leerlo en cualquier momento y descartarlo sin necesidad de ejecutar ninguna acción formal. No hay plazo de reconocimiento ni escalada.

**Alerta (`alerta`):** notificación que requiere reconocimiento explícito. El profesional debe marcarla como leída mediante una acción deliberada (mínimo un clic con confirmación). Tiene un plazo máximo de reconocimiento de **4 horas en horario laboral**. Si vence sin ser reconocida, escala automáticamente al supervisor de la UO.

### 2.2 Origen de las alertas

Las alertas las genera siempre la aplicación, nunca un profesional directamente. Los módulos que pueden generarlas son, entre otros:

- **Centros:** plaza disponible en lista de espera (alerta al TSR activo del ciudadano en lista).
- **Intervención:** plazo de seguimiento vencido; apunte pendiente de aprobación previa; reconocimiento de alerta supervisada requerido.
- **Usuarios/Permisos:** acción pendiente de aprobación previa por supervisor.
- **Sistema:** cualquier evento configurable desde backoffice que deba notificarse.

El campo `origen_type` / `origen_id` identifica mediante polimorfismo el objeto concreto que generó la alerta, de forma que la interfaz puede ofrecer un enlace directo al contexto.

### 2.3 Destinatarios

Una alerta puede dirigirse a:

- **Un usuario concreto** (`destinatario_type = 'usuario'`): se identifica por su `usuario_id`.
- **Todos los usuarios con un rol en una UO** (`destinatario_type = 'rol_uo'`): se identifican por `destinatario_rol` + `destinatario_uo_id`. El sistema resuelve en tiempo real qué usuarios cumplen esa condición y crea un registro de reconocimiento individual para cada uno.

### 2.4 Ciclo de vida de una alerta

```
[generada] → pendiente → reconocida (fin)
                       ↘ [vence plazo] → escalada → reconocida por supervisor (fin)
                                                   ↘ [vence plazo supervisor] → vencida (fin, sin más escalada)
```

La escalada es de **un único nivel**: el supervisor activo de la UO del destinatario original hereda la alerta. No se crea una alerta nueva: la alerta original cambia de estado a `escalada` y el supervisor queda registrado como destinatario heredado en `alerta_reconocimientos`.

### 2.5 Cálculo del vencimiento

El campo `expira_en` no se calcula sumando 4 horas brutas al momento de creación, sino 4 horas de **horario laboral efectivo**. El cálculo lo realiza un servicio `HorarioLaboralService`. En la fase actual, este servicio consulta un horario por defecto configurable en backoffice (`catalogos_sistema`). Cuando el módulo de Agenda esté disponible, el servicio se actualizará para leer el calendario laboral real.

**Decisión pendiente registrada:** integración de `HorarioLaboralService` con el módulo de Agenda para usar el calendario laboral oficial.

---

## 3. Mensajería interna

### 3.1 Características generales

- Mensajería **uno a uno** exclusivamente. No existe la figura del grupo o canal.
- Las conversaciones se organizan en **hilos** (un hilo por par remitente-destinatario y asunto). Las respuestas se acumulan en el mismo hilo.
- El remitente puede seleccionar al destinatario por nombre o, si no lo conoce, filtrando por rol y UO. El sistema presenta el listado de usuarios que cumplen esa condición.
- Los mensajes pueden incluir **adjuntos** (documentos) y/o **enlaces a historias sociales** de ciudadanos.

### 3.2 Vinculación a la Historia Social

Cuando un mensaje contiene información relevante sobre un ciudadano, el **Trabajador Social de Referencia (TSR)** responsable de ese expediente puede decidir registrarlo en la Historia Social. Esta acción es **explícita y voluntaria**: no ocurre de forma automática.

Al registrar un mensaje en la historia:

- El TSR puede **editar el contenido** antes de registrarlo (por ejemplo, para eliminar información no relevante o reformular).
- Lo que queda registrado es la **copia editada**, no necesariamente el mensaje original íntegro.
- El registro se crea como una entrada de tipo `comunicacion_interna` en la Historia Social, con visibilidad por defecto `profesionales`. El TSR puede cambiar la visibilidad al registrar (`privada` o `profesionales`; nunca `ciudadano`, ya que es comunicación interna).
- Solo el TSR responsable del expediente puede tomar esta decisión. Ningún otro participante del hilo puede registrar el mensaje en una historia de la que no es responsable.

### 3.3 Privacidad y seguridad

- Ningún contenido de mensajes sale del sistema. El módulo existe precisamente para sustituir el uso de canales externos (correo, mensajería corporativa) para información sensible.
- Los adjuntos están sujetos a las mismas políticas de acceso y auditoría que el resto de documentos del sistema.
- El acceso al historial de mensajes de otro usuario no está permitido salvo por administración del sistema con justificación registrada.

---

## 4. Modelo de datos

### 4.1 Alertas

#### `alertas`

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | bigint PK | |
| `tipo` | enum(`aviso`, `alerta`) | Nivel de gravedad |
| `origen_type` | string | Clase del modelo generador (polimórfico) |
| `origen_id` | bigint | ID del objeto generador (polimórfico) |
| `titulo` | string | Texto corto para listados |
| `cuerpo` | text | Contenido completo de la alerta |
| `destinatario_type` | enum(`usuario`, `rol_uo`) | Tipo de destinatario |
| `destinatario_usuario_id` | bigint FK nullable | Ref. a `usuarios` si tipo = `usuario` |
| `destinatario_rol` | string nullable | Rol objetivo si tipo = `rol_uo` |
| `destinatario_uo_id` | bigint FK nullable | Ref. a `unidades_organizativas` si tipo = `rol_uo` |
| `estado` | enum(`pendiente`, `reconocida`, `escalada`, `vencida`) | Estado del ciclo de vida |
| `expira_en` | timestamp nullable | Solo para alertas; calculado en horas laborales |
| `escalada_en` | timestamp nullable | Momento en que se produjo la escalada |
| `escalada_a_usuario_id` | bigint FK nullable | Supervisor que hereda la alerta |
| `created_at` | timestamp | |

#### `alerta_reconocimientos`

Registra el reconocimiento individual de cada destinatario real, incluyendo la herencia por escalada y los descartes de avisos.

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | bigint PK | |
| `alerta_id` | bigint FK | Ref. a `alertas` |
| `usuario_id` | bigint FK | Usuario que reconoce |
| `tipo` | enum(`reconocida`, `escalada`, `descartada`) | Naturaleza del reconocimiento |
| `reconocida_en` | timestamp | |
| `ip_address` | string | Auditoría |

---

### 4.2 Mensajería

#### `mensajes_hilos`

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | bigint PK | |
| `asunto` | string | |
| `creado_por_id` | bigint FK | Ref. a `usuarios` |
| `created_at` | timestamp | |

#### `mensajes_participantes`

Un hilo tiene exactamente dos participantes (mensajería 1 a 1). Esta tabla permite gestionar el estado de lectura y archivado de forma independiente para cada participante.

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | bigint PK | |
| `hilo_id` | bigint FK | Ref. a `mensajes_hilos` |
| `usuario_id` | bigint FK | Ref. a `usuarios` |
| `rol` | enum(`remitente_inicial`, `participante`) | |
| `fecha_ultima_lectura` | timestamp nullable | Para calcular mensajes no leídos |
| `archivado_en` | timestamp nullable | El usuario puede archivar su vista del hilo |

#### `mensajes`

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | bigint PK | |
| `hilo_id` | bigint FK | Ref. a `mensajes_hilos` |
| `remitente_id` | bigint FK | Ref. a `usuarios` |
| `cuerpo` | text | Contenido del mensaje |
| `created_at` | timestamp | |

#### `mensajes_adjuntos`

Gestionada mediante `spatie/laravel-medialibrary` con `mensajes` como modelo propietario. Se define una colección `adjuntos_mensaje` con las políticas de acceso correspondientes.

#### `mensajes_referencias_ciudadano`

Enlace informativo entre un mensaje y un ciudadano. No implica registro en la historia; solo permite la navegación desde el mensaje al expediente.

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | bigint PK | |
| `mensaje_id` | bigint FK | Ref. a `mensajes` |
| `ciudadano_id` | bigint FK | Ref. a `ciudadanos` |

#### `mensajes_registro_historia`

Materializa la decisión del TSR de incorporar un mensaje a la Historia Social. Cada registro representa una entrada de tipo `comunicacion_interna` en el expediente del ciudadano.

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | bigint PK | |
| `mensaje_id` | bigint FK | Ref. al mensaje original |
| `ciudadano_id` | bigint FK | Ref. al ciudadano cuya historia se actualiza |
| `registrado_por_id` | bigint FK | Ref. a `usuarios` (debe ser el TSR responsable) |
| `cuerpo_registrado` | text | Copia editada del mensaje; puede diferir del original |
| `visibilidad` | enum(`privada`, `profesionales`) | Por defecto `profesionales` |
| `registrado_en` | timestamp | |

---

### 4.3 Diagrama conceptual

```
alertas
  ├── alerta_reconocimientos
  └── [origen polimórfico → Intervención, Centros, Sistema...]

mensajes_hilos
  ├── mensajes_participantes
  └── mensajes
        ├── mensajes_adjuntos (spatie/medialibrary)
        ├── mensajes_referencias_ciudadano → ciudadanos
        └── mensajes_registro_historia → Historia Social (Intervención)
```

---

## 5. Integración con otros módulos

| Módulo | Relación |
|---|---|
| **Usuarios/Permisos** | Las alertas usan `usuario_id`, `rol` y `unidad_organizativa_id` para resolver destinatarios y supervisor de escalada. El supervisor se obtiene consultando la tabla `usuario_uo` del módulo de Usuarios. |
| **Intervención** | Los registros en `mensajes_registro_historia` aparecen en la Historia Social como apuntes de tipo `comunicacion_interna`. Siguen las reglas de visibilidad del módulo de Intervención. |
| **Centros** | El módulo de Centros genera alertas al TSR activo cuando hay movimiento en una lista de espera. |
| **Agenda** | `HorarioLaboralService` deberá integrarse con el módulo de Agenda para usar el calendario laboral real en el cálculo de vencimientos. Hasta entonces usa horario por defecto configurable. |

---

## 6. Responsabilidades Filament / Livewire

Siguiendo el principio 4.16 del documento de principios (Filament = configuración; Livewire = operación):

**Filament (backoffice / configuración):**
- Configuración del horario laboral por defecto para cálculo de vencimientos.
- Definición de tipos de alerta configurables desde backoffice (si aplica en el futuro).
- Consulta de logs de alertas vencidas y escaladas para supervisión de calidad.

**Livewire (interfaz operativa):**
- Bandeja de alertas del profesional: listado, reconocimiento, navegación al contexto de origen.
- Bandeja de entrada de mensajes: listado de hilos, redacción, respuesta, archivo.
- Selección de destinatario por rol+UO al redactar un mensaje nuevo.
- Acción de registrar un mensaje en la Historia Social (con editor previo y selector de visibilidad).
- Indicador de alertas y mensajes no leídos visible de forma permanente en la interfaz (badge en navegación).

---

## 7. Decisiones tomadas

| Decisión | Resolución |
|---|---|
| Niveles de gravedad de alertas | Dos niveles: `aviso` (ignorable) y `alerta` (requiere reconocimiento). |
| Plazo de reconocimiento de alertas | 4 horas en horario laboral. |
| Escalada por vencimiento | Un único nivel: el supervisor de la UO hereda la alerta original. No se crea una alerta duplicada. |
| Segundo nivel de escalada | No existe. Si el supervisor tampoco reconoce en plazo, la alerta queda en estado `vencida`. |
| Mensajería grupal | Fuera de scope. La mensajería es estrictamente uno a uno. Solo las alertas del sistema pueden dirigirse a un rol+UO. |
| Registro en Historia Social | Acción explícita del TSR responsable del expediente. Solo él puede tomar esta decisión. |
| Contenido registrado en historia | Copia editable del mensaje original. Lo que se registra puede diferir del mensaje enviado. |
| Visibilidad del registro en historia | Por defecto `profesionales`. El TSR puede cambiarlo a `privada` al registrar. Nunca `ciudadano`. |
| Delegación por ausencia | Diferida. Se diseñará junto al módulo de Agenda. Gap conocido documentado. |
| Paquete para adjuntos | `spatie/laravel-medialibrary`, coherente con el resto del sistema. |
| Paquete para notificaciones | Sistema de Notifications nativo de Laravel como backbone. Las tablas propias (`alertas`, `alerta_reconocimientos`) añaden la lógica de reconocimiento, escalada y trazabilidad que el sistema nativo no cubre. |

---

## 8. Decisiones pendientes

| Elemento | Descripción |
|---|---|
| Integración `HorarioLaboralService` con Agenda | El cálculo de vencimientos en horas laborales usará un horario por defecto hasta que el módulo de Agenda esté disponible. En ese momento, el servicio deberá actualizarse para consumir el calendario laboral real. |
| Notificación externa de aviso | Pendiente de decidir si el sistema envía un correo de aviso ("tienes mensajes nuevos en VIDA") sin exponer contenido, como mecanismo de alerta para profesionales que no consultan la aplicación frecuentemente. Esta funcionalidad no expone información sensible pero requiere decisión explícita antes de implementarse. |
| Delegación de mensajería por ausencia | Cuando un profesional está de baja o vacaciones, sus alertas escalan normalmente al supervisor. Sus mensajes no tienen destinatario alternativo. Se resolverá en la fase de diseño del módulo de Agenda. |

## 9. Test funcionales

Este apartado define los tests funcionales del módulo de Mensajería y Alertas de VIDA 360.
Para cada test se especifica el requisito que verifica, las condiciones de partida, la acción a ejecutar y el resultado esperado.
La implementación (código) la realiza Claude CLI a partir de esta especificación.

### Convenciones
**Requisito:** sección del documento de diseño que origina el test.
**Dado:** estado del sistema antes de ejecutar el test (fixtures, usuarios, datos).
**Cuando:** acción que se ejecuta.
**Entonces:** resultado esperado que debe verificarse.
Los tests se agrupan por capa: primero los servicios de negocio (unit/feature sin interfaz), después los componentes Livewire (feature con interfaz).

### Grupo 1 — HorarioLaboralService

Requisito de referencia: § 2.5 del documento de diseño.
El vencimiento de una alerta se calcula en horas laborales efectivas, no en horas brutas.
El horario laboral configurado para todos estos tests es lunes a viernes, 08:00–17:00.


**T-HLS-01 — Vencimiento dentro del mismo día laboral**
Requisito: § 2.5 — cálculo en horas laborales.
Dado: una alerta creada un lunes a las 09:00.
Cuando: se calcula su fecha de expiración.
Entonces: la fecha de expiración es ese mismo lunes a las 13:00 (4 horas laborales completas).

**T-HLS-02 — Vencimiento con desbordamiento al día siguiente**
Requisito: § 2.5 — cálculo en horas laborales.
Dado: una alerta creada un lunes a las 15:30. Quedan 1,5 horas laborales en el día (hasta las 17:00). Faltan 2,5 horas por completar.
Cuando: se calcula su fecha de expiración.
Entonces: la fecha de expiración es el martes a las 10:30 (08:00 + 2,5 horas).

**T-HLS-03 — Vencimiento saltando el fin de semana**
Requisito: § 2.5 — el cómputo no incluye sábado ni domingo.
Dado: una alerta creada un viernes a las 16:00. Queda 1 hora laboral en el día. Faltan 3 horas por completar.
Cuando: se calcula su fecha de expiración.
Entonces: la fecha de expiración es el lunes siguiente a las 11:00 (08:00 + 3 horas). El sábado y el domingo no se contabilizan.

**T-HLS-04 — Alerta creada fuera de horario laboral**
Requisito: § 2.5 — el cómputo empieza en el inicio del siguiente período laboral.
Dado: una alerta creada un lunes a las 20:00, fuera del horario laboral.
Cuando: se calcula su fecha de expiración.
Entonces: la fecha de expiración es el martes a las 12:00. El cómputo comienza a las 08:00 del martes, no a las 20:00 del lunes.

**T-HLS-05 — Alerta creada en fin de semana**
Requisito: § 2.5 — el cómputo empieza en el inicio del siguiente día laboral.
Dado: una alerta creada un sábado a las 10:00.
Cuando: se calcula su fecha de expiración.
Entonces: la fecha de expiración es el lunes siguiente a las 12:00. El cómputo comienza a las 08:00 del lunes.

### Grupo 2 — AlertaService: creación 

Requisito de referencia: § 2.1 — niveles de gravedad y sus comportamientos.


**T-ALS-01 — Crear un aviso no genera fecha de expiración**
Requisito: § 2.1 — los avisos no tienen plazo de reconocimiento.
Dado: un usuario profesional existente en el sistema.
Cuando: se crea una alerta de tipo aviso dirigida a ese usuario.
Entonces: la alerta se persiste con expira_en nulo y estado pendiente.

**T-ALS-02 — Crear una alerta genera fecha de expiración en horas laborales**
Requisito: § 2.1 y § 2.5 — las alertas tienen plazo de 4 horas laborales.
Dado: un usuario profesional existente. El tiempo actual está fijado a un lunes a las 09:00.
Cuando: se crea una alerta de tipo alerta dirigida a ese usuario.
Entonces: la alerta se persiste con expira_en igual al mismo lunes a las 13:00, y estado pendiente.

### Grupo 3 — AlertaService: reconocimiento

Requisito de referencia: § 2.1 y § 2.4 — ciclo de vida.


**T-ALS-03 — Reconocer una alerta cambia su estado y registra el reconocimiento**
Requisito: § 2.1 — las alertas requieren reconocimiento explícito.
Dado: una alerta de tipo alerta en estado pendiente dirigida a un usuario concreto.
Cuando: ese usuario ejecuta la acción de reconocimiento.
Entonces: el estado de la alerta cambia a reconocida. Se crea un registro en alerta_reconocimientos con tipo reconocida, el usuario_id del usuario y la IP del request registrada.

**T-ALS-04 — Descartar un aviso crea un registro de tipo descartado**
Requisito: § 2.1 — los avisos pueden ignorarse sin acción formal, pero el descarte se registra.
Dado: una alerta de tipo aviso en estado pendiente dirigida a un usuario.
Cuando: ese usuario ejecuta la acción de descartar.
Entonces: se crea un registro en alerta_reconocimientos con tipo descartada. El tipo del reconocimiento es descartada, no reconocida.

**T-ALS-05 — No se puede reconocer dos veces la misma alerta**
Requisito: § 2.4 — integridad del ciclo de vida.
Dado: una alerta ya reconocida por un usuario (existe ya un registro en alerta_reconocimientos para ese par alerta-usuario).
Cuando: se intenta registrar un segundo reconocimiento del mismo usuario sobre la misma alerta.
Entonces: el sistema lanza un error de violación de unicidad. No se crea un segundo registro.

### Grupo 4 — AlertaService: escalada

Requisito de referencia: § 2.4 — ciclo de vida y escalada.


**T-ALS-06 — Escalar una alerta vencida asigna al supervisor de la UO**
Requisito: § 2.4 — escalada automática al supervisor de la UO del destinatario original.
Dado: una UO con un profesional (destinatario original) y un supervisor activo. Una alerta de tipo alerta en estado pendiente con expira_en ya superado, dirigida al profesional.
Cuando: se ejecuta el proceso de escalada sobre esa alerta.
Entonces: el estado de la alerta cambia a escalada. Los campos escalada_en y escalada_a_usuario_id se rellenan con el momento actual y el ID del supervisor respectivamente. Se crea un registro en alerta_reconocimientos con tipo escalada para el supervisor. La alerta original no se duplica.

**T-ALS-07 — Escalar sin supervisor disponible marca la alerta como vencida**
Requisito: § 2.4 — si no hay supervisor activo, la alerta pasa a vencida sin escalarse.
Dado: una UO sin supervisor activo asignado. Una alerta vencida dirigida a un profesional de esa UO.
Cuando: se ejecuta el proceso de escalada.
Entonces: el estado de la alerta cambia a vencida. Los campos escalada_en y escalada_a_usuario_id permanecen nulos. No se crea ningún registro en alerta_reconocimientos. Se registra un evento de nivel warning en el log de la aplicación.

**T-ALS-08 — No existe segundo nivel de escalada**
Requisito: § 2.4 — un único nivel de escalada; decisión de diseño explícita.
Dado: una alerta en estado escalada, ya asignada a un supervisor, con expira_en también superado.
Cuando: se ejecuta el proceso de escalada sobre esa alerta.
Entonces: el estado cambia a vencida. El campo escalada_a_usuario_id no se modifica (sigue apuntando al supervisor original). No se busca ni asigna ningún tercer usuario.

### Grupo 5 — AlertaService: destinatarios por rol y UO

Requisito de referencia: § 2.3 — destinatarios de tipo rol_uo.


**T-ALS-09 — Resolver destinatarios de una alerta por rol+UO**
Requisito: § 2.3 — las alertas rol_uo se dirigen a todos los usuarios con ese rol en esa UO.
Dado: una UO con dos usuarios de rol trabajador_social y uno de rol educador_social.
Cuando: se resuelven los destinatarios de una alerta dirigida a trabajador_social en esa UO.
Entonces: el resultado contiene exactamente los dos trabajadores sociales. El educador social no está incluido.

### Grupo 6 — MensajeriaService: creación de hilos

Requisito de referencia: § 3.1 — mensajería uno a uno, hilos, participantes.


**T-MSG-01 — Crear un hilo genera exactamente dos participantes con sus roles**
Requisito: § 3.1 — mensajería uno a uno; roles de participante.
Dado: dos usuarios profesionales del sistema.
Cuando: el primero crea un hilo dirigido al segundo.
Entonces: el hilo tiene exactamente dos registros en mensajes_participantes. El remitente tiene rol remitente_inicial. El destinatario tiene rol participante.

**T-MSG-02 — Crear un hilo persiste el primer mensaje correctamente**
Requisito: § 3.1 — el primer mensaje se crea al crear el hilo.
Dado: dos usuarios profesionales.
Cuando: el primero crea un hilo con un asunto y un cuerpo de mensaje.
Entonces: existe un único mensaje en ese hilo. El cuerpo del mensaje coincide con el texto enviado. El remitente_id del mensaje es el ID del usuario que creó el hilo.

**T-MSG-03 — Crear un hilo con referencias a ciudadanos las persiste correctamente**
Requisito: § 3.1 — los mensajes pueden llevar enlaces a historias sociales.
Dado: dos usuarios profesionales y un ciudadano existente en el sistema.
Cuando: se crea un hilo con ese ciudadano referenciado.
Entonces: el mensaje del hilo tiene un registro en mensajes_referencias_ciudadano apuntando al ciudadano. La referencia es un enlace informativo: no se crea ningún registro en mensajes_registro_historia.

### Grupo 7 — MensajeriaService: respuestas y lectura

**T-MSG-04 — Responder añade un mensaje al hilo existente sin crear uno nuevo**
Requisito: § 3.1 — las respuestas se acumulan en el mismo hilo.
Dado: un hilo existente entre dos usuarios con un mensaje inicial.
Cuando: el destinatario responde con un nuevo mensaje.
Entonces: el hilo tiene ahora dos mensajes. El segundo tiene como remitente_id el ID del destinatario que respondió. No se crea un hilo nuevo.

**T-MSG-05 — Marcar como leído actualiza la fecha de última lectura del participante**
Requisito: § 3.1 — gestión del estado de lectura por participante.
Dado: un hilo con mensajes no leídos. El participante destinatario tiene fecha_ultima_lectura nula.
Cuando: ese participante ejecuta la acción de marcar como leído.
Entonces: el campo fecha_ultima_lectura de ese participante en mensajes_participantes se actualiza al momento actual. El campo del otro participante no se modifica.

**T-MSG-06 — El contador de mensajes no leídos es correcto**
Requisito: § 3.1 — indicador de mensajes no leídos.
Dado: un hilo con tres mensajes enviados por el remitente. El destinatario nunca ha marcado el hilo como leído (fecha_ultima_lectura nula).
Cuando: se consulta el número de mensajes no leídos para el destinatario.
Entonces: el resultado es 3. Tras ejecutar marcar como leído, el resultado es 0.

### Grupo 8 — MensajeriaService: registro en Historia Social

Requisito de referencia: § 3.2 — vinculación voluntaria a la Historia Social.


**T-MSG-07 — El TSR responsable puede registrar un mensaje en la Historia Social**
Requisito: § 3.2 — acción explícita del TSR responsable del expediente.
Dado: un mensaje en un hilo. Un ciudadano cuyo TSR responsable es uno de los participantes del hilo.
Cuando: ese TSR ejecuta la acción de registrar el mensaje en la historia, proporcionando un texto editado y visibilidad profesionales.
Entonces: se crea un registro en mensajes_registro_historia con el ciudadano_id correcto, el registrado_por_id del TSR, el cuerpo editado (no el original) y la visibilidad indicada.

**T-MSG-08 — El cuerpo registrado en historia puede diferir del mensaje original**
Requisito: § 3.2 — el TSR puede editar el contenido antes de registrarlo.
Dado: un mensaje con un cuerpo de texto concreto. El TSR proporciona un texto diferente al registrar.
Cuando: se persiste el registro en la Historia Social.
Entonces: el campo cuerpo_registrado contiene el texto editado por el TSR. El campo cuerpo del mensaje original en la tabla mensajes no se modifica.

**T-MSG-09 — La visibilidad por defecto del registro es 'profesionales'**
Requisito: § 3.2 — visibilidad por defecto definida en el diseño.
Dado: un TSR que registra un mensaje sin especificar visibilidad explícita.
Cuando: se persiste el registro.
Entonces: el campo visibilidad del registro en mensajes_registro_historia es profesionales.

**T-MSG-10 — No se permite registrar con visibilidad 'ciudadano'**
Requisito: § 3.2 — los registros de comunicación interna nunca son visibles para el ciudadano.
Dado: un TSR que intenta registrar un mensaje con visibilidad ciudadano.
Cuando: se ejecuta la acción de registro.
Entonces: el sistema lanza una excepción de argumento inválido. No se crea ningún registro en mensajes_registro_historia.

**T-MSG-11 — Un profesional que no es el TSR responsable no puede registrar en la historia**
Requisito: § 3.2 — solo el TSR responsable del expediente puede tomar esta decisión.
Dado: un mensaje en un hilo. Un ciudadano cuyo TSR responsable es el profesional A. El profesional B (no es el TSR de ese ciudadano) intenta registrar el mensaje en la historia de ese ciudadano.
Cuando: B ejecuta la acción de registro.
Entonces: el sistema lanza una excepción de autorización. No se crea ningún registro en mensajes_registro_historia.

### Grupo 9 — Componente Livewire: BandejaAlertas

Verifica la interfaz operativa de alertas. Requisito de referencia: §§ 2.1, 2.3.


**T-LW-01 — El usuario solo ve sus propias alertas**
Requisito: § 2.3 — aislamiento entre usuarios.
Dado: dos usuarios. Cada uno tiene una alerta pendiente dirigida individualmente.
Cuando: el primer usuario abre la bandeja de alertas.
Entonces: ve su alerta. No ve la alerta del otro usuario.

**T-LW-02 — El usuario ve las alertas dirigidas a su rol en su UO**
Requisito: § 2.3 — destinatarios rol_uo.
Dado: un usuario con rol trabajador_social en una UO concreta. Una alerta dirigida a trabajador_social en esa UO.
Cuando: ese usuario abre la bandeja.
Entonces: la alerta aparece en su bandeja aunque no esté dirigida individualmente a él.

**T-LW-03 — Reconocer una alerta desde la bandeja actualiza su estado**
Requisito: § 2.1 — reconocimiento explícito desde la interfaz.
Dado: un usuario con una alerta pendiente.
Cuando: el usuario ejecuta la acción de reconocimiento sobre esa alerta en la bandeja.
Entonces: la alerta desaparece del listado de pendientes. En base de datos, el estado de la alerta es reconocida.

**T-LW-04 — Un usuario no puede reconocer una alerta que no le pertenece**
Requisito: § 3.3 — control de acceso.
Dado: una alerta dirigida al usuario A. El usuario B está autenticado.
Cuando: B intenta reconocer la alerta de A (por ejemplo, enviando el ID de la alerta ajena).
Entonces: el sistema devuelve un error de autorización. El estado de la alerta no cambia.

**T-LW-05 — Las alertas se ordenan por urgencia**
Requisito: § 2.1 — los avisos son menos urgentes que las alertas.
Dado: un usuario con tres notificaciones: una alerta que vence en 1 hora, una alerta que vence en 3 horas y un aviso sin fecha de vencimiento.
Cuando: el usuario abre la bandeja.
Entonces: el orden en pantalla es: alerta de 1 hora → alerta de 3 horas → aviso. Los avisos aparecen siempre después de todas las alertas con fecha de vencimiento.

### Grupo 10 — Componente Livewire: BandejaMensajes e HiloMensajes

Requisito de referencia: §§ 3.1, 3.2, 3.3.


**T-LW-06 — La bandeja muestra solo los hilos en los que el usuario participa**
Requisito: § 3.3 — control de acceso; § 3.1 — mensajería uno a uno.
Dado: tres usuarios. A y B tienen un hilo. B y C tienen otro hilo. El usuario A está autenticado.
Cuando: A abre la bandeja de mensajes.
Entonces: A ve el hilo con B. A no ve el hilo entre B y C.

**T-LW-07 — Un hilo archivado no aparece en la bandeja principal**
Requisito: § 3.1 — los usuarios pueden archivar su vista de un hilo.
Dado: un usuario con un hilo activo. Ese usuario archiva el hilo (su registro en mensajes_participantes tiene archivado_en relleno).
Cuando: el usuario abre la bandeja.
Entonces: el hilo archivado no aparece en la vista principal del usuario que lo archivó. El otro participante del hilo sí lo sigue viendo en su bandeja.

**T-LW-08 — El botón de registrar en historia solo es visible para el TSR responsable**
Requisito: § 3.2 — solo el TSR responsable puede registrar en la historia.
Dado: un mensaje en un hilo que referencia a un ciudadano. El TSR responsable de ese ciudadano es el participante A. El profesional B participa en otro hilo que también referencia al mismo ciudadano, pero B no es el TSR responsable.
Cuando: A abre su hilo → el control para registrar en historia es visible. Cuando B abre su hilo → el control no es visible.
Entonces: la visibilidad del control depende exclusivamente de si el usuario autenticado es el TSR responsable del ciudadano referenciado, no de si participa en el hilo.

### Grupo 11 — Componente Livewire: NuevoMensaje

Requisito de referencia: § 3.1 — selección de destinatario y validaciones.


**T-LW-09 — Filtrar destinatarios por rol y UO devuelve solo los usuarios que cumplen ambas condiciones**
Requisito: § 3.1 — el remitente puede buscar destinatarios por rol y UO.
Dado: una UO con dos trabajadores sociales y un educador social. Una segunda UO con otro trabajador social.
Cuando: el remitente filtra por rol trabajador_social y la primera UO.
Entonces: el listado muestra los dos trabajadores sociales de esa UO. El educador social no aparece. El trabajador social de la segunda UO tampoco aparece.

**T-LW-10 — El formulario no se envía sin asunto**
Requisito: § 3.1 — el asunto es obligatorio.
Dado: un formulario con destinatario seleccionado y cuerpo relleno, pero asunto vacío.
Cuando: el usuario intenta enviar.
Entonces: el sistema muestra un error de validación sobre el campo asunto. No se crea ningún hilo ni mensaje.

**T-LW-11 — El formulario no se envía sin cuerpo de mensaje**
Requisito: § 3.1 — el cuerpo es obligatorio.
Dado: un formulario con destinatario y asunto rellenos, pero cuerpo vacío.
Cuando: el usuario intenta enviar.
Entonces: el sistema muestra un error de validación sobre el campo cuerpo. No se crea ningún hilo ni mensaje.

**T-LW-12 — El formulario no se envía sin destinatario**
Requisito: § 3.1 — el destinatario es obligatorio.
Dado: un formulario con asunto y cuerpo rellenos, pero sin destinatario seleccionado.
Cuando: el usuario intenta enviar.
Entonces: el sistema muestra un error de validación sobre el campo destinatario. No se crea ningún hilo ni mensaje.

**T-LW-13 — Un usuario no puede enviarse un mensaje a sí mismo**
Requisito: § 3.3 — restricción de integridad básica de la mensajería.
Dado: un usuario que selecciona su propio perfil como destinatario.
Cuando: intenta enviar el formulario.
Entonces: el sistema muestra un error de validación sobre el campo destinatario. No se crea ningún hilo.

### Resumen de cobertura
GrupoTestsRequisito cubiertoHorarioLaboralServiceT-HLS-01 a T-HLS-05§ 2.5 — cálculo de vencimiento en horas laboralesAlertaService: creaciónT-ALS-01, T-ALS-02§ 2.1 — niveles de gravedadAlertaService: reconocimientoT-ALS-03 a T-ALS-05§ 2.1, § 2.4 — ciclo de vidaAlertaService: escaladaT-ALS-06 a T-ALS-08§ 2.4 — escalada y sus límitesAlertaService: destinatariosT-ALS-09§ 2.3 — destinatarios rol+UOMensajeriaService: hilosT-MSG-01 a T-MSG-03§ 3.1 — creación de hilosMensajeriaService: respuestasT-MSG-04 a T-MSG-06§ 3.1 — respuestas y lecturaMensajeriaService: historiaT-MSG-07 a T-MSG-11§ 3.2 — registro en Historia SocialLivewire: BandejaAlertasT-LW-01 a T-LW-05§ 2.1, § 2.3 — interfaz de alertasLivewire: BandejaMensajesT-LW-06 a T-LW-08§ 3.1, § 3.2, § 3.3 — interfaz de mensajeríaLivewire: NuevoMensajeT-LW-09 a T-LW-13§ 3.1, § 3.3 — formulario de redacciónTotal31 tests


*Documento elaborado en fase de diseño del proyecto. Versión inicial: marzo 2026.*
