# CHANGELOG — VIDA 360

Registro de cambios agrupado por módulo y área funcional, en orden cronológico descendente.

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
