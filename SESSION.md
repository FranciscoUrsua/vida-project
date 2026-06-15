# SESSION — VIDA 360

_Actualizado: 2026-06-15_

## Tarea completada

`TipoFichaResource` — Fichas de Valoración en Filament (5 pasos de `tipo-ficha-implementacion.md`).

## Estado actual

### Cambios aplicados en esta sesión

**Modelo TipoFicha**
- `const TIPOS_CAMPO = ['texto', 'numero', 'select', 'booleano', 'fecha', 'escala']`.
- `fichas(): HasMany` — relación con fichas cumplimentadas.
- `tieneFichasAsociadas()` — detecta fichas reales asociadas.
- `booted()` `saving` event → `validarSchema()` (estructura, tipos, opciones select, tipo_escala_id, ids únicos, inmutabilidad).
- `TipoFichaFactory` actualizado al formato canónico con clave `campos`.

**TipoFichaResource (Filament)**
- Grupo «Informes y Plantillas», sort 3.
- Tabla con columna num_campos calculada, toggle activo, filtro ternario.
- `DeleteAction` condicional (`! tieneFichasAsociadas()`).
- Formulario con Builder (6 bloques tipados) + `afterStateHydrated`/`dehydrateStateUsing`.
- Placeholder de inmutabilidad cuando hay fichas asociadas.
- Páginas con manejo de `ValidationException`.

**TipoEscalaResource**: sort ajustado de 5 a 4.

**Seeders**: `IntervencionFichaSeeder` (3 fichas nuevas en formato canónico). `IntervencionSeeder` refactorizado para llamarlo.

**Tests TF-INT-H01 a H10**: todos en verde. Suite completa Intervención: 134 pasando + 1 incompleto (Agenda, esperado).

### TODOs documentados en código (sin cambios)
- `CiudadanoPage::statPrestaciones()`: integrar con módulo Prestaciones.
- Modal "Ver historial completo" de accesos (enlace "Ver todo").
- Route PISO show (Entrega 4).
- Menú ⋯ con acciones del expediente.
- `unidades_convivencia` (tabla pendiente).

## Siguiente paso recomendado

1. **Modal "Ver historial completo" de accesos** — el enlace "Ver todo" existe pero apunta a `#`.
2. **Integrar `statPrestaciones`** con el módulo Prestaciones cuando esté disponible.
3. **Flujo de autorización de colectivos protegidos** — AccesoProtegido (Módulo Ciudadanía) pendiente.
4. **PISO/plan detail page** (Entrega 4).

## Contexto técnico para retomar

### Schema TipoFicha — formato canónico
```json
{
  "campos": [
    {"id": "...", "tipo": "texto|numero|select|booleano|fecha|escala",
     "etiqueta": "...", "descripcion": null, "obligatorio": false, "orden": 1}
  ]
}
```
- `select` → campo adicional `opciones` (array de strings, mín. 2).
- `numero` → campo adicional `unidad` (string nullable).
- `escala` → campo adicional `tipo_escala_id` (int FK a tipo_escalas).

### Livewire 4 — restricciones consolidadas
- `livewire:updated` no existe. Usar `Livewire.hook('morphed', cb)` tras `livewire:initialized`.
- Full-page components: `mount()` solo recibe parámetros de ruta, no query string. Leer con `request()->query('param')` directamente.
- `redirect()` en un componente devuelve `Livewire\Features\SupportRedirects\Redirector`. Usar `$this->redirect(route(...))` con retorno `void`.
- `wire:model` es diferido. Usar `wire:model.live` cuando el re-render inmediato es necesario.

### Layout CiudadanoPage
- `.ciudadano-layout`: `1fr 2fr` (columna ciudadano 1/3, herramientas 2/3).
- Toolbox: 4 columnas fijas, labels siempre visibles, estado activo solo por color.
- Filtros: 8 pills con estado activo (fondo sólido) y sugerido (solo borde).
- Modal: z-index 500 (sobre todo el layout). Alpine para cierre con Escape.
