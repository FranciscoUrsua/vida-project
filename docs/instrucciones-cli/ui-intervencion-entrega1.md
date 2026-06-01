# Instrucciones para Claude CLI — UI Intervención · Entrega 1
## `docs/instrucciones-cli/ui-intervencion-entrega1.md`

> Implementación del interfaz operativo del rol `intervencion` — Primera entrega.
> Cubre el layout base con navegación persistente y la pantalla de Agenda completa
> (vistas de día, semana y mes).
>
> **Diseño de referencia:** `docs/front/ui-intervencion.md` (secciones 2 y 3)
> **Módulos afectados:** `Modules/Intervencion`, `Modules/Agenda`, `Modules/Mensajes`
> **Principios generales:** `docs/principios-vida360.md`

---

## Contexto

El módulo de Intervención está en **fase 1**: modelos, policies y 35 tests de
backend pasando. No hay Livewire todavía. Esta entrega añade la capa Livewire
operativa para el rol `intervencion`, empezando por la estructura de navegación
y la pantalla de Agenda.

El interfaz operativo vive en rutas protegidas bajo el middleware `auth` y
el gate `role:intervencion`. Las vistas usan el layout `layouts.operativo` que
se crea en esta entrega. El backoffice Filament en `/admin` no se toca.

---

## Paso 1 — Revisar el estado actual antes de escribir nada

```bash
# ¿Existe ya algún layout operativo?
ls resources/views/layouts/

# ¿Hay componentes Livewire en el módulo de Intervención?
ls Modules/Intervencion/resources/views/livewire/ 2>/dev/null || echo "Vacío"

# ¿Existen rutas operativas para intervencion?
grep -r "intervencion\|agenda" routes/web.php Modules/Intervencion/routes/ 2>/dev/null

# Tests actuales del módulo
php artisan test --filter=Intervencion 2>&1 | tail -5
```

Si ya existe algún layout o componente Livewire relacionado, documentarlo antes
de proceder. No sobreescribir trabajo existente sin confirmarlo explícitamente.

---

## Paso 2 — Layout base operativo

Crear `resources/views/layouts/operativo.blade.php`.

El layout contiene:

**Sidebar izquierdo** (196px, fijo, fondo `var(--color-background-primary)`):
- Logo/nombre de la app: "VIDA 360" con icono `ti-heart-handshake` en color
  `#534AB7`. Lee el nombre real de `config('app.name')`.
- Ítems de navegación: Agenda, Mis casos, Alertas y mensajes, Buscar ciudadano.
  Cada ítem muestra un badge de conteo cuando procede (ver más abajo).
- Parte inferior: avatar con iniciales del usuario autenticado
  (componente `<x-avatar>` existente), nombre completo y
  "Intervención · [nombre del CSS del profesional]".

**Área principal** (`flex: 1`, sin padding propio): slot `{{ $slot }}` de
Blade components, o `@yield('content')` si se usa `@extends`.

**Badges del sidebar:**

| Ítem | Badge | Condición |
|---|---|---|
| Mis casos | Número de ciudadanos asignados | Siempre visible |
| Alertas y mensajes | Total alertas sin reconocer + mensajes no leídos | Solo si > 0, fondo coral `#F0997B` |

Los contadores los proporciona `IntervencionSidebarDataService` (crear en
`Modules/Intervencion/app/Services/`). Este servicio consulta:
- `alertas` donde `usuario_id = Auth::id()` y `reconocida_en IS NULL`
- `mensajes_participantes` donde `user_id = Auth::id()` y `leido_en IS NULL`
  (solo mensajes, no alertas)
- `historias_sociales` donde `profesional_responsable_id = Auth::id()`

El servicio se inyecta en un componente Livewire `<livewire:intervencion.sidebar />`
que se refresca automáticamente cada 5 minutos con `wire:poll.300s`.

**Ítem activo**: el ítem activo del sidebar se detecta con
`request()->routeIs('intervencion.agenda*')`, etc. No usar JavaScript para esto.

---

## Paso 3 — Rutas

Añadir en `Modules/Intervencion/routes/web.php`:

```php
Route::middleware(['auth', 'role:intervencion'])->prefix('intervencion')->name('intervencion.')->group(function () {
    Route::get('/', fn() => redirect()->route('intervencion.agenda.index'));
    Route::get('/agenda', \Modules\Intervencion\Http\Livewire\AgendaPage::class)->name('agenda.index');
    // Las rutas de Entrega 2 y 3 se añadirán más adelante
});
```

Verificar que `Modules/Intervencion/Providers/RouteServiceProvider.php` carga
este fichero de rutas. Si no existe el provider, crearlo siguiendo el patrón
de otros módulos del proyecto.

---

## Paso 4 — Componente Livewire: AgendaPage

Crear `Modules/Intervencion/app/Http/Livewire/AgendaPage.php` y su vista
`Modules/Intervencion/resources/views/livewire/agenda-page.blade.php`.

### 4.1 Propiedades

```php
public string $vista = 'dia';       // 'dia' | 'semana' | 'mes'
public string $fechaAncla;          // ISO 8601, fecha de referencia de navegación
```

`$fechaAncla` se inicializa en `mount()` con `today()->toDateString()`.

### 4.2 Métodos de navegación

```php
public function navegarAnterior(): void  // retrocede 1 día/semana/mes según $vista
public function navegarSiguiente(): void // avanza 1 día/semana/mes según $vista
public function irAHoy(): void           // resetea $fechaAncla a today()
public function setVista(string $vista): void // cambia entre 'dia', 'semana', 'mes'
```

### 4.3 Propiedades computadas

```php
// Título para la barra superior según vista y fecha ancla
public function getTituloFechaProperty(): string

// Citas para la vista de día (array de 4 días)
public function getCitasDiaProperty(): array  // [fecha => [citas]]

// Citas para la vista de semana (lunes a viernes)
public function getCitasSemanaProperty(): array  // [fecha => [citas]]

// Datos del mes para la vista de mes
public function getDatosMesProperty(): array  // [dia => ['citas' => n, 'tipos' => [...]]]

// KPIs del sidebar superior
public function getKpisProperty(): array
```

### 4.4 KPIs

Los cuatro KPIs se calculan así:

| KPI | Fuente |
|---|---|
| Alertas sin reconocer | `alertas` donde `usuario_id = Auth::id()` y `reconocida_en IS NULL` |
| Seguimientos vencidos | `historias_sociales` join `planes_intervencion` donde responsable = Auth::id() y `fecha_siguiente_seguimiento < today()` y plan activo |
| Citas (día/semana/mes) | `citas` del módulo Agenda donde `profesional_id` del slot coincide con el profesional del usuario autenticado, en el rango de fechas de la vista activa |
| Mensajes sin leer | `mensajes_participantes` donde `user_id = Auth::id()` y `leido_en IS NULL` |

Si el módulo Agenda no tiene el método de consulta necesario, crear un método
en el servicio o consultar directamente la tabla `slots` / `citas` con la
relación `profesional_id`. Si las tablas no existen todavía, devolver `0`
con un comentario `// TODO: conectar con módulo Agenda cuando esté disponible`.

### 4.5 Datos de citas

Las citas se obtienen del módulo de Agenda. Cada cita debe exponer:

```php
[
    'id'         => int,
    'hora'       => string,       // '09:30'
    'duracion'   => int,          // minutos
    'ciudadano'  => string,       // nombre completo
    'tipo'       => string,       // 'entrevista' | 'seguimiento' | 'urgencia' | 'evento'
    'fecha'      => string,       // ISO 8601
]
```

Si el módulo de Agenda no está disponible, el componente devuelve datos de
ejemplo para desarrollo (fixture hardcodeada) con un comentario
`// TODO: reemplazar con consulta real al módulo Agenda`.

---

## Paso 5 — Vista Blade: agenda-page

La vista Blade del componente `AgendaPage` implementa las tres vistas definidas
en `docs/front/ui-intervencion.md`, sección 3.

### 5.1 Estructura general

```
[Barra superior con título + fecha + navegación + selector de vista]
[Franja de 4 KPIs]
[Área de contenido — cambia según $vista]
```

### 5.2 Barra superior

- Título: "Agenda"
- Fecha: `$this->tituloFecha`
- Botones `‹` `›` con `wire:click="navegarAnterior"` y `wire:click="navegarSiguiente"`
- Botón "Hoy" con `wire:click="irAHoy"`
- Segmentado de vista: tres botones con `wire:click="setVista('dia')"`,
  `wire:click="setVista('semana')"`, `wire:click="setVista('mes')"`.
  El activo tiene clase CSS diferenciada.

### 5.3 Franja de KPIs

Cuatro tarjetas en fila. Los valores vienen de `$this->kpis`.
"Alertas sin reconocer" y "Seguimientos vencidos" se colorean en coral y ámbar
respectivamente si son > 0. El label del tercer KPI cambia:
- Vista día → "Citas hoy"
- Vista semana → "Citas esta semana"
- Vista mes → "Citas este mes"

### 5.4 Vista de día

Cuatro columnas: ayer, hoy, mañana, pasado mañana. Cada columna:
- Cabecera: nombre corto del día + número. Hoy en morado `#534AB7`.
- Ayer: opacidad reducida (`opacity-50`).
- Citas como tarjetas con borde izquierdo coloreado por tipo.
- Slots libres como tarjetas punteadas — **solo en la columna de hoy y futuras**,
  no en ayer. Los slots libres son las franjas sin cita entre 08:00 y 17:00.

**Código de color por tipo** (aplicar como clase CSS o inline style):

| Tipo | Fondo | Borde |
|---|---|---|
| `entrevista` | `#EEEDFE` | `#534AB7` |
| `seguimiento` | `#E1F5EE` | `#0F6E56` |
| `urgencia` | `#FAECE7` | `#993C1D` |
| `evento` | `var(--color-background-secondary)` | `var(--color-border-secondary)` |

Las citas de tipo `urgencia` tienen además un chip de texto "Urgencia" en
fondo `#F5C4B3` color `#4A1B0C`.

### 5.5 Vista de semana

Cuadrícula de hora × día. Columnas: lunes a viernes (5 columnas).
Filas: 08:00 a 17:00 (10 filas de 1 hora). Sábados y domingos no se muestran.

Las citas son bloques posicionados en la celda correspondiente a su hora de inicio.
Si una cita dura más de 1 hora, el bloque ocupa visualmente más de una fila.
No se muestran slots libres en la vista de semana.

### 5.6 Vista de mes

Calendario mensual clásico (7 columnas L–D). Para cada día:
- Número del día.
- Indicadores por tipo (máximo 3 visibles): píldoras compactas con
  conteo y color semántico.
- Orden de prioridad: urgencias primero, luego entrevistas, seguimientos, eventos.
- Fines de semana con fondo ligeramente atenuado.
- Día actual con borde morado `#534AB7` de 1.5px.
- Días del mes anterior/siguiente con opacidad reducida.

Al hacer clic en un día de la vista de mes, se ejecuta:
```php
$this->fechaAncla = $fecha;
$this->setVista('dia');
```

---

## Paso 6 — Tests

Crear `Modules/Intervencion/tests/Feature/Livewire/AgendaPageTest.php`.

```
TF-LW-AGE-01 — Redirige a /intervencion/agenda al acceder a /intervencion
TF-LW-AGE-02 — Usuario sin rol intervencion recibe 403 al acceder a /intervencion/agenda
TF-LW-AGE-03 — El componente AgendaPage monta correctamente con fechaAncla = today()
TF-LW-AGE-04 — navegarSiguiente en vista dia avanza 1 día
TF-LW-AGE-05 — navegarAnterior en vista dia retrocede 1 día
TF-LW-AGE-06 — navegarSiguiente en vista semana avanza 7 días
TF-LW-AGE-07 — navegarSiguiente en vista mes avanza 1 mes
TF-LW-AGE-08 — irAHoy resetea fechaAncla a today() desde cualquier fecha
TF-LW-AGE-09 — setVista('semana') cambia la vista y actualiza el título
TF-LW-AGE-10 — La vista renderiza sin errores en los tres modos
TF-LW-AGE-11 — El label del tercer KPI dice "Citas hoy" en vista día
TF-LW-AGE-12 — El label del tercer KPI dice "Citas esta semana" en vista semana
TF-LW-AGE-13 — El sidebar contiene los cuatro ítems de navegación
TF-LW-AGE-14 — El ítem "Agenda" tiene clase activa cuando la ruta es intervencion.agenda.*
```

Para los tests que necesitan el módulo Agenda no implementado, usar la fixture
hardcodeada del paso 4.5 y documentarlo con `// TODO`.

Ejecutar al terminar:

```bash
php artisan test --filter=AgendaPage
php artisan test --filter=Intervencion
```

La suite completa del proyecto no debe romper tests existentes:

```bash
php artisan test 2>&1 | tail -5
```

---

## Lo que NO hay que hacer

- No tocar `/admin` ni ningún Resource de Filament.
- No implementar las rutas de Mis casos, Alertas/mensajes ni Buscar ciudadano
  (son Entregas 2 y 3).
- No añadir lógica de escritura en la agenda — el TSR solo consulta.
- No implementar la pantalla del ciudadano ni las herramientas de trabajo
  (es Entrega 3).
- No crear tablas ni migraciones nuevas. Todos los datos vienen de tablas
  ya existentes o de fixtures de desarrollo.
- No usar `markTestSkipped` sin un comentario `// TODO:` que explique el bloqueo.

---

## Checklist de finalización

- [ ] `php artisan test --filter=AgendaPage` pasa los 14 tests
- [ ] `php artisan test --filter=Intervencion` sigue pasando los 35 tests previos
- [ ] `php artisan test` no introduce fallos nuevos
- [ ] La ruta `/intervencion` redirige a `/intervencion/agenda`
- [ ] Un usuario sin rol `intervencion` recibe 403 al acceder
- [ ] Las tres vistas renderizan sin errores PHP en el navegador
- [ ] La navegación anterior/siguiente funciona en las tres vistas
- [ ] Los slots libres solo aparecen en la vista de día
- [ ] Los fines de semana no aparecen en la vista de semana
- [ ] Entrada añadida en `CHANGELOG.md`
