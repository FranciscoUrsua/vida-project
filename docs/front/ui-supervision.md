# UI Supervisión — Especificación funcional de pantallas

> **Referencia:** `docs/front/ui-supervision.md`
> **Rol:** `supervision`
> **Módulo Laravel:** `Modules/Supervision`
> **Documento relacionado:** `docs/front/ui-intervencion.md`

---

## 1. Principios generales

El módulo de supervisión comparte el layout operativo base con el módulo de intervención: topbar con título y menú de usuario a la derecha, sidebar izquierdo con el menú de pantallas, y espacio de trabajo en el resto de la pantalla.

El rol `supervision` no tiene funcionalidades de intervención directa con ciudadanos. Su espacio de trabajo es la gestión del centro: equipo, agenda, actividades, plazas y vigilancia de la integridad de los accesos a datos. La interfaz prioriza la visión de conjunto sobre el detalle de expediente.

**Principios específicos de este módulo:**

- La analítica queda fuera del alcance del módulo operativo. Los indicadores del dashboard son de gestión operativa del día a día, no métricas históricas ni tendencias.
- El supervisor no crea ni edita expedientes de ciudadanos. El acceso a la ficha de un ciudadano es de solo lectura y se realiza a través del módulo de ciudadanía compartido.
- Las aprobaciones pendientes tienen prioridad visual: el badge del sidebar y la pantalla de aprobaciones son los puntos de máxima urgencia de la interfaz.
- Las funcionalidades condicionales (plazas, accesos protegidos) se muestran o se ocultan según la configuración del centro. No se muestran con estado deshabilitado; directamente no aparecen si no aplican.

---

## 2. Layout y navegación

### 2.1 Sidebar

Ocho ítems de navegación en el orden siguiente:

| Ítem | Ruta | Badge | Condicional |
|---|---|---|---|
| Inicio | `supervision.inicio` | — | No |
| Cuadrante del centro | `supervision.cuadrante` | — | No |
| Actividades grupales | `supervision.actividades` | — | No |
| Plazas | `supervision.plazas` | — | Solo centros con plazas |
| Mi equipo | `supervision.equipo` | — | No |
| Accesos | `supervision.auditoria` | — | No |
| Aprobaciones | `supervision.aprobaciones` | Nº pendientes | No |
| Configuración | `supervision.configuracion` | — | No |

El badge de aprobaciones muestra el número total de solicitudes pendientes (accesos protegidos + asignaciones de rol). Si es cero, no se muestra. El polling del sidebar se realiza cada 300 s, igual que en el módulo de intervención.

El sidebar no incluye acceso a Mensajes en esta fase. Se incorporará en una entrega posterior reutilizando el componente `BuzonPage` con los permisos extendidos que correspondan al rol `supervision`.

### 2.2 Topbar

Idéntico al del módulo de intervención: nombre de la aplicación a la izquierda, menú de usuario con avatar de iniciales, nombre, rol activo y «Cerrar sesión» a la derecha.

### 2.3 Rutas protegidas

Todas las rutas del módulo requieren `auth + role:supervision`. El prefijo de URL es `/supervision`. La ruta raíz `/supervision` redirige a `/supervision/inicio`.

---

## 3. Pantalla: Inicio (dashboard)

**Ruta:** `supervision.inicio` → `/supervision/inicio`
**Componente:** `InicioPage`

### 3.1 Propósito

Pantalla de aterrizaje. Ofrece una visión rápida del estado operativo del centro para tomar decisiones en el momento: detectar desequilibrios de carga, anticipar problemas de agenda y ver si hay alertas pendientes de resolución. No es analítica: no hay gráficas históricas ni tendencias.

### 3.2 Estructura de la pantalla

La pantalla se divide en tres zonas verticales:

**Zona superior — indicadores de gestión**

Cuatro tarjetas de indicador en fila, estilo KPI compacto:

- **Ratio personas/profesional**: número de historias sociales activas dividido entre profesionales con agenda activa. Umbral configurable por centro; se resalta en ámbar si supera el umbral.
- **Espera media para primera cita**: media de días entre la solicitud y la primera cita concedida en los últimos 30 días. Umbral configurable.
- **Profesionales sin agenda activa hoy**: count de profesionales adscritos al centro que no tienen ningún slot disponible en la jornada actual (ausencias, días libres, etc.).
- **Actividades abiertas a inscripción**: número de actividades grupales con inscripción activa y fecha de cierre en los próximos 7 días. Badge de urgencia si alguna cierra en menos de 48 h.

Todos los indicadores son computados en tiempo real desde la base de datos. Los umbrales se leen de `organizacion_configuracion`.

**Zona central — cuadrante compacto del día**

Vista de solo lectura de la agenda del centro para el día actual. Muestra todos los profesionales en filas y las franjas horarias en columnas. Cada celda indica si el slot está disponible, ocupado o bloqueado, sin detalle de ciudadano. El supervisor puede hacer clic en «Ver cuadrante completo» para ir a `supervision.cuadrante`.

**Zona inferior — aprobaciones pendientes**

Si hay solicitudes pendientes, se muestra una lista compacta (máximo 5 ítems visibles, enlace «Ver todas» a `supervision.aprobaciones`). Cada ítem muestra el tipo de solicitud, el profesional solicitante y la antigüedad. Si no hay pendientes, la zona no se renderiza.

### 3.3 Comportamiento condicional

- Si el centro no tiene plazas, el indicador de «Ocupación de plazas» no aparece.
- Si el centro atiende a colectivos protegidos, se añade un indicador de «Accesos a expedientes protegidos pendientes de revisión».

---

## 4. Pantalla: Cuadrante del centro

**Ruta:** `supervision.cuadrante` → `/supervision/cuadrante`
**Componente:** `CuadrantePage`

### 4.1 Propósito

Vista de gestión de la agenda del centro en su conjunto. El supervisor ve la disponibilidad de todos los profesionales de su UO, puede introducir excepciones (ausencias, bloqueos de franja) y gestionar reasignaciones de citas cuando un profesional no puede atender.

Esta pantalla consume el módulo de agenda. No duplica lógica: lee y escribe a través de los servicios del módulo `Agenda`. La vista de cuadrante del centro listada en `docs/modulo-agenda.md §5` como componente Livewire de supervisión es esta pantalla.

### 4.2 Vista principal del cuadrante

Vista semanal por defecto. Cada fila es un profesional; cada columna, una franja horaria. Las celdas muestran el estado del slot: disponible, ocupado (cita), bloqueado (evento o excepción), urgencia.

Controles de navegación: semana anterior / siguiente, botón «Hoy», selector de profesional individual para filtrar la vista.

El modo de agenda del centro (`basico`, `estandar`, `avanzado`) determina qué controles de edición están activos, tal como se especifica en `docs/modulo-agenda.md`.

### 4.3 Sub-flujo: Excepciones y ausencias

El supervisor puede marcar una franja horaria de un profesional concreto como no disponible. Formulario modal con los campos:

- Profesional (selector)
- Fecha de inicio y fecha de fin
- Motivo: `ausencia_justificada` / `baja_medica` / `formacion` / `reunion_interna` / `otros`
- Notas (texto libre, opcional)

Si la franja afectada tiene citas confirmadas, el sistema muestra un aviso con el número de citas afectadas. La excepción no se bloquea, pero se registra la necesidad de reasignación.

El campo `origen` de `ExcepcionProfesional` se establece a `supervisor` para las excepciones creadas desde esta pantalla.

### 4.4 Sub-flujo: Reasignación de citas

Cuando un slot tiene citas confirmadas y el profesional no puede atender, el supervisor puede iniciar una reasignación. El sistema muestra los slots de urgencia disponibles en el centro para esa franja horaria. La selección final es siempre humana; el sistema asiste pero no decide. Se crea un registro en `reasignaciones_cita` con `realizada_por_id` = supervisor.

---

## 5. Pantalla: Actividades grupales

**Ruta:** `supervision.actividades` → `/supervision/actividades`
**Componente:** `ActividadesPage`

### 5.1 Propósito

Programar y hacer seguimiento de actividades grupales: cursos, talleres, grupos de apoyo y similares. El supervisor crea la actividad y gestiona su ciclo de vida; los profesionales de intervención gestionan la inscripción de ciudadanos concretos desde su propio módulo.

### 5.2 Vista principal

Lista de actividades del centro con columnas: nombre, tipo, responsable, fechas, aforo (inscritos/máximo), estado (`programada` / `en_curso` / `finalizada` / `cancelada`).

Filtros: por estado, por tipo de actividad, por responsable, por fecha.

Botón «Nueva actividad» que abre el formulario de creación.

### 5.3 Sub-flujo: Nueva actividad / Editar actividad

Formulario con los campos:

- Nombre
- Tipo (select desde catálogo `tipos_actividad`)
- Descripción
- Responsable (profesional de la UO)
- Fecha de inicio y fecha de fin
- Sesiones: lista de sesiones con fecha, hora de inicio y duración. Al menos una sesión obligatoria.
- Aforo máximo
- Inscripción abierta (boolean)
- Fecha límite de inscripción (visible solo si inscripción abierta)
- Espacio físico (opcional, si el centro tiene espacios configurados)
- Notas internas

Las actividades se crean en estado `programada`. El estado `en_curso` se activa automáticamente al llegar la fecha de la primera sesión.

### 5.4 Sub-flujo: Inscripción y asistencia

Al hacer clic en una actividad de la lista, se accede a su pantalla de detalle con dos pestañas:

**Pestaña «Inscripciones»**

Lista de ciudadanos inscritos con: nombre, profesional responsable, fecha de inscripción, estado de inscripción (`confirmada` / `en_lista_espera` / `baja`).

El supervisor puede dar de baja a un inscrito (con motivo) y mover personas de la lista de espera a confirmada cuando se libera una plaza.

**Pestaña «Asistencia»**

Por sesión: lista de inscritos con checkbox de asistencia. El supervisor (o el responsable de la actividad) marca presencia/ausencia. Las sesiones futuras muestran la lista pero sin checkboxes activos.

---

## 6. Pantalla: Plazas

**Ruta:** `supervision.plazas` → `/supervision/plazas`
**Componente:** `PlazasPage`
**Condicional:** Solo se muestra en centros con `tiene_plazas = true` en su configuración.

### 6.1 Propósito

Gestión del inventario de plazas del centro. El supervisor decide qué plazas existen y cuáles están operativas. La asignación de una plaza a un ciudadano concreto es responsabilidad del profesional de intervención y se realiza desde su módulo.

### 6.2 Vista principal

Listado de plazas con columnas: identificador/nombre, tipo de espacio, estado (`disponible` / `ocupada` / `no_disponible`), ciudadano asignado (si `ocupada`, solo nombre, sin enlace activo desde esta pantalla), fecha de último cambio de estado.

Filtros por estado y por tipo de espacio.

### 6.3 Acciones sobre plazas

**Nueva plaza:** formulario modal con nombre/identificador, tipo de espacio (select desde `tipos_espacio`) y notas.

**Marcar como no disponible:** modal con campo de motivo obligatorio. La plaza pasa a `no_disponible` y se registra la fecha y el motivo. No afecta a ciudadanos asignados: si la plaza estaba `ocupada`, el cambio requiere confirmación explícita indicando que el ciudadano deberá ser reubicado.

**Marcar como disponible:** devuelve la plaza a `disponible`. Sin modal adicional.

**Eliminar plaza:** solo posible si la plaza está en estado `disponible` y nunca ha tenido ciudadano asignado, o si su último ciudadano ya causó baja hace más de 90 días (configurable). Se solicita confirmación.

---

## 7. Pantalla: Mi equipo

**Ruta:** `supervision.equipo` → `/supervision/equipo`
**Componente:** `EquipoPage`

### 7.1 Propósito y alcance

Vista del estado operativo de los profesionales adscritos a la UO del supervisor, y gestión de su ciclo de vida como profesionales del centro. Esta pantalla gestiona la entidad **Profesional** (nombre, cargo, adscripción, perfil horario, situación), no la entidad **Usuario** (credenciales, roles de sistema, acceso a VIDA360).

**Separación conceptual entre Profesional y Usuario:**

- Un **Profesional** define quién es alguien en el centro: su nombre, cargo, UO de adscripción, fecha de incorporación y situación operativa. Es una entidad del dominio de servicios sociales.
- Un **Usuario** define cómo esa persona se identifica y qué puede hacer en VIDA360: sus credenciales, sus roles de sistema (`intervencion`, `supervision`, etc.) y su cuenta en el sistema de información. Es una entidad del dominio IT/seguridad.

El supervisor gestiona Profesionales desde esta pantalla Livewire. La gestión de Usuarios y la asignación de roles de sistema es responsabilidad del rol `adm_usuarios` y se realiza en Filament, habitualmente de forma centralizada a nivel de organización. El vínculo entre ambas entidades (`profesionales.usuario_id`) lo establece el administrador de usuarios al crear o importar la cuenta.

**Implicación práctica:** cuando el supervisor da de alta a un profesional nuevo en «Mi equipo», esa persona aún no tiene acceso a VIDA360. El supervisor deberá solicitar al administrador de usuarios que cree la cuenta y la vincule al registro de profesional.

### 7.2 Vista principal

Tabla de profesionales con columnas: nombre, cargo, casos activos (count de planes activos asignados), estado de agenda hoy (`activo` / `ausente` / `sin agenda`), perfil horario asignado.

Filtros: por cargo, por estado de agenda.

Botón «Añadir profesional» para dar de alta un nuevo profesional en el centro.

Al hacer clic en la fila de un profesional se accede a su ficha (ver §7.3).

### 7.3 Sub-flujo: Alta de profesional

Formulario modal con los campos:

- Nombre completo
- Cargo (select desde catálogo `cargos`)
- Fecha de incorporación al centro
- Perfil horario (select desde perfiles configurados)
- Notas internas (opcional)

El nuevo profesional se crea sin `usuario_id`. El sistema muestra un aviso informativo: «Este profesional no tiene cuenta de usuario en VIDA360 todavía. Comunica al administrador de usuarios que vincule la cuenta cuando esté disponible».

### 7.4 Sub-pantalla: Ficha del profesional

Pantalla de detalle de un profesional concreto, con tres pestañas:

**Pestaña «Resumen»**

Datos del profesional (nombre, cargo, UO), fecha de incorporación al centro, número de casos activos, próximas citas en agenda (vista compacta de los próximos 7 días), alertas activas sin reconocer. Si el profesional tiene `usuario_id` vinculado, se muestra un enlace discreto «Ver cuenta de usuario» que abre Filament en nueva pestaña (solo visible para quien tenga también el rol `adm_usuarios`).

**Pestaña «Perfil horario»**

Perfil horario asignado al profesional. El supervisor puede cambiar el perfil horario desde aquí (selector de perfiles configurados en `PerfilHorarioProfesional`). El cambio es efectivo desde la próxima semana salvo que se indique otra fecha de inicio.

**Pestaña «Suplencias»**

Lista de suplencias activas e históricas. El supervisor puede crear una nueva suplencia indicando profesional sustituto, período y alcance (total o parcial).

### 7.5 Baja de profesional

La baja de un profesional se realiza desde su ficha con el botón «Dar de baja». Requiere confirmación y fecha de baja. El sistema advierte si el profesional tiene casos activos asignados y pide que se confirme que serán reasignados antes de proceder. La baja es lógica (soft delete); el historial de casos y apuntes se conserva íntegro.

La baja del profesional en VIDA360 (desactivación de su cuenta de usuario) es una operación separada que debe realizar el administrador de usuarios en Filament.

---

## 8. Pantalla: Auditoría de accesos

**Ruta:** `supervision.auditoria` → `/supervision/auditoria`
**Componente:** `AuditoriaPage`

### 8.1 Propósito

El supervisor puede ver un listado de todos los accesos «inesperados» a datos de ciudadanos del centro: profesionales que han consultado historias sociales asignadas al centro sin ser el responsable de ese ciudadano. El caso más frecuente es un profesional de otra UO que accede en Nivel 2 a la historia de un ciudadano cuyo TSR de referencia pertenece a este centro.

Esto complementa la vista que el propio profesional TSR tiene al entrar en un expediente (lista de últimos accesos), pero con visión de conjunto del centro.

### 8.2 Vista principal

Tabla de accesos con columnas:

- Profesional que accedió (nombre, UO de origen)
- Ciudadano cuyo expediente se consultó (nombre)
- Colectivo protegido (badge destacado si aplica, con enlace a la autorización si existe)
- Fecha y hora del acceso
- Motivo declarado (si el acceso fue en Nivel 2 con justificación, o campo vacío si no)
- Estado (solo para accesos a colectivos protegidos): `autorizado` con enlace a la autorización / `sin autorización` en rojo

Los accesos de ciudadanos pertenecientes a colectivos protegidos se destacan visualmente (fila con fondo ámbar o rojo según si hay autorización o no) y aparecen primero en la ordenación por defecto, independientemente de la fecha.

### 8.3 Filtros

- Rango de fechas (por defecto: últimos 30 días)
- Profesional concreto
- UO de origen del profesional
- Solo colectivos protegidos (boolean)
- Solo accesos sin autorización (boolean, visible solo si el centro tiene colectivos protegidos)

### 8.4 Condicional de colectivos protegidos

Si el centro no atiende a colectivos protegidos (`tiene_colectivos_protegidos = false`), la columna «Colectivo protegido» no se muestra y los filtros relacionados tampoco. La pantalla sigue siendo útil: muestra los accesos externos a cualquier ciudadano del centro.

### 8.5 Relación con `AccesosExpedienteQuery`

Esta pantalla reutiliza `app/Queries/AccesosExpedienteQuery` (ya existente), ampliándola con los parámetros de filtro necesarios y el criterio de «externo al centro» (profesional cuya UO no es la UO del ciudadano).

---

## 9. Pantalla: Aprobaciones

**Ruta:** `supervision.aprobaciones` → `/supervision/aprobaciones`
**Componente:** `AprobacionesPage`

### 9.1 Propósito

Bandeja de solicitudes que requieren acción del supervisor antes de ser efectivas. Dos tipos de solicitud conviven en la misma bandeja: accesos a expedientes de ciudadanos de colectivos protegidos, y asignaciones de roles con `nivel_supervision = aprobacion_previa`.

### 9.2 Vista principal

Lista unificada de solicitudes pendientes, con columnas: tipo, solicitante, objeto de la solicitud, fecha de solicitud, antigüedad.

Pestañas de filtro rápido: «Todas» / «Accesos a expedientes» / «Asignaciones de rol».

Cada fila es expandible para ver el detalle sin abandonar la pantalla. Las acciones (aprobar / denegar) están disponibles en el detalle expandido y en el modal de detalle completo.

**Condicional:** La pestaña «Accesos a expedientes» y las solicitudes de ese tipo solo aparecen en centros o servicios cuyo público objetivo incluye algún colectivo protegido (`tiene_colectivos_protegidos = true` en la configuración del centro).

### 9.3 Sub-flujo: Aprobar o denegar un acceso a expediente protegido

El supervisor ve: profesional solicitante, ciudadano cuyo expediente se solicita, motivo declarado, fecha de solicitud.

Acciones disponibles:

- **Aprobar**: acceso se activa. El sistema registra la autorización en `accesos_protegidos` con `aprobado_por_id` y `aprobado_en`. El profesional recibe una alerta de tipo `aviso`.
- **Denegar**: modal con campo de motivo obligatorio. El registro pasa a `estado = denegado`. El profesional recibe una alerta de tipo `aviso` con el motivo.

### 9.4 Sub-flujo: Aprobar o denegar una asignación de rol

El supervisor ve: usuario al que se quiere asignar el rol, rol solicitado, quién realizó la solicitud (`adm_usuarios`), fecha.

Acciones disponibles:

- **Aprobar**: el registro en `usuario_rol` pasa de `estado = pendiente_aprobacion` a `estado = activo`. Spatie sincroniza el rol en `model_has_roles`.
- **Denegar**: el registro pasa a `estado = denegado`. El administrador de usuarios que realizó la solicitud recibe una alerta.

---

## 10. Pantalla: Configuración del centro

**Ruta:** `supervision.configuracion` → `/supervision/configuracion`
**Componente:** `ConfiguracionCentroPage`

### 10.1 Propósito

Parámetros del centro que el supervisor puede modificar sin necesidad de acceder al panel de administración Filament. Se limita a los parámetros de su incumbencia directa; la configuración técnica profunda (tipos de ficha, catálogo de prestaciones, estructura de UOs) sigue siendo responsabilidad del administrador del sistema en Filament.

### 10.2 Parámetros editables

La pantalla se organiza en secciones:

**Identificación del centro**

- Nombre completo del centro
- Nombre corto (usado en el sidebar y en cabeceras)
- Nombre completo del plan de intervención (por UO; configurable por el supervisor de esa UO)
- Nombre corto del plan de intervención

**Horario y agenda**

- Horario de atención al público (apertura y cierre, por día de la semana)
- Modo de agenda (`basico` / `estandar` / `avanzado`) — con advertencia de que el cambio afecta a la interfaz de todos los profesionales del centro
- Umbral de ratio personas/profesional para alerta en el dashboard (número)
- Umbral de espera media para alerta en el dashboard (días)

**Plazas** *(visible solo si `tiene_plazas = true`)*

- Capacidad máxima declarada del centro (informativo, no impide crear más plazas)

Todos los cambios se guardan en `organizacion_configuracion` (clave-valor) o en los campos correspondientes de `unidades_organizativas`, siguiendo la arquitectura existente.

---

## 11. Mapa de rutas

```
/supervision                          → redirect a /supervision/inicio
/supervision/inicio                   → InicioPage
/supervision/cuadrante                → CuadrantePage
/supervision/actividades              → ActividadesPage
/supervision/actividades/{id}         → ActividadesPage (detalle, pestañas inscripción/asistencia)
/supervision/actividades/nueva        → ActividadesPage (formulario nueva)
/supervision/plazas                   → PlazasPage          [condicional]
/supervision/equipo                   → EquipoPage
/supervision/equipo/{profesional}     → EquipoPage (ficha profesional, pestañas)
/supervision/auditoria                → AuditoriaPage
/supervision/aprobaciones             → AprobacionesPage
/supervision/configuracion            → ConfiguracionCentroPage
```

---

## 12. Componentes y servicios

### 12.1 Componentes Livewire

| Componente | Ruta de archivo |
|---|---|
| `InicioPage` | `Modules/Supervision/app/Http/Livewire/InicioPage.php` |
| `CuadrantePage` | `Modules/Supervision/app/Http/Livewire/CuadrantePage.php` |
| `ActividadesPage` | `Modules/Supervision/app/Http/Livewire/ActividadesPage.php` |
| `PlazasPage` | `Modules/Supervision/app/Http/Livewire/PlazasPage.php` |
| `EquipoPage` | `Modules/Supervision/app/Http/Livewire/EquipoPage.php` |
| `AuditoriaPage` | `Modules/Supervision/app/Http/Livewire/AuditoriaPage.php` |
| `AprobacionesPage` | `Modules/Supervision/app/Http/Livewire/AprobacionesPage.php` |
| `ConfiguracionCentroPage` | `Modules/Supervision/app/Http/Livewire/ConfiguracionCentroPage.php` |
| `Sidebar` | `Modules/Supervision/app/Http/Livewire/Sidebar.php` |

### 12.2 Servicios

| Servicio | Responsabilidad |
|---|---|
| `SupervisionSidebarDataService` | Contadores de badge para el sidebar (aprobaciones pendientes) |
| `IndicadoresCentroService` | Computa los KPIs del dashboard: ratio, espera media, etc. |

### 12.3 Dependencias de módulos externos

| Módulo | Qué consume |
|---|---|
| `Modules/Agenda` | `CuadrantePage` lee y escribe via servicios de Agenda (slots, excepciones, reasignaciones) |
| `Modules/Mensajes` | `AprobacionesPage` crea alertas de tipo `aviso` al aprobar o denegar |
| `app/Queries/AccesosExpedienteQuery` | `AuditoriaPage` extiende esta query para la vista de accesos externos |

---

## 13. Acceso compartido con módulo de ciudadanía

El supervisor puede acceder a la ficha de un ciudadano desde la pantalla de auditoría de accesos (columna «Ciudadano»). El enlace lleva a `ciudadania.ciudadano.ficha`, igual que desde el módulo de intervención para roles distintos de `intervencion`. El supervisor tiene solo lectura en esa pantalla: sin botón de edición, sin modal de nuevo documento, sin acceso a la historia social directa.

---

## 14. Decisiones de diseño pendientes

- **Generación asistida del cuadrante:** el módulo de agenda prevé la posibilidad de asistencia de IA para generar propuestas de cuadrante mensual. Diferido hasta que haya datos históricos suficientes. Cuando se implemente, el punto de entrada será `CuadrantePage`.
- **Notificaciones al ciudadano por cambio de cita:** cuando una reasignación afecta a una cita, el sistema debería poder notificar al ciudadano. Diferido a la definición del módulo de comunicaciones ciudadanas.
- **Mensajes:** el ítem de Mensajes en el sidebar se añadirá en una entrega posterior, reutilizando `BuzonPage` con permisos extendidos para envío de comunicados al equipo.
- **Firma de propuestas:** el rol `supervision` puede firmar o rechazar propuestas que requieran aprobación de supervisión (según `docs/modulo-usuarios-permisos.md`). Si estas propuestas son distintas de los dos tipos ya cubiertos en Aprobaciones, se ampliará esa pantalla o se añadirá un tercer tipo de solicitud.

---

*Documento elaborado en fase de diseño. Versión inicial: junio 2026.*
