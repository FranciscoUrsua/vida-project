# Módulo: Registro de Atención — VIDA 360
## `docs/modulo-atencion.md`

> Este documento describe el modelo conceptual, de datos e implementación del
> Registro de Atención. Debe leerse junto a `docs/modulo-ciudadania.md`,
> `docs/modulo-intervencion.md` y `docs/modulo-usuarios-permisos.md`
> (especialmente secciones 2.4 — Rol `consulta_basica` — y 2.7 — Rol `intervencion`).

---

## 1. Contexto y motivación

En los servicios sociales municipales existen interacciones con ciudadanos que
no implican la apertura de una Historia Social pero que deben quedar registradas:

- Una consulta de información en un Centro de Servicios Sociales.
- Una orientación puntual con derivación a otro organismo.
- La inscripción y participación en actividades de acceso libre (talleres,
  cursos, charlas en centros de mayores, centros juveniles, CAIM...).
- Una llamada telefónica de seguimiento informal.

Estas interacciones consumen recursos del sistema, son relevantes para entender
la situación de la persona, y en el sistema actual (CIVIS) quedan registradas
bajo un número de "Primera Atención" (PA). VIDA360 generaliza este concepto
como **Registro de Atención**.

El `RegistroAtencion` es independiente de la `HistoriaSocial`. Ambos cuelgan
del `Ciudadano`. Un ciudadano puede tener registros de atención sin historia
social, y puede tener historia social sin registros de atención previos. Si en
el futuro se abre historia, los registros previos son visibles como antecedente
de contacto con los servicios.

**El número de Historia Social es tan único y significativo como el número de
historia sanitaria.** No se abre a la ligera, no se abre para conveniencia
administrativa, y su apertura es una decisión profesional del TSR. Los
`RegistroAtencion` existen precisamente para no forzar la apertura de historia
cuando no corresponde.

---

## 2. Relación con otros módulos

```
Ciudadano (Módulo Ciudadanía)
  ├── HistoriaSocial (Módulo Intervención) — cuando el TSR decide abrirla
  └── RegistroAtencion (Módulo Atención) — cualquier otra interacción
        ├── tipo: informacion — creado por profesional con rol intervencion
        │                       o consulta_basica
        ├── tipo: actividad   — creado automáticamente al inscribirse en
        │                       una actividad (Módulo Centro, fase 2)
        └── tipo: contacto    — llamada, email, comunicación informal
```

La relación polimórfica `origen_tipo / origen_id` permite que modelos de otros
módulos (Inscripcion, Actividad) generen registros de atención automáticamente
sin que el módulo Atención dependa de ellos — la dependencia es inversa.

---

## 3. Modelo de datos

### Tabla `registros_atencion`

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | PK | |
| `ciudadano_id` | FK | Ciudadano identificado. Siempre presente |
| `tipo` | enum | `informacion` / `actividad` / `contacto` |
| `fecha` | date | Fecha de la atención |
| `profesional_id` | FK nullable | Null para registros generados por sistema |
| `prestacion_id` | FK nullable | Prestación del catálogo. Obligatoria para tipo `informacion` si la atención corresponde a una prestación tasada (ej: 01001 Primera Atención) |
| `demanda` | text nullable | Qué solicita el ciudadano. Solo tipo `informacion` y `contacto` |
| `respuesta` | text nullable | Qué se le responde o proporciona |
| `origen` | enum | `manual` / `sistema` |
| `origen_tipo` | string nullable | Clase del modelo que origina el registro (ej: `Modules\Centro\Models\Inscripcion`) |
| `origen_id` | bigint nullable | ID del modelo origen |
| `cita_generada_id` | FK nullable | Cita con TSR generada como resultado de la atención |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

**Índices:** `ciudadano_id`, `profesional_id`, `(origen_tipo, origen_id)`,
`(ciudadano_id, fecha)` para el historial cronológico.

### Restricciones de negocio en código

- Tipo `informacion`: `profesional_id` obligatorio (no puede ser un registro
  generado por sistema).
- Tipo `actividad`: `origen_tipo` y `origen_id` obligatorios; `profesional_id`
  nullable (el registro lo genera el sistema al inscribirse).
- No existe ciudadano anónimo: `ciudadano_id` siempre presente.

---

## 4. Roles y permisos

### Rol `consulta_basica`

El auxiliar de información puede:
- Crear `RegistroAtencion` de tipo `informacion` y `contacto`.
- Ver el historial de atenciones de un ciudadano.
- Dar de alta ciudadanos (para identificarlos antes de registrar la atención).
- Generar una cita con el TSR como resultado de la atención
  (`cita_generada_id`).

El auxiliar de información **no puede**:
- Ver ni crear `HistoriaSocial`.
- Crear apuntes, valoraciones ni planes.
- Acceder a datos de categoría especial.
- Ver el contenido de la Historia Social de un ciudadano aunque sepa que existe.

### Rol `intervencion`

El TSR puede:
- Crear `RegistroAtencion` de cualquier tipo.
- Ver el historial de atenciones de cualquier ciudadano (dentro de su ámbito
  habitual de acceso).
- Abrir la `HistoriaSocial` desde la ficha del ciudadano.
- Navegar a `CiudadanoPage` (pantalla de intervención) desde la ficha.

### Permisos atómicos nuevos

Añadir al `PermisosSeeder`:

```
atencion.crear
atencion.leer
atencion.leer_ajeno   — ver atenciones de ciudadanos fuera de la UO del profesional
```

El permiso `atencion.crear` se asigna a `consulta_basica` e `intervencion`.
El permiso `atencion.leer` se asigna a todos los roles con acceso a datos
de ciudadanos (`consulta_basica`, `intervencion`, `tramitacion`, `supervision`).
El permiso `atencion.leer_ajeno` se asigna solo a `intervencion` y `supervision`.

---

## 5. Flujos principales

### 5.1 Primera atención en CSS — rol `consulta_basica`

1. El auxiliar de información recibe al ciudadano.
2. Busca al ciudadano en VIDA360 por nombre, DNI u otros datos. Si no existe,
   lo da de alta con datos básicos (sin historia social).
3. Abre el formulario de nueva atención desde la ficha del ciudadano.
4. Registra la demanda y la respuesta. Selecciona la prestación del catálogo
   si corresponde (ej: 01001 Primera Atención / Información).
5. Si determina que el Ayuntamiento es competente y debe intervenir el TSR,
   genera una cita desde el mismo formulario.
6. El sistema crea el `RegistroAtencion` y, si procede, la cita vinculada.

### 5.2 El TSR recibe al ciudadano con atenciones previas

1. El TSR accede a la ficha del ciudadano desde el buscador.
2. Ve los datos básicos y el historial de atenciones previas (fecha, tipo,
   demanda resumida, profesional que atendió).
3. Si hay una cita vinculada a la última atención, ve el contexto de por qué
   se generó.
4. Decide si abre historia social (botón "Abrir historia social") o registra
   otra atención sin abrirla.

### 5.3 Inscripción en actividad — generado por sistema (fase 2)

1. El responsable de una actividad en el módulo Centro gestiona la inscripción
   de un ciudadano.
2. Al confirmar la inscripción, el sistema crea automáticamente un
   `RegistroAtencion` de tipo `actividad` con `origen_tipo = Inscripcion` y
   `origen_id = {id de la inscripción}`.
3. Ni el responsable ni el ciudadano ven este registro en el contexto de la
   actividad — es transparente al flujo del módulo Centro.
4. El TSR que posteriorment accede a la ficha del ciudadano ve en el historial
   "Participación en Taller de acuarela — Centro de Mayores Retiro — 12/03/2024"
   con enlace a la actividad.

---

## 6. Vista del historial en la ficha del ciudadano

El historial de atenciones es visible en la `FichaCiudadanoPage` (ficha de
datos del ciudadano en el módulo Ciudadanía). Es una lista cronológica inversa,
una línea por registro.

**Formato de cada línea según tipo:**

| Tipo | Contenido visible | Enlace |
|---|---|---|
| `informacion` | Fecha · Profesional · Primeras palabras de la demanda | "Ver detalle" (expande inline) |
| `actividad` | Fecha · Nombre de la actividad (desde origen) | Enlace a la actividad |
| `contacto` | Fecha · Profesional · Nota breve | "Ver detalle" (expande inline) |

El historial no muestra el campo `respuesta` directamente — se ve al expandir
el detalle. Esto mantiene la lista legible con muchos registros.

**Visibilidad según rol:**
- `consulta_basica`: ve el historial de atenciones del ciudadano que está
  atendiendo en ese momento. No tiene acceso al historial de atenciones fuera
  de su contexto inmediato.
- `intervencion`: ve el historial completo de cualquier ciudadano que pueda
  acceder (igual que accede a su ficha).
- `tramitacion`, `supervision`: ven el historial en modo lectura.

---

## 7. Botones en la ficha del ciudadano (`FichaCiudadanoPage`)

La ficha del ciudadano muestra acciones distintas según el rol del usuario y el
estado del ciudadano en el sistema.

### Para rol `consulta_basica`

Siempre visible:
- **"Nueva atención"** — abre el formulario de `RegistroAtencion` de tipo
  `informacion`. Si la atención concluye con derivación al TSR, el formulario
  permite generar la cita directamente.

### Para rol `intervencion`

Siempre visible:
- **"Nueva atención"** — crea un `RegistroAtencion`. El TSR puede registrar
  cualquier tipo de atención.

Condicional según estado del ciudadano:
- **"Abrir historia social"** — visible solo si el ciudadano NO tiene historia
  social. Crea la `HistoriaSocial` con el TSR autenticado como
  `profesional_responsable_id` y navega a `CiudadanoPage`.
- **"Ver historia social"** — visible solo si el ciudadano YA tiene historia
  social. Navega a `CiudadanoPage`.

Ambos botones comparten el mismo espacio visual — son mutuamente excluyentes.

---

## 8. Consideraciones de implementación

### Fase 1 (implementación actual)

- Modelo `RegistroAtencion` con tipos `informacion` y `contacto`.
- Formulario de nueva atención en `FichaCiudadanoPage` (Livewire, modal o
  sección inline).
- Historial de atenciones en `FichaCiudadanoPage`.
- Botones "Abrir historia social" / "Ver historia social" para rol
  `intervencion`.
- Permisos atómicos `atencion.crear` y `atencion.leer`.

### Fase 2 (con módulo Centro)

- Tipo `actividad` activado.
- Relación polimórfica `origen_tipo / origen_id` implementada.
- Generación automática desde `Inscripcion`.
- Vista de actividades en el historial con enlace al módulo Centro.

### Integración futura con HSU (Historia Social Única)

El número de `HistoriaSocial` en VIDA360 debe estar preparado para sincronizarse
con el identificador de HSU de la Comunidad de Madrid. El `RegistroAtencion`
no tiene equivalente directo en HSU (es un concepto local de gestión municipal),
pero los registros de tipo `informacion` vinculados a una prestación tasada
pueden ser relevantes para la auditoría de atenciones.

---

## 9. Decisiones pendientes

- **Código de Primera Atención:** en CIVIS cada PA tiene un número único
  secuencial visible para el ciudadano. VIDA360 usa el `id` interno del
  `RegistroAtencion`, que no está diseñado para ser comunicado. Pendiente
  decidir si se necesita un identificador visible tipo "PA-2024-001234".

- **Tipo `contacto`:** definido en el modelo pero sin UI específica en fase 1.
  Las llamadas telefónicas informales de seguimiento son un caso de uso real
  pero secundario. Se implementará cuando el módulo de Agenda esté operativo.

- **Acceso del ciudadano a su historial de atenciones:** la carpeta ciudadana
  (portal externo, fuera del alcance actual) podría mostrar el historial de
  atenciones al propio ciudadano. Pendiente de definir qué campos son visibles
  y con qué nivel de detalle.

---

## 10. Referencias de código

| Clase | Archivo |
|---|---|
| `RegistroAtencion` | `Modules\Atencion\Models\RegistroAtencion` |
| `RegistroAtencionPolicy` | `Modules\Atencion\Policies\RegistroAtencionPolicy` |
| `RegistroAtencionFactory` | `Modules\Atencion\Database\Factories\RegistroAtencionFactory` |
| Tests funcionales | `Modules\Atencion\Tests\Feature\RegistroAtencionTest` (TF-AT-XX) |
| Tests Livewire | `Modules\Atencion\Tests\Feature\Livewire\FichaAtencionTest` (TF-LW-AT-XX) |

---

*Documento elaborado en junio 2026. Fase 1.*
