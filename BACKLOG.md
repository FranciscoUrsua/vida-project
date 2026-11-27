# BACKLOG — VIDA 360

Registro de ideas, mejoras, decisiones pendientes y deuda técnica.
Actualizar con fecha y contexto breve al añadir cada entrada. Lo cerrado se retira (queda en el CHANGELOG y en git).
**Reorganizado el 2026-11-27** en cuatro bloques; las entradas que trataban lo mismo se han fundido. La fecha entre paréntesis es la de la entrada más antigua fundida.

1. **Low hanging fruit** — impacto relevante con poco desarrollo (o sin desarrollo).
2. **Bloqueantes de la 1.0** — decisiones aplazadas y preparación de producción.
3. **Versión 1.x / 2.0** — evolución y funcionalidades nuevas.
4. **Menores** — ni urgentes ni importantes.

---

# 1. 🍒 LOW HANGING FRUIT

## 1.1 Seguridad y privacidad: cambios pequeños

**`ApuntePolicy::view` no mira el acceso a la Historia** (2026-11-26) · Intervención
- Devuelve `true` para cualquier apunte no privado; protege solo el scope `ambito_uo`. Con `withoutGlobalScopes()` quedaría a la vista de cualquiera, también del profesional de encargo (EA-7). Propuesta: `view` = autor, o `HistoriaSocialPolicy::view`, o `ActuacionesEncargo::esVisible()`; revisar antes a quién afecta (supervisión sin `historia.leer`, R2).
- Retirar `Modules\Usuarios\Policies\ApuntePolicy` (campos inexistentes `privada`, `profesional_id`: código muerto).

**`PrescribirRecursoModal` no comprueba permisos** (2026-11-17) · Intervención
- Al confirmar no mira permisos (ni plazas ni sesiones); F6-2 dice «quien pueda editar el plan». Tampoco guarda `plan_intervencion_id` (siempre nulo): la prestación nacida de una prescripción no queda ligada al plan.

**`tramitacion` con permisos de apuntes en la BD compartida** (2026-11-25) · Usuarios
- Tiene `apunte.leer*`, `atencion.crear` y `atencion.leer_ajeno`, que `RolesSeeder` no le da y contradicen `roles-consulta-basica-tramitacion.md`. Confirmar y retirarlos con una migración como la `2026_11_25_100001`.

**Agendas sin ámbito en el backoffice** (2026-11-25) · Usuarios / Filament
- `PerfilHorarioProfesionalResource`, `ExcepcionProfesionalResource` y `CuadranteMesResource` no filtran por la UO de `adm_usuarios`.

**Auditoría de autenticación y de roles** (2026-11-25)
- Registrar en `audits` los inicios de sesión y los cambios de contraseña (hoy no se registran).
- Auditar los cambios de `role_has_permissions` desde `RolResource` (así entraron sin rastro `centro.gestionar` y `configuracion.acceder` en `supervision`) y avisar cuando la BD se aparte de `RolesSeeder`.
- `TipoFicha` sin `Versionable` ni auditoría: un cambio de schema no deja rastro (incidencia del 17 nov; principio 3.5).

**Normalización de los documentos de identidad** (2026-11-12) · Ciudadanía
- El alta guarda el NIE con guiones (`X-1234567-L`) y la ficha tal como se escribe; `valor_hash` solo pasa a minúsculas, así que la búsqueda exacta (buscador, citas, matching, cita externa) falla según el origen. Forma canónica única (mayúsculas, sin espacios ni guiones) en `hashDe()` y en lo guardado, con migración que recalcule `valor_hash`.

## 1.2 Operación sin código (servidor y staging)

- **Comprobar que el servidor ejecuta el planificador** (2026-11-16): `* * * * * cd /var/www/vida-project/vida && php artisan schedule:run` en el crontab de `www-data`. Sin él no se publican cuadrantes ni se cierran citas ni expiran slots.
- **`APP_DEBUG=false` en staging** (2026-11-23): un 500 enseña la traza de Laravel.
- **Ámbitos territoriales de staging** (2026-11-25): CSS Arganzuela y CSS Entrevías tienen adscripción por domicilio sin ámbito, y `ambitos_territoriales` está vacía. Configurarlos en `CentroResource` y reevaluar a las 74 personas con domicilio normalizado. Sin esto no se puede probar la asignación por domicilio.
- **Casos de `abogada.ciam`** (2026-11-25): la supervisión del CIAM Puente de Vallecas reparte HS 941–944 desde Supervisión → Asignaciones (si la abogada debe seguir, el TSR que los reciba le hace un encargo).
- **Plantillas de informe de staging** (2026-11-16): #1 y #2 están en la UO 3; comprobar que llegan a los centros donde se usan.
- **Prestación de derivación al PIA** (2026-11-18): `DER-PIA` no existe en staging; configurar una en Filament para probar la derivación.

## 1.3 Funcionalidad pequeña con mucho uso

- **«Cambiar referencia» en Mi equipo y en la bandeja de asignaciones** (2026-11-14): hoy solo en la ficha; el componente se incrusta tal cual.
- **Reactivar un usuario desactivado** (2026-11-13): no existe ni en Filament ni en Mi equipo (hoy, `desactivado_en = null` a mano y readscribir).
- **Contador de derivaciones pendientes** en el menú de Supervisión, como el de asignaciones (2026-11-14).
- **Avisos al publicar cuadrantes** (2026-11-16): `SlotMaterializadorService` devuelve 0 sin avisar si no hay horario, tipos de slot o perfiles, y `agenda:publicar-cuadrantes` salta centros en silencio. Avisar a la supervisión.
- **Tablas operativas** (2026-11-25): ordenación con `OrdenaPorColumnas` y `<x-op.th-orden>` en Actividades, Excepciones y Eventos, Control de alertas y Mi equipo. Paginar lo que crece: «Enviadas» de la bandeja de tramitación, catálogo de Actividades y Control de alertas (en «Recientes» **corta a 100 sin avisar**).
- **Tramitación con su layout** (2026-11-15): `FichaCiudadanoPage`, `BuscarCiudadanoPage` y `AltaCiudadano` fijan `layouts.operativo`; extender `User::layoutOperativo()`.
- **Bandeja de alertas para `consulta_basica`** (2026-11-15): recibe toasts pero no tiene bandeja (su ruta exige `agenda.ver_propia` o `derivacion.ver_asignadas`). De paso, quitar los avisos duplicados a quien tiene `consulta_basica` y `tramitacion` (`AvisosCitas::solicitudCreada()`).
- **Enlace al origen en la bandeja de alertas** (2026-09-26): reutilizar `AlertaToast::enlaceOrigen()`; añadir «Ver origen» a las alertas de `AccesoProtegido` si se crea pantalla para resolverlas.
- **Aprobar y denegar roles desde la pantalla de usuarios de Filament** (2026-11-17), reutilizando `ResolucionRolesService`.
- **Centro de la persona en la cabecera del expediente** (2026-06-15): `centroActivo()` en `CiudadanoPage`.
- **`AsignarPlazaModal::coleccionesDelCentro()`** usa `Profesional::unidad_organizativa_id` en vez de `CentroDeUsuario` (2026-11-14): sin ese campo no se ven plazas.
- **Anular un cierre de calendario futuro** registrado por error, de tipo o de centro (2026-11-13).
- **Autocompletado del callejero en `CentroResource`** (2026-11-25): hoy solo en la persona.
- **Plantillas de encargo fuera del asistente de la Historia** (2026-11-26): excluir las plantillas con fuente `encargo.datos` (fuera de un encargo salen vacías) o marcarlas «de encargo».
- **Programar `documentos:proponer-destruccion`** en el scheduler (2026-09-25); mientras ningún tipo tenga `conservacion_anyos` no propone nada.

## 1.4 Calidad de código, tests y rendimiento

**CI «Calidad de código» en verde** (2026-09-29) · transversal
- En rojo desde junio (último verde `80c3325`). Recuento del 11 nov: 281 errores, casi todos falsos positivos. Decidir y aplicar la propuesta ya escrita: `ignoreErrors` con `identifier: argument.templateType` limitado a `Modules/*/tests/*` y `tests/*` (164 errores, `Livewire::test()`), y lo mismo para `larastan.noEnvCallsOutsideOfConfig` en `Modules/Documentos/config/documentos.php` (13). Corregir lo mecánico: `nullsafe.neverNull` (17), `missingType.iterableValue` de Agenda (9).
- Actualizar `larastan/larastan` 3.10 → 3.14 y `phpstan/phpstan` 2.2.1 → 2.3.5 después de medir.
- Decidir si `quality.yml` sigue commiteando el formato de Pint o pasa a `pint --test`.
- Reducir el baseline (523 entradas el 27 nov) en cada refactor.

**Tests inestables o incompletos** (2026-06-01)
- `CuadranteSupervisorTest`: 2 tests dependen de la fecha; `Carbon::setTestNow()`.
- TF-LW-NAV-03 (`markTestIncomplete`): necesita datos de plan activo.
- Local: subir `max_locks_per_transaction` (p. ej. 256) o usar 2 procesos de paratest.

**Rendimiento: arreglos rápidos** (2026-09-29)
- Consultas en la vista: `Ciudadano::find($ucCiudadanoSeleccionado)` en `ciudadano-page.blade.php`; `$datos`, `$doc` y `$yaEnPlan` en bucles de `plan-page`; `Configuracion::logoUrl()` y `nombreAplicacion()` en cada request (cachear).
- `$herramientas` del expediente: sacarla a una constante.

**Código muerto** — retirar tras confirmar: `agenda.supervisor.sidebar` (Agenda), middleware `AuditarAccesoCiudadano` (o aplicarlo a rutas `{historia}` sin duplicar filas), grupo `documento.tipo` de `catalogos_sistema` (migrado a `tipos_documentales`; desactivar `informe_generado`), clave `configuracion.nombre_plan_asp` de `catalogos_sistema` (desde el 27 nov «Mis casos» nombra los planes por su tipo de plan y ya nadie la lee).

---

# 2. 🚧 BLOQUEANTES DE LA 1.0

## 2.1 Decisiones aplazadas

**A. Correo electrónico y contraseñas** (2026-11-27) · Autenticación / notificaciones
- VIDA no envía correo (ni SMTP ni proveedor). Atajo actual: «¿Olvidaste tu contraseña?» avisa a la supervisión y a `adm_usuarios` (`App\Services\Auth\AvisoContrasenaOlvidada`), que comprueban la identidad y asignan una nueva.
- Decidir: servidor (¿SMTP del Ayuntamiento?), remitente, plantillas, y si la identidad se federa con el directorio corporativo (LDAP/AD, `modulo-usuarios-permisos.md`). Con correo: flujo estándar de Laravel (`Password::sendResetLink`) y cambio obligatorio de la contraseña asignada en el primer acceso.
- Confirmar la política de contraseñas con el Ayuntamiento (hoy provisional: 12 caracteres con letras y números, `App\Livewire\CambiarContrasena`).
- Desbloquea las notificaciones por correo (alertas, citas) y, más adelante, las comunicaciones con el ciudadano (§3.2).

**B. Cierre del modelo de permisos** (2026-10-02) · Usuarios / Supervisión / transversal
Decisiones que hay que tomar juntas:
- Supervisión sola: ¿se le ocultan prestaciones y atenciones en la ficha (`solicitud.ver`, `atencion.leer`)? ¿y los documentos en `DocumentoResource` (desde R2 no los ve en la ficha)?
- `CiudadanoPolicy::create()` y `delete()` bloquean a todo usuario con `supervision` aunque tenga `intervencion` (`dir.ciam` y `dir.css` no ven «Alta de ciudadano/a»). En `update()` gana el otro rol desde el 12 nov: ¿se igualan?
- Excepción `supervisa` en `PlanDeIntervencionService::exigirAperturaDelPrincipal`: ¿sigue haciendo falta tras R1?
- `cambiarManual()` exige supervisión **adscrita** (TF-CIR-37): una coordinación de UO superior ya no resuelve la bandeja «sin referencia». ¿Es lo querido?
- `TipoSlotResource` es global y lo modifica cualquier supervisión de centro: ¿pasa a `adm_sistema`?
- Aprobación de roles: §2.8 dice «UO superior», pero la regla admite la misma UO; un supervisor que es `adm_usuarios` puede aprobar lo que asignó él (solo `adm_sistema` tiene esa restricción); con un solo `adm_sistema` nadie resuelve sus asignaciones (caso #41). Nombrar un segundo `adm_sistema` o cambiar el reparto.
- Supervisor de UO padre: ¿ve a los equipos de las UO hijas en avisos y seguimiento?

Trabajo asociado (conviene hacerlo de una vez, después de decidir):
- Barrido `role:` → permisos en las rutas que quedan: Historia Social, plan, valoración, escala, ficha de valoración, `intervencion.buscar.index`, informes (`Modules/Documentos/routes/web.php`) y solicitud de prestación. Test de arquitectura que falle si una ruta operativa usa `role:`.
- Comprobaciones de rol en código: `FichaCiudadanoPage::puedeVerHistoria()`, `User::layoutOperativo()`, `User::accedeAlBackoffice()`, «Ir a HS», el tipo de atención oculto a `consulta_basica` y las excepciones de padrón PSH/VVG de `AltaCiudadano`.

**C. Personas protegidas** (2026-11-18) · Ciudadanía / Agenda / Documentos
- Ninguna pantalla marca `colectivo_extra_protegido` (el indicador que manda en RN-17); el alta VVG pone `es_vvg` pero no la protección. Decidir quién lo marca y dónde.
- La agenda no enmascara: la cita de una persona protegida con encargo cerrado sigue mostrando su nombre.
- Documentos compartidos: con la regla provisional («quien pueda ver al menos una persona vinculada»), un documento compartido entre una persona protegida y otra que no lo es se abre por la no protegida.

**D. Datos cifrados: auditorías antiguas y búsqueda** (2026-06-01) · Ciudadanía / Auditoría / seguridad
- Las filas antiguas de `audits` tienen en `datos_antes` valores descifrados (fichas, `Ciudadano`…). Decidir si se cifran o se purgan (la tabla se trata como inmutable).
- Índice de búsqueda sobre campos cifrados (blind index o tokens con hash). Hoy `BuscarCiudadanoPage`, el panel de redacción de Mensajes y el buscador de Intervención cargan ≤ 500 registros y filtran en PHP: **con el volumen de producción no encontrarán a todas las personas.** Lo necesitan también `MotorMatching` (`fecha_nacimiento_hash`) y el filtro por ciudadano de la auditoría de accesos.

**E. Agenda: cuadrantes y slots** (2026-11-16) · Agenda
- Un cuadrante publicado no incorpora al personal nuevo (`regenerar()` solo en borrador; `agenda:publicar-cuadrantes` no toca centros con cuadrante). Quien llega a mitad de mes no tiene huecos hasta el siguiente. Decidir cómo añadir líneas y huecos sin tocar las citas.
- Con varios tipos de slot enlazados a un horario, el materializador crea huecos superpuestos y un profesional podría recibir dos citas a la vez. Decidir cómo reparte el cuadrante las franjas entre tipos.

**F. Firma de informes** (2026-11-16) · Documentos
- Decidir qué validación exige la 1.0. Hoy `ValidadorFirmaPdf` solo comprueba integridad, páginas e identificador: vale cualquier certificado íntegro, sin cadena de confianza (FNMT/@firma, CRL/OCSP), sin ligar el titular al autor y sin número de colegiado (`informes.numero_colegiado_firmante`). Valorar el servicio @firma de la AGE.
- Decidir si la 1.0 sale con AutoFirma integrada (preparada, `DOCUMENTOS_AUTOFIRMA_ACTIVA=false`; probar en puestos reales, `decisiones-tecnicas.md` §17) o solo con la de escritorio.

**G. Custodia de documentos** (2026-09-25) · Documentos
- Qué hito crea una retención `intervencion_cerrada` (`RetencionService::retener()` sin evento conectado).
- Proveedor de la clave maestra en producción (KMS, Vault o HSM); hoy `ProveedorClavesLocal` con `DOCUMENTOS_CLAVE_MAESTRA`.

**H. ENS** (2025-05-13) · transversal
- La entrada original lo ligaba a la adopción por otros ayuntamientos, pero el ENS (RD 311/2022) se aplica a todo el sector público, el Ayuntamiento de Madrid incluido. Determinar con IAM la categoría del sistema y revisar las medidas. Varias entradas de este bloque le sirven de base: auditoría de autenticación, clave maestra, auditorías en claro. La ENI sí puede esperar a que haya adopción.

**I. Entornos y seeders** (2026-09-24) · Infraestructura
- **Local y staging comparten la BD `vida`.** Aplazado el 11 nov «hasta cerrar la 1.0»: decidir ahora (p. ej. `vida_dev`). Mientras tanto: nada de `demo:reset`, `migrate:fresh` ni `db:seed` desde local.
- **`PrestacionesSeeder` sobrescribe el catálogo real:** si se ejecuta `DatabaseSeeder` sobre la BD compartida, pisa 25 prestaciones de la guía y crea otras 24. Pasar a cargar `docs/referencia/prestaciones-guia-2026.json` (las 113) o usar códigos que no choquen; igual con `prestacion.categoria`.

**Confirmar (ya implementado, solo falta el sí):** la baja de una actividad cancela sus sesiones futuras con «Actividad dada de baja: <motivo>» y avisa (2026-11-17).

## 2.2 Preparación de producción

- **Cron y worker de la cola** con cron.d y supervisor o systemd, no con crontab y PM2 de un usuario personal (`decisiones-tecnicas.md` §18).
- **Vigilancia del sistema:** avisar si falla el planificador o un job programado, y si la última importación del callejero tiene más de 40 días (`callejero_importaciones`).
- **Callejero:** primera importación en producción y luego `geocoder.proveedor = callejero` (la migración `2026_11_25_100004` solo lo activa si ya está cargado).
- **Servidor:** `StreamMaxLength` de clamd mayor que el tamaño máximo de subida; reglas AppArmor de qpdf y gs en `/etc/apparmor.d/local/` (comprobarlas tras cada `apt upgrade` o cambio de versión; detalle en `modulo-documentos.md` §3); `APP_DEBUG=false`.
- **Catálogos y configuración real:** revisar a mano las vías de acceso y los tipos de centro de las 113 prestaciones; unidades tramitadoras reales de las prestaciones de expediente externo (hoy «centro de solicitud»), con formularios y documentos exigidos; centros reales con tipo, ámbito y modo (todos los del mismo tipo con igual modo, ventana y segmentos, o el paso 3 de tipo-centro aborta).
- **Retirar las herramientas de prueba del área jurídica** (`HerramientasPruebaAreaJuridicaSeeder`: ficha, escala `prueba_riesgo_vivienda` y plantilla) o sustituirlas por las reales.

## 2.3 Pruebas en staging, ronda 2

Repetir INF-01, INF-08, EST-02 a EST-09 y VEN-01 a VEN-05 tras la ronda 1. Añadir:
- Preferencia de página de inicio con `dir.ciam` y `dir.css`; `coo.ciam` ve la ficha en lectura sin documentos ni enlace a la HS.
- Fase 4: `consulta_basica` y `tramitacion` adscritos a un centro (buscador, ficha, alta, relaciones, citación, retirada de documento).
- Fase 5: informe desde la HS (vista previa, PDF, firma con AutoFirma de escritorio, subida, anulación); PISO firmado desde el plan principal.
- Fase 6: vincular prestación a actividad, inscritos, cancelar sesión, baja de actividad; «Inscribir en actividad»; «Prescribir recurso» → sesión.

---

# 3. 🚀 VERSIÓN 1.x / 2.0

## 3.1 Versión 1.x — evolución de lo que ya existe

**Historia Social**
- Pantalla «Resumen de la persona» (2026-11-27): vista de un vistazo de la situación de la persona, con acceso directo a la ficha de datos, la Historia Social y los planes. Candidatos: planes vigentes y próximo seguimiento, recursos prescritos, últimas actuaciones, próxima cita, alertas, resumen de prestaciones (la HS ya no muestra ninguno desde U5). Pendiente de diseño. Origen: revisión de usabilidad del 27 nov (se quitaron de la HS «Apuntes», «Prestaciones activas» y «Último contacto»).

**Supervisión**
- Pantalla «Ciudadanos del centro» (EST-08): listado filtrable con cita pendiente, última atención, plan activo y colectivo; sin acceso a la HS salvo con `intervencion`.

**Encargo profesional**
- Anular un informe firmado desde el encargo (misma regla que `InformesHistorial`).
- Reasignar un encargo aceptado (hoy se anula y se encarga de nuevo).
- Pre-relleno de fichas y serie temporal de escalas en la valoración y el encargo; desde un encargo, solo lo del propio profesional (EA-8).

**Fichas**
- Mínimo y máximo en campos `numero` (`anio_llegada_espana`, `numero_pagas`): schema de `TipoFicha`, `TipoFichaResource`, `<x-ficha.campos>` y `ValidadorDatosFicha::validarTipos()`.
- Visibilidad condicional de campos (citada en el docblock de `TipoFicha`, sin implementar).

**Solicitudes, prestaciones y recursos**
- Listas de espera: por sesión en actividades (F6-3) y de prestaciones con plaza (`modulo-solicitudes-expedientes.md` §8), diseñadas juntas.
- Reasignar una solicitud tomada (TF-SOL-12).
- Prescripciones de plaza abiertas desde Recursos: sin prestación, no crean prestación de la persona.
- Aviso cuando la persona se queda sin inscripciones en una actividad (F6-10).
- Auto-asignar la primera plaza libre al prescribir (hoy `plaza_id` queda nulo).
- Retirar documentos subidos por error fuera del día (`CicloVidaDocumentoService::retirar()` ya existe).

**Informes**
- Fuentes automáticas sin resolver (`ciudadano.unidad_convivencia`, `historia_social.prestaciones_historico`, `historia_social.plan_activo`, `escalas.historico_barthel`) y merge tags a «—» (expediente, plan, centro, colegiado) en `construirMapaValores()`.
- Parámetros por UO con resolución jerárquica, como `EstiloInforme`.
- Modelos de informe por tipo de centro.

**Agenda y citas**
- Onboarding de un centro nuevo: configuración mínima, comportamiento sin `HorarioCentro`, tipo de slot «Cita» automático en `modo_agenda = basico` (`tipos_slot` no tiene código).
- Enlazar las citas del cuadrante del supervisor con su detalle.
- Perfil horario por defecto con pausa (hoy, de apertura a cierre: 45 h/semana en el CIAM).

**Mensajes**
- Alertas «a cualquiera del colectivo» (el primer reconocimiento cierra las demás) y avisos por cargo (nuevo `destinatario_type`).
- Destinatarios que dejan la UO: ¿se traspasan o cierran sus alertas pendientes?

**Atención y Ciudadanía**
- `RegistroAtencion`: tipo actividad (¿genera registro la asistencia a una sesión?), tipo contacto (llamadas de seguimiento) y código visible de Primera Atención («PA-2026-001234»).
- Alta rápida desde el modal UC con contexto prerrelleno y vuelta al modal.
- Menú ⋯ contextual del expediente.
- Al restaurar un ciudadano dado de baja, ¿se reactivan sus vínculos a documentos?

**Documentos**
- Abrir versiones anteriores sustituidas; panel de documentos en planes y valoraciones.
- Accesos a documentos compartidos en la traza de cada TSR.
- PDF/A validado con veraPDF y OutputIntent explícito.

**Tipo de centro**
- Traslado en bloque de personas y casos al crear un tipo o mover prestaciones.
- Vista pública del centro (denominación vigente, tipo, UO funcional).
- Cargos habituales por tipo como ayuda al alta de profesionales.

**Frontend**
- Partir `CiudadanoPage` (1.592 líneas) y `plan-page` (~66 KB) en componentes hijos: hoy cualquier `wire:model.live` vuelve a pintar toda la pantalla.
- Polling más ligero para los toasts (solo el contador; no volver a Alpine con `wire:ignore`).
- Tests de navegador (Dusk o Playwright): morph de Livewire con Alpine, panel de redacción, pantallas estrechas.
- Customizador de marca: color primario, secundario y tipografía en Filament y Livewire (el logotipo ya existe).

**Deuda técnica**
- Fusionar `App\Services\HistoriaSocialService` en `Modules\Intervencion\Services`.
- `Ficha` con doble vínculo (`historia_id` y `valoracion_id`): dejar una sola fuente.
- Centro obligatorio en el modelo de plan cuando factorías y demo lo den; `PlanPage::asignarTipoPlan()` al servicio.
- Symfony fijado a `^7.4`: quitar las restricciones al migrar a Laravel 13.

**Demo**
- Agenda mínima y citas en los mundos demo (`TrayectoriaNueva`, `TrayectoriaActiva`).
- `demo:load` crea prescripciones sin inscripción ni aforo: pasar por `PrescripcionService::crear()`.
- Purga por etiqueta (`TEST_CIAM`): orden de borrado, soft/hard delete, pivotes no registrados, `inscripciones_sesion`.
- TF-DEMO-08 a 12 en una suite separada sin `RefreshDatabase`.

**Otros**
- Requisitos de acción y tareas guiadas (`propuesta-requisitos-y-tareas-guiadas.md`, con apartados «Por decidir»; dependencias resueltas).
- Licencias de las escalas Zarit ZBI y GDS de Yesavage (`modulo-escala.md` §7.2).
- Adaptador real de la BDC: decidir si hace falta ahora que el callejero da los códigos en Madrid.

## 3.2 Versión 2.0 — funcionalidades nuevas

- **Integraciones:** Cita Previa (contrato, mapeo de servicios, reconciliación, canales de cita por tipo de centro; `modulo-citas.md` §11); gestor de expedientes (endpoint de avisos sobre `SolicitudPrestacionService::procesarAviso()`); proveedores para el inicio efectivo; directorio corporativo si no entra en la 1.0.
- **Comunicaciones con el ciudadano:** canal oficial bidireccional (carpeta ciudadana, SMS, correo), notificaciones y recordatorios de cita con la etiqueta pública del tipo; portal del ciudadano (CSV, representantes); remisión a otras administraciones. Depende de §2.1 A.
- **Analítica y cuadro de mandos:** carga por UO, demanda, prestaciones, indicadores de citas (`cita_eventos`) y de prestaciones, cofinanciación e HSU. BigQuery tendrá que descifrar en la aplicación (`fichas.datos` va cifrado).
- **Datos abiertos y extracciones anonimizadas:** valor K por perfil, exclusión de colectivos protegidos (VVG, PSH), generalización de la ubicación de las PSH y validación formal del k-anonimato por el DPD (bloqueante solo para activar `datos_abiertos`). Sandbox de API cuando haya un integrador real.
- **Asignación avanzada** (`modulo-asignacion.md` §11): PSH por coordenadas de pernocta y equipos de calle, zonas por profesional, cambio a petición y cupos, fórmulas mixtas, revisión de secciones censales y reasignación por NDP.
- **Seguridad avanzada:** alertas por patrones anómalos de acceso; exportación a SIEM.
- **IA** (principio 3.9: consulta, nunca modifica): asistente conversacional con los permisos del profesional y log de consultas; propuesta de cuadrantes a partir de la demanda histórica.
- **Espacios:** conflictos de sala con bloqueo real y aforo de sala frente a inscritos.
- **Genograma** (bloqueado por `tipo_dinamica`, `fecha_fallecimiento` y nodos ligeros; `modulo-ciudadania.md` §8).
- **Protocolos estructurados** por tipo de centro.
- **Editor de texto enriquecido** empaquetado con Vite (tablas, variables), sustituyendo el `contenteditable` con `execCommand`.

---

# 4. 🧹 MENORES

**Interfaz y textos**
- Fecha abreviada «14 mar. 2026» frente a «14 abr 2026» del design system: helper común o actualizar el design system.
- `ui:auditar` no detecta ternarios en `class="{{ }}"`; queda uno en `recursos-page.blade.php` (pasarlo a `Tono`).
- `abort(403, 'mensaje')` en informes, documentos, configuración de UO y equipo: pasar a `ActuacionNoPermitida` si alguno debe enseñar su motivo.
- Fichas de valoración del tipo: el formulario no distingue «todas» de «ninguna elegida».
- Colores por tipo de centro en la barra superior.
- Toasts en el backoffice Filament.
- «Aprobaciones» fuera del menú de Supervisión (decidido el 11 nov; reconsiderar si no la encuentran).

**Auditoría («Últimos accesos», usabilidad del 27 nov)**
- Una escritura puede dar varias filas (una valoración crea `Valoracion` y `Ficha`; cancelar una prescripción de sesión da de baja también la inscripción): el widget no las agrupa, igual que las consultas repetidas.
- En el detalle, los identificadores (plaza, plan, prestación…) no se traducen a nombres (`campos_ocultos`); resolverlos en `CambiosAuditoria` si hace falta.
- La unidad de convivencia se audita sin `ciudadano_id` (es de varias personas): sus cambios no salen en «Últimos accesos» de ningún miembro, solo en el visor de supervisión. Las citas y las asignaciones de referencia no pasan por `audits` (tienen historial propio); las cancelaciones anteriores al 27 nov no se reconstruyen.

**Lógica de borde**
- Bajas: la adscripción cerrada hoy sigue vigente hasta el final del día; las citas que bloquean la salida se cuentan desde hoy sin mirar la hora; «Repartir casos» puede llevar a otro centro si el supervisor tiene varios.
- Alerta a «supervisión de la UO» sin destinatarios si la persona con plaza no tiene historia.
- Vías con cifras («Cañada Real Sector 6») mal analizadas en texto libre (con el autocompletado van bien).
- Haversine en PHP en `PrescribirRecursoModal::opcionesDestino()`: PostGIS si hay más de 100 colecciones.
- R5 de bootstrap da por usada una clase que coincide con un nombre de vista o ruta.

**Catálogos a la espera de ficheros**
- `comun.pais` sin `equivalencias.ine` (con el fichero de IDA-Padrón entra Taiwán) y sin claves especiales OACI (`XXA`, `XXB`, `XXC`, `XXX`, `XKX`; falta el PDF del Doc 9303).
- `comun.idioma` sin lenguas de signos (`ssp` solo tiene código ISO 639-3).

**Documentos: vigilar**
- Coste de `inkcov` (6–14 s por PDF grande en 1 CPU): medir con uso real; opciones `-r36` o parar en la primera página con tinta.
- TF-DOC-ING-01 (fichero de 0 bytes): no se reproduce; anotar navegador si vuelve.

**Limpieza de datos de staging**
- Ficha 14 huérfana (valoración de `tec.ciam` sin apunte); `adm1.css` con `consulta_basica` en Spatie pero no en `usuario_rol`; ciudadano con `sexo = 'H'` (corregirlo desde la ficha, no con `UPDATE`).
- Campos añadidos después a la ficha #2 «Situación de vivienda», a mano en Filament.
- 96 direcciones de texto libre sin normalizar (58 sin número, 38 fuera del callejero).
- Tras la limpieza de @demo.es: borrar `~/backups/vida-20261117-1111-antes-limpieza-demo-es.dump` cuando se confirme; 2 profesionales sin usuario; 58 historias sin los planes de los @demo.es.
- `[Demo]` `historias_sociales` sin `fecha_apertura`, `fecha_cierre` ni `sia_contacto_id`: actualizar los escenarios si se añaden.

**Documentación**
- `instrucciones-cli-mensajes.md`: «17:30 → 09:00 del día siguiente» es incorrecto; con 4 horas laborales son las 12:00 (T-HLS-04).
