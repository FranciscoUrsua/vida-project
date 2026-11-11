# CHANGELOG — VIDA 360 — Noviembre 2026

> Entradas de noviembre 2026. Para meses anteriores, ver `CHANGELOG-102026.md`.

---

## 2026-11-11 — Documentos: rechazos que faltaban y causa técnica (A1 de estabilización)

### Cambios
- `IngestaDocumentoService::ingerir()`: rechaza con `fichero_vacio` un fichero de 0 bytes justo después de copiarlo a la zona temporal (antes del antivirus), y con `pdf_sin_contenido` un PDF sin tinta visible después de `normalizar()` (nuevo `rechazarSiSinContenido()`, también para los firmados).
- `SaneadorPdf::tintaTotal()`: `gs -q -dSAFER -o - -sDEVICE=inkcov`, suma C+M+Y+K de todas las páginas. Si Ghostscript falla o no devuelve ninguna página, `pdf_no_normalizable` con la causa adjunta.
- `config/documentos.php`: `ingesta.umbral_tinta_minimo` = 0,00001 (`DOCUMENTOS_UMBRAL_TINTA_MINIMO`).
- `ProcesoExternoFallidoException` (de `584d5ad`) **renombrada a `FalloHerramientaExterna`**, el nombre que fijan las instrucciones. Ahora guarda solo el nombre del binario (no su ruta) y recorta la salida de error a 2.000 caracteres (`MAX_SALIDA_ERROR`).
- `ConversorPdfLocal::desdeOfimatica()`: el fallo y el timeout de LibreOffice llevan `FalloHerramientaExterna` como previa de `conversion_fallida`.
- `DocumentosCiudadano::guardar()`: regla propia «El fichero está vacío.» sobre `fichero`, y `report($e->getPrevious())` en lugar de `report($e)` (al log va la causa técnica).
- `IngestaRechazadaException::MENSAJES`: `fichero_vacio` y `pdf_sin_contenido`.
- Fixtures nuevos (y su generación en `generar-fixtures.sh`): `en-blanco.pdf`, `tres-paginas-segunda-en-blanco.pdf`, `truncado.pdf` y `contenido-danado.pdf`.
- Tests TF-DOC-ING-01 y 08 en `DocumentosCiudadanoTest`; TF-DOC-ING-02 a 07 y el recorte de la salida de error en `IngestaDocumentoTest`. El test de qpdf de `584d5ad` se adapta (al log va la causa, no el rechazo). 03, 04 y 07 comprobados en negativo: fallan sin la medición de tinta y sin la condición de qpdf.
- `docs/modulo-documentos.md` §3: los dos motivos nuevos, la medición de tinta y el registro de la causa. `docs/decisiones-tecnicas.md` §14 «Dependencias del sistema de la custodia».
- Tests: `Modules/Documentos/tests` **100 passed** (477 assertions). PHPStan sin errores nuevos en lo modificado.

### Por dónde pasaba el fichero de 0 bytes
- **No se reproduce.** En local, `finfo` devuelve `application/x-empty` y `DetectorFormato` lo rechaza como `formato_no_admitido` (TF-DOC-ING-01 escrito antes del cambio: mostraba «Este tipo de fichero no se admite»). En la BD compartida no hay ninguna versión de 0 bytes: las cinco subidas del 11 nov son `09_informe…` (24.780 B), `02_documento_identidad…jpg`, `14_informe_derivacion….docx`, `E07_pdf_con_javascript.pdf` y `E04_pdf_corrupto_truncado.pdf` (2.669 B, el que se guardó en blanco). `E10_fichero_vacio.pdf` no llegó a guardarse; lo más probable es que se viera el error y se tomara por otro caso. El rechazo explícito se añade igualmente, con su mensaje propio.

### Umbral de tinta: mediciones
Tinta total (C+M+Y+K, todas las páginas) tras sanear con los parámetros de la tubería:

| Fichero | Páginas | Tinta |
|---|---|---|
| Documentos reales del juego (01 a 22, PDF de ReportLab) | 1–4 | 0,305 a 1,611 |
| `E05_documento_51_paginas.pdf` | 51 | 10,54 |
| `E06_escaneo_alta_resolucion.pdf` | 6 | 18,28 |
| `E07_pdf_con_javascript.pdf` | 1 | 0,054 |
| `valido.pdf` (fixture: una línea de 18 pt por página) | 2 | 0,0045 |
| Página con una sola «x» de 10 pt | 1 | 0,00002 |
| Página con un solo «.» de 10 pt | 1 | 0 |
| Página en blanco | 1 | 0 |
| `E04_pdf_corrupto_truncado.pdf` tras el saneado de entonces | 1 | 0 |

**Propuesta: 0,00001**, la resolución de inkcov (cinco decimales por canal). Rechaza lo que mide 0 (en blanco o «reparado» en blanco) y admite cualquier página con un carácter legible. Un umbral mayor no separa mejor: los casos reales están cuatro órdenes de magnitud por encima, y el riesgo de subirlo es rechazar documentos válidos con muy poco texto.

### Decisiones
- **TF-DOC-ING-03 con un PDF dañado, no truncado.** Con la regla del punto 3 (qpdf con código 2 y salida de error → `pdf_no_normalizable`), un PDF truncado sin trailer, como `E04`, ya no llega a Ghostscript: qpdf escribe «unable to find trailer dictionary» y se rechaza antes. El caso «Ghostscript lo repara y queda en blanco» solo se da con un PDF cuyas referencias están intactas y el contenido dañado (`qpdf --requires-password` no lee los flujos): `contenido-danado.pdf`. El test comprueba los dos: el dañado da `pdf_sin_contenido` y el truncado `pdf_no_normalizable`.
- **Sin `min:1` en la regla de `fichero`:** Laravel mide los ficheros en KB con decimales, así que `min:1` rechazaría todo fichero de menos de 1.024 bytes (una imagen pequeña válida) con «El fichero está vacío.». Se usa una regla propia que mira si son 0 bytes, con el mismo mensaje.
- **Comando sin la ruta del binario, con las rutas de trabajo:** los argumentos son rutas de la zona temporal con nombres neutros (`original`, `saneado.pdf`) y sirven para diagnosticar AppArmor; no llevan datos personales.
- **Coste de la medición:** `inkcov` rasteriza todas las páginas. En esta máquina (1 CPU) añade 6,5 s al PDF de 51 páginas y unos 14 s al escaneo de alta resolución de 6 páginas. Se deja la resolución por defecto (72 ppp); a 36 ppp el escaneo baja a 6,7 s y la «x» se sigue detectando. Anotado en el BACKLOG.
- Pint no se aplica: sus reglas pendientes de decisión (A3) borrarían docblocks.

## 2026-11-11 — CI «Calidad de código»: PHPStan medido (A3 de estabilización)

### Cambios
- `vida/composer.json`: `analyse-ci` con `--memory-limit=2G` (antes 512M).
- `ResolucionCentroService::seccionesSinCobertura()` declara y devuelve siempre `Illuminate\Database\Eloquent\Collection` (antes `Support\Collection` y `collect()` en el caso «ciudad completa»). Sus dos llamadores (`ComprobarCoberturaCommand` y `ListCentros`) llaman a `->load()`, que solo existe en la colección de Eloquent; hoy no fallaba porque salen antes si está vacía. Sin cambio de comportamiento, sin test nuevo: `AmbitoCoberturaTest` 5 passed.
- `BACKLOG.md`: entrada de CI reescrita con el recuento por identificador (281 errores en local; 287 en CI sobre `584d5ad`), la propuesta para el falso positivo de `Livewire::test()` y el conflicto de Pint con los docblocks.

### Decisiones
- **En CI PHPStan ya terminaba** con 512M (1 min 36 s, 287 errores): la falta de memoria que citaba el BACKLOG no se reproduce. Se sube igualmente a 2G, como piden las instrucciones. En la máquina de desarrollo (1 CPU, 1 GB) el modo paralelo se queda parado; se ha medido por módulo con `--debug`.
- **Solo un fallo real con corrección local evidente** (el de `seccionesSinCobertura()`). El resto son falsos positivos de Livewire y de Larastan, anotaciones de tipos o `?->` innecesarios, que se quedan medidos en el BACKLOG. No se toca el baseline.
- **`argument.templateType` sin solución estándar:** Livewire 4.4.8 es la última versión y su docblock de `test()` impide inferir el componente; no hay extensión oficial de Larastan para Livewire. Propuesta en el BACKLOG.
- **Pint solo sobre el fichero modificado.** Su única corrección aplicable era `new EloquentCollection` sin paréntesis; `no_superfluous_phpdoc_tags` se deja sin aplicar porque borraría los `@param`/`@return` exigidos por `CLAUDE.md`. El auto-commit de Pint del workflow no commitea nada por la expansión del patrón `vida/**/*.php`; se documenta en el BACKLOG en lugar de arreglarlo, porque arreglarlo borraría docblocks en todo el proyecto.

## 2026-11-11 — Documentos: fallos de qpdf y Ghostscript con causa y registro

### Cambios
- `SaneadorPdf::rechazarSiProtegido()`: el código 2 de `qpdf --requires-password` solo cuenta como «sin cifrar» si la salida de error está vacía. Con salida de error, el PDF se rechaza con `pdf_no_normalizable`.
- Nueva `Modules/Documentos/app/Exceptions/ProcesoExternoFallidoException` (comando, código de salida y salida de error; código `null` si el proceso no terminó). Todos los rechazos por fallo de proceso de `SaneadorPdf` (protección, conteo de páginas, saneado, recompresión, expansión con qpdf y timeout) la llevan como excepción previa de `IngestaRechazadaException`.
- `DocumentosCiudadano::guardar()`: registra con `report()` los rechazos de la ingesta que tienen excepción previa (causa técnica). Los que no la tienen (formato, virus, páginas…) siguen sin ir al log.
- Tests en `DocumentosCiudadanoTest`: «un fallo de qpdf con salida de error se rechaza y queda en el log» (binario falso que devuelve 2 y escribe en la salida de error; falla con la condición anterior) y «un rechazo sin causa técnica no se registra en el log».
- `docs/modulo-documentos.md` §3 y `BACKLOG.md`: reglas locales de AppArmor para qpdf y gs sobre `storage/app/tmp` en el servidor, que hay que revisar tras cada actualización del sistema.
- Máquina de desarrollo: reglas locales de AppArmor ampliadas (sudo, por el desarrollador) con `/home/jupiter/code/vida-project/vida/storage/framework/testing/** rwk,` y `/home/jupiter/code/vida-project/vida/storage/app/tmp/** rwk,` en `/etc/apparmor.d/local/qpdf` y `/etc/apparmor.d/local/gs`, y perfiles recargados con `apparmor_parser -r`.
- Tests: `Modules/Documentos/tests` **91 passed** (445 assertions). Antes de ampliar las reglas: 64 fallos, todos por `Permission denied` de qpdf/gs; sin el cambio de código, `IngestaDocumentoTest` ya fallaba (7) por la misma causa.

### Decisiones
- **Los rechazos sin causa técnica no se registran,** para no llenar el log con errores del usuario. El criterio es si la `IngestaRechazadaException` tiene excepción previa (el antivirus no disponible también la tiene y también se registra).
- **El test llama a `guardar()` sobre la instancia del componente**, no con `->call()`: `Livewire::test()` sustituye el manejador de excepciones por uno que no registra nada mientras atiende cada llamada, así que `report()` no llegaría al log.
- **Se descubre un fallo oculto en la máquina de desarrollo:** el perfil AppArmor de qpdf le impide leer `storage/framework/testing/ingesta/{uuid}/original`, la zona temporal de los tests, que no tiene extensión y que las reglas locales no cubren. Antes se aceptaba como «sin cifrar» sin que se notara. El desarrollador ha añadido a mano (sudo) las rutas de tests y de la zona temporal local a las reglas; `DocumentosTest` usa `storage/app/tmp`, no la zona de tests, así que hacen falta las dos.

## 2026-11-11 — Usuarios / UI: nombre visible del usuario y tests previos reparados

### Cambios
- `User::nombreVisible()`: nombre completo del Profesional vinculado; si no hay Profesional (perfiles técnicos), el email.
- Vistas que mostraban `User::name` (que siempre es el email) o montaban el nombre a mano pasan a `nombreVisible()`: topbar y menú de usuario de `operativo-shell`, componente `x-avatar` (las iniciales salen de `nombreVisible()` si no se pasa `nombre`), portada `inicio`, onboarding y `Modules/Agenda/.../cuadrante-mes.blade.php`.
- `tests/Feature/Auth/AutenticacionTest.php`: el usuario de prueba se vincula a un Profesional «Juana López García» en lugar de fijar `name`. Nuevo TF-AUTH-16b: sin Profesional la UI muestra el email.
- `FichaAtencionTest` (TF-LW-AT-04) y `FichaCiudadanoPageTest`: el texto esperado pasa de «Ver historia social» a «Ir a HS», que es lo que muestra la ficha.
- Tests: `AutenticacionTest`, `FichaAtencionTest` y `FichaCiudadanoPageTest`: 51 passed, 1 incomplete (TF-AUTH-20) y 1 skipped (TF-AUTH-04), los dos previos. `CuadranteMesTest`: 5 passed. `ui:auditar` sin infracciones tras `npm run build`.

### Decisiones
- **No se toca `User::booted()`.** La corrección propuesta en el BACKLOG (rellenar `name` solo si viene vacío) se descarta: `name` no se expone en formularios y la identidad del usuario es la del Profesional. Los tests TF-AUTH-16/17 se adaptan a ese modelo.
- Los dos fallos de «Ver historia social» eran de los tests, no de la vista: el enlace se renombró a «Ir a HS» y los tests no se actualizaron.

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
