# Instrucciones CLI — Documentos: editor con merge tags en PlantillaInformeResource

> Leer este fichero íntegramente antes de tocar cualquier fichero.
> Prerrequisito: módulo Documentos implementado, 20/20 tests pasando.
> Referencia de diseño: `docs/modulo-documentos.md`.

---

## Contexto y alcance

El módulo Documentos está implementado y los 20 tests pasan. Esta sesión mejora
exclusivamente la experiencia de edición de plantillas de informe en Filament.

El problema actual: las secciones de tipo `texto_libre` en `PlantillaInformeResource`
tienen un campo `instrucciones` de texto plano donde el supervisor escribe las
instrucciones para el profesional. No hay ningún mecanismo para que el supervisor
sepa qué variables dinámicas puede incorporar en el contenido del informe, ni
ninguna forma cómoda de insertarlas. El supervisor tiene que saber de memoria que
existe `{{ nombre_ciudadano }}` o escribir el JSON a mano, lo cual es impracticable.

La solución: añadir un campo `contenido_plantilla` con `RichEditor` y `mergeTags`
nativo de Filament v4 a las secciones de tipo `texto_libre`. Este campo permite al
supervisor redactar el texto base de la sección e insertar variables dinámicas
con autocompletado al escribir `{{` o desde el botón «Insertar variable» de la toolbar.
Las variables se sustituyen con datos reales del ciudadano en el momento de generar
el informe.

**Ficheros afectados:**
- `app/Filament/Resources/PlantillaInformeResource.php` — campo principal
- `docs/modulo-documentos.md` — actualizar estructura del campo `secciones`
- `Modules/Documentos/app/Services/ResolverFuentesInforme.php` — soporte del nuevo campo

**Tests afectados:** ninguno roto. Se añade un test nuevo (TF-DOC-21).

**No se toca:** modelos, migraciones, ni ningún otro recurso Filament.

---

## 1. Catálogo de variables (merge tags)

Definir el catálogo como un array PHP estático en una clase dedicada para que
sea reutilizable tanto en el `RichEditor` del backoffice como en el servicio de
resolución que sustituye los valores al generar el informe.

**Ubicación:** `Modules/Documentos/app/Support/MergeTagsCatalogo.php`

```php
<?php

namespace Modules\Documentos\Support;

/**
 * Catálogo centralizado de merge tags disponibles en plantillas de informe.
 * Las claves son los identificadores que se insertan en el contenido ({{ clave }}).
 * Los valores son las etiquetas legibles que se muestran en el editor de Filament.
 *
 * Al añadir un nuevo tag aquí también hay que implementar su resolución
 * en ResolverFuentesInforme::resolverMergeTag().
 */
class MergeTagsCatalogo
{
    /**
     * Devuelve el array de merge tags en el formato que espera RichEditor::mergeTags().
     * Formato: ['clave' => 'Etiqueta legible'].
     *
     * @return array<string, string>
     */
    public static function todos(): array
    {
        return [
            // Ciudadano
            'nombre_ciudadano'        => 'Ciudadano — Nombre completo',
            'fecha_nacimiento'        => 'Ciudadano — Fecha de nacimiento',
            'edad'                    => 'Ciudadano — Edad',
            'nie_nif'                 => 'Ciudadano — Documento identificativo',
            'direccion'               => 'Ciudadano — Dirección de empadronamiento',
            'telefono'                => 'Ciudadano — Teléfono de contacto',

            // Expediente
            'numero_expediente'       => 'Expediente — Número',
            'fecha_apertura'          => 'Expediente — Fecha de apertura',
            'motivo_demanda'          => 'Expediente — Motivo de la demanda',

            // Valoración
            'fecha_valoracion'        => 'Valoración — Fecha de la última valoración',
            'score_barthel'           => 'Valoración — Puntuación escala Barthel',
            'interpretacion_barthel'  => 'Valoración — Interpretación escala Barthel',
            'score_pfeiffer'          => 'Valoración — Puntuación escala Pfeiffer',
            'interpretacion_pfeiffer' => 'Valoración — Interpretación escala Pfeiffer',
            'score_lawton'            => 'Valoración — Puntuación escala Lawton-Brody',
            'interpretacion_lawton'   => 'Valoración — Interpretación escala Lawton-Brody',

            // Plan de intervención
            'lista_prestaciones'      => 'Plan — Prestaciones del plan activo',
            'fecha_inicio_plan'       => 'Plan — Fecha de inicio del plan activo',
            'objetivos_plan'          => 'Plan — Objetivos del plan activo',

            // Profesional y centro
            'nombre_profesional'      => 'Profesional — Nombre del TSR autor',
            'cargo_profesional'       => 'Profesional — Cargo',
            'numero_colegiado'        => 'Profesional — Número de colegiación',
            'nombre_centro'           => 'Centro — Nombre del centro',
            'direccion_centro'        => 'Centro — Dirección del centro',
            'telefono_centro'         => 'Centro — Teléfono del centro',

            // Informe
            'fecha_informe'           => 'Informe — Fecha de generación',
        ];
    }

    /**
     * Devuelve solo las claves, para validación.
     *
     * @return array<string>
     */
    public static function claves(): array
    {
        return array_keys(self::todos());
    }
}
```

---

## 2. Cambio en `PlantillaInformeResource`

### 2.1 Añadir el campo `contenido_plantilla` a los bloques de tipo `texto_libre`

El formulario de `PlantillaInformeResource` ya usa un `Builder` (o `Repeater`) para
editar el campo `secciones`. Dentro del bloque de tipo `texto_libre`, añadir el campo
`contenido_plantilla` con `RichEditor` y merge tags.

Localizar el bloque `texto_libre` y añadir el campo después del campo `instrucciones`:

```php
use Filament\Forms\Components\RichEditor;
use Modules\Documentos\Support\MergeTagsCatalogo;

// Dentro del bloque/schema de tipo 'texto_libre':

RichEditor::make('contenido_plantilla')
    ->label('Contenido base de la sección')
    ->hint('Escribe {{ para insertar una variable dinámica, o usa el botón «Insertar variable» de la barra de herramientas.')
    ->hintIcon('heroicon-o-information-circle')
    ->mergeTags(MergeTagsCatalogo::todos())
    ->toolbarButtons([
        ['bold', 'italic', 'underline', 'strike'],
        ['h2', 'h3'],
        ['bulletList', 'orderedList', 'blockquote'],
        ['undo', 'redo'],
        // El botón 'mergeTags' se añade automáticamente al usar ->mergeTags()
    ])
    ->nullable()
    ->columnSpanFull(),
```

El campo es `nullable` porque no todas las secciones de texto libre necesitan
contenido base — el supervisor puede dejarla vacía y que el profesional redacte
todo desde cero al generar el informe.

### 2.2 Distinción visual entre tipos de sección

Las secciones de tipo `automatico` no deben mostrar el `RichEditor` de contenido
(son datos pre-cargados desde la Historia Social, no redactables). Asegurarse de
que el campo `contenido_plantilla` solo aparece en bloques de tipo `texto_libre`.

Si el Builder ya separa los tipos en bloques distintos, esto es automático.
Si usa un Repeater con campo de tipo dinámico, añadir visibilidad condicional:

```php
->visible(fn (Get $get): bool => $get('tipo') === 'texto_libre')
```

---

## 3. Actualizar la estructura del campo `secciones` en el modelo

El campo `secciones` del modelo `PlantillaInforme` ahora incluye el subcampo
`contenido_plantilla` en las secciones de tipo `texto_libre`. Actualizar el
PHPDoc del modelo para reflejar la nueva estructura:

```php
/**
 * Estructura del campo secciones (jsonb):
 *
 * Sección de tipo automatico:
 * {
 *   "id": "datos_ciudadano",
 *   "titulo": "Datos del ciudadano",
 *   "tipo": "automatico",
 *   "fuente": "ciudadano.datos_basicos",
 *   "editable": false
 * }
 *
 * Sección de tipo texto_libre:
 * {
 *   "id": "situacion_actual",
 *   "titulo": "Situación actual",
 *   "tipo": "texto_libre",
 *   "instrucciones": "Describa la situación actual...",
 *   "contenido_plantilla": "<p>En relación a {{ nombre_ciudadano }}...</p>",
 *   "obligatorio": true
 * }
 *
 * El campo contenido_plantilla almacena HTML con nodos de merge tag de TipTap.
 * Se procesa en RenderizadorInforme::resolverMergeTags() al generar el PDF.
 */
```

No hay migración: `secciones` ya es JSONB y admite el nuevo subcampo sin cambios
en la base de datos.

---

## 4. Actualizar `ResolverFuentesInforme`

El servicio `ResolverFuentesInforme` necesita un método para sustituir los merge tags
en el `contenido_plantilla` de una sección, dado el contexto de un informe concreto
(ciudadano, historia social, profesional, fecha).

Añadir el método `resolverMergeTags()` al servicio existente:

```php
use Modules\Documentos\Support\MergeTagsCatalogo;

/**
 * Sustituye los merge tags en el contenido HTML de una sección de plantilla,
 * devolviendo el HTML con los valores reales del informe.
 *
 * @param  string       $html           HTML con tags {{ clave }} de TipTap
 * @param  int          $ciudadanoId
 * @param  int          $profesionalId
 * @param  Carbon       $fechaInforme
 * @return string                       HTML con valores sustituidos
 */
public function resolverMergeTags(
    string $html,
    int $ciudadanoId,
    int $profesionalId,
    Carbon $fechaInforme
): string {
    $valores = $this->construirMapaValores($ciudadanoId, $profesionalId, $fechaInforme);

    // TipTap almacena los merge tags como nodos con data-type="mergeTag" y data-id="clave"
    // Al renderizar como HTML, quedan como {{ clave }} en el texto.
    foreach ($valores as $clave => $valor) {
        $html = str_replace('{{ ' . $clave . ' }}', e((string) $valor), $html);
        $html = str_replace('{{' . $clave . '}}', e((string) $valor), $html);
    }

    return $html;
}

/**
 * Construye el mapa completo de clave → valor para un informe concreto.
 * Todas las claves de MergeTagsCatalogo::claves() deben tener entrada aquí.
 *
 * @return array<string, string>
 */
private function construirMapaValores(
    int $ciudadanoId,
    int $profesionalId,
    Carbon $fechaInforme
): array {
    $ciudadano    = Ciudadano::with(['historiaSocial.planActivo.prestaciones'])->findOrFail($ciudadanoId);
    $profesional  = Usuario::with('centro')->findOrFail($profesionalId);
    $historia     = $ciudadano->historiaSocial;
    $planActivo   = $historia?->planActivo;
    $ultimoBarthe = $historia?->pasesEscala()
                             ->where('tipo_escala_id', TipoEscala::codigoId('barthel'))
                             ->completados()
                             ->latest('fecha')
                             ->first();

    return [
        // Ciudadano
        'nombre_ciudadano'        => $ciudadano->nombre_completo,
        'fecha_nacimiento'        => $ciudadano->fecha_nacimiento?->format('d/m/Y') ?? '—',
        'edad'                    => $ciudadano->edad ?? '—',
        'nie_nif'                 => $ciudadano->nie_nif ?? '—',
        'direccion'               => $ciudadano->direccion_completa ?? '—',
        'telefono'                => $ciudadano->telefono ?? '—',

        // Expediente
        'numero_expediente'       => $historia?->numero_expediente ?? '—',
        'fecha_apertura'          => $historia?->created_at->format('d/m/Y') ?? '—',
        'motivo_demanda'          => $historia?->motivo_demanda ?? '—',

        // Valoración — Barthel (si no hay pase, mostrar '—')
        'fecha_valoracion'        => $ultimoBarthe?->fecha->format('d/m/Y') ?? '—',
        'score_barthel'           => $ultimoBarthe?->score_total ?? '—',
        'interpretacion_barthel'  => $ultimoBarthe?->interpretacion_codigo ?? '—',
        'score_pfeiffer'          => $this->ultimoScore($historia, 'pfeiffer_spmsq'),
        'interpretacion_pfeiffer' => $this->ultimaInterpretacion($historia, 'pfeiffer_spmsq'),
        'score_lawton'            => $this->ultimoScore($historia, 'lawton_brody'),
        'interpretacion_lawton'   => $this->ultimaInterpretacion($historia, 'lawton_brody'),

        // Plan
        'lista_prestaciones'      => $this->formatearPrestaciones($planActivo),
        'fecha_inicio_plan'       => $planActivo?->fecha_inicio->format('d/m/Y') ?? '—',
        'objetivos_plan'          => $planActivo?->objetivos ?? '—',

        // Profesional y centro
        'nombre_profesional'      => $profesional->nombre_completo,
        'cargo_profesional'       => $profesional->cargo ?? '—',
        'numero_colegiado'        => $profesional->numero_colegiado ?? '—',
        'nombre_centro'           => $profesional->centro?->nombre ?? '—',
        'direccion_centro'        => $profesional->centro?->direccion ?? '—',
        'telefono_centro'         => $profesional->centro?->telefono ?? '—',

        // Informe
        'fecha_informe'           => $fechaInforme->format('d/m/Y'),
    ];
}
```

Añadir también los helpers privados usados arriba:

```php
private function ultimoScore(?HistoriaSocial $historia, string $codigoEscala): string
{
    if (!$historia) return '—';
    $pase = $historia->pasesEscala()
        ->where('tipo_escala_id', TipoEscala::codigoId($codigoEscala))
        ->completados()
        ->latest('fecha')
        ->first();
    return $pase?->score_total ?? '—';
}

private function ultimaInterpretacion(?HistoriaSocial $historia, string $codigoEscala): string
{
    if (!$historia) return '—';
    $pase = $historia->pasesEscala()
        ->where('tipo_escala_id', TipoEscala::codigoId($codigoEscala))
        ->completados()
        ->latest('fecha')
        ->first();
    return $pase?->interpretacion_codigo ?? '—';
}

private function formatearPrestaciones(?PlanDeIntervencion $plan): string
{
    if (!$plan) return '—';
    return $plan->prestaciones
        ->pluck('nombre')
        ->join(', ') ?: '—';
}
```

**Nota sobre `TipoEscala::codigoId()`:** si este scope estático no existe en el modelo
`TipoEscala`, añadirlo:

```php
/**
 * Devuelve el id del TipoEscala con el código dado, desde caché.
 *
 * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
 */
public static function codigoId(string $codigo): int
{
    return Cache::remember(
        "tipo_escala_id_{$codigo}",
        now()->addDay(),
        fn () => static::where('codigo', $codigo)->firstOrFail()->id
    );
}
```

---

## 5. Actualizar `docs/modulo-documentos.md`

En la sección «2.3 PlantillaInforme», actualizar la estructura del campo `secciones`
para incluir `contenido_plantilla` en las secciones de tipo `texto_libre`:

```json
{
  "id": "situacion_actual",
  "titulo": "Situación actual",
  "tipo": "texto_libre",
  "instrucciones": "Describa la situación actual de la persona...",
  "contenido_plantilla": "<p>En relación a {{ nombre_ciudadano }}, con expediente n.º {{ numero_expediente }}...</p>",
  "obligatorio": true
}
```

Añadir una nota en la sección de decisiones:

> **Merge tags en plantillas de texto libre.** Las secciones de tipo `texto_libre`
> pueden incorporar variables dinámicas mediante merge tags (sintaxis `{{ clave }}`).
> El catálogo de variables disponibles está centralizado en
> `Modules\Documentos\Support\MergeTagsCatalogo`. La sustitución se realiza en
> `ResolverFuentesInforme::resolverMergeTags()` al generar el PDF. Las variables de
> escalas de valoración (Barthel, Pfeiffer, Lawton-Brody) se resuelven buscando
> el pase completado más reciente de cada instrumento en la Historia Social del ciudadano.

---

## 6. Test nuevo: TF-DOC-21

Añadir al fichero de tests del módulo Documentos:

**TF-DOC-21 — Los merge tags se sustituyen correctamente al generar el contenido**

```
Dado: un Ciudadano con nombre «María López» y número de expediente «EXP-2026-001»;
      un PaseEscala completado de Barthel con score_total=75 e interpretacion_codigo='moderada';
      un PlantillaInforme con una sección texto_libre cuyo contenido_plantilla contiene
      '<p>D./Dña. {{ nombre_ciudadano }}, expediente {{ numero_expediente }}, Barthel: {{ score_barthel }}.</p>'.

Cuando: se llama a ResolverFuentesInforme::resolverMergeTags() con ese HTML
        y el ciudadano_id y profesional_id correspondientes.

Entonces: el HTML resultante contiene «María López», «EXP-2026-001» y «75»;
          no contiene ningún tag sin sustituir de la forma {{ ... }}.
```

Añadir TF-DOC-21 a la tabla de tests del módulo con estado ⬜ hasta que pase.

---

## 7. Lo que NO debes hacer en esta sesión

- No modificar la estructura de la tabla `plantillas_informe` ni crear migraciones.
- No tocar `NuevoInformeWizard` ni ningún componente Livewire. El wizard ya consume
  el campo `contenido_plantilla` a través del servicio; el cambio en el servicio es
  suficiente para que funcione end-to-end.
- No cambiar los 20 tests existentes del módulo.
- No añadir más variables al catálogo de las especificadas. Si al implementar
  `construirMapaValores()` alguna relación no está disponible en el modelo actual,
  devolver `'—'` y anotarlo en el BACKLOG como variable pendiente de implementar.

---

## 8. Verificación

```bash
php artisan test --filter=DocumentosTest
```

Los 20 tests originales deben seguir pasando. El test TF-DOC-21 debe pasar también.
Total esperado: 21 tests pasando, 0 fallos.

Verificación manual adicional:

1. Abrir en Filament la pantalla de creación de una `PlantillaInforme`.
2. Añadir una sección de tipo `texto_libre`.
3. Comprobar que aparece el `RichEditor` con la barra de herramientas.
4. Escribir `{{` y verificar que aparece el autocompletado con las variables del catálogo.
5. Escribir `{{nom` y verificar que el autocompletado filtra a las variables que contienen «nom».
6. Seleccionar `nombre_ciudadano` y verificar que se inserta como chip en el editor.
7. Guardar la plantilla y recargar — verificar que el contenido persiste correctamente.
8. Comprobar que en secciones de tipo `automatico` el `RichEditor` no aparece.

---

## 9. Cierre de sesión

Seguir el protocolo estándar de `CLAUDE.md` sección 4.

**CHANGELOG.md** — añadir entrada con:
- Fecha de la sesión
- Módulo: Documentos
- Cambios: clase `MergeTagsCatalogo` creada con 26 variables agrupadas por categoría;
  campo `contenido_plantilla` con `RichEditor` y `mergeTags` añadido a las secciones
  `texto_libre` de `PlantillaInformeResource`; método `resolverMergeTags()` y helpers
  privados añadidos a `ResolverFuentesInforme`; método `TipoEscala::codigoId()` añadido
  si no existía; `docs/modulo-documentos.md` actualizado; test TF-DOC-21 añadido y pasando
- Decisiones de implementación tomadas que no estaban en las instrucciones (p.ej.
  variables con relación no disponible que devuelven '—' y se han anotado en BACKLOG)

**BACKLOG.md** — añadir una entrada por cada variable del catálogo cuya relación
no estaba disponible en los modelos actuales y que devuelve '—' provisionalmente.

**SESSION.md** — actualizar con:
- Tarea completada: «Documentos — merge tags en editor de plantillas de informe»
- Siguiente paso recomendado: el que indique el estado actual del proyecto

**Commit:**
```bash
git add -A
git commit -m "feat(documentos): merge tags en editor de plantillas de informe"
git push origin main
```

---

*Instrucciones preparadas: mayo 2026. Prerrequisito: módulo Documentos 20/20 tests pasando.*
