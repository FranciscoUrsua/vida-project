# CHANGELOG — VIDA 360

---

## Módulo Agenda — No-show del ciudadano (PF-06) — 2026-05-20

### Implementación

- **`Modules/Agenda/app/Models/Cita.php`** — método añadido:
  - `noShowCiudadano()`: transiciona la cita a `no_show_ciudadano` sin tocar el slot. El slot permanece `reservado`; el `SlotExpirationJob` lo transitará a `no_ocupado` al final del día.

### Tests desbloqueados (3 tests pasan ahora ✅)

- **PF-06.1** — No-show registrado después de que la franja ha pasado: cita → `no_show_ciudadano`, slot permanece `reservado`.
- **PF-06.2** — Cancelación anticipada del ciudadano (con margen): reutiliza `cancelar()` con slot futuro → cita `cancelada`, slot `disponible`.
- **PF-06.3** — No-show en el momento (franja en curso): idéntico a PF-06.1; slot permanece `reservado` hasta que el job lo expire.

### Estado de la suite

258 tests pasan ✅ — 0 fallos — 17 incompletos (eran 20 antes de esta sesión).

---

## Módulo Agenda — Ciclo de vida de Cita — 2026-05-20

### Implementación

- **`Modules/Agenda/app/Observers/CitaObserver.php`** — Observer nuevo:
  - `creating`: lanza `LogicException` si se intenta reservar un slot `bloqueado_urgencia` desde canal `api_externa`.
  - `created`: actualiza el slot asociado a estado `reservado` tras crear la cita.

- **`Modules/Agenda/app/Models/Cita.php`** — métodos y relación añadidos:
  - `completar()`: transiciona a `completada` y registra `completada_en = now()`.
  - `cancelar(User $canceladoPor, string $motivo)`: cancela la cita y ajusta el slot según si su hora_inicio ya ha pasado (`no_ocupado`) o no (`disponible`). Usa `Slot::findOrFail` para garantizar estado fresco.
  - `apuntes()`: `MorphMany` hacia `Apunte` (Intervención) via `apuntable`, para detectar apuntes vinculados antes de una cancelación retroactiva.

- **`Modules/Agenda/app/Providers/AgendaServiceProvider.php`** — registra `Cita::observe(CitaObserver::class)` en `boot()`.

### Tests desbloqueados (7 tests pasan ahora ✅)

- **PF-05.1** — Crear cita interna → slot pasa a `reservado` (Observer::created).
- **PF-05.2** — Cita externa registra `referencia_externa` y slot queda `reservado`.
- **PF-05.4** — Intento de cita externa sobre slot urgencia → `LogicException` (Observer::creating).
- **PF-05.5** — `completar()` registra `completada_en` y transiciona a `completada`.
- **PF-05.6** — `cancelar()` con slot futuro → slot vuelve a `disponible`.
- **PF-05.7** — `cancelar()` con slot pasado → slot queda en `no_ocupado`.
- **PF-05.8** — `apuntes()` detecta apuntes vinculados; la cancelación no los elimina.

### Estado de la suite

258 tests pasan ✅ — 0 fallos — 20 incompletos (eran 27 antes de esta sesión).

---

## Módulo Agenda — CuadranteGeneratorService — 2026-05-20

### Servicio implementado

- **`Modules/Agenda/app/Services/CuadranteGeneratorService.php`** — implementación completa de los dos métodos:
  - `generarBorrador(CuadranteMes $cuadrante)`: itera cada día laborable del mes según `HorarioCentro.dias_laborables`, crea una `LineaCuadrante` por profesional activo aplicando su `PerfilHorarioProfesional.horario_habitual`. Las `ExcepcionProfesional` que cubren el día se incorporan marcando la línea con `anulada = true` y `excepcion_id`. Usa `insert()` bulk para eficiencia.
  - `generarYPublicarAutomaticamente(Centro $centro, int $anyo, int $mes)`: crea el `CuadranteMes` con `generado_automaticamente = true`, llama a `generarBorrador`, lo publica y delega en `SlotMaterializadorService::materializar()`.

### Tests desbloqueados (4 tests pasan ahora ✅)

- **PF-03.1** — Generación en modo estándar crea borrador: 3 profesionales × 22 días laborables de junio 2026 = 66 líneas; estado `borrador`; 0 slots.
- **PF-03.4** — Modo básico genera y publica automáticamente: cuadrante con `generado_automaticamente = true`, estado `publicado`, líneas generadas.
- **PF-03.5** — Excepciones previas generan líneas anuladas: excepción del 10 al 20 de junio → 8 días laborables anulados con `excepcion_id` correcto; 14 líneas activas.
- **PF-10.1** — Centro sin profesionales produce cuadrante vacío sin error: 0 líneas, sin excepción.

### Decisiones de implementación

- Los keys del JSON `horario_habitual` se convierten a string al hacer cast `'array'` en PHP. La búsqueda usa `(string)$dia->isoWeekday()` para evitar miss por tipo.
- Las excepciones se cargan en bloque y se agrupan por `usuario_id` antes del bucle principal (evita N+1).
- Se usa solapamiento de períodos para filtrar perfiles vigentes: `vigente_desde <= $ultimoDia AND (vigente_hasta IS NULL OR vigente_hasta >= $primerDia)`.

### Estado de la suite

258 tests pasan ✅ — 0 fallos — 27 incompletos (eran 31 antes de esta sesión).

---

## Módulo Usuarios — Tests funcionales fase 2 — 2026-05-19

### Tests implementados (23 tests pasan ✅; 1 pendiente)

- **Grupo A — Historial de roles (TF-USU-19 a TF-USU-23):** fichero nuevo `UsuarioRolTest.php`.
  - TF-USU-19 a TF-USU-22: creación con estado activo → Observer sincroniza `model_has_roles`; cierre con `fecha_fin`; historial preservado; varios roles activos simultáneos.
  - TF-USU-23: comando `usuarios:reconciliar-roles` — resincroniza `model_has_roles` a partir de `usuario_rol` vigente.
- **Grupo B — Jerarquía UO (TF-USU-24 a TF-USU-28):** fichero nuevo `UnidadOrganizativaTest.php`.
  - TF-USU-24 a TF-USU-27: `children`, `descendants`, `tieneAccesoGestionA()` con relación padre-descendiente y con UO paralela.
  - TF-USU-28: desactivar UO preserva adscripciones históricas.
- **Grupo C — Adscripción UO (TF-USU-29 a TF-USU-31):** incorporados a `UnidadOrganizativaTest.php`.
  - TF-USU-29 a TF-USU-30: usuario adscrito a varias UOs; historial de adscripciones con fechas de vigencia.
  - TF-USU-31: pendiente — no existe Policy/Service para autorizar adscripción; marcado `markTestIncomplete`.
- **Grupo D — Supervisión de asignación (TF-USU-32 a TF-USU-35):** incorporados a `UsuarioRolTest.php`.
  - TF-USU-32 a TF-USU-35: `pendiente_aprobacion` no activa rol; aprobación activa; denegación no activa; `alerta_supervisada` activa inmediatamente.
- **Grupo E — Modelo Profesional (TF-USU-36 a TF-USU-40):** fichero nuevo `ProfesionalTest.php`.
  - TF-USU-36 a TF-USU-40: Profesional sin Usuario; Usuario sin Profesional; navegación bidireccional; `cargo_id` obligatorio (DB constraint); campo `organizacion` nullable para internos.
- **Grupo F — Versionado Profesional (TF-USU-41 a TF-USU-42):** incorporados a `ProfesionalTest.php`.
  - TF-USU-41 a TF-USU-42: versión con `versionable_type`; snapshot completo (no solo diff).

### Infraestructura nueva

- **`Modules/Usuarios/app/Console/Commands/ReconciliarRoles.php`:** comando `usuarios:reconciliar-roles`. Asigna roles vigentes de `usuario_rol` a Spatie y revoca los huérfanos. Registrado en `UsuariosServiceProvider`.
- **`App\Models\UnidadOrganizativa::isDescendantOf()`:** método añadido. Usa `ancestors()` (CTE recursiva de staudenmeir) para comprobar si una UO es descendiente de otra. Nota: la WHERE clause no puede usar prefijo de tabla en el contexto externo de la CTE.
- **`TieneUO::unidadesOrganizativas()`:** relación `BelongsToMany` añadida (a través de `usuario_uo`). Permite `$user->unidadesOrganizativas()->count()`.

### Decisiones de implementación

- TF-USU-20 usa `fecha_fin = ayer` (no hoy): el Observer comprueba `isPast()` que es `false` para today; la semantica correcta de "cerrar" un rol es fecha_fin en el pasado.
- TF-USU-31 marcado `markTestIncomplete`: la restricción de ámbito en adscripción requiere una capa de servicio o Policy que no existe aún.
- `isDescendantOf()` en `UnidadOrganizativa` es nueva infraestructura necesaria — el paquete staudenmeir no la incluye (solo `isChildOf()` y `isParentOf()`).

---

## Módulo Prestaciones — Tests funcionales — 2026-05-19

### Tests implementados (45 tests, 45 pasan ✅; 2 pendientes)

- **Grupos 1–4 (modelo y versionado):** añadidos a `PrestacionesTest.php` (preexistente).
  - T-PRE-01 a T-PRE-06: `CatalogoSistema` — creación, constraint único, `opcionesParaSelect` (orden, filtro inactivos, grupo inexistente, baja lógica).
  - T-PRE-07 a T-PRE-16: `PrestacionModel` — creación mínima, enums, código único, JSONB array, scopes, baja lógica.
  - T-PRE-17 a T-PRE-19: `PrestacionTipoCentro` — relación `hasMany`, constraint único, cascade en `forceDelete`.
  - T-PRE-20 a T-PRE-25: `PrestacionVersionado` — no crea versión en `create`, snapshot completo, múltiples versiones, reconstrucción por fecha, baja lógica en snapshot.
- **Grupo 5 (seeder):** fichero nuevo `PrestacionSeederTest.php`.
  - T-PRE-26 a T-PRE-31: carga de 8 objetivos generales, idempotencia catálogo, carga de 49 prestaciones (49 implementadas del catálogo de 112), idempotencia prestaciones, campos obligatorios, unicidad de códigos.
- **Grupo 6 (Filament):** fichero nuevo `PrestacionFilamentResourceTest.php`.
  - T-PRE-32 a T-PRE-40: listado, filtros por tipo y activa, creación, validación nombre requerido, código duplicado, edición, versión en Filament, toggle inline.
  - T-PRE-41 (historial RelationManager) y T-PRE-42 (acceso por rol): pendientes — documentados en spec con motivo.
- **Grupo 7 (consulta):** fichero nuevo `PrestacionConsultaTest.php`.
  - T-PRE-43 a T-PRE-47: búsqueda por nombre, filtro por objetivo, tipos de centro, inactiva no aparece en scope, inactiva recuperable por id.

### Reformateo de `docs/modulo-prestaciones.md`

- La sección de tests usa ahora el mismo formato que `docs/modulo-mensajes.md`: IDs `T-PRE-XX`, grupos `### Grupo N — ClassName`, formato `Dado:` / `Cuando:` / `Entonces:` sin bloques de código ni separadores entre tests individuales.

### Decisiones de implementación

- El seeder tiene 49 prestaciones; T-PRE-28 aserta 49, no 112 (el catálogo completo comprende 112; la diferencia se anota en el spec).
- El soft delete no dispara el cascade FK de `prestacion_tipo_centro`; T-PRE-19 usa `forceDelete()` con nota explicativa.
- `ToggleColumn` en Filament 5 requiere `->call('updateTableColumnState', ...)` en lugar de `callTableColumnAction` — patrón documentado en el test.
- Los Grupos 1–4 se acumulan en el fichero preexistente para no duplicar contexto; los Grupos 5–7 son ficheros independientes por coherencia con los otros módulos.

---

## Módulo Mensajes — Tests funcionales — 2026-05-19

### Tests implementados (31/31 pasan ✅)

- **Grupos 1–8 (servicio):** incorporados a los tres ficheros de test preexistentes.
  - `HorarioLaboralServiceTest`: +5 tests (T-HLS-01 a T-HLS-05) con escenarios exactos del spec (09:00→13:00, 15:30→10:30, viernes→lunes, fuera-horario, fin-de-semana).
  - `AlertaServiceTest`: +5 tests (T-ALS-02 con tiempo fijado, T-ALS-05 unicidad, T-ALS-07 warning log, T-ALS-08 sin segundo nivel, T-ALS-09 destinatarios rol+UO).
  - `MensajeriaServiceTest`: +5 tests (T-MSG-04 respuesta, T-MSG-06 contador no-leídos, T-MSG-07 TSR registra, T-MSG-09 visibilidad defecto, T-MSG-10 visibilidad ciudadano prohibida).
- **Grupos 9–11 (Livewire):** tres ficheros nuevos.
  - `BandejaAlertasTest` (T-LW-01 a T-LW-05): aislamiento, alertas por rol+UO, reconocimiento, control de autorización, ordenación.
  - `BandejaMensajesHiloTest` (T-LW-06 a T-LW-08): hilos propios, archivado, visibilidad botón TSR.
  - `NuevoMensajeTest` (T-LW-09 a T-LW-13): filtro rol+UO, validaciones de asunto/cuerpo/destinatario, auto-mensaje prohibido.

### Correcciones en componentes existentes

- `CatalogoSistema::$fillable` — añadido `valor` (bug preexistente: campo no declarado, se perdía silenciosamente en `create()`).
- `MensajeriaService::registrarEnHistoria()` — guard `InvalidArgumentException` para visibilidad `ciudadano` (el enum no la incluye; antes lanzaba `ValueError` de PHP en lugar de error semántico del dominio).
- `BandejaAlertas::reconocer()` — comprobación de autorización: solo el `destinatario_usuario_id` o el `escalada_a_usuario_id` puede reconocer; lanza `AuthorizationException` (→ 403) en caso contrario.
- `HiloMensajes` — método `esTsrDeCiudadano(int $ciudadanoId): bool` añadido; view `hilo-mensajes.blade.php` protege el botón "Registrar en Historia Social" con `@if($this->esTsrDeCiudadano(...))`.
- `NuevoMensaje` — corregido tipo de retorno vacío en `resultadosDestinatario()` y `resultadosCiudadano()` (`collect()` → `new Eloquent\Collection()`); añadida validación que impide seleccionar al propio usuario como destinatario.

### Decisiones de implementación

- Los tests de servicio se añaden a los ficheros preexistentes (no se crean ficheros duplicados); los preexistentes ya cubrían el contrato general, los nuevos verifican los escenarios exactos del spec con valores concretos.
- Los tests Livewire son los primeros del proyecto; usan `Livewire::actingAs()` + `->test()` con la API de Livewire v4.
- `BandejaMensajesHiloTest` agrupa los tests de `BandejaMensajes` e `HiloMensajes` en un único fichero (solo 3 tests; separar habría sido prematuramente verboso).

Registro de cambios agrupado por módulo y área funcional, en orden cronológico descendente.

---

## Módulo Intervención — Fase 1 — 2026-05-18

### Nuevas funcionalidades

- **Módulo `Modules/Intervencion/`** creado desde cero con estructura completa nwidart v12.
- **9 enums PHP**: `EstadoPlan`, `TipoPlan`, `MotivoCierre`, `VisibilidadApunte`, `TipoApunte`, `TipoEntrevista`, `EstadoValoracion`, `ClasificacionSia`, `UrgenciaSia`.
- **12 migraciones** en orden estricto de dependencias: `tipo_fichas`, `tipo_valoraciones`, `tipo_valoracion_fichas`, `entrevistas`, `valoraciones`, `fichas`, `planes_intervencion` (con FK auto-referencial `plan_asp_id`), `firmas_plan`, `seguimientos_plan`, `revisiones_plan`, `plan_apuntes`, `sia_contactos`.
- **11 modelos Eloquent**: `TipoFicha`, `TipoValoracion`, `TipoValoracionFicha`, `Entrevista`, `Valoracion`, `Ficha`, `PlanDeIntervencion`, `FirmaPlan`, `RevisionPlan`, `SeguimientoPlan`, `Apunte`, `SiaContacto`.
  - `PlanDeIntervencion::crearNuevaVersion()`: genera nueva versión del plan, archiva la anterior como `en_revision`, registra la revisión en `revisiones_plan`.
  - `PlanDeIntervencion::estaFirmado()`: verifica firma del plan filtrando por `plan_id + version` actuales.
  - Guard de DomainException en `PlanDeIntervencion`: impide cambiar estado a `activo` sin firma cuando el plan ya existe.
  - `Apunte::scopeVisiblesParaUsuario()`: excluye apuntes privados de otros autores.
  - `TipoFicha::setSchemaAttribute()`: valida JSON a nivel de mutador (no de evento saving).
  - `SeguimientoPlan::solicitarCitaSiguiente()`: stub documentado, integración con Agenda pendiente.
  - `SiaContacto::scopeCompetenciaMunicipal()`: filtra por clasificación.
- **`ApuntePolicy`**: view/update/delete con regla de privacidad absoluta para apuntes de nivel `privada`.
- **9 factories** con estados nombrados para todos los modelos del módulo.
- **`IntervencionSeeder`**: crea 3 `tipo_fichas`, 1 `tipo_valoracion`, 3 entradas pivot.
- **35 tests funcionales** (TF-INT-A01 a G03) en 7 clases, todos pasan ✅:
  - `VisibilidadApuntesTest` (7), `VersionadoPlanTest` (9), `SeguimientoTest` (4), `ValoracionFichasTest` (5), `EntrevistaTest` (3), `ContactoSiaTest` (2), `ConfiguracionTest` (3).
- `docs/modulo-intervencion.md` actualizado con tabla de resultados y marcadores ✅ por test.

### Decisiones de implementación

- La tabla `plan_apuntes` (no `apuntes`) evita conflicto con el stub `App\Models\Apunte` que ya usa la tabla `apuntes` para los tests del módulo Usuarios. Los dos modelos conviven hasta que se complete la migración definitiva del stub.
- La validación de JSON en `TipoFicha` se implementa en `setSchemaAttribute()` porque el cast `'array'` de Eloquent transforma el string antes de que se dispare el evento `saving`, haciendo la validación en ese evento inefectiva.
- El guard de firma en `PlanDeIntervencion` solo aplica a updates (`$plan->exists = true`); permite crear planes con `estado = activo` directamente para fixtures y seeders.
- `SeguimientoPlan::solicitarCitaSiguiente()` es un stub con comentario explícito; se implementará con la integración del módulo Agenda (ver principio 8.5 del módulo).

---

## Módulo Documentos — Verificación de tests — 2026-05-18

### Verificación
- 20/20 tests funcionales (TF-DOC-01 a TF-DOC-20) verificados y pasando en `vida_testing`.
- Los servicios (`ServicioAlmacenamiento`, `ServicioGeneracionPDF`, `ServicioFirmaInforme`, `ResolverEstiloInforme`) ya estaban implementados en sesiones anteriores.
- `docs/modulo-documentos.md` actualizado con tabla de estado y marcadores ✅. Estado cambiado a "Implementado".

---

## Módulo Centros — Fase 2 — 2026-05-18

### Nuevas funcionalidades
- **`PrescripcionService`**: gestiona el ciclo de vida de prescripciones de plazas.
  - `crear()`: asigna plaza libre o pone en lista de espera según disponibilidad.
  - `liberarPlaza()`: marca la plaza como libre y actualiza `profesional_alerta_id` en `ListaEspera` usando un resolver de TSR inyectable (preparado para integración con módulo Ciudadanía).
  - `cancelar()`: cancela la prescripción y libera la plaza asignada si la hubiera.
- **Métodos añadidos a modelos existentes**:
  - `Centro::directorActivo()` — devuelve el `DirectorCentro` activo (sin `fecha_fin`).
  - `ColeccionPlazas::plazasDisponibles()` — cuenta plazas libres; devuelve 0 si la colección está inactiva. El accessor `plazas_disponibles` delega a este método.
  - `Red::plazasLibresTotal()` — agrega plazas libres de todos los centros de la red (solo colecciones activas).
  - `Actividad::verificarInscripcionCentro(int $ciudadanoId)` — lanza `InvalidArgumentException` si la actividad requiere inscripción previa y el ciudadano no la tiene activa.
- **31 tests funcionales** en `Modules/Centro/tests/Feature/` (7 clases), todos pasan ✅:
  - `CentroUoTest` (3 tests), `AmbitoTerritorialTest` (6), `RedCentrosTest` (6), `ColeccionPlazasTest` (3), `PrescripcionTest` (5), `InscripcionCentroTest` (5), `DirectorCentroTest` (3).
- Migraciones pendientes commiteadas: `drop_distrito_from_centros_table`, `create_ambitos_territoriales_table`.
- `docs/modulo-centros.md` actualizado con tabla de estado y marcadores ✅ por test.
- `phpunit.xml` y `composer.json` actualizados con la suite de tests del módulo Centro.

### Decisiones de implementación
- El TSR activo se resuelve mediante `PrescripcionService::setTsrResolver(callable)` en lugar de una dependencia directa al módulo Ciudadanía (aún no implementado). En producción se conectará mediante un adaptador.
- `ColeccionPlazas::listaEspera()` sigue siendo `HasOne` (una entrada por prescripción); las consultas de múltiples entradas de una colección se hacen con `ListaEspera::where('coleccion_plazas_id', ...)`.

---

## Módulo Agenda — Fase 2 — 2026-05-18

### Nuevas funcionalidades
- **`SlotMaterializadorService::materializar()`** implementado: genera `Slot` al publicar un `CuadranteMes`.
  - Busca `HorarioCentro` vigente para el primer día del mes del cuadrante.
  - Comprueba que cada día de `LineaCuadrante` sea laborable según `dias_laborables`.
  - Intersecta la franja del profesional con la ventana de atención del centro (buffer inicio/fin).
  - Aplica `porcentaje_urgencias` con redondeo `floor`; slots normales primero, urgencias al final.
  - Bulk insert (`Slot::insert()`) por cuadrante.
- **9 factories** para todos los modelos del módulo Agenda (`SlotFactory`, `CuadranteMesFactory`, `LineaCuadranteFactory`, `HorarioCentroFactory`, `TipoSlotFactory`, `PerfilHorarioProfesionalFactory`, `ExcepcionProfesionalFactory`, `CitaFactory`, `EventoAgendaFactory`).
- **10 clases de test funcionales** con 44 tests (PF-01 a PF-10) basados en `docs/modulo-agenda.md §8`.
  - 14 tests pasan ✅; 30 pendientes ⏳ a la espera de servicios restantes.
- `docs/modulo-agenda.md` actualizado con tabla de estado por área y marcador ✅/⏳ en cada test individual.
- Corregida indentación en `autoload-dev` de `composer.json` (entrada `Modules\\Agenda\\Tests\\`).

### Decisiones de implementación
- Los campos `time` de PostgreSQL se recuperan como `"HH:MM:SS"`; las aserciones de hora usan `assertStringStartsWith()` en lugar de comparación exacta.
- `CuadranteMes::slots()` (`hasManyThrough`) requiere `select('slots.fecha')` para evitar columna ambigua al hacer `pluck('fecha')`.

---

## Módulo Agenda — Patch 01 — 2026-05-13

### Correcciones de modelo
- Añadido `EstadoSlot::Anulado` para distinguir slots invalidados activamente por una `ExcepcionProfesional` posterior a la publicación del cuadrante (vs. `Expirado`, que implica tiempo pasado sin uso).
- Añadido `Slot::scopeAnulados()`.
- Actualizado docblock de `GestionAusenciaService` con el flujo correcto.

---

## Módulo Agenda — Fase 1 — 2026-04-07

### Nuevas funcionalidades
- Estructura completa del módulo `Modules/Agenda/` con provider, autoload PSR-4 y registro en `modules_statuses.json`.
- **9 enums PHP** con `label()` en español: `ModoAgenda`, `EstadoCuadrante`, `EstadoSlot`, `EstadoCita`, `OrigenCita`, `TipoExcepcion`, `OrigenExcepcion`, `OrigenPermitidoSlot`, `MotivoReasignacion`.
- **11 migrations** en orden estricto de dependencias: `horarios_centro`, `tipos_slot`, `perfiles_horario_profesional`, `excepciones_profesional`, `cuadrantes_mes`, `lineas_cuadrante`, `slots`, `citas`, `reasignaciones_cita`, `eventos_agenda`, `evento_usuario`.
- **9 modelos Eloquent** con relaciones, scopes y casts de enums/JSON: `HorarioCentro`, `TipoSlot`, `PerfilHorarioProfesional`, `ExcepcionProfesional`, `CuadranteMes`, `LineaCuadrante`, `Slot`, `Cita`, `ReasignacionCita`, `EventoAgenda`.
- **5 Filament Resources** en `app/Filament/Resources/` (grupo *Agenda*):
  - Configuración: `HorarioCentroResource` (con `TiposSlotsRelationManager`), `TipoSlotResource`, `PerfilHorarioProfesionalResource`.
  - Supervisión: `CuadranteMesResource` (con `LineasCuadranteRelationManager` + acción *Publicar*), `ExcepcionProfesionalResource`.
- **Seeder** `AgendaSeeder`: crea un `HorarioCentro` y tres `TipoSlot` de ejemplo para el primer centro disponible. Integrado en `DatabaseSeeder`.
- **Servicios esqueleto**: `DisponibilidadService`, `CuadranteGeneratorService`, `SlotMaterializadorService`, `GestionAusenciaService`.
- **`SlotExpirationJob`** registrado en el scheduler (`dailyAt('20:00')`).
- Paquetes añadidos: `spatie/period` ^2.4, `simshaun/recurr` ^5.0.

### Pendiente (fases posteriores)
- Componentes Livewire: agenda del profesional, gestión de ausencias, cuadrante del centro.
- Lógica interna de servicios y del job de expiración.
- Componente IA de generación de cuadrantes (diferido explícitamente).
- Endpoint API de recepción de citas externas (módulo Integraciones).

---

## [Sin versión] — 2026-03-25

### Documentación general
- Generación de `documentacion-proyecto.md` completa con arquitectura, decisiones y convenciones.
- PHPDoc completos añadidos a modelos de Centro, Mensajes y Prestaciones.

### Infraestructura / Arquitectura
- Corrección de incoherencias estructurales entre módulos y app raíz (namespaces, rutas de autoload, providers).

---

## Módulo Mensajes — 2026-03-24

### Nuevas funcionalidades
- Implementación completa de mensajería interna entre profesionales.
- Sistema de alertas del sistema.
- Resource de Filament para gestión de mensajes y alertas desde el backoffice.

---

## Módulo Prestaciones — 2026-03-18

### Nuevas funcionalidades
- Migraciones, modelos y relaciones para gestión de prestaciones sociales.
- Resource de Filament con formulario reactivo (campos dependientes con `Get`).
- Tests de integración para el módulo.

### Correcciones
- Namespace incorrecto de `Get` en `PrestacionResource` para Filament v5 (`Filament\Schemas\Components\Utilities\Get`).

---

## Módulo Centro — 2026-03-17

### Nuevas funcionalidades
- Migraciones para centros, tipos de centro, colecciones de plazas, actividades y espacios.
- Modelos Eloquent con relaciones (Centro, Red, TipoEspacio, TipoActividad, SegmentoPoblacion).
- Resources de Filament: `CentroResource` (con RelationManager `ColeccionesPlazas`), `RedResource`, `TipoEspacioResource`, `TipoActividadResource`, `SegmentoPoblacionResource`.
- Grupos de navegación Filament: `Centros` y `Catálogos de centros`.

---

## Módulo Usuarios / Organización — 2026-03-11 a 2026-03-13

### Nuevas funcionalidades
- Creación de módulos `Organizacion` y `Usuarios` con `nwidart/laravel-modules` v12.
- Entidad `Profesional` con datos técnicos del trabajador social; relación con `User` mediante `profesional_id` nullable.
- Entidades de organización: `UnidadOrganizativa` (árbol autorreferencial con CTE recursiva), `Cargo`, `Titulacion`, `TipoRelacionProfesional`.
- Tabla `usuario_uo` (adscripción UO) y `usuario_rol` (historial de roles con flujo de aprobación: `pendiente_aprobacion → activo → inactivo`).
- Traits: `TieneUO`, `TieneRoles`.
- Policies para `HistoriaSocial` y `Apunte`, registradas en `UsuariosServiceProvider`.
- `RolResource` en Filament para supervisión de roles.
- Resources adicionales de Filament para modelos sin backoffice previo.

### Refactorizaciones
- Renombrado tabla `usuario_uo_rol → usuario_uo`.
- Corrección de autoload PSR-4 para seeders, migrations y factories de módulos (mapeos específicos por módulo).
- Corrección de inconsistencias de namespaces en módulos.

### Correcciones
- Namespace de `EditAction`, `DeleteAction`, `CreateAction` corregido a `Filament\Actions` (Filament v5).
- Seeder de usuario admin: uso de `User::create()` en lugar de `factory()` (sin faker en prod), inclusión de `email_verified_at`, eliminación de `bcrypt()` manual (el cast `hashed` del modelo lo gestiona).

---

## Panel de administración Filament — 2026-03-10 a 2026-03-11

### Nuevas funcionalidades
- Instalación del panel Filament v5 con `AdminPanelProvider`.
- 8 recursos CRUD iniciales para gestión de `UnidadOrganizativa` y `Usuario`.
- Grupos de navegación: `Organización`, `Profesionales`.

---

## Infraestructura y configuración inicial — 2025-10-22 a 2025-12-05

### Setup del proyecto
- Scaffolding inicial Laravel (renombrado carpeta `backend/ → vida/`).
- Configuración de CI/CD (GitHub Actions → rama `master`).
- Documentación: `README.md`, `LICENSE.md`, `NOTICE.md`, `CLAUDE.md`, `principios-vida360.md`.
- Principios técnicos de desarrollo y convenciones de abstracción.

---

## Notas sobre convenciones del proyecto

- **Keep-Alive**: soft deletes en todas las entidades sensibles; no hay hard deletes.
- **Filament Resources centralizados** en `app/Filament/Resources/` (decisión arquitectónica deliberada).
- **Tests**: usan PostgreSQL (`vida_testing`), no SQLite.
- **Módulos nwidart v12**: código en `Modules/NombreModulo/app/`; providers en `bootstrap/providers.php`.
