# SESSION — VIDA 360

_Actualizado: 2026-06-16_

## Tarea completada

`UnidadConvivencia` — modelos, migraciones y tests de Unidad de Convivencia (10 pasos de `uc-implementacion.md`).

## Estado actual

### Cambios aplicados en esta sesión

**Documentación**
- `docs/modulo-ciudadania.md` sección 3.4 — reescrita con decisiones de diseño UC:
  campo `verificado_por`/`verificado_en`, restricción de perceptor, titularidad de planes.
- `docs/modulo-intervencion.md` sección 5.2 — añadido `unidad_convivencia_id` nullable a atributos de `PlanDeIntervencion`.

**Migraciones**
- `Modules/Ciudadania/database/migrations/2026_06_16_000001_create_unidades_convivencia_tables.php`
  crea `unidades_convivencia` (con softDeletes) y `unidad_convivencia_miembros`
  (con `verificado_por`, `verificado_en`, índices compuestos).
- `Modules/Intervencion/database/migrations/2026_06_16_000002_add_unidad_convivencia_to_planes_intervencion.php`
  añade `unidad_convivencia_id` nullable con FK a `unidades_convivencia`.

**Modelos**
- `Modules/Ciudadania/app/Models/UnidadConvivencia.php` — cifrado de domicilio
  via Crypt, `agregarMiembro()`, `darDeBajaMiembro()`, `miembrosActivos()`,
  `miembrosVerificados()`, `estaDisuelta()`, softDeletes, factory.
- `Modules/Ciudadania/app/Models/UnidadConvivenciaMiembro.php` — `verificar()`,
  `estaActiva()`, `puedeSerPerceptorPrestaciones()`.
- `app/Models/Ciudadano.php` — añadidas relaciones: `membresiasUC()`,
  `unidadesConvivencia()`, `unidadesConvivenciaActivas()`, `tieneResidenciaVerificada()`.

**Factory y autoload**
- `Modules/Ciudadania/database/factories/UnidadConvivenciaFactory.php` —
  estado `disuelta()` incluido.
- `composer.json` — añadida entrada `"Modules\\Ciudadania\\Database\\Factories\\"` (faltaba).

**Versionable en UnidadConvivencia**: trait añadido + TF-UC-14 que verifica snapshot en `versiones` al actualizar.

**Tests TF-UC-01 a TF-UC-14**: todos en verde. Suite completa Ciudadanía: 49 pasando.

### TODOs documentados en código (sin cambios)
- `CiudadanoPage::statPrestaciones()`: integrar con módulo Prestaciones.
- Modal "Ver historial completo" de accesos (enlace "Ver todo").
- Route PISO show (Entrega 4).
- Menú ⋯ con acciones del expediente.
- Botón "Ver ficha" UC en `FichaCiudadanoPage` apunta a TODO.

## Siguiente paso recomendado

1. **UI Livewire para gestión de UC** — añadir/dar de baja miembros, verificar
   residencia, ver composición dentro de la pantalla de intervención del ciudadano.
2. **Modal "Ver historial completo" de accesos** — el enlace "Ver todo" existe pero apunta a `#`.
3. **Integrar `statPrestaciones`** con el módulo Prestaciones cuando esté disponible.
4. **PISO/plan detail page** (Entrega 4).

## Contexto técnico para retomar

### Ciudadano — relaciones UC (añadidas en esta sesión)
- `membresiasUC()` → HasMany de `UnidadConvivenciaMiembro` (historial completo)
- `unidadesConvivencia()` → BelongsToMany via tabla pivote
- `unidadesConvivenciaActivas()` → idem, filtrando `fecha_fin IS NULL`
- `tieneResidenciaVerificada()` → bool, para control de perceptores de prestaciones

### Decisiones de dominio fijadas (UC)
1. Todo miembro UC es ciudadano pleno (flujo de alta completo).
2. `verificado` en membresía = residencia municipal verificada; sin ella no puede ser perceptor.
3. `PlanDeIntervencion` puede tener `unidad_convivencia_id` (intervención familiar); exactamente uno de `ciudadano_id` o `unidad_convivencia_id` debe ser el destinatario.
4. La UC no tiene titular.

### Livewire 4 — restricciones consolidadas
- `livewire:updated` no existe. Usar `Livewire.hook('morphed', cb)` tras `livewire:initialized`.
- Full-page components: `mount()` solo recibe parámetros de ruta. Leer con `request()->query('param')`.
- `redirect()` en un componente devuelve `Livewire\Features\SupportRedirects\Redirector`. Usar `$this->redirect(route(...))`.
- `wire:model` es diferido. Usar `wire:model.live` cuando el re-render inmediato es necesario.
