# Instrucciones CLI — Alta de ciudadano

**Sesión:** 2026-06-09  
**Módulo:** `Modules/Ciudadania`  
**Referencia funcional:** `docs/alta-ciudadano-funcional.md`  
**Referencia técnica:** especificación `AltaCiudadano` en sesión de diseño del 2026-06-09

---

## Contexto

El botón "Dar de alta nuevo ciudadano" en `BuscarCiudadanoPage` está deshabilitado (ver BACKLOG, entrada 2026-06-08). Esta sesión implementa el componente `AltaCiudadano` completo, incluyendo la migración para el campo `primera_demanda` en `ciudadanos`.

El módulo `Ciudadanía` existe en la documentación pero su código está mayormente pendiente. Los servicios `NormalizadorCiudadano` y `MotorMatching` están referenciados en `docs/modulo-ciudadania.md §7.4` pero no implementados. Esta sesión los implementa junto con el componente Livewire.

---

## Tarea 1 — Migración: campo `primera_demanda` en `ciudadanos`

Crear la migración:

```
database/migrations/YYYY_MM_DD_XXXXXX_add_primera_demanda_to_ciudadanos_table.php
```

El campo es `text nullable`, sin cifrado en aplicación (es un dato operativo del momento del alta, no un dato de identidad sensible). Añadir al `$fillable` de `App\Models\Ciudadano` y al cast correspondiente si procede.

---

## Tarea 2 — Servicio `NormalizadorCiudadano`

Crear `Modules/Ciudadania/app/Services/NormalizadorCiudadano.php`.

Métodos requeridos:

**`static documento(string $tipo, string $valor): string`**  
Normaliza a formato canónico. NIF: mayúsculas, sin espacios ni guiones, letra verificada. NIE: formato X-NNNNNNN-L. Pasaporte: mayúsculas, sin espacios. Si el formato no es válido devuelve el valor original sin lanzar excepción (la validación de formato es responsabilidad del formulario, no del normalizador).

**`static nombre(string $valor): string`**  
Elimina espacios múltiples, aplica `mb_convert_case` con `MB_CASE_TITLE`, expande abreviaturas conocidas ("Mª" → "María", "J." → no expandir — solo las que tienen expansión unívoca). Lista de abreviaturas configurable como constante de clase.

**`static telefono(string $valor): string`**  
Elimina espacios, guiones y paréntesis. Si empieza por 6, 7, 8 o 9 sin prefijo, añade "+34". Si ya tiene prefijo internacional lo respeta.

**`static email(string $valor): string`**  
`strtolower` + `trim`.

**`static normalizar(array $datos): array`**  
Aplica todos los normalizadores anteriores sobre el array de datos del formulario. Claves esperadas: `nombre`, `apellido1`, `apellido2`, `tipo_documento`, `valor_documento`, `telefono`, `email`. Devuelve el array con los valores normalizados, dejando intactos los campos no reconocidos.

---

## Tarea 3 — Servicio `MotorMatching`

Crear `Modules/Ciudadania/app/Services/MotorMatching.php`.

El servicio detecta posibles duplicados de un ciudadano aún no guardado. Recibe un array de datos normalizados y devuelve una colección de `ResultadoMatching` ordenada por score descendente.

**`buscar(array $datosnormalizados): Collection`**

Lógica de búsqueda en orden de prioridad:

1. Si hay `valor_documento`: buscar en `ciudadano_identificadores` por hash determinista (`hash_sha256(strtolower($valor))`). Si hay coincidencia exacta, devolver con score máximo (bloqueo). No continuar con los siguientes pasos.

2. Si hay `fecha_nacimiento` + al menos uno de (`apellido1`, `apellido2`): cargar ciudadanos con la misma fecha de nacimiento (búsqueda directa, el campo está cifrado pero el hash de fecha es buscable — si no existe el hash, cargar todos y filtrar en PHP con el mismo patrón que `BuscarCiudadanoPage`). Aplicar Jaro-Winkler sobre nombre y apellidos.

3. Si hay `telefono` o `email`: buscar por hash determinista en `ciudadanos`. Si hay coincidencia exacta, score alto.

**Pesos para el score final:**

| Condición | Peso |
|---|---|
| Documento exacto | 1.0 (bloqueo) |
| Fecha de nacimiento + apellido1 muy similar (JW > 0.92) | 0.85 |
| Fecha de nacimiento + nombre + apellido1 similar (JW > 0.80) | 0.70 |
| Teléfono o email exacto | 0.75 |
| Solo nombre + apellido1 similar | 0.40 |

Los umbrales de actuación (mínimo para mostrar, máximo para bloquear) se leen de `configuracion_sistema('matching.umbral_minimo', 0.60)` y `configuracion_sistema('matching.umbral_bloqueo', 0.90)`.

**DTO `ResultadoMatching`:**

```php
readonly class ResultadoMatching {
    public function __construct(
        public int    $ciudadanoId,
        public string $nombreCompleto,
        public ?string $documento,
        public ?string $fechaNacimiento,
        public float  $score,
        public array  $camposCoincidentes,  // ['documento', 'fecha_nacimiento', 'apellido1']
        public bool   $bloquea,
    ) {}
}
```

Para el cálculo de Jaro-Winkler usar la función nativa de PHP `similar_text` como fallback si no hay librería disponible, o implementar Jaro-Winkler directamente (el algoritmo es simple y no requiere dependencia externa).

---

## Tarea 4 — Componente Livewire `AltaCiudadano`

Crear:
- `Modules/Ciudadania/app/Http/Livewire/AltaCiudadano.php`
- `Modules/Ciudadania/resources/views/livewire/alta-ciudadano.blade.php`

### Propiedades

```php
// Control de flujo
public string $fase = 'busqueda'; // busqueda | padron | formulario | confirmacion

// Fase busqueda
public string $busquedaTipoDoc = 'nif';
public string $busquedaValorDoc = '';
public string $busquedaNombre = '';
public string $busquedaApellido1 = '';
public string $busquedaApellido2 = '';
public string $busquedaFechaNacimiento = '';
public array  $resultadosBusqueda = [];
public bool   $busquedaRealizada = false;

// Fase padron
public ?string $excepcionPadron = null;
public bool    $padronConsultado = false;
public bool    $padronEncontrado = false;

// Fase formulario — datos del ciudadano
public string  $nombre = '';
public string  $apellido1 = '';
public string  $apellido2 = '';
public string  $fechaNacimiento = '';
public string  $sexo = '';
public string  $alias = '';
public string  $tipoDocumento = 'nif';
public string  $valorDocumento = '';
public string  $direccionTexto = '';
public string  $telefono = '';
public string  $email = '';
public array   $fuenteCampos = []; // ['nombre' => 'padron', ...]

// Fase confirmacion
public string  $primeraDemanda = '';
public string  $accionPostAlta = 'ficha'; // cita | ficha | solo_alta
public ?int    $ciudadanoIdCreado = null;
```

### Métodos

**`buscar()`**  
Valida que haya al menos un criterio (documento O nombre+fecha). Ejecuta `MotorMatching::buscar()` con los datos de búsqueda normalizados. Asigna `$resultadosBusqueda` y `$busquedaRealizada = true`. No transiciona de fase.

**`seleccionarExistente(int $ciudadanoId)`**  
Redirige a la ficha: `$this->redirectRoute('ciudadania.ciudadano.ficha', $ciudadanoId)`.

**`continuarConNuevoAlta()`**  
Requiere `$busquedaRealizada === true`. Precarga en el formulario los datos introducidos en la búsqueda (documento, nombre, apellidos, fecha si los hay). Transiciona a fase `padron`, salvo que `$excepcionPadron === 'vvg'` (viene de una selección previa), en cuyo caso va directamente a `formulario`.

**`consultarPadron()`**  
Solo se ejecuta si `$excepcionPadron` es null o distinto de `'vvg'`. Si es `'vvg'`, ignorar la llamada silenciosamente. En cualquier otro caso, llamar a `app(FuenteIdentidadInterface::class)->consultarDatos($valorDocumento)`. Si devuelve datos: precargar campos con `$fuenteCampos['campo'] = 'padron'`, `$padronEncontrado = true`, transicionar a `formulario`. Si no devuelve datos o el servicio falla: `$padronEncontrado = false`, permanecer en fase `padron` para que el profesional seleccione excepción.

**`seleccionarExcepcionPadron(string $excepcion)`**  
Validar que `$excepcion` está en `['psh', 'vvg', 'representante', 'otra']`. Para `'psh'` y `'vvg'`, verificar `auth()->user()->hasRole(['intervencion', 'supervision'])` — si no, retornar sin hacer nada (sin excepción, sin mensaje). Asignar `$excepcionPadron = $excepcion`. Transicionar a fase `formulario`.

**`guardar()`**  
1. Validar con `$this->validate($this->rules())`.  
2. Normalizar con `NormalizadorCiudadano::normalizar([...])`.  
3. Segunda pasada de `MotorMatching::buscar($datosNormalizados)`. Si hay resultado que bloquea y no estaba en la primera pasada: asignar a `$resultadosBusqueda`, transicionar a `busqueda`, retornar.  
4. `DB::transaction()`:
   - Calcular `$nivelIdentificacion`: `'identificado'` si hay documento, `'probable'` si hay nombre+fecha sin documento, `'no_identificado'` solo si contexto PSH y sin ningún dato de identidad.
   - `Ciudadano::create([...campos normalizados, 'nivel_identificacion' => $nivelIdentificacion, 'origen_direccion' => OrigenDireccion::Profesional])`. El `DireccionObserver` lanzará la geocodificación automáticamente.
   - Si hay documento: `CiudadanoIdentificador::create(['ciudadano_id' => $ciudadano->id, 'tipo' => $tipoDocumento, 'valor' => $valorDocumento, 'fecha_inicio' => today(), 'verificado' => false, 'fuente' => 'manual'])`.
5. Asignar `$ciudadanoIdCreado = $ciudadano->id`. Transicionar a fase `confirmacion`.

**`confirmarAlta()`**  
Si `$primeraDemanda` no está vacío: `Ciudadano::find($ciudadanoIdCreado)->update(['primera_demanda' => $primeraDemanda])`.  
Enrutar según `$accionPostAlta`:
- `'cita'` → `$this->redirectRoute('ciudadania.ciudadano.nueva-cita', $ciudadanoIdCreado)`
- `'ficha'` → `$this->redirectRoute('ciudadania.ciudadano.ficha', $ciudadanoIdCreado)`
- `'solo_alta'` → `$this->redirectRoute('ciudadania.buscar')`

### Reglas de validación

```php
protected function rules(): array
{
    $esPsh = $this->excepcionPadron === 'psh';
    return [
        'nombre'          => $esPsh ? 'nullable|string|max:100' : 'required|string|max:100',
        'apellido1'       => $esPsh ? 'nullable|string|max:100' : 'required|string|max:100',
        'apellido2'       => 'nullable|string|max:100',
        'fechaNacimiento' => 'nullable|date|before:today',
        'sexo'            => 'required|string',
        'alias'           => $esPsh ? 'required|string|max:200' : 'nullable|string|max:200',
        'tipoDocumento'   => 'nullable|string|in:nif,nie,pasaporte',
        'valorDocumento'  => 'nullable|string|max:20',
        'direccionTexto'  => 'nullable|string|max:500',
        'telefono'        => 'nullable|string|max:20',
        'email'           => 'nullable|email|max:255',
        'primeraDemanda'  => 'nullable|string|max:2000',
    ];
}
```

### Ruta

Añadir en `Modules/Ciudadania/routes/web.php` (crearlo si no existe):

```php
Route::middleware(['auth', 'role_or_permission:intervencion|supervision|tramitacion|consulta_basica'])
    ->group(function () {
        Route::get('/ciudadania/buscar', BuscarCiudadanoPage::class)->name('ciudadania.buscar');
        Route::get('/ciudadania/alta', AltaCiudadano::class)->name('ciudadania.alta');
    });
```

Habilitar el botón en `BuscarCiudadanoPage`: sustituir el atributo `disabled` por `wire:navigate` apuntando a `route('ciudadania.alta')`.

---

## Tarea 5 — Tests

Crear `Modules/Ciudadania/tests/Feature/Livewire/AltaCiudadanoTest.php`.

Implementar los siguientes tests con las fixtures mínimas necesarias. Usar `RefreshDatabase`. Los mocks de `FuenteIdentidadInterface` deben inyectarse con `$this->app->instance()`.

| ID | Descripción |
|---|---|
| TF-LW-ALT-01 | Componente no accesible sin autenticación → 302 |
| TF-LW-ALT-02 | Roles `intervencion`, `tramitacion`, `consulta_basica` pueden montar el componente |
| TF-LW-ALT-03 | `buscar()` por documento normaliza y encuentra por hash |
| TF-LW-ALT-04 | `buscar()` por nombre+fecha devuelve candidatos ordenados por score |
| TF-LW-ALT-05 | Coincidencia exacta por documento: `$resultadosBusqueda[0]->bloquea === true` |
| TF-LW-ALT-06 | Sin coincidencias: `continuarConNuevoAlta()` transiciona a fase `padron` |
| TF-LW-ALT-07 | Padrón encontrado: campos precargados con `fuenteCampos['nombre'] === 'padron'` |
| TF-LW-ALT-08 | Padrón no encontrado: fase permanece en `padron` |
| TF-LW-ALT-09 | Rol `tramitacion` llama a `seleccionarExcepcionPadron('psh')` → `$excepcionPadron` permanece null |
| TF-LW-ALT-10 | Rol `intervencion` puede seleccionar las cuatro excepciones |
| TF-LW-ALT-11 | Excepción `vvg`: `consultarPadron()` no invoca `FuenteIdentidadInterface` |
| TF-LW-ALT-12 | Contexto PSH: `guardar()` sin nombre acepta; sin alias falla validación |
| TF-LW-ALT-13 | Contexto estándar: `guardar()` sin nombre falla validación |
| TF-LW-ALT-14 | `guardar()` exitoso crea `Ciudadano` + `CiudadanoIdentificador` en transacción |
| TF-LW-ALT-15 | `nivel_identificacion` = `identificado` con documento; `probable` sin documento |
| TF-LW-ALT-16 | `confirmarAlta()` con `accion = 'ficha'` redirige a ficha del ciudadano creado |
| TF-LW-ALT-17 | `confirmarAlta()` con `accion = 'solo_alta'` redirige a `ciudadania.buscar` |
| TF-LW-ALT-18 | Primera demanda se persiste en `ciudadanos.primera_demanda` |
| TF-LW-ALT-19 | Segunda pasada de matching en `guardar()` bloquea y retrocede a `busqueda` si hay coincidencia casi segura nueva |

---

## Tarea 6 — BACKLOG y CHANGELOG

Marcar en `BACKLOG.md` como resuelto:

> **Alta de ciudadano desde búsqueda — pendiente** — 2026-06-08

Añadir entrada en `CHANGELOG.md` con:
- Fecha: la de ejecución de la sesión
- Módulo: `Ciudadanía`
- Descripción: migración `primera_demanda`, servicios `NormalizadorCiudadano` y `MotorMatching`, componente Livewire `AltaCiudadano`, tests TF-LW-ALT-01 a TF-LW-ALT-19
- Decisiones de implementación relevantes (ver sección siguiente)

---

## Decisiones de implementación a documentar en CHANGELOG

1. **`primera_demanda` en `ciudadanos` (Capa 1), no como apunte.** La primera demanda es un dato del momento del alta, no un acto profesional. No requiere historia social previa. Campo `text nullable` sin cifrado.

2. **Motor de matching sin IA.** Se usa Jaro-Winkler / `similar_text` de PHP. Los algoritmos deterministas son auditables y suficientes para este caso de uso.

3. **VVG: la consulta al padrón no se lanza, no se ignora.** La condición se evalúa antes de cualquier llamada HTTP. Documentar explícitamente en el código con comentario de seguridad.

4. **PSH y VVG: validación de rol en servidor, no solo en vista.** `seleccionarExcepcionPadron()` verifica el rol aunque la vista no muestre los botones. La UI oculta; el servidor rechaza.

5. **Geocodificación transparente.** El componente no invoca el geocodificador directamente. Lo dispara `DireccionObserver` al hacer `Ciudadano::create()` con `origen_direccion = OrigenDireccion::Profesional`.

6. **Búsqueda por nombre: carga ≤ 500 registros y filtra en PHP.** Mismo patrón que `BuscarCiudadanoPage`. TODO: reemplazar por índice hash determinista cuando esté disponible. Documentar este TODO en el código.
