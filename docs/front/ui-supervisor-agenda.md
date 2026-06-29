# UI Agenda — Rol supervisor

**Módulo:** `Modules\Agenda`
**Rol:** Director / coordinador del centro
**Estado:** Especificación UI — pendiente de implementación
**Última revisión:** Junio 2026
**Referencia funcional:** `docs/modulo-agenda.md`

---

## 1. Contexto y principios de diseño

El supervisor gestiona la disponibilidad del equipo en cuatro pantallas distintas. El flujo es secuencial: primero se configura el centro (semana tipo + tipos de slot), luego los perfiles individuales y excepciones de cada profesional, y finalmente se genera y publica el cuadrante mensual.

**Principios que aplican a toda la UI del supervisor:**

- Las excepciones (vacaciones, bajas, permisos) **viven en la ficha del profesional**, no en el cuadrante. El cuadrante las incorpora automáticamente al generarse.
- El cuadrante no es editable celda a celda para redefinir franjas de atención. Solo permite añadir **eventos puntuales** (reuniones, mesas de trabajo) sobre el estándar ya definido en la semana tipo.
- VIDA 360 no valida ni autoriza permisos laborales. El director introduce el resultado de decisiones tomadas fuera del sistema.
- VIDA 360 no busca disponibilidades comunes al convocar reuniones. El director convoca sabiendo ya que hay hueco.

**Design system:** tokens VIDA 360 — primario `#2A5B8A`, acento `#C76E4A`, fondo `#FAF7F1`, sand `#F2EADA`. Tipografía Source Sans 3. Iconografía Heroicons outline. Componentes `op-*` sobre Bootstrap 5.3. Sidebar 188px + topbar 52px.

---

## 2. Pantalla: Semana tipo del centro

**Ruta:** Configuración → Semana tipo
**Acceso:** Solo supervisor
**Filament / Livewire:** Livewire (`SemanaTypoComponent`)

### 2.1 Propósito

El director define la distribución estándar del tiempo para un día laboral tipo en el centro. Es el molde base que se proyectará sobre todos los días laborables del cuadrante mensual. Los días de la semana que difieran del estándar (lunes con reunión de equipo, viernes sin tarde) se sobreescriben solo en esos días.

Esta pantalla es el **paso 2** del flujo de configuración. Los pasos se muestran como indicador de progreso en la parte superior.

### 2.2 Indicador de pasos

Barra horizontal con 5 pasos:

| # | Paso | Estado inicial |
|---|---|---|
| 1 | Horario del centro | ✓ Completado |
| 2 | Semana tipo | Activo |
| 3 | Tipos de slot | Pendiente |
| 4 | Perfiles del equipo | Pendiente |
| 5 | Generar cuadrante | Pendiente |

### 2.3 Layout principal

**Cabecera:** título "Semana tipo — [nombre del centro]", subtítulo con horario vigente (ej: "L-V 09:00–19:00"). Acciones: "Descartar cambios" + "Guardar semana tipo" (primario).

**Aviso contextual (infobox info):** "Define qué ocurre en un día tipo para cada profesional. Los días que sean iguales, cópialos. Los que tengan algo diferente edítalos solo en esos días. Haz clic en cualquier celda para añadir o editar una franja."

**Leyenda de tipos de franja:** fila horizontal con píldoras de color para cada tipo.

### 2.4 Grid de semana tipo

Cuadrícula con:
- **Filas:** un profesional por fila. Columna izquierda fija (188px) con avatar iniciales + nombre + cargo + jornada semanal.
- **Columnas:** lunes a viernes. Los días con variación respecto al estándar se marcan con indicador visual ("diferente") en la cabecera de columna.

**Interacción por celda:**
- Muestra los bloques de franja ya configurados para ese profesional ese día (chips con color por tipo y hora).
- Botón "＋ Añadir franja" al final de la celda.
- Clic en un bloque existente abre el drawer de edición de esa franja.
- Clic en la celda (fuera de un bloque) equivale a "Añadir franja".

**Botones globales sobre el grid:**
- "Copiar día a otros": selecciona día origen y días destino, reemplaza el contenido.
- "Añadir franja global": añade una franja a todos los profesionales en un día determinado (útil para reuniones de equipo).

### 2.5 Drawer de franja

Panel modal que aparece al editar o crear una franja. Campos:

| Campo | Tipo | Notas |
|---|---|---|
| Tipo de franja | Selector visual (4 opciones) | Atención ciudadana / Descanso / Trabajo propio / Evento interno |
| Hora inicio | time | |
| Hora fin | time | |
| Etiqueta | text (opcional) | Solo para Descanso y Evento interno |
| Aplicar a todos los profesionales | checkbox | Visible si se abre desde "Añadir franja global" |
| Slot de urgencias al inicio | checkbox | Solo visible si tipo = Atención ciudadana (reservado para uso futuro) |

**Tipos de franja y su comportamiento al materializar slots:**

| Tipo | Color | Genera slots | Estado slot |
|---|---|---|---|
| `atencion` | Azul primario | Sí — slots de 30 min consecutivos | `disponible` |
| `descanso` | Verde | No — bloque único | `bloqueado` |
| `trabajo_propio` | Gris | No — bloque único | `bloqueado` |
| `evento_interno` | Morado | No — bloque único, referencia `tipo_slot_id` | `bloqueado_evento` |

**Acciones:** "Eliminar franja" (destructivo, solo en edición) + "Cancelar" + "Guardar franja".

### 2.6 Panel de resumen de slots estimados

Bajo el grid, fila con 5 tarjetas (una por día de la semana) mostrando el número estimado de slots de 30 minutos de atención ciudadana que se generarán ese día, calculado en tiempo real según las franjas definidas. Se actualiza al guardar cualquier franja.

### 2.7 Guardado

Al guardar, el sistema persiste `HorarioCentro.semana_tipo` como JSON. **No afecta al cuadrante ya publicado.** Si hay un cuadrante en borrador, se muestra aviso: "Hay un borrador en curso. Los cambios en la semana tipo se aplicarán si regeneras el cuadrante."

---

## 3. Pantalla: Tipos de slot

**Ruta:** Configuración → Tipos de slot
**Acceso:** Solo supervisor
**Filament / Livewire:** Filament (`TipoSlotResource`)

### 3.1 Propósito

Catálogo de eventos internos configurables por el centro. Define los tipos de bloque que no son citas con ciudadanos: reuniones de equipo, mesas de trabajo, sesiones de coordinación, etc.

> Las citas con ciudadanos son siempre de 30 minutos y no se configuran aquí. Este catálogo solo cubre eventos internos.

### 3.2 Resource Filament

**Lista:** tabla con columnas Nombre, Duración, Bloquea a todos los convocados, Activo. Ordenable por nombre. Filtro por activo. Acción de edición inline.

**Formulario (crear / editar):**

| Campo | Tipo | Validación | Notas |
|---|---|---|---|
| `nombre` | TextInput | required, max:100 | Ej: "Reunión de equipo" |
| `descripcion` | Textarea | nullable | |
| `duracion_minutos` | numeric | required, min:15, múltiplo de 15 | Selector con pasos de 15 min |
| `bloquea_todos_convocados` | Toggle | default: false | Si true, al usarlo en semana tipo bloquea a todo el equipo |
| `activo` | Toggle | default: true | |

**Nota en el formulario:** "La duración incluye el tiempo de preparación y cierre. No se añaden buffers adicionales."

---

## 4. Pantalla: Perfil horario y excepciones del profesional

**Ruta:** Mi equipo → [profesional] → pestañas "Perfil horario" y "Excepciones"
**Acceso:** Supervisor (edición) y profesional (solo lectura de su propio perfil)
**Filament / Livewire:** Livewire integrado en la ficha existente del profesional

### 4.1 Contexto

Estas dos secciones se añaden como pestañas nuevas a la ficha de profesional existente. La ficha ya tiene las pestañas "Datos personales" e "Historial". Se añaden entre medias: Datos personales → **Perfil horario** → **Excepciones** → Historial.

### 4.2 Pestaña: Perfil horario

#### Cabecera de la pestaña

Infobox info: "El perfil horario define cuándo está disponible este profesional. Las actividades que realiza en ese tiempo se definen en la semana tipo del centro y en el cuadrante mensual."

#### Tarjeta resumen

Tres métricas en grid: Jornada semanal (horas), Días activos (lista abreviada L·M·X·J·V), Horario (franja principal).

#### Editor de días y horario

**Selector de días:** cinco botones circulares (L M X J V). Activo = fondo azul primario. Al desactivar un día, su fila de horario se muestra en gris tachado con "No trabaja este día".

**Franjas por día:** para cada día activo se muestran las franjas configuradas como píldoras:
- Píldora mañana (azul claro): "☀ 09:00–14:00"
- Píldora tarde (naranja claro, si existe): "🌙 15:00–19:00"
- Botón "＋ Añadir tarde" si no tiene tarde configurada

Al hacer clic en una píldora se abre un mini-drawer para editar esa franja (hora inicio, hora fin).

**Nota importante:** el perfil solo define disponibilidad temporal. No se configuran aquí los tipos de actividad.

#### Campos adicionales

| Campo | Tipo | Notas |
|---|---|---|
| Jornada semanal (horas) | numeric | Informativo para el resumen |
| Vigente desde | date | Fecha a partir de la cual aplica este perfil |
| Notas | text | Solo informativas — ej: "Reducción por conciliación familiar". Sin efecto en lógica de negocio |

#### Aviso al guardar

Banner warning: "Guardar cambios en el perfil horario no afecta al cuadrante ya publicado. Los cambios se aplicarán al generar el próximo cuadrante mensual."

**Acciones:** "Cancelar" + "Guardar perfil horario" (primario).

#### Comportamiento técnico

Persiste en `PerfilHorarioProfesional`. Si ya existe un perfil activo para este profesional y centro, lo actualiza. Si la fecha de vigencia cambia respecto al perfil actual, crea una nueva versión (`vigente_hasta` en el anterior, nuevo registro con `vigente_desde`).

### 4.3 Pestaña: Excepciones

#### Layout

Cabecera con título, subtítulo y botón "＋ Registrar excepción" (primario, alineado a la derecha).

Dos secciones:
1. **Próximas y en curso** — tabla con las excepciones futuras o activas.
2. **Historial pasado** — tabla colapsada con las excepciones pasadas, botón "Ver todo".

#### Tabla de excepciones

Columnas: Tipo (badge de color), Período (fechas + duración en días laborables), Afecta a citas (badge), Notas, Origen (manual / api_rrhh), Acciones (Editar, Eliminar).

**Colores de badge por tipo:**

| Tipo | Color |
|---|---|
| `baja_medica` | Rojo |
| `vacaciones` | Ámbar |
| `formacion` | Azul info |
| `dia_libre` | Verde |
| `reduccion_jornada` | Morado |
| `guardia` | Morado |
| `otros` | Gris |

#### Modal: registrar / editar excepción

| Campo | Tipo | Notas |
|---|---|---|
| Tipo de excepción | Select | Enum completo |
| Fecha inicio | date | |
| Fecha fin | date | |
| Franja afectada | time × 2 | Solo visible si tipo = `reduccion_jornada`. Vacío = día completo |
| Cancelar citas existentes | checkbox | Default: true. Label explicativo |
| Notas | text | Opcional |

**Nota bajo el checkbox de cancelar citas:** "Desmarca si el profesional va a gestionar sus citas antes de ausentarse."

**Acciones:** "Cancelar" + "Guardar excepción" (primario).

#### Comportamiento al guardar

Persiste en `ExcepcionProfesional`. Si `afecta_disponibilidad = true` y hay citas confirmadas en el período, el sistema muestra un resumen de las citas afectadas antes de guardar: "Hay N citas confirmadas en este período que serán canceladas. ¿Confirmar?" con opción de ver el listado.

---

## 5. Pantalla: Cuadrante mensual

**Ruta:** Cuadrante → [mes]
**Acceso:** Solo supervisor
**Filament / Livewire:** Livewire (`CuadranteMesComponent`)

### 5.1 Propósito

Vista central de supervisión. Permite al director revisar el cuadrante generado para el mes, añadir eventos puntuales no previstos en la semana tipo, y publicarlo cuando esté listo.

**Lo que el director puede hacer aquí:**
- Ver la distribución de todos los profesionales día a día.
- Ver las excepciones ya incorporadas automáticamente.
- Añadir eventos internos puntuales (reuniones, mesas de trabajo) sobre el estándar.
- Publicar el cuadrante.

**Lo que el director NO puede hacer aquí:**
- Redefinir franjas de atención celda a celda (eso va en la semana tipo).
- Editar excepciones (eso va en la ficha del profesional).

### 5.2 Cabecera

Título: "Cuadrante — [centro] · [Mes Año]". Subtítulo con badge de estado (`borrador` / `publicado`) + fecha de generación + número de profesionales + días laborables.

**Estado `borrador`:** botón "Publicar cuadrante" visible y activo (primario).
**Estado `publicado`:** solo lectura. Badge verde. Botón "Ver mes siguiente" si procede.

### 5.3 Alerta de excepciones incorporadas

Si el cuadrante tiene excepciones incorporadas automáticamente, banner info: "N excepciones incorporadas automáticamente desde las fichas del equipo: [lista resumida]. Haz clic en cualquier celda sombreada para ver el detalle."

### 5.4 Métricas resumen

Cuatro tarjetas: Slots de cita disponibles, Días con excepciones, Eventos internos, Slots afectados por excepciones.

### 5.5 Navegación por semanas

Flechas anterior / siguiente + label de semana actual (ej: "Semana 2 · 7–11 jul") + botones de acceso directo a cada semana del mes (1, 2, 3, 4, 5).

### 5.6 Leyenda

Fila horizontal con píldoras de color para cada tipo de bloque: Atención ciudadana, Descanso, Trabajo propio, Evento interno, Vacaciones, Baja médica, Formación, Día libre.

### 5.7 Grid del cuadrante

Cuadrícula con:
- **Filas:** un profesional por fila. Columna izquierda fija (148px) con avatar + nombre + cargo.
- **Columnas:** los días laborables de la semana visible (máximo 5).

**Cabecera de columna:** día de la semana + número de día del mes.

**Tipos de celda:**

| Situación | Visual | Interacción |
|---|---|---|
| Día normal con franjas estándar | Chips de color por tipo de franja | Clic abre modal para añadir evento |
| Día con excepción | Fondo atenuado + chip de excepción | Clic abre modal de detalle con enlace a ficha del profesional |
| Día no laborable | Fondo gris claro | No interactivo |

**Chips dentro de celda:**
- Chip atención (azul): hora de inicio–fin + etiqueta "Atención"
- Chip descanso (verde): hora + "Descanso"
- Chip trabajo propio (gris): hora + "T. propio"
- Chip evento interno (morado): etiqueta del evento
- Chip excepción (color por tipo): nombre del tipo de excepción

**Botón "＋ Evento"** dentro de cada celda normal, al final del contenido.

### 5.8 Modal: añadir evento puntual

Se abre al hacer clic en una celda normal (o en "＋ Evento").

| Campo | Tipo | Notas |
|---|---|---|
| Tipo | Selector visual | Reunión interna / Mesa de trabajo |
| Título | text | |
| Hora inicio | time | |
| Hora fin | time | |
| Profesionales convocados | checkboxes | Lista del equipo. El profesional de la celda seleccionado por defecto |
| Notas | text | Opcional |

Al guardar, crea un `EventoAgenda` con `origen = director` y bloquea los slots correspondientes de todos los convocados. Si algún convocado tiene citas confirmadas en esa franja, se muestra aviso: "N citas confirmadas serán afectadas."

**Acciones:** "Cancelar" + "Añadir al cuadrante" (primario).

### 5.9 Modal: detalle de excepción

Se abre al hacer clic en una celda con excepción.

Muestra:
- Nombre del profesional + día.
- Badge de tipo de excepción.
- Período completo de la excepción.
- Nota registrada (si existe).
- Origen (manual / api_rrhh).
- Texto: "Esta excepción está registrada en la ficha del profesional. Para modificarla, ve a Mi equipo → [nombre] → Excepciones."

**Acciones:** "Cerrar" + "Ir a la ficha del profesional →" (navega a la ficha con la pestaña Excepciones abierta).

### 5.10 Modal: publicar cuadrante

Checklist de revisión antes de confirmar:

- ✓ Semana tipo del centro aplicada a los N días laborables
- ✓ Perfiles individuales cruzados (resumen de particularidades)
- ✓ N excepciones incorporadas desde fichas del equipo
- ✓ N slots de cita de 30 min generados
- ⚠ N slots afectados por excepciones no disponibles para citas (si aplica)

Resumen: "Al publicar, los slots quedan disponibles para el gestor de citas. Los profesionales recibirán una notificación con su planificación de [mes]."

Campo opcional: "Notas para el equipo".

**Acciones:** "Cancelar" + "Publicar y notificar al equipo" (primario verde).

Al publicar:
- `CuadranteMes.estado` cambia a `publicado`.
- Se registran `publicado_en` y `publicado_por_id`.
- `SlotMaterializadorService` genera los slots.
- Se envía notificación a todos los profesionales del centro.
- El badge de estado en la cabecera cambia a verde "Publicado".
- El botón "Publicar cuadrante" desaparece.

---

## 6. Flujo completo — resumen de navegación

```
Configuración
  └── Horario del centro (ya implementado)
  └── Semana tipo  ← nueva pantalla
  └── Tipos de slot  ← nuevo Resource Filament

Mi equipo
  └── [ficha profesional]
        └── Pestaña: Perfil horario  ← nueva pestaña
        └── Pestaña: Excepciones    ← nueva pestaña

Cuadrante
  └── [mes]  ← nueva pantalla
        └── Grid semana por semana
        └── Añadir eventos puntuales
        └── Ver detalle de excepciones → enlace a ficha
        └── Publicar
```

---

## 7. Notas de implementación

- El grid del cuadrante puede tener hasta 5 columnas × N profesionales. En centros con muchos profesionales, considerar scroll vertical en el body del grid con cabecera fija.
- La columna de nombre de profesional es sticky a la izquierda en scroll horizontal.
- La cabecera del grid es sticky al topbar (top: 52px) en scroll vertical.
- Los chips dentro de las celdas son compactos (font-size 10–11px). En celdas con muchos bloques, limitar a 4 chips visibles y mostrar "+N más" al hacer hover.
- El modal de añadir evento comprueba en tiempo real si alguno de los convocados tiene ese hueco ya ocupado (slot en estado `reservado` o `bloqueado_evento`). Si es así, muestra aviso inline bajo el checkbox del convocado afectado, pero no impide la selección.
- En modo `basico`, la pantalla de cuadrante es de solo lectura (el cuadrante se genera automáticamente). El botón "Publicar" no existe.
