# SESSION — VIDA 360

_Actualizado: 2026-06-16_

## Tarea completada

Modal de gestión de UC en `CiudadanoPage` (Livewire).

## Estado actual

### Cambios aplicados en esta sesión

**CiudadanoPage.php**
- Nuevas propiedades: `$modalUcAbierto`, `$ucBusqueda`, `$ucMiembroParaBaja`,
  `$ucCiudadanoSeleccionado`, `$ucMensaje`.
- Nuevas propiedades computadas: `ucVigente()`, `ucMiembrosActivos()`, `ucResultadosBusqueda()`.
- Nuevos métodos: `abrirModalUc`, `cerrarModalUc`, `seleccionarCiudadanoUc`,
  `confirmarAnadirMiembro`, `cancelarSeleccionUc`, `iniciarBajaMiembro`,
  `confirmarBajaMiembro`, `cancelarBajaMiembro`, `verificarMiembro`, `crearUc`.
- Corrección aplicada en `crearUc()`: usa `$this->ciudadano->direccion_texto`,
  `coordenadas_lat`, `coordenadas_lng` (los campos reales de Ciudadano, no `domicilio`/`latitud`/`longitud`).
- Corrección aplicada en `ucResultadosBusqueda()`: usa `withoutGlobalScope(AmbitoUoScope::class)`
  para permitir buscar ciudadanos sin HistoriaSocial en el UO del usuario.

**ciudadano-page.blade.php**
- Widget UC (columna izquierda) reemplazado: muestra miembros activos con icono
  verificado/sin-verificar + botón "Gestionar UC" (`wire:click="abrirModalUc"`).
- Modal de gestión UC añadido antes del cierre del componente raíz, al nivel de los
  modales existentes. Cierre con Escape vía Alpine `x-on:keydown.escape.window`.

**app-operativo.css**
- Bloque completo de estilos UC: backdrop, modal, miembros, badges, botones utilitarios,
  búsqueda con resultados, confirmación de adición, botón del widget.

**Tests TF-LW-UC-01 a TF-LW-UC-13**: todos en verde (13/13, 19 assertions).
- Setup sigue el patrón de CiudadanoPageTest: seed roles, user con rol 'intervencion',
  UsuarioUo, Livewire::actingAs().

### TODOs documentados en código (sin cambios)
- `fichas.valoracion_id` nullable y `historia_id` directa es solución provisional.
  TODO: vincular Ficha → Valoracion cuando ese flujo esté completo.
- `CiudadanoPage::statPrestaciones()`: integrar con módulo Prestaciones.
- Modal "Ver historial completo" de accesos (enlace "Ver todo" apunta a `#`).
- Route PISO show (Entrega 4).
- Alta rápida de ciudadano desde modal UC con contexto prerellenado → BACKLOG.

## Siguiente paso recomendado

1. **Fichas sociales / Formulario de valoración estructurado** — el schema de fichas
   ya existe (TipoFicha con schema JSON). Implementar el formulario dinámico completo
   para rellenar fichas dentro del flujo de intervención (bloquea el PISO completo).
2. **Modal "Ver historial completo" de accesos** — el enlace "Ver todo" existe pero apunta a `#`.
3. **Vincular Ficha → Valoracion** — `valoracion_id NOT NULL` de nuevo, con
   `Valoracion::firstOrCreate` en `guardar()`.
4. **PISO/plan detail page** (Entrega 4).

## Contexto técnico para retomar

### ucResultadosBusqueda — decisión clave
Usa `Ciudadano::withoutGlobalScope(AmbitoUoScope::class)` para permitir buscar
ciudadanos sin HistoriaSocial en la UO del usuario. Esto es correcto: al añadir
miembros a una UC de convivencia familiar, puede haber personas cuya HS esté en
otra UO (custodia compartida, domicilios múltiples).

### Campos de Ciudadano (vs UnidadConvivencia)
- `Ciudadano.direccion_texto` (cifrado) — se usa como `domicilio` al crear la UC
- `Ciudadano.coordenadas_lat`, `coordenadas_lng` — se usan como `latitud`/`longitud` en UC
- `UnidadConvivencia.domicilio`, `latitud`, `longitud` — campos propios del modelo UC

### Schema TipoFicha — formato canónico
```json
{
  "campos": [
    {"id": "...", "tipo": "texto|numero|select|booleano|fecha|escala",
     "etiqueta": "...", "descripcion": null, "obligatorio": false, "orden": 1}
  ]
}
```
- `select` → `opciones`: array de strings simples.
- `numero` → `unidad`: string nullable.
- `escala` → `tipo_escala_id`: int FK.

### Deadlocks en suite completa (PostgreSQL)
Los tests del módulo Intervencion fallan con deadlock cuando se ejecutan todos a la vez.
Es un problema de infraestructura preexistente. Los tests individuales o por filter pasan.
