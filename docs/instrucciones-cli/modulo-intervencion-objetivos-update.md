# Instrucciones CLI — Actualizar `docs/modulo-intervencion.md`
# Objetivos con áreas, indicadores, motivos de cierre y beneficiarios

## Cambio 1 — Sección 5.4: reemplazar "Versionado y revisiones" por modelo completo

Localiza `### 5.4 Versionado, cambios e historial` y el bloque que viene
inmediatamente después (`### 5.5 Modificación del plan...`). Inserta **antes**
de `### 5.4` la siguiente sección nueva `### 5.2.1` que amplía los objetivos:

```markdown
### 5.2.1 Objetivos del plan — modelo completo

**Objetivos generales**

Los objetivos generales son comunes a todos los planes del mismo tipo. Se
configuran en el catálogo desde el backoffice (Catálogos → Tipos de plan →
Objetivos) y no están vinculados a ningún área temática. Son el marco de
propósito general del plan.

**Objetivos específicos**

Los objetivos específicos están vinculados a un área temática, que en VIDA360
es exactamente el `TipoFicha` de valoración correspondiente (vivienda, económica,
laboral, jurídica, sanitaria…). La FK `tipo_ficha_id` conecta el objetivo con
la ficha que lo origina.

El flujo de creación de objetivos específicos en el plan es:

1. El TSR incluye una ficha en el diagnóstico del plan (ej: ficha de vivienda).
2. El sistema propone automáticamente los objetivos específicos del catálogo
   vinculados a ese `tipo_ficha_id`.
3. El TSR selecciona cuáles incluir, puede editar su texto, y puede añadir
   objetivos ex-novo escribiendo texto libre.
4. Al incluir un objetivo (del catálogo o ex-novo), se instancia su indicador
   con valoración inicial nula.

**Catálogo de objetivos en backoffice** — tabla `objetivos_catalogo` actualizada:

| Campo | Tipo | Descripción |
|---|---|---|
| `tipo_plan_id` | FK | Tipo de plan al que pertenece |
| `nivel` | enum | `general` / `especifico` |
| `tipo_ficha_id` | FK nullable | Solo para específicos: área temática (= tipo de ficha) |
| `objetivo_general_id` | FK nullable (self) | Para específicos: su general del catálogo |
| `texto` | text | Texto del objetivo |
| `activo` | boolean | |
| `orden` | smallint | |

**Indicadores del catálogo** — tabla `indicadores_catalogo`:

| Campo | Tipo | Descripción |
|---|---|---|
| `objetivo_catalogo_id` | FK | Un indicador por objetivo del catálogo |
| `descripcion` | text | Qué se mide |
| `tipo_valoracion` | enum | `conseguido_proceso_no` / `favorable_mantiene_desfavorable` / `si_no` |

**Objetivos en el plan** — tabla `plan_objetivos` actualizada:

| Campo | Tipo | Descripción |
|---|---|---|
| `plan_id` | FK | |
| `objetivo_catalogo_id` | FK nullable | Null si el objetivo es ex-novo |
| `nivel` | enum | `general` / `especifico` |
| `tipo_ficha_id` | FK nullable | Área temática, para específicos |
| `objetivo_general_id` | FK nullable (self) | Para específicos |
| `texto` | text | Del catálogo (editable) o escrito libremente |
| `estado` | enum | `pendiente` / `en_proceso` / `conseguido` / `abandonado` |
| `orden` | smallint | |

**Indicadores en el plan** — tabla `plan_objetivo_indicadores`:

| Campo | Tipo | Descripción |
|---|---|---|
| `plan_objetivo_id` | FK | |
| `indicador_catalogo_id` | FK nullable | Null si el indicador es ex-novo |
| `descripcion` | text | Del catálogo o escrita libremente |
| `tipo_valoracion` | enum | `conseguido_proceso_no` / `favorable_mantiene_desfavorable` / `si_no` |
| `valoracion_actual` | string nullable | Valor concreto según el tipo |
| `fecha_valoracion` | date nullable | |
| `seguimiento_id` | FK nullable | Si la valoración viene de un seguimiento |

Los valores posibles de `valoracion_actual` según `tipo_valoracion`:
- `conseguido_proceso_no` → `conseguido` / `en_proceso` / `no_conseguido`
- `favorable_mantiene_desfavorable` → `favorable` / `se_mantiene` / `desfavorable`
- `si_no` → `si` / `no`

La distinción entre objetivos del catálogo y ex-novo se resuelve con los campos
nullable: `objetivo_catalogo_id` null + `indicador_catalogo_id` null = creado
libremente por el TSR, sin origen en el catálogo.
```

---

## Cambio 2 — Sección 5.2: actualizar enum motivo_cierre

En la tabla de atributos de `planes_intervencion`, localiza la fila:

```
| `motivo_cierre` | enum nullable | `objetivos_cumplidos` / `abandono` / `derivacion` / `fallecimiento` / `otros` |
```

Y reemplázala por:

```markdown
| `motivo_cierre` | enum nullable | `negativa_firma` / `consecucion_objetivos` / `cambio_residencia` / `imposibilidad_localizacion` / `fallecimiento` / `fin_intervencion` |
```

Añade inmediatamente bajo la tabla de atributos:

```markdown
**Motivos de cierre del plan:**

| Valor | Descripción visible |
|---|---|
| `negativa_firma` | Cerrado por negativa a la firma / falta de colaboración |
| `consecucion_objetivos` | Cerrado por consecución de objetivos |
| `cambio_residencia` | Cerrado por cambio de residencia |
| `imposibilidad_localizacion` | Cerrado por imposibilidad de localizar a la familia |
| `fallecimiento` | Cerrado por fallecimiento |
| `fin_intervencion` | Cerrado por finalización de la intervención |

El cierre del plan siempre requiere seleccionar un motivo. Si el motivo es
`negativa_firma` o `imposibilidad_localizacion`, el sistema muestra un aviso
al TSR indicando que debe quedar constancia en el historial de apuntes.
```

---

## Cambio 3 — Sección 5.2: aclarar modelo de beneficiarios

Al final de la tabla de atributos de `planes_intervencion`, tras el párrafo
existente sobre `unidad_convivencia_id`, añade:

```markdown
**Modelo de beneficiarios del plan:**

El plan pertenece a una persona (`historia_id` presente, `unidad_convivencia_id`
null) o a una Unidad de Convivencia (`unidad_convivencia_id` presente,
`historia_id` null). No existe un tercer modelo de "lista de beneficiarios
individuales seleccionados".

Cuando el plan es de una UC, los beneficiarios son implícitamente todos los
miembros activos de la UC en cada momento. Si un miembro entra o sale de la UC
mientras el plan está activo, el TSR actualiza el plan (con motivo si está
firmado) y, si los cambios son sustanciales, genera una nueva versión.

Una persona que sale de la UC pierde el plan de la UC. Si el TSR lo considera
oportuno, puede abrir un plan individual para esa persona.
```

---

## Cambio 4 — Actualizar sección 9: decisiones pendientes

Localiza en la sección 9 el ítem:

```
- **Pool de actuaciones del ciudadano**: actualmente texto libre...
```

Y añade **antes** de ese ítem:

```markdown
- **Objetivos ex-novo en UI**: el TSR puede crear objetivos e indicadores fuera
  del catálogo escribiendo texto libre. Al crear un indicador ex-novo, debe
  elegir el tipo de valoración entre los tres definidos. Esta funcionalidad está
  implementada en el modelo (campos nullable) pero la UI del plan debe ofrecer
  el formulario correspondiente. Ver `docs/front/ui-intervencion-plan.md`,
  sección 5 (objetivos).

- **Propuesta automática de objetivos al añadir ficha**: cuando el TSR añade
  una ficha al diagnóstico, el sistema debería proponer automáticamente los
  objetivos específicos del catálogo vinculados a ese `tipo_ficha_id`. Esta
  sugerencia es opcional — el TSR puede ignorarla. Pendiente de implementar
  en el drawer de selección de fichas de `PlanPage`.
```

---

## Checklist

- [ ] Sección 5.2.1 añadida con modelo completo de objetivos e indicadores
- [ ] Enum `motivo_cierre` actualizado con los 6 motivos correctos
- [ ] Modelo de beneficiarios aclarado en sección 5.2
- [ ] Sección 9 actualizada con los dos ítems pendientes de objetivos
- [ ] Sin modificaciones en otras secciones
- [ ] Commit: `docs(intervencion): objetivos con áreas e indicadores, motivos de cierre, beneficiarios`
