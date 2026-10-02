# Informe cualitativo de calidad — VIDA 360

Revisión de lectura del código en `https://github.com/FranciscoUrsua/vida-project`, commit `21f3254` (2026-10-01). No sustituye los tests automáticos de calidad y seguridad. El criterio es de diseño, responsabilidad de las clases y coherencia con las restricciones de dominio de `CLAUDE.md` y `docs/principios-vida360.md`.

Alcance leído: módulos (`Ciudadania`, `Intervencion`, `Agenda`, `Usuarios`, `Documentos`, `Atencion`), scopes y policies, páginas Livewire grandes, `CLAUDE.md`, `BACKLOG.md`, `docs/plan-refactorizacion.md`. Unos 117 000 líneas de PHP de aplicación, 12 módulos, 141 tests, 188 migraciones, 121 modelos.

## Lectura general

El proyecto tiene un marco de dominio claro (historia social única, ámbito de UO, colectivos protegidos, pasado inmutable, Filament para catálogo y Livewire para la operación) y bastante de eso está escrito en servicios y policies. El problema de calidad no es la ausencia de reglas, sino que el código nuevo las rodea. Las páginas Livewire se han convertido en el lugar donde se consulta, se autoriza a medias y se pinta. Los stubs de `app/` conviven con la implementación real del módulo. El backlog ya nombra varios de estos fallos; siguen abiertos y el patrón que los produce sigue siendo el camino fácil para Claude.

---

## Ahora

### 1. La ficha del ciudadano no aplica la policy de colectivos protegidos

`FichaCiudadanoPage::mount()` comprueba solo el rol (`intervencion`, `tramitacion`, `consulta_basica`, `supervision`) y carga con `Ciudadano::withoutGlobalScope(AmbitoUoScope::class)`. La propiedad computada `ciudadano()` repite el bypass. No llama a `CiudadanoPolicy::view`.

Eso contradice la restricción de `CLAUDE.md` §3: un colectivo protegido no es accesible fuera de la UO responsable sin aprobación explícita. `DocumentoPolicy` sí pasa por la policy; la ficha, que es la pantalla que muestra nombre, documento, dirección y teléfono, no. El mismo hueco está anotado en el backlog para la bandeja de asignaciones del supervisor y para `BuscadorPersonasCita` (el documento exacto encuentra a la persona protegida).

Hay que cerrarlo en el mismo cambio: `Gate::authorize('view', $c)` en `mount()` y en cada computed que recarga el ciudadano, y un test en negativo (profesional de otra UO, colectivo protegido, sin acceso aprobado → 403, sin filtrar datos a la vista). Misma prueba para el buscador de citación y la bandeja del supervisor.

### 2. `withoutGlobalScopes()` se ha vuelto el modo normal de leer expediente

El scope de ámbito está bien pensado (`AmbitoUoScope`, par scope + policy en `HistoriaSocialService`). En la práctica se desactiva en el binding de ruta (`IntervencionServiceProvider`: `HistoriaSocial::withoutGlobalScopes()->findOrFail`), en relaciones (`Cita`, `SolicitudCita`, `AsignacionPendiente`), en queries de accesos y en modales (`PrescribirRecursoModal`, `AsignarPlazaModal`, `RegistrarValoracionPage`, `CiudadanoPage`).

El comentario del binding es razonable (querer 403 y no 404). Solo es seguro si cada consumidor autoriza justo después. Hoy no hay un único sitio que lo garantice: cada página decide. Un modal nuevo que copie el `find` del de al lado hereda el bypass y se olvida del `Gate`.

Hasta tener un acceso único (`ExpedienteVisible::historia($id, $user)` que quite el scope y autorice, o falle), no debería añadirse otro `withoutGlobalScopes()` en Livewire.

### 3. Dos modelos `Apunte` y dos policies que no se hablan

- `App\Models\Apunte`: stub, con `AmbitoUoScope`. Lo usa `Modules\Usuarios\Policies\ApuntePolicy` (173 líneas), registrada en `UsuariosServiceProvider`.
- `Modules\Intervencion\Models\Apunte`: el modelo real. Lo usan citas, el expediente y `Modules\Intervencion\Policies\ApuntePolicy` (58 líneas), registrada en `IntervencionServiceProvider`.

No pisan el mismo `Gate::policy` porque son clases distintas. El riesgo es de divergencia: la regla de anotación privada («solo el autor, ni el supervisor») vive documentada en el stub y puede no ser la que ejecuta la pantalla. Hay que dejar un solo modelo, una sola policy, y borrar el stub y su registro. Hasta entonces, un cambio en la policy de Usuarios no protege el expediente.

### 4. Páginas que ya no se pueden cambiar con seguridad

| Clase | Líneas | Vista |
|---|---|---|
| `PlanPage` | 1 669 | 1 261 |
| `CiudadanoPage` | 1 348 | 1 121 |
| `FichaCiudadanoPage` | 1 096 | — |
| `AuditorUi` | 1 141 | — |

`PlanPage` concentra diagnóstico, objetivos, firma, activación, prestaciones y catálogos. `CiudadanoPage` concentra timeline, accesos, unidad de convivencia, citas y herramientas. La vista del expediente consulta Eloquent dentro del Blade (`Ciudadano::find($ucCiudadanoSeleccionado)`). Cada computed vuelve a pegar a base de datos en cada render, y `wire:poll.60s` de `AlertaToast` repinta el árbol entero.

No hace falta el rediseño visual. Hace falta partir antes de la siguiente funcionalidad: un hijo por bloque (timeline, accesos, UC, firma) y la escritura solo en servicios que ya autorizan. El propio backlog lo pide; mientras no se haga, el punto 1 se va a reabrir en el siguiente modal.

---

## Pronto

### 5. El núcleo sigue en `app/` y el módulo tiene un doble

`docs/plan-refactorizacion.md` dice mover `Ciudadano`, `HistoriaSocial`, `Apunte` y sus servicios al módulo. No está hecho. Además hay dos `HistoriaSocialService`: un stub en `App\Services` (el todo dice moverlo «cuando Intervención esté operativo») y el de dominio en `Modules\Intervencion\Services`, que sí aplica scope + policy. Intervención ya está operativo. El stub es una trampa para el siguiente cambio de mensajería.

Conviene ejecutar el plan en un commit solo de movimiento, sin cambiar comportamiento, y borrar el stub en el mismo commit.

### 6. Versionado incompleto respecto al requisito

El requisito y `CLAUDE.md` piden `Versionable` en toda entidad no auxiliar. El trait está en unidad de convivencia, tipo documental, profesional y poco más. `Ciudadano` no lo usa. En la base compartida hay un `sexo = 'H'` fuera del catálogo `M/F/D`, guardado antes de alinear la ficha; el backlog pide corregirlo editando para que quede versionado. Si el modelo no versiona, esa corrección no deja rastro útil.

Inventariar entidades de persona, documento de identidad, centro y dirección, y enganchar el trait antes de seguir ampliando fichas.

### 7. `$guarded = []` en modelos con datos de persona o de cita

Catorce modelos, entre ellos `Cita`, `Informe`, `PisoFirmado`, `EventoAgenda`. Con un `create($request->all())` o un `fill` de Livewire mal acotado, entran columnas que la policy no mira. No es un hallazgo de exploit; es una puerta que el estilo actual deja abierta. En modelos de ciudadano, cita, informe y plan, lista blanca (`$fillable`) o DTO del servicio.

### 8. Línea base de PHPStan que ya no discrimina, y CI en rojo

`phpstan-baseline.neon` tiene 3 151 líneas y 523 ignores. La muestra es casi toda `missingType.generics` en relaciones Eloquent. El último commit del repo es «CI de calidad en rojo (PHPStan sin memoria)». Una base así de grande más un job que no cabe en memoria significa que el semáforo de calidad no está mirando el código nuevo. Subir memoria del job o excluir genéricos de Eloquent, y no meter en la base errores de llamadas indefinidas o de argumentos. La base no debería crecer en un commit de funcionalidad.

### 9. Assets de Filament versionados en `public/`

`public/js/filament` pesa unos 3,5 MB y no está en `.gitignore`. Se desalinean del `composer.lock` en cuanto alguien publica assets en local. Publicarlos en el build, no en git.

---

## Cuando haya tiempo

### 10. `CLAUDE.md` mezcla reglas, bitácora y catálogo de encargos

201 líneas. La sección 5 es un índice de instrucciones CLI ya ejecutadas. La sección 4 manda escribir en `CHANGELOG.md`, pero el historial real está en `CHANGELOG-052026.md`, `CHANGELOG-062026.md` y `CHANGELOG-092026.md` (decenas de miles de líneas cada uno). PHPDoc obligatorio en todo método público empuja a documentar páginas de 1 500 líneas en vez de partirlas. El efecto es que Claude cumple el ritual (docblock, changelog, push a `master`) y se salta la restricción de dominio, que está más abajo y no tiene test asociado.

### 11. Consultas y catálogos reconstruidos en cada render

`$herramientas` del expediente, `Configuracion::logoUrl()` en el shell, `$datos` / `$yaEnPlan` dentro de bucles de `plan-page`. Correcto funcionalmente, caro y frágil cuando el poll de alertas dispara un morph completo. Cache de request o un componente de alertas que solo sondee el contador.

### 12. Nombres en inglés en la regla, español en el código de auth

`CLAUDE.md` pide métodos en inglés. Login usa `mostrar`, `autenticar`, `cerrarSesion`. No rompe nada. Unificar solo cuando se toque ese controlador, no en una pasada cosmética.

### 13. Base local y staging son la misma

Está en `SESSION.md`. No es un defecto del código, pero cualquier migración o `demo:reset` lanzado «en local» pisa staging. Separar connection de desarrollo cuando se pueda.

---

## Propuesta para `CLAUDE.md`

Añadir una sección corta, encima de las convenciones de PHPDoc, con prohibiciones comprobables. El índice de instrucciones CLI puede vivir en `docs/instrucciones-cli/README.md`; no hace falta releerlo en cada sesión.

```markdown
## 0. Prohibiciones (se comprueban en el diff, no se negocian)

1. Prohibido cargar `Ciudadano`, `HistoriaSocial`, `Apunte` o `PlanDeIntervencion`
   con `withoutGlobalScope` o `withoutGlobalScopes` en un `mount()`, computed,
   acción Livewire o Blade sin un `Gate::authorize` sobre ese mismo registro
   en el mismo método. Si hace falta saltarse el scope para distinguir 403 de 404,
   el salto vive en una sola clase (`ExpedienteVisible` o el servicio de dominio),
   nunca copiado en la página.
2. Prohibido abrir la ficha, el buscador o una bandeja de una persona de colectivo
   protegido comprobando solo el rol. La policy es la que decide. Todo cambio en
   estas pantallas incluye un test en negativo.
3. Un modelo, una policy, un `Gate::policy`. No crear stub en `App\Models` si
   el módulo ya tiene la clase. No registrar la policy en dos providers.
4. Una página Livewire no pasa de ~400 líneas. Si el cambio la acerca a ese
   tamaño, se parte antes de añadir la funcionalidad. Prohibido Eloquent o
   `DB::` en Blade.
5. Prohibido `$guarded = []` en modelos con datos de ciudadano, cita, informe,
   plan o documento. Escritura por servicio o por `$fillable`.
6. No añadir otro `*Service` en `App\Services` para un concepto que ya tiene
   servicio en un módulo. El stub se borra en el mismo commit que deja de usarse.
7. No ampliar `phpstan-baseline.neon` para tapar un error del código nuevo.
8. No dar por cerrada una tarea de expediente o ficha sin el test que fallaría
   si un profesional de otra UO lee a una persona protegida.
```

Esas ocho líneas atacan los cuatro fallos que se están repitiendo: bypass del scope, comprobación solo por rol, stub duplicado y página dios. El PHPDoc obligatorio puede bajar a «clase y métodos públicos de servicio y policy»; en una página Livewire, el docblock no está evitando los bugs que importa evitar.
