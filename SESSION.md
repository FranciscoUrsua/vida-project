# SESSION — VIDA 360

_Actualizado: 2026-06-18_

## Tarea completada

Ficha — schema_snapshot, Versionable y pre-relleno de nueva valoración.
Implementación completa de los 6 pasos de `ficha-schema-snapshot.md`: migración,
modelo Ficha con Versionable + scope + prerellenarDesde, inversión restricción TipoFicha,
persistencia schema_snapshot/profesional_id en RegistrarValoracionPage, backfill fichas
existentes, 12 tests TF-INT-I01..I12 todos en verde.

## Estado actual

### Cambios aplicados en esta sesión

**Módulo Intervencion — Ficha con schema_snapshot**
- Migración `2026_06_18_000001_add_schema_snapshot_and_profesional_to_fichas.php`:
  `schema_snapshot` (jsonb nullable) y `profesional_id` (FK users, nullOnDelete) en `fichas`.
- `Ficha.php`: trait `Versionable`, casts `schema_snapshot → array`, `$fillable` ampliado,
  scope `historialPara()`, método estático `prerellenarDesde()`.
- `TipoFicha.php`: eliminar campo con fichas asociadas ahora permitido (`continue`);
  cambiar tipo sigue prohibido. PHPDoc actualizado.
- `TipoFichaTest.php` H08 invertido: 10/10 en verde.
- `RegistrarValoracionPage.php`: `guardar()` persiste `schema_snapshot` y `profesional_id`.
- Backfill: 2 fichas preexistentes actualizadas.
- `FichaVersionadoTest.php`: 12 tests TF-INT-I01..I12, todos en verde (26 assertions).
- `docs/modulo-intervencion.md` §4: reescrito con entidad Ficha, filosofía de versionado,
  visualización histórica y lista de tests I01-I12.
- `CLAUDE.md` §6: fila `ficha-schema-snapshot.md` añadida.

## Siguiente paso recomendado

1. **Visualización de historial de fichas en CiudadanoPage** — el scope `historialPara()` y la
   tabla de fichas con `schema_snapshot` ya están listos; falta la UI que los consuma.
   Contexto: ver `docs/modulo-intervencion.md` §4.7.
2. **Pre-relleno en RegistrarValoracionPage** — `Ficha::prerellenarDesde()` existe como método
   puro; integrarlo en `mount()` cuando `$tipoFichaId` se inicia con una ficha anterior.
3. **PISO/plan detail page** — Entrega 4.
4. **Genograma** — bloqueado hasta definir tipo_dinamica, fecha_fallecimiento y decisión
   sobre nodos ligeros (ver BACKLOG).

## Contexto técnico para retomar

### schema_snapshot — filosofía
- Cada `Ficha` guarda el schema del `TipoFicha` en el momento de creación.
- Fichas son autocontenidas: interpretables incluso si el TipoFicha evoluciona.
- Eliminar un campo del TipoFicha es SEGURO (fichas conservan snapshot).
- Cambiar el TIPO de un campo existente sigue siendo PROHIBIDO.

### Versionable en Ficha — dos actos
- **Corrección** (update sobre ficha incompleta): genera registro `Version` con el estado anterior.
- **Nueva valoración** (create de Ficha nueva): NO genera Version; la ficha anterior permanece intacta.
- `versionable_type` = `Modules\Intervencion\Models\Ficha` (FQCN).

### prerellenarDesde
- Método puro: recibe `Ficha $anterior` y `TipoFicha $actual`, devuelve array de valores.
- Copia solo campos presentes en el schema actual; descarta retirados; null para nuevos.
- No persiste nada; el caller decide si usar los valores.

### CiudadanoRelacion — reciprocidad automática (sesión anterior)
- `booted()` created: crea el registro inverso con el tipo recíproco del catálogo.
- `$sincronizandoReciproca` estático evita recursión infinita.
- Computeds en CiudadanoPage: `representante()`, `relacionesAgrupadas()`, `relacionesMiembrosUc()`.
