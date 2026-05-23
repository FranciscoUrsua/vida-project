# Instrucciones CLI — Restyling del backoffice Filament

> Fichero de instrucciones para Claude CLI. Colocar en `docs/instrucciones-cli/filament-backoffice-restyling.md`.
>
> **Leer antes de actuar:**
> 1. `docs/design-system/SKILL.md` — resumen ejecutivo del design system
> 2. `docs/design-system/README.md` — referencia completa de tokens y patrones
> 3. `docs/design-system/colors_and_type.css` — fuente de verdad de los tokens CSS
> 4. `docs/principios-vida360.md` — principios 3.12 y 4.12 (Filament = configuración)
> 5. `CLAUDE.md` — convenciones generales del proyecto

---

## Contexto

El backoffice de VIDA 360 usa **Filament 5.3**. El objetivo es aplicar el design system del proyecto al panel de administración sin modificar la lógica de Filament ni sus componentes PHP: solo se tocan ficheros de presentación (CSS, Blade de tema, configuración del panel).

Filament permite personalización visual mediante:
- Un **service provider del panel** (`AdminPanelProvider`) donde se declara el tema.
- Un fichero CSS propio compilado con Vite que Filament inyecta.
- Variables CSS que sobreescriben los tokens internos de Filament.

**No se toca ningún `Resource`, `Page` ni `Widget` existente** salvo para corregir etiquetas de texto o grupos de navegación. Los cambios son exclusivamente de presentación.

---

## Alcance de esta tarea

### 1. Tema de color y tipografía

Filament 5.3 expone sus colores mediante variables CSS de Tailwind. Hay que mapear los tokens del design system de VIDA a las variables que Filament usa.

**Fichero a crear o editar:** `resources/css/filament/admin/theme.css`

Si el fichero no existe, crearlo. Si ya existe, añadir al final sin eliminar lo que haya.

```css
/* ─────────────────────────────────────────────
   VIDA 360 — Design System tokens para Filament
   Fuente de verdad: docs/design-system/colors_and_type.css
   ───────────────────────────────────────────── */

@import url('https://fonts.googleapis.com/css2?family=Source+Sans+3:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap');

:root {
    /* Tipografía */
    --font-sans: 'Source Sans 3', ui-sans-serif, system-ui, sans-serif;
    --font-mono: 'JetBrains Mono', ui-monospace, monospace;

    /* Colores primarios — mapeados a las variables de Tailwind que usa Filament */
    --color-primary-50:  #EBF2F9;
    --color-primary-100: #C7DDEF;
    --color-primary-200: #9EC4E3;
    --color-primary-300: #6FAAD5;
    --color-primary-400: #4A8EC4;
    --color-primary-500: #2A5B8A;   /* Azul Retiro — color principal */
    --color-primary-600: #214A72;
    --color-primary-700: #1A3A5A;
    --color-primary-800: #122842;
    --color-primary-900: #0A1828;
    --color-primary-950: #060D17;

    /* Fondo general — papel cálido */
    --color-gray-50:  #FAF7F1;   /* --color-paper del design system */
    --color-gray-100: #F2EADA;   /* --color-sand */
    --color-gray-200: #E6E1D8;
    --color-gray-300: #D7CFBE;
    --color-gray-400: #B8B0A0;
    --color-gray-500: #8C8070;
    --color-gray-600: #6B5F52;
    --color-gray-700: #4A3F35;
    --color-gray-800: #2E231A;
    --color-gray-900: #1D160E;   /* --color-ink-900 */
    --color-gray-950: #120D08;

    /* Radios — alineados al design system */
    --radius-sm: 4px;
    --radius-md: 8px;
    --radius-lg: 14px;
    --radius-pill: 9999px;

    /* Sombras cálidas */
    --shadow-sm: 0 1px 2px rgba(29, 22, 14, .04), 0 1px 3px rgba(29, 22, 14, .06);
    --shadow-md: 0 8px 24px rgba(29, 22, 14, .08), 0 2px 6px rgba(29, 22, 14, .05);
}

/* ── Tamaño de fuente base ── */
html {
    font-size: 16px;
}

/* ── Sidebar ── */
.fi-sidebar {
    background-color: #FFFFFF;
    border-right: 1px solid #E6E1D8;
}

.fi-sidebar-nav-groups {
    padding: 8px 0;
}

/* Etiquetas de grupo de navegación */
.fi-sidebar-group-label {
    font-size: 10px;
    font-weight: 600;
    letter-spacing: .06em;
    text-transform: uppercase;
    color: #8C8070;
    padding: 6px 14px 4px;
}

/* Ítems de navegación */
.fi-sidebar-item-button {
    border-radius: 0;
    font-size: 13px;
    padding: 6px 14px;
    color: #6B5F52;
    transition: background 120ms ease-out, color 120ms ease-out;
}

.fi-sidebar-item-button:hover {
    background-color: #FAF7F1;
    color: #1D160E;
}

.fi-sidebar-item-button.fi-active {
    background-color: #FAF7F1;
    color: #1D160E;
    font-weight: 500;
}

/* ── Topbar / Header ── */
.fi-topbar {
    background-color: #FFFFFF;
    border-bottom: 1px solid #E6E1D8;
    height: 56px;
}

/* ── Fondo de contenido ── */
.fi-main {
    background-color: #FAF7F1;
}

/* ── Tablas ── */
.fi-ta-header-cell {
    background-color: #F2EADA;
    font-size: 12px;
    font-weight: 600;
    color: #6B5F52;
    text-transform: none;
    letter-spacing: 0;
}

.fi-ta-row {
    height: 52px;
}

.fi-ta-row:hover td {
    background-color: #FAF7F1;
}

/* ── Cards / Secciones ── */
.fi-section {
    background-color: #FFFFFF;
    border: 1px solid #E6E1D8;
    border-radius: 8px;
    box-shadow: 0 1px 2px rgba(29, 22, 14, .04), 0 1px 3px rgba(29, 22, 14, .06);
}

/* ── Badges de estado (chips) ── */
.fi-badge {
    border-radius: 9999px;
    font-size: 12px;
    font-weight: 600;
    padding: 2px 10px;
}

/* ── Botones primarios ── */
.fi-btn-primary {
    background-color: #2A5B8A;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    transition: background 120ms ease-out, transform 60ms ease-out;
}

.fi-btn-primary:hover {
    background-color: #214A72;
}

.fi-btn-primary:active {
    background-color: #1A3A5A;
    transform: translateY(1px);
}

/* ── Focus ring — accesibilidad obligatoria ── */
:focus-visible {
    outline: 2px solid #2A5B8A;
    outline-offset: 2px;
}

/* ── Inputs ── */
.fi-input {
    font-size: 14px;
    border-radius: 4px;
    border: 1px solid #E6E1D8;
    background-color: #FFFFFF;
    line-height: 1.5;
}

.fi-input:focus {
    border-color: #2A5B8A;
    box-shadow: 0 0 0 1px #2A5B8A inset;
}

/* ── Mono: códigos, DNIs, IDs de auditoría ── */
.fi-ta-cell code,
[data-field="codigo"],
[data-field="dni"],
[data-field="nie"] {
    font-family: 'JetBrains Mono', monospace;
    font-size: 13px;
}

/* ── Colectivos protegidos ── */
.fi-badge[data-status="protegido"] {
    background-color: rgba(107, 61, 107, .12);
    color: #6B3D6B;
    border: 1px solid rgba(107, 61, 107, .25);
}
```

**Registrar el CSS en el panel.** En `app/Providers/Filament/AdminPanelProvider.php`, asegurarse de que el tema está registrado:

```php
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;

// Dentro del método boot() o panel():
->viteTheme('resources/css/filament/admin/theme.css')
```

Si el proyecto usa el método `->theme()` en lugar de `->viteTheme()`, adaptar según la versión instalada de Filament. Verificar en `composer.json` la versión exacta antes de actuar.

---

### 2. Reorganización del menú de navegación

Los grupos de navegación se declaran en cada `Resource` mediante el método `getNavigationGroup()`. El objetivo es tener exactamente cuatro grupos, en este orden:

| Grupo | Resources que lo integran |
|---|---|
| `Catálogos` | `PrestacionResource`, `CentroResource`, `RedResource`, `TipoSlotResource`, `ColectivoProtegidoResource`, `SegmentoPoblacionResource` |
| `Organización` | `UnidadOrganizativaResource`, `ProfesionalResource`, `UsuarioResource`, `UsuarioRolResource`, `ConfiguracionRolResource`, `ServicioEmergenciaResource` |
| `Informes y plantillas` | `PlantillaInformeResource`, `EstiloInformeResource`, `ConfiguracionTipografiaResource` |
| `Sistema` | `ConfiguracionOrganizacionResource`, `ConfiguracionHorarioLaboralResource` |

**Para cada Resource afectado**, editar o añadir:

```php
public static function getNavigationGroup(): ?string
{
    return 'Catálogos'; // o el grupo que corresponda según la tabla anterior
}

public static function getNavigationSort(): ?int
{
    return 10; // ajustar el orden dentro del grupo (10, 20, 30...)
}
```

**Verificar antes de editar** qué Resources existen realmente en `app/Filament/Resources/`. No crear ni eliminar Resources en esta tarea — solo mover grupos.

---

### 3. Dashboard de inicio

Sustituir el dashboard por defecto (mensaje de bienvenida de Filament) por uno con widgets operativos.

**Fichero a crear:** `app/Filament/Pages/Dashboard.php`

```php
<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

/**
 * Panel de inicio del backoffice de VIDA 360.
 *
 * Muestra indicadores de estado del sistema de configuración:
 * prestaciones activas, centros, profesionales y alertas operativas.
 * No muestra métricas de actividad asistencial (→ Power BI).
 * Ver principio 3.14 en docs/principios-vida360.md.
 */
class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';
    protected static ?string $navigationLabel = 'Panel principal';
    protected static ?string $title = 'Panel principal';
    protected static ?int $navigationSort = -10;

    public function getColumns(): int | string | array
    {
        return 4;
    }

    public function getWidgets(): array
    {
        return [
            \App\Filament\Widgets\EstadoSistemaWidget::class,
            \App\Filament\Widgets\RolesPendientesWidget::class,
            \App\Filament\Widgets\AlertasSistemaWidget::class,
            \App\Filament\Widgets\ActividadCatalogosWidget::class,
        ];
    }
}
```

**Widgets a crear en `app/Filament/Widgets/`:**

#### `EstadoSistemaWidget.php`

```php
<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Modules\Prestaciones\Models\Prestacion;
use Modules\Centro\Models\Centro;
use Modules\Usuarios\Models\Profesional;
use App\Models\User;

/**
 * Widget de estado del sistema de configuración.
 * Solo contadores de entidades de configuración — no métricas asistenciales.
 */
class EstadoSistemaWidget extends BaseWidget
{
    protected static ?int $sort = 1;
    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        return [
            Stat::make('Prestaciones activas', Prestacion::activas()->count())
                ->description('de ' . Prestacion::count() . ' en catálogo')
                ->icon('heroicon-o-list-bullet'),

            Stat::make('Centros', Centro::count())
                ->description('en ' . \Modules\Centro\Models\Red::count() . ' redes')
                ->icon('heroicon-o-building-library'),

            Stat::make('Profesionales activos', Profesional::activos()->count())
                ->icon('heroicon-o-users'),

            Stat::make('Roles pendientes', \Modules\Usuarios\Models\HistorialRolUsuario::pendientes()->count())
                ->description('requieren aprobación')
                ->icon('heroicon-o-shield-exclamation')
                ->color('warning'),
        ];
    }
}
```

> **Nota para el implementador:** los scopes `activos()` y `pendientes()` deben existir en los modelos correspondientes. Verificar antes de usar; si no existen, crearlos o sustituir por la query equivalente directa.

#### `RolesPendientesWidget.php`

```php
<?php

namespace App\Filament\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Modules\Usuarios\Models\HistorialRolUsuario;

/**
 * Widget de asignaciones de rol pendientes de aprobación.
 * Acceso directo a la gestión desde el dashboard.
 */
class RolesPendientesWidget extends BaseWidget
{
    protected static ?int $sort = 2;
    protected int | string | array $columnSpan = 2;
    protected static ?string $heading = 'Roles pendientes de aprobación';

    public function table(Table $table): Table
    {
        return $table
            ->query(HistorialRolUsuario::pendientes()->with(['usuario', 'rol']))
            ->columns([
                Tables\Columns\TextColumn::make('usuario.name')
                    ->label('Profesional')
                    ->searchable(),
                Tables\Columns\TextColumn::make('rol.name')
                    ->label('Rol solicitado')
                    ->badge(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Solicitado')
                    ->since()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('aprobar')
                    ->label('Aprobar')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(fn ($record) => $record->aprobar(auth()->user())),
            ])
            ->emptyStateHeading('No hay roles pendientes')
            ->emptyStateDescription('Todas las asignaciones están al día.');
    }
}
```

#### `AlertasSistemaWidget.php`

```php
<?php

namespace App\Filament\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Modules\Mensajes\Models\Alerta;

/**
 * Widget de alertas activas del sistema dirigidas a administración.
 * Solo alertas de ámbito de backoffice (origen sistema/permisos).
 */
class AlertasSistemaWidget extends BaseWidget
{
    protected static ?int $sort = 3;
    protected int | string | array $columnSpan = 2;
    protected static ?string $heading = 'Alertas del sistema';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Alerta::query()
                    ->whereIn('origen_type', [
                        'sistema',
                        \Modules\Usuarios\Models\HistorialRolUsuario::class,
                    ])
                    ->where('estado', 'pendiente')
                    ->latest()
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge(),
                Tables\Columns\TextColumn::make('mensaje')
                    ->label('Descripción')
                    ->limit(60),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Generada')
                    ->since(),
            ])
            ->emptyStateHeading('Sin alertas activas');
    }
}
```

#### `ActividadCatalogosWidget.php`

```php
<?php

namespace App\Filament\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

/**
 * Widget de actividad reciente en catálogos (últimas modificaciones).
 * Usa la tabla de auditoría para mostrar los últimos cambios en entidades de configuración.
 */
class ActividadCatalogosWidget extends BaseWidget
{
    protected static ?int $sort = 4;
    protected int | string | array $columnSpan = 'full';
    protected static ?string $heading = 'Actividad reciente en catálogos';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                \App\Models\Audit::query()
                    ->whereIn('auditable_type', [
                        \Modules\Prestaciones\Models\Prestacion::class,
                        \Modules\Centro\Models\Centro::class,
                        \Modules\Documentos\Models\PlantillaInforme::class,
                    ])
                    ->latest()
                    ->limit(8)
            )
            ->columns([
                Tables\Columns\TextColumn::make('auditable_type')
                    ->label('Entidad')
                    ->formatStateUsing(fn ($state) => class_basename($state))
                    ->badge(),
                Tables\Columns\TextColumn::make('event')
                    ->label('Acción'),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Por'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Cuándo')
                    ->since(),
            ])
            ->emptyStateHeading('Sin actividad reciente');
    }
}
```

---

## Restricciones importantes

- **No modificar** ninguna lógica de negocio en Resources, Models, Policies o Services.
- **No crear** nuevos Resources ni Pages más allá del `Dashboard` y los cuatro widgets.
- **No añadir** dependencias npm o composer no presentes en el proyecto.
- **No inventar** métodos de modelo que no existan. Si un scope o método no existe, indicarlo como pendiente con un comentario `// TODO:` y consultar la documentación del módulo correspondiente.
- **Terminología:** respetar estrictamente el glosario del design system. En etiquetas, headings y copy nunca usar "usuario", "expediente", "ayuda" o "beneficiario".

---

## Verificación

Tras implementar, revisar visualmente:

1. La fuente cargada es Source Sans 3 (verificar en DevTools → Network → Fonts).
2. El color de los botones primarios es `#2A5B8A`, no el azul por defecto de Filament.
3. El fondo del sidebar y de la aplicación es el papel cálido (`#FAF7F1`), no blanco puro ni gris.
4. Los grupos de navegación son exactamente cuatro y en el orden declarado.
5. El dashboard de inicio no contiene ninguna referencia a Filament ni el mensaje de bienvenida por defecto.
6. El ring de foco (`2px #2A5B8A`) es visible al navegar con teclado.
7. Los campos de tipo código/DNI/ID de auditoría se renderizan en JetBrains Mono.
