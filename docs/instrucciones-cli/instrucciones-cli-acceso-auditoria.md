# Instrucciones CLI — Acceso y auditoría de expedientes

Encargo para Claude CLI. Corregir el salto de la policy de colectivos protegidos y el hecho de que casi ninguna lectura de expediente queda en `audits`. Escribir los tests de este documento, implementar el mínimo para que pasen y lanzarlos.

No es una tarea de estilo. No partir páginas, no mover módulos, no ampliar `phpstan-baseline.neon`.

Commit de partida: `21f3254`. Leer antes `CLAUDE.md` §3, `docs/modulo-auditoria.md` §3 y §10.6, `docs/modulo-usuarios-permisos.md` y `app/Policies/CiudadanoPolicy.php`.

## Hechos que no hay que redescubrir

- `FichaCiudadanoPage::mount()` y `ciudadano()` cargan con `Ciudadano::withoutGlobalScope(AmbitoUoScope::class)` y solo comprueban el rol. No llaman a `CiudadanoPolicy::view`.
- El expediente operativo es `GET /intervencion/ciudadano/{historia}` (`CiudadanoPage`). La ruta no lleva `audit.ciudadano`. El parámetro no se llama `ciudadano`, así que el middleware actual no la vería aunque se añadiera.
- `BuscarCiudadanoPage::registrarAccesoNivel2()` escribe `Log::info('acceso_nivel2')` y un TODO que dice que la tabla `audits` no existe. Existe. El test `TF-LW-BUS-06` solo afirma ese log: hay que cambiar el test, no conservar el log como prueba.
- `AccionAuditEnum::AccesoRestringido` no se escribe en ningún sitio de producción. `tests/Feature/Auditoria/AuditAccesoRestringidoTest.php` solo llama a `AuditService` a mano. No sustituye a los tests de pantalla.
- `AuditObserver` cubre crear, editar y eliminar de modelos con el trait `Auditable`. No cubre lecturas. No hace falta rehacerlo.
- El middleware `AuditarAccesoCiudadano` es complementario. No basta. Las acciones Livewire van a `/livewire/update` y no traen `{ciudadano}`.

## Regla de producto que el código debe cumplir

1. Nadie abre ficha, expediente, plan, valoración ni ficha de valoración de una persona si `CiudadanoPolicy::view` (o la policy del recurso, que debe delegar en ella) devuelve false. El resultado es 403. La respuesta no incluye nombre, apellidos, documento, dirección, teléfono, email ni alias.
2. Un colectivo protegido fuera de la UO del profesional solo es visible con un `AccesoProtegido` aprobado y vigente para ese usuario y ese ciudadano. Sin eso, 403.
3. Toda lectura intencional de una persona (abrir ficha, abrir expediente, abrir plan, abrir valoración, descarga de documento ya cubierta) crea exactamente una fila en `audits` con `user_id`, `accion`, `auditable_type`, `auditable_id`, `ciudadano_id`, `ip` y `contexto.ruta`.
4. Si la persona es de colectivo protegido, esa fila usa `accion = acceso_restringido`, no `ver`. El contexto incluye `autorizado` (bool) y, si hay solicitud, `acceso_protegido_id`.
5. Un acceso denegado también deja fila `acceso_restringido` con `autorizado = false` y `motivo = denegado`. La respuesta sigue siendo 403 y sin datos.
6. Listar o buscar no equivale a abrir. Una búsqueda que ya muestra nombre de alguien a quien la policy niega el `view` es un fallo. Los resultados de otra UO de una persona protegida no incluyen nombre ni documento; como mucho el hecho de que existe una restricción y el botón de solicitar acceso.

## Diseño mínimo de implementación

Un solo punto de entrada, no un `authorize` copiado en cada página.

- Crear `App\Services\AccesoExpediente` (nombre fijo) con dos métodos:
  - `ciudadano(User $user, int $ciudadanoId): Ciudadano`
  - `historia(User $user, int $historiaId): HistoriaSocial`
- Cada método carga sin `AmbitoUoScope`, llama a `Gate::authorize('view', $modelo)` y, gane o pierda la policy, registra la auditoría antes de relanzar la `AuthorizationException`.
- Si `Gate::authorize` lanza, capturar, registrar `acceso_restringido` con `autorizado = false` solo cuando el ciudadano es protegido, y relanzar. Si no es protegido, un 403 de ámbito ordinario se registra como `ver` con `autorizado = false` o no se registra: elegir `ver` + `autorizado = false` y documentarlo en el commit. No inventar otra acción de enum.
- Si autoriza y el ciudadano tiene `colectivo_extra_protegido`, acción `acceso_restringido` y `autorizado = true`. Si no, acción `ver`.
- Idempotencia de la petición: una apertura de pantalla, una fila. No registrar otra vez en cada computed que recarga el mismo id en el mismo request. Guardar en el contenedor los ids ya anotados en ese request.
- `FichaCiudadanoPage`, `CiudadanoPage`, `PlanPage`, `RegistrarValoracionPage`, `VerFichaPage` y `BuscarCiudadanoPage::registrarAccesoNivel2` obtienen el registro por este servicio. Quitar los `withoutGlobalScope(s)` de esos `mount` y computed. El binding de ruta puede seguir sin scope solo si la página autoriza por el servicio antes de leer atributos.
- `registrarAccesoNivel2` deja de usar `Log::info`. Redirige solo después de que el servicio haya autorizado y anotado. Si no autoriza, no redirige.
- El buscador de citación (`BuscadorPersonasCita`) y la bandeja de asignaciones del supervisor no muestran nombre ni documento de una persona protegida sin acceso aprobado. Reutilizar la policy; no duplicar el criterio.

No registrar lecturas de relaciones Eloquent internas. No tocar Filament en este encargo.

## Tests

Añadir `vida/tests/Feature/Acceso/AccesoExpedienteTest.php`. Trait `RefreshDatabase`, seed de `PermisosSeeder` y `RolesSeeder`, dos UO, profesional de intervención en la UO A, ciudadano protegido cuya historia está en la UO B, y otro ciudadano no protegido en la UO B. Seguir el estilo de `BuscarCiudadanoPageTest` para usuario, UO y `UsuarioUo`.

Identificadores `TF-ACC-01` a `TF-ACC-12`. Un test, un comportamiento. Afirmar también la ausencia de datos en la respuesta 403 (`assertDontSee` del nombre y del documento).

| Id | Dado | Cuando | Entonces |
|---|---|---|---|
| TF-ACC-01 | Profesional UO A, ciudadano no protegido con historia en UO A | `GET` ficha `/ciudadania/ciudadano/{id}` | 200 y una fila `audits` `ver` con su `ciudadano_id` |
| TF-ACC-02 | Mismo profesional, ciudadano protegido con historia solo en UO B, sin `AccesoProtegido` | `GET` ficha | 403, cuerpo sin nombre ni documento, una fila `acceso_restringido` con `autorizado = false` |
| TF-ACC-03 | Igual que 02 más `AccesoProtegido` aprobado y `acceso_valido_hasta` futuro | `GET` ficha | 200 y una fila `acceso_restringido` con `autorizado = true` y `acceso_protegido_id` |
| TF-ACC-04 | Igual que 03 con `acceso_valido_hasta` pasado | `GET` ficha | 403 y fila `autorizado = false` |
| TF-ACC-05 | Profesional UO A, historia de no protegido en UO B | `GET /intervencion/ciudadano/{historia}` | 403. Hoy el binding sin scope puede dejar ver la historia: el test debe fallar antes del arreglo |
| TF-ACC-06 | Historia propia, no protegido | `GET` expediente | 200 y exactamente una fila `ver` para ese ciudadano. Repetir un computed no añade otra fila en la misma petición |
| TF-ACC-07 | Historia de protegido en otra UO, sin aprobación | `GET` expediente y `GET` plan | 403 en ambas, sin nombre, una fila `acceso_restringido` por intento |
| TF-ACC-08 | `registrarAccesoNivel2` sobre historia de otra UO, no protegido, sin permiso de consulta | llamar a la acción | no redirige a `intervencion.ciudadano.show`; no basta un `Log::info` |
| TF-ACC-09 | Búsqueda por documento exacto de protegido de otra UO | `BuscarCiudadanoPage` y `BuscadorPersonasCita` | el resultado no contiene nombre ni documento; sí puede ofrecer solicitar acceso |
| TF-ACC-10 | Bandeja de asignaciones del supervisor con pendiente de persona protegida de fuera de su ámbito | pintar la bandeja | no aparece el nombre |
| TF-ACC-11 | Descarga de documento ya auditada | no romper | el test existente de documentos sigue pasando; no duplicar fila si el controlador también registra. Si se duplica, quitar la llamada suelta del controlador y dejar el servicio, o excluir documentos de este encargo y afirmar que sigue habiendo una fila |
| TF-ACC-12 | Profesional sin rol operativo | `GET` ficha | 403 y ninguna fila, o una fila `ver` con `autorizado = false`. Elegir la segunda y fijarla |

Actualizar `TF-LW-BUS-06`: debe afirmar fila en `audits`, no `Log::shouldReceive`. Borrar el TODO de `registrarAccesoNivel2`.

No marcar tests incompletos. Si un flujo de urgencia preautorizada no existe, no lo inventes en este encargo y no lo dejes `markTestIncomplete` nuevo.

## Orden

1. Escribir `AccesoExpedienteTest` y ajustar `TF-LW-BUS-06`. Correrlos y comprobar que fallan por el motivo esperado (200 donde debe haber 403, o cero filas en `audits`).
2. Implementar `AccesoExpediente` y cablear las pantallas de la lista.
3. Volver a lanzar. No dar por bueno un 403 que aún filtra el nombre en el HTML.

## Comandos

Desde `vida/`:

```bash
php artisan test --filter=AccesoExpedienteTest
php artisan test --filter=acceso_nivel_2_registra_en_log_de_auditoria
php artisan test tests/Feature/Auditoria
```

La base de tests es `vida_testing` en PostgreSQL, no la base compartida con staging. No lanzar `demo:reset`. No migrar la base de staging.

## Hecho cuando

- Los doce tests pasan.
- `rg "withoutGlobalScope" Modules/Ciudadania/app/Http/Livewire/FichaCiudadanoPage.php Modules/Intervencion/app/Http/Livewire/CiudadanoPage.php` no devuelve cargas de ciudadano o historia en `mount` ni computed.
- `rg "Log::info\('acceso_nivel2'" vida` no devuelve nada.
- `SESSION.md` dice que el siguiente paso ya no es el hueco de colectivos protegidos, y el backlog marca ese punto como cerrado con la fecha y los ids `TF-ACC-01` a `12`.
- Un commit: `fix(acceso): policy y auditoria de lectura de expediente`.
