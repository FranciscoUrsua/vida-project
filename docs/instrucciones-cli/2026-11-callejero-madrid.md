# Domicilio con el callejero oficial de Madrid

**Fecha:** 25 nov 2026 · **Estado:** completada el 25 nov 2026 (pasos 0 a 5; fuente corregida al conjunto 200075) · **Módulos:** Ciudadanía, Centro (asignación), infraestructura de geocodificación

Instrucciones para sustituir la «normalización» de direcciones, que hoy no existe, por un geocodificador local basado en el callejero oficial del Ayuntamiento de Madrid, con autocompletado del domicilio en los formularios.

---

## Hechos que no hay que redescubrir

- **No hay geocodificador real.** El único adaptador que existía era el simulador (`MockGeocodificador`): daba por buena cualquier dirección e inventaba coordenadas y sección censal. Desde el 25 nov 2026 solo se usa en los tests. Fuera de ellos, el adaptador es `ninguno` (`SinGeocodificador`), la dirección queda como texto libre sin normalizar y no se encolan reintentos (`GeocodificadorService::proveedorActivo()` / `hayGeocodificador()`). La migración `2026_11_25_100002` retiró los datos inventados de la BD compartida (7 personas).
- **La asignación de centro por domicilio depende de la sección censal** (`ResolucionCentroService`, `ciudadanos.seccion_censal_codigo`). Sin sección, la asignación queda pendiente de hacerse a mano. Esta tarea es lo que la hace funcionar de verdad.
- **Arquitectura ya decidida** (decisiones técnicas §9, `docs/geocodificacion.md`): `GeocodificadorInterface`, adaptadores intercambiables por `geocoder.proveedor`, `DireccionObserver` al guardar, `NormalizarDireccionJob` de reintento, trait `TieneDireccion` con el modelo canónico (`direccion_texto` siempre se conserva). No se cambia el contrato: se añade un adaptador.
- **Fuente: conjunto 200075 de datos.madrid.es** («Callejero. Información adicional asociada», CC BY 4.0, actualización **mensual**), recurso `200075-1-callejero-csv` (`callejero_vigente_numeraciones_AAAAMM.csv`). Es el extracto público de la Base de Datos Ciudad y el único fichero abierto con la **sección censal** de cada número. **No se usa el conjunto 213605** («Callejero oficial»), que citaba la primera versión de estas instrucciones: no trae la sección censal y le faltan 1.336 números, unos 900 de ellos de la Cañada Real (sectores 2 a 6) (paso 0, decisión del desarrollador del 24 nov 2026).
- **Formato del fichero (comprobado el 24 nov 2026, versión 202611):** CSV con `;`, todos los campos entre comillas y rellenos de espacios, latin-1, CRLF; unas 215.600 filas. Columnas usadas: «Codigo de numero» (= `COD_NDP`, único), «Codigo de via», «Clase de la via», «Partícula de la vía», «Nombre de la vía» (mayúsculas sin tildes), «Literal de numeracion» (`NUM000002`, `NUM000076 B`, `KM.001000EN`), «Codigo de distrito», «Codigo de barrio», «Seccion censal» (3 cifras dentro del distrito), «Codigo postal», «Direccion completa para el numero» (con tildes: de ahí sale el nombre para mostrar), «Longitud/Latitud en S R ETRS89 WGS84» (grados, minutos y segundos, `3º36'5.49'' W`) y «Tipologia del numero» (Portal, Frente fachada, Parcela, Garaje, Jardín o parque público). El nombre del fichero cambia cada mes: la URL se resuelve con la API CKAN del portal (`package_show`), no se fija en la configuración.
- **Los códigos casan con el catálogo de Organización** (comprobado): distrito `DD`; barrio `DD` + número de barrio (`011`); sección `28079` + `DD` + sección (`2807901001`). Los 131 barrios y las 2.462 secciones del catálogo están en el fichero, y el barrio de cada número coincide siempre con el de su sección en el catálogo. La sección `000` (3 números: una parcela de la Cañada Real y la cárcel provisional de Carabanchel) se trata como «sin sección».
- **Coordenadas:** se toman las WGS84 del fichero (conversión de grados, minutos y segundos a decimal); no hace falta convertir desde UTM. Diferencia con la conversión desde UTM ETRS89: menos de 0,3 m.
- **Se importan todos los tipos de número** (decisión del desarrollador del 24 nov 2026): en la Cañada Real hay viviendas registradas como «Parcela». Se guarda el tipo de cada uno.
- **Homónimos reales:** 348 nombres de vía existen con varias clases (p. ej. calle Mayor y plaza Mayor, ambas con número 15).

## Decisiones de esta tarea

1. **Geocodificador local, sin terceros.** Ninguna dirección de una persona sale de VIDA: entre las personas atendidas hay víctimas de violencia de género y colectivos protegidos. Se descartan Google (datos a un tercero con transferencia internacional; sus condiciones solo permiten guardar coordenadas 30 días) y Nominatim público (su política desaconseja el uso continuo desde aplicaciones; 1 petición/s).
2. **El domicilio se elige, no se adivina.** En los formularios de alta y edición de la persona, el domicilio es un autocompletado de vía y número sobre el callejero. Al elegir un portal se rellenan directamente los campos estructurados y territoriales (sin pasar por el geocodificador de texto). El texto libre se mantiene como alternativa para personas sin hogar, domicilios fuera de Madrid o direcciones que no estén en el callejero; en esos casos el adaptador intenta casar el texto y, si no lo consigue sin ambigüedad, la dirección queda sin normalizar.
3. **Una dirección ambigua no se normaliza.** Si el texto casa con varios portales (o con una vía sin número), no se elige uno: queda sin normalizar y la asignación de centro, pendiente. Mejor un caso pendiente que una asignación equivocada.
4. **Fuera del municipio de Madrid, sin normalizar** por ahora. Si hace falta más adelante, CartoCiudad (IGN) como segundo adaptador, con decisión aparte (sigue siendo un tercero).

## Fuera de alcance

- CartoCiudad u otro proveedor externo.
- Geocodificación inversa y mapas.
- Recalcular asignaciones de centro ya hechas: solo se avisa (ver paso 5).

---

## Paso 0 — Verificación (antes de escribir código)

**Hecho el 24 nov 2026.** Resultado en «Hechos que no hay que redescubrir»: la sección censal no está en el conjunto 213605 sino en el 200075, que pasa a ser la única fuente. `pg_trgm` y `unaccent` son «trusted» en PostgreSQL 18 y se crean con `vida_user` (BD `vida`) y con `vida_test` (BD `vida_testing`); en la suite nocturna el usuario es superusuario.

## Paso 1 — Modelo e importación

**Hecho el 24 nov 2026.** Migración `2026_11_25_100003`, modelos `App\Models\Callejero\CallejeroPortal` y `CallejeroImportacion`, enum `TipoNumeroCallejero`, `App\Services\Geocodificacion\Callejero\{FuenteCallejero, ImportadorCallejero, TextoBusqueda}` y comando `callejero:importar` (domingos a las 04:30). Diferencias con lo previsto: la sustitución se hace en una sola transacción (borrar e insertar por lotes) en vez de con una tabla temporal, con la misma garantía (si falla, la copia anterior queda intacta, y las lecturas siguen viéndola mientras dura), y se aborta si más del 1 % de las filas son ilegibles. La Ñ también es calificador (8 números). Prueba con el fichero real: 215.605 números, ninguno descartado, unos 2,5 minutos.

- Tabla `callejero_portales` (sin `SoftDeletes`: es una copia de un dato externo que se reemplaza): `cod_ndp` (único), `cod_via`, `clase_via`, `particula_via`, `nombre_via` (como viene, sin tildes), `nombre_via_mostrar` (con tildes, de «Direccion completa»), `nombre_via_busqueda` (minúsculas, sin tildes, con índice trigram), `clase_numero` (`numero` / `km`), `numero`, `calificador` (letra, `EN`/`SA` en kilómetros…), `tipo_numero` (portal, frente de fachada, parcela, garaje, jardín), `codigo_postal`, `distrito_codigo`, `barrio_codigo`, `seccion_censal_codigo` (null con la sección `000`), `lat`, `lng` (WGS84 del fichero), `importado_en`.
- Comando `callejero:importar`: resuelve la URL del recurso con la API CKAN, descarga, valida cabeceras (falla con mensaje claro si cambian), carga en una tabla temporal y sustituye en una transacción. Idempotente. Opción `--fichero=` para importar un CSV local (tests y emergencias). Registra el número de números importados, la fecha y la versión del fichero. Planificado semanalmente en el `schedule` (el fichero es mensual: así se recoge en pocos días).
- La URL de la API, el conjunto y el recurso van en `config/geocodificacion.php` (no son secretos). Sin claves ni `.env` nuevos.

## Paso 2 — Adaptador `callejero`

**Hecho el 24 nov 2026**, salvo activarlo en staging. `ParserDireccion` (con `DireccionAnalizada`) extraído del simulador, que ahora lo usa; `CallejeroMadridGeocodificador` registrado como `callejero`. Decisiones de implementación:
- **Erratas por distancia de edición, no por trigramas.** En el callejero real «Mayro» está más cerca de «Mayo» (0,375) que de «Mayor» (0,333) por trigramas, y las dos calles existen. Los trigramas solo preseleccionan (`umbral_similitud`, 0,3); decide la distancia de edición con transposiciones (1 errata en nombres de 5 a 9 letras, 2 a partir de 10, ninguna por debajo). La vía tiene que tener ese número, y si dos nombres empatan, la dirección es ambigua (TF-CAL-06 bis).
- **Solo se buscan erratas si no existe una vía con ese nombre exacto:** «Calle Mayor 99» no acaba en «Calle Mayo 99» (TF-CAL-08 bis).
- **Fuera de Madrid:** localidad escrita distinta de Madrid, o código postal que no aparece en el callejero.
- **Reintentos:** un «no existe», «ambigua» o «fuera de Madrid» es definitivo; `ResultadoGeocodificacion::$reintentable` distingue los errores transitorios y `NormalizarDireccionJob` ya no deja un trabajo fallido por cada dirección que no casa (TF-CAL-08 ter).
- El nombre para mostrar lleva la partícula y mayúscula inicial («Paseo de la Castellana»); el parser reconoce también «2º B», «bajo», «ático», «15B» (calificador) y la localidad.
- **Datos reales:** «Mayor 15» sin clase es ambigua (calle Mayor 15 y plaza Mayor 15 existen), y «Avenida de la Albufera 120» no existe (hay 119 y 121). Los tests usan su propio callejero.
- **Activación:** migración `2026_11_25_100004`, que pone `geocoder.proveedor = callejero` solo si el callejero está importado y no hay otro proveedor configurado (en staging se activa con el despliegue; en un entorno nuevo, tras la primera importación, a mano). Se hizo después de cerrar el paso 5.

- `CallejeroMadridGeocodificador implements GeocodificadorInterface`, registrado en `GeocodificadorService::ADAPTADORES` como `callejero`.
- Extraer a una clase propia (`ParserDireccion`) el análisis del texto que hoy está dentro de `MockGeocodificador` (tipo de vía, nombre, número, complementos, código postal) y usarla en los dos adaptadores.
- Búsqueda: vía por nombre normalizado (igualdad y, si no hay, similitud trigram con umbral configurable), después número y calificador. Un único portal → éxito con los datos del callejero (`proveedor = 'callejero'`, `codigo_ndp`, distrito, barrio, sección, coordenadas). Cero o varios → fallo con motivo («no encontrada» / «ambigua»).
- `geocoder.proveedor = callejero` en la configuración del sistema de staging cuando esté importado.

## Paso 3 — Autocompletado del domicilio

**Hecho el 25 nov 2026.** Componente `ciudadania.domicilio-callejero` (`Modules\Ciudadania\Http\Livewire\DomicilioCallejero`, `#[Modelable]`) en el alta y en la edición de la ficha; servicio `App\Services\Geocodificacion\Callejero\SeleccionDomicilio` (búsqueda de vías y números, estado del campo y paso a atributos); `OrigenDireccion::Callejero`; `ResultadoGeocodificacion::atributosDireccion()`; `CallejeroMadridGeocodificador::resultadoDePortal()` compartido con el adaptador. Decisiones de implementación:
- Vías por fragmento del nombre (índice trigram), primero las que empiezan por él, con los distritos que recorren para distinguir homónimas. Números en orden numérico; dentro de cada número, primero portal y parcela; se indica el tipo si no es portal.
- En la ficha, el domicilio solo se reescribe si ha cambiado: guardar otros datos no convierte en texto libre un domicilio elegido cuyo número ya no está en el callejero.
- «Escribir la dirección a mano» parte de lo elegido. El padrón rellena el texto libre. PSH sigue sin domicilio. Sin callejero cargado, solo texto libre.
- Sin clases nuevas: Bootstrap y `op-lista-scroll`; `ui:auditar` sin infracciones.

- Componente Livewire compartido (`ciudadania.domicilio-callejero`) para el alta (`AltaCiudadano`) y la edición del domicilio en la ficha: búsqueda de vía desde 3 caracteres (debounce), lista de vías y, elegida la vía, sus números. Piso, puerta, escalera y portal se escriben aparte.
- Al elegir un portal se guardan los campos estructurados y territoriales y `origen_direccion` con un valor nuevo `callejero` en el enum `OrigenDireccion` (el observer no debe volver a geocodificar ese origen). `direccion_texto` se compone a partir del portal elegido.
- Enlace «Escribir la dirección a mano» para el texto libre (decisión 2).
- Bootstrap y catálogo cerrado (`docs/instrucciones-cli/2026-09-bootstrap-unico.md`); `ui:auditar` en verde.

## Paso 4 — Direcciones existentes

- `php artisan vida:normalizar-direcciones --pendientes` (ya existe) pasa las direcciones de texto libre por el adaptador `callejero`. Las ambiguas quedan como están.

**Hecho el 25 nov 2026.** El comando tenía tres defectos que se corrigieron antes de ejecutarlo: no guardaba los códigos territoriales (habría dejado direcciones «normalizadas» sin sección), no disparaba `DireccionCiudadanoNormalizada` ni fijaba el origen (no habría asignado centro) y procesaba también las del padrón (§3.3). Ahora usa `ResultadoGeocodificacion::atributosDireccion()`, pone origen `geocodificacion`, avisa a la asignación, solo toma origen `profesional` o sin origen (cargas antiguas), no espera entre lotes con el callejero local y resume los motivos de fallo. TF-CAL-13 a 15.

Ejecutado en la BD compartida tras una simulación de solo lectura: 171 pendientes, **73 normalizadas** con sección, 96 sin normalizar (58 sin número, 38 no encontradas en el callejero). Ninguna asignación ni propuesta: en staging los dos centros con adscripción por domicilio (CSS Arganzuela y CSS Entrevías) no tienen ámbitos territoriales, y sin ellos la asignación por domicilio no resuelve a nadie (BACKLOG).

## Paso 5 — Asignación de centro

**Hecho el 24 nov 2026.** No hizo falta código: `AsignarCentroPorDireccion` ya asignaba a quien no tiene centro y, con asignación geográfica vigente, `evaluarCambioDomicilio()` crea una propuesta de traslado en la bandeja del supervisor (RN-10) sin reasignar. Una asignación por elección o manual no genera propuesta (quien eligió centro lo conserva; `modulo-asignacion.md` §3.5). TF-CAL-12 (con bis y ter) de punta a punta con el adaptador del callejero, en `Modules/Centro/tests/Feature/Asignacion/AsignacionPorCallejeroTest.php`; negativo comprobado.

- Al normalizarse una dirección ya se dispara `DireccionCiudadanoNormalizada`. Comprobar que la asignación por domicilio usa la sección del callejero y que una persona sin asignación vigente pasa a tenerla; una persona con asignación vigente distinta de la que daría el domicilio **no se reasigna**: se avisa a la bandeja de asignaciones del supervisor.

## Cierre

- `docs/geocodificacion.md` y decisiones técnicas §9 al día (adaptador `callejero`, simulador solo en tests, decisión de privacidad).
- CHANGELOG, BACKLOG y SESSION según `CLAUDE.md`.

## Criterios de finalización

- Ninguna petición a servicios externos con direcciones de personas.
- En staging, una dirección elegida en el autocompletado queda normalizada con la sección censal oficial (comprobado a mano por el desarrollador el 25 nov 2026), y la asignación por domicilio la usa (comprobado en TF-CAL-12; en staging falta configurar los ámbitos territoriales de los centros).
- Tests TF-CAL-01 a 12 en verde, con los negativos comprobados.

---

## Tests

**TF-CAL-01 — Importación:** un CSV de prueba con el formato real (latin-1, `;`, comillas y relleno de espacios) y 3 números se importa; los campos (códigos de barrio y sección compuestos, nombre con tildes, número y calificador, tipo) y las coordenadas (grados, minutos y segundos → decimal, tolerancia de 1 m) son correctos; un número con sección `000` queda sin sección.

**TF-CAL-02 — Importación idempotente:** importar dos veces el mismo fichero deja los mismos portales; un portal que desaparece del fichero desaparece de la tabla.

**TF-CAL-03 — Cabeceras cambiadas** **[negativo]**: un CSV con otras columnas hace fallar la importación sin tocar la tabla existente.

**TF-CAL-04 — Dirección exacta:** «Calle Mayor 15» casa con su portal: `direccion_normalizada`, `codigo_ndp`, distrito, barrio y sección del callejero, `geocoder_proveedor = callejero`.

**TF-CAL-05 — Variantes de escritura:** «c/ mayor, 15», «CALLE MAYOR 15 2º B» y «Mayor 15» casan con el mismo portal; el piso y la puerta se conservan.

**TF-CAL-06 — Errata leve:** «Calle Mayro 15» casa por similitud con el umbral por defecto.

**TF-CAL-07 — Ambigua** **[negativo]**: un nombre de vía que existe con dos clases (calle y plaza) sin clase en el texto queda sin normalizar.

**TF-CAL-08 — Sin número o número inexistente** **[negativo]**: queda sin normalizar, sin sección.

**TF-CAL-09 — Fuera de Madrid** **[negativo]**: «Calle Mayor 15, Alcalá de Henares» queda sin normalizar.

**TF-CAL-10 — Autocompletado:** el profesional busca «albuf», elige «Avenida de la Albufera» y el número 120; se guarda con `origen_direccion = callejero` y los datos del portal, sin llamar al geocodificador de texto.

**TF-CAL-11 — Texto libre como alternativa:** «Sin domicilio fijo» se guarda como texto libre, sin normalizar y sin error.

**TF-CAL-13 — Direcciones pendientes (paso 4):** una dirección de texto libre guardada sin geocodificador queda normalizada con `vida:normalizar-direcciones --pendientes`, con los códigos del callejero, origen `geocodificacion`, el texto original y la asignación de centro por su sección.

**TF-CAL-14 — Pendiente que no casa** **[negativo]**: queda como estaba, sin sección ni asignación.

**TF-CAL-15 — Origen** **[negativo]**: una dirección sin origen (carga antigua) se normaliza; una del padrón no se toca.

**TF-CAL-12 — Asignación de centro:** una persona sin centro con domicilio normalizado en una sección asignada a un centro queda asignada a ese centro; una persona con asignación vigente distinta no se reasigna y genera el aviso al supervisor **[negativo]**.
