# Asignación de centro y profesional de referencia — Diseño funcional

**Módulos afectados:** `Organizacion` (unidades territoriales), `Centro` (ámbitos territoriales, configuración), `Ciudadania` (dirección y centro asignado), `Intervencion` (profesional de referencia), `Usuarios` (cargos), geocodificación (`app/Services/Geocodificacion`).
**Estado:** diseño funcional v1 cerrado. Implementado el 2026-09-30 (ver `CHANGELOG.md`).
**Última revisión:** septiembre 2026

**Documentos relacionados:**
- Instrucciones de implementación: `docs/instrucciones-cli/2026-09-asignacion-implementacion.md`
- Tests funcionales: `docs/instrucciones-cli/2026-09-asignacion-tests.md` (TF-ASG-01 a TF-ASG-34)
- Depende de este diseño: `docs/modulo-citas.md` (destino "profesional de referencia").

---

## Nota de diseño

Toda persona atendida en servicios sociales tiene un centro y, si tiene Historia Social, un profesional de referencia. Hasta ahora VIDA asignaba la referencia a quien abría la historia y no asignaba centro. Este diseño separa dos decisiones:

1. **A qué centro pertenece la persona.** Por su dirección (geografía) o por su elección.
2. **Qué profesional del centro es su referencia.** Por sorteo o por su elección.

Tres ideas vertebran el diseño:

- **Repartir entradas, no igualar carga.** El sorteo reparte los casos *nuevos* en proporción a la jornada de cada profesional y no mira cuántos casos tiene cada uno. Así mantener abiertos casos dormidos no protege de recibir casos, y cerrar casos no se castiga con más entradas.
- **La geografía se resuelve con códigos oficiales, no con polígonos.** La Base de Datos Ciudad devuelve distrito, barrio y sección censal de cada dirección. El área de un centro es una lista de esas unidades.
- **El sistema asigna solo lo que es inequívoco.** Lo que no puede asignar con certeza va a una bandeja del supervisor, que decide a mano y con motivo.

---

## 1. Reglas de negocio

**RN-01 — Dos formas de asignar centro.** Cada centro está configurado como:
- **Adscripción por domicilio** (`inscripcion_libre = false`): la persona pertenece al centro cuyo ámbito territorial incluye su dirección. Caso general de los centros de servicios sociales.
- **Libre elección** (`inscripcion_libre = true`): la persona elige a qué centro acude. Caso de los CIAM.

Las soluciones mixtas (elegir centro dentro del distrito) quedan fuera de la v1.

**RN-02 — Un centro por tipo de centro.** Una persona tiene como máximo un centro asignado vigente por cada tipo de centro (centro de servicios sociales, CIAM…). Una mujer puede tener asignado su centro de servicios sociales por domicilio y, a la vez, el CIAM que ha elegido.

**RN-03 — Tres formas de asignar profesional; dos en la v1.** Cada centro está configurado con uno de estos modos:
- **Sorteo** (por defecto).
- **Libre elección**: la persona elige profesional del centro al abrir su Historia Social. Si no elige, se sortea.
- **Sin asignación automática**: se mantiene el comportamiento actual (quien abre la historia queda como referencia). Para centros donde la referencia no aplica o se decide de otra forma.

La asignación por geografía dentro del centro (zonas por profesional) **queda fuera de la v1**. El modelo de unidades territoriales la permitiría sin rediseño.

**RN-04 — Sin cambio a petición de la persona en la v1.** Una vez asignado, la persona no puede pedir cambio de centro ni de profesional. Solo el supervisor cambia asignaciones, siempre con motivo.

**RN-05 — Reparto proporcional a la jornada.** En el sorteo, cada profesional recibe a medio plazo una parte de las entradas nuevas proporcional a su jornada semanal en el centro.

**RN-06 — Unidad de convivencia.** Una persona que se incorpora a una unidad de convivencia cuyos miembros ya tienen profesional de referencia en el centro recibe ese mismo profesional. No entra en el sorteo ni cuenta como entrada.

**RN-07 — Bajas temporales.** Un profesional con una ausencia larga registrada no entra en el sorteo mientras dure. Su referencia sobre los casos que ya tiene no cambia; las citas se resuelven con sustitutos (ver `modulo-citas.md`).

**RN-08 — Salida de un profesional.** Cuando un profesional deja el centro, el supervisor lanza un reparto de sus casos entre el resto. El sistema propone, el supervisor revisa y confirma. Nada cambia hasta la confirmación.

**RN-09 — Nada ambiguo se asigna automáticamente.** Una dirección sin geocodificar, fuera de cualquier ámbito o que encaja en más de un centro, y las personas sin hogar, van a la bandeja de asignaciones del supervisor.

**RN-10 — Un cambio de domicilio no mueve a nadie.** Si la nueva dirección corresponde a otro centro, se genera una propuesta en la bandeja del supervisor del centro actual. El traslado lo decide una persona.

**RN-11 — Todo deja rastro.** Cada asignación de centro y de profesional guarda cómo se hizo (geografía, elección, sorteo, unidad de convivencia, reparto, manual), quién la hizo y por qué. Los registros no se sobrescriben: una asignación nueva cierra la anterior (principio 4.3).

**RN-12 — Casos existentes.** Las asignaciones actuales se conservan. Las reglas se aplican a partir de su implantación.

---

## 2. Unidades territoriales

### 2.1 Qué son

Divisiones oficiales del municipio, jerárquicas:

| Nivel | Ejemplo | Clave |
|---|---|---|
| Distrito | 21 — Barajas | Código de distrito (2 dígitos) |
| Barrio | 214 — Timón | Distrito + barrio. El código del barrio solo es único dentro de su distrito |
| Sección censal | 2807921028 | Código INE de 10 dígitos: provincia (28), municipio (079), distrito (21), sección (028). El número de sección solo es único dentro de su distrito |

Se cargan de los datos oficiales y se gestionan como catálogo. No se editan a mano salvo corrección.

Las "zonas censales" que usa Madrid para repartir profesionales son agrupaciones de secciones censales; el modelo las admitiría si en el futuro se implementa la asignación geográfica de profesionales.

### 2.2 Qué guarda la dirección

Cuando la dirección de una persona se normaliza con la BDC, además de lo que ya se guarda (vía, número, código postal, coordenadas), se guardan:

- el **código NDP** (identificador del portal): permite recalcular la asignación si cambian los límites, sin volver a geocodificar, y detectar que dos personas viven en el mismo portal;
- los códigos de **distrito, barrio y sección censal**.

No se guardan la parcela catastral ni la sección de cartería (minimización).

La BDC devuelve las coordenadas en UTM (ETRS89, huso 30N). El adaptador las convierte a latitud y longitud WGS84, que es lo que guarda VIDA.

---

## 3. Asignación de centro

### 3.1 Ámbito territorial del centro

El ámbito de un centro es una lista de unidades territoriales de cualquier nivel, gestionada desde Filament con la entidad `AmbitoTerritorial` que ya existe:

- un centro que cubre dos distritos lleva dos distritos;
- un centro que cubre parte de un distrito lleva barrios o secciones;
- un centro de ámbito municipal lleva "ciudad completa".

El tipo "polígono GIS" no se usa para asignar en la v1.

### 3.2 Resolución por dirección

Para una persona y un tipo de centro con adscripción por domicilio:

1. Se toman los centros activos de ese tipo.
2. Se busca el que incluya la **sección censal** de la dirección; si no hay, el que incluya el **barrio**; si no, el **distrito**; si no, el de **ciudad completa**. Gana la coincidencia más específica.
3. Si hay exactamente un centro en ese nivel, se asigna.
4. Si no hay ninguno, o hay más de uno en el mismo nivel, o la dirección no tiene códigos, la persona va a la bandeja de asignaciones.

### 3.3 Comprobación de cobertura

Desde Filament, para cada tipo de centro con adscripción por domicilio:

- **Solapamientos:** no se permite guardar un ámbito que haga que una misma unidad quede asignada a dos centros del mismo tipo en el mismo nivel.
- **Huecos:** una acción *Comprobar cobertura* lista las secciones censales sin centro. Los huecos se ven al configurar, no al dar de alta a una persona.

### 3.4 Libre elección de centro

En los tipos de centro de libre elección (CIAM), la asignación se hace cuando la persona elige, con `modo = eleccion`. No hay resolución por dirección.

### 3.5 Cuándo se asigna

- Al normalizarse la dirección de una persona, para cada tipo de centro con adscripción por domicilio.
- Al abrir la Historia Social, si aún no tiene centro del tipo correspondiente.
- Si la persona cambia de domicilio y el nuevo corresponde a otro centro: propuesta en la bandeja (RN-10).

---

## 4. Asignación del profesional de referencia

### 4.1 Quién entra en el reparto

Profesionales con perfil horario activo en el centro y un cargo marcado como **elegible para referencia** (configurable en Filament; en la v1, trabajador/a social). Su peso es su jornada semanal en ese centro. Quedan fuera mientras dure una ausencia registrada más larga que el umbral de ausencia prolongada del centro (el mismo que usa citas).

### 4.2 Sorteo con corrección de desvío

Cuando hay que asignar referencia a una persona nueva:

1. Para cada profesional del reparto se calcula cuántas entradas le **corresponderían** en la ventana de reparto (por defecto, 12 meses) según su peso, contando solo el tiempo en que estuvo en el reparto, y cuántas ha **recibido**.
2. Son candidatos los profesionales que han recibido menos de lo que les corresponde. Si nadie está por debajo, lo son todos.
3. Se sortea entre los candidatos, con probabilidad proporcional a su jornada.

Así nadie puede prever a quién le toca el siguiente caso (a diferencia de un turno rotatorio), y a medio plazo el reparto es exacto (a diferencia de un sorteo puro).

**Cuentan como entradas** las asignaciones por sorteo y por libre elección. **No cuentan** las de unidad de convivencia, las del reparto por salida ni las manuales.

**Cada sorteo es auditable:** se guardan los profesionales candidatos, sus pesos y el resultado.

### 4.3 Libre elección de profesional

En los centros con este modo, al abrir la Historia Social se ofrece elegir entre los profesionales del reparto. Si la persona elige, se asigna con `modo = eleccion` y **cuenta como entrada** de ese profesional: a quien eligen mucho le tocan menos por sorteo. Si no elige, se sortea. En la v1 no hay cupo máximo ni cambio posterior a petición de la persona.

### 4.4 Cuándo se asigna

Al abrir la Historia Social. Si la persona no tiene centro del tipo correspondiente, primero se asigna el centro; si eso no es posible, la historia se abre sin referencia y la persona queda en la bandeja.

El texto actual del alta ("quedar como profesional de referencia") solo se muestra en centros sin asignación automática.

### 4.5 Cambios por el supervisor

El supervisor puede cambiar la referencia de una persona con motivo (conflicto, parentesco, incompatibilidad, corrección). Genera una asignación `manual`, que no cuenta en el reparto.

---

## 5. Reparto por salida de un profesional

1. El supervisor inicia el reparto de los casos de un profesional (por salida del centro o por decisión organizativa).
2. El sistema agrupa sus casos por unidad de convivencia (cada unidad va entera) y los separa en **con actividad** y **dormidos** (§6).
3. Reparte cada grupo por separado entre el resto de profesionales del reparto, en proporción a su jornada y en orden aleatorio. Así a nadie le tocan todos los casos vivos.
4. El supervisor ve la propuesta, puede cambiar destinos individuales y confirma. Hasta entonces nada cambia.
5. Al confirmar, se cierran las asignaciones anteriores y se crean las nuevas con `modo = reparto`. Los profesionales afectados reciben aviso.

---

## 6. Actividad de los casos

Para el supervisor, por profesional:

- **Casos asignados:** asignaciones vigentes.
- **Casos con actividad:** con plan activo, o con algún apunte en los últimos N meses (configurable por centro; por defecto 6).
- **Casos dormidos:** el resto.

Es información, no una regla: no afecta al sorteo ni cierra historias. Sirve para que la carga declarada y la real se vean igual para todo el equipo.

---

## 7. Bandeja de asignaciones

Pantalla de operación para el supervisor del centro (Livewire):

- personas sin centro: dirección no geocodificada, fuera de ámbito, ambigua, persona sin hogar;
- historias sin profesional de referencia;
- propuestas de cambio de centro por cambio de domicilio;
- repartos por salida pendientes de confirmar.

Cada caso se resuelve con asignación manual y motivo.

---

## 8. Configuración

| Dónde | Qué | Por defecto |
|---|---|---|
| Centro | Tipo de centro | — |
| Centro | Adscripción por domicilio o libre elección (`inscripcion_libre`, ya existe) | Domicilio |
| Centro | Modo de asignación de referencia: sorteo, libre elección, sin asignación automática | Sorteo |
| Centro | Ventana de reparto (meses) | 12 |
| Centro | Meses sin apuntes para considerar un caso dormido | 6 |
| Centro | Ámbito territorial (lista de unidades) | — |
| Cargo | Elegible para referencia | Trabajador/a social |
| Horario del centro | Umbral de ausencia prolongada (días; compartido con citas) | 15 |

---

## 9. Impacto en otros documentos

- **`modulo-intervencion.md` §1.1.3:** el origen de la asignación inicial deja de ser siempre "quien abre la historia"; depende del modo del centro. La "reasignación en masa pendiente" queda resuelta por el reparto por salida.
- **`modulo-centros.md` §2.3 y §9:** la consulta "qué centro atiende esta dirección" deja de estar diferida; se resuelve por códigos, sin consulta espacial.
- **`modulo-ciudadania.md` §6.1:** personas sin hogar, asignación manual en la v1.
- **`geocodificacion.md`:** nuevos datos del resultado; conversión UTM → WGS84 en el adaptador BDC.
- **`front/alta-ciudadano-funcional.md` §4.4:** la opción "quedar como referencia" solo en centros sin asignación automática.
- **`modulo-citas.md`:** el destino "referencia" usa la asignación vigente de este diseño.

---

## 10. Decisiones tomadas

| Tema | Decisión |
|---|---|
| Asignación de centro | Geografía o libre elección, configurado por centro. Sin mixtas en v1 |
| Asignación de profesional | Sorteo o libre elección (con sorteo si no elige). Geografía fuera de v1 |
| Criterio de reparto | Entradas nuevas proporcionales a la jornada; no se mira la carga acumulada |
| Cambios a petición de la persona | No en v1. Solo el supervisor, con motivo |
| Geografía | Por códigos de la BDC; sin PostGIS en v1 |
| Casos ambiguos | Bandeja del supervisor; nunca asignación automática |
| Cambio de domicilio | Propuesta al supervisor; no traslado automático |
| Salida de un profesional | Reparto propuesto por el sistema y confirmado por el supervisor |
| Casos existentes | Se conservan |

## 11. Decisiones pendientes

- **Personas sin hogar:** asignación por coordenadas de pernocta (requiere geometría) y zonificación de equipos de calle.
- **Asignación geográfica de profesionales** (zonas por profesional).
- **Cambio a petición de la persona** y cupos por profesional.
- **Fórmulas mixtas de centro** (elegir dentro del distrito).
- **Revisiones de secciones censales:** procedimiento para actualizar el catálogo y reasignar con el código NDP.
