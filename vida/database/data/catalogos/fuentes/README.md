# Fuentes oficiales de los catálogos comunes

Ficheros originales, **tal como se descargaron**, de los que salen los catálogos
`comun.pais`, `comun.nivel_estudios`, `comun.situacion_laboral` y `comun.idioma`
de `catalogos_sistema`. No se editan a mano. La URL, la fecha de descarga y la
versión de cada fichero están en `fuentes.json`, que lee `php artisan catalogos:normalizar`
para rellenar la columna `fuente`.

Flujo (ver `docs/instrucciones-cli/2026-11-ficha-sociodemografica.md`, bloque A):

1. Se descarga la versión nueva del fichero aquí, con el mismo nombre, y se actualiza `fuentes.json`.
2. `php artisan catalogos:normalizar` regenera los CSV de `database/data/catalogos/`.
3. Se versionan juntos el original y los CSV. Al desplegar, una migración de datos
   (o `CatalogosOficialesSeeder`) llama a `CargaCatalogosOficiales::todos()`, que
   actualiza por clave sin duplicar, sin reactivar lo desactivado a mano y sin borrar.

El comando de normalización **no** se ejecuta al desplegar.

| Fichero | Organismo | Catálogo | Licencia o condiciones de reutilización |
|---|---|---|---|
| `m49_overview.html` | ONU, División de Estadística | `comun.pais` (clave ISO alfa-3, nombre en español, región y subregión M49) | Condiciones de uso de datos de unstats.un.org (reutilización citando la fuente) |
| `eurostat_glossary_country_codes.html` | Eurostat | `comun.pais` → `atributos.ue` (27 Estados miembros) | Reutilización autorizada citando la fuente (Decisión 2011/833/UE) |
| `CNED14_A_estr.xls` | INE | `comun.nivel_estudios` | Reutilización citando la fuente (aviso legal del INE; Ley 37/2007) |
| `CORR_CNED14_CINE11_A.xls` | INE | `comun.nivel_estudios` → `equivalencias.cine2011` | Ídem |
| `dr_EPA_2026.xlsx` | INE | `comun.situacion_laboral` (variables SIDAC y SIDI, situación autopercibida) | Ídem |
| `iso-639-3.tab` | SIL International (autoridad de registro de ISO 639-3) | `comun.idioma` (códigos ISO 639-1, 639-2 y 639-3) | Condiciones de uso de las tablas de códigos de SIL (uso libre de las tablas) |
| `cldr_es_languages.json` | Unicode Consortium (CLDR 49.0.0) | `comun.idioma` (nombres en español) | Unicode License v3 |

Notas:

- Los ficheros `.xls` del INE son en realidad libros `.xlsx` (OOXML): se conservan con su nombre original.
- Eurostat usa `EL` para Grecia; en ISO 3166 es `GR`. La normalización lo traduce.
- Pendiente (BACKLOG): la tabla de Países/Nacionalidades del INE (IDA-Padrón) para `equivalencias.ine`,
  y el Doc 9303 parte 3 de la OACI para las claves especiales (`XXA`, `XXB`, `XXC`, `XXX`, `XKX`).
