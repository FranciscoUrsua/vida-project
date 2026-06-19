# UI — Página del Plan de Intervención
## VIDA 360 · `docs/front/ui-intervencion-plan.md`

> Este documento describe el diseño de la página `PlanPage`, la pantalla de
> elaboración, visualización y edición del Plan de Intervención. Debe leerse
> junto con `docs/modulo-intervencion.md` (sección 5) y
> `docs/front/ui-intervencion-ciudadano.md`.

---

## 1. Contexto y acceso

`PlanPage` es una página Livewire propia (`/intervencion/plan/{plan}`),
no un modal ni una pestaña de `CiudadanoPage`. Tiene su propia URL y su
propio ciclo de vida de componente.

**Puntos de entrada:**

- Botón "Ver plan" en `CiudadanoPage` cuando existe un plan activo o en borrador.
- Botón "Crear plan" en `CiudadanoPage` cuando no existe ningún plan activo.
- Enlace desde el timeline de la historia social (apuntes de tipo `plan`).

**Mantiene el sidebar global** con las opciones de navegación general
(inicio, mis casos, agenda, alertas, configuración). El sidebar no cambia
respecto al resto de la aplicación.

**No es accesible directamente por URL sin autenticación.** Las mismas
políticas de acceso que `CiudadanoPage` aplican aquí:
`PlanDeIntervencionPolicy` verifica permisos y ámbito de UO.

---

## 2. Layout general

```
┌─────────────────────────────────────────────────────────────────┐
│ SIDEBAR │              BANDA DE CONTEXTO (sticky)               │
│  52px   │  ← Intervención  Amparo Serrano  [Borrador v1]  [PDF] │
├─────────┼────────────────────────────────────────┬──────────────┤
│         │                                        │              │
│ SIDEBAR │         CUERPO DEL PLAN                │  ÍNDICE      │
│         │         (scroll vertical)              │  LATERAL     │
│         │                                        │  (sticky)    │
│         │                                        │  140px       │
└─────────┴────────────────────────────────────────┴──────────────┘
```

- **Sidebar:** 52px fijo, idéntico al resto de la aplicación.
- **Banda de contexto:** sticky en la parte superior, altura ~48px. Contiene
  el nombre del ciudadano, el tipo de plan, el estado (badge), la versión y
  las acciones principales.
- **Cuerpo del plan:** columna central con scroll. Secciones verticales
  consecutivas con separación visual clara.
- **Índice lateral:** 140px sticky a la derecha. Lista de secciones con
  indicadores de progreso. Incluye un resumen de periodicidad de seguimiento
  al pie.

---

## 3. Banda de contexto

Siempre visible mientras se trabaja en el plan.

**Elementos de izquierda a derecha:**

1. Botón `← Intervención` — navega de vuelta a `CiudadanoPage` (wire:navigate).
2. Nombre del ciudadano (o "Unidad de convivencia — [nombre titular]").
3. Nombre del tipo de plan (badge neutro).
4. Badge de estado: `Borrador` (amber) / `Activo` (green) / `En revisión`
   (blue) / `Cerrado` (gray).
5. Badge de versión: `v1`, `v2`…
6. Acciones: `Generar PDF` siempre visible. `Activar plan` solo en borrador,
   desactivado si no están ambas firmas marcadas.

**Regla de acciones según estado:**

| Estado | Acciones disponibles |
|---|---|
| Borrador | Generar PDF · Activar plan (disabled hasta firmas) |
| Activo | Generar PDF · Cerrar plan |
| En revisión | Generar PDF · (edición bloqueada hasta nueva firma) |
| Cerrado | Generar PDF · (solo lectura) |

---

## 4. Índice lateral (sticky)

Lista vertical de secciones con un indicador de estado por sección:

- ● gris: sección vacía o no iniciada
- ● azul: sección actual (scroll position)
- ● verde: sección con contenido suficiente

Al pie del índice, un bloque pequeño muestra:
- **Periodicidad:** [valor del select]
- **Próximo seguimiento:** fecha o `—`

El índice es solo visual en el mockup; en la implementación los ítems hacen
scroll suave a la sección correspondiente (`scrollIntoView`).

---

## 5. Secciones del plan

Las secciones se renderizan siempre en este orden. No son colapsables —
el plan es un documento lineal y debe leerse de arriba abajo.

### Sección 0 — Datos de la persona

Solo lectura. Los datos vienen de la Historia Social y de la Unidad de
Convivencia vinculada al plan.

**En la UI:** bloque compacto con fondo `--color-background-secondary`.
Muestra nombre completo, fecha de nacimiento, documento de identidad y
domicilio del ciudadano titular. Si el plan está vinculado a una UC,
muestra la composición de la unidad (miembros activos con su tipo de
relación respecto al titular).

Un badge `Solo lectura · desde Historia Social` deja claro que estos
datos no se editan aquí.

**En el PDF:** sección completa y primera del documento. Incluye todos
los campos de identificación del ciudadano o de la UC.

### Sección 1 — Diagnóstico social

Dos bloques diferenciados visualmente dentro de la misma sección.

**Bloque A — Evidencia de fichas (solo lectura)**

Lista de tarjetas, una por ficha seleccionada. Cada tarjeta muestra:
- Nombre de la ficha y fecha de cumplimentación.
- Icono de candado (🔒) que refuerza que es solo lectura.
- Botón × para eliminar la ficha del diagnóstico.
- Contenido expandible (colapsado por defecto).

En el estado colapsado se muestra solo el encabezado (nombre + fecha).
En el estado expandido se muestra el contenido completo de la ficha tal
como fue registrado.

**Si el plan está firmado:** el botón × dispara el modal de motivo
obligatorio antes de eliminar. Ver sección 8.

Botón al pie: `+ Añadir ficha` — abre el drawer del historial (sección 7).

**Bloque B — Síntesis profesional**

Campo de texto enriquecido con barra de herramientas mínima:
negrita (`B`), cursiva (`I`), lista con viñetas. Sin más opciones.

Placeholder: *"Redacta aquí tu síntesis diagnóstica a partir de la
evidencia anterior."*

Si el plan está firmado, cualquier edición del texto dispara el modal
de motivo al intentar guardar.

### Sección 2 — Objetivos

Grid de dos columnas: objetivos generales a la izquierda, específicos
a la derecha vinculados al general correspondiente con sangría visual.

Cada objetivo general muestra:
- Texto del objetivo.
- Lista de sus específicos (sangría, guión).
- Badge de estado: `Pendiente` / `En proceso` / `Conseguido` / `Abandonado`.
- Botón de edición inline.

Botón `+ Añadir objetivo` en el header de la sección. Al pulsar, abre
un panel lateral (no un drawer de toda la altura, sino un panel inline
que aparece bajo el botón) con:
1. Lista de objetivos del catálogo del tipo de plan, agrupados por nivel.
2. Opción "Texto libre" si ninguno del catálogo encaja.

En planes firmados, añadir o eliminar un objetivo requiere motivo.

### Sección 3 — Compromisos del Ayuntamiento

Tabla con columnas: Prestación, Concreción, Responsable, Inicio previsto,
Estado, Acciones.

La columna Prestación muestra el nombre y el código de catálogo en texto
secundario bajo el nombre.

Botón `+ Añadir` en el header abre un buscador de prestaciones del catálogo
(reutiliza el componente de búsqueda existente). Una vez seleccionada la
prestación, aparece el campo `descripcion_especifica` como texto libre
opcional y el selector de responsable.

**Regla de negocio visible:** el botón de guardar de la fila nueva está
desactivado mientras no haya prestación seleccionada. Tooltip: *"Toda
actuación del Ayuntamiento debe estar vinculada a una prestación del
catálogo."*

### Sección 4 — Compromisos de la persona

Lista de ítems, más simple que la tabla de actuaciones del Ayuntamiento.

Cada ítem muestra:
- Texto libre del compromiso.
- Badge opcional con el nombre de la prestación vinculada (cuando aplica).
- Botón de edición y botón de eliminar.

Botón `+ Añadir` en el header abre un formulario inline con campo de
texto libre obligatorio y selector de prestación opcional.

### Sección 5 — Profesionales participantes

Lista de participantes. El profesional responsable siempre en primer lugar,
con badge `Responsable`, sin botón de eliminar (se gestiona desde la
cabecera del plan o desde `CiudadanoPage`).

El resto de participantes muestran nombre, rol en el plan y servicio de
procedencia si aplica. Botón × para dar de baja a cada uno.

Botón `+ Añadir participante` abre un buscador de usuarios del sistema.
Al seleccionar, aparece el campo `rol_en_plan` como texto libre obligatorio.

### Sección 6 — Seguimiento y firmas

Última sección. Dos subsecciones dentro de la misma tarjeta.

**Subsección A — Condiciones de seguimiento**

Dos campos:

1. `Frecuencia de seguimiento` — select con opciones:
   `Bimensual` / `Trimestral` / `Cuatrimestral` / `Semestral`.
   Valor por defecto: `Trimestral`.

2. `Observaciones sobre el seguimiento` — textarea, texto libre, opcional.
   Placeholder: *"Acuerdos sobre el seguimiento, condiciones especiales…"*

**Subsección B — Firmas**

Grid de dos columnas, una por firmante.

Cada columna muestra:
- Nombre completo y rol ("Trabajadora Social responsable" / "Persona interesada").
- Checkbox `Ha firmado en papel` con label explícito.
- Al marcar el checkbox, el sistema registra `firmado_en = now()` y muestra
  la fecha y hora de registro.
- Fecha de la firma presencial (campo date, separado del timestamp de registro
  en el sistema). El TSR introduce la fecha real de la firma manuscrita.

Cuando ambos checks están marcados:
- Aparece un aviso verde: *"Ambas partes han firmado. El plan puede activarse."*
- El botón `Activar plan` en la banda de contexto se habilita.

Nota informativa al pie: *"Una vez activado el plan, cualquier cambio requerirá
indicar el motivo. El documento PDF puede generarse en cualquier momento desde
el botón superior."*

---

## 6. Drawer del historial (timeline)

Se activa desde el botón `Añadir ficha` / `+ Añadir otra ficha` en la sección
de diagnóstico.

**Comportamiento:**
- Aparece desde la derecha, ocupando el 40% del ancho de la pantalla.
- El resto de la pantalla se oscurece con un overlay semitransparente.
- Se cierra con el botón × del drawer, con la tecla Escape, o pulsando
  fuera del drawer.

**Contenido del drawer:**

Cabecera con título "Historia social — fichas" y botón de cierre.

Filtros en chips horizontales: `Todas` / `Valoraciones` / `Entrevistas` /
más filtro de fecha (`Último mes` / `Último año` / `Todo`).

Cuerpo con lista de valoraciones/entrevistas en orden cronológico inverso.
Cada una es un bloque colapsable que muestra:
- Nombre del tipo de valoración o entrevista y fecha.
- Al expandir: lista de fichas con checkbox individual por ficha.
- Las fichas ya incluidas en el diagnóstico aparecen con checkbox marcado
  y badge `Añadida`.

Pie del drawer con dos botones: `Cancelar` y `Aplicar selección`.
Al aplicar, las fichas marcadas (y no previamente en el diagnóstico) se
añaden como tarjetas al bloque A del diagnóstico.

**Si el plan está firmado:** al aplicar la selección con cambios respecto
al estado anterior, el drawer muestra primero el modal de motivo antes de
confirmar los cambios.

---

## 7. Estados de edición según estado del plan

| Operación | Borrador | Activo (firmado) | En revisión | Cerrado |
|---|---|---|---|---|
| Editar diagnóstico texto | ✓ libre | ✓ con motivo | ✗ | ✗ |
| Añadir/eliminar ficha | ✓ libre | ✓ con motivo | ✗ | ✗ |
| Añadir/editar objetivo | ✓ libre | ✓ con motivo | ✗ | ✗ |
| Añadir actuación Ayto. | ✓ libre | ✓ con motivo | ✗ | ✗ |
| Cambiar estado objetivo | ✓ libre | ✓ libre | ✓ libre | ✗ |
| Añadir participante | ✓ libre | ✓ con motivo | ✗ | ✗ |
| Marcar firma | ✓ libre | — | — | ✗ |
| Editar seguimiento | ✓ libre | ✓ con motivo | ✗ | ✗ |
| Generar PDF | ✓ | ✓ | ✓ | ✓ |

"Con motivo" = el cambio dispara el modal de motivo obligatorio, que genera
un registro en `plan_cambios` antes de persistir el cambio.

El cambio de estado de un objetivo no requiere motivo porque forma parte
del seguimiento natural del plan, no de su contenido estructural.

---

## 8. Modal de motivo obligatorio

Aparece cuando se intenta modificar cualquier contenido de un plan en estado
`activo`.

**Contenido:**
- Título: "Cambio en plan firmado"
- Descripción breve de la operación que se está realizando (ej: *"Para
  eliminar la ficha económica del diagnóstico, indica el motivo."*)
- Textarea de motivo, obligatoria. Sin mínimo de caracteres pero sin posibilidad
  de confirmar vacío.
- Dos botones: `Cancelar` (descarta el cambio) y `Confirmar cambio`.

**Al confirmar:**
1. Se llama a `$plan->registrarCambio(...)` con el snapshot actual y el motivo.
2. Se aplica el cambio al plan.
3. El modal se cierra y la UI refleja el nuevo estado.

El modal NO aparece en estado `borrador`. Solo en `activo`.

---

## 9. Modos de la página: crear, ver, editar

`PlanPage` maneja tres contextos con la misma URL y el mismo componente:

**Crear:** no existe plan. Se monta con `historia_id` (o `unidad_convivencia_id`)
como parámetro. El componente crea un plan en estado `borrador` al montar
(o al primer guardado, según se decida en implementación). Todas las secciones
aparecen vacías con sus respectivos CTAs.

**Ver/Editar activo:** el plan existe. El componente recibe `plan_id`.
El estado del plan determina qué campos son editables (tabla sección 7).
No hay un "modo edición" explícito — los campos son editables o no según
el estado, sin necesidad de pulsar un botón "Editar".

**Solo lectura (cerrado):** plan en estado `cerrado`. Todo es solo lectura.
La banda de contexto muestra el motivo de cierre. Se puede generar el PDF.

---

## 10. Generación del PDF

El botón `Generar PDF` está siempre visible en la banda de contexto.

Al pulsar:
1. `PlanPage` llama a `$this->generarPdfPlan($plan->id)` que devuelve un
   `StreamedResponse`.
2. El navegador descarga el archivo con nombre
   `plan_{ciudadano_id}_v{version}_{fecha}.pdf`.
3. No se almacena el PDF en el servidor; se genera en el momento.

El PDF incluye la sección 0 (datos del ciudadano/UC) completa, aunque en la
UI esa sección sea compacta. El contenido del PDF sigue el orden de las
secciones 0-6 del plan.

---

## 11. Referencias de componentes y rutas

| Elemento | Referencia |
|---|---|
| Componente Livewire | `Modules\Intervencion\Http\Livewire\PlanPage` |
| Vista | `Modules/Intervencion/resources/views/livewire/plan-page.blade.php` |
| Ruta | `GET /intervencion/plan/{plan}` — `plan.show` |
| Ruta crear | `GET /intervencion/plan/crear?historia={id}` — `plan.crear` |
| CSS | `resources/css/app-operativo.css` (clases prefijo `plan-`) |
| Servicio PDF | `Modules\Intervencion\Services\PlanPdfService` |
| Policy | `Modules\Intervencion\Policies\PlanDeIntervencionPolicy` |

---

## 12. Decisiones de diseño

- **Sin modo edición explícito.** Los campos se editan inline. El estado del
  plan controla qué es editable. Esto reduce fricción y es coherente con el
  resto de la aplicación.

- **El drawer del historial no reemplaza la pantalla.** Es un panel superpuesto
  que no hace navegar al usuario fuera del plan. Esto es deliberado: el TSR
  necesita seleccionar fichas sin perder el contexto de lo que ya ha escrito
  en el diagnóstico.

- **El diagnóstico tiene dos capas semánticamente distintas.** La evidencia
  de fichas (hechos registrados, inmutables salvo con motivo) y la síntesis
  profesional (interpretación del TSR, editable). Esta separación tiene valor
  legal y de trazabilidad.

- **El PDF se genera bajo demanda, no se almacena.** Hasta que exista el módulo
  de Documentos, el PDF es efímero. El TSR lo imprime cuando necesita firmar.

- **El módulo de Documentos está fuera del alcance actual.** La columna
  `documento_firmado_id` en `firmas_plan` está reservada para cuando se
  implemente. No se muestra ningún campo de upload en la UI actual.

---

*Documento elaborado en junio 2026.*
