# Cambios a aplicar en `docs/modulo-centros.md`
## Versión resultante: v1.3 · Junio 2026

> Este documento lista los cambios concretos a aplicar sobre `modulo-centros.md`.
> Todos los demás contenidos del documento permanecen sin cambios.

---

## Cabecera del documento

**Cambiar:**
```
## Documento funcional v1.2 · Mayo 2026

> **Cambios respecto a v1.1**: ...
```

**Por:**
```
## Documento funcional v1.3 · Junio 2026

> **Cambios respecto a v1.2**: se añaden `slug` y `activo` a `TipoActividad`. Se incorpora
> la entidad `Sala` como espacio funcional de un centro, independiente de la jerarquía de
> plazas (`ColeccionPlazas → Espacio`). Se añade `sala_id` en `SesionActividad`.
> Se actualizan la sección de catálogo backoffice y las decisiones diferidas.
```

---

## Sección 2.6 Actividades

**Cambiar** el último párrafo de la sección:
```
Las actividades se materializan en **sesiones** convocadas explícitamente. La inscripción
apunta siempre a una sesión concreta. VIDA 360 gestiona inscripciones y control de aforo
por sesión; la gestión operativa interna (recursos, asistencia, certificados) corresponde
al centro.
```

**Por:**
```
Las actividades se materializan en **sesiones** convocadas explícitamente. La inscripción
apunta siempre a una sesión concreta. VIDA 360 gestiona inscripciones y control de aforo
por sesión; la gestión operativa interna (asistencia, certificados) corresponde al centro.

Cada sesión puede asociarse a una **sala** del centro donde se celebra. La sala es
informativa: VIDA 360 no gestiona disponibilidad ni conflictos de reserva (esto
corresponde al módulo de Agenda).
```

---

## Sección 4 — Modelo de datos: añadir nueva sección 4.7a (Sala) antes de 4.7 (Actividad)

**Insertar** la siguiente sección entre el final de `### 4.6 Plaza` y `### 4.7 Actividad`:

```markdown
### 4.7 Sala

Espacio funcional de un centro (aula, sala de reuniones, despacho, polivalente...).
Es una entidad distinta de `Espacio`, que pertenece a la jerarquía de alojamiento
(`ColeccionPlazas → Espacio → Plaza`). Las salas no tienen plazas asignables; se
referencian desde las sesiones de actividad como dato informativo de ubicación.

| Atributo     | Tipo    | Notas                         |
|--------------|---------|-------------------------------|
| id           | PK      |                               |
| centro_id    | FK      |                               |
| nombre       | string  |                               |
| descripcion  | text    | Nullable                      |
| capacidad    | integer | Nullable. Personas, no plazas |
| accesible    | boolean | default false                 |
| activa       | boolean | default true                  |
| notas        | text    | Nullable                      |
```

---

## Sección 4 — Renumerar entidades

Con la inserción de `Sala` como `4.7`, las secciones siguientes pasan a:

| Antes | Después |
|-------|---------|
| 4.7 Actividad | 4.8 Actividad |
| 4.8 SesionActividad | 4.9 SesionActividad |
| 4.9 InscripcionCentro | 4.10 InscripcionCentro |
| 4.10 DirectorCentro | 4.11 DirectorCentro |
| 4.11 ContactoCentro | 4.12 ContactoCentro |
| 4.12 Prescripcion | 4.13 Prescripcion |
| 4.13 ListaEspera | 4.14 ListaEspera |

---

## Sección 4.9 SesionActividad (antigua 4.8)

**Cambiar** la tabla de atributos:
```
| estado | enum | `programada` · `celebrada` · `cancelada` |
| notas  | text | Nullable |
```

**Por:**
```
| estado   | enum | `programada` · `celebrada` · `cancelada` |
| sala_id  | FK   | Nullable. FK → salas.id. Sala donde se celebra la sesión |
| notas    | text | Nullable |
```

---

## Sección 7 — Catálogo y configuración backoffice

**Cambiar** la tabla de entidades catálogo:
```
| `TipoActividad` | Tipos de actividad: taller, charla, seminario, grupo de apoyo... |
```

**Por:**
```
| `TipoActividad` | Tipos de actividad: taller, charla, seminario, grupo de apoyo... Campos: `id`, `nombre`, `slug` (único, obligatorio), `descripcion`, `activo`. |
```

**Cambiar** la línea de gestionables desde Filament:
```
Gestionables desde Filament (backoffice): `Centro`, `Red`, `ColeccionPlazas`, `Servicio`, y todos los catálogos anteriores.
```

**Por:**
```
Gestionables desde Filament (backoffice): `Centro`, `Red`, `ColeccionPlazas`, `Sala`, `Servicio`, y todos los catálogos anteriores.
```

---

## Sección 8 — Relación con otros módulos

**Añadir** al final de la lista (antes del cierre `---`):

```
- **Módulo de Agenda**: además del horario detallado de centros y la gestión de citas, la gestión de disponibilidad de `Sala` (detección de conflictos de reserva) se diseñará en este módulo.
```

*(Esto reemplaza la línea existente sobre Agenda, que solo menciona horario y citas.)*

---

## Sección 9 — Decisiones diferidas

**Cambiar** la entrada existente:
```
**Gestión interna de actividades**: espacios, recursos, asistencia y certificados
corresponden a herramientas especializadas del centro. VIDA 360 llega hasta inscripción
y control de aforo.
```

**Por:**
```
**Gestión interna de actividades**: asistencia y certificados corresponden a herramientas
especializadas del centro. VIDA 360 gestiona inscripciones, control de aforo y referencia
de sala por sesión.

**Disponibilidad de salas**: VIDA 360 almacena la sala asociada a cada sesión como dato
informativo, pero no gestiona disponibilidad ni detecta conflictos de reserva entre
sesiones que usen la misma sala. Esta funcionalidad corresponde al módulo de Agenda.
La validación de que el aforo de la sala sea suficiente para el número de inscritos queda
a criterio del profesional que programa la sesión.
```
