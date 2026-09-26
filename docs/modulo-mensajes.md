# Módulo: Mensajería y Alertas

> Documento de diseño funcional y modelo de datos. Forma parte de la serie de documentos de módulo de VIDA 360.
> Versión inicial: marzo 2026. Actualizado con decisiones de interfaz: septiembre 2026.

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
- Adjuntos en mensajes. Los documentos pertenecen a la Historia Social y deben gestionarse desde allí. Los mensajes pueden incluir enlaces de contexto a elementos de la historia, pero no archivos adjuntos propios.

---

## 2. Sistema de alertas

### 2.1 Niveles de gravedad

El sistema distingue dos niveles:

**Aviso (`aviso`):** notificación informativa. El profesional puede leerlo en cualquier momento y descartarlo sin necesidad de ejecutar ninguna acción formal. No hay plazo de reconocimiento ni escalada. Los avisos se muestran en la bandeja con un contador numérico; su nivel de intrusión visual es moderado.

**Alerta (`alerta`):** notificación que requiere reconocimiento explícito. El profesional debe marcarla como leída mediante una acción deliberada (mínimo un clic con confirmación). Tiene un plazo máximo de reconocimiento de **4 horas en horario laboral**. Si vence sin ser reconocida, escala automáticamente al supervisor de la UO. Las alertas son el único tipo que activa las notificaciones intrusivas de toast.

### 2.2 Origen de las alertas

Las alertas las genera siempre la aplicación, nunca un profesional directamente. Los módulos que pueden generarlas son, entre otros:

- **Centros:** plaza disponible en lista de espera (alerta al TSR activo del ciudadano en lista).
- **Intervención:** plazo de seguimiento vencido; apunte pendiente de aprobación previa; reconocimiento de alerta supervisada requerido.
- **Usuarios/Permisos:** acción pendiente de aprobación previa por supervisor.
- **Sistema:** cualquier evento configurable desde backoffice que deba notificarse.

El campo `origen_type` / `origen_id` identifica mediante polimorfismo el objeto concreto que generó la alerta, de forma que la interfaz puede ofrecer un enlace directo al contexto.

### 2.3 Avisos generados por supervisores

Además de las alertas generadas por el sistema, los usuarios con rol supervisor pueden crear manualmente **avisos** dirigidos a sus subordinados en su propia UO. Estas son las reglas:

- Solo un supervisor puede crear avisos manuales. Los usuarios sin ese rol no ven ni tienen acceso a esta funcionalidad.
- El aviso se dirige exclusivamente a subordinados en la propia UO del supervisor. No es posible enviar un aviso a una UO ajena.
- La comunicación es **unidireccional**: el destinatario no puede responder al aviso. Se muestra con una etiqueta visual clara ("Aviso del supervisor") para diferenciarlo de las alertas automáticas del sistema.
- Estos avisos siguen el mismo ciclo de vida que cualquier otro aviso: el destinatario puede descartarlos sin plazo, y no generan escalada.

### 2.4 Destinatarios

Una alerta puede dirigirse a:

- **Un usuario concreto** (`destinatario_type = 'usuario'`): se identifica por su `usuario_id`.
- **Todos los usuarios con un rol en una UO** (`destinatario_type = 'rol_uo'`): se identifican por `destinatario_rol` + `destinatario_uo_id`. Es una alerta **a un colectivo**: van dirigidas a **cada** miembro (p. ej. todos los trabajadores sociales de un centro).

**Reconocimiento por destinatario** (decisión de 2026-09-26): al crear la alerta se fijan sus destinatarios (`alerta_destinatarios`), uno por cada usuario que en ese momento tiene el rol y adscripción vigente en la UO. **Cada destinatario debe reconocer la alerta o cerrar el aviso por su cuenta**; que uno lo haga no lo retira a los demás. Quien entra en la UO después no la recibe. Si el colectivo está vacío, la alerta queda `vencida` y se deja aviso en el log.

Queda fuera, por ahora, la alerta **a cualquier persona de un colectivo** (basta con que la atienda un trabajador social del centro, cualquiera): ver `BACKLOG.md`.

### 2.5 Ciclo de vida de una alerta

```
[generada] → pendiente → reconocida (fin)
                       ↘ [vence plazo] → escalada → reconocida por supervisor (fin)
                                                   ↘ [vence plazo supervisor] → vencida (fin, sin más escalada)
```

El ciclo se aplica **a cada destinatario** (`alerta_destinatarios.estado`). La escalada es de **un único nivel** y también es individual: al vencer el plazo, la parte de cada destinatario que no la reconoció pasa al supervisor activo de la UO (nunca a sí mismo, si el destinatario es supervisor); si no hay otro supervisor, esa parte queda `vencida`. No se crea una alerta nueva: el evento queda en `alerta_reconocimientos` (tipo `escalada`, `usuario_id` = supervisor).

El `estado` de la alerta resume el de sus destinatarios: `pendiente` mientras alguno lo esté; si no, el peor desenlace (`vencida` > `escalada` > `reconocida`).

El supervisor consulta y atiende las partes escaladas en una **pantalla de control de alertas**, que puede ser la misma o parecida a la de envío de avisos a su equipo (decisión de 2026-09-26; pendiente de implementar).

### 2.6 Cálculo del vencimiento

El campo `expira_en` no se calcula sumando 4 horas brutas al momento de creación, sino 4 horas de **horario laboral efectivo**. El cálculo lo realiza un servicio `HorarioLaboralService`. En la fase actual, este servicio consulta un horario por defecto configurable en backoffice (`catalogos_sistema`). Cuando el módulo de Agenda esté disponible, el servicio se actualizará para leer el calendario laboral real.

**Decisión pendiente registrada:** integración de `HorarioLaboralService` con el módulo de Agenda para usar el calendario laboral oficial.

---

## 3. Mensajería interna

### 3.1 Características generales

- Mensajería **uno a uno** exclusivamente. No existe la figura del grupo o canal.
- Las conversaciones se organizan en **hilos** (un hilo por par remitente-destinatario y asunto). Las respuestas se acumulan en el mismo hilo.
- El remitente puede seleccionar al destinatario por nombre o, si no lo conoce, filtrando por cargo (rol) y UO. El sistema presenta el listado de usuarios que cumplen esa condición con nombre completo y UO principal.
- Los mensajes pueden incluir **enlaces de contexto** a elementos de la Historia Social (ciudadano, valoración, intervención) pero **no adjuntan documentos**. Los documentos deben gestionarse desde la Historia Social.

### 3.2 Vinculación a la Historia Social

Cuando un mensaje contiene información relevante sobre un ciudadano, el **Trabajador Social de Referencia (TSR)** responsable de ese expediente puede decidir registrarlo en la Historia Social. Esta acción es **explícita y voluntaria**: no ocurre de forma automática.

Al registrar un mensaje en la historia:

- El TSR puede **editar el contenido** antes de registrarlo (por ejemplo, para eliminar información no relevante o reformular).
- Lo que queda registrado es la **copia editada**, no necesariamente el mensaje original íntegro.
- El registro se crea como una entrada de tipo `comunicacion_interna` en la Historia Social, con visibilidad por defecto `profesionales`. El TSR puede cambiar la visibilidad al registrar (`privada` o `profesionales`; nunca `ciudadano`, ya que es comunicación interna).
- Solo el TSR responsable del expediente puede tomar esta decisión. Ningún otro participante del hilo puede registrar el mensaje en una historia de la que no es responsable.

### 3.3 Privacidad y seguridad

- Ningún contenido de mensajes sale del sistema. El módulo existe precisamente para sustituir el uso de canales externos (correo, mensajería corporativa) para información sensible.
- No hay adjuntos en mensajes. Esta restricción es deliberada y de seguridad: los documentos deben permanecer en la Historia Social del ciudadano, gestionados bajo las políticas de acceso y auditoría del módulo de Intervención.
- El acceso al historial de mensajes de otro usuario no está permitido salvo por administración del sistema con justificación registrada.

---

## 4. Interfaz de usuario

### 4.1 Menú de navegación

El módulo ocupa **tres entradas separadas** en el menú lateral de la aplicación:

- **Alertas** — Con tinte visual rojo/naranja cuando hay alertas pendientes de reconocimiento, para diferenciarse visualmente del resto de entradas del menú.
- **Avisos** — Con badge numérico cuando hay avisos no descartados.
- **Mensajes** — Con badge numérico cuando hay mensajes no leídos.

Las tres entradas abren la misma pantalla unificada (componente Livewire `BandejaAlertasYMensajes`) con la pestaña correspondiente ya seleccionada. Esto simplifica la implementación manteniendo la claridad visual de tres puntos de entrada diferenciados.

**Implementación (2026-09-26):** la pantalla está publicada en los dos interfaces operativos, cada uno con su layout y su menú: Intervención (`/intervencion/mensajes/{alertas|avisos|mensajes}`) y Supervisión (`/supervision/bandeja/{...}`). La supervisión se incluyó porque las alertas dirigidas al rol `supervision` no tenían dónde verse. Los contadores del menú salen de `ContadoresBandejaService` y los menús se refrescan cada 60 segundos. Hacen el papel del `BadgeNotificaciones` descrito en las instrucciones, que se ha retirado.

### 4.2 Notificaciones toast para alertas

Las alertas pendientes de reconocimiento se muestran además como **toast notifications persistentes** en la parte superior de la pantalla. El comportamiento es deliberadamente intrusivo porque la urgencia lo requiere:

- **Al entrar en sesión:** si el usuario tiene alertas pendientes al autenticarse, los toasts aparecen inmediatamente.
- **En tiempo real:** cuando llega una nueva alerta mientras el usuario tiene la sesión activa, el toast aparece sin necesidad de que el usuario navegue a ninguna pantalla.
- **Reiteración cada 30 minutos:** si el usuario minimiza o ignora un toast sin reconocer la alerta, el toast reaparece automáticamente cada 30 minutos. Esto continúa hasta que la alerta sea reconocida, escalada por vencimiento o quede en estado `vencida`.
- **No auto-dismiss:** los toasts de alertas no desaparecen solos. El usuario debe tomar una acción explícita (reconocer la alerta o minimizar el toast conscientemente).
- Cada alerta genera su propio toast individual. Si hay múltiples alertas pendientes, hay múltiples toasts apilados.

Los avisos (incluyendo los del supervisor) **no generan toasts**. Solo se reflejan en el contador del menú.

### 4.3 Panel de redacción flotante global

El módulo expone un **panel de redacción flotante** que puede lanzarse desde cualquier punto de la aplicación, no solo desde la bandeja de mensajes.

**Comportamiento general:**
- Es un componente Livewire invocable globalmente (registrado en el layout principal de la aplicación).
- Al lanzarse desde la bandeja de mensajes, abre sin contexto previo y el usuario rellena todos los campos manualmente.

**Comportamiento con contexto:**
- Cuando se lanza desde otra pantalla (expediente de un ciudadano, una valoración, una intervención), el elemento que se estaba viendo se pasa como contexto al panel.
- El campo "elemento vinculado" se pre-rellena automáticamente con ese elemento.
- Si el elemento de contexto tiene un autor identificable (por ejemplo, el profesional que creó una valoración o intervención), ese profesional se sugiere como destinatario mediante un **chip gris en estado "no confirmado"**. El usuario debe confirmar o sustituir explícitamente la sugerencia.

**Razón del chip gris:**
La sugerencia de destinatario es orientativa. Es frecuente querer enviar un mensaje sobre el trabajo de alguien a una tercera persona (supervisor, colega). El chip gris indica que la sugerencia existe pero no es una selección definitiva; el usuario debe confirmarla para que el campo quede en estado válido.

**Campos del panel de redacción:**
- Destinatario (búsqueda por nombre o por rol+UO; chip sugerido en gris hasta confirmar)
- Asunto
- Cuerpo del mensaje
- Elemento vinculado (pre-rellenado desde contexto si aplica; editable)

No hay campo de adjuntos.

---

## 5. Modelo de datos

### 5.1 Alertas

#### `alertas`

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | bigint PK | |
| `tipo` | enum(`aviso`, `alerta`) | Nivel de gravedad |
| `origen_type` | string | Clase del modelo generador (polimórfico); valor `supervisor_manual` para avisos creados por supervisores |
| `origen_id` | bigint | ID del objeto generador (polimórfico); ID del supervisor para avisos manuales |
| `titulo` | string | Texto corto para listados |
| `cuerpo` | text | Contenido completo de la alerta |
| `destinatario_type` | enum(`usuario`, `rol_uo`) | Tipo de destinatario |
| `destinatario_usuario_id` | bigint FK nullable | Ref. a `usuarios` si tipo = `usuario` |
| `destinatario_rol` | string nullable | Rol objetivo si tipo = `rol_uo` |
| `destinatario_uo_id` | bigint FK nullable | Ref. a `unidades_organizativas` si tipo = `rol_uo` |
| `estado` | enum(`pendiente`, `reconocida`, `escalada`, `vencida`) | Resumen del estado de sus destinatarios (ver §2.5) |
| `expira_en` | timestamp nullable | Solo para alertas; calculado en horas laborales; null para avisos |
| `escalada_en` | timestamp nullable | Momento de la primera escalada (el detalle está en `alerta_destinatarios`) |
| `escalada_a_usuario_id` | bigint FK nullable | Supervisor de la primera escalada |
| `created_at` | timestamp | |

#### `alerta_destinatarios`

Una fila por cada persona que debe reconocer la alerta, con su propio estado. Se crean al generar la alerta.

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | bigint PK | |
| `alerta_id` | bigint FK | Ref. a `alertas` |
| `usuario_id` | bigint FK | Destinatario. Único por alerta |
| `estado` | enum(`pendiente`, `reconocida`, `escalada`, `vencida`) | Estado de su parte |
| `atendida_en` | timestamp nullable | Cuándo la reconoció o descartó |
| `escalada_en` | timestamp nullable | Cuándo se escaló su parte |
| `escalada_a_usuario_id` | bigint FK nullable | Supervisor que recibió su parte |

#### `alerta_reconocimientos`

Registro de eventos (solo alta): reconocimientos, descartes de avisos y escaladas. Sin índice único: un supervisor puede recibir varias escaladas de la misma alerta.

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | bigint PK | |
| `alerta_id` | bigint FK | Ref. a `alertas` |
| `alerta_destinatario_id` | bigint FK nullable | Parte de la alerta a la que se refiere |
| `usuario_id` | bigint FK | Usuario que reconoce, o supervisor que recibe la escalada |
| `tipo` | enum(`reconocida`, `escalada`, `descartada`) | Naturaleza del reconocimiento |
| `reconocida_en` | timestamp | |
| `ip_address` | string | Auditoría |

---

### 5.2 Mensajería

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

### 5.3 Diagrama conceptual

```
alertas
  ├── alerta_destinatarios (una fila por destinatario, con su estado)
  ├── alerta_reconocimientos (eventos)
  └── [origen polimórfico → Intervención, Centros, Sistema, supervisor_manual]

mensajes_hilos
  ├── mensajes_participantes
  └── mensajes
        ├── mensajes_referencias_ciudadano → ciudadanos
        └── mensajes_registro_historia → Historia Social (Intervención)
```

---

## 6. Integración con otros módulos

| Módulo | Relación |
|---|---|
| **Usuarios/Permisos** | Las alertas usan `usuario_id`, `rol` y `unidad_organizativa_id` para resolver destinatarios y supervisor de escalada. El supervisor se obtiene consultando la tabla `usuario_uo` del módulo de Usuarios. La creación de avisos manuales valida que el usuario tiene rol supervisor y que los destinatarios son subordinados en su UO. |
| **Intervención** | Los registros en `mensajes_registro_historia` aparecen en la Historia Social como apuntes de tipo `comunicacion_interna`. Siguen las reglas de visibilidad del módulo de Intervención. El panel de redacción flotante puede pre-rellenar el elemento vinculado con una valoración o intervención del expediente activo. |
| **Centros** | El módulo de Centros genera alertas al TSR activo cuando hay movimiento en una lista de espera. |
| **Agenda** | `HorarioLaboralService` deberá integrarse con el módulo de Agenda para usar el calendario laboral real en el cálculo de vencimientos. Hasta entonces usa horario por defecto configurable. |

---

## 7. Responsabilidades Filament / Livewire

Siguiendo el principio del proyecto (Filament = configuración; Livewire = operación):

**Filament (backoffice / configuración):**
- Configuración del horario laboral por defecto para cálculo de vencimientos.
- Definición de tipos de alerta configurables desde backoffice (si aplica en el futuro).
- Consulta de logs de alertas vencidas y escaladas para supervisión de calidad.

**Livewire (interfaz operativa):**
- Bandeja unificada (`BandejaAlertasYMensajes`) con tres pestañas: Alertas, Avisos, Mensajes.
- Toast notifications persistentes para alertas con lógica de reiteración cada 30 minutos (`AlertaToast`).
- Panel de redacción flotante invocable globalmente (`PanelRedaccion`), con lógica de contexto y chip de destinatario sugerido.
- Bandeja de alertas del profesional: listado, reconocimiento, navegación al contexto de origen.
- Bandeja de entrada de mensajes: listado de hilos, redacción, respuesta, archivo.
- Selección de destinatario por nombre o por rol+UO al redactar un mensaje nuevo.
- Acción de registrar un mensaje en la Historia Social (con editor previo y selector de visibilidad).
- Indicador permanente de alertas, avisos y mensajes pendientes: contadores en las tres entradas del menú lateral (Intervención y Supervisión), con polling cada 60 segundos (sustituye a `BadgeNotificaciones`).
- Formulario de creación de aviso manual para supervisores (con validación de rol y restricción a subordinados de la propia UO).

---

## 8. Decisiones tomadas

| Decisión | Resolución |
|---|---|
| Niveles de gravedad de alertas | Dos niveles: `aviso` (ignorable) y `alerta` (requiere reconocimiento). |
| Plazo de reconocimiento de alertas | 4 horas en horario laboral. |
| Escalada por vencimiento | Un único nivel, por destinatario: el supervisor de la UO hereda la parte de quien no reconoció. No se crea una alerta duplicada. |
| Reconocimiento con varios destinatarios | Cada destinatario de una alerta o aviso a un colectivo debe reconocerla o cerrarla por su cuenta. Los destinatarios se fijan al crear la alerta (2026-09-26). |
| Control de alertas del supervisor | El supervisor tiene una pantalla de control de alertas (escaladas y estado de las de su equipo), igual o parecida a la de envío de avisos (2026-09-26). |
| Segundo nivel de escalada | No existe. Si el supervisor tampoco reconoce en plazo, la alerta queda en estado `vencida`. |
| Mensajería grupal | Fuera de scope. La mensajería es estrictamente uno a uno. Solo las alertas del sistema pueden dirigirse a un rol+UO. |
| Registro en Historia Social | Acción explícita del TSR responsable del expediente. Solo él puede tomar esta decisión. |
| Contenido registrado en historia | Copia editable del mensaje original. Lo que se registra puede diferir del mensaje enviado. |
| Visibilidad del registro en historia | Por defecto `profesionales`. El TSR puede cambiarlo a `privada` al registrar. Nunca `ciudadano`. |
| Delegación por ausencia | Diferida. Se diseñará junto al módulo de Agenda. Gap conocido documentado. |
| Adjuntos en mensajes | No se permiten adjuntos. Los documentos deben gestionarse desde la Historia Social. Los mensajes solo pueden contener enlaces de contexto a elementos de la historia. |
| Paquete para notificaciones | Sistema de Notifications nativo de Laravel como backbone. Las tablas propias (`alertas`, `alerta_reconocimientos`) añaden la lógica de reconocimiento, escalada y trazabilidad que el sistema nativo no cubre. |
| Navegación al módulo | Tres entradas de menú separadas (Alertas, Avisos, Mensajes) que abren la misma pantalla con la pestaña correspondiente pre-seleccionada. |
| Toast para alertas | Toasts persistentes (no auto-dismiss) que aparecen al login y en tiempo real. Reaparecen cada 30 minutos si el usuario no actúa. Solo para alertas, no para avisos. |
| Avisos manuales de supervisor | Los supervisores pueden crear avisos dirigidos a sus subordinados en su propia UO. Comunicación unidireccional, sin posibilidad de respuesta. |
| Panel de redacción flotante | Componente global invocable desde cualquier pantalla. Con contexto, pre-rellena el elemento vinculado y sugiere el autor como destinatario con un chip gris (no confirmado). El usuario debe confirmar o cambiar la sugerencia. |

---

## 9. Decisiones pendientes

| Elemento | Descripción |
|---|---|
| Alerta a cualquier persona de un colectivo | Alerta que basta con que atienda un miembro cualquiera del colectivo (p. ej. un trabajador social del centro). Aplazada el 2026-09-26: de momento todas las alertas a un colectivo exigen el reconocimiento de cada destinatario. Ver `BACKLOG.md`. |
| Integración `HorarioLaboralService` con Agenda | El cálculo de vencimientos en horas laborales usará un horario por defecto hasta que el módulo de Agenda esté disponible. En ese momento, el servicio deberá actualizarse para consumir el calendario laboral real. |
| Notificación externa de aviso | Pendiente de decidir si el sistema envía un correo de aviso ("tienes mensajes nuevos en VIDA") sin exponer contenido, como mecanismo de alerta para profesionales que no consultan la aplicación frecuentemente. Esta funcionalidad no expone información sensible pero requiere decisión explícita antes de implementarse. |
| Delegación de mensajería por ausencia | Cuando un profesional está de baja o vacaciones, sus alertas escalan normalmente al supervisor. Sus mensajes no tienen destinatario alternativo. Se resolverá en la fase de diseño del módulo de Agenda. |
| WebSockets para notificaciones en tiempo real | Actualmente las alertas en tiempo real se detectan por polling del badge (60 segundos) y los toasts se gestionan en cliente. Si la escala crece hacia los 5.000 usuarios simultáneos, evaluar Laravel Echo + Laravel Reverb para notificaciones push verdaderas en tiempo real. No implementar antes de que la escala lo justifique. |

---

*Documento elaborado en fase de diseño del proyecto. Versión inicial: marzo 2026. Actualizado: septiembre 2026.*
