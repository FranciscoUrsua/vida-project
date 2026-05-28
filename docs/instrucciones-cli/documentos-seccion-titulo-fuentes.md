# Instrucciones CLI — Bugfix: título de sección y selector de fuentes en PlantillaInformeResource

> **Presupuesto máximo: 10k tokens.**
> Un único fichero. No abrir nada más.

---

## Dos problemas, mismo fichero

**Fichero:** `app/Filament/Resources/PlantillaInformeResource.php`

---

## Problema 1 — El título no aparece en la lista de secciones

### Causa

El Repeater o Builder de secciones no tiene `->itemLabel()` configurado,
o lo tiene apuntando a un campo que no existe o está vacío en ese momento.

### Corrección

Localizar el Repeater o Builder que gestiona las secciones y añadir o
corregir `->itemLabel()`:

**Si es un `Repeater`:**
```php
Repeater::make('secciones')
    ->itemLabel(fn (array $state): ?string =>
        filled($state['titulo'] ?? null)
            ? $state['titulo']
            : 'Nueva sección'
    )
    // resto sin cambios
```

**Si es un `Builder`:**
```php
Builder\Block::make('seccion')
    ->label(fn (array $state): string =>
        filled($state['titulo'] ?? null)
            ? $state['titulo']
            : 'Nueva sección'
    )
    // resto sin cambios
```

Adicionalmente, para que el label se actualice en tiempo real mientras
el usuario escribe el título, añadir `->live(onBlur: true)` al campo
`TextInput::make('titulo')` dentro del schema de la sección:

```php
TextInput::make('titulo')
    ->label('Título de la sección')
    ->required()
    ->live(onBlur: true),  // refresca el itemLabel al salir del campo
```

---

## Problema 2 — El campo «fuente de datos» no propone opciones

### Causa

El campo `fuente` en las secciones de tipo `automatico` está implementado
como `TextInput` de texto libre, cuando debería ser un `Select` con las
fuentes disponibles definidas como opciones fijas.

### Corrección

Localizar el campo `fuente` dentro del bloque/schema de tipo `automatico`
y reemplazarlo por un `Select` con el catálogo de fuentes disponibles:

```php
Select::make('fuente')
    ->label('Fuente de datos')
    ->required()
    ->options([
        // Ciudadano
        'ciudadano.datos_basicos'        => 'Ciudadano — Datos básicos (nombre, NIF, fecha nacimiento, dirección)',
        'ciudadano.datos_contacto'       => 'Ciudadano — Datos de contacto (teléfono, email)',
        'ciudadano.unidad_convivencia'   => 'Ciudadano — Unidad de convivencia',

        // Historia Social
        'historia_social.resumen'                => 'Historia Social — Resumen y motivo de apertura',
        'historia_social.prestaciones_activas'   => 'Historia Social — Prestaciones activas del plan vigente',
        'historia_social.prestaciones_historico' => 'Historia Social — Historial completo de prestaciones',
        'historia_social.plan_activo'            => 'Historia Social — Plan de intervención activo (objetivos)',

        // Escalas de valoración
        'escalas.barthel_ultimo'         => 'Escalas — Último pase Barthel (score e interpretación)',
        'escalas.pfeiffer_ultimo'        => 'Escalas — Último pase Pfeiffer SPMSQ (score e interpretación)',
        'escalas.lawton_ultimo'          => 'Escalas — Último pase Lawton-Brody (score e interpretación)',
        'escalas.historico_barthel'      => 'Escalas — Histórico de pases Barthel',

        // Profesional
        'profesional.datos'              => 'Profesional — Datos del autor (nombre, cargo, colegiado, centro)',
    ])
    ->searchable()
    ->native(false),
```

`->searchable()` permite al supervisor filtrar las opciones escribiendo,
útil cuando la lista crece. `->native(false)` usa el selector de Filament
en lugar del `<select>` nativo del navegador, que es más usable.

### Nota sobre la visibilidad condicional

El campo `fuente` solo debe aparecer cuando el tipo de sección es `automatico`.
Si ya tiene visibilidad condicional (`->visible()` o `->hidden()`), no cambiarla.
Si no la tiene, añadirla para evitar que aparezca en secciones `texto_libre`:

```php
->visible(fn (Get $get): bool => $get('tipo') === 'automatico')
```

---

## Verificación

1. Abrir la pantalla de editar una `PlantillaInforme` existente.
2. Comprobar que cada sección muestra su título en el header del bloque
   colapsado. Las secciones sin título deben mostrar «Nueva sección».
3. Escribir en el campo Título de una sección y salir del campo (blur):
   el header del bloque debe actualizarse con el nuevo texto.
4. Añadir una sección de tipo `automatico`: el campo «Fuente de datos»
   debe mostrar un `Select` con las opciones del catálogo, con buscador.
5. Añadir una sección de tipo `texto_libre`: el campo «Fuente de datos»
   no debe aparecer.

No hay tests que ejecutar.

---

## Commit

```bash
git add app/Filament/Resources/PlantillaInformeResource.php
git commit -m "fix(documentos): título de sección en lista y selector de fuentes automáticas"
git push origin main
```

---

*Mayo 2026.*
