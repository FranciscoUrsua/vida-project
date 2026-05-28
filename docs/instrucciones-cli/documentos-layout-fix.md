# Instrucciones CLI — Bugfix: layout del formulario de PlantillaInformeResource

> **Presupuesto máximo: 5k tokens.**
> Un único fichero. No abrir nada más.

---

## El problema

El formulario de `PlantillaInformeResource` tiene un layout de dos columnas que
comprime el `RichEditor` del campo `contenido_plantilla` a una columna estrecha
con una sola fila visible. El editor debe ocupar el ancho completo del panel.

---

## Fichero a modificar

**Único fichero:** `app/Filament/Resources/PlantillaInformeResource.php`

---

## Cambios

### 1. Layout general del formulario: de dos columnas a una

Localizar la definición del `Schema` o `Form` del resource. Si tiene `->columns(2)`
o `Grid::make(2)` como wrapper del formulario completo, cambiarlo a `->columns(1)`.

Si el layout de dos columnas está en un `Section` o `Grid` que envuelve tanto los
campos de cabecera (nombre, descripción, tipo, UO) como el editor de secciones,
separarlo en dos bloques distintos:

```php
// Bloque 1: datos generales — dos columnas está bien aquí
Section::make('Datos generales')
    ->columns(2)
    ->schema([
        TextInput::make('nombre')->columnSpan(1),
        Select::make('tipo_informe')->columnSpan(1),
        Select::make('unidad_organizativa_id')->columnSpan(1),
        Toggle::make('activa')->columnSpan(1),
        Textarea::make('descripcion')->columnSpanFull(),
    ]),

// Bloque 2: secciones — ancho completo
Section::make('Secciones del informe')
    ->columnSpanFull()
    ->schema([
        // aquí el Builder o Repeater de secciones
    ]),
```

### 2. Campo `contenido_plantilla`: ancho completo y altura mínima

Localizar el `RichEditor::make('contenido_plantilla')` dentro del bloque de
tipo `texto_libre` y asegurarse de que tiene:

```php
RichEditor::make('contenido_plantilla')
    ->columnSpanFull()   // ocupa el ancho completo de su contenedor
    // resto de configuración sin cambios
```

Si el Repeater o Builder interior de secciones tiene `->columns(2)` propio,
cambiarlo a `->columns(1)`.

---

## Verificación

1. Abrir la pantalla de crear o editar una `PlantillaInforme` en Filament.
2. Comprobar que los campos de cabecera (nombre, tipo, UO) siguen en dos columnas.
3. Comprobar que el bloque de secciones ocupa el ancho completo del panel.
4. Añadir una sección de tipo `texto_libre` y comprobar que el `RichEditor`
   ocupa el ancho completo y muestra al menos 4-5 filas de altura visibles.

No hay tests que ejecutar. No hay commit de tests.

---

## Commit

```bash
git add app/Filament/Resources/PlantillaInformeResource.php
git commit -m "fix(documentos): editor de secciones a ancho completo en PlantillaInformeResource"
git push origin main
```

---

*Mayo 2026.*
