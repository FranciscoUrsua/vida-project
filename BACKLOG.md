# BACKLOG — VIDA 360

Registro de ideas, mejoras, decisiones pendientes y deuda técnica.
Actualizar con fecha y contexto breve al añadir cada entrada.

---

# 🔧 DEUDA TÉCNICA Y PENDIENTES

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

**Relaciones en UI de intervención** — 2026-06-16
`Módulo: Intervención / Ciudadanía`
El catálogo TipoRelacion está implementado. Pendiente:
- Widget UC en CiudadanoPage: mostrar tipo de relación de cada miembro respecto
  al titular (leer de ciudadano_relaciones filtrando por el par).
- Línea de representante entre cabecera ciudadano y widget UC.
- Modal "Ver todas las relaciones" accesible desde FichaCiudadanoPage.
- Gestión de relaciones (crear/editar/cerrar) en FichaCiudadanoPage.
- Modelo CiudadanoRelacion + migración ciudadano_relaciones + trait TieneRelacionesReciprocas
  (tabla diseñada en docs/modulo-ciudadania.md §3.3 pero pendiente de implementar).

---

**Añadir TipoRelacionSeeder al DatabaseSeeder raíz** — 2026-06-16
`Módulo: Ciudadanía`
El seeder existe (`TipoRelacionSeeder`) pero no está encadenado al DatabaseSeeder
raíz del módulo ni al global. Añadir cuando se haga el primer deploy con datos
del catálogo de relaciones.

---
