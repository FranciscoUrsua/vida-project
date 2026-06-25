# Guardrails de Bootstrap para VIDA 360

Norma transitoria obligatoria para la migración descrita en
`docs/design-system/bootstrap-migration-plan.md`.

Este documento define cómo debe trabajar cualquier agente o desarrollador
cuando toque la superficie Blade/Livewire operativa o pública durante la
Fase 0: cortar el crecimiento de la deuda antes de seguir migrando.

---

## 1. Ámbito

Estas reglas aplican a:

- Blade público;
- Blade/Livewire operativo;
- layouts compartidos;
- SCSS y JS de la aplicación fuera de Filament.

No cambian la base de Filament, que mantiene su tema propio y su ecosistema
actual.

---

## 2. Regla principal

En la superficie operativa y pública de VIDA 360, la jerarquía de decisión es
siempre esta:

1. **Bootstrap 5.3 estándar**
2. **componentes compartidos `op-*`**
3. **clases específicas de pantalla**, solo si existe una necesidad
   estructural real

Si Bootstrap resuelve el problema, no se crea una abstracción nueva.

---

## 3. Prohibiciones de Fase 0

Mientras dure esta fase:

1. no crear nuevas reglas en `vida/resources/css/app-operativo.css`;
2. no usar Tailwind en Blade/Livewire operativo;
3. no añadir CDNs de Bootstrap, iconos, fuentes ni utilidades de UI;
4. no crear clases del tipo:
   - `xxx-btn`
   - `xxx-input`
   - `xxx-select`
   - `xxx-modal`
   - `xxx-table`
   - `xxx-title`
   si Bootstrap ya aporta esa semántica;
5. no resolver estructura con estilos inline salvo valores dinámicos
   inevitables;
6. no introducir una nueva librería de iconos.

`app-operativo.css` se considera **CSS legacy en retirada**. Puede seguir
existiendo mientras convivan capas, pero no debe crecer.

---

## 4. Regla de iconos

La decisión de arquitectura para la migración es:

- **Filament**: Heroicons
- **Blade/Livewire público y operativo**: Heroicons

Consecuencias:

1. no usar Bootstrap Icons;
2. no usar Tabler Icons;
3. no usar CDNs de iconos;
4. si una vista Blade/Livewire ya usa otro sistema y no se migra en esa tarea,
   no ampliarlo ni extenderlo: dejarlo como deuda existente y documentarlo.

---

## 5. Dónde escribir CSS nuevo

Si hace falta CSS nuevo, solo puede ir en:

- SCSS modular compartido, si representa un componente reutilizable;
- el SCSS específico de la pantalla, si la necesidad es realmente local.

No se escribe CSS nuevo en:

- `vida/resources/css/app-operativo.css`

---

## 6. Preguntas obligatorias antes de editar UI

Antes de tocar una vista Blade/Livewire, el agente debe responder
explícitamente:

1. ¿Qué partes se resuelven con clases Bootstrap estándar?
2. ¿Qué parte, si alguna, requiere un componente `op-*` existente?
3. ¿Qué parte, si alguna, necesita CSS nuevo y por qué Bootstrap no basta?

Si no puede justificar el punto 3, no debe crear CSS nuevo.

---

## 7. Criterio de revisión

Una tarea de UI en Fase 0 debe considerarse incorrecta si:

- añade clases nuevas a `app-operativo.css`;
- mete Tailwind en la superficie operativa;
- usa CDN para Bootstrap o iconos;
- crea clases por elemento cuando Bootstrap ya lo resolvía;
- mezcla varios sistemas de iconos en una misma pantalla nueva.

---

## 8. Prompt base para agentes

Usar este bloque al iniciar cualquier tarea de UI:

> Lee `docs/design-system/bootstrap-migration-plan.md` y
> `docs/design-system/frontend-bootstrap-guardrails.md` antes de editar.
>  
> Reglas obligatorias:
> 1. Bootstrap 5.3 es la capa base en Blade/Livewire operativo y público.
> 2. No uses Tailwind en esa superficie.
> 3. No añadas CSS a `vida/resources/css/app-operativo.css`.
> 4. No crees clases `xxx-btn`, `xxx-input`, `xxx-modal`, `xxx-table` si
>    Bootstrap ya lo resuelve.
> 5. Usa este orden: Bootstrap -> `op-*` -> clase específica justificada.
> 6. No añadas CDNs de UI ni librerías nuevas de iconos.
> 7. Usa Heroicons en Blade/Livewire y en Filament.
> 8. Antes de editar, explica qué resolverás con Bootstrap y qué requerirá
>    SCSS nuevo, si aplica.

