# BACKLOG — VIDA 360

Registro de ideas, mejoras, decisiones pendientes y deuda técnica.
Actualizar con fecha y contexto breve al añadir cada entrada.

---

# 🔧 DEUDA TÉCNICA Y PENDIENTES

---

**⚠️ La ficha del ciudadano no aplica la restricción de colectivos protegidos** — 2026-09-25
Módulo: Ciudadanía (prioritario: restricción crítica de `CLAUDE.md` §3)
`FichaCiudadanoPage::mount()` carga al ciudadano con `withoutGlobalScope(AmbitoUoScope::class)` y solo comprueba el rol; no llama a `CiudadanoPolicy::view`. Cualquier profesional con rol `intervencion`, `tramitacion`, `consulta_basica` o `supervision` puede abrir la ficha de una persona de colectivo protegido de otra UO sin acceso aprobado. Detectado al implementar `DocumentoPolicy`, que sí aplica la policy. Solución probable: `Gate::authorize('view', $c)` en `mount()` y un test en negativo.

---

**Mensajes — pendientes detectados al corregir el módulo** — 2026-09-26
Módulo: Mensajes
- **Alertas a «cualquier persona de un colectivo»** (decisión aplazada por el desarrollador): una alerta que basta con que atienda un miembro cualquiera del colectivo (p. ej. un trabajador social del centro, vale cualquiera). Hoy todas las alertas `rol_uo` son «a cada miembro»: cada destinatario debe reconocerla. Cuando se aborde: nuevo valor de `destinatario_type` (o un indicador) y que el primer reconocimiento cierre la parte de los demás. ¿Cómo escalar entonces: una sola vez o por destinatario?
- **Avisos del supervisor a un cargo concreto** (p. ej. solo los trabajadores sociales del centro): hoy van a todo el equipo de la UO. Los roles de Spatie no sirven como «colectivo» visible (no tienen nombre legible y no equivalen a cargos); habría que dirigir por `cargo` (nuevo `destinatario_type`).
- **Supervisor con varias UO o con UO hijas:** el aviso y el seguimiento usan las UO con adscripción vigente del supervisor, sin bajar a las UO hijas. Revisar si un supervisor de una UO padre debe ver a los equipos de las hijas.
- **Destinatarios que dejan la UO** conservan sus alertas pendientes (los destinatarios se fijan al crear). Revisar si al cerrar una adscripción deben traspasarse o cerrarse.
- **Bandeja: enlace al contexto de origen** (instrucciones, `BandejaAlertas`): cada alerta debería enlazar a su origen (p. ej. la solicitud de acceso o la asignación de rol) si existe y el usuario puede verlo. No se ha hecho en el paso 2: cada `origen_type` necesita su ruta y su comprobación de permiso.
- **`Modules/Agenda` tiene un sidebar de supervisor (`agenda.supervisor.sidebar`) registrado que ningún layout usa**: el de Supervisión lo sustituyó. No tiene las entradas de la bandeja; retirarlo si se confirma que está muerto.
- **Panel de redacción, pendientes:** (a) más contextos (apunte, entrevista, prestación) cuando haga falta; (b) la búsqueda de ciudadanos filtra por nombre en PHP sobre 500 registros como mucho, igual que la de Intervención: sustituir por el índice de búsqueda cuando exista; (c) el panel no se ha probado en navegador (foco, teclado, pantallas estrechas).
- **Error en `instrucciones-cli-mensajes.md` (tests mínimos):** «17:30 de un día laborable → 09:00 del día siguiente» es incorrecto con 4 horas laborales; lo correcto es 12:00 (lo que ya comprueba T-HLS-04).

---

**Suite completa con 75 fallos fuera de Documentos** — 2026-09-25
Módulos: Agenda, Mensajes, Ciudadanía, Autenticación, Intervención
(2026-09-26: los 6 fallos de Mensajes están resueltos; `t_lw_09` se sustituyó por TF-MSG-PAN-10 al retirar `NuevoMensaje`.)
Primera ejecución completa registrada (908 passed, 76 failed; el fallo de Documentos ya está corregido). Detalle por módulo en `CHANGELOG-092026.md` («Documentos: custodia v2, UI operativa y cierre»). La mayoría son de Agenda (esquema que no existe). En Ciudadanía, dos tests buscan un texto que ya no está en la ficha. Revisarlos antes de un merge a `main`.

---

**Documentos — decisiones y mejoras pendientes de la custodia v2** — 2026-09-25
Módulo: Documentos
- Qué hito crea una retención `intervencion_cerrada` (`RetencionService::retener()` existe sin ningún evento conectado).
- Proveedor de la clave maestra en producción (KMS, Vault o HSM); hoy `ProveedorClavesLocal` con `DOCUMENTOS_CLAVE_MAESTRA`.
- Permisos sobre documentos compartidos: regla provisional «puede verlo quien pueda ver al menos una persona vinculada» (con `CiudadanoPolicy::view`, decisión del 2026-09-25). Un documento compartido entre una persona protegida y otra que no lo es se abre por la no protegida.
- Auditoría de accesos a documentos compartidos: una entrada por acceso, con `ciudadano_id` de la persona que da el acceso y el resto en `contexto.ciudadanos_vinculados`. Decidir si la traza de cada TSR debe mostrar también los accesos a documentos compartidos por sus personas.
- Retirar el grupo `documento.tipo` de `catalogos_sistema` (ya migrado a `tipos_documentales`). Su clave `informe_generado` se migró como tipo «aportado por el ciudadano», igual que el resto; probablemente debería desactivarse porque los informes firmados usan `informe_profesional`.
- Futuro, sin fecha: portal del ciudadano (CSV, representantes), remisión a otras administraciones (nuevo motivo de retención).
- `StreamMaxLength` de clamd (25 MB por defecto) debe ser mayor que el tamaño máximo de subida. Si no, los ficheros grandes se rechazan como «antivirus no disponible», un mensaje que confunde.
- Programar `documentos:proponer-destruccion` en el scheduler (hoy solo a mano). Mientras ningún tipo tenga `conservacion_anyos`, no propone nada.
- Si se restaura un ciudadano dado de baja, sus vínculos a documentos siguen inactivos. Decidir si se reactivan.
- UI: abrir versiones anteriores sustituidas (hoy el controlador solo sirve la vigente), y el mismo panel en planes de intervención y valoraciones cuando se vinculen documentos a ellos.
- Conformidad PDF/A estricta: Ghostscript declara PDF/A-2b, pero no se ha validado con veraPDF ni se añade un OutputIntent explícito.

---

**Cuatro ojos en la aprobación de roles: quien solicita un rol para otro puede aprobarlo** — 2026-09-25
Módulo: Usuarios / Supervisión
Desde 2026-09-25 nadie resuelve su propia solicitud de rol. Pero si un usuario tiene `adm_usuarios` y `supervision` en la misma UO, puede pedir `supervision` o `adm_sistema` para un compañero desde el formulario de usuarios y aprobarla él mismo en Supervisión → Aprobaciones (`usuario_rol.asignado_por` = quien aprueba). Decidir si se bloquea también ese caso; en instalaciones pequeñas puede no haber un segundo supervisor.

---

**Destinatario de la alerta supervisada de roles: UO del usuario frente a «UO superior»** — 2026-09-25
Módulo: Usuarios / Mensajes
La sección 2.8 habla del «supervisor de la UO superior». `AsignacionRolesService` dirige la alerta al rol `supervision` de la UO de la primera adscripción vigente del usuario (a la raíz si no tiene adscripción), igual que las solicitudes de acceso a colectivos protegidos y que `AprobacionesPage`, que muestra al supervisor su propia UO y las inferiores. Confirmar si debe subir a la UO padre.

---

**Local y staging comparten la misma base de datos `vida`** — 2026-09-24
Módulo: Infraestructura
Detectado en la Fase 0 del mundo «Prueba CIAM»: `~/code/vida-project/vida/.env` (`APP_ENV=local`) y `/var/www/vida-project/vida/.env` (`APP_ENV=staging`) apuntan a `vida@127.0.0.1`. Toda migración o `demo:*` lanzado «en local» se aplica sobre staging, y un `demo:reset` en local truncaría los datos de staging (incluidos los de otros equipos). Recomendación: crear una BD de desarrollo propia (p. ej. `vida_dev`) para el entorno local.

---

**No existe asociación tipo de plan ↔ centro / UO / servicio** — 2026-09-24
Módulo: Intervención
`PlanPage::tiposPlanes()` ofrece todos los tipos activos (`piso`, `pia`…) a cualquier profesional de cualquier centro. El punto 1.4 de `docs/instrucciones-cli/2026-09-demo-ciam-aditivo.md` (asociar `pia` al CIAM) se omitió por decisión del desarrollador: no se crea un mecanismo nuevo sin diseño. Decidir si el tipo de plan se restringe por centro, por UO (junto a `plan_nombre_*`) o por servicio especializado (`planes_intervencion.servicio_especializado_id`, hoy sin uso: la tabla `servicios` está vacía).

---

**La interfaz no permite crear planes especializados; `PlanPage` fija siempre `tipo = general_asp`** — 2026-09-24
Módulo: Intervención
`PlanPage::crearNuevoPlan()` guarda `tipo = TipoPlan::GeneralAsp` sea cual sea el `tipo_plan_id` elegido. Un «PIA» creado hoy desde la UI queda como plan general sin plan ASP, y `CiudadanoPage::pisoActivo()` / `MisCasosPage` lo tratan como el PISO de la persona. Los planes PIA del mundo «Prueba CIAM» sí son `especializado` (entrada directa). Pendiente: derivar `tipo` del `ambito` del tipo de plan al crear, y diseñar el flujo de creación de planes especializados (derivación desde PISO y entrada directa con `admite_entrada_directa`). Fuera del alcance de la sesión CIAM por instrucción explícita.

---

**Retirada de los datos de mundos aditivos (etiqueta `TEST_CIAM`)** — 2026-09-24
Módulo: Demo / World Building
Todo lo creado por `demo:load` queda en `demo_world_registros` (`DemoWorldRegistro::de('TEST_CIAM')`), pero no hay comando de purga por etiqueta: por instrucción, la retirada se diseñará aparte (orden de borrado, soft vs hard delete, auditoría generada por la carga, filas pivote no registradas como `model_has_roles`, `actividad_profesional` y `sesion_actividad_profesional`).

---

**Suite de `Modules/Agenda` rota: `tipos_slot.horario_centro_id` no existe en el esquema** — 2026-09-24
Módulo: Agenda
Al ejecutar la suite completa (verificación de una actualización de dependencias) se detectaron ~60 tests de `Modules/Agenda` fallando con `QueryException: column "horario_centro_id" of relation "tipos_slot" does not exist`. Reproducible también con las dependencias de Composer anteriores a la actualización de hoy — no relacionado con dependencias. Probable causa: el commit `3003283` ("convertir TipoSlot en catálogo global del sistema") eliminó esa columna del esquema pero algún factory o helper de test (`Modules/Agenda/database/factories/CitaFactory.php`, `AgendaSupervisorTestHelpers.php`, entre otros) sigue insertándola. Revisar si falta una migración por ejecutar en `vida_testing` o si el factory quedó desactualizado tras ese refactor.

---

**TF-AUTH-16 y TF-AUTH-17 fallan: `User::booted()` sobreescribe `name` con el email siempre** — 2026-09-24
Módulo: Usuarios / Auth
`User::booted()` (hook `creating`) hace `$user->name = $user->email;` de forma incondicional, incluso cuando se crea el usuario con un `name` explícito. Esto rompe `tests/Feature/Auth/AutenticacionTest.php::tf_auth_16_el_nombre_del_usuario_aparece_en_la_ui` y `tf_auth_17_las_iniciales_del_avatar_son_las_dos_primeras_letras_del_name`, que esperan ver "Juana López" y sus iniciales "JL" en la UI tras crear el usuario con ese nombre. Confirmado reproducible en `master` sin relación con ningún cambio de la sesión que lo detectó (soft delete de `User`, ver `docs/decisiones-tecnicas.md` Sección 13). Corrección probable: solo rellenar `name` con el email cuando `name` no se ha establecido explícitamente.

---

**TF-SUP-F03 falla: columna "Colectivo protegido" en auditoría** — 2026-06-29
Módulo: Supervision
`auditoria_con_colectivos_muestra_columna_protegido` espera `assertSee('Colectivo protegido')` pero `AuditoriaPage` devuelve estado vacío ("No hay accesos registrados"). La columna solo se renderiza si hay accesos en el periodo. El test necesita crear al menos un acceso de auditoría antes de montar el componente. Fallo pre-existente, no causado por la sesión actual.

---

**Notificaciones reales al TSR tras asignación de plaza** — 2026-06-26
Módulo: Intervencion
`AsignarPlazaModal::notificarTsr()` actualmente solo escribe en el log (`Log::info`). Pendiente implementar notificación real (alerta en bandeja o push) cuando se asigne una plaza a una prescripción de un ciudadano con TSR activo. El módulo de Mensajes deberá gestionar el formato final.

---

**Asignación automática de plaza en `PrescribirRecursoModal::confirmar()`** — 2026-06-26
Módulo: Intervencion
Cuando `hayPlazaDisponible = true`, el estado de la prescripción se marca como 'asignada' pero `plaza_id` queda null (la asignación concreta la hace el TS del centro vía `AsignarPlazaModal`). Valorar si conviene auto-asignar la primera plaza libre disponible para reducir fricción en centros pequeños.

---

**Criterio territorial: reemplazar haversine PHP por PostGIS** — 2026-06-26
Módulo: Intervencion / Centro
`PrescribirRecursoModal::opcionesDestino()` ordena por distancia con haversine calculado en PHP (colección cargada en memoria). Con muchas colecciones (> 100), migrar a `ST_Distance` de PostGIS sería más eficiente. Por ahora la solución en PHP es suficiente.

---

**Disponibilidad de salas (validación de conflictos de reserva)** — 2026-06-25
Módulo: Agenda (futuro)
VIDA 360 almacena `sala_id` en `SesionActividad` como dato informativo de ubicación, pero no detecta conflictos cuando dos sesiones distintas usan la misma sala en el mismo horario. Esta funcionalidad se diseñará en el módulo de Agenda. Tampoco se valida que el aforo de la sala sea suficiente para el número de inscritos (queda a criterio del profesional).

---

**Migraciones de slugs en tipos_actividad existentes** — 2026-06-25
Módulo: Centro
El campo `slug` se añadió como nullable para compatibilidad con datos existentes. Si hay filas en `tipos_actividad` previas al seeder, tendrán `slug = NULL` hasta que se editen en Filament o se ejecute `db:seed --class=CentroSeeder`. Pendiente: valorar añadir un comando artisan de migración de slugs (slugify del `nombre`) o documentarlo para los administradores del sistema.

---

**Actividades grupales — gestión completa** — 2026-06-25
Módulo: Supervision / Centro
El modal de alta de actividades está implementado. Pendiente: edición y baja de actividades, gestión de sesiones (`SesionActividad`), e inscripción de ciudadanos desde Intervención. El modelo `Actividad` ya tiene la estructura; falta la UI de ciclo de vida completo.

---

**Filtro por segmento en SelectorPrestacionesCentro** — 2026-06-08
Módulo: Centro / Prestaciones
Implementar el filtro por segmento de población en el selector de prestaciones del centro. Actualmente el filtro aparece en la UI (botones por segmento) pero no aplica ninguna restricción a la query de prestaciones.
Razón: el campo `poblacion_destinataria` en `Prestacion` es un array JSONB de claves de `catalogos_sistema` (grupo `prestacion.poblacion`), no una FK a `segmentos_poblacion`. Para implementar el filtro es necesario definir un mapeo entre los segmentos de población del centro (`SegmentoPoblacion`) y las claves del campo `poblacion_destinataria`, o añadir una relación directa `Prestacion` ↔ `SegmentoPoblacion`.
Ver: `app/Livewire/Centros/SelectorPrestacionesCentro.php` — comentario TODO en `prestacionesFiltradas()`.

---

**TODOs activos en CiudadanoPage** — 2026-06-15
Módulo: Intervención / Ciudadanía
Varios TODOs documentados en `ciudadano-page.blade.php` que requieren trabajo pendiente:
- `centroActivo()` en `CiudadanoPage.php` — pendiente de implementación.
- DNI en cabecera — requiere `CiudadanoIdentificador::activo()` (tabla `ciudadano_identificadores` aún no existe).
- Menú ⋯ contextual — sin implementar.
- `statPrestaciones()` devuelve null — pendiente integración con módulo Prestaciones.

---

**Tabla `ciudadano_identificadores` pendiente de crear** — 2026-06-10
Módulo: Ciudadanía
La búsqueda por `doc`/`hsu` en `BuscarCiudadanoPage` devuelve vacío con TODO. La tabla y modelo `CiudadanoIdentificador` no existen aún. Necesario también para `CiudadanoIdentificador::activo()` que se usa en cabecera de ciudadano.

---

**UC en UI de intervención** — 2026-06-16
`Módulo: Ciudadanía / Intervención`
Los modelos y migraciones de UnidadConvivencia están implementados.
Pendiente: UI Livewire para gestión de UC dentro de la pantalla de intervención
del ciudadano (añadir/dar de baja miembros, verificar residencia, ver composición).
El botón "Ver ficha" en el bloque UC de `FichaCiudadanoPage` sigue apuntando a TODO.

---

**Tabla `derivaciones` pendiente de crear** — 2026-06-01
Módulo: Intervención
`crearDerivacion()` en `CiudadanoPage` crea solo el Apunte (tipo `derivacion`); la tabla y modelo `Derivacion` no existen. Añadir cuando esté disponible.

---

**Búsqueda por nombre cifrado con índice hash determinista** — 2026-06-01
Módulo: Ciudadanía
`BuscarCiudadanoPage` carga ≤ 500 registros y filtra en PHP para evitar el problema de búsqueda sobre campos cifrados. Reemplazar por índice hash determinista cuando el módulo Ciudadanía esté completo.

---

**Integración real de auditoría en BuscarCiudadanoPage (nivel 2)** — 2026-06-01
Módulo: Ciudadanía / Auditoría
El registro de acceso nivel 2 usa `\Log::info()` con TODO. Ya existe la tabla `audits` e `AuditService` desde 2026-06-14; pendiente conectar `BuscarCiudadanoPage` al `AuditService` en lugar del `\Log::info()`.

---

**[Demo] Citas en escenarios de demo** — 2026-06-03
Módulo: Demo world-building
Las trayectorias del sistema de demo no generan citas porque requieren `slot_id` (FK NOT NULL a la tabla `slots`) y toda la maquinaria de agenda (cuadrantes, perfiles horarios, excepciones). Pendiente para cuando el módulo Agenda exponga una API simplificada de creación de citas de test.
Ver: `database/seeders/Demo/Scenarios/` — todos los escenarios tienen comentario explicit documentando la omisión.

---

**[Demo] Campos ausentes en historias_sociales** — 2026-06-03
Módulo: Demo world-building / HistoriaSocial
La tabla `historias_sociales` no tiene `fecha_apertura`, `fecha_cierre` ni `sia_contacto_id`. Si se añaden estos campos en futuras migraciones, actualizar todos los escenarios de demo para poblarlos. Los escenarios actuales usan `created_at` implícitamente para la fecha de apertura.

---

**[Demo] Tests de integración pesados TF-DEMO-08 a TF-DEMO-12** — 2026-06-03
Módulo: Demo world-building / Tests
Los tests TF-DEMO-08 a TF-DEMO-12 están declarados como `markTestIncomplete`. Requieren:
- BD de demo aislada con roles Spatie (`intervencion`, `supervisor`, `consulta_basica`) ya sembrados
- APP_ENV = 'local' o 'staging' (no 'testing') para que `demo:reset` no aborte
- Suite de tests separada que no use RefreshDatabase global
Ver: `tests/Feature/Demo/DemoWorldLoaderTest.php`

---

**Migrar `app/Services/HistoriaSocialService` al módulo Intervención** — 2026-05-25
Módulo: Intervención
Actualmente coexisten dos `HistoriaSocialService` con namespaces distintos (`App\Services` y `Modules\Intervencion\Services`). Fusionar cuando el módulo Intervención esté consolidado.

---

**TF-LW-NAV-03 marcado como `markTestIncomplete`** — 2026-06-01
Módulo: Intervención / Tests
Requiere datos de plan activo en BD para completarse.
Ver: `Modules/Intervencion/tests/Feature/Livewire/NavegacionTest.php`

---

**Módulo Escalas fase 2 — Livewire de aplicación del pase** — 2026-05-26
Módulo: Escalas / Intervención
Componente Livewire para aplicar un pase de escala desde la Historia Social del ciudadano:
selección de instrumento, presentación sección a sección, confirmación de instrucciones si
`confirmar_instrucciones=true`, cierre del pase con cálculo de scores, y visualización del
historial de pases por escala ordenado cronológicamente.
Bloqueante: definir el punto de entrada desde la Historia Social (¿pestaña independiente?
¿acción en la ficha de valoración?).

---

**Clarificación licencia Zarit ZBI** — 2026-05-26
Módulo: Escalas
Contactar con Steven Zarit (Pennsylvania State University) para confirmar si el uso en un
sistema público municipal de servicios sociales no comercial está cubierto por la excepción
de uso clínico. Si se confirma, añadir al `EscalaSeeder`.
Ref: `docs/modulo-escala.md §7.2`.

---

**Clarificación licencia GDS de Yesavage** — 2026-05-26
Módulo: Escalas
Aclarar si la versión original de 30 ítems (1983) está en dominio público y si la traducción
española validada es de libre uso en contexto público no comercial.
Ref: `docs/modulo-escala.md §7.2`.

---

**Conectar PrescripcionService al TSR activo del ciudadano** — 2026-05-18
Módulo: Centros / Ciudadanía
`PrescripcionService::liberarPlaza()` usa un resolver inyectable para el TSR activo del ciudadano.
En producción debe conectarse al módulo Ciudadanía (o al registro de HistoriaSocial del módulo Intervención)
cuando esté disponible. Actualmente el resolver por defecto devuelve null (sin actualización de alerta).
Ref: `Modules/Centro/app/Services/PrescripcionService.php` — método `setTsrResolver`.

---

**Implementar servicios pendientes del módulo Agenda (30 tests bloqueados)** — 2026-05-18
Módulo: Agenda
Tras implementar `SlotMaterializadorService`, quedan pendientes:
- `CuadranteGeneratorService` (bloquea PF-03.1, PF-03.4, PF-03.5, PF-10.1)
- `DisponibilidadService` (bloquea PF-04.2, PF-09.1, PF-09.2)
- `SlotExpirationJob` (bloquea PF-04.4, PF-04.5, PF-06.3)
- Lógica de ciclo de vida de `Cita` (bloquea PF-05.1, PF-05.2, PF-05.4–PF-05.8)
- Bloqueo de slots al crear `EventoAgenda` (bloquea PF-04.3, PF-08.1–PF-08.4)
- `GestionAusenciaService` (bloquea PF-06.1, PF-06.2, PF-07.1–PF-07.5)
- Validación de solapamiento en `PerfilHorarioProfesional` (bloquea PF-02.3)
Ver `docs/modulo-agenda.md §8` para detalle de cada test.

---

**larastan/larastan — migración pendiente** — 2026-06-03
Módulo: Tooling
El paquete `nunomaduro/larastan` está marcado como abandonado upstream; el sucesor es `larastan/larastan`. Migrar en la próxima sesión de actualización de dependencias planificada.

---

**Reducir baseline de PHPStan** — 2026-06-03
Módulo: Tooling
La baseline actual tiene 772 errores heredados. Reducirlos progresivamente en cada sesión de refactor.
Ref: `phpstan-baseline.neon`.

---

**Estrategia de onboarding de un centro nuevo en el módulo Agenda** — (desde `docs/modulo-agenda.md`)
Módulo: Agenda
Definir la configuración mínima necesaria para activar el módulo en un centro nuevo y
el comportamiento del sistema si un centro no tiene `HorarioCentro` configurado.
Diferido al diseño de la interfaz de onboarding.

---

**Compatibilidad ENI / ENS para adopción por otras administraciones** — 2025-05-13
Transversal
Si el proyecto va a ser adoptado por otros ayuntamientos, revisar el cumplimiento del
Esquema Nacional de Interoperabilidad y el Esquema Nacional de Seguridad.
Las decisiones de arquitectura actuales no deben cerrar esta puerta.

---

**Calibración del valor K en perfiles de anonimización** — 2026-05-21
Transversal / API
K=10 como valor por defecto para datos abiertos es conservador. Evaluar si perfiles
de investigación con convenio pueden usar K=5 con salvaguardas adicionales.
Requiere consulta con el Delegado de Protección de Datos.

---

**Tratamiento de colectivos protegidos en extracciones anonimizadas** — 2026-05-21
Transversal / API
Definir si los registros de ciudadanos de colectivos protegidos (VVG, PSH) se excluyen
de extracciones de Nivel 2 y 3 incluso después de anonimizar, o si la anonimización
es garantía suficiente. Requiere análisis legal y consulta con DPD.

---

**Generalización de ubicación para PSH en extracciones analíticas** — 2026-05-21
Transversal / API
Las PSH tienen coordenadas de pernocta, no dirección postal. Definir cómo se
generaliza este campo en extracciones: zona, distrito de intervención, o supresión
si la densidad es insuficiente para garantizar k-anonimato.

---

**Validación formal del proceso de k-anonimato por el DPD** — 2026-05-21
Transversal / API
Antes de la primera publicación en el portal de datos abiertos, someter el proceso
de k-anonimato a revisión formal por el Delegado de Protección de Datos.
Bloqueante para la activación del perfil datos_abiertos.

---

**Sandbox de API** — 2026-05-20
API
Entorno separado con datos ficticios para desarrollo de integraciones externas.
Diferido hasta que haya un integrador externo real que lo necesite.
Prerequisito: factories y seeders completos para todas las entidades principales.

---

# 💡 IDEAS Y FUNCIONALIDADES AMBICIOSAS

> Ideas que requieren decisión de diseño antes de poder planificarse.

---

**Chat IA para búsqueda de información en la aplicación** — 2025-05-13
Asistente conversacional integrado en la interfaz operativa que permita a los profesionales
consultar información del sistema en lenguaje natural: "¿cuántas plazas libres hay en centros
de mayores en Carabanchel?", "¿qué prestaciones tiene activas este ciudadano?".
Restricción irrenunciable: el principio 3.9 aplica por completo. La IA consulta e informa;
nunca crea, modifica ni elimina registros.
Requiere: definir el modelo de lenguaje, el alcance de datos consultables, la gestión de
permisos en las consultas IA (el asistente no puede devolver datos a los que el profesional
no tendría acceso por los canales normales), y el log de auditoría de consultas.

---

**Generación IA de cuadrantes de agenda** — (desde `docs/modulo-agenda.md`)
Componente IA para proponer cuadrantes mensuales a partir de demanda histórica.
Requiere: definir métricas de input, criterios de calidad de la propuesta, y flujo de
validación profesional antes de publicar. Diferido hasta tener datos históricos suficientes.

---

**Panel de análisis estadístico y cuadro de mandos** — 2025-05-13
Explotación de los datos del sistema para planificación basada en evidencia: indicadores
de carga asistencial por UO, evolución de prestaciones, detección de patrones de demanda.
Conexión natural con el principio de transparencia y rendición de cuentas del nuevo modelo
de servicios sociales. Diferido a fases posteriores del proyecto.

---

**Alertas automáticas por patrones anómalos de acceso** — (desde `docs/modulo-auditoria.md`)
Detección de accesos masivos, accesos reiterados fuera de horario u otros patrones sospechosos,
con alerta automática al supervisor. Diferido a fases posteriores.

---

**Integración con sistemas SIEM corporativos** — (desde `docs/modulo-auditoria.md`)
Exportación de logs de auditoría a sistemas de gestión de eventos de seguridad del Ayuntamiento.
Diferido hasta que se identifique el sistema SIEM del municipio adoptante.

---

**Notificación al ciudadano por cambio de cita** — (desde `docs/modulo-agenda.md`)
Avisar al ciudadano cuando su cita se modifica o cancela.
Diferido a la definición del módulo de comunicaciones ciudadanas (canal a determinar:
carpeta ciudadana, SMS, email).

---

**Gestión de conflictos de espacio con bloqueo efectivo** — (desde `docs/modulo-agenda.md`)
El sistema actual solo genera aviso cuando dos actividades compiten por el mismo espacio.
Evaluar si se justifica un sistema de reserva con bloqueo real. Diferido a fases posteriores.

---

**Módulo de comunicaciones con el ciudadano**
Canal de comunicación oficial bidireccional entre el sistema y el ciudadano. Prerequisito
para notificaciones de cita, avisos de prestaciones, etc. Requiere definición del canal
disponible en el municipio adoptante.

---

**Customizador de marca (logo, colores, tipografía)** — mayo 2026
⚠️ Parcialmente implementado: el logotipo configurable en sidebar ya está disponible (2026-06-15).
Pendiente: extender a color primario, color secundario y tipografía (Google Fonts) tanto en
backoffice Filament como en la superficie operativa Livewire.

---

**Alta rápida de ciudadano desde modal UC** — 2026-06-16
`Módulo: Ciudadanía / Intervención`
El enlace "Dar de alta ciudadano nuevo" en el modal UC apunta a `ciudadania.alta`
sin contexto prerellenado. Pendiente: pasar parámetros de contexto a AltaCiudadano
para prerellenar domicilio de la UC y retornar al modal tras el alta (con el
ciudadano recién creado seleccionado para confirmar su adición).

---


**Genograma** — 2026-06-18
`Módulo: Ciudadanía / Intervención`
Ver decisiones pendientes en docs/modulo-ciudadania.md sección 8.
Bloqueado hasta definir: tipo_dinamica en ciudadano_relaciones, fecha_fallecimiento
en ciudadanos, y decisión sobre nodos ligeros para personas no registradas.

---

**Añadir TipoRelacionSeeder al DatabaseSeeder raíz** — 2026-06-16
`Módulo: Ciudadanía`
El seeder existe (`TipoRelacionSeeder`) pero no está encadenado al DatabaseSeeder
raíz del módulo ni al global. Añadir cuando se haga el primer deploy con datos
del catálogo de relaciones.

---

~~**UI del Plan de Intervención en CiudadanoPage**~~ — 2026-06-18 → **COMPLETADO 2026-06-19**
`Módulo: Intervención`
PlanPage Livewire implementado con diagnóstico, objetivos, actuaciones, participantes, firmas y PDF.

---

**Instalar barryvdh/laravel-dompdf si no está** — 2026-06-18
`Infraestructura`
El servicio PlanPdfService usa dompdf. Verificar si está instalado:
`composer require barryvdh/laravel-dompdf`
Documentar en SESSION.md tras instalación.

---

**Seguimiento del plan — UI** — 2026-06-18
`Módulo: Intervención`
El modelo SeguimientoPlan ya existe (tests TF-INT-C). Pendiente: integrar el
seguimiento en la UI de CiudadanoPage, con evaluación de objetivos por estado
y programación del siguiente seguimiento.

---

**Código de Primera Atención (PA)** — 2026-06-19
`Módulo: Atención`
Pendiente decidir si RegistroAtencion necesita un identificador visible
tipo "PA-2024-001234" para comunicar al ciudadano o para referencia interna.
Ver sección 9 de docs/modulo-atencion.md.

---

**Tipo actividad en RegistroAtencion** — 2026-06-19
`Módulo: Atención / Centro`
El tipo actividad está definido en el modelo pero sin UI ni generación
automática. Se activa al implementar el módulo Centro (inscripciones).
La relación polimórfica `origen_tipo / origen_id` está preparada.

---

**Tipo contacto en RegistroAtencion** — 2026-06-19
`Módulo: Atención`
Definido en el modelo pero sin UI específica en fase 1. Se implementará
cuando el módulo de Agenda esté operativo (llamadas de seguimiento).

---

**Generar cita desde RegistroAtencion** — 2026-06-19
`Módulo: Atención / Agenda`
El campo `cita_generada_id` existe en la tabla pero el formulario de nueva
atención no permite crear la cita desde FichaCiudadanoPage aún. Se activará
cuando el módulo Agenda exponga una API de creación de citas simplificada.

---
