# Instrucciones CLI — Mejoras pantalla de intervención (`CiudadanoPage`)

> Antes de empezar: `git pull origin master`, leer `SESSION.md`, `CLAUDE.md`, `docs/design-system/SKILL.md`
> y `docs/modulo-intervencion.md`. Esta tarea toca Filament (backoffice) y Livewire (operativo).

---

## Resumen de cambios

Esta sesión implementa 7 mejoras en la pantalla de intervención (`CiudadanoPage`) y sus
dependencias de configuración en Filament. Se añaden dos nuevas capacidades de configuración
por organización/UO y se refactoriza el layout y los datos del ciudadano mostrados en pantalla.

No se crean tests nuevos en esta sesión (los cambios son de UI pura y configuración de modelos
ya testeados). Actualizar `SESSION.md` al terminar.

---

## Cambio 1 — Logotipo configurable en el sidebar

### Objetivo
El sidebar muestra actualmente el texto "VIDA360" como identificador de la aplicación.
Se necesita un sistema configurable (como el de WordPress) con tres niveles de fallback:
1. Si `ConfiguracionOrganizacion` tiene un logotipo subido → mostrar el `<img>`.
2. Si tiene nombre de aplicación configurado pero no logotipo → mostrar el nombre.
3. Si no hay nada → mostrar "VIDA360".

### Backend — `ConfiguracionOrganizacion`

Verificar si el modelo `ConfiguracionOrganizacion` ya existe (es probable que sí, aparece
en el grupo «Sistema» de Filament). Si existe:

1. Añadir campo `logo_path` (string, nullable) y `nombre_aplicacion` (string, nullable)
   a la tabla mediante una migración nueva: `add_branding_to_configuracion_organizacion`.
2. El campo `logo_path` almacena la ruta relativa en el disco de Storage de Laravel.
3. Añadir al modelo el accessor `logoUrl(): ?string` que devuelve `Storage::url($this->logo_path)`
   si `logo_path` no es null, o `null` si lo es.

### Filament — `ConfiguracionOrganizacionResource`

En el formulario de edición, añadir una sección «Identidad visual» con:
- Campo `FileUpload` para `logo_path`. Disco: `public`. Directorio: `branding`.
  Tipos aceptados: `jpg`, `jpeg`, `png`, `svg`, `webp`. Dimensiones máximas recomendadas: 200×60 px.
  Label: «Logotipo de la aplicación». Helper text: «Se muestra en la barra lateral. Si no se sube
  ninguno, se muestra el nombre de la aplicación o "VIDA360".»
- Campo `TextInput` para `nombre_aplicacion`. Label: «Nombre de la aplicación».
  Helper text: «Se muestra si no hay logotipo. Si se deja vacío, se muestra "VIDA360".»
  Máximo 60 caracteres.

### Livewire — `sidebar.blade.php`

Reemplazar el bloque actual que muestra "VIDA360" con la lógica de los tres niveles de fallback.
Obtener la configuración mediante `ConfiguracionOrganizacion::first()` en el componente Sidebar
(o pasarla como variable desde el layout si ya se carga allí).

```blade
{{-- Logotipo configurable --}}
@if($config?->logoUrl())
    <img src="{{ $config->logoUrl() }}" alt="{{ $config->nombre_aplicacion ?? 'VIDA360' }}"
         class="op-sidebar-logo-img">
@elseif($config?->nombre_aplicacion)
    <span class="op-sidebar-logo-text">{{ $config->nombre_aplicacion }}</span>
@else
    <span class="op-sidebar-logo-text">VIDA360</span>
@endif
```

Añadir en `app-operativo.css` las clases `.op-sidebar-logo-img` (max-height: 36px; width: auto)
y `.op-sidebar-logo-text` (los estilos que tenga actualmente el texto "VIDA360").

---

## Cambio 2 — Nombre del Plan de Intervención configurable por UO

### Contexto
La banda superior de `CiudadanoPage` muestra "PISO activo / Sin PISO / PISO cerrado" y el
botón "Ver PISO". "PISO" es el acrónimo del Plan de Intervención de Atención Social Primaria,
pero otras UO pueden usar nombres distintos (p. ej. "PIS", "PIA", "Plan de intervención").
El nombre debe ser configurable por UO.

### Backend — `UnidadOrganizativa`

Añadir dos campos nuevos al modelo y su tabla mediante migración
`add_plan_intervencion_nombre_to_unidades_organizativas`:

| Campo | Tipo | Descripción |
|---|---|---|
| `plan_nombre_completo` | string, nullable | Ej.: «Plan de Intervención Social» |
| `plan_nombre_corto` | string, nullable | Ej.: «PISO» |

Valores por defecto cuando son null: nombre completo → «Plan de intervención»,
nombre corto → «Plan».

Añadir al modelo dos accessors:

```php
/** @return string Nombre completo del plan, con fallback. */
public function getPlanNombreCompletoAttribute(): string
{
    return $this->attributes['plan_nombre_completo'] ?? 'Plan de intervención';
}

/** @return string Nombre corto del plan, con fallback. */
public function getPlanNombreCortoAttribute(): string
{
    return $this->attributes['plan_nombre_corto'] ?? 'Plan';
}
```

### Filament — `UnidadOrganizativaResource`

En el formulario de edición, añadir una sección «Plan de intervención» con:
- `TextInput` para `plan_nombre_completo`. Label: «Nombre completo del plan».
  Placeholder: «Plan de intervención». Máximo 80 caracteres.
- `TextInput` para `plan_nombre_corto`. Label: «Nombre abreviado (acrónimo)».
  Placeholder: «Plan». Máximo 10 caracteres.
- Helper text del grupo: «Define cómo se llama el plan de intervención en esta unidad
  organizativa. Si se deja en blanco, se usará "Plan de intervención" / "Plan".»

### Livewire — `CiudadanoPage.php`

En el método `mount()` (o donde se cargue `$pisoActivo`), resolver también el nombre del plan:

```php
// Obtener la UO del profesional autenticado para leer el nombre del plan
$uo = auth()->user()->unidadOrganizativa;
$this->planNombreCorto  = $uo?->plan_nombre_corto  ?? 'Plan';
$this->planNombreCompleto = $uo?->plan_nombre_completo ?? 'Plan de intervención';
```

Exponer `$planNombreCorto` y `$planNombreCompleto` como propiedades públicas de Livewire.

### Vista — `ciudadano-page.blade.php`

En la banda superior, reemplazar todas las ocurrencias literales de "PISO" por
`{{ $planNombreCorto }}`. Ejemplos:

```blade
{{-- Badge de estado --}}
@if($pisoActivo)
    <span class="hs-piso-badge hs-piso-badge--activo">
        {{ $planNombreCorto }} activo
    </span>
@elseif(...)
    <span class="hs-piso-badge hs-piso-badge--sin">
        Sin {{ $planNombreCorto }}
    </span>
@endif

{{-- Botones --}}
<button ...>Ver {{ $planNombreCorto }}</button>
```

El filtro de episodios que actualmente dice "PISO" en los tabs también debe usar
`{{ $planNombreCorto }}`.

---

## Cambio 3 — Sin avatar de iniciales

No añadir avatar de iniciales en la cabecera del ciudadano. El nombre del ciudadano
es el elemento de mayor peso visual; no necesita soporte gráfico adicional.
Si ya existe algún avatar de iniciales en `ciudadano-page.blade.php`, eliminarlo.

---

## Cambio 4 — Mostrar nombre de la UO en lugar del ID

### Contexto
Actualmente se muestra `UO #383`. Debe mostrarse el nombre completo o corto de la UO.

### Implementación

En `CiudadanoPage.php`, al cargar los datos de la Historia Social, resolver también la UO:

```php
// La UO se obtiene desde la Historia Social → profesional asignado, o desde auth()
$this->uoNombre = $historia->unidadOrganizativa?->nombre_corto
    ?? $historia->unidadOrganizativa?->nombre
    ?? null;
```

En la vista, reemplazar el badge `UO #{{ $historia->unidad_organizativa_id }}` por:

```blade
@if($uoNombre)
    <span class="hs-badge hs-badge--uo">{{ $uoNombre }}</span>
@endif
```

El campo `nombre_corto` de `UnidadOrganizativa` puede no existir todavía. Si no existe,
añadirlo a la migración del Cambio 2 como tercer campo opcional (`nombre_corto`, string,
nullable, máximo 40 caracteres), junto con su campo en el formulario Filament dentro de
la sección de identificación de la UO (no en la sección del plan de intervención).

---

## Cambio 5 — Más datos del ciudadano en la cabecera

### Datos actuales
- Nombre completo
- HS #xxxx · UO nombre · Estado HS
- Fecha de nacimiento · Edad · Dirección postal

### Datos a añadir
Bajo la línea de fecha/edad/dirección, añadir una segunda línea con:
- Número de documento de identidad (DNI, NIE, Pasaporte) con su tipo
- Teléfono de contacto (si existe)
- Correo electrónico de contacto (si existe)

### Implementación

En `CiudadanoPage.php`, añadir al `mount()` la carga de estos datos desde el modelo
`Ciudadano` (ya disponible via `$historia->ciudadano`):

```php
$ciudadano = $historia->ciudadano;
$this->ciudadanoDocumento   = $ciudadano->tipo_documento
    ? strtoupper($ciudadano->tipo_documento) . ' ' . $ciudadano->numero_documento
    : null;
$this->ciudadanoTelefono    = $ciudadano->telefono ?? null;
$this->ciudadanoEmail       = $ciudadano->email ?? null;
```

Si los campos `tipo_documento`, `numero_documento`, `telefono`, `email` no existen en el
modelo `Ciudadano` (pueden estar en una tabla de identificadores o de contactos separada),
usar los que existan con el nombre correcto. No inventar campos; dejar en null los que
no estén disponibles y no mostrar la línea si todo es null.

En la vista, añadir la línea de contacto/documento justo debajo de la línea
de fecha de nacimiento / edad / dirección:

```blade
@if($ciudadanoDocumento || $ciudadanoTelefono || $ciudadanoEmail)
<p class="hs-ciudadano-contacto">
    @if($ciudadanoDocumento)
        <span>{{ $ciudadanoDocumento }}</span>
    @endif
    @if($ciudadanoTelefono)
        <span>{{ $ciudadanoTelefono }}</span>
    @endif
    @if($ciudadanoEmail)
        <span>{{ $ciudadanoEmail }}</span>
    @endif
</p>
@endif
```

Separar los elementos con un separador visual ligero (por ejemplo `·` o un `|` en color
`--color-ink-300`) para mantener legibilidad.

---

## Cambio 6 — Reorganización del layout de la pantalla

### Layout objetivo

La pantalla se divide en dos zonas verticales principales:

```
┌──────────────────────────────────────────────────────────┐
│  BANDA SUPERIOR DEL PLAN (fondo --color-primary)         │ ← sin cambios estructurales
├────────────────────┬─────────────────────────────────────┤
│  ZONA SUPERIOR     │  ZONA SUPERIOR DERECHA              │
│  IZQUIERDA         │  (toolbox de herramientas)          │
│  datos ciudadano   │  fondo blanco                       │
│  + UC colapsable   │                                     │
│  fondo blanco      │                                     │
├────────────────────┼─────────────────────────────────────┤
│  ZONA INFERIOR     │  ZONA INFERIOR DERECHA              │
│  filtros +         │  área de trabajo activa             │
│  timeline HS       │  + estadísticas pie                 │
│  fondo paper       │  fondo paper                        │
└────────────────────┴─────────────────────────────────────┘
```

### Detalles de implementación

**Zona superior izquierda (datos del ciudadano + UC):**
- Fondo blanco (`#ffffff`).
- Contiene: nombre, badges (HS, estado), datos de contacto/documento (Cambio 5), UC colapsable.
- La UC colapsable permanece en esta zona, no se mueve.

**Zona superior derecha (toolbox):**
- Fondo blanco (`#ffffff`).
- Contiene únicamente las 7 tarjetas de herramientas de intervención en su grid actual.
- Sin cambios en la lógica de las herramientas.

**Zona inferior izquierda (filtros + timeline):**
- Fondo `var(--color-paper)` (equivalente a `#FAF7F1`).
- Los botones de filtro «Todos», el nombre corto del plan (`$planNombreCorto`),
  «Entrevistas», «Apuntes» se ubican AQUÍ, en la parte superior de esta zona,
  justo encima del listado de episodios. No en la cabecera superior.
- El listado de episodios (timeline) continúa debajo de los filtros.

**Zona inferior derecha (área de trabajo + estadísticas):**
- Fondo `var(--color-paper)`.
- Área de trabajo de la herramienta activa (panel principal donde se expande cada tool).
- Estadísticas en el pie (ver Cambio 7).

### CSS

En `app-operativo.css`, definir las clases del nuevo layout si no existen ya:

```css
.ciudadano-layout {
    display: grid;
    grid-template-columns: 300px 1fr;
    grid-template-rows: auto 1fr;
    height: 100%;
    overflow: hidden;
}
.ciudadano-header-left  { background: #fff; border-right: 1px solid var(--color-border); border-bottom: 1px solid var(--color-border); }
.ciudadano-header-right { background: #fff; border-bottom: 1px solid var(--color-border); }
.ciudadano-body-left    { background: var(--color-paper); border-right: 1px solid var(--color-border); overflow-y: auto; }
.ciudadano-body-right   { background: var(--color-paper); overflow-y: auto; display: flex; flex-direction: column; }
```

Ajustar los nombres de clase exactos a los que ya existen en la vista para minimizar
la cantidad de refactoring.

---

## Cambio 7 — Estadísticas de contexto en el pie del panel derecho

### Objetivo
Mostrar en el pie de la zona inferior derecha tres métricas de lectura rápida que dan
contexto del caso sin necesidad de navegar.

### Métricas

| Métrica | Fuente |
|---|---|
| Total de apuntes en la HS | `$apuntesHS->count()` (ya disponible en el componente) |
| Prestaciones activas | Pendiente de integración real → mostrar `—` con TODO |
| Fecha del último contacto | `$apuntesHS->first()?->created_at` formateada como `d M Y` |

### Implementación

En `CiudadanoPage.php`, calcular estas tres métricas en `mount()` o como propiedades
computadas y exponerlas como propiedades públicas:

```php
$this->statApuntes       = $this->apuntesHS->count();
$this->statPrestaciones  = null; // TODO: integrar con módulo Prestaciones
$this->statUltimoContacto = $this->apuntesHS->first()?->created_at?->translatedFormat('j M Y');
```

En la vista, añadir al pie de la zona inferior derecha:

```blade
<div class="hs-stats-bar">
    <div class="hs-stat">
        <span class="hs-stat__val">{{ $statApuntes }}</span>
        <span class="hs-stat__label">Apuntes</span>
    </div>
    <div class="hs-stat">
        <span class="hs-stat__val">{{ $statPrestaciones ?? '—' }}</span>
        <span class="hs-stat__label">Prestaciones activas</span>
    </div>
    <div class="hs-stat">
        <span class="hs-stat__val">{{ $statUltimoContacto ?? '—' }}</span>
        <span class="hs-stat__label">Último contacto</span>
    </div>
</div>
```

En `app-operativo.css`:

```css
.hs-stats-bar {
    display: flex;
    gap: 0;
    border-top: 1px solid var(--color-border);
    background: var(--color-background);
    flex-shrink: 0;
}
.hs-stat {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 10px 8px;
    border-right: 1px solid var(--color-border);
}
.hs-stat:last-child { border-right: none; }
.hs-stat__val   { font-size: 15px; font-weight: 600; color: var(--color-primary); }
.hs-stat__label { font-size: 11px; color: var(--color-ink-500); margin-top: 2px; font-weight: 500; }
```

---

## Al terminar

1. Verificar que `php artisan view:clear` y `php artisan config:clear` no generan errores.
2. Comprobar que los tests existentes (suite `TF-LW-CIU-*`) siguen en verde. Si algún
   test falla por los cambios de propiedades en `CiudadanoPage`, actualizar el test.
3. Actualizar `SESSION.md`: describir qué se ha completado, qué queda pendiente
   (p. ej. integración real de prestaciones activas en las estadísticas) y el
   siguiente paso recomendado.
4. Añadir una entrada en `CHANGELOG.md` bajo el encabezado correspondiente describiendo
   todos los cambios de esta sesión.
