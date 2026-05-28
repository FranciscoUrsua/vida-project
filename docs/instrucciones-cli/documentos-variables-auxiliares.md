# Instrucciones CLI — Variables auxiliares en plantillas de informe

> Leer este fichero íntegramente antes de escribir cualquier línea de código.
> Prerrequisito: módulo Documentos implementado, merge tags funcionando.
> Referencia: `docs/modulo-documentos.md`.

---

## Contexto y alcance

Las plantillas de informe ya soportan merge tags contextuales (datos del ciudadano,
profesional, escalas, etc.). Esta sesión añade dos nuevas categorías de variables:

**Variables dinámicas de sistema** — calculadas en tiempo de ejecución, iguales para
todos los informes. No tienen configuración. Ejemplos: `fecha_hoy`, `año_actual`.

**Parámetros configurables** — valores fijos que el administrador define en backoffice.
Permiten crear variables como `{{ ciudad }}` o `{{ web_municipal }}` sin tocar código.
Son globales (un único valor por instalación). La variante por UO queda documentada
como evolución futura.

**Ficheros a crear o modificar:**

| Acción | Fichero |
|---|---|
| Crear | `Modules/Documentos/database/migrations/..._create_parametros_informe_table.php` |
| Crear | `Modules/Documentos/app/Models/ParametroInforme.php` |
| Crear | `app/Filament/Resources/ParametroInformeResource.php` |
| Modificar | `Modules/Documentos/app/Support/MergeTagsCatalogo.php` |
| Modificar | `Modules/Documentos/app/Services/ResolverFuentesInforme.php` |
| Modificar | `docs/modulo-documentos.md` |

No se tocan migraciones existentes ni otros servicios.

---

## 1. Migración `parametros_informe`

```php
Schema::create('parametros_informe', function (Blueprint $table) {
    $table->id();
    $table->string('clave', 100)->unique();
    $table->string('etiqueta', 200);
    $table->text('valor');
    $table->text('descripcion')->nullable();
    $table->timestamps();
});
```

La clave `unique` garantiza que no pueden existir dos parámetros con el mismo
nombre de tag. No hay softDeletes: borrar un parámetro lo elimina del catálogo.

---

## 2. Modelo `ParametroInforme`

```php
<?php

namespace Modules\Documentos\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class ParametroInforme extends Model
{
    protected $table = 'parametros_informe';

    protected $fillable = ['clave', 'etiqueta', 'valor', 'descripcion'];

    /**
     * Devuelve todos los parámetros como array clave => valor,
     * con caché de 60 minutos que se invalida al guardar o borrar.
     *
     * @return array<string, string>
     */
    public static function comoMapa(): array
    {
        return Cache::remember('parametros_informe_mapa', now()->addHour(), function () {
            return static::pluck('valor', 'clave')->all();
        });
    }

    /**
     * Devuelve todos los parámetros como array clave => etiqueta,
     * para el catálogo de merge tags del editor.
     *
     * @return array<string, string>
     */
    public static function comoEtiquetas(): array
    {
        return Cache::remember('parametros_informe_etiquetas', now()->addHour(), function () {
            return static::pluck('etiqueta', 'clave')->all();
        });
    }

    /**
     * Invalida la caché al guardar o borrar un parámetro.
     */
    protected static function booted(): void
    {
        $invalidar = fn () => Cache::forget('parametros_informe_mapa')
            || Cache::forget('parametros_informe_etiquetas');

        static::saved($invalidar);
        static::deleted($invalidar);
    }
}
```

---

## 3. Seeder de parámetros de ejemplo

Crear `Modules/Documentos/database/seeders/ParametroInformeSeeder.php` con
parámetros útiles para una instalación nueva. Usar `updateOrCreate` para
garantizar idempotencia:

```php
$parametros = [
    [
        'clave'       => 'ciudad',
        'etiqueta'    => 'Nombre del municipio',
        'valor'       => 'Madrid',
        'descripcion' => 'Nombre del municipio que aparece en los informes.',
    ],
    [
        'clave'       => 'nombre_sistema',
        'etiqueta'    => 'Nombre del sistema de servicios sociales',
        'valor'       => 'Servicios Sociales Municipales',
        'descripcion' => 'Denominación institucional del sistema.',
    ],
    [
        'clave'       => 'web_municipal',
        'etiqueta'    => 'Web del ayuntamiento',
        'valor'       => 'www.madrid.es',
        'descripcion' => 'URL de la web municipal para el pie de los informes.',
    ],
    [
        'clave'       => 'telefono_atencion',
        'etiqueta'    => 'Teléfono de atención ciudadana',
        'valor'       => '010',
        'descripcion' => 'Teléfono general de información y atención al ciudadano.',
    ],
];

foreach ($parametros as $p) {
    ParametroInforme::updateOrCreate(['clave' => $p['clave']], $p);
}
```

Añadir este seeder a `DatabaseSeeder` solo si no está ya incluido.

---

## 4. Variables dinámicas de sistema

Definir en una clase estática dedicada. No hay tabla ni configuración.

**Crear:** `Modules/Documentos/app/Support/VariablesDinamicas.php`

```php
<?php

namespace Modules\Documentos\Support;

use Carbon\Carbon;

/**
 * Variables auxiliares calculadas en tiempo de ejecución.
 * Son iguales para todos los informes generados en el mismo momento.
 * No requieren configuración ni BD.
 *
 * Al añadir una variable aquí, añadirla también en etiquetas() para
 * que aparezca en el editor, y asegurarse de que resolver() la incluye.
 */
class VariablesDinamicas
{
    /**
     * Devuelve el mapa clave => etiqueta para el catálogo del editor.
     *
     * @return array<string, string>
     */
    public static function etiquetas(): array
    {
        return [
            'fecha_hoy'      => 'Sistema — Fecha de hoy (dd/mm/aaaa)',
            'año_actual'     => 'Sistema — Año actual',
            'mes_actual'     => 'Sistema — Mes actual (nombre)',
        ];
    }

    /**
     * Devuelve el mapa clave => valor resuelto en este momento.
     *
     * @return array<string, string>
     */
    public static function resolver(): array
    {
        $hoy = Carbon::now();

        return [
            'fecha_hoy'  => $hoy->format('d/m/Y'),
            'año_actual' => $hoy->format('Y'),
            'mes_actual' => ucfirst($hoy->translatedFormat('F')), // requiere locale es_ES
        ];
    }
}
```

---

## 5. Actualizar `MergeTagsCatalogo`

Modificar `Modules/Documentos/app/Support/MergeTagsCatalogo.php` para que
`todos()` incluya las variables dinámicas y los parámetros configurables.

```php
public static function todos(): array
{
    return array_merge(
        // Variables contextuales (ciudadano, profesional, etc.) — ya existentes
        self::contextuales(),

        // Variables dinámicas de sistema
        VariablesDinamicas::etiquetas(),

        // Parámetros configurables — leídos de BD con caché
        self::etiquetasParametros(),
    );
}

/**
 * Mueve los tags contextuales actuales a este método privado.
 * Extraer el contenido actual de todos() aquí sin modificarlo.
 */
private static function contextuales(): array
{
    return [
        // Ciudadano
        'nombre_ciudadano'        => 'Ciudadano — Nombre completo',
        // ... resto del catálogo actual sin cambios
    ];
}

/**
 * Etiquetas de los parámetros configurables, con prefijo de grupo.
 *
 * @return array<string, string>
 */
private static function etiquetasParametros(): array
{
    return collect(ParametroInforme::comoEtiquetas())
        ->mapWithKeys(fn (string $etiqueta, string $clave) => [
            $clave => 'Parámetro — ' . $etiqueta,
        ])
        ->all();
}
```

**Importante:** añadir los imports necesarios al inicio del fichero:

```php
use Modules\Documentos\Models\ParametroInforme;
use Modules\Documentos\Support\VariablesDinamicas;
```

---

## 6. Actualizar `ResolverFuentesInforme`

Modificar `construirMapaValores()` para incluir las variables dinámicas y
los parámetros configurables. Añadirlos al final del array de retorno, para
que los tags contextuales tengan prioridad en caso de colisión de claves:

```php
private function construirMapaValores(
    int $ciudadanoId,
    int $profesionalId,
    Carbon $fechaInforme
): array {
    // ... código existente que construye $valores con los tags contextuales ...

    // Añadir variables dinámicas de sistema
    $valores = array_merge(VariablesDinamicas::resolver(), $valores);

    // Añadir parámetros configurables (menor prioridad que los contextuales)
    $valores = array_merge(ParametroInforme::comoMapa(), $valores);

    return $valores;
}
```

El orden de `array_merge` es deliberado: los valores que aparecen más a la
derecha sobreescriben a los de la izquierda. Los tags contextuales siempre
ganan frente a los parámetros configurables, que a su vez ganan frente a las
variables dinámicas. Esto evita que un administrador rompa un informe creando
un parámetro `nombre_ciudadano`.

Añadir también los imports:

```php
use Modules\Documentos\Models\ParametroInforme;
use Modules\Documentos\Support\VariablesDinamicas;
```

---

## 7. Resource Filament `ParametroInformeResource`

Grupo de navegación: `'Informes y Plantillas'`. Orden: después de `TipoEscalaResource`.
Icono: `heroicon-o-variable`.

**Tabla:**

Columnas: `clave` (con badge de fuente mono), `etiqueta`, `valor` (truncado a 60 chars),
`updated_at`. Sin filtros adicionales. Búsqueda sobre `clave`, `etiqueta` y `descripcion`.

**Formulario:**

```php
TextInput::make('clave')
    ->label('Clave (nombre del tag)')
    ->required()
    ->unique(ignoreRecord: true)
    ->regex('/^[a-z][a-z0-9_]*$/')
    ->helperText('Solo minúsculas, números y guiones bajos. Ejemplo: ciudad, web_municipal')
    ->prefix('{{ ')
    ->suffix(' }}')
    ->columnSpan(1),

TextInput::make('etiqueta')
    ->label('Etiqueta en el editor')
    ->required()
    ->helperText('Texto legible que aparece en el autocompletado del editor.')
    ->columnSpan(1),

Textarea::make('valor')
    ->label('Valor')
    ->required()
    ->rows(2)
    ->columnSpanFull(),

Textarea::make('descripcion')
    ->label('Descripción')
    ->rows(2)
    ->helperText('Para qué se usa este parámetro. Solo visible en el backoffice.')
    ->nullable()
    ->columnSpanFull(),
```

La validación `->regex('/^[a-z][a-z0-9_]*$/')` evita que el administrador cree
claves con espacios, mayúsculas o caracteres especiales que luego no funcionen
en el editor.

**Permisos:** accesible solo a usuarios con rol `adm_sistema`. Los supervisores
pueden ver los parámetros pero no modificarlos.

---

## 8. Actualizar `docs/modulo-documentos.md`

En la sección «3. Servicios / ResolverFuentesInforme» añadir:

> **Variables auxiliares.** Además de los tags contextuales (ciudadano, profesional,
> escalas), el resolver incluye dos categorías adicionales con menor prioridad:
> variables dinámicas de sistema (fecha_hoy, año_actual, mes_actual — calculadas en
> tiempo de ejecución mediante `VariablesDinamicas::resolver()`) y parámetros
> configurables almacenados en `parametros_informe` y gestionados desde el backoffice
> (`ParametroInformeResource`). En caso de colisión de clave, los tags contextuales
> siempre tienen prioridad.

Añadir entrada en la sección «2. Entidades»:

> **ParametroInforme** (`parametros_informe`) — par clave/valor configurable por el
> administrador. Permite crear variables auxiliares en plantillas de informe sin
> modificar código. Los valores se cachean con TTL de 1 hora; la caché se invalida
> automáticamente al guardar o borrar un parámetro. Campos: `clave` (unique),
> `etiqueta`, `valor`, `descripcion`.

---

## 9. Tests

Añadir al fichero de tests del módulo Documentos:

**TF-DOC-22 — Variables dinámicas se resuelven correctamente**

```
Dado: ningún parámetro en BD; informe generado en una fecha conocida.
Cuando: se llama a ResolverFuentesInforme::resolverMergeTags() con un HTML
        que contiene {{ fecha_hoy }} y {{ año_actual }}.
Entonces: {{ fecha_hoy }} se sustituye por la fecha de hoy en formato dd/mm/aaaa;
          {{ año_actual }} se sustituye por el año actual como string de 4 dígitos.
```

**TF-DOC-23 — Parámetro configurable se resuelve en el informe**

```
Dado: un ParametroInforme con clave='ciudad' y valor='Madrid'.
Cuando: se llama a ResolverFuentesInforme::resolverMergeTags() con un HTML
        que contiene {{ ciudad }}.
Entonces: {{ ciudad }} se sustituye por 'Madrid'.
```

**TF-DOC-24 — Tag contextual tiene prioridad sobre parámetro configurable**

```
Dado: un ParametroInforme con clave='nombre_ciudadano' y valor='VALOR_TRAMPA'.
Cuando: se llama a resolverMergeTags() con un HTML que contiene {{ nombre_ciudadano }}
        y el ciudadano del informe se llama 'María López'.
Entonces: {{ nombre_ciudadano }} se sustituye por 'María López', no por 'VALOR_TRAMPA'.
```

**TF-DOC-25 — Clave de parámetro con formato inválido no puede guardarse**

```
Dado: ningún parámetro existente.
Cuando: se intenta crear un ParametroInforme con clave='Mi Ciudad' (contiene espacio).
Entonces: falla la validación; no se crea ningún registro.
```

---

## 10. Ejecución y cierre

```bash
php artisan migrate
php artisan db:seed --class=ParametroInformeSeeder
php artisan test --filter=DocumentosTest
```

Los tests TF-DOC-01 a TF-DOC-21 deben seguir pasando.
Los tests TF-DOC-22 a TF-DOC-25 deben pasar también.
Total esperado: 25 tests, 0 fallos.

**Commit:**
```bash
git add -A
git commit -m "feat(documentos): variables auxiliares dinámicas y parámetros configurables"
git push origin main
```

**CHANGELOG.md:**
- Módulo: Documentos
- Añadido: tabla `parametros_informe` con modelo, caché y seeder; clase
  `VariablesDinamicas` con `fecha_hoy`, `año_actual`, `mes_actual`;
  `ParametroInformeResource` en Filament bajo «Informes y Plantillas»;
  `MergeTagsCatalogo` actualizado con las nuevas categorías; prioridad de
  resolución documentada; 4 tests nuevos (TF-DOC-22 a TF-DOC-25) pasando.

**BACKLOG.md — añadir entrada:**
> **Parámetros configurables por UO** — actualmente los parámetros de `parametros_informe`
> son globales. Variables como `{{ distrito }}` o `{{ nombre_centro }}` podrían necesitar
> valores distintos según la UO del autor del informe. Diseño previsto: añadir
> `unidad_organizativa_id nullable` a `parametros_informe` y aplicar resolución jerárquica
> igual que en `EstiloInforme` — buscar en la UO del autor y subir por la jerarquía hasta
> encontrar valor. Si no hay valor en ningún nivel, usar el parámetro global (sin UO).

---

*Mayo 2026. Prerrequisito: merge tags funcionando (TF-DOC-21 pasando).*
