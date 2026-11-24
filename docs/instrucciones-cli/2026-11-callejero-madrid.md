# Domicilio con el callejero oficial de Madrid

**Fecha:** 25 nov 2026 · **Estado:** pendiente · **Módulos:** Ciudadanía, Centro (asignación), infraestructura de geocodificación

Instrucciones para sustituir la «normalización» de direcciones, que hoy no existe, por un geocodificador local basado en el callejero oficial del Ayuntamiento de Madrid, con autocompletado del domicilio en los formularios.

---

## Hechos que no hay que redescubrir

- **No hay geocodificador real.** El único adaptador que existía era el simulador (`MockGeocodificador`): daba por buena cualquier dirección e inventaba coordenadas y sección censal. Desde el 25 nov 2026 solo se usa en los tests. Fuera de ellos, el adaptador es `ninguno` (`SinGeocodificador`), la dirección queda como texto libre sin normalizar y no se encolan reintentos (`GeocodificadorService::proveedorActivo()` / `hayGeocodificador()`). La migración `2026_11_25_100002` retiró los datos inventados de la BD compartida (7 personas).
- **La asignación de centro por domicilio depende de la sección censal** (`ResolucionCentroService`, `ciudadanos.seccion_censal_codigo`). Sin sección, la asignación queda pendiente de hacerse a mano. Esta tarea es lo que la hace funcionar de verdad.
- **Arquitectura ya decidida** (decisiones técnicas §9, `docs/geocodificacion.md`): `GeocodificadorInterface`, adaptadores intercambiables por `geocoder.proveedor`, `DireccionObserver` al guardar, `NormalizarDireccionJob` de reintento, trait `TieneDireccion` con el modelo canónico (`direccion_texto` siempre se conserva). No se cambia el contrato: se añade un adaptador.
- **El callejero oficial es un conjunto de datos abierto** (datos.madrid.es, conjunto 213605, «Callejero oficial del Ayuntamiento de Madrid», CC BY 4.0, actualización diaria). Incluye las direcciones vigentes con código de portal (`COD_NDP`), distrito, barrio y coordenadas UTM ETRS89 huso 30N; el fichero de direcciones postales incluye además la sección censal. Es el extracto público de la Base de Datos Ciudad, que `docs/geocodificacion.md` ya señala como referencia para producción.

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

1. Descargar los ficheros del conjunto 213605 y comprobar las cabeceras reales: dónde está la sección censal (fichero de direcciones con coordenadas o fichero de direcciones postales), formato de los códigos de distrito, barrio y sección (deben casar con `distritos`, `barrios` y `secciones_censales` del catálogo de Organización), codificación del CSV y separador.
2. Comprobar que la extensión `pg_trgm` y `unaccent` se pueden crear con el usuario de la aplicación (son extensiones «trusted» desde PostgreSQL 13; si no, pedirlo al desarrollador con `sudo`).
3. Informar al desarrollador del resultado antes de seguir si algo no coincide con estas instrucciones.

## Paso 1 — Modelo e importación

- Tabla `callejero_portales` (sin `SoftDeletes`: es una copia de un dato externo que se reemplaza): `cod_ndp` (único), `cod_via`, `clase_via`, `nombre_via`, `nombre_via_busqueda` (minúsculas, sin tildes, con índice trigram), `numero`, `calificador` (bis, letra…), `codigo_postal`, `distrito_codigo`, `barrio_codigo`, `seccion_censal_codigo`, `lat`, `lng` (WGS84, convertidas desde UTM ETRS89 30N al importar), `importado_en`.
- Comando `callejero:importar`: descarga, valida cabeceras (falla con mensaje claro si cambian), carga en una tabla temporal y sustituye en una transacción. Idempotente. Registra el número de portales y la fecha. Planificado semanalmente en el `schedule`.
- La URL del fichero va en `config/geocodificacion.php` (no es un secreto). Sin claves ni `.env` nuevos.

## Paso 2 — Adaptador `callejero`

- `CallejeroMadridGeocodificador implements GeocodificadorInterface`, registrado en `GeocodificadorService::ADAPTADORES` como `callejero`.
- Extraer a una clase propia (`ParserDireccion`) el análisis del texto que hoy está dentro de `MockGeocodificador` (tipo de vía, nombre, número, complementos, código postal) y usarla en los dos adaptadores.
- Búsqueda: vía por nombre normalizado (igualdad y, si no hay, similitud trigram con umbral configurable), después número y calificador. Un único portal → éxito con los datos del callejero (`proveedor = 'callejero'`, `codigo_ndp`, distrito, barrio, sección, coordenadas). Cero o varios → fallo con motivo («no encontrada» / «ambigua»).
- `geocoder.proveedor = callejero` en la configuración del sistema de staging cuando esté importado.

## Paso 3 — Autocompletado del domicilio

- Componente Livewire compartido (`ciudadania.domicilio-callejero`) para el alta (`AltaCiudadano`) y la edición del domicilio en la ficha: búsqueda de vía desde 3 caracteres (debounce), lista de vías y, elegida la vía, sus números. Piso, puerta, escalera y portal se escriben aparte.
- Al elegir un portal se guardan los campos estructurados y territoriales y `origen_direccion` con un valor nuevo `callejero` en el enum `OrigenDireccion` (el observer no debe volver a geocodificar ese origen). `direccion_texto` se compone a partir del portal elegido.
- Enlace «Escribir la dirección a mano» para el texto libre (decisión 2).
- Bootstrap y catálogo cerrado (`docs/instrucciones-cli/2026-09-bootstrap-unico.md`); `ui:auditar` en verde.

## Paso 4 — Direcciones existentes

- `php artisan direcciones:normalizar --pendientes` (ya existe) pasa las direcciones de texto libre por el adaptador `callejero`. Las ambiguas quedan como están.

## Paso 5 — Asignación de centro

- Al normalizarse una dirección ya se dispara `DireccionCiudadanoNormalizada`. Comprobar que la asignación por domicilio usa la sección del callejero y que una persona sin asignación vigente pasa a tenerla; una persona con asignación vigente distinta de la que daría el domicilio **no se reasigna**: se avisa a la bandeja de asignaciones del supervisor.

## Cierre

- `docs/geocodificacion.md` y decisiones técnicas §9 al día (adaptador `callejero`, simulador solo en tests, decisión de privacidad).
- CHANGELOG, BACKLOG y SESSION según `CLAUDE.md`.

## Criterios de finalización

- Ninguna petición a servicios externos con direcciones de personas.
- En staging, una dirección elegida en el autocompletado queda normalizada con la sección censal oficial, y la asignación por domicilio la usa.
- Tests TF-CAL-01 a 12 en verde, con los negativos comprobados.

---

## Tests

**TF-CAL-01 — Importación:** un CSV de prueba con 3 portales se importa; los campos y las coordenadas (UTM → WGS84, tolerancia de 1 m) son correctos.

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

**TF-CAL-12 — Asignación de centro:** una persona sin centro con domicilio normalizado en una sección asignada a un centro queda asignada a ese centro; una persona con asignación vigente distinta no se reasigna y genera el aviso al supervisor **[negativo]**.
