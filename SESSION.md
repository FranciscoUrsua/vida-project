# SESSION — VIDA 360

_Actualizado: 2026-06-16_

## Tarea completada

Catálogo `TipoRelacion` — enum `ImplicacionFuncional`, migración, modelo, seeder,
factory, resource Filament y 15 tests en verde.

## Estado actual

### Cambios aplicados en esta sesión

**Módulo Ciudadania — nuevos ficheros**
- `Modules/Ciudadania/app/Enums/ImplicacionFuncional.php`
  — enum PHP con casos `Representante`, `TutorLegal`, `CuidadorPrincipal`.
- `Modules/Ciudadania/database/migrations/2026_06_16_000003_create_tipos_relacion_table.php`
  — tabla `tipos_relacion` con slug único, FK lógica `slug_reciproco`,
  `implicacion_funcional` indexado.
- `Modules/Ciudadania/app/Models/TipoRelacion.php`
  — modelo con `HasFactory`, scopes `activos`/`conImplicacion`, método
  `tipoRecíproco()`, helpers `conImplicacionFuncional()`, `existeImplicacion()`,
  `opcionesParaSelect()`. Hook `deleting` que bloquea tipos con `eliminable = false`.
- `Modules/Ciudadania/database/seeders/TipoRelacionSeeder.php`
  — 15 tipos iniciales, idempotente (updateOrCreate por slug).
- `Modules/Ciudadania/database/factories/TipoRelacionFactory.php`.
- `Modules/Ciudadania/tests/Feature/TipoRelacionTest.php`
  — 15 tests TF-TR-01..15, todos en verde.

**Filament (centralizado en app/)**
- `app/Filament/Resources/TipoRelacionResource.php`
  — grupo Catálogos, sort 9, acceso restringido a `adm_sistema`.
  Slug no editable en modo edición. Eliminar visible solo si `eliminable = true`.
- `app/Filament/Resources/TipoRelacionResource/Pages/{List,Create,Edit}TipoRelacion.php`

**Configuración**
- `composer.json`: añadido mapping PSR-4 `Modules\\Ciudadania\\Database\\Seeders\\`
  → `Modules/Ciudadania/database/seeders/` (faltaba, como en los demás módulos).

**Documentación**
- `docs/modulo-ciudadania.md` §3.3 y §7.4 actualizados.

### Paso 5 omitido (sin base)
El Paso 5 de las instrucciones pedía actualizar `CiudadanoRelacion` y el trait
`TieneRelacionesReciprocas`, pero ni el modelo ni el trait ni la tabla
`ciudadano_relaciones` existen aún. Añadido a BACKLOG.

## Siguiente paso recomendado

1. **Modelo CiudadanoRelacion + migración `ciudadano_relaciones`** — prerequisito
   para mostrar relaciones en CiudadanoPage y FichaCiudadanoPage.
2. **Widget UC con tipo de relación** — una vez exista CiudadanoRelacion,
   mostrar el tipo de relación del miembro respecto al titular.
3. **Modal gestión de relaciones** en FichaCiudadanoPage (crear/editar/cerrar).
4. **Fichas sociales / Formulario de valoración** — bloquea el PISO completo.

## Contexto técnico para retomar

### TipoRelacion — diseño clave
- El código evalúa `implicacion_funcional`, nunca el slug ni la etiqueta.
- `eliminable = false` → error LogicException al intentar borrar.
- `tipoRecíproco()` devuelve `$this` para tipos simétricos; el tipo inverso para asimétricos.
- `opcionesParaSelect()` → `['slug' => 'etiqueta']` para formularios.

### PSR-4 Ciudadania
- `Modules\\Ciudadania\\Database\\Seeders\\` → `Modules/Ciudadania/database/seeders/`
  añadido a `composer.json`. Sin esto los seeders no se autocargan.

### Campos de Ciudadano (referencia)
- `Ciudadano.direccion_texto` (cifrado) — se usa como `domicilio` al crear la UC.
- `Ciudadano.coordenadas_lat`, `coordenadas_lng` — se usan como `latitud`/`longitud` en UC.
- `nombre`, `apellido1`, `apellido2` también están cifrados (cast `encrypted`).
