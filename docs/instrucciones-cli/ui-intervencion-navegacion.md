# Instrucciones para Claude CLI — Navegación y funcionalidad básica UI operativa
## `docs/instrucciones-cli/ui-intervencion-navegacion.md`

> Conecta los elementos de UI ya renderizados con su funcionalidad real:
> enlaces a la pantalla del ciudadano, correcciones en la tabla de casos,
> apertura del redactor de mensajes, menú de usuario en topbar.
>
> **Prerequisito:** entregas 1, 2 y 3 completadas y design system aplicado.
> **Módulos afectados:** `Modules/Intervencion`, `Modules/Mensajes`,
> `resources/views/layouts/`

---

## Paso 1 — Revisar estado antes de empezar

```bash
git pull origin master

# Rutas disponibles
php artisan route:list --path=intervencion -v

# Confirmar que la ruta del ciudadano existe
php artisan route:list --name=intervencion.ciudadano.show

# Tests actuales
php artisan test --filter="AgendaPage|MisCasosPage|BuscarCiudadanoPage|BuzonPage|CiudadanoPage" \
  2>&1 | tail -5
```

---

## Paso 2 — Agenda: citas con ciudadano → pantalla del ciudadano

### 2.1 Contexto

La fixture de citas en `AgendaPage` devuelve actualmente un array con campos
básicos. Para navegar a la pantalla del ciudadano necesitamos `historia_id`
en cada cita de tipo ciudadano.

### 2.2 Ampliar la estructura de datos de cita

En `AgendaPage`, la fixture (o la consulta real cuando el módulo Agenda esté
disponible) debe incluir dos campos adicionales:

```php
[
    'id'          => int,
    'hora'        => string,       // '09:30'
    'duracion'    => int,          // minutos
    'ciudadano'   => string,       // nombre completo — null si no es cita con ciudadano
    'historia_id' => int|null,     // ID de HistoriaSocial — null si tipo != ciudadano
    'tipo'        => string,       // 'entrevista' | 'seguimiento' | 'urgencia' | 'evento'
    'subtipo'     => string|null,  // 'mesa_etmf' | 'taller' | null — para eventos futuros
    'fecha'       => string,
]
```

Actualizar la fixture de desarrollo para incluir `historia_id` con IDs reales
de historias sociales existentes en la base de datos de desarrollo:

```php
// En mount() o en el método que genera la fixture:
$historiasEjemplo = HistoriaSocial::where('profesional_responsable_id', Auth::user()->profesional_id)
    ->limit(5)
    ->pluck('id')
    ->toArray();

// Si no hay historias, usar IDs de ejemplo que no rompen el render
$historiaId = $historiasEjemplo[0] ?? null;
```

### 2.3 Clic en cita — vista de día

En `agenda-page.blade.php`, las tarjetas de cita en la vista de día:

```blade
{{-- Si la cita tiene historia_id → enlazar a pantalla del ciudadano --}}
@if($cita['historia_id'])
    <a href="{{ route('intervencion.ciudadano.show', $cita['historia_id']) }}"
       class="cita-card cita-card--{{ $cita['tipo'] }}"
       wire:navigate>
        <span class="cita-hora">{{ $cita['hora'] }}</span>
        <span class="cita-nombre">{{ $cita['ciudadano'] }}</span>
        <span class="cita-tipo">{{ ucfirst($cita['tipo']) }}</span>
    </a>

{{-- Si no tiene historia_id → div no clicable por ahora --}}
@else
    <div class="cita-card cita-card--{{ $cita['tipo'] }}"
         {{-- TODO: enlazar a pantalla de mesa/taller cuando estén implementadas --}}
         title="{{ $cita['ciudadano'] ?? 'Evento interno' }}">
        <span class="cita-hora">{{ $cita['hora'] }}</span>
        <span class="cita-nombre">{{ $cita['ciudadano'] ?? 'Evento interno' }}</span>
        <span class="cita-tipo">{{ ucfirst($cita['tipo']) }}</span>
    </div>
@endif
```

### 2.4 Clic en cita — vista de semana

Mismo patrón. Los bloques de cita en la cuadrícula semanal son clicables
si tienen `historia_id`, no clicables si no lo tienen.

### 2.5 Clic en día — vista de mes

El clic en un día de la vista de mes ya navega a la vista de día de esa fecha
(`$this->fechaAncla = $fecha; $this->setVista('dia')`). No hay cambio aquí.

---

## Paso 3 — Mis casos: correcciones de columnas y navegación

### 3.1 Problema actual

La columna "Ciudadano/a" muestra el número de historia social en lugar del
nombre del ciudadano. Verificar cómo se construye la query en `MisCasosPage`:

```bash
grep -n "nombre\|ciudadano\|historia" \
  Modules/Intervencion/app/Http/Livewire/MisCasosPage.php | head -30
```

### 3.2 Corrección de la query

La query debe hacer join con `ciudadanos` para obtener nombre y apellidos,
y exponer el `id` de la historia social para el enlace:

```php
public function getCasosProperty(): LengthAwarePaginator
{
    return HistoriaSocial::query()
        ->with(['ciudadano', 'planActivoAsp'])  // eager load
        ->withCount(['planesEspecializadosActivos'])
        ->withExists(['alertasSinReconocer'])
        ->where('profesional_responsable_id', Auth::user()->profesional_id)
        // ... filtros existentes ...
        ->paginate($this->porPagina);
}
```

Si la relación `ciudadano` no existe en `HistoriaSocial`, añadirla:

```php
// En HistoriaSocial.php — si no existe ya
public function ciudadano(): BelongsTo
{
    return $this->belongsTo(Ciudadano::class);
}
```

Consultar `docs/modulo-intervencion.md` para verificar el nombre exacto de
la FK (`ciudadano_id`).

### 3.3 Nueva estructura de columnas

La tabla pasa de 5 a 6 columnas:

| # | Columna | Contenido |
|---|---|---|
| 1 | Ciudadano/a | Nombre completo (`$hs->ciudadano->nombre_completo`) |
| 2 | Historia Social | Número de HS (`$hs->numero`) — si existe |
| 3 | Próximo seguimiento | Semáforo existente |
| 4 | PISO | Estado del plan general |
| 5 | Planes especializados | Recuento |
| 6 | Alertas | Icono si hay alertas |

El campo que contiene el número de Historia Social: consultar
`docs/modulo-intervencion.md` para el nombre exacto. Probablemente
`numero`, `numero_historia` o `codigo`. Si no existe un campo de número
visible, mostrar el `id` prefijado: `HS-{{ str_pad($hs->id, 6, '0', STR_PAD_LEFT) }}`.

### 3.4 Navegación desde la tabla

Toda la fila es clicable y lleva a la pantalla del ciudadano:

```blade
<tr wire:click="$dispatch('navigate', { url: '{{ route('intervencion.ciudadano.show', $caso->id) }}' })"
    style="cursor: pointer;"
    class="fila-caso">
    <td>{{ $caso->ciudadano->nombre_completo }}</td>
    <td class="font-mono text-sm">{{ $caso->numero ?? 'HS-'.str_pad($caso->id, 6, '0', STR_PAD_LEFT) }}</td>
    {{-- resto de columnas --}}
</tr>
```

Alternativamente, usando `wire:navigate` en un enlace que envuelve la primera celda
y haciendo el resto de la fila visualmente clicable con CSS. La opción de `wire:click`
con dispatch es más limpia para filas completas.

**El número de Historia Social NO es un enlace independiente** — toda la fila navega
como unidad. No añadir un `<a>` dentro de la celda HS porque crearía un enlace
anidado dentro de la fila clicable.

---

## Paso 4 — Alertas y mensajes: botón "Nuevo mensaje"

### 4.1 Estado actual

El botón "Nuevo mensaje" en `BuzonPage` no tiene acción asignada.

### 4.2 Componente NuevoMensaje

Crear `Modules/Mensajes/app/Http/Livewire/NuevoMensajePage.php` y su vista,
o implementarlo como modal dentro de `BuzonPage`. La opción modal es más
apropiada para un flujo corto como este.

**Propiedades del modal:**

```php
public bool $modalAbierto = false;
public string $destinatarioBusqueda = '';
public ?int $destinatarioId = null;
public string $destinatarioNombre = '';
public string $asunto = '';
public string $cuerpo = '';
public array $resultadosDestinatario = [];
```

**Método de búsqueda de destinatario:**

```php
public function buscarDestinatario(): void
{
    if (strlen($this->destinatarioBusqueda) < 2) {
        $this->resultadosDestinatario = [];
        return;
    }

    $this->resultadosDestinatario = User::query()
        ->with('profesional')
        ->whereHas('profesional', function ($q) {
            $q->where(
                DB::raw("CONCAT(nombre, ' ', apellido1, ' ', COALESCE(apellido2, ''))"),
                'ILIKE',
                '%' . $this->destinatarioBusqueda . '%'
            );
        })
        ->where('id', '!=', Auth::id())  // excluirse a uno mismo
        ->limit(8)
        ->get()
        ->map(fn($u) => [
            'id'     => $u->id,
            'nombre' => $u->profesional?->nombre_completo ?? $u->email,
            'rol'    => $u->roles->first()?->name ?? '—',
        ])
        ->toArray();
}

public function seleccionarDestinatario(int $id, string $nombre): void
{
    $this->destinatarioId = $id;
    $this->destinatarioNombre = $nombre;
    $this->destinatarioBusqueda = $nombre;
    $this->resultadosDestinatario = [];
}
```

**Método de envío:**

```php
public function enviarMensaje(): void
{
    $this->validate([
        'destinatarioId' => 'required|exists:users,id',
        'asunto'         => 'required|min:3|max:200',
        'cuerpo'         => 'required|min:5',
    ]);

    // Crear hilo y primer mensaje
    // Consultar docs/modulo-mensajes.md para la estructura exacta
    // de mensajes_hilos y mensajes
    $hilo = MensajeHilo::create(['asunto' => $this->asunto]);
    $hilo->participantes()->attach([Auth::id(), $this->destinatarioId]);
    $hilo->mensajes()->create([
        'autor_id' => Auth::id(),
        'cuerpo'   => $this->cuerpo,
    ]);

    $this->modalAbierto = false;
    $this->reset(['destinatarioBusqueda', 'destinatarioId', 'destinatarioNombre', 'asunto', 'cuerpo']);
    $this->dispatch('mensaje-enviado');
    // Redirigir a la pestaña de mensajes para ver el hilo recién creado
    $this->pestana = 'mensajes';
}
```

### 4.3 Vista del modal

El modal sigue los patrones del design system: `--radius-lg`, `--shadow-2`,
fondo blanco, borde `--color-ink-200`.

Estructura del formulario:
- Campo de búsqueda de destinatario con autocompletado (lista desplegable
  de resultados bajo el input).
- Campo asunto.
- Textarea cuerpo.
- Botones: "Cancelar" (secundario) + "Enviar mensaje" (primario).

### 4.4 Apertura del modal

```blade
{{-- Botón en la topbar de BuzonPage --}}
<button wire:click="$set('modalAbierto', true)"
        class="btn btn-primary">
    <x-heroicon-o-pencil-square class="icon-16" aria-hidden="true"/>
    Nuevo mensaje
</button>
```

Si el mensaje viene desde la agenda (cita → "Enviar mensaje"), el asunto
y el contexto deben precargarse. Implementar mediante parámetros de URL:

```
/intervencion/mensajes?nuevo=1&asunto=Cita+14+abr+2026+Roberto+Sanz
```

El componente lee estos parámetros en `mount()`:

```php
public function mount(string $asunto = ''): void
{
    $this->pestana = 'mensajes';
    if ($asunto) {
        $this->modalAbierto = true;
        $this->asunto = urldecode($asunto);
    }
}
```

---

## Paso 5 — Buscar ciudadano: clic en nombre → pantalla del ciudadano

### 5.1 Estado actual

El nombre del ciudadano en los resultados de búsqueda no tiene enlace activo.

### 5.2 Lógica de navegación por nivel de acceso

La navegación depende del nivel de acceso ya calculado en `BuscarCiudadanoPage`:

```blade
@if($resultado['nivel'] === 'propio' || $resultado['nivel'] === 'otra_uo')
    {{-- Nivel 1 y 2: navegar directamente (el nivel 2 registra acceso en audits) --}}
    @if($resultado['historia_id'])
        <a href="{{ route('intervencion.ciudadano.show', $resultado['historia_id']) }}"
           wire:navigate
           class="font-semibold text-primary hover:underline">
            {{ $resultado['nombre'] }}
        </a>
    @else
        {{-- Sin HS: el nombre no es enlace; el botón "Abrir Historia Social" es la acción --}}
        <span class="font-semibold" style="color: var(--color-ink-900);">
            {{ $resultado['nombre'] }}
        </span>
    @endif

@elseif($resultado['nivel'] === 'protegido')
    {{-- Nivel 3: el nombre no es enlace; hay que solicitar acceso primero --}}
    <span class="font-semibold" style="color: var(--color-ink-900);">
        {{ $resultado['nombre'] }}
    </span>
@endif
```

**Importante:** el clic en el nombre de un ciudadano de nivel 2 debe registrar
el acceso en audits igual que el botón "Ver Historia Social". Compartir el
mismo método `registrarAccesoNivel2(int $historiaId)` para ambas acciones.

### 5.3 Botón "Dar de alta nuevo ciudadano"

Mantener desactivado con atributo `disabled` y tooltip explicativo:

```blade
<button disabled
        title="Alta de ciudadanos: pendiente de implementación"
        class="btn btn-secondary opacity-50 cursor-not-allowed">
    <x-heroicon-o-user-plus class="icon-16" aria-hidden="true"/>
    Dar de alta nuevo ciudadano
</button>
```

Añadir entrada en `BACKLOG.md`:

```markdown
**Alta de ciudadano desde búsqueda** — pendiente
`Módulo: Ciudadanía / Intervención`
El botón "Dar de alta nuevo ciudadano" en BuscarCiudadanoPage está deshabilitado.
Requiere diseño e implementación del formulario de alta de ciudadano en el módulo Ciudadanía.
```

---

## Paso 6 — Menú de usuario: de sidebar inferior a topbar

### 6.1 Estado actual

El nombre y rol del profesional aparecen en la parte inferior izquierda del sidebar.
En Filament el menú de usuario está arriba a la derecha. Esta tarea homogeneiza
el patrón moviendo el menú de usuario al topbar del layout operativo.

### 6.2 Cambios en `layouts/operativo.blade.php`

**Eliminar** del sidebar el bloque inferior con el usuario:
```blade
{{-- ELIMINAR este bloque del sidebar --}}
<div class="sidebar__user">
    {{-- avatar + nombre + rol --}}
</div>
```

**Añadir** en el topbar del layout (barra superior fija de 56px), a la derecha:

```blade
<div class="topbar__user" x-data="{ abierto: false }" style="position:relative;">
    <button @click="abierto = !abierto"
            @click.outside="abierto = false"
            class="topbar__user-btn"
            aria-haspopup="true"
            :aria-expanded="abierto">

        {{-- Avatar con iniciales --}}
        <div class="avatar avatar--sm">
            {{ Auth::user()->profesional?->iniciales ?? substr(Auth::user()->email, 0, 2) }}
        </div>

        {{-- Nombre completo (visible en viewports >= md) --}}
        <span class="topbar__user-nombre hidden md:inline">
            {{ Auth::user()->profesional?->nombre_completo ?? Auth::user()->email }}
        </span>

        <x-heroicon-o-chevron-down class="icon-16" aria-hidden="true"
           :class="{ 'rotate-180': abierto }"></i>
    </button>

    {{-- Menú desplegable --}}
    <div x-show="abierto"
         x-transition
         class="topbar__user-menu"
         style="display:none;">

        {{-- Info del usuario --}}
        <div class="topbar__user-info">
            <div class="font-semibold" style="font-size:14px; color:var(--color-ink-900);">
                {{ Auth::user()->profesional?->nombre_completo ?? Auth::user()->email }}
            </div>
            <div style="font-size:12px; color:var(--color-ink-500);">
                {{ Auth::user()->roles->first()?->name ?? '—' }}
            </div>
            @if(Auth::user()->profesional?->css)
            <div style="font-size:12px; color:var(--color-ink-500);">
                {{-- TODO: nombre del CSS cuando centroActivo() esté implementado --}}
                {{ Auth::user()->profesional->css ?? '' }}
            </div>
            @endif
        </div>

        <div class="topbar__user-divider"></div>

        {{-- Cerrar sesión --}}
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="topbar__user-menu-item topbar__user-menu-item--danger">
                <x-heroicon-o-arrow-right-on-rectangle class="icon-16" aria-hidden="true"/>
                Cerrar sesión
            </button>
        </form>
    </div>
</div>
```

### 6.3 CSS del menú de usuario

Añadir en `resources/css/app-operativo.css`:

```css
.topbar__user {
    position: relative;
    display: flex;
    align-items: center;
}

.topbar__user-btn {
    display: flex;
    align-items: center;
    gap: var(--space-2);
    padding: var(--space-1) var(--space-2);
    border-radius: var(--radius-md);
    border: none;
    background: transparent;
    cursor: pointer;
    color: var(--color-ink-700);
    font-family: var(--font-sans);
    font-size: 14px;
}

.topbar__user-btn:hover {
    background: var(--color-sand);
}

.topbar__user-menu {
    position: absolute;
    top: calc(100% + 8px);
    right: 0;
    min-width: 220px;
    background: #FFFFFF;
    border: 1px solid var(--color-ink-200);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-2);
    z-index: 50;
    overflow: hidden;
}

.topbar__user-info {
    padding: var(--space-3) var(--space-4);
}

.topbar__user-divider {
    height: 1px;
    background: var(--color-ink-200);
    margin: 0;
}

.topbar__user-menu-item {
    display: flex;
    align-items: center;
    gap: var(--space-2);
    width: 100%;
    padding: var(--space-2) var(--space-4);
    border: none;
    background: transparent;
    cursor: pointer;
    font-family: var(--font-sans);
    font-size: 14px;
    color: var(--color-ink-700);
    text-align: left;
}

.topbar__user-menu-item:hover {
    background: var(--color-sand);
}

.topbar__user-menu-item--danger {
    color: var(--color-danger);
}

.topbar__user-menu-item--danger:hover {
    background: var(--color-danger-50);
}
```

### 6.4 Alpine.js

El menú desplegable usa Alpine.js (`x-data`, `x-show`, `@click`). Verificar
que Alpine está disponible en el layout operativo:

```bash
grep -n "alpine\|alpinejs" resources/views/layouts/operativo.blade.php
```

Si no está, añadir en el `<head>` del layout:

```blade
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
```

O si el proyecto ya lo carga via npm:

```bash
grep -n "alpine" package.json vite.config.js
```

Livewire 4 incluye Alpine.js por defecto — verificar que no se carga dos veces.

---

## Paso 7 — Tests

Crear `Modules/Intervencion/tests/Feature/Livewire/NavegacionTest.php`:

```
TF-LW-NAV-01 — Cita con historia_id en agenda renderiza como enlace a intervencion.ciudadano.show
TF-LW-NAV-02 — Cita sin historia_id en agenda renderiza como div no clicable
TF-LW-NAV-03 — La tabla de casos muestra nombre y apellidos del ciudadano, no el ID
TF-LW-NAV-04 — La tabla de casos tiene columna Historia Social con el número de HS
TF-LW-NAV-05 — Clic en fila de casos navega a intervencion.ciudadano.show
TF-LW-NAV-06 — El nombre del ciudadano en búsqueda es enlace si tiene historia_id y nivel 1 o 2
TF-LW-NAV-07 — El nombre del ciudadano en búsqueda no es enlace si es nivel 3 (protegido)
TF-LW-NAV-08 — El nombre del ciudadano en búsqueda no es enlace si no tiene historia_id
TF-LW-NAV-09 — El botón "Nuevo mensaje" abre el modal de redacción
TF-LW-NAV-10 — enviarMensaje() con datos válidos crea un hilo y un mensaje
TF-LW-NAV-11 — enviarMensaje() con destinatarioId vacío no crea el hilo
TF-LW-NAV-12 — El layout operativo muestra el nombre del usuario en el topbar
TF-LW-NAV-13 — El topbar no tiene bloque de usuario en el sidebar inferior
```

Ejecutar al terminar:

```bash
php artisan test --filter=NavegacionTest
php artisan test --filter="AgendaPage|MisCasosPage|BuscarCiudadanoPage|BuzonPage|CiudadanoPage"
php artisan test 2>&1 | tail -5
npm run build 2>&1 | tail -5
```

---

## Lo que NO hay que hacer

- No implementar la pantalla de detalle de mesa ETMF, talleres ni eventos internos.
  Las citas sin `historia_id` quedan como `<div>` no clicable con comentario `// TODO`.
- No implementar el alta de ciudadano. El botón queda `disabled`.
- No modificar el CSS ni los componentes de Filament.
- No cambiar la lógica PHP de los componentes Livewire más allá de lo descrito.
  Esta tarea es de navegación, no de lógica de negocio.
- No mover el menú de usuario en Filament — se deja el comportamiento por defecto.

---

## Checklist de finalización

- [ ] `php artisan test --filter=NavegacionTest` pasa los 13 tests
- [ ] `php artisan test` no introduce fallos nuevos
- [ ] `npm run build` sin errores
- [ ] Las citas con ciudadano en la agenda son enlaces al ciudadano
- [ ] Las citas de evento son divs no clicables con comentario TODO
- [ ] La tabla de casos muestra nombre+apellidos en columna 1
- [ ] La tabla de casos tiene columna "Historia Social" con el número
- [ ] Clic en cualquier punto de la fila de casos navega al ciudadano
- [ ] El nombre del ciudadano en búsqueda es enlace si tiene HS y nivel 1 o 2
- [ ] El botón "Nuevo mensaje" abre el modal de redacción
- [ ] Enviar un mensaje crea el hilo y navega a la pestaña Mensajes
- [ ] El menú de usuario está en el topbar arriba a la derecha
- [ ] El sidebar ya no tiene bloque de usuario abajo a la izquierda
- [ ] El menú desplegable del topbar tiene nombre, rol, CSS y "Cerrar sesión"
- [ ] "Cerrar sesión" cierra la sesión y redirige al login
- [ ] Entrada añadida en `CHANGELOG.md`
