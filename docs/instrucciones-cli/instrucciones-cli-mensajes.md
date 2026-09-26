# Instrucciones CLI — Módulo: Mensajería y Alertas

> Este documento guía la implementación del módulo de Mensajería y Alertas de VIDA 360.
> Léelo completo antes de escribir ninguna línea de código.
> El diseño funcional y el modelo de datos de referencia están en `docs/modulo-mensajes.md`.
> Los principios arquitectónicos que rigen todas las decisiones están en `docs/principios-vida360.md`.

---

## Contexto obligatorio antes de empezar

Lee los siguientes documentos del repositorio antes de comenzar cualquier tarea:

- `docs/principios-vida360.md` — principios arquitectónicos globales
- `docs/modulo-mensajes.md` — diseño funcional y modelo de datos de este módulo
- `docs/glosario.md` — terminología del dominio

El módulo se implementa dentro de la estructura `Modules/Mensajes/` usando `nwidart/laravel-modules`. Sigue exactamente la misma estructura de carpetas que el resto de módulos del proyecto.

---

## Restricciones generales

Estas restricciones aplican a todo el trabajo de implementación. No las omitas aunque parezcan obvias.

**Ningún valor de dominio hardcodeado.** Los valores que pueden cambiar (horario laboral por defecto, roles disponibles, tipos configurables) deben guardarse en `catalogos_sistema`, nunca en el código ni en migraciones como valores fijos.

**Enums solo cuando el código los necesita.** Usa enums de PHP/PostgreSQL únicamente para valores que la lógica de negocio necesita evaluar (estados, tipos de destinatario). Los valores puramente descriptivos van en `catalogos_sistema`.

**Filament para configuración, Livewire para operación.** Los recursos de backoffice (configuración del horario laboral por defecto, consulta de logs) van en Filament. Las interfaces operativas (bandeja de alertas, mensajería, panel de redacción) van en Livewire.

**Todo en español.** Nombres de tablas, columnas, clases, variables, vistas y comentarios en español, siguiendo las convenciones del resto del proyecto.

**No hay adjuntos en mensajes.** No implementes ningún sistema de adjuntos en la mensajería. Los documentos pertenecen a la Historia Social. Los mensajes solo pueden incluir enlaces de contexto.

**No implementes lo que está fuera de scope.** No implementes delegación por ausencia, mensajes a grupos, ni integración con el módulo de Agenda. Si en algún punto parece necesario, detente y consulta.

---

## Fase 1 — Migraciones

Crea las migraciones en el orden indicado. Respeta las dependencias entre tablas.

### 1.1 `alertas`

```
id                        bigint PK autoincrement
tipo                      enum('aviso', 'alerta') not null
origen_type               varchar(255) not null          -- clase del modelo generador (polimórfico)
origen_id                 bigint not null                -- id del objeto generador
titulo                    varchar(255) not null
cuerpo                    text not null
destinatario_type         enum('usuario', 'rol_uo') not null
destinatario_usuario_id   bigint nullable FK usuarios
destinatario_rol          varchar(100) nullable
destinatario_uo_id        bigint nullable FK unidades_organizativas
estado                    enum('pendiente', 'reconocida', 'escalada', 'vencida') not null default 'pendiente'
expira_en                 timestamp nullable             -- solo para alertas, calculado en horas laborales
escalada_en               timestamp nullable
escalada_a_usuario_id     bigint nullable FK usuarios
created_at                timestamp not null
updated_at                timestamp not null
```

Índices: `(origen_type, origen_id)`, `(destinatario_usuario_id)`, `(destinatario_uo_id, destinatario_rol)`, `(estado)`.

Constraint de integridad: cuando `destinatario_type = 'usuario'`, `destinatario_usuario_id` debe ser not null. Cuando `destinatario_type = 'rol_uo'`, `destinatario_rol` y `destinatario_uo_id` deben ser not null. Implementa esto como check constraint en PostgreSQL.

### 1.2 `alerta_reconocimientos`

```
id              bigint PK autoincrement
alerta_id       bigint not null FK alertas on delete cascade
usuario_id      bigint not null FK usuarios
tipo            enum('reconocida', 'escalada', 'descartada') not null
reconocida_en   timestamp not null
ip_address      varchar(45) nullable
```

Índice único: `(alerta_id, usuario_id)` para evitar reconocimientos duplicados del mismo usuario sobre la misma alerta.

### 1.3 `mensajes_hilos`

```
id              bigint PK autoincrement
asunto          varchar(255) not null
creado_por_id   bigint not null FK usuarios
created_at      timestamp not null
updated_at      timestamp not null
```

### 1.4 `mensajes_participantes`

```
id                      bigint PK autoincrement
hilo_id                 bigint not null FK mensajes_hilos on delete cascade
usuario_id              bigint not null FK usuarios
rol                     enum('remitente_inicial', 'participante') not null
fecha_ultima_lectura    timestamp nullable
archivado_en            timestamp nullable
```

Índice único: `(hilo_id, usuario_id)`. Un usuario no puede ser participante dos veces en el mismo hilo.

### 1.5 `mensajes`

```
id              bigint PK autoincrement
hilo_id         bigint not null FK mensajes_hilos on delete cascade
remitente_id    bigint not null FK usuarios
cuerpo          text not null
created_at      timestamp not null
updated_at      timestamp not null
```

Índice: `(hilo_id, created_at)` para ordenación eficiente de mensajes dentro de un hilo.

### 1.6 `mensajes_referencias_ciudadano`

```
id              bigint PK autoincrement
mensaje_id      bigint not null FK mensajes on delete cascade
ciudadano_id    bigint not null FK ciudadanos
created_at      timestamp not null
```

Índice: `(ciudadano_id)` para consultas desde el expediente del ciudadano.

### 1.7 `mensajes_registro_historia`

```
id                  bigint PK autoincrement
mensaje_id          bigint not null FK mensajes
ciudadano_id        bigint not null FK ciudadanos
registrado_por_id   bigint not null FK usuarios
cuerpo_registrado   text not null
visibilidad         enum('privada', 'profesionales') not null default 'profesionales'
registrado_en       timestamp not null
created_at          timestamp not null
updated_at          timestamp not null
```

Índice: `(ciudadano_id, registrado_en)` para consultas cronológicas desde la Historia Social.

---

## Fase 2 — Modelos Eloquent

Crea los modelos en `Modules/Mensajes/Models/`. Un modelo por archivo.

### `Alerta`

Relaciones:
- `reconocimientos()` → hasMany(AlertaReconocimiento)
- `destinatarioUsuario()` → belongsTo(User, 'destinatario_usuario_id') nullable
- `destinatarioUo()` → belongsTo(UnidadOrganizativa, 'destinatario_uo_id') nullable
- `escaladaA()` → belongsTo(User, 'escalada_a_usuario_id') nullable
- `origen()` → morphTo()

Casts: `tipo` y `estado` como enums de PHP. `expira_en`, `escalada_en` como Carbon.

Scope `pendientes()`: filtra por estado `pendiente`. Scope `vencidas()`: filtra alertas con `expira_en < now()` y estado `pendiente`.

### `AlertaReconocimiento`

Relaciones:
- `alerta()` → belongsTo(Alerta)
- `usuario()` → belongsTo(User)

### `MensajeHilo`

Relaciones:
- `participantes()` → hasMany(MensajeParticipante)
- `mensajes()` → hasMany(Mensaje)->orderBy('created_at')
- `creadoPor()` → belongsTo(User, 'creado_por_id')
- `ultimoMensaje()` → hasOne(Mensaje)->latestOfMany()

Método `tieneParticipante(int $usuarioId): bool`.

### `MensajeParticipante`

Relaciones:
- `hilo()` → belongsTo(MensajeHilo)
- `usuario()` → belongsTo(User)

Método `mensajesNoLeidos(): int` — cuenta mensajes del hilo con `created_at > fecha_ultima_lectura`.

### `Mensaje`

Relaciones:
- `hilo()` → belongsTo(MensajeHilo)
- `remitente()` → belongsTo(User, 'remitente_id')
- `referenciasCiudadano()` → hasMany(MensajeReferenciaCiudadano)
- `registrosHistoria()` → hasMany(MensajeRegistroHistoria)

No implementes HasMedia ni colecciones de spatie/medialibrary en mensajes. Los mensajes no tienen adjuntos.

### `MensajeReferenciaCiudadano`

Relaciones:
- `mensaje()` → belongsTo(Mensaje)
- `ciudadano()` → belongsTo(Ciudadano)

### `MensajeRegistroHistoria`

Relaciones:
- `mensaje()` → belongsTo(Mensaje)
- `ciudadano()` → belongsTo(Ciudadano)
- `registradoPor()` → belongsTo(User, 'registrado_por_id')

---

## Fase 3 — Servicios de negocio

Crea los servicios en `Modules/Mensajes/Services/`.

### `HorarioLaboralService`

Responsable de calcular el timestamp de vencimiento de una alerta dado su `created_at`.

```php
public function calcularExpiracion(Carbon $desde): Carbon
```

La implementación actual lee el horario laboral por defecto desde `catalogos_sistema` (clave `horario_laboral_defecto`). El formato esperado en backoffice es un JSON con `inicio` y `fin` en formato HH:MM y `dias_semana` como array de números (1=lunes…5=viernes). Suma 4 horas laborales efectivas a `$desde`.

Cuando el módulo de Agenda esté disponible, este servicio se actualizará para consultar el calendario laboral real. Por ahora, solo lee el valor por defecto. Documenta este punto con un comentario `// TODO: integrar con módulo Agenda`.

### `AlertaService`

```php
// Crea una alerta y calcula su expiración si es de tipo 'alerta'
public function crear(array $datos): Alerta

// Crea un aviso manual generado por un supervisor para sus subordinados en su UO
// Valida que $supervisor tiene rol supervisor y que $destinatarios son subordinados en su UO
// Lanza UnauthorizedException si no se cumplen las condiciones
public function crearAvisoSupervisor(User $supervisor, UnidadOrganizativa $uo, string $titulo, string $cuerpo): Alerta

// Marca una alerta como reconocida por un usuario
public function reconocer(Alerta $alerta, User $usuario, string $ipAddress): AlertaReconocimiento

// Ejecuta la escalada de una alerta vencida al supervisor de la UO
public function escalar(Alerta $alerta): void

// Resuelve qué usuarios son destinatarios reales de una alerta rol_uo
public function resolverDestinatarios(Alerta $alerta): Collection
```

El método `escalar()` debe: obtener el supervisor activo de la UO del destinatario original (consultando el módulo de Usuarios/Permisos), actualizar el estado de la alerta a `escalada`, rellenar `escalada_en` y `escalada_a_usuario_id`, e insertar un registro en `alerta_reconocimientos` con `tipo = 'escalada'` para el supervisor.

Si no existe supervisor activo en la UO, la alerta debe pasar directamente a estado `vencida` y registrar este hecho en el log de la aplicación con nivel `warning`.

El método `crearAvisoSupervisor()` crea una alerta con `tipo = 'aviso'`, `origen_type = 'supervisor_manual'`, `origen_id = $supervisor->id`, y `destinatario_type = 'rol_uo'` apuntando a los subordinados del supervisor en la UO indicada. No genera `expira_en`.

### `MensajeriaService`

```php
// Crea un hilo nuevo y envía el primer mensaje
public function crearHilo(User $remitente, User $destinatario, string $asunto, string $cuerpo, array $ciudadanoIds = []): MensajeHilo

// Añade un mensaje de respuesta a un hilo existente
public function responder(MensajeHilo $hilo, User $remitente, string $cuerpo): Mensaje

// Registra un mensaje en la Historia Social de un ciudadano
public function registrarEnHistoria(Mensaje $mensaje, Ciudadano $ciudadano, User $tsr, string $cuerpoEditado, string $visibilidad = 'profesionales'): MensajeRegistroHistoria

// Marca todos los mensajes de un hilo como leídos para un usuario
public function marcarComoLeido(MensajeHilo $hilo, User $usuario): void
```

Nota: los parámetros `array $adjuntos` han sido eliminados de todos los métodos. Los mensajes no tienen adjuntos.

El método `registrarEnHistoria()` debe verificar que `$tsr` es el profesional de referencia del expediente del `$ciudadano`. Si no lo es, lanzar `UnauthorizedException` con mensaje descriptivo.

---

## Fase 4 — Jobs y scheduling

### `EscalarAlertasVencidasJob`

Job que se ejecuta periódicamente para detectar alertas vencidas y escalarlas.

```php
// Modules/Mensajes/Jobs/EscalarAlertasVencidasJob.php
public function handle(AlertaService $alertaService): void
```

Lógica: busca alertas con `tipo = 'alerta'`, `estado = 'pendiente'` y `expira_en <= now()`. Para cada una, llama a `$alertaService->escalar($alerta)`.

Registra el job en el scheduler del módulo con frecuencia de cada 15 minutos:

```php
// En el ServiceProvider del módulo
$schedule->job(EscalarAlertasVencidasJob::class)->everyFifteenMinutes();
```

---

## Fase 5 — Interfaces Livewire

Crea los componentes en `Modules/Mensajes/Livewire/`. Las vistas en `Modules/Mensajes/resources/views/livewire/`.

### `BadgeNotificaciones` (componente embebible)

Componente pequeño para incrustar en la barra de navegación. Muestra el recuento de alertas pendientes + mensajes no leídos del usuario autenticado. Se actualiza por polling cada 60 segundos usando `wire:poll`.

Muestra tres contadores diferenciados:
- **Alertas** (con tinte rojo si hay alguna pendiente, para que el indicador del menú pueda reflejar urgencia)
- **Avisos** (badge numérico neutro)
- **Mensajes** (badge numérico neutro)

### `AlertaToast` (componente global de notificaciones)

Componente que gestiona la aparición de toasts para alertas pendientes. Se registra en el layout principal de la aplicación junto a `BadgeNotificaciones`.

Comportamiento:

- Al montar el componente (login del usuario), consulta las alertas pendientes del usuario y muestra un toast por cada una.
- Usa `wire:poll` cada 30 minutos para comprobar si siguen sin reconocerse y, si es así, vuelve a mostrar los toasts correspondientes.
- Cuando llega una nueva alerta (detectada en el ciclo de polling), muestra su toast inmediatamente.
- Cada toast es individual por alerta y muestra: título, tiempo restante hasta vencimiento, enlace al origen si está disponible, y botón de reconocimiento directo.
- Los toasts no tienen auto-dismiss. Permanecen hasta que el usuario los minimiza conscientemente o reconoce la alerta.
- Al minimizar un toast, se guarda en sesión del cliente (`sessionStorage`) el ID de alerta minimizado y el timestamp. Tras 30 minutos, el componente lo vuelve a mostrar.
- Los avisos (del sistema o del supervisor) no generan toasts. Solo los de tipo `alerta`.

Implementación técnica del retraso de 30 minutos:
- El componente expone una propiedad Livewire `$ultimaComprobacion` (timestamp).
- El `wire:poll` del componente se ejecuta cada 30 minutos.
- En cliente, JavaScript complementario puede gestionar el re-show de toasts minimizados consultando `sessionStorage`.

### `BandejaAlertasYMensajes`

Componente principal que alberga las tres pestañas: Alertas, Avisos, Mensajes.

Recibe un parámetro `$pestañaInicial` (valores: `'alertas'`, `'avisos'`, `'mensajes'`) que determina cuál de las tres pestañas se muestra activa al montar el componente. Las tres entradas del menú lateral pasan valores distintos a este parámetro.

Dentro de cada pestaña se renderiza el subcomponente correspondiente.

### `BandejaAlertas` (subcomponente de pestaña)

Muestra las alertas del usuario autenticado.

Debe mostrar:
- Alertas pendientes (ordenadas por `expira_en` ASC para las alertas, avisos al final)
- Indicador visual de tiempo restante para alertas próximas a vencer
- Botón de reconocimiento para alertas (acción con confirmación)
- Botón de descartar para avisos
- Enlace al contexto de origen (si el modelo origen existe y el usuario tiene permisos de acceso)
- Para avisos con `origen_type = 'supervisor_manual'`: etiqueta visual "Aviso del supervisor". No mostrar botón de respuesta.

Al reconocer una alerta, llamar a `AlertaService::reconocer()` con la IP del request.

Para alertas dirigidas a `rol_uo`, el componente debe mostrar solo las que correspondan a un rol que el usuario tenga activo en alguna UO.

### `BandejaMensajes` (subcomponente de pestaña)

Muestra la lista de hilos del usuario con:
- Asunto, nombre del otro participante
- Fecha del último mensaje
- Indicador de mensajes no leídos (badge numérico)
- Opción de archivar hilo

Al seleccionar un hilo, carga el componente `HiloMensajes`.

### `HiloMensajes`

Muestra los mensajes de un hilo en orden cronológico.

Funcionalidades:
- Caja de respuesta con editor de texto simple
- No hay campo de adjuntos
- Por cada mensaje, si el usuario autenticado es TSR de algún ciudadano referenciado en el mensaje, mostrar botón "Registrar en historia"
- Al pulsar "Registrar en historia": abrir modal con el cuerpo del mensaje editable, selector de visibilidad (`privada` / `profesionales`) y selector del ciudadano (si el mensaje referencia a más de uno)

### `PanelRedaccion` (componente global flotante)

Panel de redacción de mensajes nuevos. Se registra en el layout principal de la aplicación para estar disponible desde cualquier pantalla.

**Estado del componente:**
- `$abierto` (bool): controla la visibilidad del panel
- `$contexto` (array nullable): datos del elemento desde el que se invocó el panel (`tipo`, `id`, `etiqueta`)
- `$destinatarioSugerido` (array nullable): datos del autor del elemento de contexto (`id`, `nombre`, `cargo`), en estado "no confirmado"
- `$destinatarioConfirmado` (array nullable): usuario seleccionado definitivamente
- `$asunto` (string)
- `$cuerpo` (string)
- `$ciudadanoIds` (array): referencias a ciudadanos incluidas en el mensaje

**Método de apertura:**

```php
public function abrir(array $contexto = [], ?int $sugerirDestinatarioId = null): void
```

Este método es invocable desde otros componentes Livewire con `$this->dispatch('abrir-panel-redaccion', contexto: [...], sugerirDestinatarioId: ...)`.

Cuando se recibe `$sugerirDestinatarioId`, el componente carga los datos del usuario y los pone en `$destinatarioSugerido`. El destinatario aparece como un **chip gris** en el campo de destinatario, en estado "no confirmado". El usuario debe hacer clic en el chip para confirmarlo (cambia a color normal) o eliminarlo para buscar otro.

Si el panel se abre sin contexto, todos los campos quedan vacíos.

**Campos del formulario:**
- Destinatario: campo de búsqueda por nombre. Muestra chip gris si hay sugerencia no confirmada. Si el usuario no conoce el nombre, ofrece filtro por cargo (rol) y UO. El listado muestra nombre completo y UO principal. La validación del formulario falla si el destinatario no está confirmado.
- Asunto
- Cuerpo del mensaje
- Referencias a ciudadanos: búsqueda por nombre/identificador (solo ciudadanos a los que el usuario tiene acceso). Se pre-rellena si el contexto incluye un ciudadano.
- No hay campo de adjuntos.

Al enviar el formulario, llama a `MensajeriaService::crearHilo()` y cierra el panel.

### `NuevoAvisoSupervisor` (componente accesible solo para supervisores)

Formulario de creación de avisos manuales. Solo se muestra en la interfaz si el usuario autenticado tiene rol supervisor.

Campos:
- UO destinataria: solo permite seleccionar la UO propia del supervisor (no editable, informativo)
- Título del aviso
- Cuerpo del aviso

Al enviar, llama a `AlertaService::crearAvisoSupervisor()`. Si el usuario no es supervisor, el componente rechaza la acción con una excepción.

---

## Fase 6 — Recursos Filament

Crea los recursos en `Modules/Mensajes/Filament/Resources/`.

### `ConfiguracionHorarioLaboralResource`

Recurso para que el administrador configure el horario laboral por defecto usado en el cálculo de vencimientos.

Edita el valor `horario_laboral_defecto` en `catalogos_sistema`. El formulario debe tener:
- Hora de inicio (TimePicker)
- Hora de fin (TimePicker)
- Días laborables (CheckboxList: lunes a viernes, con sábado y domingo opcionales)

Muestra una nota informativa: "Este horario se usa para calcular vencimientos de alertas hasta que el módulo de Agenda esté disponible."

### `LogAlertasResource`

Recurso de solo lectura para supervisión. Lista todas las alertas con filtros por estado, tipo, fecha y UO. Especialmente útil para auditar alertas vencidas y escaladas. No permite edición.

---

## Fase 7 — Integración con Historia Social

Este paso conecta el módulo de Mensajería con el módulo de Intervención.

En el módulo de Intervención, cuando se renderice la línea de tiempo de la Historia Social de un ciudadano, debe incluirse también los registros de `mensajes_registro_historia` para ese ciudadano. Estos registros aparecen como entradas de tipo `comunicacion_interna` en la línea de tiempo.

La consulta debe respetar la visibilidad: si el profesional no tiene el permiso para ver entradas `privadas`, no se muestran los registros con `visibilidad = 'privada'`.

Implementa esta integración añadiendo un método en el servicio de Historia Social del módulo de Intervención:

```php
// En Modules/Intervencion/Services/HistoriaSocialService.php (o equivalente)
public function obtenerEntradas(Ciudadano $ciudadano, User $profesional): Collection
// Debe incluir, además de los apuntes propios, los MensajeRegistroHistoria del ciudadano
// filtrados por visibilidad según permisos del $profesional
```

---

## Fase 8 — Integración del panel de redacción con pantallas existentes

Una vez implementados los componentes Livewire del módulo, añade el botón/enlace de "Escribir mensaje" en las pantallas desde las que tiene sentido lanzar el panel de redacción. El criterio es: cualquier pantalla donde haya un elemento creado por un profesional identificable.

En cada pantalla que aplique:
1. Añade un botón o enlace de texto "Escribir mensaje" (posición discreta; no en el flujo principal de la pantalla).
2. Al pulsar, emite el evento Livewire `abrir-panel-redaccion` con el contexto del elemento activo y el ID del autor como sugerencia de destinatario.

Ejemplo para la pantalla de una intervención:

```php
$this->dispatch('abrir-panel-redaccion',
    contexto: [
        'tipo' => 'intervencion',
        'id' => $this->intervencion->id,
        'etiqueta' => 'Intervención #' . $this->intervencion->id
    ],
    sugerirDestinatarioId: $this->intervencion->creado_por_id
);
```

Pantallas prioritarias para esta integración (según criterio funcional):
- Vista de una intervención (autor = profesional que la creó)
- Vista de una valoración (autor = profesional que la realizó)
- Expediente de un ciudadano (sugerencia = TSR responsable)

---

## Orden de ejecución recomendado

1. Fase 1: todas las migraciones en orden
2. Fase 2: todos los modelos
3. Fase 3: servicios (en orden: HorarioLaboralService → AlertaService → MensajeriaService)
4. Fase 4: job de escalada y registro en scheduler
5. Fase 5: componentes Livewire (en orden: BadgeNotificaciones → AlertaToast → BandejaAlertasYMensajes → BandejaAlertas → BandejaMensajes → HiloMensajes → PanelRedaccion → NuevoAvisoSupervisor)
6. Fase 6: recursos Filament
7. Fase 7: integración con Historia Social
8. Fase 8: integración del panel de redacción con pantallas existentes

No avances a la siguiente fase si la anterior tiene errores. Ejecuta `php artisan test` entre fases.

---

## Tests mínimos esperados

Escribe tests para los siguientes casos. Usa el módulo de tests existente en el proyecto.

**AlertaService:**
- Crear alerta tipo `aviso` no genera `expira_en`
- Crear alerta tipo `alerta` genera `expira_en` correcto según horario laboral
- Reconocer una alerta cambia su estado a `reconocida`
- Escalar una alerta sin reconocer en plazo asigna supervisor y cambia estado a `escalada`
- Escalar una alerta sin supervisor disponible cambia estado a `vencida`
- `crearAvisoSupervisor()` lanza excepción si el usuario no tiene rol supervisor
- `crearAvisoSupervisor()` crea aviso con `origen_type = 'supervisor_manual'` y `expira_en = null`

**MensajeriaService:**
- Crear hilo genera dos participantes (remitente_inicial y participante)
- `registrarEnHistoria()` lanza excepción si el usuario no es TSR del expediente
- `registrarEnHistoria()` crea registro con el cuerpo editado, no el original

**HorarioLaboralService:**
- Calcular expiración a las 17:30 de un día laborable con horario 08:00-17:00 devuelve las 09:00 del día siguiente
- Calcular expiración a las 14:00 de un viernes devuelve el lunes siguiente a la hora correspondiente

**PanelRedaccion (Livewire):**
- Abrir el panel sin contexto deja todos los campos vacíos
- Abrir el panel con contexto pre-rellena el elemento vinculado y pone el destinatario sugerido en estado "no confirmado" (chip gris)
- Intentar enviar el formulario con el destinatario en estado "no confirmado" falla la validación
- Confirmar el chip de destinatario cambia su estado y permite el envío

---

## Notas finales

**Sin adjuntos en mensajes.** No instales ni configures `spatie/laravel-medialibrary` para el módulo de Mensajería. Si el paquete está instalado en el proyecto por otros módulos, no lo uses aquí. Los mensajes no tienen colecciones de media.

**Queue worker:** el job `EscalarAlertasVencidasJob` requiere que el queue worker esté corriendo. En local, usa `php artisan queue:work`. Verifica que el scheduler también está activo (`php artisan schedule:work` en local).

**Rendimiento de `BadgeNotificaciones`:** con la concurrencia esperada del sistema (300-600 usuarios simultáneos), el polling cada 60 segundos genera aproximadamente 5-10 requests/segundo hacia el servidor. Cada request implica dos consultas ligeras e indexadas (alertas pendientes + mensajes no leídos del usuario). Esta carga es perfectamente asumible en un servidor estándar con PostgreSQL bien configurado y no requiere ninguna optimización adicional para la escala actual.

**Rendimiento de `AlertaToast`:** el polling de 30 minutos de este componente es aún menos frecuente que el del badge, por lo que su impacto en servidor es despreciable.

**WebSockets (no implementar ahora):** si en el futuro la carga del servidor aumenta significativamente, el primer ajuste es subir el intervalo de polling. La alternativa arquitectónica a largo plazo es Laravel Echo + Laravel Reverb (el servidor WebSocket oficial de Laravel), que eliminaría el polling y reemplazaría por eventos push. El umbral donde esa inversión se justifica está en torno a los 5.000 usuarios simultáneos. No implementes esta solución ahora.

**Rol supervisor:** la validación de que un usuario es supervisor debe hacerse consultando el módulo de Usuarios/Permisos, no con un campo hardcodeado. Usa la misma lógica que el resto del proyecto para verificar roles.
