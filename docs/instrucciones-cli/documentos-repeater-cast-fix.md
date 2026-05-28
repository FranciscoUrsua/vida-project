# Instrucciones CLI — Bugfix: Repeater recibe string en PlantillaInformeResource

> **Presupuesto máximo: 8k tokens.**
> Dos ficheros como máximo. No abrir vendors. No buscar en otros módulos.

---

## El error

```
ErrorException
vendor/filament/forms/src/Components/Repeater.php:828
foreach() argument must be of type array|object, string given
```

Se produce al intentar editar una `PlantillaInforme` recién creada
(GET `.../admin/plantilla-informes/2/edit`).

---

## Causa (diagnóstico hecho — no investigar)

El campo `secciones` llega al Repeater como string JSON (`"[{\"id\":...}]"`)
en lugar de como array PHP (`[['id' => ...]]`). Hay dos causas posibles,
verificar en este orden:

---

## Verificación 1 — Cast en el modelo `PlantillaInforme`

**Fichero:** `Modules/Documentos/app/Models/PlantillaInforme.php`

Comprobar que el campo `secciones` tiene cast a `array`:

```php
protected $casts = [
    'secciones' => 'array',
    // ...
];
```

Si el cast **no existe o es `'json'` en lugar de `'array'`**: corregirlo a `'array'`.
El cast `'array'` garantiza que Eloquent deserializa el JSON a array PHP al leer,
y serializa el array a JSON al escribir. Sin él, el campo llega al Repeater como string.

Si el cast **ya existe y es correcto**: pasar a la Verificación 2.

---

## Verificación 2 — `dehydrateStateUsing` devuelve array, no string

**Fichero:** `app/Filament/Resources/PlantillaInformeResource.php`

Si el formulario usa `dehydrateStateUsing` en el campo `secciones` (o en el
Builder/Repeater que lo gestiona), comprobar que devuelve un **array PHP**,
no un string JSON:

```php
// MAL — devuelve string:
->dehydrateStateUsing(fn ($state) => json_encode($state))

// BIEN — devuelve array:
->dehydrateStateUsing(fn ($state) => $state)
// o si hay transformación:
->dehydrateStateUsing(fn ($state) => [...])
```

Eloquent + el cast `'array'` del modelo se encargan de serializar a JSON al persistir.
Si `dehydrateStateUsing` serializa manualmente con `json_encode`, el modelo recibe
un string, lo serializa de nuevo, y queda doble-codificado (`"\"[{...}]\""`) o
directamente como string sin cast.

---

## Corrección esperada

Con el cast `'array'` correcto en el modelo y sin `json_encode` manual en el
resource, el ciclo completo funciona así:

```
Filament guarda  → dehydrate devuelve array PHP
                 → Eloquent serializa a JSON por el cast
                 → BD almacena: [{"id":"sec_1",...}]

Filament carga   → Eloquent deserializa JSON a array PHP por el cast
                 → Repeater/Builder recibe array PHP
                 → foreach() funciona correctamente
```

---

## Verificación final

1. Crear una nueva `PlantillaInforme` con al menos una sección.
2. Guardar.
3. Volver a abrir para editar — no debe aparecer el `ErrorException`.
4. Comprobar en BD (o con `PlantillaInforme::find(2)->getRawOriginal('secciones')`)
   que el valor almacenado es JSON válido (array), no un string doblemente codificado.

No hay tests que ejecutar. No hay cambios de lógica.

---

## Commit

```bash
git add Modules/Documentos/app/Models/PlantillaInforme.php
# Si también se tocó el resource:
git add app/Filament/Resources/PlantillaInformeResource.php
git commit -m "fix(documentos): cast array en secciones de PlantillaInforme corrige Repeater"
git push origin main
```

---

*Mayo 2026.*
