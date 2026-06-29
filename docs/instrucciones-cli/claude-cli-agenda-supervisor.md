# Instrucciones para Claude CLI — UI Agenda Supervisor

**Tarea:** Implementar la interfaz de supervisión del módulo Agenda de VIDA 360
**Documentos de referencia:**
- `docs/modulo-agenda.md` — modelo de datos, entidades y servicios
- `docs/ui-supervisor-agenda.md` — especificación completa de la UI
**Tests de aceptación:** `docs/tests-ui-supervisor-agenda.md`

---

## Contexto del proyecto

VIDA 360 es una aplicación Laravel modular. El módulo Agenda está en `Modules/Agenda`. La lógica de dominio (servicios, modelos, tests) está implementada y en verde. Lo que falta es la interfaz.

La interfaz usa dos tecnologías según el tipo de pantalla:
- **Filament** (`Modules/Agenda/app/Filament/`) para configuración y backoffice.
- **Livewire** (`Modules/Agenda/app/Livewire/`) para pantallas operativas.

Antes de empezar, lee los documentos de referencia completos. No asumas nada que no esté documentado.

---

## Paso 0 — Verificación del entorno

```bash
# Confirmar que los tests de dominio pasan
php vendor/bin/phpunit --filter="Modules\\Agenda" --testdox

# Confirmar que Filament y Livewire están disponibles
php artisan filament:check
php artisan livewire:list
```

Si algún test de dominio falla, detente y reporta el error. No continúes con la UI hasta que el dominio esté en verde.

---

## Paso 1 — Resource Filament: TipoSlot

### Qué hacer

Crear `Modules/Agenda/app/Filament/Resources/TipoSlotResource.php` con su formulario y tabla.

### Especificación

**Tabla (lista):**
- Columna `nombre` — texto, sortable
- Columna `duracion_minutos` — numérico, suffix "min", sortable
- Columna `bloquea_todos_convocados` — boolean (icono check/cruz)
- Columna `activo` — boolean con badge verde/gris
- Acción editar, acción eliminar (soft si el modelo lo soporta)
- Filtro por `activo`

**Formulario (crear / editar):**
```php
// Campos en orden
TextInput::make('nombre')
    ->required()->maxLength(100)
    ->placeholder('Ej: Reunión de equipo')

Textarea::make('descripcion')->nullable()->rows(2)

Select::make('duracion_minutos')
    ->options([15=>,'15 min',30=>'30 min',45=>'45 min',
               60=>'1 hora',90=>'1 h 30 min',120=>'2 horas',
               150=>'2 h 30 min',180=>'3 horas'])
    ->required()
    ->helperText('La duración incluye preparación y cierre. No se añaden buffers adicionales.')

Toggle::make('bloquea_todos_convocados')
    ->helperText('Si está activo, al usar este tipo en la semana tipo bloqueará el hueco en todos los profesionales del centro')
    ->default(false)

Toggle::make('activo')->default(true)
```

**Grupo de navegación Filament:** "Agenda — Configuración"

**Permisos:** solo usuarios con rol `supervisor` o `admin` pueden acceder.

### Archivos a crear

```
Modules/Agenda/app/Filament/Resources/TipoSlotResource.php
Modules/Agenda/app/Filament/Resources/TipoSlotResource/Pages/ListTiposSlot.php
Modules/Agenda/app/Filament/Resources/TipoSlotResource/Pages/CreateTipoSlot.php
Modules/Agenda/app/Filament/Resources/TipoSlotResource/Pages/EditTipoSlot.php
```

### Verificación

```bash
php artisan filament:cache-components
# Navegar a /admin/tipos-slot y confirmar que carga sin errores
```

---

## Paso 2 — Livewire: SemanaTypoComponent

### Qué hacer

Crear el componente Livewire que permite al director definir la semana tipo del centro.

### Ruta

`/supervisor/centro/{centro}/semana-tipo`

### Archivos a crear

```
Modules/Agenda/app/Livewire/SemanaTypoComponent.php
Modules/Agenda/resources/views/livewire/semana-typo.blade.php
```

### Especificación del componente

```php
class SemanaTypoComponent extends Component
{
    public Centro $centro;
    public array $semana = [];   // estructura editable en memoria
    public string $estado = '';  // '' | 'guardado' | 'error'

    public function mount(Centro $centro): void
    public function guardar(): void          // persiste HorarioCentro.semana_tipo
    public function abrirFranja(int $profId, int $diaSemana, ?int $franjaIdx): void
    public function guardarFranja(array $data): void
    public function eliminarFranja(int $profId, int $diaSemana, int $franjaIdx): void
    public function copiarDia(int $diaOrigen, array $diasDestino): void
    public function getSlotsEstimadosProperty(): array  // computed: slots por día
}
```

**Estructura `$semana`:**
```php
// Igual que HorarioCentro.semana_tipo pero expandida por profesional
// para la edición. Al guardar se colapsa al formato JSON del modelo.
[
  'profId' => [
    'diaNumero' => [   // 1=Lun … 5=Vie
      ['tipo' => 'atencion', 'inicio' => '09:00', 'fin' => '13:00', 'etiqueta' => ''],
      ['tipo' => 'descanso', 'inicio' => '10:00', 'fin' => '10:30', 'etiqueta' => 'Descanso A'],
    ]
  ]
]
```

**Tipos de franja válidos:** `atencion`, `descanso`, `trabajo_propio`, `evento_interno`.

**Cálculo de slots estimados:**
```php
// Para cada día y cada profesional, sumar minutos de franjas tipo 'atencion'
// y dividir entre 30 (duración fija de cita ciudadana).
// Redondeo hacia abajo (floor).
```

**Al guardar:**
1. Validar que ninguna franja tiene `fin <= inicio`.
2. Validar que no hay solapamientos dentro del mismo profesional y día.
3. Si hay un `CuadranteMes` en estado `borrador` para el mes siguiente, mostrar aviso: "Hay un borrador en curso para [mes]. Los cambios se aplicarán si lo regeneras."
4. Persistir en `HorarioCentro.semana_tipo`.
5. Mostrar toast de confirmación.

### Vista blade

La vista debe respetar el design system VIDA 360:
- Sidebar 188px con nav activo en "Semana tipo"
- Topbar 52px con logo + breadcrumb + avatar supervisor
- Indicador de 5 pasos en la parte superior
- Grid con columna fija de profesional (148px) + 5 columnas de días
- Cabecera sticky al topbar
- Modal de franja (Alpine.js o Livewire modal)
- Fila de slots estimados bajo el grid

Usar clases CSS del design system existente. No inventar estilos nuevos.

---

## Paso 3 — Livewire: PerfilHorarioComponent (pestaña en ficha de profesional)

### Qué hacer

Añadir dos pestañas nuevas a la ficha de profesional existente: "Perfil horario" y "Excepciones".

### Localizar la ficha existente

```bash
# Encontrar el componente o vista de la ficha de profesional
grep -r "PerfilProfesional\|FichaProfesional\|mi-equipo" Modules/ --include="*.php" -l
```

### Archivos a crear / modificar

```
Modules/Agenda/app/Livewire/PerfilHorarioComponent.php
Modules/Agenda/app/Livewire/ExcepcionesComponent.php
Modules/Agenda/resources/views/livewire/perfil-horario.blade.php
Modules/Agenda/resources/views/livewire/excepciones.blade.php
# Modificar la vista de ficha de profesional para incluir las nuevas pestañas
```

### Especificación PerfilHorarioComponent

```php
class PerfilHorarioComponent extends Component
{
    public Usuario $profesional;
    public Centro $centro;
    public array $diasActivos = [];     // [1,2,3,4,5]
    public array $franjasPorDia = [];   // [diaN => [{mIni, mFin, tIni?, tFin?}]]
    public float $jornadaSemanal = 35;
    public string $vigenteDesde = '';
    public string $notas = '';

    public function mount(Usuario $profesional, Centro $centro): void
    public function toggleDia(int $dia): void
    public function addTarde(int $dia): void
    public function removeTarde(int $dia): void
    public function guardar(): void
    public function getRresumenProperty(): array  // jornada, días activos, horario principal
}
```

**Al guardar:**
1. Si existe un `PerfilHorarioProfesional` activo para este profesional y centro:
   - Si `vigenteDesde` es igual al actual: actualizar el registro.
   - Si `vigenteDesde` es diferente: marcar el actual con `vigente_hasta = vigenteDesde - 1 día`, crear nuevo registro.
2. Mostrar aviso: "Los cambios se aplicarán al generar el próximo cuadrante mensual."

### Especificación ExcepcionesComponent

```php
class ExcepcionesComponent extends Component
{
    public Usuario $profesional;
    public Centro $centro;
    public bool $modalAbierto = false;
    public array $form = [];       // datos del modal
    public ?int $editandoId = null;

    public function mount(Usuario $profesional, Centro $centro): void
    public function abrirModal(?int $excepcionId = null): void
    public function guardar(): void
    public function eliminar(int $id): void
    public function getProximasProperty(): Collection
    public function getHistorialProperty(): Collection
}
```

**Validaciones al guardar:**
- `fecha_fin >= fecha_inicio`
- Si `afecta_disponibilidad = true`: consultar citas confirmadas en el período y mostrar resumen antes de guardar.
- `tipo` debe ser uno de los valores del enum `ExcepcionProfesional::TIPOS`.

---

## Paso 4 — Livewire: CuadranteMesComponent

### Qué hacer

Crear la pantalla principal de supervisión del cuadrante mensual.

### Ruta

`/supervisor/centro/{centro}/cuadrante/{anyo}/{mes}`

### Archivos a crear

```
Modules/Agenda/app/Livewire/CuadranteMesComponent.php
Modules/Agenda/resources/views/livewire/cuadrante-mes.blade.php
```

### Especificación del componente

```php
class CuadranteMesComponent extends Component
{
    public Centro $centro;
    public int $anyo;
    public int $mes;
    public int $semanaActual = 0;   // 0-4
    public ?CuadranteMes $cuadrante = null;

    // Modal añadir evento
    public bool $modalEventoAbierto = false;
    public array $eventoForm = [];
    public ?int $eventoProf = null;
    public ?int $eventoDay = null;

    // Modal excepción
    public bool $modalExcAbierto = false;
    public array $excDetalle = [];

    public function mount(Centro $centro, int $anyo, int $mes): void
    public function prevSemana(): void
    public function nextSemana(): void
    public function goSemana(int $idx): void

    // Grid
    public function getDiasSemanProperty(): array   // días laborables de la semana actual
    public function getCelda(int $profId, Carbon $fecha): array  // franjas + excepciones

    // Evento puntual
    public function abrirModalEvento(int $profId, int $dayNum): void
    public function guardarEvento(): void

    // Excepción
    public function abrirModalExc(int $profId, int $dayNum): void

    // Publicación
    public function publicar(string $notasEquipo = ''): void

    // Métricas
    public function getMetricasProperty(): array
    public function getExcepcionesIncorporadasProperty(): array
}
```

**Método `getCelda`:**
```php
// Para un profesional y fecha, devuelve:
// - franjas: array de bloques de la LineaCuadrante (si existe)
//   o de la semana tipo cruzada con el perfil (si el cuadrante es borrador y no hay línea)
// - excepcion: ExcepcionProfesional|null
// - eventos: EventoAgenda[] del día para ese profesional
// - tipo_celda: 'normal' | 'excepcion' | 'no_laborable'
```

**Método `guardarEvento`:**
```php
// 1. Crear EventoAgenda con origen = 'director'
// 2. Llamar a $evento->agregarProfesionales($convocados)
// 3. Si hay citas confirmadas afectadas, devolver lista para mostrar aviso
// 4. Emitir evento Livewire para refrescar el grid
```

**Método `publicar`:**
```php
// 1. Cambiar CuadranteMes.estado a 'publicado'
// 2. Registrar publicado_en y publicado_por_id
// 3. Llamar a SlotMaterializadorService
// 4. Enviar notificación a profesionales del centro
// 5. Refrescar la vista
```

### Vista blade — estructura mínima

```blade
{{-- Cabecera con badge de estado --}}
{{-- Alerta de excepciones incorporadas --}}
{{-- Métricas (4 tarjetas) --}}
{{-- Navegación por semanas --}}
{{-- Leyenda --}}
{{-- Grid: thead sticky + tbody con filas de profesionales --}}
{{-- Modal añadir evento --}}
{{-- Modal detalle excepción --}}
{{-- Modal publicar --}}
```

**Grid — reglas de renderizado:**
- Celda `tipo_celda = 'excepcion'`: fondo atenuado, chip de excepción, cursor pointer, clic → `abrirModalExc()`.
- Celda `tipo_celda = 'normal'`: chips de franjas + botón "＋ Evento", clic en celda → `abrirModalEvento()`.
- Celda `tipo_celda = 'no_laborable'`: fondo `--ink1`, sin interacción.

---

## Paso 5 — Rutas y navegación

### Registrar rutas Livewire

En `Modules/Agenda/routes/web.php`:

```php
Route::middleware(['auth', 'role:supervisor|admin'])->prefix('supervisor')->group(function () {
    Route::get('/centro/{centro}/semana-tipo', SemanaTypoComponent::class)
        ->name('agenda.semana-tipo');

    Route::get('/centro/{centro}/cuadrante/{anyo}/{mes}', CuadranteMesComponent::class)
        ->name('agenda.cuadrante');
});

// Las rutas de perfil y excepciones se integran en la ficha de profesional existente
```

### Añadir al sidebar del supervisor

Localizar el componente de sidebar y añadir los ítems de navegación de Agenda:

```php
// En la sección de configuración del centro:
['label' => 'Semana tipo',    'route' => 'agenda.semana-tipo',  'icon' => 'calendar']
['label' => 'Tipos de slot',  'route' => 'filament.admin.resources.tipos-slot.index', 'icon' => 'clock']

// En la sección principal:
['label' => 'Cuadrante',      'route' => 'agenda.cuadrante',    'icon' => 'calendar-days']
```

---

## Paso 6 — Verificación final

Una vez implementados todos los pasos, ejecutar el juego de tests de UI:

```bash
php artisan test --filter="UIAgendaSupervisor" --testdox
```

Y verificar manualmente el flujo completo:

1. Entrar como supervisor al centro CSS Retiro (fixture de desarrollo).
2. Ir a Configuración → Tipos de slot → crear "Reunión de equipo" (60 min, bloquea todos).
3. Ir a Configuración → Semana tipo → definir franjas para lunes a viernes.
4. Ir a Mi equipo → Laura Díaz → Perfil horario → configurar solo mañanas.
5. Ir a Mi equipo → Laura Díaz → Excepciones → registrar vacaciones semana 4 de julio.
6. Ir a Cuadrante → Julio 2026 → verificar que las excepciones aparecen.
7. Añadir una reunión interna el lunes 7 de julio para todo el equipo.
8. Publicar el cuadrante y verificar que los slots se materializan.

---

## Notas generales

- No modificar los servicios de dominio (`CuadranteGeneratorService`, `SlotMaterializadorService`, `DisponibilidadService`). Solo consumirlos desde los componentes Livewire.
- Todos los componentes Livewire deben extender `Livewire\Component` y usar `#[Layout('layouts.supervisor')]`.
- Los modales se implementan con Alpine.js (`x-show`, `x-on:click.away`) o con Livewire modals según el patrón ya establecido en el proyecto.
- Los toasts de confirmación siguen el patrón `dispatch('toast', ['message' => ..., 'type' => 'success'])` ya existente.
- No usar CSS inline ni estilos fuera del design system. Revisar las clases disponibles en `resources/css/vida360.css` antes de añadir estilos.
