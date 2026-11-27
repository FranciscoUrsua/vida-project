# Instrucciones CLI — Ficha de datos sociodemográficos y catálogos oficiales

**Origen:** decisión del 26 nov 2026, `docs/decisiones-tecnicas.md` sección 19.1. Los datos sociodemográficos de la persona (país de nacimiento, nacionalidad, estudios, situación laboral, ingresos…) se recogen con una ficha configurable más (`TipoFicha`), no con una tabla de formulario fijo. Para que los campos codificados usen clasificaciones estándar sin teclear cientos de opciones a mano, se amplía `catalogos_sistema` con catálogos versionados que se cargan desde ficheros oficiales, y se añade un tipo de campo «selector de catálogo».
**Requisito previo:** bloque B de `docs/instrucciones-cli/2026-11-encargo-actuaciones.md` cerrado (extrae el guardado de fichas a un servicio y añade `fichas.derivacion_id`). Si no lo está, **detente y pregunta**.
**Tests:** TF-SDE-01 a TF-SDE-34 (al final de este documento).
**Estado:** revisado por el desarrollador el 26 nov 2026. Paso 0 y bloques A, B y C hechos el 26 y 27 nov 2026. Las claves especiales de `comun.pais` (OACI) y `equivalencias.ine` quedan aplazadas por decisión del desarrollador (BACKLOG).

**Alcance:**
- **A.** Catálogos versionados en `catalogos_sistema` y carga desde fuentes oficiales.
- **B.** Tipo de campo «catálogo» en `TipoFicha` (backoffice, validación, renderizado y visualización).
- **C.** Fichas de persona: `uso = persona`, vínculo con el ciudadano sin Historia Social, «no desea contestar», permisos.
- **D.** Interfaz en la ficha del ciudadano.
- **E.** Ficha inicial «Datos sociodemográficos».

**Regla de trabajo:** si el código contradice estas instrucciones o aparece un caso no previsto, **detente y pregunta**. Lo marcado como *fuera de alcance* no se toca.

**Regla sobre los datos oficiales:** los catálogos se construyen **solo** a partir de ficheros descargados de las fuentes indicadas. Nunca se escriben listas de países, idiomas o niveles de estudios de memoria ni se completan a mano. Si no puedes descargar una fuente (red, formato, licencia), detente y pide el fichero al desarrollador.

**Antes de empezar:** `CLAUDE.md`, `SESSION.md`, `docs/principios-vida360.md` (3.1, 4.2, 4.3, 4.13), `docs/decisiones-tecnicas.md` (secciones 16 y 19), `docs/modulo-intervencion.md` (§4: fichas, `schema_snapshot`, versionado), `docs/modulo-ciudadania.md`, `docs/roles-consulta-basica-tramitacion.md`, `docs/design-system/SKILL.md` y `docs/instrucciones-cli/2026-09-bootstrap-unico.md`.

---

## Hechos que no hay que redescubrir

- **`catalogos_sistema`** (`App\Models\CatalogoSistema`): `grupo`, `clave`, `etiqueta`, `valor`, `orden`, `activo`, único (`grupo`, `clave`). `opcionesParaSelect($grupo)` devuelve `[clave => etiqueta]` de los activos. Principio 4.2: sus valores **nunca** se referencian por nombre en lógica de negocio. No hay recurso de Filament genérico para el catálogo.
- **Precedente de carga oficial:** `Modules\Organizacion\Services\CargaUnidadesTerritoriales` carga barrios y secciones censales desde `Modules/Organizacion/database/data/` (fichero oficial tal cual y carpeta `fuentes/`), con upsert por código, sin reactivar lo desactivado a mano. La usan los seeders **y una migración de datos**, porque los seeders no se ejecutan al desplegar (decisiones técnicas, sección 16). Se sigue el mismo patrón.
- **`TipoFicha`** (`tipo_fichas`): `nombre`, `descripcion`, `schema`, `activo`, `uso` (`valoracion` | `solicitud`). `TIPOS_CAMPO = ['texto', 'numero', 'select', 'booleano', 'fecha', 'escala']`. `validarSchema()` en `saving`. No tiene código estable.
- **`campo_id` es estable:** `TipoFichaResource::dehydrateStateUsing` lo genera desde la etiqueta solo si el campo es nuevo.
- **Las opciones de `select` se guardan como texto plano** (`normalizarOpcionesParaSchema`): etiqueta y valor guardado son lo mismo. Por eso los campos codificados de esta ficha usan el tipo nuevo «catálogo», no `select`.
- **`Ficha`** (`fichas`): `historia_id` (nullable), `valoracion_id` (nullable), `tipo_ficha_id`, `schema_snapshot`, `profesional_id`, `datos`, `notas`, `completada`, más `derivacion_id` del encargo. Traits `Auditable` y `Versionable`. `getCiudadanoId()` pasa por la historia. Pre-relleno: `Ficha::prerellenarDesde()` (solo lo usan los tests; `datosPrerrellenados()` no existe, ver el resultado del paso 0).
- **Renderizado común:** `resources/views/components/ficha/campos.blade.php` (`<x-ficha.campos>`), usado por `registrar-valoracion-page` y `solicitud-page`. La visualización de fichas guardadas está repartida: `ver-ficha-page` (con `MuestraFicha`), el modal de apunte de `ciudadano-page`, la tarjeta de diagnóstico de `plan-page` y `pdf/plan`.
- **Ficha del ciudadano:** `Modules\Ciudadania\Http\Livewire\FichaCiudadanoPage`, con secciones en tarjetas y computeds `puedeVer…()`.
- **Permisos:** nombres `ciudadano.*`; los permisos nuevos los añade una migración de datos; `RolesSeeder` es aditivo (sección 16).

---

## Decisiones

| Id | Decisión |
|---|---|
| SD-1 | **Catálogos versionados en `catalogos_sistema`**, no en tablas nuevas. Columnas nuevas: `vigente_desde` y `vigente_hasta` (date, nullable), `atributos` (jsonb, agrupaciones: continente, UE…), `equivalencias` (jsonb, códigos en otros sistemas: `{"iso2": "ES", "m49": "724", "ine": "108", "hsu": null}`) y `fuente` (string: fuente y versión del fichero). La `clave` es el código estándar y **no cambia nunca** |
| SD-2 | **Carga oficial idempotente.** Servicio único `App\Services\Catalogos\CargaCatalogosOficiales`, con un método por catálogo. Lee CSV normalizados de `database/data/catalogos/` (los originales, tal cual, en `database/data/catalogos/fuentes/`, con un `README.md` que anota URL, fecha de descarga, versión y licencia de cada fichero). Upsert por (`grupo`, `clave`): actualiza la etiqueta, los atributos y las equivalencias, no reactiva lo desactivado a mano y **no borra**. Un valor que desaparece de la fuente recibe `vigente_hasta`. Lo usan un seeder y una migración de datos |
| SD-3 | **Catálogos de esta entrega** (ver la tabla de fuentes): `comun.pais`, `comun.nivel_estudios`, `comun.situacion_laboral` y `comun.idioma`. Son descriptivos (principio 4.2) y reutilizables por cualquier ficha |
| SD-4 | **Tipo de campo `catalogo`.** El schema guarda `catalogo` (el grupo); la ficha guarda la **clave**, nunca la etiqueta. Validación: el grupo existe y tiene al menos dos valores activos. Con fichas asociadas, cambiar el grupo de un campo está prohibido (como cambiar el tipo). El selector ofrece los valores activos y vigentes **con búsqueda** |
| SD-5 | **Visualización única.** Un helper, `App\Support\Fichas\ValorCampo::mostrar(array $campo, mixed $valor): string`, formatea el valor de cualquier tipo de campo para pantalla y PDF (para `catalogo`: la etiqueta actual de la clave, aunque ya no esté activa o vigente). Todas las vistas de fichas guardadas pasan a usarlo |
| SD-6 | **`TipoFicha.codigo`**: string, único, nullable para los tipos existentes, inmutable si hay fichas. Es el identificador estable para la analítica y para el código (`modulo-intervencion.md` §4.3). La ficha inicial lleva `codigo = sociodemografica` |
| SD-7 | **Fichas de persona: `uso = persona`.** Una ficha de este uso pertenece al ciudadano, no a una Historia Social: `fichas.ciudadano_id` (FK, nullable), con `historia_id`, `valoracion_id` y `derivacion_id` nulos. `CHECK`: una ficha de uso persona tiene `ciudadano_id`. `getCiudadanoId()` devuelve `ciudadano_id` si está informado. **No genera apunte** en la Historia: no es un acto de intervención |
| SD-8 | **Vigencia y versionado.** La situación vigente de una persona es su última ficha completada de ese tipo. Actualizar la situación es **una ficha nueva** pre-rellenada desde la anterior (pasado inmutable, principio 4.3); corregir un error de grabación es la corrección `Versionable` ya existente. Al pre-rellenar, una clave de catálogo que ya no está activa o vigente se deja vacía y se avisa |
| SD-9 | **Todo voluntario.** En las fichas de persona ningún campo es obligatorio (la validación lo rechaza en el backoffice) y cada campo admite «No desea contestar». Se guarda en una columna aparte, `fichas.no_contesta` (jsonb, lista de `campo_id`), para no mezclar marcas con valores en `datos`. Así la analítica distingue «no contesta» de «no se preguntó» |
| SD-10 | **Permisos nuevos:** `ciudadano.leer_fichas_persona` (intervención, supervisión, consulta básica) y `ciudadano.registrar_fichas_persona` (intervención y consulta básica: se puede recoger en la acogida). Personas protegidas: consulta básica no ve ni registra fichas de persona (solo ve datos objetivos). Lectura y escritura quedan en la auditoría como el resto de fichas |
| SD-11 | **Sin filtro por tipo de centro** en esta entrega: las fichas de persona son de la persona y se ofrecen en todos los centros. `ConfiguracionEfectivaCentro::tiposFicha()` sigue filtrando solo las de valoración |
| SD-12 | **Ficha inicial «Datos sociodemográficos»** (`codigo = sociodemografica`, uso persona), sembrada al desplegar y editable después en el backoffice como cualquier `TipoFicha`. Campos en el bloque E |

### Fuentes oficiales (SD-3)

| Grupo | Clave | Fuente principal | Atributos y equivalencias |
|---|---|---|---|
| `comun.pais` | ISO 3166-1 alfa-3 | División de Estadística de la ONU, metodología M49 (CSV con códigos ISO alfa-2, alfa-3 y numérico, y regiones). Nombres en español: la versión en español de M49 o la relación de países del INE | `atributos`: región y subregión M49, `ue` (sí/no, según la lista oficial de Estados miembros de la UE). `equivalencias`: `iso2`, `m49` y `ine` (código de país del INE, para el padrón), cuando exista |
| `comun.pais` (claves especiales) | Códigos del documento 9303 de la OACI | `XXA` apátrida, `XXB` refugiado, `XKX` Kosovo (si M49 no lo incluye) y `XXX` no consta | Marcados con `atributos.especial = true` |
| `comun.nivel_estudios` | Código CNED-A 2014 (niveles de formación alcanzados) | INE, Clasificación Nacional de Educación 2014 (CNED-2014) | `equivalencias.cine2011` (correspondencia oficial con la CINE/ISCED 2011). `atributos.agrupacion`: el nivel agregado de la propia CNED |
| `comun.situacion_laboral` | Código de la variable de relación con la actividad de la EPA | INE, Encuesta de Población Activa (diseño de registro o metodología vigente) | `atributos.grupo`: activo ocupado / activo parado / inactivo |
| `comun.idioma` | ISO 639-1 (o 639-2 si no hay código de dos letras) | Autoridad de registro de ISO 639 (Biblioteca del Congreso de EE. UU.). Nombres en español: datos de locale `es` de Unicode CLDR | `equivalencias.iso639_2` |

Si al descargarlas encuentras una fuente mejor o más actual que las de la tabla (por ejemplo, un fichero oficial del INE con códigos ISO incluidos), proponla antes de usarla.

---

## Fuera de alcance

- Exportación a BigQuery y tabla de hechos (decisiones técnicas, sección 19.2): entrega posterior.
- Código estable en las opciones de los `select` existentes (pendiente de la sección 19.1).
- Equivalencias con la HSU: la clave `hsu` de `equivalencias` queda vacía hasta que haya especificación.
- Derivar los ingresos del hogar a partir de las fichas de los miembros de la unidad de convivencia (es analítica).
- Filtro de fichas de persona por tipo de centro y requisitos de acción sobre estos datos.
- Recurso de Filament genérico para editar `catalogos_sistema`, salvo lo imprescindible del paso B.2.

---

## Paso 0 — Verificación

1. `git pull`, revisión de la suite nocturna. Confirma que el bloque B del encargo está cerrado.
2. Lista todos los puntos que crean, leen o muestran fichas (`Ficha::create`, `RegistrarValoracionPage`, `SolicitudPrestacionService`, `ver-ficha-page`, `ciudadano-page`, `pdf/plan`, servicios del bloque B del encargo) y confirma si las fichas de solicitud tienen `historia_id`.
3. Busca un componente de selección con búsqueda ya existente (búsqueda de personas para relaciones, selector de prestaciones…). Si no hay ninguno reutilizable, **propón** la solución antes de añadir dependencias: el catálogo de clases de `config/ui-catalogo.php` es cerrado y no se cargan librerías por CDN.
4. Comprueba que se pueden descargar las fuentes oficiales de la tabla. Si alguna falla, detente y pide el fichero.
5. Anota aquí el resultado y corrige los «Hechos» si no coinciden.

### Resultado del paso 0 (26 nov 2026)

**Requisitos.** Suite nocturna en verde (run 45846433450). Bloque B del encargo cerrado: `ValoracionService` con `ContextoActuacion`, `fichas.derivacion_id`, `FichaPolicy` (vías historia y encargo).

**Puntos que crean fichas:** `ValoracionService::registrar()` (valoración, Historia y encargo) y `SolicitudPrestacionService` (formulario de solicitud). Las fichas de solicitud **sí tienen `historia_id`** (la Historia abierta de la persona).

**Puntos que muestran fichas guardadas** (todos pasan a `ValorCampo::mostrar()` en B.5):
- `Concerns\MuestraFicha::camposConValor()` + `ver-ficha-page.blade.php` (usados por `VerFichaPage` y `VerFichaEncargoPage`). La cabecera dice «Ficha de valoración» fija: para las de persona debe decir otra cosa.
- Modal de apunte de `CiudadanoPage` (`modalApunteDatos['ficha_campos']`, `CiudadanoPage.php` ~1252, vista ~811).
- Tarjeta de diagnóstico de `plan-page.blade.php` (~231): hoy pinta `campo_id => valor` crudo, sin schema. Pasa a usar el schema y `ValorCampo` (cambio visible: etiqueta en lugar del id).
- `pdf/plan.blade.php` (~198).
- Edición: `<x-ficha.campos>` (`registrar-valoracion-page`, `solicitud-page`).

**Correcciones a los «Hechos»:**
- `Ficha::datosPrerrellenados()` **no existe**. `Ficha::prerellenarDesde()` existe pero solo lo usan los tests (`FichaVersionadoTest`): ninguna pantalla pre-rellena hoy. B.6 cambia su firma (devuelve también los campos afectados) sin romper llamadas de producción.
- La «visibilidad condicional» que cita el docblock de `TipoFicha` **no está implementada** (ni en el schema, ni en `TipoFichaResource`, ni en `<x-ficha.campos>`). Por tanto `anio_llegada_espana` no es condicional (bloque E): anotado en BACKLOG.
- `tipo_fichas.uso` es `string(20)` sin `CHECK`: admite `persona` sin migración de tipo.
- El `CHECK` de SD-7 no puede leer `uso` (está en `tipo_fichas`): se valida en `FichaPersonaService` y se prueba (TF-SDE-19), como prevé C.1.

**Selector con búsqueda (decisión de implementación).** No hay componente genérico, pero `Modules\Ciudadania\Http\Livewire\DomicilioCallejero` (Livewire con `#[Modelable]`, `input type=search` con `wire:model.live.debounce`, `list-group` + `op-lista-scroll`, búsqueda en servidor) resuelve lo mismo. Se crea un componente Livewire `selector-catalogo` con ese patrón, enlazado a `datos.{campo_id}` desde `<x-ficha.campos>`; búsqueda sin acentos sobre los vigentes del grupo. Sin dependencias nuevas ni clases nuevas en el catálogo.

**Fuentes oficiales (decisiones del desarrollador del 26 nov):**

| Catálogo | Fuente usada | Notas |
|---|---|---|
| `comun.pais` | M49 de la ONU, tabla en español (`downloadTableES` de unstats.un.org/unsd/methodology/m49/overview/) | 248 filas con ISO-2, ISO-3, M49, región y subregión. No incluye Kosovo (`XKX`, va como clave especial) ni Taiwán |
| `comun.pais` → `atributos.ue` | Glosario de códigos de país de Eurostat (27 Estados miembros) | La página de europa.eu solo enlaza 20 países. Eurostat usa `EL` para Grecia (ISO `GR`): la normalización lo traduce y lo comenta |
| `comun.pais` → `equivalencias.ine` | **Ninguna por ahora** (decisión del desarrollador) | La tabla de Países/Nacionalidades de IDA-Padrón solo se descarga desde su utilidad web y `arce.ine.es` no responde. `ine` queda nulo; Taiwán queda fuera hasta tener el fichero. BACKLOG |
| `comun.pais` (claves especiales) | Doc 9303 parte 3 de la OACI | **Bloqueada (403).** Pendiente de que el desarrollador deje el PDF; sin él no se cargan `XXA`, `XXB`, `XXC`, `XXX`, `XKX` |
| `comun.nivel_estudios` | INE, `CNED14_A_estr.xls` y `CORR_CNED14_CINE11_A.xls` (actualizados 20-04-2022) | Clave: código de dos cifras (24 niveles). `atributos.agrupacion`: nivel de una cifra; `atributos.etapa`: A/B/C. `equivalencias.cine2011`: código CINE-A de tres cifras |
| `comun.situacion_laboral` | INE, diseño de registro de la EPA 2026 (`dr_EPA_2026.xlsx`), **situación autopercibida** (decisión del desarrollador) | No se usa `AOI` (la deriva el INE con criterios OIT; no se puede asignar en una entrevista). Se unen `SIDAC` (1 trabajando, 2 buscando empleo) y `SIDI` (01 a 07; sin el 00 «no sabe»), 9 valores, con claves con prefijo para que no choquen (`SIDAC-1`, `SIDI-01`…). `atributos.grupo`: ocupado / parado / inactivo |
| `comun.idioma` | **SIL International**, `iso-639-3.tab` (autoridad de registro de ISO 639-3; decisión del desarrollador) + nombres `es` de Unicode CLDR 49 (`cldr-localenames-full`) | La lista de loc.gov está bloqueada por Cloudflare. Bloque A: lenguas individuales y macrolenguas **vivas** con código 639-1 o 639-2 (368; 174 con código de dos letras); clave 639-1 o, si no hay, 639-2 terminológico. Tres sin nombre en CLDR (`cnr`, `luo`, `vai`) llevan el nombre de SIL en inglés y `atributos.nombre_es = false`. La lengua de signos española (`ssp`) no tiene código 639-2: no entra |

## Bloque A — Catálogos versionados y carga oficial

1. Migración: columnas de SD-1 en `catalogos_sistema`. Las filas existentes quedan con todo nulo (sin vigencia = vigente siempre).
2. `CatalogoSistema`: casts, `@property` nuevas, scope `vigentes(?Carbon $fecha = null)` (activo y dentro de vigencia) y `opcionesParaSelect()` sobre vigentes. Método `etiquetaDe(string $grupo, string $clave): ?string` que devuelve la etiqueta aunque el valor esté inactivo o no vigente (para mostrar fichas antiguas).
3. Ficheros: descarga los originales a `database/data/catalogos/fuentes/` sin tocarlos y escribe `fuentes/README.md` (URL, fecha, versión, licencia). Genera los CSV normalizados (`clave;etiqueta;orden;atributos;equivalencias;fuente`) con un comando reproducible, `php artisan catalogos:normalizar`, y versiona ambos. El comando no se ejecuta al desplegar.
4. `CargaCatalogosOficiales` (SD-2): `paises()`, `nivelesEstudios()`, `situacionesLaborales()`, `idiomas()` y `todos()`. Devuelve un recuento (creados, actualizados, sin vigencia). Lanza una excepción si falta un fichero o si una clave aparece dos veces.
5. `CatalogosOficialesSeeder` (también en `DatabaseSeeder`) y migración de datos que llama a `todos()` para que los catálogos estén en cada entorno al desplegar.

**Hecho el 26 nov 2026.** Migraciones `2026_11_26_200001` (columnas) y `2026_11_26_200002` (carga), que se aplican con el despliegue. Decisiones de implementación:
- `vigente_hasta` es el **último día vigente** (inclusive): un valor que desaparece de la fuente recibe la fecha de ayer y deja de ofrecerse el mismo día de la carga. Si vuelve a aparecer en la fuente, recupera la vigencia. Los valores nuevos no reciben `vigente_desde` (no hay fecha oficial de alta).
- `deGrupo()` (y con él `opcionesParaSelect()` y `opcionesParaSelectConPrefijo()`) aplica `vigentes()`; para los catálogos existentes no cambia nada (no tienen fechas).
- La carga compara `atributos` y `equivalencias` sin orden (jsonb reordena las claves), para no contar como «actualizado» lo que no ha cambiado.
- `todos()` valida los cuatro ficheros antes de escribir y carga en una sola transacción.
- La normalización lee los `.xls`/`.xlsx` del INE con un lector OOXML mínimo propio (`LectorXlsx`, ZipArchive + SimpleXML) en lugar de añadir PhpSpreadsheet. La columna `fuente` sale de `fuentes/fuentes.json`.
- TF-SDE-07 no comprueba `XXA`/`XXX` (claves especiales aplazadas). Se añade TF-SDE-07 bis: los CSV versionados coinciden con lo que genera `catalogos:normalizar` desde los originales.

## Bloque B — Tipo de campo «catálogo»

1. `TipoFicha::TIPOS_CAMPO` incluye `catalogo`. `validarSchema()`: el bloque lleva `catalogo`; el grupo existe con al menos dos valores activos; con fichas asociadas no se puede cambiar el grupo de un campo existente (SD-4).
2. `TipoFichaResource`: bloque «Catálogo» en el Builder, con un selector de grupo (los grupos distintos de `catalogos_sistema` con al menos dos valores activos, mostrando el número de valores). Columna y campo `codigo` (SD-6), no editable si hay fichas. Opción `persona` en `uso`; para uso persona, el formulario oculta «obligatorio» (SD-9).
3. Migración: `tipo_fichas.codigo` (string, único, nullable).
4. `<x-ficha.campos>`: renderizado de `catalogo` con búsqueda según lo acordado en el paso 0. El valor enviado es la clave. Una clave que no pertenece al grupo o no está vigente se rechaza al guardar (en el servicio, no solo en la vista).
5. `ValorCampo::mostrar()` (SD-5) y sustitución en todas las vistas de fichas guardadas listadas en el paso 0. Las vistas antiguas no deben cambiar de aspecto para los tipos que ya existían.
6. Pre-relleno (SD-8): `prerellenarDesde()` deja vacía una clave de catálogo que ya no está vigente y devuelve la lista de campos afectados para avisar.

**Hecho el 27 nov 2026.** Migración `2026_11_26_200003` (`tipo_fichas.codigo`), que se aplica con el despliegue. Decisiones de implementación:
- La validación en el servidor de las claves es común: `App\Support\Fichas\ValidadorDatosFicha::validar()`, llamada desde `ValoracionService::registrar()` y `SolicitudPrestacionService::guardarDatos()` (la usará también `FichaPersonaService`). Error por campo («Elige un valor de la lista.»). `SolicitudPrestacionPage::ejecutar()` deja pasar la `ValidationException` para que se pinte en el campo (antes la habría convertido en un error genérico).
- Selector: componente Livewire `App\Livewire\Fichas\SelectorCatalogo` (`<livewire:fichas.selector-catalogo>`, `#[Modelable]` sobre `datos.{campo}`). Los catálogos de hasta 30 valores se listan enteros; los largos piden escribir y muestran hasta 20 resultados. Busca por etiqueta (sin acentos ni mayúsculas) o por clave exacta, en PHP sobre los vigentes del grupo. Sin clases nuevas: `list-group` y `op-lista-scroll`.
- `ValorCampo::mostrar(array $campo, mixed $valor, string $formatoFecha = 'j M Y')`: el formato de fecha es un parámetro opcional porque el PDF usa `d/m/Y`; **no incluye la unidad** (cada vista la pinta como antes). `ValorCampo::tieneValor()` decide qué cuenta como respuesta.
- La tarjeta de diagnóstico de `plan-page` pasa a mostrar los campos del schema con respuesta, con su etiqueta (antes, el `campo_id` crudo). El resto de vistas no cambian de aspecto para los tipos antiguos (TF-SDE-15).
- Pre-relleno: método nuevo `Ficha::prerellenarConAvisos()` (`datos` y `avisos`); `prerellenarDesde()` se conserva y devuelve solo los datos, ya sin las claves no vigentes.
- `TipoFichaResource`: columna y campo `codigo` (deshabilitado con fichas), uso `persona`, bloque «Catálogo» con los grupos que tienen al menos dos valores vigentes y su número. Para uso persona el toggle «obligatorio» se oculta y `convertirSchemaBlocks()` guarda `obligatorio = false` cuando no llega. El selector de catálogo no se bloquea en la interfaz con fichas: lo rechaza el modelo con un mensaje claro.
- `TipoFicha::validarSchema()` ya rechaza un campo obligatorio en uso persona (SD-9; TF-SDE-24 se prueba en el bloque C). El código se valida en el modelo (formato `^[a-z][a-z0-9_]*$`, único, inmutable con fichas).
- Pendiente para el bloque D: la cabecera de `ver-ficha-page` dice «Ficha de valoración» fija.

## Bloque C — Fichas de persona

1. Migración: `fichas.ciudadano_id` (FK a `ciudadanos`, nullable, índice), `fichas.no_contesta` (jsonb, nullable) y el `CHECK` de SD-7 (si el `CHECK` no puede leer `uso` del tipo, valídalo en el servicio y pruébalo).
2. `Ficha`: relación `ciudadano()`, `getCiudadanoId()` según SD-7, scope `dePersona(int $ciudadanoId, ?int $tipoFichaId = null)` y `vigentePara(int $ciudadanoId, int $tipoFichaId)` (última completada). `TipoFicha`: scope `dePersona()`.
3. Servicio `FichaPersonaService` (en el módulo de Intervención, junto a los servicios del bloque B del encargo): `registrar(Ciudadano, TipoFicha, User $autor, array $datos, array $noContesta, ?string $notas, bool $completar)` y `corregir(Ficha, …)`. Copia `schema_snapshot`, valida tipos y claves de catálogo, rechaza tipos que no son de uso persona y, a la inversa, impide que una ficha de uso persona entre por los flujos de valoración o solicitud.
4. Permisos (SD-10): migración de datos que crea `ciudadano.leer_fichas_persona` y `ciudadano.registrar_fichas_persona` y los asigna; `RolesSeeder` los añade. `FichaPolicy` (o la policy vigente de fichas): vía de lectura y escritura para fichas de persona según el permiso, el ámbito habitual y la regla de personas protegidas.
5. Auditoría: lectura y escritura registradas con el `ciudadano_id` correcto.

**Hecho el 27 nov 2026.** Migraciones `2026_11_27_100001` (cifrado), `100002` (`ciudadano_id`, `no_contesta`, CHECK) y `100003` (permisos), que se aplican con el despliegue. Decisiones:
- **Cifrado de todas las fichas (decisión del desarrollador del 27 nov):** `datos` (`encrypted:array`, la columna pasa de jsonb a texto) y `notas` (`encrypted`). La migración cifra las fichas existentes y esos dos valores en sus versiones. `Versionable` y `AuditObserver` guardaban el estado anterior **descifrado** (`getOriginal()`); ahora copian los atributos cifrados tal cual están en la BD (`App\Support\Cifrado\OriginalSinDescifrar`). Eso también afecta a la auditoría de `Ciudadano` (sus `datos_antes` de campos cifrados pasan a ir cifrados, como ya iban sus `datos_despues`). `no_contesta` (lista de ids de campo) no se cifra.
- **CHECK `fichas_persona_sin_historia`:** una ficha con `ciudadano_id` no tiene `historia_id`, `valoracion_id` ni `derivacion_id`. Lo que el CHECK no puede ver (el uso del tipo) lo garantiza una guarda del modelo (`Ficha::exigirCoherenciaDePersona()`, en `saving`): tipo persona ⇔ `ciudadano_id`. Así ninguna vía (valoración, solicitud, creación directa) mete una ficha de la persona en una Historia. `ValoracionService` y `SolicitudPrestacionService::crear()` además lo rechazan con un mensaje propio.
- **`FichaPersonaService`** (`registrar`, `corregir`, `registrarLectura`): exige RN-15 (`exigirActuacion`, R1), valida tipos (`ValidadorDatosFicha::validarTipos()`, nuevo) y claves de catálogo; los campos de `no_contesta` se guardan nulos aunque se envíe un valor; `corregir` usa el `schema_snapshot` de la ficha; un borrador se completa al corregirlo y una completada no vuelve a borrador.
- **Policy (`FichaPolicy`):** `verDePersona`, `crearDePersona`, `corregirDePersona` y la vía persona de `view`, siempre dentro de `CiudadanoPolicy::view`. Personas protegidas, **por permisos y no por rol**: leer exige `historia.leer` o `colectivo_protegido.aprobar_acceso`; registrar, `historia.leer`. Así la consulta básica no las ve (SD-10) y la supervisión sí las lee.
- TF-SDE-26 y 28 se prueban ya en la policy; la parte de interfaz (tarjeta y ruta) va en el bloque D. La vista de «No desea contestar» (TF-SDE-23) también va en D.

## Bloque D — Interfaz

1. `FichaCiudadanoPage`: tarjeta «Datos sociodemográficos» (en general, una por cada `TipoFicha` activo de uso persona), visible con `ciudadano.leer_fichas_persona`. Muestra la situación vigente (resumen de campos con `ValorCampo::mostrar()`, fecha y autor), o «Sin datos registrados»; los campos «No desea contestar» aparecen como tales.
2. Botones «Registrar» (si no hay ficha) o «Actualizar» (ficha nueva pre-rellenada, SD-8), con `ciudadano.registrar_fichas_persona`, y «Historial» (lista de fichas anteriores con su fecha; cada una se abre en lectura).
3. Página de registro (ruta bajo el ciudadano, en el módulo dueño de `Ficha`) con `<x-ficha.campos>`, la casilla «No desea contestar» por campo (al marcarla, el campo se vacía y se deshabilita), notas, «Guardar borrador» y «Completar». El aviso de valores no vigentes del pre-relleno aparece arriba.
4. Textos para el profesional (consulta básica incluida): breves, sin jerga técnica; recordar arriba que todos los datos son voluntarios.
5. Bootstrap y componentes `op-*` según el design system; `php artisan ui:auditar` en verde.

## Bloque E — Ficha inicial «Datos sociodemográficos»

Seeder idempotente `FichaSociodemograficaSeeder` (por `codigo`) más migración de datos. Si el tipo ya existe, **no** se sobrescribe su schema (puede haberse editado en el backoffice).

| Campo (`id`) | Tipo | Detalle |
|---|---|---|
| `pais_nacimiento` | catalogo | `comun.pais` |
| `nacionalidad` | catalogo | `comun.pais` |
| `segunda_nacionalidad` | catalogo | `comun.pais` |
| `anio_llegada_espana` | numero | Año (4 cifras); ayuda: «Solo si nació fuera de España» |
| `idioma_principal` | catalogo | `comun.idioma` |
| `nivel_estudios` | catalogo | `comun.nivel_estudios`; etiqueta «Estudios terminados de mayor nivel» |
| `situacion_laboral` | catalogo | `comun.situacion_laboral` |
| `ingresos_netos_mensuales` | numero | Unidad €; ayuda: «Ingresos propios netos de un mes normal» |
| `numero_pagas` | numero | Ayuda: «Pagas al año (12, 14…)» |
| `fecha_referencia_ingresos` | fecha | Ayuda: «Mes o fecha a la que corresponden los ingresos» |

Lógica condicional mínima, si el schema la admite ya: `anio_llegada_espana` solo cuando `pais_nacimiento` no es `ESP`. Si no la admite para campos de catálogo, no la añadas: anótalo en BACKLOG.

---

## Cierre

1. Ficheros de test tocados; suite completa en GitHub tras el último push (`gh workflow run nightly-tests.yml`: el bloque B toca el renderizado común de fichas).
2. `npm run build` y `php artisan ui:auditar` en verde.
3. Documentación: `modulo-intervencion.md` (§4: tipo `catalogo`, `uso = persona`, `ciudadano_id`, `no_contesta`, `codigo`), `modulo-ciudadania.md` (tarjeta y permisos), `modulo-usuarios-permisos.md` (permisos nuevos), `roles-consulta-basica-tramitacion.md`, `documentacion-proyecto.md` (catálogos oficiales y su carga), `decisiones-tecnicas.md` (sección 19.1: marcar lo hecho), `CLAUDE.md` §6.
4. CHANGELOG, BACKLOG, SESSION. Push.

---

# Tests — TF-SDE-01 a TF-SDE-34

**Ubicación:** `tests/Feature/Catalogos/` (bloque A) y `Modules/Intervencion/tests/Feature/FichaPersona/` (bloques B a E). Datos: `CircuitoTestSetup`, un ciudadano `$ana` sin Historia Social, otro `$luis` con Historia, una persona protegida `$protegida`, usuarios con intervención, supervisión y consulta básica. Los tests de catálogo usan CSV pequeños de prueba (fixtures), no los oficiales, salvo TF-SDE-07. **[negativo]**: debe fallar sin la protección.

## Grupo A — Catálogos
- **TF-SDE-01** Cargar un catálogo de prueba crea las filas con `clave`, `etiqueta`, `atributos`, `equivalencias` y `fuente`.
- **TF-SDE-02** Cargar dos veces el mismo fichero no duplica filas (recuento: 0 creados).
- **TF-SDE-03** Un cambio de etiqueta en el fichero actualiza la etiqueta sin cambiar `clave` ni `id`.
- **TF-SDE-04** [negativo] Un valor desactivado a mano no se reactiva al volver a cargar.
- **TF-SDE-05** [negativo] Un valor que desaparece del fichero no se borra: recibe `vigente_hasta` y sale de `opcionesParaSelect()`.
- **TF-SDE-06** [negativo] Fichero ausente o clave duplicada → excepción, sin datos a medias (transacción).
- **TF-SDE-07** Tras la migración de datos, los cuatro grupos existen con valores; `comun.pais` incluye `ESP` con `iso2 = ES` y las claves especiales `XXA` y `XXX`.
- **TF-SDE-08** `etiquetaDe()` devuelve la etiqueta de un valor inactivo o no vigente; `vigentes()` lo excluye.

## Grupo B — Tipo de campo «catálogo»
- **TF-SDE-09** Un schema con un campo `catalogo` sobre un grupo válido se guarda.
- **TF-SDE-10** [negativo] Grupo inexistente o con menos de dos valores activos → `ValidationException`.
- **TF-SDE-11** [negativo] Con fichas asociadas, cambiar el grupo de un campo → `ValidationException`; cambiar su etiqueta, permitido.
- **TF-SDE-12** Una ficha guarda la clave (`ESP`), no la etiqueta.
- **TF-SDE-13** [negativo] Una clave de otro grupo, inexistente o no vigente → rechazada por el servicio (también con la petición forjada).
- **TF-SDE-14** `ValorCampo::mostrar()` devuelve la etiqueta actual; para una clave ya no vigente, la etiqueta sin error.
- **TF-SDE-15** Las vistas de fichas de valoración existentes muestran igual los tipos antiguos (texto, número, select, booleano, fecha, escala).
- **TF-SDE-16** Pre-relleno: una clave no vigente queda vacía y aparece en la lista de avisos; las vigentes se copian.
- **TF-SDE-17** [negativo] `codigo` de `TipoFicha`: único; no editable con fichas asociadas.

## Grupo C — Fichas de persona
- **TF-SDE-18** Registrar la ficha sociodemográfica de `$ana` (sin Historia): `ciudadano_id`, `schema_snapshot`, `historia_id` nulo; sin apunte en ninguna Historia.
- **TF-SDE-19** [negativo] Una ficha de uso persona sin `ciudadano_id` → rechazada.
- **TF-SDE-20** [negativo] Un tipo de uso valoración no entra por `FichaPersonaService`; un tipo de uso persona no entra por la valoración ni por la solicitud.
- **TF-SDE-21** `vigentePara()` devuelve la última completada; los borradores no cuentan.
- **TF-SDE-22** «Actualizar» crea una ficha nueva y la anterior queda intacta (pasado inmutable); «corregir» genera versión `Versionable`.
- **TF-SDE-23** «No desea contestar»: el campo queda en `no_contesta`, su valor en `datos` es nulo y la vista lo muestra como tal.
- **TF-SDE-24** [negativo] Un tipo de uso persona con un campo obligatorio → rechazado en la validación del schema.
- **TF-SDE-25** Auditoría: registrar y leer quedan registrados con el `ciudadano_id` de `$ana`.

## Grupo D — Permisos e interfaz
- **TF-SDE-26** Intervención y consulta básica registran; supervisión solo lee.
- **TF-SDE-27** [negativo] Un usuario sin `ciudadano.leer_fichas_persona` no ve la tarjeta y la ruta de registro responde 403.
- **TF-SDE-28** [negativo] Consulta básica con `$protegida`: no ve la tarjeta ni puede registrar (también por petición forjada).
- **TF-SDE-29** La tarjeta muestra la situación vigente con etiquetas, fecha y autor; sin fichas, «Sin datos registrados».
- **TF-SDE-30** El historial lista las fichas anteriores y cada una se abre en lectura.
- **TF-SDE-31** El selector de un campo de catálogo encuentra un país al escribir parte de su nombre.

## Grupo E — Ficha inicial
- **TF-SDE-32** Tras la migración de datos existe el tipo `sociodemografica`, uso persona, con los diez campos del bloque E.
- **TF-SDE-33** [negativo] Volver a ejecutar el seeder no sobrescribe un schema editado en el backoffice.
- **TF-SDE-34** La ficha inicial no aparece en el selector de valoraciones de ningún centro (SD-11 y uso persona).

## Tabla de estado

| Grupo | Tests | Estado |
|---|---|---|
| A — Catálogos | TF-SDE-01..08 (+07 bis) | Hecho (26 nov; negativos 04, 05 y 06 comprobados) |
| B — Tipo de campo «catálogo» | TF-SDE-09..17 (+09 bis) y TF-SDE-31 | Hecho (27 nov; negativos 10, 11, 13, 16 y 17 comprobados) |
| C — Fichas de persona | TF-SDE-18..25 (+24 bis), 26 y 28 en la policy, cifrado | Hecho (27 nov; negativos 19, 23, 26, 28 y cifrado comprobados) |
| D — Permisos e interfaz | TF-SDE-26..31 | Pendiente |
| E — Ficha inicial | TF-SDE-32..34 | Pendiente |
