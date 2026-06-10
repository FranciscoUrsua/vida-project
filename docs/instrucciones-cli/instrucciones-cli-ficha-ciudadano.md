# Instrucciones CLI — Ficha del ciudadano

**Sesión:** 2026-06-09  
**Módulo:** `Modules/Ciudadania`  
**Referencia funcional:** `docs/ui-ficha-ciudadano.md`  
**Dependencias:** `AltaCiudadano` (implementado en sesión anterior)

---

## Contexto

Implementar el componente Livewire `FichaCiudadanoPage` accesible en `/ciudadania/ciudadano/{ciudadano}`. Esta pantalla pivota sobre `Ciudadano` (Capa 1) y es distinta de `intervencion/ciudadano/{historia}`, que pivota sobre `HistoriaSocial`.

La pantalla es accesible para roles `intervencion`, `tramitacion`, `consulta_basica` y `supervision`. Los tres primeros pueden editar Capa 1 y UC; `supervision` solo lectura.

---

## Tarea 1 — Migración: tabla `ciudadano_prestaciones_resumen`

Crear la migración en `database/migrations/`:

```php
Schema::create('ciudadano_prestaciones_resumen', function (Blueprint $table) {
    $table->id();
    $table->foreignId('ciudadano_id')->constrained('ciudadanos')->cascadeOnDelete();
    $table->string('modulo_origen');        // 'centros', 'teleasistencia', 'prestaciones'...
    $table->unsignedBigInteger('origen_id');// id en la tabla origen
    $table->string('tipo');                 // clave de catálogo
    $table->string('descripcion');          // nombre legible, desnormalizado
    $table->string('estado');               // activo | en_tramite | finalizado | denegado | baja
    $table->date('fecha_inicio');
    $table->date('fecha_fin')->nullable();
    $table->timestamps();

    $table->index(['ciudadano_id', 'estado']);
    $table->index(['modulo_origen', 'origen_id']);
});
```

Crear modelo `Modules/Ciudadania/app/Models/CiudadanoPrestacionResumen.php`:

- `$fillable`: todos los campos excepto `id` y timestamps.
- Cast `fecha_inicio` y `fecha_fin` a `date`.
- Scope `activas()`: `whereIn('estado', ['activo', 'en_tramite'])`.
- Scope `recientes(int $limit = 4)`: `orderByRaw("CASE estado WHEN 'activo' THEN 0 WHEN 'en_tramite' THEN 1 WHEN 'finalizado' THEN 2 ELSE 3 END")->orderByDesc('fecha_inicio')->limit($limit)`.

Añadir relación en `Ciudadano`:
```php
public function prestacionesResumen(): HasMany
{
    return $this->hasMany(CiudadanoPrestacionResumen::class);
}
```

---

## Tarea 2 — Componente Livewire `FichaCiudadanoPage`

Crear:
- `Modules/Ciudadania/app/Http/Livewire/FichaCiudadanoPage.php`
- `Modules/Ciudadania/resources/views/livewire/ficha-ciudadano-page.blade.php`

### Propiedades

```php
public Ciudadano $ciudadano;        // model binding por ruta
public bool $modoEdicion = false;   // activa edición de todos los campos a la vez

// Campos editables de Capa 1 (se cargan en mount)
public string $nombre = '';
public string $apellido1 = '';
public string $apellido2 = '';
public string $fechaNacimiento = '';
public string $sexo = '';
public string $alias = '';
public string $direccionTexto = '';
public string $telefono = '';
public string $email = '';

// Modal de nuevo documento
public bool   $modalDocumento = false;
public string $nuevoTipoDocumento = 'nif';
public string $nuevoValorDocumento = '';
```

### `mount(Ciudadano $ciudadano)`

Verificar que el usuario autenticado tiene uno de los roles `intervencion|tramitacion|consulta_basica|supervision`. Si no, `abort(403)`.

Cargar campos editables desde el ciudadano descifrado:
```php
$this->nombre          = $ciudadano->nombre ?? '';
$this->apellido1       = $ciudadano->apellido1 ?? '';
// ...etc
$this->direccionTexto  = $ciudadano->direccion_texto ?? '';
```

### Propiedades computadas

**`puedeEditar(): bool`**  
`auth()->user()->hasRole(['intervencion', 'tramitacion', 'consulta_basica'])` — falso para `supervision`.

**`historiaSocial(): ?HistoriaSocial`**  
`HistoriaSocial::where('ciudadano_id', $this->ciudadano->id)->first()` con `withoutGlobalScopes()` para no interferir con `AmbitoUoScope`. Si no existe, devuelve null.

**`puedeVerHistoria(): bool`**  
`auth()->user()->hasRole(['intervencion'])`.

**`documentos(): Collection`**  
`CiudadanoIdentificador::where('ciudadano_id', $this->ciudadano->id)->orderByDesc('fecha_inicio')->get()`.

**`ucVigente(): ?UnidadConvivencia`**  
`UnidadConvivencia::where('ciudadano_id', $this->ciudadano->id)->whereNull('fecha_fin')->first()`. Devuelve null si no existe tabla `unidades_convivencia` (stub con TODO hasta que el modelo esté implementado).

**`prestaciones(): Collection`**  
`CiudadanoPrestacionResumen::where('ciudadano_id', $this->ciudadano->id)->recientes(4)->get()`.

**`actividadReciente(): Collection`**  
Query a `ciudadanos_auditoria` donde `ciudadano_id = $this->ciudadano->id`, ordenada desc, límite 5. Si la tabla no existe, devolver colección vacía con TODO.

### Métodos

**`activarEdicion()`**  
Solo si `puedeEditar()`. Asigna `$modoEdicion = true`.

**`cancelarEdicion()`**  
Recarga los campos desde el ciudadano. `$modoEdicion = false`.

**`guardar()`**  
Solo si `puedeEditar()`. Validar:
```php
[
    'nombre'          => 'required|string|max:100',
    'apellido1'       => 'required|string|max:100',
    'apellido2'       => 'nullable|string|max:100',
    'fechaNacimiento' => 'nullable|date|before:today',
    'sexo'            => 'required|string',
    'alias'           => 'nullable|string|max:200',
    'direccionTexto'  => 'nullable|string|max:500',
    'telefono'        => 'nullable|string|max:20',
    'email'           => 'nullable|email|max:255',
]
```
Normalizar con `NormalizadorCiudadano::normalizar([...])`. Actualizar el ciudadano. `DireccionObserver` lanzará la geocodificación automáticamente si `direccionTexto` ha cambiado. `$modoEdicion = false`. Dispatch `$this->dispatch('ciudadano-actualizado')`.

**`abrirModalDocumento()`**  
Solo si `puedeEditar()`. `$modalDocumento = true`.

**`cerrarModalDocumento()`**  
Limpiar campos del modal. `$modalDocumento = false`.

**`guardarDocumento()`**  
Solo si `puedeEditar()`. Validar `nuevoTipoDocumento` en `['nif', 'nie', 'pasaporte']` y `nuevoValorDocumento` no vacío y max 20. `DB::transaction()`:
1. `CiudadanoIdentificador::where('ciudadano_id', ...)->whereNull('fecha_fin')->update(['fecha_fin' => today()])` — cierra el activo anterior.
2. `CiudadanoIdentificador::create([...nuevo documento, 'fecha_inicio' => today(), 'verificado' => false, 'fuente' => 'manual'])`.
Cerrar modal.

### Ruta

Añadir en `Modules/Ciudadania/routes/web.php`:

```php
Route::middleware(['auth', 'role_or_permission:intervencion|tramitacion|consulta_basica|supervision'])
    ->group(function () {
        // rutas existentes...
        Route::get('/ciudadania/ciudadano/{ciudadano}', FichaCiudadanoPage::class)
            ->name('ciudadania.ciudadano.ficha');
    });
```

### Vista Blade

La vista implementa el layout de dos columnas descrito en `docs/ui-ficha-ciudadano.md §4`.

Puntos clave de la vista:

**Lápiz de edición inline:** se renderiza solo si `$puedeEditar` y `!$modoEdicion`. En modo edición, los campos se convierten en inputs con `wire:model`.

**Banner de historia social:** se renderiza solo si `$historiaSocial !== null`. El enlace `wire:navigate` a `intervencion.ciudadano.show` solo es navegable si `$puedeVerHistoria`. Para otros roles, renderizar el mismo elemento visual pero como `<span>` no clicable:

```blade
@if($puedeVerHistoria)
    <a wire:navigate href="{{ route('intervencion.ciudadano.show', $historiaSocial) }}">
        Ir a HS →
    </a>
@else
    <span style="opacity:.4" title="Requiere rol de intervención">Ir a HS →</span>
@endif
```

**Widget de prestaciones:** se renderiza solo si `$prestaciones->isNotEmpty()`. Los badges de estado usan las clases CSS del design system según el valor del campo `estado`.

**Widget de permisos:** tabla estática generada a partir de `$puedeEditar` y `$puedeVerHistoria`. No requiere lógica adicional.

**Modo edición:** cuando `$modoEdicion === true`, la cabecera muestra los botones "Guardar cambios" y "Cancelar" en lugar del botón "Editar datos". Los campos del bloque de identificación y contacto se convierten en inputs.

---

## Tarea 3 — Actualizar `confirmarAlta()` en `AltaCiudadano`

El método `confirmarAlta()` con acción `'ficha'` actualmente redirige a `ciudadania.ciudadano.ficha`. Verificar que la ruta existe tras crear la tarea 2 y que el redirect funciona correctamente. Si la ruta ya estaba como TODO o placeholder, actualizar.

---

## Tarea 4 — Añadir ítem en sidebar

Siguiendo el mismo patrón que la sesión anterior para "Alta de ciudadano", no es necesario añadir un ítem de sidebar para la ficha: la navegación llega siempre desde búsqueda, desde el alta o desde enlaces internos (UC, agenda). No es un punto de entrada directo desde el sidebar.

---

## Tarea 5 — Tests

Crear `Modules/Ciudadania/tests/Feature/Livewire/FichaCiudadanoPageTest.php`.

| ID | Descripción |
|---|---|
| TF-LW-FIC-01 | Componente no accesible sin autenticación → 302 |
| TF-LW-FIC-02 | Roles `intervencion`, `tramitacion`, `consulta_basica`, `supervision` pueden montar el componente |
| TF-LW-FIC-03 | Rol sin ninguno de los cuatro roles → 403 |
| TF-LW-FIC-04 | `puedeEditar()` devuelve true para `tramitacion`, false para `supervision` |
| TF-LW-FIC-05 | `activarEdicion()` con rol `supervision` no cambia `$modoEdicion` |
| TF-LW-FIC-06 | `guardar()` actualiza los campos del ciudadano y `$modoEdicion` vuelve a false |
| TF-LW-FIC-07 | `guardar()` con rol `supervision` no actualiza el ciudadano |
| TF-LW-FIC-08 | `guardar()` sin nombre falla validación |
| TF-LW-FIC-09 | `guardarDocumento()` crea nuevo `CiudadanoIdentificador` y cierra el anterior |
| TF-LW-FIC-10 | `guardarDocumento()` con rol `supervision` no crea documento |
| TF-LW-FIC-11 | Vista renderiza banner de historia social si existe `HistoriaSocial` para el ciudadano |
| TF-LW-FIC-12 | Vista no renderiza banner si no existe `HistoriaSocial` |
| TF-LW-FIC-13 | Enlace "Ir a HS" es navegable para `intervencion`, no navegable para `tramitacion` |
| TF-LW-FIC-14 | Widget de prestaciones no aparece si `ciudadano_prestaciones_resumen` está vacío |
| TF-LW-FIC-15 | Widget de prestaciones muestra máximo 4 registros |
| TF-LW-FIC-16 | `confirmarAlta()` con acción `ficha` en `AltaCiudadano` redirige a `ciudadania.ciudadano.ficha` |

---

## Tarea 6 — CHANGELOG y SESSION

Añadir entrada en `CHANGELOG.md`:
- Migración `ciudadano_prestaciones_resumen` con modelo y scopes.
- Componente Livewire `FichaCiudadanoPage`: Capa 1 editable con permisos por rol, documentos de identidad con historial, unidad de convivencia, banner de historia social, widget de prestaciones.
- Tests TF-LW-FIC-01 a TF-LW-FIC-16.
- Decisiones: tabla de agregación `ciudadano_prestaciones_resumen` como capa de desacoplamiento entre módulos; historia social única e inmutable por ciudadano; `supervision` ve todo sin editar.

Actualizar `SESSION.md`:
- Tarea completada: `FichaCiudadanoPage` con 16 tests en verde.
- Añadir a pendientes: índice único `UNIQUE(ciudadano_id)` en `historias_sociales` para garantizar en BD la unicidad de historia por ciudadano (ver `docs/ui-ficha-ciudadano.md §10`).
- Añadir a pendientes: vista expandida de prestaciones (pantalla completa desde "Ver todo").
- Añadir a pendientes: vista de historial de versiones de UC.
