# CHANGELOG — VIDA 360 — Noviembre 2026

> Entradas de noviembre 2026. Para meses anteriores, ver `CHANGELOG-102026.md`.

---

## 2026-11-11 — Dependencias y CI: Symfony fijado a 7.4, PHP 8.5 y despliegue reparado

### Cambios
- `composer.json`: `symfony/event-dispatcher`, `symfony/html-sanitizer` y `symfony/string` fijados a `^7.4`, seguido de `composer update --lock`. Las versiones instaladas no cambian (7.4.17, 7.4.23 y 7.4.22). En `composer.lock` solo cambian el `content-hash` y la URL del repositorio de `vlucas/phpdotenv`, que ahora es `phpdotenv/phpdotenv` con la misma referencia. `composer audit`: sin avisos. Motivo y revisión pendiente en el BACKLOG.
- `.github/workflows/` (`ci.yml`, `quality.yml`, `security.yml`, `phpdoc.yml`): `php-version: '8.5'`.
- `ci.yml`, despliegue: `sudo systemctl restart php8.5-fpm`. Además, el script carga nvm (`NVM_DIR` y `nvm.sh`) antes de `npm`.
- Sudoers del servidor, cambiado por el desarrollador: las reglas NOPASSWD de `systemctl restart|reload php8.3-fpm` pasan a `php8.5-fpm`.
- Tests (`tests/Feature/Ui`, `tests/Feature/Auth`, `tests/Feature/Acceso`, `Modules/Ciudadania/tests`, `Modules/Agenda/tests/Feature/Citas`): 244 passed, 1 incomplete, 1 skipped y los mismos 4 fallos previos (TF-AUTH-16/17 y los dos de «Ver historia social»).

### Decisiones
- **PHP 8.5, no 8.4.** El encargo pedía 8.4, pero `systemctl list-units 'php*-fpm*'` en el servidor mostró `php8.5-fpm` activo, `php8.3-fpm` como `not-found` y ningún `php8.4-fpm`. En el servidor solo está instalado `/usr/bin/php8.5`. El desarrollador eligió 8.5 en todo, para que CI pruebe con la misma versión que ejecuta el servidor.
- **Los despliegues fallaban desde el 2026-11-10** (9 ejecuciones seguidas) con `npm: command not found` (exit 127). npm está instalado con nvm, que solo se carga desde `.bashrc` en sesiones interactivas. Por eso esas ejecuciones no llegaron a `migrate`, a los `*:cache` ni al reinicio del FPM. Sí se aplicaron `git reset` y `composer install`. El reinicio a `php8.3-fpm` también habría fallado, porque el servicio ya no existe.

### Verificación del despliegue
Commit `1e74c8d`, ejecución de CI/CD `42988736089`: `test` y `deploy` en verde. El job `deploy` terminó a las 08:43:53 UTC.
- Log del job: `npm ci` y `npm run build` sin errores (`✓ built in 1m 59s`), con nvm cargado. `migrate --force` da «Nothing to migrate». El script termina en `sudo systemctl restart php8.5-fpm` sin errores.
- Servidor: `systemctl show php8.5-fpm` muestra `ActiveState=active`, `SubState=running` y `ActiveEnterTimestamp=2026-11-11 08:43:51 UTC`, dos segundos antes del fin del job. Eso confirma que el reinicio fue del servicio correcto.
- `/var/www/vida-project/vida` está en `1e74c8d`.

---

## 2026-11-10 — Dependencias: parches de seguridad de Livewire y Symfony

El hook de pre-commit bloqueaba todo commit: `composer audit` y `local-php-security-checker` daban 13 avisos en 7 paquetes, publicados entre el 23 de octubre y principios de noviembre (Livewire: bypass de `#[Authorize]`; Symfony `html-sanitizer`, `http-foundation`, `http-kernel`, `mime`, `routing` y `yaml`, uno de severidad alta). Autorizado por el desarrollador.

### Cambios
- `composer update livewire/livewire symfony/html-sanitizer symfony/http-foundation symfony/http-kernel symfony/mime symfony/routing symfony/yaml --with-dependencies`: Livewire 4.4.6 → 4.4.8; Symfony 7.4.x a sus últimos parches (`http-foundation`, `http-kernel`, `routing`, `yaml` y `console` 7.4.23; `mime`, `error-handler`, `var-dumper` y `string` 7.4.22; `html-sanitizer` 7.4.13 → 7.4.23); contratos de Symfony 3.8.1, polyfills 1.43.3, `league/uri` 7.10.0, `laravel/prompts` 0.3.27, `masterminds/html5` 2.12.0. Solo cambia `composer.lock`.
- `composer audit`: sin avisos.
- Tests (`tests/Feature/Ui`, `tests/Feature/Auth`, `tests/Feature/Acceso`, `Modules/Ciudadania/tests`, `Modules/Agenda/tests/Feature/Citas`): 244 passed, 1 incomplete, 1 skipped y 4 fallos, todos previos y conocidos (TF-AUTH-16/17 y los dos de Ciudadanía que buscan «Ver historia social»).

### Decisiones
- La primera resolución subía `symfony/event-dispatcher`, `html-sanitizer` y `string` a la 8.1 (las restricciones de Laravel lo permiten). Se mantienen en 7.4, como el resto de Symfony, con `--with` (restricción temporal; `composer.json` no cambia): un salto de versión mayor no hacía falta para los parches. Ver BACKLOG.

---

## 2026-11-10 — Documentación: puesta al día (noviembre 2026)

Encargo `docs/instrucciones-cli/2026-11-puesta-al-dia-documental.md`. Solo documentación: ningún cambio de código, migraciones, seeders, tests ni configuración. No se han lanzado tests, migraciones ni `demo:*`. Commit de partida: `0b97bf4` (2026-10-02).

### Ficheros incorporados (paso 0)
Subidos por el desarrollador desde el proyecto de Claude «VIDA 360»:
- `docs/modulo-citas.md` (sustituye al anterior), `docs/roles-consulta-basica-tramitacion.md`, `docs/modulo-tipo-centro.md`, `docs/propuesta-requisitos-y-tareas-guiadas.md`, `docs/estado-proyecto-2026-11-10.md`.
- `docs/instrucciones-cli/2026-10-tipo-centro-implementacion.md`, `docs/instrucciones-cli/2026-10-tipo-centro-tests.md` (llegó como `2026-10-centro-tests.md` y se renombró a petición del desarrollador) y `docs/instrucciones-cli/2026-11-puesta-al-dia-documental.md`.

### Paso 1 — `modulo-citas.md`: revisión del 30 sep por la tarde
**Novedades respecto a la versión implementada** (comparación con `0b97bf4`, sin contar el formato):
- Cabecera: los permisos de `tramitacion` están definidos y su implantación es diferida; nuevo documento relacionado, `docs/roles-consulta-basica-tramitacion.md`.
- `tramitacion` se añade junto a `consulta_basica` en el canal interno (§1), la etiqueta pública (§2.1), la tabla cita/evento (§2.6), los avisos de solicitud creada (§3.1) y la bandeja de citación (§6).
- **RN-05 ter** (nueva): la gestión de citas se ejerce en la UO del usuario; `consulta_basica` y `tramitacion` dan, reprograman y cancelan solo en slots de centros de su UO; el historial de citas se consulta de cualquier ciudadano.
- §2.1: la etiqueta pública protege el contenido de la intervención, no el tipo de centro («Que una cita sea de un CIAM no es por sí mismo un dato sensible: el centro se muestra siempre»).
- §3.2: búsqueda y ficha disponibles aunque la persona no tenga Historia Social en la UO de quien atiende; la cita solo en un centro de esa UO.
- **§6 bis** (nueva): historial de citas en la ficha del ciudadano para `consulta_basica` y `tramitacion`.
- §7: columna `tramitacion` (⏸), «en su UO» en dar, reprogramar y cancelar, y fila «Ver historial de citas del ciudadano».
- §9: impacto en `modulo-ciudadania.md` (bloque de historial de citas).
- §10: tres decisiones nuevas («tramitacion y citas», «Ámbito de la gestión de citas», «Historial de citas en la ficha»).
- Se pierde la precisión de RN sobre la referencia («la asignación vigente de su historia (`AsignacionReferenciaService::vigente()`)»), que la versión del repo había añadido al implementar: ver dudas.

**Verificación en el código:**
- **RN-05 ter: implementada.** `vida/Modules/Agenda/app/Policies/AlcanceCentro.php:21-26` (`incluye()`: el centro es de una UO activa del usuario); `CitaPolicy.php:55-58` (`citar`), `:152-160` (`gestionaAjena`, que usan `reprogramar` y `cancelar`), `:179-181`; `SolicitudCitaPolicy.php:37-39` (`verBandeja`) y `:49-51` (`gestionar`). `Services/Citas/CitacionService.php` autoriza con ellas en las líneas 71-72, 133, 160-161 y 216.
- **§6 bis: no implementado.** `vida/Modules/Ciudadania/app/Http/Livewire/FichaCiudadanoPage.php` solo usa citas para vincular el registro de atención (`citaVinculable()`, línea 1069) y para el botón «Dar cita» (`puedeDarCita()`, línea 1081); la vista (`ficha-ciudadano-page.blade.php`) no tiene bloque de historial. No existe el permiso `cita.ver_historial`.
- **`tramitacion` en citas:** `Modules/Agenda/database/migrations/2026_09_30_120006_add_permisos_citas.php:25-29` no le da permisos `citas.*` (coherente con la implantación diferida).
- Línea Estado reescrita; entrada «Citas: delta de la revisión del 30 sep» en BACKLOG.

### Documentos modificados
- `docs/modulo-citas.md`: línea Estado (implementado sobre la revisión anterior; pendiente §6 bis y `cita.ver_historial`; RN-05 ter ya cumplida).
- `docs/modulo-agenda.md`: línea Estado con las pantallas que existen (recursos de Filament, supervisión, agenda del profesional en Intervención, citación) y lo que falta. §8 no lista tests bloqueados (45 de 45).
- `docs/modulo-asignacion.md`: nota al principio de §8 (la configuración pasa al tipo con Tipo de Centro).
- `docs/modulo-tipo-centro.md` §11: la asignación está implementada; rama A.
- `docs/modulo-auditoria.md`: «Pendiente de implementación» → implementado el 2026-06-14 (`CHANGELOG-062026.md`, «Módulo Auditoría — implementación completa») y lectura de expedientes el 2026-10-02.
- `docs/modulo-escala.md`: «Pendiente de implementación» → fase 1 implementada el 2026-05-26 (`CHANGELOG-052026.md`, «Módulo Escalas fase 1»); pendiente la fase 2.
- `docs/modulo-usuarios-permisos.md`: ROL 5 marcado como implantación diferida, con citas según `modulo-citas.md` §7; ROL 7 con capacidades resumidas de §3.4 del documento de roles y ámbito «limita la operación, no la consulta»; §4.4 con `historia.ver_estado`, `cita.ver_historial` y `documento.gestionar_propios` (no implementados).
- `docs/modulo-ciudadania.md`: Capa 1 accesible sin ámbito de UO para `consulta_basica` y `tramitacion`; relaciones editables también por `consulta_basica`; `consulta_basica` puede dar altas (hoy no tiene `ciudadano.crear`); banner de protegido solo para `intervencion` ante datos sensibles (en §6.2); bloques de `FichaCiudadanoPage` por rol (en §7.4). Todo marcado como diseño cerrado sin implementar.
- `docs/modulo-documentos.md`: §1, excepción de `consulta_basica` (solo sus documentos del día); §2.1, lo que falta en la custodia v2 (retirada lógica con quién/cuándo/motivo y vínculo con una solicitud; versiones y vínculos ya existen).
- `docs/design-system/SKILL.md` y `README.md`: el banner de protegido solo se muestra a `intervencion` cuando intenta abrir datos sensibles.
- `docs/decisiones-pendientes.md`: decisiones resueltas retiradas, sección «Decisiones pendientes registradas en otros documentos», totales (34) y fecha.
- `docs/estado-proyecto-2026-11-10.md`: sección «Seguimiento» al final (sin tocar el resto).
- `CLAUDE.md`: §6 con los dos ficheros de Tipo de Centro y este encargo; §5 con el documento de roles y el informe de estado.
- `BACKLOG.md`: ver abajo.

### Decisiones pendientes dadas por resueltas
- Resuelto el 2026-06-29 / 2026-09-30: **A-06** (interfaz Livewire/Filament de Agenda) — `CHANGELOG-062026.md` «2026-06-29 — Agenda: UI de supervisión» y `CHANGELOG-092026.md` «2026-09-30 — Citas (Agenda): implementación completa» (la agenda del profesional sustituye la fixture por citas reales).
- Resuelto el 2026-06-09: **CIU-01** (tabla y modelo `CiudadanoIdentificador`) — `CHANGELOG-062026.md` «Alta de ciudadano — Módulo Ciudadanía»; migración `2026_06_09_100002_create_ciudadano_identificadores_table.php`.
- Resuelto el 2026-06-16/17: **CIU-02** (tablas y modelo de la unidad de convivencia) — migración `2026_06_16_000001_create_unidades_convivencia_tables.php`; `CHANGELOG-062026.md` «Relaciones entre ciudadanos + UC solo lectura en FichaCiudadanoPage».
- Resuelto el 2026-05-21: **C-01** (entidad Servicio) — ya marcado como resuelto en el propio fichero («Módulo Centro — Entidad Servicio Fase 2»); se retira.

### BACKLOG
**Cerradas, con evidencia en cada entrada:** «Suite de `Modules/Agenda` rota» (paso 0 de citas; Agenda 173 passed), «Implementar servicios pendientes del módulo Agenda» (todos los servicios existen; ningún `markTestIncomplete`), «Integración real de auditoría en BuscarCiudadanoPage (nivel 2)» (`registrarAccesoNivel2()` con `AccesoExpediente`), «Tabla `ciudadano_identificadores`» (existe; la búsqueda por documento sigue en «Acceso a expedientes: pendientes»), «UC en UI de intervención» (gestión de la UC en `CiudadanoPage`; la ficha enlaza a cada miembro), «Instalar dompdf» (`composer.json:10`), «Añadir TipoRelacionSeeder al DatabaseSeeder» (`DatabaseSeeder.php:47`) y «[Demo] Citas en escenarios de demo» (duplicada de «Citas: pendientes de la fase»).

**Actualizadas sin cerrar:** «TODOs activos en CiudadanoPage» (DNI resuelto; `centroActivo()`, menú ⋯ y `statPrestaciones()` siguen), «Suite completa con 75 fallos» (solo quedan TF-AUTH-16/17, `AutorizacionDatosTest` y Ciudadanía «Ver historia social»), «Seguimiento del plan — UI» (falta la evaluación de objetivos por estado), «No existe asociación tipo de plan ↔ centro» (resuelto en diseño por Tipo de Centro), «La interfaz no permite crear planes especializados» (Tipo de Centro no lo resuelve; prioritario), «Asignación de centro y referencia: pendientes» (aviso del paso 3 de Tipo de Centro).

**Fundidas:** las dos «CI «Calidad de código» en rojo» (2026-09-29 y 2026-10-01).

**Nuevas:** «Citas: delta de la revisión del 30 sep», «`consulta_basica`: diseño cerrado sin instrucciones de implementación», «Tipo de Centro: listo para ejecutar», «Requisitos de acción y tareas guiadas — en análisis».

### Decisiones de implementación
- La línea de estado de `modulo-auditoria.md` y `modulo-escala.md` se corrige por evidencia inequívoca en el CHANGELOG (paso 2, «resto de documentos»).
- En `modulo-ciudadania.md` no hay un apartado que describa `FichaCiudadanoPage`: los bloques por rol van como elemento de §7.4 (referencias de código), y el banner de protegido al final de §6.2, aclarando que vale para todos los colectivos protegidos.

### Dudas (contradicciones no previstas en las instrucciones, sin resolver)
1. **Los ficheros incorporados han llegado sin formato Markdown** (`modulo-citas.md`, `modulo-tipo-centro.md`, `roles-consulta-basica-tramitacion.md`, `propuesta-requisitos-y-tareas-guiadas.md`, `estado-proyecto-2026-11-10.md` y las tres instrucciones nuevas): sin `#` en los títulos, tablas separadas por tabuladores, sin código entre comillas invertidas. Parecen copiados como texto plano. No se han reformateado (las instrucciones prohíben mejorar la redacción). Conviene volver a subirlos en Markdown o autorizar la conversión.
2. **`modulo-citas.md` pierde una precisión de la versión del repo:** la regla del profesional de referencia decía «la asignación vigente de su historia (`AsignacionReferenciaService::vigente()`, ver `docs/modulo-asignacion.md`)», añadido al implementar; la versión nueva dice solo «para el profesional de referencia del ciudadano». ¿Se repone?
3. **`modulo-documentos.md` §1 no contiene literalmente** la frase «para cualquier profesional con acceso al expediente» que citan las instrucciones; se ha añadido una frase con la disponibilidad general y la excepción de `consulta_basica`.
4. **`docs/decisiones-pendientes.md`, indicios sin constancia:** A-02 (la agenda del profesional ya existe, con «Pedir cambio», pero no consta qué ve del cuadrante de sus compañeros), CIU-03 (el centro se ve en el bloque «Centro y referencia» de la ficha, pero no en la cabecera del expediente) y T-01 (existe `MotorMatching`, pero no consta la estrategia para cambios de documento, nombre o sexo). Se dejan.
5. **`modulo-intervencion.md:793`** dice «Implementado (fase 1 — modelos, policies, factories, tests funcionales)» y no menciona la interfaz, que existe desde junio. No contradice el CHANGELOG, pero se queda corto; no se ha tocado.

---
