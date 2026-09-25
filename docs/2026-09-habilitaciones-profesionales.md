# Instrucciones CLI — Habilitación profesional (tercera dimensión del permiso)

> Fichero: `docs/instrucciones-cli/2026-09-habilitaciones-profesionales.md`
> Módulos afectados: `Modules/Usuarios`, `Modules/Intervencion`, `Modules/Documentos`, `Modules/Escalas`,
> `app/Filament/Resources/`, `database/seeders/worlds/demo_ciam.yaml`
> Tests: `TF-HAB-01` a `TF-HAB-25`
> **Ejecutar después de** `2026-09-demo-ciam-aditivo.md`.

---

## Contexto

Hoy el permiso efectivo es **rol ∩ UO**. El rol `intervencion` lo tienen trabajadoras sociales,
psicólogas, educadores, auxiliares de servicios sociales y, en el CIAM, abogadas. Con eso,
cualquiera de ellas puede crear un Plan de Intervención, que es un acto reservado a Trabajo Social.
Es un problema general, no exclusivo del CIAM.

Se añade una **tercera dimensión: la habilitación profesional**. Deriva de la titulación del
`Profesional`, no del rol ni de la UO.

| Dimensión | Pregunta que responde |
|---|---|
| Rol | ¿Qué tipo de operaciones puede hacer? |
| UO | ¿Sobre qué personas? |
| Habilitación | ¿Qué actos profesionales reservados puede asumir como autor? |

**Por qué importa:** los actos profesionales pueden tener efectos legales y de responsabilidad
civil para quien los ejecuta. Un informe erróneo que causa un daño tiene consecuencias para quien lo
emitió. El sistema debe dejar claro, sin ambigüedad y de forma inalterable, **quién hizo qué, cuándo
y con qué habilitación**.

Leer antes de empezar:
- `docs/principios-vida360.md`
- `docs/modulo-usuarios-permisos.md` (§1.2 y §5)
- `docs/modulo-intervencion.md` (§4 y §5)
- `docs/modulo-documentos.md` (§2.3 y §2.4)

---

## Modelo conceptual

### Dos niveles de acto profesional

**Nivel 1 — Autoría acreditada por el sistema.** Entrevistas, apuntes, valoraciones (fichas) y pases
de escala. Basta con que el sistema acredite quién fue el autor y cuándo.
- Por defecto, cualquier usuario con rol `intervencion` en la UO puede hacerlos.
- Un tipo concreto puede **restringirse** a determinadas titulaciones. Ejemplo: la valoración
  psicológica, solo Psicología.

**Nivel 2 — Firma del autor.** Informes profesionales y Plan de Intervención (que ya tiene firma de
profesional y ciudadano).
- Solo los hace un profesional habilitado.
- La habilitación es **obligatoria**: un tipo de nivel 2 sin habilitaciones configuradas no se puede
  usar.

### El nivel lo decide la clase de acto, no un campo configurable

| Acto (`acto`) | Objeto configurable | Nivel |
|---|---|---|
| `cumplimentar_valoracion` | `TipoFicha` | 1 |
| `aplicar_escala` | `TipoEscala` | 1 |
| `autoria_informe` | `PlantillaInforme` | 2 |
| `responsable_plan` | `TipoPlan` | 2 |

La decisión caso por caso («¿esto basta con una actuación registrada o necesita un informe firmado y
vinculante?») se toma **eligiendo qué se configura**: una valoración (nivel 1) o un informe (nivel 2).

Si algo requiere firma, se modela como informe, que ya tiene flujo de firma. **No se construye firma
para fichas ni escalas.**

### Autoría frente a uso

La habilitación restringe **solo actos de autoría**. **Nunca restringe la lectura** (que sigue
gobernada por rol, UO y colectivos protegidos) **ni el uso**.

Ejemplo:
- La psicóloga es autora de la valoración psicológica. La TS no puede cumplimentarla, pero sí leerla.
- Si la TS es responsable del plan, puede incorporar esa valoración al plan. Incorporar es un acto
  sobre el plan: lo gobierna `responsable_plan`, no la habilitación de la ficha.
- La abogada no puede cumplimentar la valoración psicológica ni ser responsable del plan, pero puede
  leer ambos y ser participante del plan.

---

## Fase 0 — Reconocimiento (sin modificar ficheros)

Comprobar e informar de lo siguiente:

1. Estructura actual del catálogo `titulaciones` y valores existentes.
2. Si `Profesional` tiene número de colegiado, y dónde se guarda.
3. Dónde se valida hoy que el informe social solo lo firma una TS (según `glosario.md`, «por rol»).
   Localizar todas las comprobaciones.
4. Policies actuales de `PlanDeIntervencion`, `Ficha`, pase de escala e `Informe`: qué métodos
   existen y qué comprueban.
5. Si existe hoy un vínculo entre plan y valoración (una ficha incorporada a un plan). Si no existe,
   **no construirlo**: solo informar.
6. Listado de `PlantillaInforme` y `TipoPlan` existentes, indicando cuál corresponde al informe social,
   al PISO y al `pia`.
7. Estado de `demo_ciam.yaml` y del modo aditivo tras la instrucción anterior.
8. Que en Filament se pueden asignar varios roles a un mismo usuario de forma individual (pivot
   `UsuarioRol` con historial y aprobación previa para `supervision`), y que en ningún punto del código
   se deducen roles o permisos a partir del cargo. Si se encuentra alguna deducción por cargo,
   informar y no tocarla. Informar también de los nombres exactos de los cargos existentes y de cómo
   es hoy el formulario de alta de usuario en Filament (necesario para la Fase 4 bis).

Si algo no coincide con estas instrucciones, parar y consultar.

---

## Fase 1 — Modelo de datos

### 1.1 Reglas de habilitación

```
habilitaciones_profesionales
- id
- habilitable_type      varchar  — TipoPlan | TipoFicha | TipoEscala | PlantillaInforme
- habilitable_id        bigint
- acto                  varchar  — cumplimentar_valoracion | aplicar_escala | autoria_informe | responsable_plan
- titulacion_id         FK titulaciones
- exige_colegiacion     boolean default false
- activa                boolean default true
- created_at, updated_at
unique (habilitable_type, habilitable_id, acto, titulacion_id)
```

- Modelo `HabilitacionProfesional` con los traits `Versionable` y `Auditable`. Un cambio de
  configuración debe poder reconstruirse en cualquier fecha pasada, porque es la regla con la que se
  juzgó un acto.
- La coherencia entre `acto` y `habilitable_type` se valida en el modelo. Por ejemplo,
  `responsable_plan` solo es válido sobre `TipoPlan`.
- El mapa acto → nivel es una constante en código (enum `ActoProfesional` con método `nivel()`).
  No es configurable.

### 1.2 Snapshot de habilitación en cada acto

Añadir `habilitacion_snapshot` (jsonb, nullable) a:
- `planes_intervencion`: al crear el plan y al cambiar de responsable.
- `firmas_plan`: en la firma del profesional.
- `fichas`
- La tabla de pases de escala.
- `informes`: en el momento de la firma.

Contenido del snapshot:

```json
{
  "acto": "autoria_informe",
  "profesional_id": 12,
  "usuario_id": 34,
  "titulacion_id": 3,
  "titulacion_nombre": "Trabajo Social",
  "numero_colegiado": "28/1234",
  "regla_ids": [7],
  "restringido": true,
  "evaluado_en": "2026-09-24T16:50:00+02:00"
}
```

- En actos de nivel 1 sin restricción se guarda igualmente, con `restringido: false` y
  `regla_ids: []`.
- El snapshot es **inmutable**: nunca se reescribe al cambiar la titulación del profesional ni la
  configuración de las reglas. Si el plan cambia de responsable, el cambio queda en `plan_cambios`
  con el snapshot anterior.

### 1.3 Titulaciones

Seeder idempotente (`updateOrCreate`) que garantiza que existen, como mínimo:
- Trabajo Social
- Psicología
- Derecho
- Educación Social
- Terapia Ocupacional

No modificar ni borrar las que ya existan.

---

## Fase 2 — Servicio y reglas

### 2.1 `HabilitacionService`

- `puede(Usuario $u, ActoProfesional $acto, Model $habilitable): bool`
- `motivoDenegacion(...)`: devuelve un texto legible para la interfaz, por ejemplo: «Este tipo de
  informe solo puede emitirlo un profesional con titulación en Trabajo Social colegiado».
- `snapshot(Usuario $u, ActoProfesional $acto, Model $habilitable): array`

### 2.2 Reglas de evaluación

1. Un usuario sin `Profesional` asociado nunca está habilitado para ningún acto.
2. Se toman las reglas **activas** de (`habilitable`, `acto`).
3. **Nivel 1 sin reglas:** permitido. Siguen aplicando rol y UO.
4. **Nivel 2 sin reglas:** **denegado**, con el motivo «tipo sin habilitación configurada». Es el
   comportamiento seguro por defecto.
5. **Con reglas:** permitido si la titulación del profesional coincide con alguna regla y, cuando la
   regla tiene `exige_colegiacion`, el profesional tiene número de colegiado registrado.
6. Un profesional sin titulación registrada solo puede hacer actos de nivel 1 sin restricción.

### 2.3 Momento de la comprobación

La habilitación se comprueba **cada vez que se ejecuta el acto, no solo al empezarlo**:

- **Informe:** al crear el borrador y otra vez al firmar. Si entre ambos momentos el profesional deja
  de estar habilitado, la firma se rechaza y el borrador queda como está.
- **Plan:**
  - Al crear.
  - Al editar el contenido (objetivos, actuaciones, diagnóstico).
  - Al cerrar.
  - En la firma del profesional.
  - Al asignar un nuevo responsable, que debe estar habilitado.
- **Ficha y escala:** al crear y al editar.

### 2.4 Participantes del plan

Los participantes (`plan_participantes`) **no** requieren habilitación de `responsable_plan`. Pueden
registrar apuntes y seguimientos según su rol, pero no pueden editar el contenido del plan, cerrarlo
ni firmarlo como responsables.

### 2.5 Actos anteriores

No se invalidan retroactivamente. Los planes, informes y fichas existentes creados por profesionales
que hoy no estarían habilitados siguen siendo válidos y legibles.
- Sus acciones de edición y cierre quedan sujetas a la nueva regla.
- Un plan cuyo responsable actual no está habilitado debe poder reasignarse a una TS. Añadir un aviso
  visible en la ficha del plan: «El responsable actual no está habilitado para este tipo de plan».

---

## Fase 3 — Policies e interfaz

- **Policies** de `PlanDeIntervencion`, `Ficha`, pase de escala e `Informe`: añadir
  `HabilitacionService::puede()` en los métodos correspondientes, **además de** las comprobaciones
  actuales de rol y UO.
- La validación actual del informe social «por rol» (Fase 0, punto 3) se **sustituye** por la
  habilitación. No debe quedar duplicada.
- **Interfaz (Livewire):**
  - Si el usuario no está habilitado, los botones de crear, editar, firmar y cerrar no se muestran.
  - Si se accede directamente por URL o por acción Livewire, respuesta 403 con el
    `motivoDenegacion()`.
  - **El control real está en la Policy, no en la vista.**
- **Filament:** componente reutilizable «Habilitaciones profesionales» (sección o relation manager)
  en `TipoPlanResource`, `TipoFichaResource`, `TipoEscalaResource` y el recurso de `PlantillaInforme`.
  El componente incluye:
  - Selector múltiple de titulaciones.
  - Toggle «Exige colegiación».
  - Un texto que explique el nivel del acto:
    - Nivel 1: «Si no se indica ninguna titulación, podrá cumplimentarlo cualquier profesional con
      rol de intervención».
    - Nivel 2: «Obligatorio: sin titulaciones configuradas, nadie podrá emitir este documento».
  - Para tipos de nivel 2 sin reglas, un aviso visible en el listado.

---

## Fase 4 — Configuración inicial

Mediante un seeder idempotente que **no sobrescribe** reglas ya configuradas:

| Objeto | Acto | Titulaciones | Exige colegiación |
|---|---|---|---|
| TipoPlan PISO | `responsable_plan` | Trabajo Social | no |
| TipoPlan `pia` | `responsable_plan` | Trabajo Social | no |
| Plantilla(s) de informe social | `autoria_informe` | Trabajo Social | **sí** |

- El resto de `TipoPlan` y `PlantillaInforme` existentes **no se configuran automáticamente**.
  CLI debe listarlos en su informe final: quedarán bloqueados hasta que el equipo los configure.
- No crear en esta sesión tipos de ficha ni plantillas nuevas (por ejemplo, la valoración
  psicológica). Se crearán desde Filament.

---

## Fase 4 bis — Roles sugeridos por cargo

Al dar de alta un usuario, hoy hay que elegir los roles uno a uno. Se añade una **sugerencia de roles
por cargo** que pre-rellena el formulario. Es una comodidad de gestión: **no tiene ningún efecto sobre
los permisos**.

### Modelo

```
cargo_roles_sugeridos
- id
- cargo_id     FK cargos
- rol          varchar  — nombre del rol Spatie (supervision, intervencion, tramitacion...)
- created_at, updated_at
unique (cargo_id, rol)
```

- Relación `Cargo::rolesSugeridos()`.
- Trait `Auditable`. No hace falta `Versionable`: no se juzga ningún acto con esta tabla.
- Validar que `rol` existe en el catálogo de roles.

### Comportamiento

1. **Filament, recurso de cargos:** selector múltiple «Roles sugeridos», con la nota «Se proponen al
   dar de alta a un usuario con este cargo. No otorgan permisos por sí mismos».
2. **Alta de usuario en Filament:** al elegir el profesional (y por tanto su cargo), el selector de
   roles se pre-rellena con los roles sugeridos del cargo. `adm_usuarios` puede quitar o añadir roles
   antes de guardar.
3. **Los roles pre-rellenados siguen el flujo normal de asignación**, sin atajos:
   - Historial en `UsuarioRol`.
   - Aprobación previa para `supervision` y `adm_sistema`.
   - Alertas supervisadas para el resto.
4. **Cambio de cargo de un usuario existente:** **no** se tocan sus roles. Se muestra un aviso en la
   ficha del usuario en Filament: «El cargo ha cambiado. Roles sugeridos para el nuevo cargo: …
   Revisa si procede ajustarlos».
5. **Ningún código fuera del formulario de alta y de ese aviso lee `cargo_roles_sugeridos`.** Ni las
   Policies, ni `HabilitacionService`, ni ningún otro componente. Esto cumple lo exigido en la Fase 0,
   punto 8: los roles nunca se deducen del cargo; solo se proponen.

### Sugerencias iniciales

Mediante un seeder idempotente que **no sobrescribe** sugerencias ya configuradas:

| Cargo | Roles sugeridos |
|---|---|
| Directora de centro | `supervision`, `intervencion` |
| Trabajadora social | `intervencion` |
| Psicóloga | `intervencion` |
| Auxiliar de servicios sociales | `intervencion` |
| Administrativa | `tramitacion` |
| Abogada | *(sin sugerencia)* |

- **Abogada no tiene sugerencia a propósito.** En el CIAM ejerce con `intervencion`; en el SOJ, con
  `consulta_profesional`. Lo decide `adm_usuarios` en cada alta.
- Si los cargos existen con otros nombres (Fase 0, punto 8), usar los existentes. Si alguno no existe,
  omitirlo e informar. **No crear cargos en esta fase**: los del CIAM ya los crea el mundo de demo.

---

## Fase 5 — Ajuste del mundo «Prueba CIAM»

En `demo_ciam.yaml`, añadir la titulación y el número de colegiado ficticio a cada profesional:

| Profesional | Titulación | Nº colegiado |
|---|---|---|
| Directora | Psicología | sí |
| TS 1-3 | Trabajo Social | sí |
| Psicóloga | Psicología | sí |
| Abogada | Derecho | sí |
| Administrativas | — | — |
| Auxiliares | — | — |

**Ser directora no implica ninguna titulación.** La directora del mundo es psicóloga a propósito, para
probar que un cargo directivo con rol `intervencion` no habilita por sí mismo para actos reservados.
Sus roles siguen siendo `supervision`, `adm_usuarios` e `intervencion`, asignados individualmente
como a cualquier otro usuario. Ningún código debe deducir roles ni habilitaciones del cargo.

**Corrección de la instrucción anterior:** en los escenarios `CiamPiaActiva` y `CiamPiaCerrada`, el
responsable del plan será **solo** una de las 3 TS. La psicóloga, la abogada y la directora pasan a
figurar como **participantes** en parte de los planes: en torno a la mitad, para la psicóloga, un
tercio, para la abogada, y unos pocos para la directora.

El mundo debe seguir cargando de forma idempotente. Si ya se cargó en local, la segunda carga debe
actualizar la titulación de los profesionales `TEST_CIAM` **sin crear registros nuevos**.

---

## Tests funcionales

Fichero nuevo: `Modules/Usuarios/tests/Feature/HabilitacionProfesionalTest.php`. Los tests de
integración con cada módulo pueden ir en el fichero de tests del módulo correspondiente.

Convenciones: PHPUnit con `#[Test]`, PostgreSQL (`vida_testing`), patrón Dado/Cuando/Entonces.
Incluir el caso negativo en todas las restricciones.

**Motor de reglas:**
- **TF-HAB-01** — Un usuario sin `Profesional` no está habilitado para ningún acto, ni siquiera para
  uno de nivel 1 sin restricción.
- **TF-HAB-02** — Un acto de nivel 1 sin reglas está permitido para cualquier profesional con rol de
  intervención en la UO.
- **TF-HAB-03** — Un acto de nivel 1 con regla «Psicología»: una psicóloga puede y una TS no.
- **TF-HAB-04** — Un acto de nivel 2 sin reglas se deniega a todos, con el motivo «tipo sin
  habilitación configurada».
- **TF-HAB-05** — Con `exige_colegiacion`, una TS sin número de colegiado es rechazada y una TS con
  número de colegiado es admitida.
- **TF-HAB-06** — La habilitación no sustituye al rol: una TS habilitada pero sin rol `intervencion`
  en la UO no puede crear el plan.
- **TF-HAB-07** — Una regla con `activa = false` no habilita.
- **TF-HAB-08** — Un acto incoherente con el tipo (`responsable_plan` sobre `TipoFicha`) lanza una
  excepción.

**Plan:**
- **TF-HAB-09** — Una abogada con rol `intervencion` no puede crear un plan `pia`. Una TS sí.
- **TF-HAB-10** — Una psicóloga participante de un plan puede registrar un seguimiento, pero no
  editar sus objetivos ni cerrarlo.
- **TF-HAB-11** — Asignar como responsable a un profesional no habilitado es rechazado.
- **TF-HAB-12** — Un plan anterior con responsable no habilitado sigue siendo legible y reasignable a
  una TS, pero su responsable actual no puede cerrarlo.

**Informe:**
- **TF-HAB-13** — Una psicóloga no puede crear un borrador de informe social.
- **TF-HAB-14** — Si la TS pierde la habilitación entre el borrador y la firma, la firma se rechaza y
  el borrador permanece intacto.
- **TF-HAB-15** — La validación antigua por rol del informe social ya no existe: un usuario con el
  rol pero sin titulación de Trabajo Social no puede firmar.

**Autoría frente a lectura:**
- **TF-HAB-16** — Una TS puede **leer** una ficha de un tipo restringido a Psicología, pero no puede
  crearla ni editarla.

**Trazabilidad:**
- **TF-HAB-17** — Tras firmar un informe, `habilitacion_snapshot` contiene profesional, titulación,
  número de colegiado, reglas aplicadas y fecha.
- **TF-HAB-18** — Cambiar después la titulación del profesional o desactivar la regla no altera el
  snapshot ya guardado.
- **TF-HAB-19** — Modificar una regla de habilitación genera una versión (`Versionable`) con el
  estado anterior.

**Mundo:**
- **TF-HAB-20** — En el mundo `demo_ciam`, todos los planes `pia` tienen como responsable a una de
  las 3 TS. La directora (rol `intervencion`, titulación Psicología) no puede crear un plan `pia`.

**Roles sugeridos por cargo:**
- **TF-HAB-21** — Al dar de alta en Filament un usuario cuyo profesional tiene el cargo «Directora de
  centro», el selector de roles aparece pre-rellenado con `supervision` e `intervencion`.
- **TF-HAB-22** — Si `adm_usuarios` quita un rol sugerido antes de guardar, el usuario se crea sin
  ese rol.
- **TF-HAB-23** — Un rol `supervision` pre-rellenado genera la solicitud de aprobación previa y no
  es efectivo hasta que se aprueba.
- **TF-HAB-24** — Cambiar el cargo de un usuario existente no modifica sus roles, y en su ficha
  aparece el aviso con los roles sugeridos del nuevo cargo.
- **TF-HAB-25** — Añadir o quitar roles sugeridos de un cargo no altera los roles de los usuarios que
  ya tienen ese cargo.

---

## Qué NO hacer

- **No** restringir la lectura por habilitación. La lectura sigue gobernada por rol, UO y colectivos
  protegidos.
- **No** construir firma para fichas ni escalas. Lo que requiera firma se modela como informe.
- **No** crear roles ni perfiles nuevos.
- **No** construir delegación ni suplencia de firma.
- **No** construir el vínculo plan ↔ valoración si no existe (Fase 0, punto 5).
- **No** validar titulaciones ni colegiación contra registros externos (colegios profesionales).
  La titulación la declara y mantiene `adm_usuarios`.
- **No** invalidar retroactivamente actos anteriores.
- **No** configurar automáticamente habilitaciones distintas de las de la Fase 4.
- **No** asignar ni retirar roles automáticamente por cargo, ni al dar de alta ni al cambiar de
  cargo. Los roles sugeridos solo pre-rellenan un formulario.

---

## Documentación

- `docs/principios-vida360.md`: nuevo principio **«El permiso efectivo tiene tres dimensiones»**
  (rol, UO y habilitación profesional). Debe explicar:
  - La distinción entre los niveles 1 y 2.
  - Que la habilitación restringe autoría, no lectura ni uso.
  - Que cada acto guarda un snapshot inmutable de la habilitación con la que se ejecutó, por la
    responsabilidad legal del profesional.
- `docs/modulo-usuarios-permisos.md`:
  - Reescribir §1.2 como «producto de tres dimensiones».
  - Añadir una sección nueva «Habilitación profesional» con el modelo de datos y las reglas de
    evaluación.
  - Añadir una subsección «Roles sugeridos por cargo» que deje claro que son una ayuda al alta y
    no una fuente de permisos.
- `docs/modulo-intervencion.md`: responsable frente a participante del plan y habilitación de
  fichas y escalas.
- `docs/modulo-documentos.md`: habilitación de plantillas y comprobación en la firma.
- `docs/glosario.md`:
  - Entradas nuevas «Habilitación profesional» y «Acto profesional (niveles 1 y 2)».
  - Corregir en la entrada «Informe Social» la mención a la «validación por rol».

---

## Criterio de finalización

1. La Fase 0 está documentada en la respuesta de CLI.
2. `TF-HAB-01` a `TF-HAB-25` están en verde.
3. Pasan sin regresiones:
   - `php artisan test Modules/Intervencion/tests/`
   - `php artisan test Modules/Documentos/tests/`
   - `php artisan test Modules/Usuarios/tests/`
   - `php artisan test tests/Feature/Demo/`
4. En local, con el mundo `demo_ciam` cargado:
   - Con `abogada.ciam@vida.local`: se puede abrir la historia de una usuaria, pero no aparece el
     botón de crear plan.
   - Con `ts1.ciam@vida.local`: sí aparece.
5. El informe final de CLI incluye la lista de `TipoPlan` y `PlantillaInforme` que quedan sin
   habilitación configurada (Fase 4).
6. La documentación está actualizada, este fichero está en la tabla de `CLAUDE.md`, hay entrada en
   el CHANGELOG del mes y `SESSION.md` está actualizado.
