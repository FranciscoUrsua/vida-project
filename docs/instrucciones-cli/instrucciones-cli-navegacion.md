# Instrucciones CLI — Navegación: enlaces y widgets ficha ciudadano

**Sesión:** 2026-06-09  
**Módulos:** `Modules/Intervencion`, `Modules/Ciudadania`  
**Referencia funcional:** `docs/front/ui-intervencion.md` §8 (mapa de navegación)  
**Dependencias:** `FichaCiudadanoPage` (implementado en sesión anterior)

---

## Contexto

Esta sesión completa el mapa de navegación entre pantallas definido en `docs/front/ui-intervencion.md` §8. Hay tres grupos de cambios:

1. **Agenda** (`AgendaPage`): el nombre del ciudadano debe bifurcar por rol —`intervencion` va a `intervencion.ciudadano.show`, el resto a `ciudadania.ciudadano.ficha`.
2. **Mis casos** (`MisCasosPage`): el nombre del ciudadano debe ir a `ciudadania.ciudadano.ficha` en lugar del comportamiento actual (toda la fila va a intervención).
3. **Ficha del ciudadano** (`FichaCiudadanoPage`): ajustes de widgets condicionales, eliminar widget de permisos, y asegurar que el botón "Ver ficha" en miembros de UC y el enlace "Ir a HS" en el banner funcionan correctamente.

---

## Tarea 1 — `AgendaPage`: bifurcación del enlace por rol

**Archivo:** `Modules/Intervencion/app/Http/Livewire/AgendaPage.php`  
**Vista:** `Modules/Intervencion/resources/views/livewire/agenda-page.blade.php`

En la vista, el nombre del ciudadano dentro de cada cita actualmente renderiza como `<a wire:navigate>` solo si hay `historia_id`. Hay que añadir la bifurcación por rol:

```blade
@if($cita['historia_id'] && auth()->user()->hasRole('intervencion'))
    {{-- TSR con historia: va a pantalla de intervención --}}
    <a wire:navigate href="{{ route('intervencion.ciudadano.show', $cita['historia_id']) }}">
        {{ $cita['ciudadano_nombre'] }}
    </a>

@elseif(isset($cita['ciudadano_id']))
    {{-- Cualquier rol sin historia, o roles no-intervencion con historia: va a ficha --}}
    <a wire:navigate href="{{ route('ciudadania.ciudadano.ficha', $cita['ciudadano_id']) }}">
        {{ $cita['ciudadano_nombre'] }}
    </a>

@else
    {{-- Sin ciudadano_id: no clicable --}}
    <span>{{ $cita['ciudadano_nombre'] }}</span>
@endif
```

Para que esto funcione, la fixture `citasFixture()` en `AgendaPage.php` debe incluir el campo `ciudadano_id` (int|null) además del ya existente `historia_id`. Añadirlo a la fixture con el mismo patrón determinista existente. Cuando el módulo Agenda real esté implementado, este campo vendrá de la relación `Cita::ciudadano_id`.

---

## Tarea 2 — `MisCasosPage`: separar enlace nombre de enlace HS

**Archivo:** `Modules/Intervencion/app/Http/Livewire/MisCasosPage.php`  
**Vista:** `Modules/Intervencion/resources/views/livewire/mis-casos-page.blade.php`

Actualmente toda la fila es clicable y lleva a `intervencion.ciudadano.show`. Hay que separar los dos elementos:

- **Nombre del ciudadano** (columna "Ciudadano/a"): enlace independiente a `ciudadania.ciudadano.ficha`. Usar `wire:navigate` y `@click.stop` para que el clic en el nombre no propague al clic de fila.
- **Identificador HS** (columna "Historia Social", formato `HS-XXXXXX`): ya enlaza a `intervencion.ciudadano.show`. Mantener.
- **Clic en el resto de la fila**: mantiene el comportamiento actual → `intervencion.ciudadano.show`.

```blade
{{-- Columna ciudadano --}}
<td>
    <a wire:navigate href="{{ route('ciudadania.ciudadano.ficha', $plan->historia->ciudadano_id) }}"
       @click.stop>
        {{ $ciudadanos[$plan->historia->ciudadano_id]->nombre_completo ?? '—' }}
    </a>
</td>

{{-- Columna historia social --}}
<td>
    <a wire:navigate href="{{ route('intervencion.ciudadano.show', $plan->historia_id) }}"
       @click.stop>
        HS-{{ str_pad($plan->historia_id, 6, '0', STR_PAD_LEFT) }}
    </a>
</td>
```

La propiedad computada `ciudadanosDelPage()` ya existe y carga los ciudadanos de la página en una query. Verificar que incluye `ciudadano_id` como clave del array. Si usa `historia_id` como clave, ajustar para que use `ciudadano_id`.

---

## Tarea 3 — `FichaCiudadanoPage`: ajustes de widgets y enlaces

### 3.1 Widgets condicionales

Verificar que en la vista los widgets de la columna lateral están envueltos en las condiciones correctas:

```blade
{{-- Banner historia social: solo si existe --}}
@if($historiaSocial)
    {{-- banner --}}
@endif

{{-- Otras prestaciones: solo si hay registros --}}
@if($prestaciones->isNotEmpty())
    {{-- widget --}}
@endif

{{-- Centro de referencia y TSR: solo si existe historia social --}}
@if($historiaSocial)
    {{-- widget --}}
@endif

{{-- Actividad reciente: siempre (siempre hay al menos el alta) --}}
{{-- widget --}}

{{-- Widget de permisos: ELIMINAR COMPLETAMENTE --}}
```

### 3.2 Enlace "Ir a HS" en el banner

El enlace debe bifurcar por rol. El banner es visible para todos, pero solo es navegable para `intervencion`:

```blade
@if($puedeVerHistoria)
    <a wire:navigate
       href="{{ route('intervencion.ciudadano.show', $historiaSocial) }}"
       class="...">
        Ir a HS →
    </a>
@else
    <span class="..." title="Requiere rol de intervención" style="opacity:.4;cursor:default">
        Ir a HS →
    </span>
@endif
```

### 3.3 Botón "Ver ficha" en miembros de UC

En el bloque de unidad de convivencia, el botón "Ver ficha" junto a cada miembro debe apuntar a `ciudadania.ciudadano.ficha` del miembro:

```blade
@if($miembro->ciudadano_id)
    <a wire:navigate
       href="{{ route('ciudadania.ciudadano.ficha', $miembro->ciudadano_id) }}"
       class="btn btn-sm">
        Ver ficha
    </a>
@endif
```

Si la tabla `unidades_convivencia` aún no existe (stub), este bloque puede quedar como TODO con comentario explícito. No crear la tabla en esta sesión.

### 3.4 Eliminar widget de permisos

Localizar el bloque de la vista correspondiente al widget "Permisos del rol activo" y eliminarlo completamente, incluyendo su contenedor y cualquier lógica PHP en el componente que solo sirva a ese widget.

---

## Tarea 4 — Tests

Añadir en `Modules/Intervencion/tests/Feature/Livewire/NavegacionTest.php` (continuando la numeración existente TF-LW-NAV-01..14, ya en verde tras sesión anterior):

| ID | Descripción |
|---|---|
| TF-LW-NAV-15 | Agenda con rol `tramitacion`: nombre del ciudadano enlaza a `ciudadania.ciudadano.ficha` |
| TF-LW-NAV-16 | Agenda con rol `intervencion`: nombre con `historia_id` enlaza a `intervencion.ciudadano.show` |
| TF-LW-NAV-17 | `MisCasosPage`: columna nombre enlaza a `ciudadania.ciudadano.ficha` con `@click.stop` |
| TF-LW-NAV-18 | `MisCasosPage`: columna HS enlaza a `intervencion.ciudadano.show` |
| TF-LW-NAV-19 | `FichaCiudadanoPage`: banner HS no renderiza si no existe historia social |
| TF-LW-NAV-20 | `FichaCiudadanoPage`: banner HS renderiza enlace clicable para `intervencion` |
| TF-LW-NAV-21 | `FichaCiudadanoPage`: banner HS renderiza enlace no clicable para `tramitacion` |
| TF-LW-NAV-22 | `FichaCiudadanoPage`: widget prestaciones no renderiza si no hay registros |
| TF-LW-NAV-23 | `FichaCiudadanoPage`: widget permisos no existe en la vista |

---

## Tarea 5 — CHANGELOG y SESSION

Añadir entrada en `CHANGELOG.md`:
- `AgendaPage`: bifurcación de enlace ciudadano por rol (intervencion → intervención, resto → ficha). Campo `ciudadano_id` añadido a fixture.
- `MisCasosPage`: columna nombre separada de clic de fila con `@click.stop`, enlaza a `ciudadania.ciudadano.ficha`.
- `FichaCiudadanoPage`: widgets condicionales verificados, widget permisos eliminado, enlace HS bifurcado por rol, botón "Ver ficha" en UC conectado.
- Tests TF-LW-NAV-15 a TF-LW-NAV-23.
- Referencia funcional: `docs/front/ui-intervencion.md` §8.

Actualizar `SESSION.md`:
- Tarea completada: mapa de navegación completo según `docs/front/ui-intervencion.md` §8.
- Actualizar contador NavegacionTest: 23 tests (TF-LW-NAV-01..23), 1 incomplete (TF-LW-NAV-03).
- Eliminar el documento temporal `docs/ui-intervencion-navegacion.md` si existe — su contenido está consolidado en `docs/front/ui-intervencion.md`.
- Mantener como pendiente: citas sin `ciudadano_id` en agenda (depende del módulo Agenda real).
- Desbloquear TF-LW-NAV-03 si ahora hay datos de plan activo disponibles en la fixture; si no, mantener `markTestIncomplete`.
