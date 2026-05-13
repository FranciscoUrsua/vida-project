# Módulo Auditoría — Diseño Funcional

> **Estado:** diseñado. Pendiente de implementación.
>
> **Propósito:** registrar de forma fiable y completa todos los accesos a datos de ciudadanos, y exponer esos registros de manera útil para los profesionales y supervisores.

---

## 1. Introducción funcional

Todo acceso a un expediente de ciudadano queda registrado: quién ha accedido, cuándo y qué ha hecho. Este registro no es solo un log técnico interno: el trabajador social de referencia puede consultarlo desde la ficha de la persona. Si detecta un acceso que no reconoce, puede preguntar al profesional que lo realizó.

Este mecanismo convierte la auditoría en un instrumento de *accountability* profesional y de cultura organizativa, no solo de cumplimiento normativo. La transparencia interna es la mejor garantía de un uso responsable de la información.

La auditoría cubre cuatro tipos de operación: **lectura**, **creación**, **edición** y **eliminación**. Los accesos a expedientes de ciudadanos pertenecientes a colectivos especialmente protegidos se registran con la acción `acceso_restringido`, diferenciándolos para facilitar su revisión supervisora.

### Qué se audita y qué no

**Se auditan** todos los accesos intencionales a datos de ciudadanos realizados desde la capa de presentación (Livewire, Filament, API). Un acceso intencional es aquel que responde a una acción deliberada de un usuario: abrir una ficha, editar un campo, generar un documento.

**No se auditan** las cargas de relación automáticas de Eloquent (eager/lazy loading interno), las consultas de tablas de catálogos, ni los propios registros de auditoría. Los registros de auditoría son inmutables — no generan a su vez entradas de auditoría.

---

## 2. Modelo de datos

### Tabla `audits`

```
audits
  id                 bigint PK
  user_id            bigint FK → users (quién realizó la acción)
  accion             enum    → ver | crear | editar | eliminar | exportar | imprimir | acceso_restringido
  auditable_type     varchar (clase del modelo afectado)
  auditable_id       bigint  (id del registro afectado)
  ciudadano_id       bigint FK nullable → ciudadanos (ciudadano al que pertenece el dato)
  datos_antes        jsonb nullable (snapshot de campos auditables antes del cambio)
  datos_despues      jsonb nullable (snapshot de campos auditables después del cambio)
  ip                 inet
  user_agent         text nullable
  contexto           jsonb nullable (módulo, ruta, canal: web | api, metadatos adicionales)
  created_at         timestamptz
```

**Notas sobre el campo `ciudadano_id`:**
Permite recuperar eficientemente todos los accesos relacionados con un ciudadano concreto, independientemente del modelo consultado (ciudadano, historia social, apunte, valoración, plan, etc.). Sin este campo, reconstruir el historial de accesos a una persona requeriría un JOIN polimórfico costoso y propenso a omisiones.

Cada modelo auditable implementa el método `getCiudadanoId(): ?int` para que el `AuditService` pueda resolverlo automáticamente. En modelos que son directamente el ciudadano, devuelve `$this->id`. En modelos asociados (historia social, apunte, etc.), devuelve la FK correspondiente.

**Índices recomendados:**
- `(ciudadano_id, created_at DESC)` — para la vista contextual en ficha del ciudadano
- `(user_id, created_at DESC)` — para análisis de actividad por profesional
- `(auditable_type, auditable_id)` — para consultas polimórficas
- `(created_at)` — para la purga por retención y filtros temporales del supervisor

**No existe índice único.** Los registros de auditoría son inmutables y no-editables. No hay operación de UPDATE ni DELETE sobre esta tabla (salvo la purga por retención programada).

---

## 3. Componentes de implementación

### 3.1 Trait `Auditable`

El trait se aplica a todos los modelos Eloquent que manejan datos de ciudadanos. Cumple dos funciones:

1. **Declara la relación** `audits()` (morphMany).
2. **Activa el `AuditObserver`** vía `bootAuditable()`, que captura `created`, `updated` y `deleted`.
3. **Expone `camposAuditables()`**: lista de campos incluidos en los snapshots `datos_antes` / `datos_despues`. Por defecto, todos los fillables. Cada modelo puede sobreescribir este método para excluir campos sensibles de los snapshots (por ejemplo, campos cifrados en bruto, tokens internos).
4. **Declara `getCiudadanoId(): ?int`** como método que cada modelo debe implementar si corresponde a datos de un ciudadano.

El trait no audita lecturas. Las lecturas se registran explícitamente mediante `AuditService::registrarAcceso()`.

### 3.2 `AuditObserver`

Observador de Eloquent registrado automáticamente por el trait. Captura:

- `created` → acción `crear`, snapshot en `datos_despues`
- `updated` → acción `editar`, diff entre `getOriginal()` y `getChanges()` en `datos_antes` / `datos_despues`
- `deleted` → acción `eliminar`, snapshot en `datos_antes`

El observador resuelve el `user_id` desde `Auth::id()`. Si no hay usuario autenticado (proceso de consola, cola de trabajos), el registro se omite salvo que el contexto de ejecución establezca explícitamente un identificador de actuante.

El observador **no llama directamente** a `Audit::create()` — delega en `AuditService` para centralizar la lógica de resolución de `ciudadano_id` y `contexto`.

### 3.3 `AuditService`

Servicio inyectable que centraliza toda la lógica de registro. Es el único punto desde el que se crea un registro en `audits`.

**Método principal:**

```php
public function registrarAcceso(
    User $user,
    Model $modelo,
    string $accion = 'ver',
    ?int $ciudadanoId = null,
    array $contexto = [],
    ?array $datosAntes = null,
    ?array $datosDespues = null
): void
```

La resolución de `ciudadano_id` sigue este orden de prioridad:
1. El parámetro `$ciudadanoId` si se pasa explícitamente.
2. El resultado de `$modelo->getCiudadanoId()` si el modelo implementa el método.
3. `null` si ninguno de los anteriores está disponible.

El `contexto` se enriquece automáticamente con el canal de acceso (`web` o `api`), la ruta actual y el módulo Laravel activo.

**Contrato arquitectural:** todo componente que acceda a datos de ciudadanos de forma intencional (controladores Livewire, Resources de Filament, controladores API) está obligado a llamar a `AuditService::registrarAcceso()` para las operaciones de lectura. Las escrituras se cubren automáticamente por el Observer, pero la llamada explícita puede añadir contexto adicional.

### 3.4 Middleware `AuditarAccesoCiudadano`

Middleware aplicado a todas las rutas que acceden a recursos de ciudadanos. Actúa como **red de seguridad de segunda línea**: registra el acceso a nivel de ruta si no existe ya un registro para la misma petición en esa sesión. Previene que un nuevo punto de acceso incorporado sin llamada explícita al `AuditService` quede sin traza.

El middleware **no sustituye** la llamada explícita — complementa. Un acceso registrado dos veces (middleware + servicio) es un bug de implementación menor; un acceso no registrado es una rotura del principio de accountability.

---

## 4. Retención de registros

El período de retención es configurable desde el backoffice mediante la clave `auditoria.retención_dias` en `organizacion_configuracion`. El valor por defecto es `1825` (5 años).

Una tarea programada (`AuditPurgeCommand`) se ejecuta diariamente y elimina los registros cuya `created_at` supera el período de retención. La purga **sí es la única operación de DELETE** legítima sobre la tabla `audits`, y se ejecuta en proceso de consola, no desde la interfaz.

---

## 5. Vista contextual en la ficha del ciudadano (Livewire)

Al abrir la ficha de un ciudadano, el componente Livewire muestra un panel de accesos recientes al expediente.

### Qué ve cada rol

| Rol | Contenido visible |
|---|---|
| Profesional de referencia (TSR asignado) | Todos los accesos al expediente de ese ciudadano |
| Supervisor de la UO | Todos los accesos al expediente de ese ciudadano |
| Cualquier otro profesional | Solo sus propios accesos al expediente |

La restricción para roles no asignados es deliberada: ver que "otro profesional consultó este expediente hace dos horas" puede revelar información sobre el estado del caso o la existencia de una intervención activa, lo que constituye en sí mismo una filtración de metadatos.

### Presentación

- Se muestran los **10 accesos más recientes** por defecto.
- Cada fila muestra: fecha/hora relativa, nombre del profesional, UO, acción en lenguaje natural.
- Los accesos del propio usuario autenticado se presentan con tratamiento visual diferenciado (no son sospechosos).
- No se muestran IP ni user agent en esta vista — son datos para el supervisor.
- Un enlace "Ver historial completo" abre un modal con todos los accesos, paginados, sin filtros adicionales (el filtrado avanzado es competencia del visor de supervisión).

### Textos de acción en lenguaje natural

| Valor enum | Texto mostrado |
|---|---|
| `ver` | Consultó el expediente |
| `crear` | Registró nueva información |
| `editar` | Modificó información existente |
| `eliminar` | Eliminó un registro |
| `exportar` | Exportó datos |
| `imprimir` | Generó un documento |
| `acceso_restringido` | Accedió a expediente con protección especial |

---

## 6. Visor de supervisión en Filament

### Ubicación y acceso

`AuditResource` en el panel Filament, grupo **Supervisión**. Visible exclusivamente para roles `supervision` y `adm_sistema`.

### Scope automático de UO

El supervisor solo visualiza registros de profesionales cuya UO de actuación sea su UO o cualquiera de sus UOs descendientes (usando `staudenmeier/laravel-adjacency-list`). El rol `adm_sistema` no tiene restricción de scope y ve todos los registros.

### Columnas de la tabla

| Columna | Descripción |
|---|---|
| Fecha y hora | Precisión de segundos, ordenable, primario DESC |
| Profesional | Nombre completo + UO |
| Acción | Badge de color por tipo de acción |
| Sobre qué | Descripción legible del modelo afectado (ej: "Ciudadano: García López, Ana") |
| Ciudadano | Nombre del ciudadano si `ciudadano_id` está presente |

### Filtros disponibles

- **Rango de fechas** (obligatorio para evitar cargas masivas — máximo 90 días por consulta)
- **Profesional** — búsqueda por nombre
- **UO** — selector de árbol de UOs dentro del ámbito del supervisor
- **Tipo de acción** — multiselect con las opciones del enum
- **Ciudadano** — búsqueda por nombre o número de documento
- **Fuera de horario laboral** — filtro calculado que marca accesos realizados fuera del horario configurado en `organizacion_configuracion`; útil para detectar anomalías

### Vista de detalle

Al abrir un registro individual se muestra:
- Todos los campos de la tabla principal
- IP de origen
- User agent
- Contexto JSON formateado (módulo, ruta, canal)
- Diff de campos `datos_antes` / `datos_despues` renderizado lado a lado si la acción es `editar`

### Restricciones del Resource

- **Solo lectura.** No hay acciones `CreateAction`, `EditAction` ni `DeleteAction`.
- **Sin exportación masiva sin filtro.** La exportación a CSV/Excel requiere que al menos el filtro de rango de fechas esté activo, para evitar descargas de millones de registros.
- **Exportación registrada.** La acción de exportar logs genera a su vez un registro de auditoría con acción `exportar` sobre el propio `AuditResource` (excepción controlada a la regla de no auditar la auditoría — es la única).

---

## 7. Integración con accesos a colectivos protegidos

Cuando un profesional accede a un expediente de un ciudadano perteneciente a un colectivo protegido, el flujo de autorización (definido en el Módulo Ciudadanía) registra la solicitud, la autorización o denegación, y el acceso final como acción `acceso_restringido`. Toda esa trazabilidad pasa por `AuditService` con el contexto extendido apropiado (`motivo_declarado`, `autorizado_por`, etc.).

---

## 8. Integración con la API

Cuando los módulos de API estén implementados, los controladores API usarán el mismo `AuditService`. El campo `contexto` incluirá `canal: api` y el identificador del sistema cliente (credenciales de sistema). El `user_id` corresponderá al usuario actuante declarado en el token de usuario, no a las credenciales del sistema cliente.

---

## 9. Fuera del alcance de esta fase

- Panel de estadísticas de actividad por UO (análisis agregado, no registro individual) — diferido.
- Alertas automáticas al supervisor cuando se detectan patrones anómalos (accesos masivos, accesos fuera de horario reiterados) — diferido.
- Integración con sistemas externos de SIEM o gestión de logs corporativos — diferido.

---

## 10. Tests funcionales

> Los tests se organizan por componente / área funcional. Se usa la nomenclatura `it('...')` al estilo Pest para que puedan trasladarse directamente a código. Cada test declara su clase sugerida, los actores necesarios y el comportamiento esperado.
>
> **Actores reutilizados en los tests:**
> - `$profesional` — usuario con rol `profesional`, sin asignación de referencia sobre el ciudadano del test.
> - `$tsr` — trabajador social de referencia asignado al ciudadano del test.
> - `$supervisor` — usuario con rol `supervision`, UO jerárquicamente superior a la del profesional.
> - `$supervisor_otra_uo` — supervisor de una UO sin relación jerárquica con el profesional.
> - `$adm` — usuario con rol `adm_sistema`.
> - `$ciudadano` — registro de ciudadano con modelo auditable.
> - `$ciudadano_protegido` — ciudadano perteneciente a un colectivo especialmente protegido.

---

### 10.1 `AuditService` — Registro de accesos

**Clase sugerida:** `AuditServiceTest`

```
it('registra un acceso de lectura con todos los campos obligatorios rellenos')
```
- **Dado** `$profesional` autenticado y `$ciudadano`.
- **Cuando** se llama a `AuditService::registrarAcceso($profesional, $ciudadano, 'ver')`.
- **Entonces** existe exactamente un registro en `audits` con `user_id = $profesional->id`, `accion = 'ver'`, `auditable_type = Ciudadano::class`, `auditable_id = $ciudadano->id`, `ciudadano_id = $ciudadano->id`, `ip` no nulo y `created_at` con timestamp reciente.

---

```
it('resuelve ciudadano_id desde el modelo cuando no se pasa explícitamente')
```
- **Dado** un `$apunte` asociado a `$ciudadano` cuyo `getCiudadanoId()` devuelve `$ciudadano->id`.
- **Cuando** se llama a `AuditService::registrarAcceso($profesional, $apunte, 'ver')` sin pasar `$ciudadanoId`.
- **Entonces** el registro tiene `ciudadano_id = $ciudadano->id`.

---

```
it('usa el ciudadano_id explícito con prioridad sobre el resuelto por el modelo')
```
- **Dado** un `$apunte` cuyo `getCiudadanoId()` devuelve `$ciudadano->id`.
- **Cuando** se llama pasando `ciudadanoId: 9999` explícitamente.
- **Entonces** el registro tiene `ciudadano_id = 9999`.

---

```
it('enriquece el contexto con canal web y ruta actual automáticamente')
```
- **Dado** una petición HTTP activa a una ruta conocida.
- **Cuando** se registra cualquier acceso.
- **Entonces** el campo `contexto` del registro contiene `canal = 'web'` y la ruta de la petición actual.

---

```
it('omite el registro si no hay usuario autenticado y no se fuerza actuante')
```
- **Dado** que no hay sesión autenticada (contexto de consola).
- **Cuando** el `AuditObserver` intenta registrar un evento `created` en un modelo auditable.
- **Entonces** no se crea ningún registro en `audits`.

---

```
it('no genera registro de auditoría al leer registros de la tabla audits')
```
- **Dado** `$supervisor` autenticado.
- **Cuando** se consultan registros de `audits` (p.ej. listado en `AuditResource`).
- **Entonces** el conteo de `audits` no aumenta como consecuencia de esa lectura.

---

### 10.2 `AuditObserver` — Escrituras automáticas

**Clase sugerida:** `AuditObserverTest`

```
it('registra acción crear con snapshot en datos_despues al persistir un modelo auditable')
```
- **Dado** `$profesional` autenticado.
- **Cuando** se crea un nuevo `$apunte` (modelo con trait `Auditable`).
- **Entonces** existe un registro con `accion = 'crear'`, `datos_antes = null` y `datos_despues` conteniendo los campos auditables del apunte recién creado.

---

```
it('registra acción editar con diff correcto en datos_antes y datos_despues')
```
- **Dado** `$apunte` existente con `observacion = 'texto original'`.
- **Cuando** `$profesional` actualiza `observacion` a `'texto modificado'`.
- **Entonces** el registro tiene `accion = 'editar'`, `datos_antes` con `observacion = 'texto original'` y `datos_despues` con `observacion = 'texto modificado'`. Los campos no modificados no aparecen en el diff.

---

```
it('registra acción eliminar con snapshot en datos_antes al borrar un modelo auditable')
```
- **Dado** `$apunte` existente.
- **Cuando** `$profesional` lo elimina.
- **Entonces** existe un registro con `accion = 'eliminar'`, `datos_antes` con el snapshot del apunte y `datos_despues = null`.

---

```
it('excluye los campos no auditables del snapshot aunque estén en fillable')
```
- **Dado** un modelo que sobreescribe `camposAuditables()` excluyendo el campo `token_interno`.
- **Cuando** se crea o edita ese modelo.
- **Entonces** `datos_despues` no contiene la clave `token_interno`.

---

```
it('no genera registros de auditoría por eager loading interno de Eloquent')
```
- **Dado** un modelo con relaciones cargadas automáticamente al acceder a una propiedad.
- **Cuando** se accede a esa relación sin interacción explícita de usuario.
- **Entonces** no se crea ningún registro adicional en `audits`.

---

### 10.3 Vista contextual en la ficha del ciudadano

**Clase sugerida:** `PanelAccesosRecentesTest`

```
it('el TSR asignado ve todos los accesos al expediente del ciudadano')
```
- **Dado** tres registros en `audits` para `$ciudadano`: uno de `$tsr`, uno de `$profesional` y uno de `$supervisor`.
- **Cuando** `$tsr` abre la ficha de `$ciudadano`.
- **Entonces** el panel muestra los tres registros.

---

```
it('el supervisor de la UO ve todos los accesos al expediente del ciudadano')
```
- **Dado** los mismos tres registros anteriores.
- **Cuando** `$supervisor` abre la ficha de `$ciudadano`.
- **Entonces** el panel muestra los tres registros.

---

```
it('un profesional no asignado solo ve sus propios accesos al expediente')
```
- **Dado** un registro de `$tsr` y otro de `$profesional` para `$ciudadano`.
- **Cuando** `$profesional` (no es el TSR) abre la ficha de `$ciudadano`.
- **Entonces** el panel muestra únicamente el registro de `$profesional`, no el de `$tsr`.

---

```
it('el panel muestra como máximo 10 accesos recientes por defecto')
```
- **Dado** 15 registros en `audits` para `$ciudadano`.
- **Cuando** `$tsr` abre la ficha.
- **Entonces** el panel renderiza exactamente 10 filas, correspondientes a los 10 más recientes.

---

```
it('no expone IP ni user_agent en la vista contextual de la ficha')
```
- **Dado** registros con IP y user_agent rellenos.
- **Cuando** `$tsr` consulta el panel de accesos recientes.
- **Entonces** el HTML renderizado no contiene ninguna dirección IP ni cadena de user_agent.

---

```
it('los accesos del propio usuario autenticado se presentan con marcador visual diferenciado')
```
- **Dado** un registro de `$tsr` y otro de `$profesional` para `$ciudadano`.
- **Cuando** `$tsr` abre la ficha.
- **Entonces** la fila correspondiente al propio `$tsr` contiene el atributo o clase CSS que identifica el acceso propio; la fila de `$profesional` no lo contiene.

---

### 10.4 Visor de supervisión (`AuditResource`)

**Clase sugerida:** `AuditResourceTest`

```
it('un profesional sin rol supervision no puede acceder al AuditResource')
```
- **Dado** `$profesional` autenticado.
- **Cuando** intenta acceder a la URL del `AuditResource` en Filament.
- **Entonces** recibe respuesta 403 o es redirigido sin ver registros.

---

```
it('el supervisor solo ve registros de profesionales de su UO y sus descendientes')
```
- **Dado** un registro de `$profesional` (UO hija del supervisor) y un registro de un profesional de una UO sin relación jerárquica.
- **Cuando** `$supervisor` lista el `AuditResource`.
- **Entonces** solo aparece el registro de `$profesional`; el de la UO no relacionada no es visible.

---

```
it('adm_sistema ve registros de todas las UOs sin restricción de scope')
```
- **Dado** registros de profesionales de tres UOs distintas sin relación jerárquica entre sí.
- **Cuando** `$adm` lista el `AuditResource`.
- **Entonces** los tres registros son visibles.

---

```
it('el AuditResource no expone acciones de creación, edición ni eliminación')
```
- **Dado** `$supervisor` autenticado con acceso al visor.
- **Cuando** se inspecciona el `AuditResource`.
- **Entonces** no existen `CreateAction`, `EditAction` ni `DeleteAction` registradas en el resource.

---

```
it('la consulta sin filtro de rango de fechas no devuelve resultados y muestra aviso')
```
- **Dado** `$supervisor` en el visor sin aplicar ningún filtro.
- **Cuando** se ejecuta la consulta.
- **Entonces** la tabla no devuelve registros y se muestra un mensaje que indica que el filtro de rango de fechas es obligatorio.

---

```
it('el filtro de rango de fechas rechaza rangos superiores a 90 días')
```
- **Dado** `$supervisor` aplica un rango de 91 días.
- **Cuando** se ejecuta la consulta.
- **Entonces** se muestra un error de validación y no se devuelven resultados.

---

```
it('la exportación sin filtro de rango de fechas activo queda bloqueada')
```
- **Dado** `$supervisor` sin filtro de fechas activo.
- **Cuando** intenta exportar.
- **Entonces** la acción de exportar no está disponible o devuelve error de validación.

---

```
it('exportar registros genera a su vez un registro de auditoría con acción exportar')
```
- **Dado** `$supervisor` con filtro de fechas válido.
- **Cuando** ejecuta la exportación.
- **Entonces** se crea un nuevo registro en `audits` con `accion = 'exportar'`, `user_id = $supervisor->id` y `auditable_type` referenciando `AuditResource`.

---

```
it('la vista de detalle muestra el diff lado a lado para registros de acción editar')
```
- **Dado** un registro con `accion = 'editar'`, `datos_antes` y `datos_despues` rellenos.
- **Cuando** `$supervisor` abre el detalle de ese registro.
- **Entonces** la vista renderiza los dos snapshots en columnas comparables con los campos modificados identificados.

---

### 10.5 `AuditPurgeCommand` — Retención

**Clase sugerida:** `AuditPurgeCommandTest`

```
it('elimina registros cuya created_at supera el período de retención configurado')
```
- **Dado** retención configurada a 30 días y un registro con `created_at = now()->subDays(31)`.
- **Cuando** se ejecuta `AuditPurgeCommand`.
- **Entonces** ese registro ya no existe en `audits`.

---

```
it('preserva registros dentro del período de retención')
```
- **Dado** retención configurada a 30 días y un registro con `created_at = now()->subDays(29)`.
- **Cuando** se ejecuta `AuditPurgeCommand`.
- **Entonces** ese registro sigue existiendo en `audits`.

---

```
it('la purga es la única vía legítima de DELETE sobre audits')
```
- Verificación arquitectural: en ningún controlador, servicio ni resource existe una llamada directa a `Audit::destroy()`, `Audit::delete()` o equivalente fuera de `AuditPurgeCommand`.
- **Entonces** la búsqueda estática de esas llamadas en el código fuente (excluyendo `AuditPurgeCommand`) no produce resultados.

---

### 10.6 Colectivos protegidos

**Clase sugerida:** `AuditAccesoRestringidoTest`

```
it('el acceso autorizado a un ciudadano protegido se registra con acción acceso_restringido')
```
- **Dado** `$ciudadano_protegido` y `$profesional` con permiso de acceso a colectivos protegidos.
- **Cuando** el flujo de autorización del Módulo Ciudadanía concede el acceso.
- **Entonces** el registro tiene `accion = 'acceso_restringido'` y el `contexto` incluye `motivo_declarado` y `autorizado_por`.

---

```
it('el acceso denegado a un ciudadano protegido también queda registrado')
```
- **Dado** `$ciudadano_protegido` y `$profesional` sin permiso suficiente.
- **Cuando** el flujo de autorización deniega el acceso.
- **Entonces** existe un registro con `accion = 'acceso_restringido'` y el `contexto` refleja que el acceso fue denegado, sin que el profesional haya podido ver datos del ciudadano.

---

```
it('el servicio de urgencia con acceso preautorizado genera registro con indicación de régimen de emergencia')
```
- **Dado** un servicio configurado como servicio de urgencia con acceso preautorizado.
- **Cuando** un profesional de ese servicio accede a `$ciudadano_protegido`.
- **Entonces** el registro tiene `accion = 'acceso_restringido'` y el `contexto` incluye la indicación del régimen de emergencia.
