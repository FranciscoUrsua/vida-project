# Instrucciones CLI — Estabilización y privacidad (fases 0 y 1 del plan de cierre)

Encargo para Claude CLI. Dos bloques independientes del plan `docs/plan-cierre-funcional-2026-11.md`:

- **Bloque A — Estabilización:** subida de documentos, tests de Supervisión, CI de calidad, base de datos de desarrollo y formato de los documentos del 10 nov.
- **Bloque B — Privacidad:** huecos pendientes de `AccesoExpediente` y búsqueda por documento.

**Commit de partida:** `bbfc3fa` (11 nov 2026).
**Orden:** A1 → A2 → A3 → A5 → B1 → B2 → B3 → B4. A4 (base de datos) se hace cuando el desarrollador lo confirme (ver A4), y antes de lanzar ningún test si ya está disponible. Un commit por punto, o por bloque si son pequeños.

**Regla de trabajo:** si algo no está previsto aquí o el código contradice estas instrucciones, **detente y pregunta**. No amplíes `phpstan-baseline.neon`. No partas pantallas ni muevas módulos. Lo marcado como *fuera de alcance* no se toca.

**Antes de empezar:** `CLAUDE.md`, `SESSION.md`, `BACKLOG.md` (entradas citadas en cada punto), `docs/modulo-documentos.md` §2.1 y §3, `docs/modulo-auditoria.md` §3.5, `docs/instrucciones-cli/instrucciones-cli-acceso-auditoria.md` (reglas 1 a 6) y `docs/plan-cierre-funcional-2026-11.md`.

---

## Bloque A — Estabilización

### A1. Subida de documentos: rechazos que faltan y causa técnica

**Hechos que no hay que redescubrir** (detectados en staging el 11 nov con el juego de documentos de prueba):

- La tubería es `IngestaDocumentoService::ingerir()`: `copiarATemporal()` → `DetectorFormato::detectar()` → antivirus → conversión → `normalizar()` (`SaneadorPdf::rechazarSiProtegido()`, `sanear()`, `verificarSinContenidoActivo()`) → `contarPaginas()` → `ajustarTamanyo()`.
- **Un fichero de 0 bytes se guarda** como documento sin contenido, aunque `finfo` devuelve `application/x-empty` y `DetectorFormato` debería rechazarlo. No se sabe por dónde pasa.
- **Un PDF truncado se guarda en blanco:** Ghostscript lo repara, termina con código 0 y produce un PDF válido sin contenido.
- **`qpdf --requires-password` devuelve 2 tanto si el PDF no está cifrado como si hay un error** (p. ej. «Permission denied»). `rechazarSiProtegido()` toma el 2 como «sin cifrar» y da por bueno un fichero que no ha podido abrir.
- **La causa técnica se pierde:** `SaneadorPdf::ejecutar()` y `reescribir()` lanzan `IngestaRechazadaException` sin el comando, el código ni la salida de error, y `DocumentosCiudadano::guardar()` muestra el mensaje sin registrar nada. Así se tardó una hora en ver que el fallo de staging era AppArmor.
- **AppArmor en staging:** tras la actualización del sistema, `qpdf` y `gs` no podían leer `storage/app/tmp`. Se resolvió con reglas locales en `/etc/apparmor.d/local/qpdf` y `/etc/apparmor.d/local/gs` (`/var/www/vida-project/vida/storage/app/tmp/** rwk,`).

**Qué hacer:**

1. **Fichero vacío.**
   - Escribe primero un test de pantalla que suba un fichero de 0 bytes desde `DocumentosCiudadano` y compruebe que no se crea documento. Averigua por dónde pasa hoy y anótalo en el CHANGELOG.
   - En `IngestaDocumentoService::ingerir()`, justo después de `copiarATemporal()`, rechaza con un motivo nuevo `fichero_vacio` si el tamaño es 0.
   - Añade `min:1` a la regla de `fichero` en `DocumentosCiudadano::guardar()`, con el mensaje «El fichero está vacío.».
2. **PDF sin contenido visible.**
   - No rechaces por el aviso de reparación de Ghostscript: es habitual en PDF reales válidos (los del juego de pruebas, generados con ReportLab, lo dan).
   - Nuevo método `SaneadorPdf::tintaTotal(string $ruta): float`, con `gs -q -dSAFER -o - -sDEVICE=inkcov`. Suma la cobertura C+M+Y+K de todas las páginas.
   - Después de `normalizar()`, si la tinta total está por debajo de `documentos.ingesta.umbral_tinta_minimo`, rechaza con un motivo nuevo `pdf_sin_contenido`. Propón el valor y justifícalo en el CHANGELOG con las mediciones de los ficheros de prueba.
   - Cuenta el documento entero: una página en blanco entre otras con contenido no es motivo de rechazo.
   - En los PDF firmados se mide igual, pero nunca se reescriben.
3. **`qpdf` con código 2.** En `rechazarSiProtegido()`, el código 2 solo significa «sin cifrar» si la salida de error está vacía. Con salida de error, rechaza con `pdf_no_normalizable` y adjunta la causa (punto 4).
4. **Causa técnica.**
   - Nueva excepción `Modules\Documentos\Exceptions\FalloHerramientaExterna` (RuntimeException) con el comando (sin rutas de datos personales: el nombre del binario y los parámetros bastan), el código de salida y los primeros 2.000 caracteres de la salida de error.
   - Todo fallo de proceso en `SaneadorPdf` y en `ConversorPdfLocal::desdeOfimatica()` la adjunta como excepción previa de `IngestaRechazadaException`. Los fallos de Imagick ya llevan su `ImagickException`.
   - En `DocumentosCiudadano::guardar()`, al capturar `IngestaRechazadaException`, si tiene excepción previa, `report($e->getPrevious())`.
   - El mensaje al usuario no cambia.
5. **Mensajes nuevos** en `IngestaRechazadaException::MENSAJES`:
   - `fichero_vacio`: «El fichero está vacío.»
   - `pdf_sin_contenido`: «El documento no tiene contenido visible. Comprueba que no esté dañado o en blanco.»
6. **Documentación:**
   - `docs/modulo-documentos.md` §3: los dos motivos nuevos, la medición de tinta y el registro de la causa técnica.
   - `docs/decisiones-tecnicas.md`: nueva sección «Dependencias del sistema de la custodia». Debe recoger `qpdf`, `gs`, `pdfinfo`, `soffice`, Imagick y clamd; las reglas locales de AppArmor de staging, con la línea exacta; y que deben revisarse tras cada actualización del sistema, con la prueba rápida (subir un PDF y una imagen).

**Tests (TF-DOC-ING-01 a 08):**

| Id | Caso | Resultado |
|---|---|---|
| 01 | Fichero de 0 bytes por la pantalla | `fichero_vacio`, sin documento creado |
| 02 | Fichero de 0 bytes por el servicio | `fichero_vacio` |
| 03 | PDF truncado que queda en blanco tras repararlo | `pdf_sin_contenido` |
| 04 | PDF de una página en blanco | `pdf_sin_contenido` |
| 05 | PDF de tres páginas con la segunda en blanco | Aceptado |
| 06 | Imagen JPEG de un documento | Aceptado |
| 07 | Binario que devuelve 2 con salida de error en `rechazarSiProtegido()` | `pdf_no_normalizable`, con `FalloHerramientaExterna` como previa |
| 08 | Fallo de Ghostscript desde la pantalla | Mensaje sin cambios y la causa en el log (`Log::spy()` o `Exceptions::fake()`) |

Los ficheros de prueba se generan en el propio test o van a `Modules/Documentos/tests/fixtures/`, pequeños. Comprueba en negativo los tests 03, 04 y 07: deben fallar sin el cambio.

**Fuera de alcance:** retirada lógica y vínculo con solicitud (van con `consulta_basica`); cambiar la normalización PDF/A.

---

### A2. Tests de Supervisión en la suite

BACKLOG: «Suite de tests: Supervisión no se ejecuta».

1. Añade `<directory>Modules/Supervision/tests/Feature</directory>` a la suite `Feature` de `phpunit.xml`. Si hay tests en otra subcarpeta, añádela también.
2. Ejecuta `Modules/Supervision/tests` y revisa los tres fallos conocidos. Para cada uno, decide con evidencia si el test está desfasado o si falta algo en la aplicación:
   - `sidebar_sin_plazas_no_muestra_item_plazas` («el menú no tiene "Aprobaciones"»): comprueba en `CHANGELOG-*` si «Aprobaciones» se retiró o se movió a propósito.
   - `ficha_profesional_muestra_tres_pestanas`.
   - `auditoria_con_colectivos_muestra_columna_protegido` (TF-SUP-F03): según el BACKLOG, el test no crea ningún acceso antes de montar la página. Corrige el test.
3. Si un test describe algo que la aplicación debería hacer y no hace, **no lo implementes**: pregunta.
4. Cierra en el BACKLOG «Suite de tests: Supervisión no se ejecuta» y «TF-SUP-F03 falla», con la evidencia. La viñeta «Tipo de slot genérico en modo básico» de esa entrada no se cierra: muévela a «Citas: pendientes de la fase».

---

### A3. CI «Calidad de código»

BACKLOG: «CI «Calidad de código» (PHPStan + Pint) en rojo».

1. Haz que PHPStan **termine**: sube `--memory-limit` (prueba 2G) en `.github/workflows/quality.yml` o en el script de Composer que use.
2. Lanza PHPStan en local con la misma configuración y clasifica los errores por identificador (`argument.templateType`, propiedades `#[Computed]`, `env()` fuera de `config/`, métodos inexistentes…), con el recuento de cada uno.
3. Corrige **solo** los errores que sean fallos reales de la aplicación y que tengan una corrección local y evidente (p. ej. un método inexistente que se llama). Cada uno con test si cambia comportamiento.
4. Para los falsos positivos de Livewire, investiga si `larastan` y la versión de Livewire en uso tienen extensión o stubs oficiales. Si existe una solución estándar, aplícala. Si no, déjalo en el BACKLOG con la propuesta.
5. Pint: aplica `--test` y corrige lo que señale, sin tocar ficheros fuera del alcance de los errores. Tras Pint, restaura los `@return` (ver `SESSION.md`).
6. Actualiza la entrada del BACKLOG con el recuento final por categoría. El objetivo de este punto es que el job termine y que lo que queda esté medido, no dejarlo en verde a cualquier precio.

**Fuera de alcance:** migrar a `larastan/larastan` (entrada propia del BACKLOG), salvo que sea la solución del punto 4 y no cambie nada más.

---

### A4. Base de datos de desarrollo propia

BACKLOG: «Local y staging comparten la misma base de datos `vida`».

**Este punto necesita confirmación del desarrollador antes de ejecutar nada en la base de datos.** Pregunta y espera respuesta a:
- ¿Se crea `vida_dev` en el mismo servidor PostgreSQL (`127.0.0.1`) y con qué usuario?
- ¿Se carga con `migrate --seed` y un mundo demo, o con una copia anonimizada de staging? Por defecto, `migrate --seed` y el mundo demo estándar.

Con la confirmación:
1. Crea la base de datos con los comandos que indique el desarrollador.
2. Cambia **solo** el `.env` local (`~/code/vida-project/vida/.env`, que no está en Git): `DB_DATABASE=vida_dev`. Antes de migrar, comprueba con `php artisan db:show` que la conexión apunta a `vida_dev`. **Si apunta a `vida`, detente.**
3. `php artisan migrate --seed` y la carga del mundo demo sobre `vida_dev`. Las variables `DOCUMENTOS_*` locales apuntan a un directorio local propio, no a `/srv/vida/documentos`.
4. Documenta en `SESSION.md` («Estado exacto»): la BD local es `vida_dev`; `vida` es staging; `demo:reset` ya se puede lanzar en local, nunca contra `vida`. Quita los avisos ⚠️ que ya no apliquen.
5. Cierra la entrada del BACKLOG.

`vida_testing` no cambia.

---

### A5. Formato Markdown de los documentos incorporados el 10 nov

Duda 1 de `CHANGELOG-112026.md`. Ficheros:
- `docs/modulo-citas.md`, `docs/modulo-tipo-centro.md`, `docs/roles-consulta-basica-tramitacion.md`, `docs/propuesta-requisitos-y-tareas-guiadas.md`, `docs/estado-proyecto-2026-11-10.md`;
- `docs/instrucciones-cli/2026-10-tipo-centro-implementacion.md`, `docs/instrucciones-cli/2026-10-tipo-centro-tests.md`, `docs/instrucciones-cli/2026-11-puesta-al-dia-documental.md`.

**Reglas:**
- Solo estructura: `#` en los títulos según su jerarquía; tablas Markdown en lugar de tabuladores; listas; código, rutas, clases, métodos, comandos y claves entre comillas invertidas; negritas donde el texto marque énfasis evidente (p. ej. «RN-01 —»).
- **No cambies ni una palabra.** Verificación obligatoria por fichero: compara la secuencia de palabras antes y después, quitando los signos de formato (`#`, `|`, `*`, `` ` ``, `-` de lista). Por ejemplo, `tr -c '[:alnum:]áéíóúüñÁÉÍÓÚÜÑ' '\n' < f | grep . > palabras.txt`. Las dos listas deben ser idénticas. Anota en el CHANGELOG que la comprobación ha pasado para cada fichero.
- Si en algún punto la jerarquía o el formato son ambiguos, elige lo más simple y anótalo.

**Además** (duda 2 del CHANGELOG, decidido): en `docs/modulo-citas.md`, donde se habla del destino «profesional de referencia», añade «(la asignación vigente de su historia: `AsignacionReferenciaService::vigente()`, ver `docs/modulo-asignacion.md`)», como estaba en la versión implementada.

Las dudas 3, 4 y 5 del CHANGELOG no se tocan.

---

## Bloque B — Privacidad

BACKLOG: «Acceso a expedientes: pendientes tras AccesoExpediente». Las reglas de producto son las de `instrucciones-cli-acceso-auditoria.md` (1 a 6). El criterio de colectivo protegido es único: `CiudadanoPolicy::consultaExternaPermitida()` / `Gate::allows('view', $ciudadano)`. No crees otro.

### B1. Personas relacionadas protegidas

**Hecho:** la ficha (`FichaCiudadanoPage`) y el expediente (`CiudadanoPage`) cargan convivientes (`ucMiembros`), relaciones (`ciudadanoRelacionado`) y representante sin scope, y muestran el nombre aunque la persona sea de un colectivo protegido de otra UO. Los buscadores de relación y de UC ya las filtran.

**Regla (decidida):** se muestra que existe la relación y su tipo, pero no la identidad. En lugar del nombre: «Persona con protección especial», sin documento, fecha de nacimiento, domicilio, alias ni enlace a su ficha, igual que `BuscadorPersonasCita`. El usuario que sí puede verla la ve como hoy.

**Qué hacer:**
1. Un único presentador para «persona en un listado» (p. ej. `App\Services\PresentacionPersona::paraListado(Ciudadano, User): array`), que use la policy y devuelva los datos visibles o la versión protegida. Que lo usen convivientes, relaciones y representante en ficha y expediente, y, si es trivial, los buscadores que hoy filtran por su cuenta.
2. Las acciones sobre una relación con una persona protegida que no se puede ver (editar, cerrar, abrir su ficha) no se ofrecen. En el servidor, las acciones Livewire correspondientes comprueban la policy antes de actuar.
3. Revisa si hay más sitios que pinten personas relacionadas (bandeja de asignaciones aparte, PDF del plan, participantes del plan) y lista en el CHANGELOG los que encuentres. Corrige los de ficha y expediente. Los demás, al BACKLOG, salvo que la corrección sea usar el mismo presentador.

### B2. `VerFichaPage` y la historia de la ruta

**Hecho:** `/intervencion/ciudadano/{historia}/ficha/{ficha}` autoriza la historia, pero pinta cualquier `Ficha` por id.

**Qué hacer:** en `mount()`, comprueba que la ficha pertenece a esa historia (por la relación que exista: valoración → historia, o la que el modelo tenga; averígualo). Si no pertenece, responde 404 sin datos. Si la relación no es inequívoca, pregunta.

### B3. `guardar()` de la ficha por policy

**Hecho:** `FichaCiudadanoPage::guardar()` decide con `puedeEditar()` (rol), no con `CiudadanoPolicy::update`.

**Qué hacer:**
1. Comprueba qué roles y condiciones admite hoy `CiudadanoPolicy::update`. **El comportamiento para los roles que hoy editan no debe cambiar**, salvo con colectivos protegidos sin acceso, que deben recibir 403.
2. `guardar()` autoriza con `Gate::authorize('update', $ciudadano)`. `puedeEditar()`, si se usa para mostrar u ocultar el botón, delega en la misma policy.
3. Si la policy y `puedeEditar()` difieren para algún rol en un caso no protegido, **detente y pregunta** antes de unificar.

### B4. Búsqueda por documento en el buscador principal

**Hecho:** `BuscarCiudadanoPage::buscarPorIdentificador()` (`Modules/Intervencion/app/Http/Livewire/BuscarCiudadanoPage.php:143`) devuelve vacío con un TODO. `ciudadano_identificadores` existe; `valor_hash` = `sha256(strtolower(valor))` (`CiudadanoIdentificador::booted()`); `MotorMatching` y `BuscadorPersonasCita` ya buscan así.

**Qué hacer:**
1. Extrae el cálculo a un único método estático (p. ej. `CiudadanoIdentificador::hashDe(string $valor): string`) y úsalo en el modelo, `MotorMatching`, `BuscadorPersonasCita` y el buscador. En la consulta, aplica `trim()` además. **No cambies la normalización de lo guardado** (cambiarla invalidaría los hashes existentes): si crees que debería quitar espacios o guiones, déjalo en el BACKLOG.
2. Implementa `buscarPorIdentificador()` para `doc`: coincidencia exacta por hash, sin filtrar por UO, y cada resultado por `resolverResultado()` (que ya devuelve nivel 3 sin datos a los protegidos sin acceso).
3. Para `hsu` (NI-HSU-CM): comprueba si `ciudadano_identificadores.tipo` tiene un valor para ese identificador. Si lo tiene, la misma búsqueda filtrando por tipo. Si no, pregunta.
4. Quita el TODO y actualiza el docblock.

**Tests (TF-ACC-13 a 22):**

| Id | Caso | Resultado |
|---|---|---|
| 13 | Ficha: conviviente protegido de otra UO sin acceso | Se ve la relación, no el nombre ni el documento; sin enlace |
| 14 | Ficha: relación con persona protegida sin acceso | Tipo de relación visible, identidad no; no se puede editar ni cerrar (también llamando a la acción) |
| 15 | Expediente: representante protegido sin acceso | Igual que 13 |
| 16 | Mismos casos con acceso aprobado vigente | Se ven como hoy |
| 17 | `VerFichaPage` con una ficha de otra historia | 404, sin datos en la respuesta |
| 18 | `VerFichaPage` con una ficha de la historia | Se muestra |
| 19 | `guardar()` de la ficha de un protegido sin acceso, llamando a la acción | 403, sin cambios en BD |
| 20 | `guardar()` por los roles que hoy editan, persona no protegida | Sin cambios de comportamiento |
| 21 | Buscador principal por documento exacto, con espacios alrededor | Encuentra a la persona |
| 22 | Buscador principal por documento de un protegido sin acceso | Nivel 3: sin nombre, alias ni documento |

Comprueba en negativo los tests 13, 14, 17, 19 y 22: deben fallar sin el cambio.

**Fuera de alcance del bloque B:**
- La bandeja de asignaciones con protegidos (pendiente de la decisión D4 del plan).
- Retirar o reutilizar el middleware `AuditarAccesoCiudadano`.
- La búsqueda por nombre cifrado (índice hash determinista).

---

## Cierre

1. Suites de los módulos tocados: Documentos, Supervisión, Ciudadanía, Intervención, `tests/Feature/Acceso`, `tests/Feature/Auth`. Después, **la suite completa** (con A4 hecho, sobre `vida_testing` como siempre; no lanzar dos ejecuciones a la vez). Sin fallos nuevos.
2. `npm run build` y `php artisan ui:auditar` en verde si se ha tocado Blade o SCSS.
3. `CHANGELOG-112026.md`: una entrada por bloque, con las decisiones de implementación no previstas aquí, los negativos comprobados y las verificaciones de A5.
4. `BACKLOG.md`: cierra con evidencia lo resuelto, actualiza lo parcial y añade lo nuevo (la normalización de identificadores si procede, los sitios del punto B1.3 no corregidos, lo que quede de PHPStan).
5. `SESSION.md`: estado y siguiente paso. El siguiente paso es la fase 2 del plan (Tipo de Centro), pendiente de las decisiones D1 y D2.
6. `CLAUDE.md` §6: añade este fichero.
7. `git push origin master` y verifica que el despliegue termina en verde (`deploy` del workflow `CI/CD`).

## Criterios de finalización

1. TF-DOC-ING-01 a 08 y TF-ACC-13 a 22 en verde, con los negativos comprobados.
2. `Modules/Supervision/tests` dentro de `phpunit.xml` y en verde.
3. El job de PHPStan termina (verde o rojo, pero sin quedarse sin memoria) y el BACKLOG tiene el recuento.
4. Los ocho documentos del 10 nov en Markdown, con la comprobación de palabras anotada.
5. A4 hecho, o anotado en `SESSION.md` como pendiente de la confirmación del desarrollador.
6. Suite completa sin fallos nuevos. CHANGELOG, BACKLOG, SESSION y CLAUDE.md actualizados. Despliegue en verde.
