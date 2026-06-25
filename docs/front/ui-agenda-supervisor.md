# UI Agenda — Vista del supervisor de centro

**Módulo:** `Modules\Agenda`
**Última revisión:** junio 2026
**Estado:** Diseño aprobado — pendiente de implementación Livewire

---

## 1. Principio de diseño

El supervisor de centro trabaja en la interfaz operativa Livewire **sin salir de ella** para las tareas del día a día. Filament se reserva exclusivamente para configuración estructural que cambia raramente. El criterio de separación es: si el supervisor necesita tocarlo más de una vez al mes, va en Livewire; si es una configuración estable del centro, va en Filament.

---

## 2. Distribución Filament / Livewire

### 2.1 Filament — configuración estable (grupo *Agenda — Configuración*)

Accesible desde el enlace "Configuración del centro ↗" en el pie del sidebar operativo. Abre en nueva pestaña para no interrumpir el flujo.

| Resource | Descripción | Frecuencia de uso |
|---|---|---|
| `HorarioCentroResource` | Días de apertura, horas, turnos, buffers, modo de agenda | Al abrir el centro o cambiar de horario estacional |
| `TipoSlotResource` | Tipos de atención: duración, % urgencias, canal permitido | Al añadir o modificar un tipo de atención |
| `PerfilHorarioProfesionalResource` | Horario habitual de cada profesional en el centro | En incorporaciones, reducciones de jornada o cambios de adscripción |

### 2.2 Livewire — operación diaria

Toda la gestión operativa ocurre en la interfaz principal. El supervisor nunca necesita ir a Filament para gestionar el trabajo del día.

---

## 3. Estructura de la interfaz operativa

### 3.1 Sidebar

```
CSS Vallecas Norte
Supervisor/a de centro
─────────────────────
AGENDA
  📅 Cuadrante mensual
  ⚠️  Ausencias           [badge con nº pendientes]
  🗓  Excepciones
  👥 Eventos internos
─────────────────────
  ⚙️  Configuración del centro ↗
      Horario · Tipos de atención · Perfiles
```

El badge en "Ausencias" muestra el número de citas pendientes de gestionar ese día. Desaparece cuando todas las citas están reasignadas o descartadas.

El enlace de configuración está en el footer del sidebar, visualmente separado de las opciones operativas, con un icono ↗ que indica que abre en otra pestaña/entorno.

### 3.2 Pantalla activa por defecto

Al acceder al módulo de agenda, la pantalla activa es **Ausencias del día**. Es la que requiere atención inmediata y es donde el supervisor empieza su jornada si hay incidencias.

---

## 4. Pantallas operativas

### 4.1 Cuadrante mensual

**Ruta sugerida:** `LivewireComponent: CuadranteSupervisorPage`

**Topbar:**
- Título: "Cuadrante mensual" + mes/año + estado (`borrador` / `publicado`)
- Selector de vista: Semana actual / Mes completo
- Botón "Regenerar" (modo estándar/avanzado): regenera el borrador a partir del horario y perfiles actuales
- Botón "Publicar cuadrante" (primary): cambia el estado a `publicado` y materializa los slots

**Grid de cuadrante:**

Tabla con columna fija de profesional (nombre + tipo de perfil) y una columna por día laborable. Cada celda muestra las franjas horarias del profesional ese día codificadas por color:

| Color | Franja | Campo en `LineaCuadrante.franjas` |
|---|---|---|
| Azul claro | Atención ciudadana | `tipo: "atencion"` |
| Morado claro | Sesión interna / coordinación | `tipo: "sesion"` |
| Verde claro | Actividad colectiva | `tipo: "colectivo"` |
| Gris discontinuo | Reserva para imprevistos | `tipo: "reserva"` |
| Rojo claro | Ausencia | celda con `anulada: true` |

Las celdas de profesionales itinerantes en días asignados a otro centro muestran el nombre del otro centro en texto tenue, sin franjas.

El día actual se marca visualmente en la cabecera de columna.

**Interacción:** hacer clic en una celda abre un formulario inline o modal para editar las franjas del profesional ese día (añadir, quitar o modificar franjas). Solo disponible cuando el cuadrante está en estado `borrador`.

**Leyenda** visible debajo del grid.

---

### 4.2 Ausencias del día

**Ruta sugerida:** `LivewireComponent: AusenciasSupervisorPage`

**Topbar:**
- Título: "Ausencias del día" + fecha
- Botón "Registrar ausencia" (primary): navega a la pantalla de Excepciones con el formulario abierto

**Sección: ausencias sobrevenidas con citas pendientes**

Por cada profesional con ausencia sobrevenida hoy, se muestra un panel de alerta con:
- Nombre del profesional, tipo de excepción, número de citas afectadas
- Enlace "Ver expediente" (opcional, si aplica)
- Lista de citas canceladas automáticamente por `GestionAusenciaService`, cada una con:
  - Hora de la cita original
  - Nombre del ciudadano
  - Tipo de cita y duración
  - Estado: `Pendiente` / `Reasignada` / `Descartada`
  - Botones de acción: **Reasignar** / **Descartar**

Al hacer clic en **Reasignar** se abre el panel lateral de reasignación (ver sección 4.2.1).

Al hacer clic en **Descartar** la cita queda en estado `cancelada` con `motivo_cancelacion = 'Ausencia del profesional — descartada por supervisor'`.

Cuando todas las citas de un panel están gestionadas, el panel muestra un estado de "todo gestionado" y el badge del sidebar se actualiza.

**Sección: no-shows de ciudadanos**

Lista de citas donde el ciudadano no se presentó ese día, con opciones de "Liberar slot" o "Contactar".

#### 4.2.1 Panel lateral de reasignación

Se desliza desde el lado derecho de la pantalla sin navegar a otra página. Muestra:

- Datos de la cita original: ciudadano, hora, tipo de atención, profesional original
- Lista de **slots disponibles hoy** para el mismo tipo de atención, ordenados por hora:
  - Primero los slots de urgencia (`bloqueado_urgencia`) marcados con un indicador ⚡
  - Luego los slots ordinarios disponibles
  - Para cada slot: hora, nombre del profesional, tipo de slot
- Si no hay ningún slot disponible hoy: estado vacío explícito con mensaje claro
- Botón "Cerrar sin reasignar" en el footer del panel

Al seleccionar un slot se ejecuta `GestionAusenciaService::reasignar()`:
- Se crea `ReasignacionCita`
- La cita vuelve a estado `confirmada` con el nuevo profesional/slot
- El slot destino pasa a `reservado`
- Se genera alerta al profesional nuevo

**Restricción de diseño:** la búsqueda de slots se limita estrictamente al día de la ausencia. No se ofrecen días alternativos desde este panel.

---

### 4.3 Excepciones de profesionales

**Ruta sugerida:** `LivewireComponent: ExcepcionesSupervisorPage`

**Topbar:**
- Título: "Excepciones de profesionales"
- Subtítulo: "Vacaciones, bajas, reducciones y cambios puntuales"

**Formulario de nueva excepción** (visible en la parte superior de la página):

| Campo | Tipo | Notas |
|---|---|---|
| Profesional | Select | Solo profesionales activos del centro |
| Tipo de excepción | Select | Baja médica · Vacaciones · Reducción de jornada · Permiso retribuido · Cambio puntual de horario |
| Fecha de inicio | Date | Requerida |
| Fecha de fin | Date | Opcional; null = indefinido |
| Notas | Text | Ej: nº de parte de baja |

Al guardar, se crea `ExcepcionProfesional`. Si el cuadrante ya está publicado y `afecta_disponibilidad = true`, el sistema ejecuta el flujo de PF-07.5 (anulan líneas y slots, se generan alertas de citas afectadas que aparecerán en la pantalla de Ausencias).

**Lista de excepciones activas y próximas:**

Tabla o lista con: avatar + nombre del profesional, tipo de excepción (con chip de color), rango de fechas, botón de eliminar. Ordenada por fecha de inicio ascendente.

---

### 4.4 Eventos internos

**Ruta sugerida:** `LivewireComponent: EventosSupervisorPage`

**Topbar:**
- Título: "Eventos internos"
- Subtítulo: "Reuniones, formaciones y sesiones de equipo"
- Botón "Nuevo evento" (primary)

**Lista de eventos próximos:**

Por cada `EventoAgenda`, se muestra:
- Fecha y hora de inicio
- Espacio reservado (si tiene)
- Nombre del evento
- Profesionales convocados y duración
- Tipo de evento (chip de color): Sesión interna / Actividad colectiva / Coordinación
- Botón de editar

Los eventos bloquean los slots de los profesionales convocados (`bloqueado_evento`). Este efecto se produce al crear el evento.

**Formulario de nuevo evento** (modal o inline):

| Campo | Tipo |
|---|---|
| Nombre | Text |
| Fecha y hora de inicio | DateTime |
| Duración en minutos | Number |
| Tipo | Select (Sesión interna / Actividad colectiva / Coordinación) |
| Profesionales convocados | MultiSelect (profesionales del centro) |
| Espacio | Select (espacios del centro, opcional) |

---

## 5. Comportamiento del badge de ausencias

El badge en el item "Ausencias" del sidebar muestra el número de citas en estado `cancelada` con `motivo_cancelacion` de ausencia del profesional, del día actual, que no tienen `ReasignacionCita` asociada ni estado `descartada`. Se recalcula en tiempo real mediante Livewire polling o evento.

---

## 6. Notas de implementación

- Todos los componentes Livewire del supervisor se ubican en `Modules/Agenda/app/Livewire/Supervisor/`.
- El layout del supervisor reutiliza `operativo.blade.php` con el sidebar específico de la sección de agenda.
- Los componentes Livewire usan `#[Computed]` para derivar datos del cuadrante; el cuadrante no se recarga en cada render.
- El panel lateral de reasignación es un componente hijo que recibe la cita como parámetro y emite un evento `citaReasignada` al padre.
- Los colores de franja usan los tokens del design system VIDA: `--vida-color-atencion`, `--vida-color-sesion`, `--vida-color-colectivo`, `--vida-color-reserva`. No colores hardcodeados.
- Bootstrap 5.3 como capa de primitives; componentes `op-*` para piezas de producto compartidas.
