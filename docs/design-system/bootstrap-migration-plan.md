# Plan de migración a Bootstrap para VIDA 360

## Estado de esta propuesta

Este documento propone **cambiar la dirección actual del frontend operativo** de VIDA 360.

A día de hoy, la documentación de `docs/design-system/README.md` y `docs/design-system/SKILL.md` parte de la premisa de que Bootstrap es deuda heredada y que la UI nueva debe construirse con Tailwind, tokens VIDA y componentes propios. Tras revisar el estado real del código, esta propuesta plantea una alternativa distinta:

- usar **Bootstrap 5.3 como capa base de UI** para Blade/Livewire;
- mantener una capa propia de componentes VIDA por encima;
- conservar Filament con su ecosistema actual;
- detener el crecimiento del CSS ad hoc de `resources/css/app-operativo.css`.

No es un parche local. Es una **decisión de arquitectura frontend**.

---

## Resumen ejecutivo

### Recomendación

Adoptar **Bootstrap 5.3 instalado localmente vía npm + Vite** como sistema base de:

- botones;
- formularios;
- grid y layout utilitario;
- tablas;
- modales;
- alerts;
- spacing estructural;
- estados interactivos comunes.

### No recomendado

- seguir ampliando `resources/css/app-operativo.css` como contenedor de clases por pantalla;
- seguir mezclando Bootstrap parcial, clases propias y estilos inline sin convención clara;
- usar Bootstrap por CDN en la aplicación principal.

### Iconos

Unificar la estrategia así:

- **Filament**: mantener `Heroicons`.
- **Blade/Livewire operativo y publico**: usar **Heroicons**.
- **Bootstrap Icons**: retirarlo de la superficie operativa salvo dependencia puntual justificada.
- **CDNs de iconos**: eliminarlos.

---

## Diagnóstico del estado actual

El frontend actual presenta estos problemas estructurales:

1. `resources/css/app-operativo.css` ha crecido como archivo de consolidación táctica, no como sistema.
2. Existen múltiples familias de componentes que resuelven el mismo problema con APIs distintas:
   - `plan-btn`
   - `ficha-btn`
   - `uc-btn`
   - clases `btn btn-*` heredadas
   - botones con estilos inline
3. Formularios equivalentes usan convenciones distintas:
   - `form-control` / `form-select`
   - `plan-input` / `plan-select`
   - `ficha-input`
   - `uc-modal__input`
   - inputs estilados inline
4. La iconografía está fragmentada:
   - `Heroicons` en Filament
   - otro sistema de iconos cargado por CDN
   - `Bootstrap Icons` por CDN
5. Hay dependencia de CDNs en layouts Blade importantes.
6. Resolver deuda del informe de frontend sin cambiar la base de UI solo reduce inline styles, pero no crea una arquitectura mantenible.

Conclusión: el problema no es solo estético. Es de **consistencia, mantenibilidad y coste de cambio**.

---

## Objetivos de la migración

1. Tener una base visual coherente y reusable.
2. Reducir drásticamente CSS específico por pantalla.
3. Poder cambiar tokens globales sin reescribir decenas de vistas.
4. Cargar todos los assets desde el pipeline local (`npm` + `Vite`).
5. Mantener la identidad VIDA encima de una base estándar.
6. Evitar una refactorización total de una sola vez.

---

## Alcance

### Entra en la migración

- layouts Blade públicos;
- layout operativo Livewire;
- páginas Blade/Livewire fuera de Filament;
- `resources/js/app.js`;
- entrypoints de estilos en Vite;
- sustitución progresiva del CSS operativo actual;
- unificación de iconos en Blade/Livewire.

### Queda fuera como base separada

- **Filament** y su theme propio;
- PDFs (`Modules/*/resources/css/*pdf.css`);
- renderizados puramente documentales.

Bootstrap será la base para la **superficie de producto operativa**, no para todo el proyecto indiscriminadamente.

---

## Instalación recomendada

### Opción elegida: local vía npm

Instalar en `vida/`:

```bash
npm install bootstrap @popperjs/core
npm install -D sass
```

### Por qué local y no CDN

**Recomendación: local**.

Razones:

- las versiones quedan fijadas en `package.json` y `package-lock.json`;
- pasa por el mismo build de Vite que el resto del frontend;
- permite tematizar Bootstrap con variables/tokens VIDA;
- evita dependencia de terceros en runtime;
- simplifica CSP, caché y despliegue;
- impide divergencias entre pantallas con assets locales y pantallas con assets remotos.

### Cuándo usar CDN

Solo para:

- prototipos rápidos fuera del proyecto;
- una página aislada no integrada en el build;
- una demo técnica temporal.

No es la opción correcta para la aplicación principal.

---

## Decisión sobre iconos

### Recomendación final

#### Filament
Mantener `Heroicons`.

Motivo:
- ya forma parte del ecosistema Filament;
- no aporta valor reescribirlo;
- está integrado con los componentes admin actuales.

#### Blade/Livewire
Usar `Heroicons` como sistema único.

Motivo:
- ya forma parte del ecosistema actual del proyecto;
- evita mantener dos sistemas de iconos distintos;
- elimina la dependencia de inicialización JS para iconos;
- encaja con Filament y con el stack Blade existente.

#### Bootstrap Icons
No usarlo como sistema principal.

Motivo:
- hoy solo añade fragmentación;
- no aporta una ventaja clara frente a Heroicons en este repo;
- ya existe deuda de iconos suficiente.

### Implementación propuesta

1. Eliminar del layout operativo:
   - CDN de `bootstrap-icons`
   - CDN de `bootstrap.bundle.min.js`
2. Importar Bootstrap JS localmente desde `resources/js/app.js`.
3. Renderizar iconos con `blade-ui-kit/blade-heroicons`.
4. Mantener una pequeña capa de tamaños reutilizables (`icon-12`, `icon-14`, etc.) o sustituirla por utilidades más estructuradas si se decide después.

---

## Arquitectura objetivo del frontend

La arquitectura objetivo queda en cuatro capas.

### 1. Tokens VIDA
Fuente de verdad para:

- color;
- tipografía;
- radios;
- spacing;
- sombras;
- estados semánticos.

### 2. Bootstrap como primitive layer
Bootstrap resuelve:

- `btn`;
- `form-control`;
- `form-select`;
- `table`;
- `alert`;
- `modal`;
- `dropdown`;
- `row` / `col` / `container`;
- spacing utilitario.

### 3. Componentes VIDA compartidos
Una capa pequeña y estable, por ejemplo:

- `op-page`
- `op-page__header`
- `op-page__title`
- `op-toolbar`
- `op-section`
- `op-empty`
- `op-chip`
- `op-metric`
- `op-filter-row`
- `op-table-toolbar`

Estas clases representan componentes de producto reales, no parches por pantalla.

### 4. Estructura específica de pantalla
Solo cuando de verdad exista una necesidad estructural propia:

- `plan-index`
- `uc-modal__miembro`
- `ficha-atencion-row`

No deben crearse clases equivalentes a:

- `buscar-ciudadano-page__submit`
- `mis-casos-page__filter`
- `registro-page__save-btn`

si Bootstrap ya lo resuelve.

---

## Estructura de archivos recomendada

Propuesta de reorganización de estilos:

```text
vida/resources/scss/
  _vida-tokens.scss
  _bootstrap-overrides.scss
  _op-layout.scss
  _op-components.scss
  _op-utilities.scss
  app-public.scss
  app-operativo.scss
```

### Descripción

- `_vida-tokens.scss`
  - mapa de tokens VIDA o puente hacia `resources/css/vida/colors_and_type.css`
- `_bootstrap-overrides.scss`
  - variables y overrides de Bootstrap 5.3
- `_op-layout.scss`
  - shell de páginas, topbar, sidebar, contenedores, page sections
- `_op-components.scss`
  - componentes compartidos de producto
- `_op-utilities.scss`
  - pocas utilidades propias si de verdad hacen falta
- `app-public.scss`
  - superficie pública/autenticación
- `app-operativo.scss`
  - superficie Livewire operativa

---

## Fases de ejecución

## Fase 0. Congelar el crecimiento de la deuda actual

### Objetivo
Evitar seguir ampliando `resources/css/app-operativo.css` con clases por pantalla.

### Tareas

1. no crear nuevas clases específicas si Bootstrap o componentes base lo resuelven;
2. no añadir nuevas dependencias CDN;
3. no continuar los batches de frontend sin una base definida;
4. declarar `app-operativo.css` como CSS legacy en transición.

### Resultado esperado
Se corta la deriva del CSS antes de migrar.

---

## Fase 1. Preparar assets y build local

### Objetivo
Dejar Bootstrap y SCSS integrados en el pipeline del proyecto.
### Tareas

1. crear `resources/scss/`;
2. mover entrypoints de CSS a SCSS;
3. actualizar `vite.config.js` para compilar:
   - `resources/scss/app-public.scss`
   - `resources/scss/app-operativo.scss`
   - mantener el theme de Filament separado;
4. importar Bootstrap localmente;
5. actualizar `resources/js/app.js` para JS de Bootstrap.
### Archivos implicados

- `vida/vite.config.js`
- `vida/resources/js/app.js`
- `vida/resources/scss/*`

### Resultado esperado
El frontend deja de depender de Bootstrap y librerías de iconos por CDN.
---

## Fase 2. Crear el tema Bootstrap VIDA

### Objetivo
Conseguir que Bootstrap hable el lenguaje visual de VIDA.

### Tareas

1. definir el color primario VIDA como `primary`;
2. ajustar `secondary`, `success`, `warning`, `danger`, `info`;
3. ajustar radios globales;
4. ajustar fondo, texto y bordes base;
5. ajustar focus ring;
6. ajustar inputs, selects, buttons, alerts, modals, tables, dropdowns.

### Resultado esperado
Los componentes estándar de Bootstrap son utilizables sin estilado adicional masivo.

---

## Fase 3. Rehacer los layouts base

### Objetivo
Crear dos superficies limpias: pública y operativa.

### 3.1. Layout público

Archivos:

- `resources/views/auth/login.blade.php`
- `resources/views/auth/onboarding.blade.php`
- `resources/views/inicio.blade.php`
- `resources/views/errors/sin-rol.blade.php`
- `resources/views/welcome.blade.php`

Tareas:

- quitar CDNs;
- usar `@vite` con assets locales;
- migrar botones y formularios a Bootstrap;
- conservar identidad VIDA encima del sistema base.

### 3.2. Layout operativo

Archivo principal:

- `resources/views/layouts/operativo.blade.php`

Tareas:

- quitar CDNs;
- montar topbar/sidebar/contenedor sobre base más estable;
- estandarizar la carga de JS e iconos;
- preparar un shell reusable para todas las pantallas Livewire.

### Resultado esperado
Los layouts dejan de ser el origen de la fragmentación.

---

## Fase 4. Construir una librería corta de componentes de producto

### Objetivo
No depender solo de Bootstrap, pero tampoco reinventarlo todo.

### Componentes recomendados

- `op-page`
- `op-page__header`
- `op-page__title`
- `op-page__meta`
- `op-toolbar`
- `op-actions`
- `op-section`
- `op-section__header`
- `op-section__title`
- `op-empty`
- `op-chip`
- `op-filter-row`
- `op-table-toolbar`

### Regla de uso

Primero Bootstrap.

Si falta algo de producto reutilizable, `op-*`.

Solo al final, clase específica de pantalla.

### Resultado esperado
Se reduce drásticamente la necesidad de clases tipo `xxx-page__submit`.

---

## Fase 5. Migración por superficies funcionales

No migrar archivo a archivo sin orden. Migrar por áreas.

### 5.1. Público y autenticación

Prioridad alta, dificultad baja.

Archivos:

- `resources/views/auth/*`
- `resources/views/inicio.blade.php`
- `resources/views/errors/sin-rol.blade.php`
- `resources/views/welcome.blade.php`

### 5.2. Shell operativo común

Prioridad crítica.

Archivos:

- `resources/views/layouts/operativo.blade.php`
- parciales asociados al shell común

### 5.3. Intervención: pantallas simples primero

Orden sugerido:

1. `agenda-page`
2. `buscar-ciudadano-page`
3. `mis-casos-page`
4. `ver-ficha-page`
5. `registrar-escala-page`
6. `registrar-valoracion-page`

Motivo:
- consolidan tablas, filtros, toolbar, formularios y paginación.

### 5.4. Ciudadanía e historia social

Archivos:

- `alta-ciudadano.blade.php`
- `ficha-ciudadano-page.blade.php`
- `ciudadano-page.blade.php`

Motivo:
- son más complejas y se benefician de tener ya una base madura.

### 5.5. Superficies complejas de dominio

Al final:

- `plan-page.blade.php`
- mensajería
- widgets avanzados de convivencia y relaciones
- componentes de interacción compleja

### Resultado esperado
La migración tiene orden y reduce regresiones.

---

## Fase 6. Limpieza del CSS legado

### Objetivo
Desactivar progresivamente `resources/css/app-operativo.css`.

### Estrategia

1. mantenerlo como archivo legacy mientras conviven ambas capas;
2. eliminar reglas conforme una pantalla queda migrada;
3. no copiar reglas antiguas al SCSS nuevo salvo que representen un componente real;
4. al final:
   - eliminar el archivo, o
   - dejarlo como wrapper mínimo si aún hiciera falta durante una transición corta.

### Resultado esperado
El CSS operativo deja de ser un contenedor histórico de excepciones.

---

## Fase 7. Convenciones JS para Bootstrap y Livewire

### Objetivo
Evitar conflicto entre Bootstrap JS, Livewire y Alpine.

### Regla

- usar Bootstrap JS para patrones visuales estándar:
  - dropdown
  - modal
  - collapse
  - tooltip, solo si aporta valor
- mantener Livewire/Alpine para:
  - estado de negocio
  - navegación reactiva
  - interacción acoplada a servidor

### Resultado esperado
Bootstrap no compite con Livewire; lo complementa.

---

## Fase 8. Documentar nuevas reglas de frontend

### Objetivo
Que la arquitectura no vuelva a degradarse.

### Documento sugerido

Crear o actualizar una guía tipo:

- `docs/design-system/frontend-bootstrap-guidelines.md`

### Reglas mínimas

- usar Bootstrap para controles estándar;
- usar `op-*` solo para componentes compartidos del producto;
- no usar estilos inline estructurales;
- no usar CDNs de UI en nuevas vistas;
- iconos Blade/Livewire: solo Heroicons;
- no crear una clase por botón/input/título si la semántica ya existe.

---

## Riesgos y mitigaciones

### Riesgo 1. Mezcla de sistemas durante demasiado tiempo

**Problema**
Convivencia prolongada entre Bootstrap, CSS legacy y componentes propios inconsistentes.

**Mitigación**
Migrar por superficies completas, no por líneas aisladas.

### Riesgo 2. Bootstrap usado solo como parche

**Problema**
Seguir con `plan-btn`, `ficha-btn`, `uc-btn` y además meter `btn btn-primary` sin estrategia.

**Mitigación**
Definir primero capa base y reglas de uso.

### Riesgo 3. Sobreescrituras masivas e incontroladas

**Problema**
Pelearse con Bootstrap desde CSS disperso.

**Mitigación**
Centralizar overrides en `_bootstrap-overrides.scss`.

### Riesgo 4. Regressions visuales en Livewire

**Problema**
Pantallas que se rompen al cambiar shell o spacing base.

**Mitigación**
Migración incremental por áreas y revisión visual por pantalla.

### Riesgo 5. Iconos rotos tras eliminar CDNs

**Problema**
Las vistas siguen esperando iconos basados en CDN o atributos legacy de iconos.

**Mitigación**
migrar primero los layouts base y los iconos Blade a Heroicons.
---

## Criterios de aceptación

La migración se puede considerar bien encaminada cuando:

1. no queden CDNs de Bootstrap ni librerías de iconos en layouts principales;
2. Bootstrap se cargue solo desde assets locales y los iconos se rendericen con Heroicons;
4. las pantallas nuevas usen Bootstrap + `op-*` y no clases por elemento;
5. `app-operativo.css` deje de crecer y empiece a reducirse;
6. la UI operativa tenga consistencia de botones, inputs, selects, alerts y títulos;
7. cambiar color primario o radios no implique tocar decenas de vistas.

---

## Orden de ejecución recomendado en commits

### Commit 1
Assets base:
- instalar dependencias;
- preparar SCSS;
- conectar Vite;
- mover Bootstrap a local y retirar CDNs de iconos.
### Commit 2
Theme Bootstrap VIDA:
- tokens;
- overrides;
- base visual.

### Commit 3
Layouts:
- layout público;
- layout operativo.

### Commit 4
Componentes compartidos:
- `op-page`, `op-section`, `op-toolbar`, `op-empty`, etc.

### Commit 5+
Migración por superficies:
- público
- shell operativo
- intervención simple
- ciudadanía
- plan/mensajería

---

## Decisión recomendada

Para este proyecto, la recomendación es:

- **sí a Bootstrap**;
- **sí a instalación local por npm + Vite**;
- **sí a Heroicons en Blade/Livewire**;
- **sí a Heroicons en Filament**;
- **no a una adopción parcial sin reglas**.

La clave no es introducir Bootstrap. La clave es introducirlo **como base arquitectónica coherente**.
