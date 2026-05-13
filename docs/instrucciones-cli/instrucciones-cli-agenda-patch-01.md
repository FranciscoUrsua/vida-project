# Instrucciones CLI — Agenda Patch 01: estado `anulado` en EstadoSlot

## Contexto

Durante la redacción de las pruebas funcionales del módulo Agenda se identificó que el enum `EstadoSlot` necesita un valor adicional: `anulado`. Este estado es distinto de `expirado` y `no_ocupado`: representa un slot que fue activamente invalidado por una `ExcepcionProfesional` registrada después de la publicación del cuadrante (por ejemplo, una baja médica sobrevenida).

Sin este estado no es posible distinguir en estadísticas un slot que simplemente no se usó de uno que fue cancelado por ausencia del profesional.

Lee `docs/modulo-agenda.md` sección 2.7 (entidad `Slot`) y sección 8 (pruebas PF-07.5) para entender el contexto completo antes de aplicar los cambios.

---

## Cambios a realizar

### 1. Enum `EstadoSlot`

**Archivo:** `Modules/Agenda/app/Enums/EstadoSlot.php`

Añadir el caso `Anulado` al enum. El orden en el enum debe ser:

```
Disponible
Reservado
BloqueadoUrgencia
BloqueadoEvento
Anulado
Expirado
NoOcupado
```

El método `label()` debe devolver `'Anulado'` para el nuevo caso.

---

### 2. Migration de modificación de columna

Crear una nueva migration que modifique la columna `estado` de la tabla `slots` para incluir el nuevo valor.

**Nombre sugerido:** `add_anulado_to_slots_estado_enum`

El cambio depende del motor de base de datos configurado en el proyecto:

- Si el proyecto usa **MySQL/MariaDB**, la columna `estado` es de tipo `ENUM` y hay que modificarla con `DB::statement` para añadir el nuevo valor:

```php
DB::statement("ALTER TABLE slots MODIFY COLUMN estado ENUM('disponible','reservado','bloqueado_urgencia','bloqueado_evento','anulado','expirado','no_ocupado') NOT NULL DEFAULT 'disponible'");
```

- Si el proyecto usa **PostgreSQL** con la columna como `string` con validación en capa de aplicación (que es el patrón habitual en este proyecto — verifica cómo están definidas las columnas enum en otras migrations del proyecto antes de decidir), no es necesaria ninguna migration: el cambio solo afecta al enum PHP.

Verifica el patrón usado en otras migrations del proyecto antes de crear esta migration. Si los enums se almacenan como `string`, omite este paso.

---

### 3. Scope en el modelo `Slot`

**Archivo:** `Modules/Agenda/app/Models/Slot.php`

Añadir el scope:

```php
public function scopeAnulados(Builder $query): Builder
{
    return $query->where('estado', EstadoSlot::Anulado);
}
```

---

### 4. Lógica en `GestionAusenciaService`

**Archivo:** `Modules/Agenda/app/Services/GestionAusenciaService.php`

Actualizar el comentario del método `procesarAusencia` para reflejar que los slots afectados pasan a estado `anulado`, no a ningún otro estado. El método sigue siendo un esqueleto sin lógica interna — solo actualizar el docblock:

```php
/**
 * Gestiona el flujo cuando un profesional no se presenta.
 * - Las citas confirmadas del día pasan a estado 'cancelada' con motivo descriptivo.
 * - Los slots disponibles y bloqueado_urgencia de esas fechas pasan a estado 'anulado'.
 * - Los slots en estado 'reservado' (con cita) no se anulan hasta que la cita sea cancelada.
 * - Genera alerta al supervisor con la lista de citas canceladas pendientes de reagendización.
 * - En modos estandar/avanzado, devuelve slots de urgencia disponibles para reasignación.
 * - En modo basico, devuelve slots disponibles de otros profesionales.
 */
```

---

## Criterio de verificación

1. `php artisan tinker` puede instanciar `EstadoSlot::Anulado` sin error.
2. `Slot::anulados()` es un scope callable sin errores.
3. Si se creó migration: `php artisan migrate` ejecuta sin errores.
4. No hay cambios en otras entidades, migrations ni recursos Filament.
