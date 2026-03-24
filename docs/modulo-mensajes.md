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

---

*Documento elaborado en fase de diseño del proyecto. Versión inicial: marzo 2026.*
