# Tests funcionales — Asignación de centro y profesional de referencia

**TF-ASG-01 a TF-ASG-34.**
Diseño funcional: `docs/modulo-asignacion.md`.
Instrucciones de implementación: `docs/instrucciones-cli/2026-09-asignacion-implementacion.md`.

> Especificaciones de comportamiento, no código. Claude CLI implementa cada test siguiendo los patrones del proyecto y rellena la tabla de estado del final.

---

## Convenciones

- **Framework:** PHPUnit con atributo `#[Test]`. No usar Pest.
- **Base de datos:** PostgreSQL (`vida_testing`). No usar SQLite.
- **Ubicación:** territorio y centro en `Modules/Centro/tests/Feature/Asignacion/` (`UnidadesTerritorialesTest`, `GeocodificacionCodigosTest`, `AmbitoCoberturaTest`, `AsignacionCentroTest`); referencia y reparto en `Modules/Intervencion/tests/Feature/Asignacion/` (`PoolReferenciaTest`, `SorteoReferenciaTest`, `AsignacionReferenciaTest`, `RepartoCasosTest`, `ActividadCasosTest`, `BandejaAsignacionesTest`).
- **Patrón:** Dado / Cuando / Entonces.
- **Negativo obligatorio:** cada test de restricción debe fallar si se elimina la protección. Compruébalo al menos una vez por grupo y anótalo en el CHANGELOG. Los marcados **[negativo]** son los críticos.
- **Aleatoriedad:** `Random\Randomizer` con semilla fija (`Mt19937`) inyectado en los servicios. Los tests estadísticos usan varias semillas y umbrales explícitos, nunca "parece aleatorio".
- **Fechas:** `Carbon::setTestNow()` fijo.
- **Datos territoriales:** mini-catálogo de test (no los CSV reales): distritos `01` y `02`; barrios `011`, `012`, `021`; secciones `2807901001`, `2807901002` (barrio 011), `2807901003` (barrio 012), `2807902001` (barrio 021).

## Actores y datos reutilizados

Trait `AsignacionTestSetup`:

- `$supervisor` — `supervision` del centro `$cssNorte`.
- `$cssNorte` — tipo `css`, adscripción por domicilio, `modo_asignacion_referencia = sorteo`, ámbito: barrio `011`.
- `$cssSur` — tipo `css`, adscripción por domicilio, `sorteo`, ámbito: barrio `012` y distrito `02`.
- `$ciam` — tipo `ciam`, `inscripcion_libre = true`.
- `$ts1`, `$ts2`, `$ts3` — cargo trabajador/a social (`puede_ser_referencia = true`), perfil horario activo en `$cssNorte` de 35, 35 y 17,5 horas.
- `$educador` — cargo educador/a (`puede_ser_referencia = false`), perfil activo en `$cssNorte`.
- `$ana` — ciudadana con dirección normalizada en sección `2807901001`.
- Unidad de convivencia `$ucGarcia` con `$pedro` y `$lucia`.

---

## Grupo A — Unidades territoriales y geocodificación

**TF-ASG-01 — Código INE de sección**
- **Dado** distrito `21` y sección `28` tal como los devuelve la BDC.
- **Cuando** se construye el código.
- **Entonces** es `2807921028`. El mismo número de sección en otro distrito da otro código.

**TF-ASG-02 — Barrio único por distrito**
- **Dado** el barrio `4` del distrito `21`.
- **Cuando** se intenta crear otro barrio `4` en el distrito `21`, y otro barrio `4` en el distrito `02`.
- **Entonces** lo primero falla; lo segundo se permite.

**TF-ASG-03 — La dirección normalizada guarda los códigos**
- **Dado** un ciudadano con dirección en texto.
- **Cuando** se normaliza con el mock.
- **Entonces** tiene NDP, distrito, barrio y sección, coherentes entre sí y existentes en el catálogo. La misma dirección normalizada dos veces da la misma sección.

**TF-ASG-04 — Fallo de geocodificación**
- **Dado** un resultado de fallo.
- **Cuando** se persiste.
- **Entonces** los cuatro códigos quedan nulos y la dirección sigue sin normalizar.

---

## Grupo B — Ámbitos y cobertura

**TF-ASG-05 — Solapamiento en el mismo tipo** **[negativo]**
- **Dado** `$cssNorte` con el barrio `011`.
- **Cuando** se intenta añadir el barrio `011` a `$cssSur`.
- **Entonces** se rechaza con un mensaje que nombra a `$cssNorte`.

**TF-ASG-06 — Sin solapamiento entre tipos distintos ni con libre elección**
- **Dado** `$cssNorte` con el barrio `011`.
- **Cuando** se añade el barrio `011` a `$ciam`.
- **Entonces** se permite.

**TF-ASG-07 — Distinto nivel no es solapamiento**
- **Dado** `$cssSur` con el distrito `02`.
- **Cuando** se añade la sección `2807902001` a otro centro `css`.
- **Entonces** se permite (gana la coincidencia más específica).

**TF-ASG-08 — Comprobación de cobertura**
- **Dado** el setup.
- **Cuando** se ejecuta `centros:comprobar-cobertura css`.
- **Entonces** no lista ninguna sección. Tras quitar el barrio `012` a `$cssSur`, lista la `2807901003`.

---

## Grupo C — Asignación de centro

**TF-ASG-09 — Asignación geográfica inequívoca**
- **Dado** `$ana` sin centro.
- **Cuando** se normaliza su dirección.
- **Entonces** tiene asignado `$cssNorte`, `modo = geografico`, con la sección de resolución guardada. Solo uno vigente del tipo `css`.

**TF-ASG-10 — Gana la unidad más específica**
- **Dado** un centro `css` con la sección `2807902001` y `$cssSur` con el distrito `02`.
- **Cuando** se asigna a una persona de esa sección.
- **Entonces** va al centro de la sección, no a `$cssSur`.

**TF-ASG-11 — Sin cobertura, a la bandeja**
- **Dado** una persona en una sección sin centro `css`.
- **Cuando** se normaliza su dirección.
- **Entonces** no tiene centro y aparece en la bandeja con motivo `sin_cobertura`.

**TF-ASG-12 — Sin códigos, a la bandeja**
- **Dado** una persona cuya dirección no se pudo geocodificar.
- **Cuando** se intenta asignar.
- **Entonces** no tiene centro y aparece en la bandeja con motivo `sin_codigos`.

**TF-ASG-13 — Libre elección de centro**
- **Dado** `$ana` con `$cssNorte` asignado.
- **Cuando** elige `$ciam`.
- **Entonces** tiene dos asignaciones vigentes (`css` y `ciam`); la de `$cssNorte` no cambia. Intentar asignar por elección un centro `css` se rechaza.

**TF-ASG-14 — Cambio de domicilio no traslada** **[negativo]**
- **Dado** `$ana` con `$cssNorte`.
- **Cuando** cambia su dirección a la sección `2807901003` (de `$cssSur`) y se normaliza.
- **Entonces** sigue asignada a `$cssNorte` y hay una propuesta de cambio en la bandeja de `$supervisor`. Al confirmarla, se cierra la asignación a `$cssNorte` y se crea una a `$cssSur`, `modo = manual`, con motivo.

**TF-ASG-15 — Asignación manual exige motivo**
- **Dado** una persona en la bandeja.
- **Cuando** `$supervisor` la asigna sin motivo, y después con motivo.
- **Entonces** lo primero se rechaza; lo segundo crea la asignación `manual` con `asignado_por_id`.

---

## Grupo D — Profesionales del reparto

**TF-ASG-16 — Solo cargos elegibles con perfil activo**
- **Dado** el setup.
- **Cuando** se calculan los elegibles de `$cssNorte`.
- **Entonces** son `$ts1`, `$ts2` y `$ts3` con pesos 35, 35 y 17,5. `$educador` no está.

**TF-ASG-17 — Ausencia larga excluye; ausencia corta no**
- **Dado** `$ts1` con baja de 30 días desde hoy y `$ts2` con 3 días de vacaciones desde hoy.
- **Cuando** se calculan los elegibles hoy.
- **Entonces** `$ts1` no está; `$ts2` sí.

---

## Grupo E — Sorteo

**TF-ASG-18 — Reparto proporcional a la jornada**
- **Dado** el setup y 250 asignaciones por sorteo sucesivas (con semilla fija).
- **Cuando** se cuentan.
- **Entonces** `$ts1` y `$ts2` tienen 100 ± 1 cada uno y `$ts3` 50 ± 1. Se repite con tres semillas distintas.

**TF-ASG-19 — Corrección del desvío**
- **Dado** que en la ventana `$ts1` ha recibido 10 entradas, `$ts2` 10 y `$ts3` 0.
- **Cuando** se sortea.
- **Entonces** el único candidato es `$ts3`.

**TF-ASG-20 — No es un turno predecible**
- **Dado** el setup.
- **Cuando** se hacen 30 sorteos con dos semillas distintas.
- **Entonces** las dos secuencias de profesionales difieren, y en ninguna se repite un ciclo fijo de 3.

**TF-ASG-21 — Los casos acumulados no influyen** **[negativo]**
- **Dado** `$ts1` con 200 historias vigentes asignadas antes de la ventana (o con `cuenta_en_reparto = false`) y `$ts2`, `$ts3` con ninguna.
- **Cuando** se hacen 50 sorteos.
- **Entonces** el reparto es el mismo que sin esas historias (proporcional a la jornada). El test falla si el sorteo cuenta asignaciones vigentes en vez de entradas.

**TF-ASG-22 — Profesional que se incorpora**
- **Dado** 100 entradas en la ventana antes de que `$ts4` (35 h) se incorpore hoy.
- **Cuando** se sortea.
- **Entonces** `$ts4` no tiene un déficit de 100 × su parte: sus esperadas solo cuentan desde su incorporación, y no acapara las siguientes entradas.

**TF-ASG-23 — Sorteo auditable**
- **Dado** un sorteo.
- **Cuando** se crea la asignación.
- **Entonces** su campo `sorteo` contiene cada candidato con peso, esperado y recibido, y el profesional elegido.

**TF-ASG-24 — Sin elegibles**
- **Dado** un centro sin profesionales elegibles.
- **Cuando** se abre una historia.
- **Entonces** la historia queda sin referencia y aparece en la bandeja.

---

## Grupo F — Asignación inicial de referencia

**TF-ASG-25 — Sorteo al abrir historia**
- **Dado** `$ana` en `$cssNorte` (modo `sorteo`), historia abierta por `$ts1`.
- **Cuando** se abre.
- **Entonces** la referencia la decide el sorteo (con la semilla del test, `$ts2`), `origen = sorteo`, `cuenta_en_reparto = true`, `centro_id = $cssNorte`. `$ts1` no es referencia por haberla abierto.

**TF-ASG-26 — Centro "quien abre" mantiene el comportamiento actual**
- **Dado** un centro con modo `quien_abre`.
- **Cuando** `$ts1` abre la historia.
- **Entonces** `$ts1` es la referencia, `origen = quien_abre`, sin sorteo.

**TF-ASG-27 — Libre elección**
- **Dado** `$cssNorte` en modo `libre_eleccion`.
- **Cuando** se abre una historia eligiendo a `$ts3`; y otra sin elegir.
- **Entonces** la primera tiene `$ts3`, `origen = eleccion`, cuenta en el reparto; la segunda se resuelve por sorteo. Elegir a `$educador` se rechaza.

**TF-ASG-28 — La elección cuenta en el reparto**
- **Dado** 10 elecciones de `$ts1` en la ventana.
- **Cuando** se sortea.
- **Entonces** `$ts1` no es candidato hasta que los demás alcancen su parte.

**TF-ASG-29 — Unidad de convivencia**
- **Dado** `$pedro` con `$ts2` como referencia en `$cssNorte`.
- **Cuando** se abre la historia de `$lucia`.
- **Entonces** su referencia es `$ts2`, `origen = unidad_convivencia`, `cuenta_en_reparto = false`, sin sorteo.

**TF-ASG-30 — Cambio manual por el supervisor** **[negativo]**
- **Dado** `$ana` con `$ts2`.
- **Cuando** `$ts2` intenta cambiar la referencia; después `$supervisor` la cambia a `$ts3` con motivo.
- **Entonces** lo primero se rechaza; lo segundo cierra la asignación de `$ts2` (con `fecha_fin`, sin modificar su profesional) y crea una `manual` para `$ts3` que no cuenta en el reparto.

---

## Grupo G — Reparto por salida

**TF-ASG-31 — Propuesta proporcional por grupos, sin cambios hasta confirmar**
- **Dado** `$ts1` con 20 historias con actividad y 40 dormidas, entre ellas `$ucGarcia` completa.
- **Cuando** `$supervisor` propone el reparto.
- **Entonces** las 20 con actividad se reparten entre `$ts2` y `$ts3` en proporción 2:1 (±1), y lo mismo las 40 dormidas; los miembros de `$ucGarcia` van al mismo destino; ninguna asignación vigente ha cambiado.

**TF-ASG-32 — Confirmación**
- **Dado** la propuesta de TF-ASG-31 con un destino modificado por `$supervisor`.
- **Cuando** se confirma.
- **Entonces** se cierran las 60 asignaciones de `$ts1`, se crean 60 nuevas con `origen = reparto`, `reparto_id` y `cuenta_en_reparto = false`; la línea modificada respeta el cambio; `$ts2` y `$ts3` reciben aviso. Descartar una propuesta no cambia nada.

---

## Grupo H — Actividad y bandeja

**TF-ASG-33 — Casos con actividad y dormidos**
- **Dado** `$ts1` con una historia con plan activo sin apuntes recientes, una con un apunte de hace 2 meses y una con el último apunte de hace 8 meses y sin plan.
- **Cuando** se calcula el resumen de `$cssNorte` (inactividad 6 meses).
- **Entonces** `$ts1` tiene 3 asignados, 2 con actividad y 1 dormido. El resumen no altera ninguna asignación ni el sorteo.

**TF-ASG-34 — Acceso a la bandeja**
- **Dado** personas pendientes en `$cssNorte` y en `$cssSur`.
- **Cuando** `$supervisor`, `$ts1` y el supervisor de `$cssSur` abren la bandeja.
- **Entonces** `$supervisor` ve solo las de `$cssNorte`; `$ts1` recibe acceso denegado; el otro supervisor ve solo las de `$cssSur`.

---

## Tabla de estado (rellenar al implementar)

| Grupo | Tests | Estado |
|---|---|---|
| A — Territorio y geocodificación | TF-ASG-01..04 | |
| B — Ámbitos y cobertura | TF-ASG-05..08 | |
| C — Asignación de centro | TF-ASG-09..15 | |
| D — Profesionales del reparto | TF-ASG-16..17 | |
| E — Sorteo | TF-ASG-18..24 | |
| F — Asignación inicial | TF-ASG-25..30 | |
| G — Reparto por salida | TF-ASG-31..32 | |
| H — Actividad y bandeja | TF-ASG-33..34 | |
