# Instrucciones CLI — Módulo Organización: colectivos protegidos y tests funcionales

> Contexto: estas instrucciones aplican dos tipos de cambios al módulo Organización.
> 1. Cambios de modelo y backoffice para reforzar la inmutabilidad de los colectivos protegidos.
> 2. Adición de la sección de tests funcionales al documento de especificaciones del módulo.
>
> Leer `docs/principios-vida360.md` (principio 3.11) antes de ejecutar.

---

## Parte 1: Cambios en el modelo y backoffice

### 1.1 Migración

Verificar si la tabla `colectivos_protegidos` tiene columna `activo`.

- Si existe: crear una nueva migración en `Modules/Organizacion/database/migrations/` que elimine esa columna.
- Si no existe: no hacer nada en migraciones.

```php
// Ejemplo de migración si la columna existe
Schema::table('colectivos_protegidos', function (Blueprint $table) {
    $table->dropColumn('activo');
});
```

### 1.2 Modelo `ColectivoProtegido`

Aplicar los siguientes cambios:

- Eliminar el campo `activo` del array `$fillable` si está presente.
- Eliminar el cast de `activo` si existe.
- Eliminar el scope `scopeActivos()` si existe.
- Verificar que el modelo **no** usa el trait `SoftDeletes`. Si lo usa, eliminarlo.
- Sobrescribir el método `delete()` para lanzar una excepción explícita:

```php
/**
 * Los colectivos protegidos no pueden eliminarse ni desactivarse.
 *
 * Diseño deliberado: la desactivación o eliminación de un colectivo protegido
 * dejaría sin protección inmediata a todos los ciudadanos vinculados a él,
 * incluyendo víctimas de violencia de género y menores. No existe baja lógica
 * en esta entidad. Para renombrar o ajustar un colectivo, editar sus campos
 * descriptivos. Para introducir un nuevo concepto, crear un colectivo nuevo.
 *
 * @throws \LogicException
 */
public function delete(): never
{
    throw new \LogicException(
        'Los colectivos protegidos no pueden eliminarse. Ver principio 3.11 de principios-vida360.md.'
    );
}
```

- Añadir el bloque PHPDoc en la cabecera de la clase explicando la ausencia de baja lógica (ver comentario del método `delete()` como referencia de tono).

### 1.3 `ColectivoProtegidoResource` (Filament)

- Eliminar `DeleteAction` de las acciones de tabla y de página si están presentes.
- Eliminar cualquier acción de cambio de estado (`activo`/`inactivo`).
- Eliminar el campo `activo` del formulario si está presente.
- Dejar únicamente `CreateAction` y `EditAction`.
- Los únicos campos editables en el formulario son `nombre` y `descripcion`.
- El listado puede mostrar `nombre`, `descripcion` y `created_at`. Sin columna de estado.

---

## Parte 2: Tests funcionales

Añadir una sección `## Tests funcionales` al documento de especificaciones del módulo Organización (actualmente en `docs/documentacion-proyecto.md`, sección *Módulo Organización*, o en `docs/modulo-organizacion.md` si existe como fichero independiente).

Implementar los siguientes tests en `Modules/Organizacion/tests/Feature/`:

---

### Estructura territorial

**TF-ORG-01: Zona no puede existir sin distrito**
Intentar crear una zona sin `distrito_id` válido debe fallar con error de validación. No se crea ningún registro en base de datos.

**TF-ORG-02: Desactivar distrito no elimina sus zonas**
Al desactivar un distrito (`activo = false`), sus zonas no se eliminan ni se desactivan automáticamente. Los registros de zonas permanecen intactos. El distrito desactivado no aparece en los selectores operativos del sistema.

**TF-ORG-03: Zona desactivada no aparece en selectores**
Una zona con `activa = false` no aparece en los selectores de asignación de ciudadanos ni en los filtros operativos. Sí es visible en el backoffice de administración.

---

### Jerarquía de Unidades Organizativas

**TF-ORG-04: Nodo raíz único**
Solo puede existir un nodo sin `parent_id` (el ayuntamiento raíz). Intentar crear un segundo nodo raíz debe fallar.

**TF-ORG-05: Consulta de descendientes es completa y recursiva**
Dado un nodo con tres niveles de descendientes, la consulta de descendientes devuelve todos los nodos en todos los niveles, no solo los hijos directos.

**TF-ORG-06: Consulta de ancestros es completa hasta la raíz**
Dado un nodo hoja, la consulta de ancestros devuelve todos los nodos hasta el nodo raíz, en orden ascendente.

**TF-ORG-07: No se puede crear una referencia circular en la jerarquía**
Intentar asignar como `parent_id` de un nodo A a un descendiente del propio nodo A debe ser rechazado. No se persiste el ciclo.

**TF-ORG-08: Desactivar UO no afecta a sus descendientes activos**
Al desactivar un nodo UO, sus nodos hijos permanecen en su estado actual. La desactivación no se propaga automáticamente por la jerarquía.

---

### Colectivos protegidos

**TF-ORG-09: Colectivo protegido es configurable sin desarrollo**
Es posible crear un nuevo colectivo protegido desde el backoffice de Filament y que quede activo de inmediato. No se requiere ningún cambio en el código ni reinicio del sistema.

**TF-ORG-10: Un colectivo protegido no puede desactivarse ni eliminarse**
Llamar a `delete()` sobre una instancia de `ColectivoProtegido` lanza una `LogicException`. El registro permanece en base de datos. La acción de eliminación no existe en `ColectivoProtegidoResource`. Los ciudadanos vinculados al colectivo mantienen su protección en todo momento.

**TF-ORG-11: La protección se asigna por vínculo ciudadano-colectivo, no por nombre**
Crear un colectivo nuevo con un nombre diferente y vincular ciudadanos a él activa la protección para esos ciudadanos. Los ciudadanos que siguen vinculados al colectivo anterior mantienen la suya. La protección depende del vínculo registrado en base de datos, no del nombre del colectivo ni de ningún valor hardcodeado en el código.

---

### Configuración del sistema

**TF-ORG-12: Valor de configuración se castea según su tipo declarado**
Un valor almacenado con tipo `booleano` devuelve un booleano PHP al leerlo mediante `valorCasteado()`, no una cadena. Un valor con tipo `numero` devuelve un entero o float. Un valor con tipo `json` devuelve un array o colección.

**TF-ORG-13: Configuración inexistente no rompe el sistema**
Intentar leer una clave de configuración que no existe devuelve un valor por defecto o `null` controlado, no una excepción no gestionada.

---

## Checklist de finalización

- [ ] Migración ejecutada (o verificado que la columna no existía)
- [ ] Modelo `ColectivoProtegido` sin `activo`, sin `SoftDeletes`, con `delete()` bloqueado y PHPDoc explicativo
- [ ] `ColectivoProtegidoResource` sin acciones de eliminación ni cambio de estado
- [ ] 13 tests implementados en `Modules/Organizacion/tests/Feature/`
- [ ] Todos los tests pasan (`php artisan test --filter=Organizacion`)
- [ ] TF-ORG-10 verificado en negativo: el test falla si se comenta el bloqueo en `delete()`
- [ ] Entrada añadida a `CHANGELOG.md` con los cambios realizados
