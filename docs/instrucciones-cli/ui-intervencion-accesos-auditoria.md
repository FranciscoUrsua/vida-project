# Instrucciones para Claude CLI — Widget de últimos accesos al expediente
## `docs/instrucciones-cli/ui-intervencion-accesos-auditoria.md`

> Implementa y corrige el widget de "Últimos accesos al expediente" en la
> pantalla de intervención del ciudadano, con visibilidad correcta por rol y UO,
> y resaltado visual de accesos anómalos.
>
> **Referencia de diseño:** `docs/modulo-auditoria.md` sección 5
> **Referencia de permisos:** `docs/principios-vida360.md` secciones 3.5 y 3.6
> **Módulos afectados:** `Modules/Intervencion`, `Modules/Auditoria` (si existe)

---

## Contexto

CLI ha implementado un widget de últimos accesos en la **ficha del ciudadano**
(Filament). Esta tarea hace dos cosas:

1. **Verifica y corrige** ese widget de Filament para que respete las reglas
   de visibilidad por rol y UO definidas en `docs/modulo-auditoria.md`.
2. **Implementa** el equivalente en la **pantalla de intervención** (Livewire),
   que es donde el TSR trabaja habitualmente y donde este widget tiene más
   valor operativo.

Ambos widgets comparten la misma lógica de consulta y visibilidad, pero viven
en contextos distintos. No duplicar código — extraer la lógica compartida a un
servicio o query object reutilizable.

---

## Paso 1 — Revisar el estado actual

```bash
# Localizar el widget implementado en Filament
grep -rn "accesos\|UltimosAccesos\|AuditWidget\|accesos_recientes" \
  app/Filament/ Modules/ --include="*.php" -l

# Ver su implementación
cat [ruta del widget encontrado]

# Verificar que la tabla audits existe
php artisan tinker --no-interaction \
  --execute="echo Schema::hasTable('audits') ? 'OK' : 'NO EXISTE';"

# Estructura de la tabla
php artisan tinker --no-interaction \
  --execute="print_r(Schema::getColumnListing('audits'));"

# Tests actuales de auditoría
php artisan test --filter=Audit 2>&1 | tail -10
```

---

## Paso 2 — Lógica de visibilidad: quién ve qué

Según `docs/modulo-auditoria.md` sección 5, la visibilidad del panel es:

| Quién | Qué ve |
|---|---|
| TSR asignado a este ciudadano | Todos los accesos al expediente |
| Supervisor de la UO del ciudadano | Todos los accesos al expediente |
| Cualquier otro profesional | Solo sus propios accesos |

**La condición "TSR asignado"** se determina así:
```php
// El usuario autenticado es el profesional responsable de la Historia Social
$esAsignado = $historia->profesional_responsable_id === Auth::user()->profesional_id;
```

**La condición "Supervisor de la UO"** se determina así:
```php
// La UO del ciudadano está dentro del subtree de UOs del supervisor
$esSupervidor = Auth::user()->hasRole('supervision')
    && Auth::user()->uoSubtreeIds()->contains($historia->unidad_organizativa_id);
```

**Caso especial — adm_sistema:** ve todos los accesos sin restricción.

Esta lógica debe encapsularse en un método del componente o en un servicio:

```php
// En un QueryObject o Service reutilizable
// Modules/Auditoria/app/Queries/AccesosExpedienteQuery.php

class AccesosExpedienteQuery
{
    public function paraUsuario(User $user, Ciudadano $ciudadano, HistoriaSocial $historia): Builder
    {
        $base = Audit::where('ciudadano_id', $ciudadano->id)
            ->with('user.profesional')
            ->orderBy('created_at', 'desc');

        // adm_sistema ve todo
        if ($user->hasRole('adm_sistema')) {
            return $base;
        }

        // TSR asignado ve todo
        if ($historia->profesional_responsable_id === $user->profesional_id) {
            return $base;
        }

        // Supervisor de la UO ve todo
        if ($user->hasRole('supervision')
            && $user->uoSubtreeIds()->contains($historia->unidad_organizativa_id)) {
            return $base;
        }

        // Cualquier otro: solo sus propios accesos
        return $base->where('user_id', $user->id);
    }
}
```

Si el módulo de Auditoría no tiene este QueryObject, crearlo en la ruta indicada.

---

## Paso 3 — Verificar y corregir el widget de Filament

### 3.1 Comprobar visibilidad actual

Abrir el widget existente en Filament y verificar:

- ¿Filtra por `ciudadano_id`? Debe hacerlo.
- ¿Aplica la lógica de visibilidad por rol del paso 2? Si no, corregir.
- ¿El widget es visible solo para roles con acceso a la ficha del ciudadano
  (`supervision`, `adm_sistema`, `intervencion` con acceso a esa ficha)?
  Si no, corregir.

### 3.2 Condición de visibilidad del widget en Filament

El widget solo debe mostrarse a usuarios con rol `intervencion` que compartan
UO con el ciudadano, supervisores de esa UO, y `adm_sistema`. Añadir en el
widget de Filament:

```php
public static function canView(): bool
{
    $user = Auth::user();
    return $user->hasAnyRole(['adm_sistema', 'supervision', 'intervencion']);
}
```

Y en el método que construye los datos, aplicar `AccesosExpedienteQuery` del
paso 2 para filtrar según el rol.

---

## Paso 4 — Implementar el widget en la pantalla de intervención (Livewire)

### 4.1 Propiedad en CiudadanoPage

Añadir en `CiudadanoPage.php`:

```php
// Propiedad computada — se evalúa una vez y se cachea en la request
public function getAccesosRecientesProperty(): Collection
{
    return app(AccesosExpedienteQuery::class)
        ->paraUsuario(Auth::user(), $this->historia->ciudadano, $this->historia)
        ->limit(5)
        ->get();
}

// ¿Puede el usuario ver todos los accesos o solo los propios?
public function getPuedeVerTodosLosAccesosProperty(): bool
{
    $user = Auth::user();
    return $user->hasRole('adm_sistema')
        || $historia->profesional_responsable_id === $user->profesional_id
        || ($user->hasRole('supervision')
            && $user->uoSubtreeIds()->contains($this->historia->unidad_organizativa_id));
}
```

### 4.2 Vista Blade — sección de últimos accesos

Añadir al final de la columna izquierda de `ciudadano-page.blade.php`,
después de la historia social, antes de cerrar la columna:

```blade
{{-- ── Últimos accesos al expediente ──────────────────────── --}}
<div class="accesos-panel">
    <div class="accesos-panel__header">
        <span class="accesos-panel__titulo">Últimos accesos</span>
        @if($this->puedeVerTodosLosAccesos)
            <a href="#" class="accesos-panel__ver-todo"
               {{-- TODO: modal historial completo --}}>
                Ver todo
            </a>
        @endif
    </div>

    @forelse($this->accesosRecientes as $acceso)
        @php
            $esPropio     = $acceso->user_id === Auth::id();
            $esOtraUo     = ! ($acceso->user?->uoSubtreeIds()?->contains(
                                $historia->unidad_organizativa_id) ?? true);
            $esCambio     = in_array($acceso->accion, ['crear', 'editar', 'eliminar']);
            $esAnomalos   = $esOtraUo && $esCambio;
            $esSospechoso = $esOtraUo && ! $esCambio;
        @endphp

        <div class="acceso-fila
            {{ $esPropio    ? 'acceso-fila--propio'     : '' }}
            {{ $esAnomalos  ? 'acceso-fila--anomalo'    : '' }}
            {{ $esSospechoso ? 'acceso-fila--sospechoso' : '' }}">

            <div class="acceso-fila__quien">
                <span class="acceso-fila__nombre">
                    {{ $acceso->user?->profesional?->nombre_completo ?? $acceso->user?->email ?? '—' }}
                </span>
                @if($esOtraUo)
                    <span class="acceso-fila__badge-uo"
                          title="Profesional de otra UO">
                        Otra UO
                    </span>
                @endif
            </div>

            <div class="acceso-fila__detalle">
                <span class="acceso-fila__accion acceso-fila__accion--{{ $acceso->accion }}">
                    {{ __('auditoria.acciones.' . $acceso->accion) }}
                </span>
                @if($esAnomalos)
                    <span class="acceso-fila__alerta"
                          title="Modificación desde otra UO — revisar">
                        <i data-lucide="alert-triangle" style="width:14px;height:14px;"></i>
                    </span>
                @endif
                <span class="acceso-fila__fecha">
                    {{ $acceso->created_at->diffForHumans() }}
                </span>
            </div>

        </div>
    @empty
        <p class="accesos-panel__vacio">Sin accesos registrados.</p>
    @endforelse
</div>
```

### 4.3 Textos de acción (fichero de traducciones)

Crear o completar `lang/es/auditoria.php`:

```php
return [
    'acciones' => [
        'ver'                 => 'Consultó el expediente',
        'crear'               => 'Registró nueva información',
        'editar'              => 'Modificó información existente',
        'eliminar'            => 'Eliminó un registro',
        'exportar'            => 'Exportó datos',
        'imprimir'            => 'Generó un documento',
        'acceso_restringido'  => 'Accedió a expediente protegido',
    ],
];
```

### 4.4 CSS del widget de accesos

Añadir en `resources/css/app-operativo.css`:

```css
/* Panel de últimos accesos */
.accesos-panel {
    border-top: 1px solid var(--color-ink-200);
    padding-top: var(--space-4);
    margin-top: var(--space-4);
}

.accesos-panel__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: var(--space-3);
}

.accesos-panel__titulo {
    font-size: 11px;
    font-weight: 600;
    color: var(--color-ink-500);
    text-transform: uppercase;
    letter-spacing: 0.06em;
}

.accesos-panel__ver-todo {
    font-size: 12px;
    color: var(--color-primary);
    text-decoration: none;
}

.accesos-panel__ver-todo:hover {
    text-decoration: underline;
}

.acceso-fila {
    padding: var(--space-2) 0;
    border-bottom: 1px solid var(--color-ink-100);
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.acceso-fila:last-child {
    border-bottom: none;
}

/* Acceso propio: tratamiento neutro, ligeramente atenuado */
.acceso-fila--propio {
    opacity: 0.65;
}

/* Acceso de otra UO solo lectura: fondo ámbar suave */
.acceso-fila--sospechoso {
    background: var(--color-warning-50);
    border-radius: var(--radius-sm);
    padding: var(--space-2) var(--space-2);
    margin: 0 calc(-1 * var(--space-2));
}

/* Acceso de otra UO con cambios: fondo coral — anomalía grave */
.acceso-fila--anomalo {
    background: var(--color-danger-50);
    border-radius: var(--radius-sm);
    padding: var(--space-2) var(--space-2);
    margin: 0 calc(-1 * var(--space-2));
    border-left: 3px solid var(--color-danger);
}

.acceso-fila__quien {
    display: flex;
    align-items: center;
    gap: var(--space-2);
}

.acceso-fila__nombre {
    font-size: 12px;
    font-weight: 500;
    color: var(--color-ink-800);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.acceso-fila__badge-uo {
    font-size: 10px;
    font-weight: 600;
    background: var(--color-warning-50);
    color: var(--color-warning);
    border-radius: var(--radius-pill);
    padding: 1px 6px;
    white-space: nowrap;
    flex-shrink: 0;
}

/* En filas anómalas, el badge de UO usa color danger */
.acceso-fila--anomalo .acceso-fila__badge-uo {
    background: var(--color-danger-50);
    color: var(--color-danger);
}

.acceso-fila__detalle {
    display: flex;
    align-items: center;
    gap: var(--space-2);
}

.acceso-fila__accion {
    font-size: 11px;
    color: var(--color-ink-500);
}

/* Badge de acción coloreado por tipo */
.acceso-fila__accion--editar,
.acceso-fila__accion--crear,
.acceso-fila__accion--eliminar {
    font-weight: 600;
    color: var(--color-ink-700);
}

.acceso-fila__alerta {
    color: var(--color-danger);
    flex-shrink: 0;
    display: flex;
    align-items: center;
}

.acceso-fila__fecha {
    font-size: 11px;
    color: var(--color-ink-400);
    margin-left: auto;
    white-space: nowrap;
}

.accesos-panel__vacio {
    font-size: 12px;
    color: var(--color-ink-400);
    padding: var(--space-2) 0;
}
```

---

## Paso 5 — Lógica de resaltado: tres niveles

El widget distingue visualmente tres tipos de acceso, de menos a más grave:

| Tipo | Condición | Tratamiento visual |
|---|---|---|
| **Propio** | `user_id = Auth::id()` | Opacidad reducida — es el ruido de fondo normal |
| **Otra UO, solo lectura** | UO diferente + acción `ver`, `exportar`, `imprimir` | Fondo ámbar (`--color-warning-50`) + badge "Otra UO" |
| **Otra UO con cambios** | UO diferente + acción `crear`, `editar`, `eliminar` | Fondo coral (`--color-danger-50`) + borde izquierdo rojo + icono `alert-triangle` |

**El tercer caso es una anomalía técnica grave.** Según el modelo de permisos,
un profesional de otra UO no debería poder crear, editar ni eliminar registros
en un expediente que no es de su UO. Si aparece en el log, significa que:
- Hubo un error de autorización en algún punto del sistema, o
- El profesional tenía temporalmente asignación en esa UO (y ya no la tiene).

El widget no bloquea ni alerta activamente — simplemente lo hace visible.
La decisión de qué hacer con esa información es del TSR y del supervisor.

**Determinar si el acceso es de "otra UO":**
```php
// La UO del acceso es la que tenía el usuario en el momento del acceso.
// Consultar docs/modulo-auditoria.md — campo contexto puede tener
// unidad_organizativa_id si se registró. Si no, comparar la UO actual
// del usuario que accedió con la UO de la historia.
// Ver nota en BACKLOG sobre "Semántica temporal en el log de accesos".

$uoAcceso = $acceso->contexto['unidad_organizativa_id']
    ?? $acceso->user?->profesional?->unidad_organizativa_id;

$esOtraUo = $uoAcceso !== null
    && $uoAcceso !== $historia->unidad_organizativa_id;
```

Si el campo no está disponible en el contexto, usar la UO actual del usuario
como aproximación, con un comentario indicando la limitación.

---

## Paso 6 — Tests

Crear `Modules/Intervencion/tests/Feature/Livewire/AccesosExpedienteTest.php`:

```
TF-AUD-INT-01 — El TSR asignado ve todos los accesos al expediente (propios y ajenos)
TF-AUD-INT-02 — El supervisor de la UO ve todos los accesos al expediente
TF-AUD-INT-03 — Un profesional de otra UO solo ve sus propios accesos
TF-AUD-INT-04 — El widget no es visible para usuarios sin rol intervencion ni supervision
TF-AUD-INT-05 — Accesos de otra UO con acción 'ver' tienen clase CSS 'acceso-fila--sospechoso'
TF-AUD-INT-06 — Accesos de otra UO con acción 'editar' tienen clase CSS 'acceso-fila--anomalo'
TF-AUD-INT-07 — Accesos propios tienen clase CSS 'acceso-fila--propio'
TF-AUD-INT-08 — El widget muestra como máximo 5 accesos en la vista de intervención
TF-AUD-INT-09 — El widget no expone IP ni user_agent en el HTML renderizado
TF-AUD-INT-10 — El widget de Filament aplica la misma lógica de visibilidad por rol
TF-AUD-INT-11 — El widget de Filament solo es visible para roles intervencion, supervision y adm_sistema
```

Ejecutar:
```bash
php artisan test --filter=AccesosExpediente
php artisan test --filter=Audit
php artisan test --filter=CiudadanoPage
php artisan test 2>&1 | tail -5
```

---

## Lo que NO hay que hacer

- No implementar alertas automáticas al supervisor cuando se detecta una
  anomalía — eso está diferido en `docs/modulo-auditoria.md` sección 9.
- No implementar el modal "Ver historial completo" — dejar el enlace con
  comentario `// TODO`.
- No modificar la tabla `audits` ni el `AuditService` — son de solo lectura
  para esta tarea.
- No mostrar IP ni user agent en ninguna vista del widget — esos campos
  son exclusivos del visor de supervisión en Filament.
- No bloquear ni tomar acción automática ante accesos anómalos — el widget
  informa, no actúa.

---

## Checklist de finalización

- [ ] `php artisan test --filter=AccesosExpediente` pasa los 11 tests
- [ ] `php artisan test` no introduce fallos nuevos
- [ ] El TSR asignado ve todos los accesos en la pantalla de intervención
- [ ] Un profesional de otra UO que abre el expediente solo ve sus propios accesos
- [ ] Los accesos de otra UO con `ver` tienen fondo ámbar
- [ ] Los accesos de otra UO con `editar`/`crear`/`eliminar` tienen fondo coral y borde rojo
- [ ] Los accesos propios tienen opacidad reducida
- [ ] El widget muestra máximo 5 accesos en la vista de intervención
- [ ] No hay IP ni user_agent en el HTML de ninguno de los dos widgets
- [ ] El widget de Filament aplica `AccesosExpedienteQuery` (sin duplicar lógica)
- [ ] El widget de Filament es invisible para roles sin `intervencion`, `supervision` ni `adm_sistema`
- [ ] `npm run build` sin errores
- [ ] Entrada añadida en `CHANGELOG.md`
