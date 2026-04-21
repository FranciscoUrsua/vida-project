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
