# Instrucciones CLI — Fase 5: informes desde la interfaz operativa

**Plan:** `docs/plan-cierre-funcional-2026-11.md`, fase 5.
**Diseño funcional:** `docs/modulo-documentos.md` §2.2 a §2.6, §3 (`ServicioGeneracionPDF`, `ServicioFirmaInforme`, `ResolverFuentesInforme`) y §4 «Livewire (operativo)».
**Requisito previo:** fase 3 (centro del plan y acceso del equipo del plan) terminada.
**Tests:** TF-INF-01 a TF-INF-28 (al final), más TF-DOC-22 a 25 de las variables auxiliares (paso 6).

**Regla de trabajo:** si el código contradice estas instrucciones o aparece un caso no previsto, **detente y pregunta**.

**Antes de empezar:** `CLAUDE.md`, `SESSION.md`, `docs/modulo-documentos.md`, `docs/instrucciones-cli/documentos-variables-auxiliares.md`, `docs/instrucciones-cli/documentos-merge-tags.md`, `docs/decisiones-tecnicas.md` (dependencias de la custodia), `docs/design-system/SKILL.md`, `docs/instrucciones-cli/2026-09-bootstrap-unico.md`.

---

## Hechos que no hay que redescubrir

- Existen los modelos `EstiloInforme`, `PlantillaInforme` (`tipo_informe`: `informe_social`, `informe_psicologico`, `informe_juridico`, `otro`; `secciones` con tipo `texto_libre`/`automatico` y `obligatorio`), `Informe` y `PisoFirmado`, y los recursos de Filament de estilos y plantillas.
- `ServicioGeneracionPDF::generarBorrador(Informe): string` y `generarFinal(Informe, string $pdfFirmadoBase64): Documento` (custodia con `CicloVidaDocumentoService`, tipo `informe_profesional`, canal `generado`).
- `ServicioFirmaInforme::firmar(Informe, string $pdfFirmadoBase64)` acepta directamente un PDF en base64 y marca siempre `metodo_firma = autofirma_certificado_empleado_publico`. Hay un TODO: falta la integración con AutoFirma, la validación de la firma y la extracción del número de colegiado. `anular()` existe (solo el autor, con motivo).
- **No hay interfaz operativa:** no existen `NuevoInformeWizard`, `InformesHistorialComponent` ni `PisoFirmadoUploadComponent`. Un profesional no puede redactar, firmar ni subir el PISO firmado.
- `pdfsig` (poppler-utils, el mismo paquete que `pdfinfo`) permite comprobar si un PDF tiene firmas y si son íntegras. Si está confinado por AppArmor como `qpdf` y `gs`, aplica la misma regla local (ver `decisiones-tecnicas.md`).

---

## Decisiones de esta fase

⚑ = decisión del analista, pendiente de confirmar por el Delegado Digital. Si no consta en `SESSION.md`, **pregunta antes del paso 3**.

| Id | Decisión |
|---|---|
| F5-1 ⚑ | **Dos formas de firmar** un informe: (a) **PDF firmado externamente**: el profesional descarga el PDF final sin firmar, lo firma con su certificado fuera de VIDA (AutoFirma de escritorio u otra herramienta) y lo sube; (b) **AutoFirma integrada** desde el navegador. La (a) se implementa en esta fase y es la que queda activa; la (b) queda preparada tras un adaptador y se activa por configuración cuando se haya probado en puestos reales con certificado |
| F5-2 ⚑ | **Validación mínima de la firma**: el PDF subido debe contener al menos una firma íntegra (`pdfsig`) y su contenido visible debe corresponder al informe (mismo número de páginas que el PDF final generado y el identificador del informe presente en el pie). Se guarda el titular del certificado que devuelve `pdfsig`. La validación criptográfica completa y la extracción del número de colegiado siguen pendientes |
| F5-3 | Quién redacta: el profesional con acceso de escritura a la historia (TSR, responsable de un plan y equipo del plan según la fase 3). Solo el autor firma y anula su informe |
| F5-4 | El PISO firmado se sube desde el plan, como documento escaneado con las dos firmas manuscritas; un plan tiene como máximo un `PisoFirmado` activo; subir uno nuevo sustituye al anterior conservándolo en la custodia |

---

## Fuera de alcance

- Validación criptográfica completa (cadena de confianza, CRL/OCSP) y extracción del número de colegiado.
- Firma del ciudadano en el navegador.
- `ConfiguracionTipografiaResource`.
- Plantillas por tipo de centro (pendiente de Tipo de Centro §13).

---

## Paso 0 — Verificación

1. `git pull`; suite de Documentos y de Intervención. Anota fallos previos.
2. Comprueba que `pdfsig` está instalado en local y en staging, y si AppArmor lo confina. Si no está, **detente** e indica el paquete.
3. Lista las plantillas existentes (seeders y staging) con su tipo y secciones, y prueba `generarBorrador()` con una de cada tipo sobre un ciudadano del mundo demo. Anota cualquier fallo.
4. Comprueba que el pie del PDF generado puede incluir un identificador del informe (necesario para F5-2).

---

## Paso 1 — Historial de informes

`InformesHistorialComponent` (Livewire) en la ficha de la persona (caja de herramientas o pestaña «Informes») y en el plan:
- informes de la persona que el usuario puede ver: título de la plantilla, tipo, autor, estado (borrador, firmado, anulado), fechas;
- acciones: «Continuar» (borrador, solo el autor), «Ver PDF» (firmado), «Anular» (firmado, solo el autor, motivo obligatorio), «Eliminar borrador» (solo el autor; los borradores sí se pueden borrar, no son documentos custodiados);
- `consulta_basica` y `tramitacion` no ven informes (son contenido de la Historia Social).

## Paso 2 — Asistente de nuevo informe

`NuevoInformeWizard`, en cuatro pasos:
1. **Plantilla**: activas, filtradas por tipo; con la descripción.
2. **Redacción**: secciones automáticas precargadas (`ResolverFuentesInforme`) y en lectura; secciones de texto libre con editor enriquecido y merge tags. Guardado automático del borrador.
3. **Vista previa**: `generarBorrador()` en un visor. No se puede avanzar con secciones obligatorias vacías.
4. **Firma** (paso 3).

El borrador se puede dejar y retomar desde el historial.

## Paso 3 — Firma

1. Enum `MetodoFirma`: añade `pdf_firmado_externamente` (además de `autofirma_certificado_empleado_publico`).
2. Interfaz `FirmadorInforme` con dos implementaciones:
   - `FirmaExternaSubida` (activa): en el paso 4, botón «Descargar PDF para firmar» (PDF final sin firma, con el identificador en el pie) y campo de subida del PDF firmado.
   - `FirmaAutoFirma` (preparada, inactiva por `config('documentos.firma.autofirma_activa')`, por defecto `false`): integración con el cliente JavaScript de AutoFirma desde Livewire, que devuelve el PDF firmado en base64. Implementa la parte de servidor y la llamada desde el navegador; **no la actives**. Documenta en `decisiones-tecnicas.md` cómo probarla.
   - Un `FirmadorSimulado` solo para tests, que firma con un certificado de prueba generado en el propio test.
3. `ServicioFirmaInforme::firmar()` recibe el método y el PDF; valida según F5-2 con un nuevo `ValidadorFirmaPdf` (usa `pdfsig`; rechaza si no hay firma, si no es íntegra o si el contenido no corresponde); guarda `metodo_firma` y `firmante_certificado` (nuevo campo, texto del titular). Un PDF que no pasa la validación no cambia el estado del informe y el motivo se muestra al usuario. La causa técnica va al log (como en `SaneadorPdf`).
4. El PDF firmado **no pasa por la normalización PDF/A** (la invalidaría): la custodia ya lo trata como «generado»; verifica que no se reescribe.
5. Tras firmar: el documento queda en la carpeta de la persona (tipo `informe_profesional`) y un apunte en la historia enlaza el informe.

## Paso 4 — PISO firmado

`PisoFirmadoUploadComponent` en `PlanPage` (planes `general_asp` activos):
- subida del escaneo (pasa por la ingesta normal: es un documento escaneado) con observaciones;
- solo el responsable del plan o la supervisión de su centro;
- si ya hay uno activo, el nuevo lo sustituye y el anterior queda en la custodia como versión anterior;
- el plan muestra «PISO firmado el [fecha], subido por [usuario]» con enlace al documento.

## Paso 5 — Permisos

`InformePolicy`: ver (quien puede leer la historia de la persona, incluido el equipo de un plan activo; personas protegidas según RN-17 ampliada), crear (escritura en la historia o responsable/equipo de un plan activo de la persona), editar y firmar (solo el autor, borrador), anular (solo el autor, firmado). `PisoFirmadoPolicy` según el paso 4. Todo validado en el servidor.

## Paso 6 — Variables auxiliares

Ejecuta `docs/instrucciones-cli/documentos-variables-auxiliares.md` tal como está (parámetros configurables, variables dinámicas de sistema, `ParametroInformeResource`), con sus tests TF-DOC-22 a 25. Si contradice algo de esta fase, detente y pregunta.

---

## Cierre

1. Suites de Documentos, Intervención y Ciudadanía, y la suite completa. Sin fallos nuevos. `npm run build` y `ui:auditar` en verde.
2. Documentación: `modulo-documentos.md` (§2.4 con `firmante_certificado` y el nuevo método, §3 con el validador y los firmadores, §4 con los componentes como implementados, §2.6 y §3 de variables), `decisiones-tecnicas.md` (`pdfsig` como dependencia; cómo probar AutoFirma).
3. CHANGELOG, BACKLOG (nueva entrada «Activar AutoFirma integrada» y «Validación criptográfica completa y número de colegiado»), SESSION, `CLAUDE.md` §6. Push y despliegue en verde.

## Criterios de finalización

1. TF-INF-01 a 28 y TF-DOC-22 a 25 en verde, con negativos comprobados.
2. Un profesional redacta, previsualiza, firma (subiendo el PDF firmado) y anula un informe desde la interfaz operativa, y sube el PISO firmado.
3. Documentación al día y despliegue en verde.

---

# Tests — TF-INF-01 a TF-INF-28

**Convenciones:** PHPUnit con `#[Test]`, PostgreSQL (`vida_testing`). Ubicación: `Modules/Documentos/tests/Feature/Informes/` (`HistorialInformesTest`, `NuevoInformeWizardTest`, `FirmaInformeTest`, `ValidadorFirmaPdfTest`, `PisoFirmadoTest`, `PermisosInformeTest`). Los PDF firmados de prueba se generan en el test con un certificado autofirmado (no se versionan certificados). **[negativo]** = debe fallar sin la protección.

**Datos:** `CircuitoTestSetup` (fase 3): `$tsNorte`, `$tsNorte2`, `$supNorte`, `$psicologaVallecas` (responsable de un PIA de `$ana`), `$ana` con PISO, `$marta` protegida, `$ventanillaNorte` (`consulta_basica`). Plantillas: `$plantillaSocial` (una sección automática con datos de la persona, una de texto libre obligatoria y otra opcional) y `$plantillaPsico`.

## Grupo A — Historial

**TF-INF-01 — Lista de informes**: con un borrador y un firmado de `$ana`, `$tsNorte` ve los dos con plantilla, tipo, autor, estado y fechas.

**TF-INF-02 — Acciones por estado y autor**: el autor ve «Continuar» en su borrador y «Anular» en su firmado; `$tsNorte2` (no autor) solo ve «Ver PDF» del firmado.

**TF-INF-03 — Eliminar un borrador**: el autor lo elimina; un firmado no se puede eliminar (acción ausente y rechazada en el servidor).

**TF-INF-04 — `consulta_basica` no ve informes** **[negativo]**: el componente no se muestra a `$ventanillaNorte` y la ruta del PDF le devuelve 403.

## Grupo B — Asistente

**TF-INF-05 — Plantillas activas**: solo se ofrecen plantillas activas, filtrables por tipo.

**TF-INF-06 — Secciones automáticas precargadas y en lectura**: la sección automática de `$plantillaSocial` muestra el nombre y los datos de `$ana`; intentar modificarla por una petición directa → ignorado o rechazado.

**TF-INF-07 — Guardado del borrador**: redactar la sección libre y salir → el borrador conserva el texto al retomarlo.

**TF-INF-08 — Obligatorias** **[negativo]**: con la sección obligatoria vacía no se puede pasar de la vista previa (acción rechazada en el servidor).

**TF-INF-09 — Merge tags**: un `{{nombre_ciudadano}}` en la sección libre aparece sustituido en la vista previa.

**TF-INF-10 — Vista previa**: `generarBorrador()` produce un PDF con el estilo de la UO y el identificador del informe en el pie.

## Grupo C — Firma

**TF-INF-11 — Firma con PDF subido**: el autor descarga el PDF para firmar, lo firma con el certificado de prueba y lo sube → informe `firmado`, `metodo_firma = pdf_firmado_externamente`, `firmante_certificado` con el titular, documento en la carpeta de `$ana` (tipo `informe_profesional`) y apunte en la historia.

**TF-INF-12 — PDF sin firma** **[negativo]**: subir el PDF sin firmar → rechazado con mensaje; el informe sigue en borrador.

**TF-INF-13 — Firma no íntegra** **[negativo]**: subir un PDF firmado y modificado después → rechazado.

**TF-INF-14 — PDF de otro informe** **[negativo]**: subir un PDF firmado de otro informe (otro identificador o distinto número de páginas) → rechazado.

**TF-INF-15 — El PDF firmado no se reescribe**: el documento custodiado conserva la firma íntegra (`pdfsig` sobre el fichero guardado).

**TF-INF-16 — Solo el autor firma** **[negativo]**: `$tsNorte2` intenta subir el PDF firmado de un borrador de `$tsNorte` → 403.

**TF-INF-17 — Solo borradores**: firmar un informe ya firmado o anulado → rechazado.

**TF-INF-18 — Causa técnica en el log**: con `pdfsig` fallando (binario simulado), el usuario ve un mensaje genérico y la causa queda en el log.

**TF-INF-19 — AutoFirma inactiva**: con `documentos.firma.autofirma_activa = false`, el paso de firma no ofrece AutoFirma y su endpoint rechaza la petición. Con `true` y el `FirmadorSimulado`, el flujo completa la firma con `metodo_firma = autofirma_certificado_empleado_publico`.

**TF-INF-20 — Anular**: el autor anula un firmado con motivo → `anulado`, con fecha y motivo; el documento sigue en la custodia. Sin motivo → rechazado. Un anulado no se reabre.

## Grupo D — PISO firmado

**TF-INF-21 — Subir el PISO firmado**: `$tsNorte` sube el escaneo en el PISO de `$ana` → `PisoFirmado` activo, documento ingestado y el plan lo muestra.

**TF-INF-22 — Sustitución**: subir otro → el nuevo es el activo; el anterior se conserva en la custodia.

**TF-INF-23 — Quién sube** **[negativo]**: `$tsNorte2` (no responsable) y `$psicologaVallecas` (equipo de otro plan) → 403; `$supNorte` → permitido.

**TF-INF-24 — Solo planes generales activos**: en un PIA o en un PISO cerrado, la acción no está y se rechaza en el servidor.

## Grupo E — Permisos

**TF-INF-25 — Equipo del plan redacta**: `$psicologaVallecas` crea y firma un informe psicológico de `$ana` (tiene un PIA activo).

**TF-INF-26 — Sin relación con la persona** **[negativo]**: un profesional de otro centro sin plan ni historia de `$ana` no puede crear informes de ella.

**TF-INF-27 — Persona protegida**: para `$marta`, solo pueden ver y redactar informes los adscritos al centro de su historia o de un plan activo suyo; `$coordIgualdad` no ve sus informes.

**TF-INF-28 — Ver PDF firmado**: quien puede leer la historia ve el PDF; el controlador sirve solo la versión vigente y audita el acceso.

## Tabla de estado

| Grupo | Tests | Estado |
|---|---|---|
| A — Historial | TF-INF-01..04 | |
| B — Asistente | TF-INF-05..10 | |
| C — Firma | TF-INF-11..20 | |
| D — PISO firmado | TF-INF-21..24 | |
| E — Permisos | TF-INF-25..28 | |
| Variables auxiliares | TF-DOC-22..25 | |
