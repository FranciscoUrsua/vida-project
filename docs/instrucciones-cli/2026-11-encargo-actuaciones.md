# Instrucciones CLI — Actuaciones del profesional de encargo

**Origen:** petición del desarrollador del 25 nov 2026. El profesional de encargo (`consulta_profesional`) necesita trabajar el encargo con documentos, fichas de valoración, escalas, anotaciones e informes. Es en la práctica un rol de intervención, pero con dos límites: el **acceso** (solo ve lo suyo dentro de sus encargos) y la **caja de herramientas** (la del área del encargo: jurídica, psicológica, formativa…). Todo lo que registra, salvo las anotaciones privadas, queda en la Historia Social, donde el TSR sigue sus avances.
**Requisito previo:** encargo profesional (`docs/instrucciones-cli/2026-11-encargo-profesional.md`, bloques A a F, cerrado el 25 nov 2026) y fase 5 (informes).
**Decisiones del desarrollador (25 nov 2026):** el área del encargo decide las herramientas; lo registrado lo ve quien tenga acceso a la Historia Social, como cualquier apunte `profesionales`.
**Tests:** TF-EAC-01 a TF-EAC-36 (al final de este documento).
**Estado:** revisadas y aprobadas por el desarrollador el 25 nov 2026. Bloques A (25 nov), B, C y D (26 nov) hechos.

**Alcance:**
- **A.** Área del encargo: entidad, caja de herramientas y elección al encargar.
- **B.** Vínculo de las actuaciones al encargo, servicios extraídos y ámbito de lectura.
- **C.** Anotaciones desde el encargo y cierre de sus citas.
- **D.** Fichas de valoración y escalas desde el encargo.
- **E.** Informes desde el encargo.
- **F.** Seguimiento del TSR y cierre del encargo con actuaciones.

**Regla de trabajo:** si el código contradice estas instrucciones o aparece un caso no previsto, **detente y pregunta**. Lo marcado como *fuera de alcance* no se toca.

**Antes de empezar:** `CLAUDE.md`, `SESSION.md`, `docs/rol-consulta-profesional.md`, `docs/instrucciones-cli/2026-11-encargo-profesional.md`, `docs/modulo-intervencion.md` (§4, §5.7.1, §7), `docs/modulo-escala.md`, `docs/modulo-citas.md` (§3.5, §5), `docs/instrucciones-cli/2026-11-fase5-informes.md`, `docs/modulo-tipo-centro.md` (§6.2, catálogo de fichas), `docs/design-system/SKILL.md` y `docs/instrucciones-cli/2026-09-bootstrap-unico.md`.

---

## Hechos que no hay que redescubrir

- **Toda actuación sobre la Historia genera un apunte** (`plan_apuntes`): la valoración (`TipoApunte::Valoracion`, `apuntable` = `Ficha`), la escala (`Escala`, `apuntable` = `PaseEscala`), la anotación (`Anotacion`, con `visibilidad`) y el informe firmado (`Documento`, desde `ServicioFirmaInforme`). El timeline del TSR ya los muestra.
- **La lógica de guardado vive en `CiudadanoPage`:** `guardarEscala()`, `guardarAnotacion()` y el guardado de la valoración. `RegistrarEscalaPage` instancia un `CiudadanoPage` para reutilizarla. `RegistrarValoracionPage` y `RegistrarEscalaPage` autorizan con `AccesoExpediente::historia()`, que el profesional de encargo no pasa (no tiene `historia.leer`).
- **`InformePolicy::crear()` exige `update` sobre la historia o el equipo del plan.** `NuevoInformeWizard` recibe la historia.
- **`ResolverFuentesInforme`** resuelve las fuentes `ciudadano.datos_basicos`, `ciudadano.datos_contacto`, `historia_social.resumen`, `historia_social.prestaciones_activas`, `escalas.barthel_ultimo`, `escalas.pfeiffer_ultimo`, `escalas.lawton_ultimo` y `profesional.datos`. Las de `historia_social.*` y `escalas.*` leen datos de toda la Historia, incluidos los de otros profesionales.
- **Cierre de citas:** el primer apunte vinculado a una cita la completa (`AtencionCitaService::completarPorApunte`); `validarVinculo()` rechaza citas canceladas o con incomparecencia. El profesional de encargo no crea apuntes hoy, así que sus citas se quedan «pendientes de cierre» (BACKLOG, decisión pendiente del bloque E del encargo).
- **Catálogo de fichas por tipo de centro:** `ConfiguracionEfectivaCentro::tiposFicha()` filtra el selector de `RegistrarValoracionPage`. No hay catálogo de escalas ni de plantillas de informe por centro; `plantillas_informe` tiene `unidad_organizativa_id`.
- **`fichas`, `pases_escala`, `informes` y `plan_apuntes`** no tienen ningún vínculo con `derivaciones`.
- **Un encargo tiene un solo profesional** (`profesional_responsable_id`); reasignar está fuera de alcance (se anula y se encarga de nuevo).
- **`AccesoDocumentosEncargo`** ya da al profesional sus documentos y los vinculados a sus encargos. Un informe firmado es un documento: hay que vincularlo también al encargo.

---

## Decisiones

| Id | Decisión |
|---|---|
| EA-1 | **Área del encargo.** Entidad `AreaEncargo` (backoffice Filament): código, nombre, descripción, activa; soft deletes y `Versionable`. Es una entidad y no un valor de `catalogos_sistema` porque el código decide con ella: filtra las herramientas |
| EA-2 | **Caja de herramientas del área:** tipos de ficha, tipos de escala y plantillas de informe que ofrece el área (tablas pivote `area_encargo_tipo_ficha`, `area_encargo_tipo_escala`, `area_encargo_plantilla_informe`). Las anotaciones, los documentos y las citas no dependen del área: los tiene siempre |
| EA-3 | **El TSR elige el área al encargar** (obligatoria en los encargos nuevos). La bandeja del destino la muestra, para que la dirección asigne a quien corresponda. Los encargos anteriores quedan con área vacía: solo anotaciones, documentos y citas |
| EA-4 | **Actuaciones desde `EncargoPage`:** anotación, ficha de valoración, escala e informe, además de lo que ya existe (documentos, citas, mensajes, resultado). Solo con el encargo **aceptado**, solo el profesional asignado, con `derivacion.registrar_resultado` (el permiso que ya da «trabajar el encargo»; no se crean permisos nuevos) y con RN-15 en el centro del encargo, que es el centro de la operación (D1.6) |
| EA-5 | **Vínculo al encargo:** columna `derivacion_id` (nullable, FK) en `plan_apuntes`, `fichas`, `pases_escala` e `informes`. Cada actuación crea el mismo apunte que crearía desde la Historia, con `derivacion_id` y `plan_id = null` (el encargo no pertenece al plan aunque nazca de él) |
| EA-6 | **Visibilidad para la Historia:** los apuntes del encargo son `profesionales` y los ve quien tiene acceso a la Historia Social (decisión del 25 nov), con la marca «Encargo · {área} · {centro}» en la tarjeta del timeline. **La anotación privada solo la ve su autor** (restricción de dominio: sin excepciones) |
| EA-7 | **Ámbito de lectura del profesional:** solo las actuaciones con `derivacion_id` de un encargo suyo y de las que es autor. Nunca otros apuntes, fichas, pases o informes de la Historia. Lo resuelve un servicio, `ActuacionesEncargo`, que usan las policies como vía adicional etiquetada (como `AccesoDocumentosEncargo`) |
| EA-8 | **Sin fugas por la puerta de atrás:** el pre-relleno de una ficha («basada en la anterior»), la serie temporal de una escala y cualquier lista de antecedentes se limitan a sus actuaciones en encargos suyos de esa persona |
| EA-9 | **Informes del encargo:** plantillas del área; fuentes automáticas permitidas `ciudadano.datos_basicos`, `ciudadano.datos_contacto`, `profesional.datos` y la nueva `encargo.datos` (área, centro, motivo, TSR, fechas). Las fuentes `historia_social.*` y `escalas.*` están prohibidas: se valida al asociar la plantilla al área (Filament) y se vuelve a comprobar al resolver (`ResolverFuentesInforme` con contexto de encargo). Firma como en la fase 5 (PDF firmado fuera de VIDA y validado con `pdfsig`). El documento firmado va a la carpeta de la persona, vinculado al encargo |
| EA-10 | **Citas del encargo:** toda actuación (anotación, ficha, escala) puede vincularse a una cita del profesional con la persona, con la casilla «Vincular a la cita de las HH:MM», marcada por defecto como en la Historia; el apunte la completa (`completarPorApunte`). «Atender» desde la agenda abre `EncargoPage` con `?cita=`. **Resuelve la decisión pendiente del BACKLOG** |
| EA-11 | **Encargo cerrado:** el profesional conserva la lectura de sus actuaciones (salvo personas protegidas, que quedan enmascaradas: EP-7), pero no crea ni edita nada. **No se puede cerrar con borradores** de ficha, escala o informe abiertos: hay que completarlos o descartarlos (el descarte de un borrador de informe es la eliminación de borradores de la fase 5) |
| EA-12 | **Seguimiento del TSR:** en `EncargosProfesionales` (Historia Social y plan principal), cada encargo muestra sus actuaciones (tipo, fecha, autor, resumen) con enlace al apunte del timeline. No hay alerta por actuación (ruido); el TSR recibe la del cierre, como hoy |

---

## Fuera de alcance

- Filtrar los centros de destino por área (que un centro declare qué áreas atiende): BACKLOG si hace falta.
- Entrevistas, planes, seguimientos y prestaciones para el profesional de encargo.
- Catálogo de escalas y plantillas por tipo de centro.
- Reasignar un encargo aceptado.

---

## Paso 0 — Verificación

1. `git pull`, revisión de la suite nocturna y los ficheros de test del encargo (`Modules/Intervencion/tests/Feature/Encargo/`).
2. Confirma que el único punto de escritura de valoraciones, escalas y anotaciones es `CiudadanoPage` (más los envoltorios `RegistrarValoracionPage` y `RegistrarEscalaPage`) y lista dónde se usa el pre-relleno de fichas.
3. Cuenta los encargos existentes (quedarán con área vacía) y las plantillas de informe con fuentes `historia_social.*` o `escalas.*`.

### Resultado del paso 0 (25 nov 2026)

- No hay ningún encargo en la base de datos compartida: ninguno queda sin área.
- Las dos plantillas de informe existentes («Certificado vulnerabilidad» e «Informe psicológico») usan fuentes `escalas.*` o `historia_social.*`: no se pueden asociar a un área.
- **Corrige los «Hechos»:** la valoración se guarda en `RegistrarValoracionPage::guardar()` (no en `CiudadanoPage`), la escala en `CiudadanoPage::guardarEscala()` y la anotación en `CiudadanoPage::guardarAnotacion()` **y en `PlanPage`**. El pre-relleno de fichas es `Ficha::datosPrerrellenados()` (modelo). El bloque B extrae estos cuatro puntos de escritura.

## Bloque A — Área del encargo

1. Migración: `areas_encargo` y las tres tablas pivote de EA-2; `derivaciones.area_encargo_id` (nullable, FK). `CHECK`: en `modalidad = encargo` creados a partir de esta migración, el área es obligatoria (validación en `EncargoService`; el `CHECK` no puede mirar la fecha, así que se valida en el servicio y se prueba).
2. Modelo `AreaEncargo` con relaciones (`tiposFicha()`, `tiposEscala()`, `plantillasInforme()`), scope `activas()`, `Versionable`, soft deletes.
3. `AreaEncargoResource` en `app/Filament/Resources/` con la caja de herramientas; al asociar una plantilla, rechaza las que usan fuentes prohibidas (EA-9).
4. `EncargoService::encargar()` recibe `AreaEncargo $area` (activa). El modal «Encargo profesional» añade el selector de área. `DerivacionesPage` y `MisEncargosPage` muestran el área.
5. Seeder de demo con tres áreas (jurídica, psicológica, formativa) y alguna herramienta en cada una.

**Hecho (25 nov 2026).** Migración `2026_11_25_300001` (siembra las áreas al desplegar, con `AreasEncargoSeeder`, idempotente y también en `DatabaseSeeder`). `AreaEncargo` (código inmutable, sin baja con encargos), pivote `AreaEncargoPlantillaInforme`, que rechaza las plantillas que leen la Historia en cualquier camino. `Modules\Documentos\Support\InformeEncargo`: fuentes por lista blanca y merge tags de expediente, valoración y plan por lista negra (lo reutiliza el bloque E). **Añadido a las instrucciones:** `CajaHerramientasEncargo`, el único sitio que decide qué ficha, escala o plantilla ofrece un encargo (para pintar y para rechazar lo forjado en los bloques D y E). No hay `CHECK` del área (EA-3 se aplica en el servicio, como estaba previsto).

## Bloque B — Vínculo, servicios y ámbito

1. Migración: `derivacion_id` en `plan_apuntes`, `fichas`, `pases_escala` e `informes`.
2. **Extraer la lógica de guardado** de `CiudadanoPage` a servicios del módulo (`ValoracionService`, `PaseEscalaService`, `AnotacionService`, o métodos en `ApunteService` si encaja), que reciben historia, autor, centro de la operación, cita opcional y encargo opcional. `CiudadanoPage`, `RegistrarValoracionPage` y `RegistrarEscalaPage` pasan a usarlos; su comportamiento no cambia (sus tests siguen en verde).
3. `ActuacionesEncargo` (EA-7): `puedeActuar(User, Derivacion)` (EA-4) y `visibles(User, Derivacion)` / `esVisible(User, Model)` (EA-7).
4. Policies de `Ficha`, `PaseEscala`, `Informe` y `Apunte`: vía adicional etiquetada «encargo» que delega en `ActuacionesEncargo`. El resto de las vías no cambia.

**Hecho (26 nov 2026).** Migración `2026_11_26_100001` (`derivacion_id` en las cuatro tablas; se aplica con el despliegue). Servicios en `Modules\Intervencion\Services\Actuaciones`: `AnotacionService`, `ValoracionService` y `PaseEscalaService`, que reciben un `ContextoActuacion` (historia, autor, plan, cita y encargo). `ContextoActuacion::desdeEncargo()` exige EA-4, fija `plan_id = null` y solo admite una cita vinculable del propio profesional (adelanta la validación de EA-10); `ValoracionService` y `PaseEscalaService` rechazan la herramienta que no es del área (adelanta TF-EAC-18 y 21 en el servidor). Los cuatro puntos de escritura (`CiudadanoPage::guardarAnotacion()` y `guardarEscala()`, `RegistrarValoracionPage`, `RegistrarEscalaPage` y la nota de cierre de `PlanPage`) pasan a los servicios; el plan de los apuntes, a `PlanDeIntervencion::enCursoParaApuntes()`. `Apunte`, al crearse, toma para RN-15 el centro del encargo si no hay cita (D1.6). **Corrige los «Hechos»:** no existían policies de `Ficha` ni de `PaseEscala`; se crean (`FichaPolicy`, `PaseEscalaPolicy`: vía historia y vía encargo). En `ApuntePolicy` no se añade vía: `view` ya deja ver todo apunte no privado y quien filtra es el scope `ambito_uo`, que al profesional de encargo solo le deja los suyos (BACKLOG).

## Bloque C — Anotaciones y citas

1. Herramienta «Anotación» en `EncargoPage`: texto y visibilidad (`privada` o `profesionales`, por defecto `profesionales`), casilla de cita (EA-10).
2. Lista de actuaciones del encargo en `EncargoPage` (las suyas, EA-7), con las privadas marcadas como tales.
3. «Atender» desde la agenda: `EncargoPage?cita=` propone la cita en las herramientas.
4. Retirar del BACKLOG la decisión pendiente de las citas del encargo.

**Hecho (26 nov 2026).** En `EncargoPage`, con el encargo en curso (`ActuacionesEncargo::puedeActuar()`): tarjeta «Anotación» (texto, visibilidad, casilla de cita con el partial `vincular-cita` de la Historia) y «Tus actuaciones en el encargo» (`ActuacionesEncargo::visibles()`, privadas con la marca «Privada»), que sigue visible con el encargo cerrado. La cita propuesta la da `AtencionCitaService::citaVinculable()` con `?cita=` (`#[Url]`); al guardar se vuelve a validar en `ContextoActuacion::desdeEncargo()`. `EnlaceAtencionCita` añade `?cita=` al enlace del encargo (cambia la URL esperada en TF-ENC-37). Marca del timeline del TSR: `Apunte::marcaEncargo()` («Encargo · {área} · {centro}»), con el área y el centro del encargo cargados en `CiudadanoPage::apuntesHS()`. Decisión del BACKLOG retirada.

## Bloque D — Fichas y escalas

1. Rutas bajo el encargo (`encargos/{derivacion}/valoracion`, `encargos/{derivacion}/escala`) que reutilizan las vistas de valoración y escala con un modo «encargo»: autorizan con `ActuacionesEncargo::puedeActuar()`, no con `AccesoExpediente::historia()`, y el selector ofrece solo las herramientas del área (EA-2).
2. Pre-relleno y antecedentes según EA-8.
3. Ver una ficha o un pase propio desde la lista del encargo (también con el encargo cerrado, EA-11).

**Hecho (26 nov 2026).** Rutas `intervencion.encargos.valoracion`, `.escala` y `.ficha` (grupo `derivacion.ver_asignadas`) con `ValoracionEncargoPage`, `EscalaEncargoPage` y `VerFichaEncargoPage`, que reutilizan las vistas de la Historia. Lo común está en traits: `ConEncargoAutorizado` (encargo y persona autorizados y `puedeActuar`, también en `EncargoPage`), `FormularioValoracion` (también en `RegistrarValoracionPage`) y `MuestraFicha` (también en `VerFichaPage`). Cada página da su vuelta (`volverUrl()`/`volverTexto()`); la escala del encargo tiene selector con las escalas del área (`escalasDisponibles()`); la ficha del encargo no ofrece mensaje con contexto `ficha`. En `EncargoPage`, botones «Ficha de valoración» y «Escala» (solo si el área tiene alguna) que llevan la cita propuesta (`abrirHerramienta()`), y «Ver ficha» en la lista. Una ficha de otro encargo responde 404 por la ruta del encargo. **Corrige EA-8 (decisión del desarrollador, 26 nov):** no hay pre-relleno «basada en la anterior» ni serie temporal en ninguna pantalla, así que no se crean; TF-EAC-19 y 22 comprueban que las páginas del encargo no muestran fichas ni pases del TSR. Si algún día se crean, desde un encargo tienen que limitarse a lo del profesional (BACKLOG). El pase propio se ve en la lista con su puntuación (contenido del apunte), sin página propia.

## Bloque E — Informes

1. `NuevoInformeWizard` en modo encargo: recibe la derivación; plantillas del área; `InformePolicy::crear` con la vía de encargo.
2. Fuente `encargo.datos` y contexto de encargo en `ResolverFuentesInforme` (rechaza las fuentes prohibidas, EA-9).
3. Firma: el documento firmado se vincula a la persona y al encargo (`documento_vinculos`); el apunte `Documento` lleva `derivacion_id`.
4. Historial de informes del encargo en `EncargoPage`.

## Bloque F — Seguimiento y cierre

1. `EncargosProfesionales`: actuaciones de cada encargo (EA-12).
2. Timeline: marca «Encargo · {área} · {centro}» en los apuntes con `derivacion_id`.
3. `EncargoService::cerrar()` rechaza el cierre con borradores abiertos (EA-11); `EncargoPage` explica qué falta.
4. Encargo cerrado: herramientas ocultas, lectura de lo suyo.

---

## Cierre

1. Ficheros de test tocados; suite completa en GitHub tras el último push (`gh workflow run nightly-tests.yml`: el bloque B toca código transversal).
2. `npm run build` y `php artisan ui:auditar` en verde.
3. Documentación: `rol-consulta-profesional.md` (§4, §6, actuaciones), `modulo-usuarios-permisos.md` (rol 6), `modulo-intervencion.md` (§5.7.1 y §7: `derivacion_id`), `modulo-escala.md`, `modulo-documentos.md` (informes del encargo, fuentes), `modulo-citas.md` (cierre por apunte desde el encargo), `CLAUDE.md` §6.
4. CHANGELOG, BACKLOG, SESSION. Push.

---

# Tests — TF-EAC-01 a TF-EAC-36

**Ubicación:** `Modules/Intervencion/tests/Feature/Encargo/Actuaciones/`. Datos: `CircuitoTestSetup` más los del encargo (`$abogadaVallecas`, `$educadorMulti`), un área «Jurídica» con una ficha y una plantilla, y un área «Psicológica» con una escala. **[negativo]**: debe fallar sin la protección.

## Grupo A — Área del encargo
- **TF-EAC-01** Encargar con área: el encargo guarda `area_encargo_id`; la bandeja y «Mis derivaciones» la muestran.
- **TF-EAC-02** [negativo] Encargar sin área o con un área inactiva → rechazado.
- **TF-EAC-03** Encargo anterior sin área: ofrece anotaciones, documentos y citas, pero ni fichas, ni escalas, ni informes.
- **TF-EAC-04** [negativo] Filament: asociar a un área una plantilla con la fuente `historia_social.resumen` o `escalas.barthel_ultimo` → rechazado.
- **TF-EAC-05** Caja de herramientas: con el área «Jurídica», el selector ofrece su ficha y su plantilla y no la escala de «Psicológica».

## Grupo B — Vínculo y ámbito
- **TF-EAC-06** Los servicios extraídos guardan desde la Historia igual que antes (valoración, escala y anotación con su apunte); `CiudadanoPageTest` y `RegistrarValoracionPageTest` en verde.
- **TF-EAC-07** [negativo] Quién actúa: otro profesional, la abogada con el encargo pendiente, rechazado o cerrado, o sin adscripción al centro del encargo (RN-15) → rechazado.
- **TF-EAC-08** [negativo] Ámbito: la abogada no ve las fichas, los pases, los informes ni los apuntes de la Historia de `$ana` hechos por el TSR.
- **TF-EAC-09** [negativo] Ámbito entre encargos: no ve las actuaciones de `$educadorMulti` en otro encargo de `$ana`.
- **TF-EAC-10** Las actuaciones llevan `derivacion_id` y `plan_id = null`, aunque el encargo naciera del plan principal.

## Grupo C — Anotaciones y citas
- **TF-EAC-11** Anotación `profesionales` desde el encargo: aparece en el timeline del TSR con la marca del encargo.
- **TF-EAC-12** [negativo] Anotación privada: solo la ve la abogada; ni el TSR ni la supervisión la ven.
- **TF-EAC-13** Una anotación vinculada a la cita del día la completa.
- **TF-EAC-14** Una cita pendiente de cierre se completa con una anotación posterior.
- **TF-EAC-15** [negativo] No se vincula a una cita cancelada, con incomparecencia o de otro profesional.
- **TF-EAC-16** «Atender» desde la agenda abre el encargo con la cita propuesta en las herramientas.

## Grupo D — Fichas y escalas
- **TF-EAC-17** Ficha de valoración desde el encargo: se guarda con `schema_snapshot`, `derivacion_id` y apunte `Valoracion`; el TSR la ve en el timeline.
- **TF-EAC-18** [negativo] Una ficha que no es del área → rechazada (también con la petición forjada).
- **TF-EAC-19** [negativo] Pre-relleno: «basada en la anterior» solo propone fichas suyas del encargo; no la del TSR del mismo tipo.
- **TF-EAC-20** Pase de escala desde el encargo: score persistido, `derivacion_id` y apunte `Escala`.
- **TF-EAC-21** [negativo] Una escala que no es del área → rechazada.
- **TF-EAC-22** [negativo] Serie temporal: la abogada solo ve sus pases, no los del TSR con la misma escala.
- **TF-EAC-23** [negativo] La ruta de valoración de la Historia (`RegistrarValoracionPage`) sigue respondiendo 403 a la abogada.
- **TF-EAC-24** Ficha y pase propios en lectura con el encargo cerrado.

## Grupo E — Informes
- **TF-EAC-25** Informe desde el encargo con una plantilla del área: se crea en borrador con `derivacion_id`.
- **TF-EAC-26** La fuente `encargo.datos` resuelve área, centro, motivo, TSR y fechas.
- **TF-EAC-27** [negativo] Una plantilla con fuente prohibida en contexto de encargo → la sección no se resuelve (excepción); una plantilla que no es del área → rechazada.
- **TF-EAC-28** Firmar: el documento queda en la carpeta de `$ana`, vinculado al encargo; el TSR lo ve; apunte `Documento` con `derivacion_id`.
- **TF-EAC-29** [negativo] La abogada no ve otros informes de `$ana` ni puede editar uno ajeno.
- **TF-EAC-30** [negativo] Persona protegida con el encargo cerrado: los informes del histórico aparecen enmascarados y sin enlace.

## Grupo F — Seguimiento y cierre
- **TF-EAC-31** `EncargosProfesionales`: el TSR ve las actuaciones del encargo con enlace al timeline.
- **TF-EAC-32** [negativo] La lista de actuaciones del TSR no incluye las anotaciones privadas de la abogada.
- **TF-EAC-33** [negativo] Cerrar con un borrador de informe o de ficha abierto → rechazado con el motivo; tras completarlo o descartarlo, se cierra.
- **TF-EAC-34** Encargo cerrado: `EncargoPage` sin herramientas; las acciones forjadas → rechazadas.
- **TF-EAC-35** No se envía ninguna alerta al TSR por actuación; sí al cerrar.
- **TF-EAC-36** Una actuación cuenta en el centro del encargo aunque el centro activo de la abogada sea otro (D1.6).

## Tabla de estado

| Grupo | Tests | Estado |
|---|---|---|
| A — Área del encargo | TF-EAC-01..05 | Hecho (25 nov), con 05 bis |
| B — Vínculo y ámbito | TF-EAC-06..10 | Hecho (26 nov) |
| C — Anotaciones y citas | TF-EAC-11..16 | Hecho (26 nov) |
| D — Fichas y escalas | TF-EAC-17..24 | Hecho (26 nov) |
| E — Informes | TF-EAC-25..30 | Pendiente |
| F — Seguimiento y cierre | TF-EAC-31..36 | Pendiente |
