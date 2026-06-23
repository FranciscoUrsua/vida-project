# Plan de refactorización

## Objetivo

Abordar dos deudas arquitectónicas distintas pero relacionadas:

1. Reubicar clases de dominio que hoy están en `vida/app` y que deberían vivir en módulos funcionales.
2. Reducir el tamaño y la responsabilidad de los componentes Livewire operativos más grandes, en especial `CiudadanoPage` y `PlanPage`.

El objetivo no es rehacer la aplicación, sino estabilizar su estructura para que los siguientes cambios sean más predecibles, más mantenibles y más fáciles de testear.

## Diagnóstico resumido

### 1. Dominio mezclado entre `app/` y módulos

Hay varias clases en `vida/app` que el propio código considera provisionales o "stub" y que ya declaran como destino natural un módulo funcional:

- `App\Models\Ciudadano` -> `Modules\Ciudadania\Models\Ciudadano`
- `App\Models\HistoriaSocial` -> `Modules\Intervencion\Models\Historia`
- `App\Models\Apunte` -> `Modules\Intervencion\Models\Apunte`
- `App\Services\CiudadanoService` -> `Modules\Ciudadania\Services\CiudadanoService`
- `App\Services\HistoriaSocialService` -> `Modules\Intervencion\Services\HistoriaSocialService`

Esto genera varios problemas:

- imports ambiguos y difíciles de seguir;
- tests que pueden estar apuntando al modelo "provisional" en lugar del definitivo;
- dificultad para saber qué pertenece realmente a cada contexto funcional;
- riesgo de duplicar lógica de dominio entre `app/` y módulos.

### 2. Componentes Livewire con demasiadas responsabilidades

Los dos casos más claros son:

- `Modules\Intervencion\Http\Livewire\CiudadanoPage`
- `Modules\Intervencion\Http\Livewire\PlanPage`

Ambos componentes mezclan:

- carga de datos;
- estado visual;
- modales;
- mutaciones de negocio;
- validación;
- composición de timeline;
- coordinación entre varios subdominios;
- acciones con efectos laterales.

El problema aquí no es de ubicación, sino de responsabilidad. Aunque estas clases ya viven en el módulo correcto, son demasiado grandes para seguir evolucionando con seguridad.

## Principios de refactorización

1. Mantener el proyecto como monolito modular, no como colección de paquetes aislados.
2. Dejar en `vida/app` solo infraestructura transversal o núcleo técnico común.
3. Mover a módulos únicamente dominio funcional claro.
4. No mezclar en una misma fase cambio de namespace, cambio de comportamiento y rediseño de UI.
5. Hacer cambios pequeños, con compatibilidad transitoria cuando sea necesario.
6. Priorizar primero claridad estructural y después elegancia interna.

## Criterio para decidir si algo va en `app/` o en un módulo

Debe quedarse en `vida/app` cuando sea una de estas cosas:

- providers globales;
- middleware transversal;
- scopes compartidos;
- observers transversales;
- servicios de infraestructura;
- traits de soporte técnico reutilizables;
- panel Filament común;
- utilidades sin dependencia real de un contexto funcional.

Debe ir a un módulo cuando represente:

- una entidad de dominio de ese contexto;
- un servicio de dominio de ese contexto;
- reglas propias del flujo funcional;
- consultas específicas de un caso de uso del módulo;
- acciones de escritura o lectura que no tengan sentido fuera de ese módulo.

## Clases candidatas a mover fuera de `vida/app`

### Ciudadanía

Mover a `Modules\Ciudadania`:

- `App\Models\Ciudadano`
- `App\Services\CiudadanoService`

Revisar también por dependencia o proximidad funcional:

- policies y queries que se apoyen principalmente en ciudadano;
- recursos Filament de catálogos exclusivos de Ciudadanía;
- posibles clases auxiliares aún en `app/` que dependan del agregado ciudadano.

### Intervención

Mover a `Modules\Intervencion`:

- `App\Models\HistoriaSocial`
- `App\Models\Apunte`
- `App\Services\HistoriaSocialService`

Revisar también:

- queries muy orientadas al expediente o timeline;
- lógica de visibilidad o timeline que hoy esté incrustada fuera del módulo.

## Clases que deben permanecer en `vida/app`

Estas piezas tienen sentido como infraestructura transversal y no deben moverse salvo rediseño mayor:

- `App\Services\AuditService`
- `App\Observers\AuditObserver`
- `App\Models\Scopes\AmbitoUoScope`
- `App\Http\Middleware\AuditarAccesoCiudadano`
- `App\Traits\Auditable`
- `App\Traits\TieneDireccion`
- `App\Providers\*`
- `App\Filament\*` cuando el recurso sea de administración general o transversal

## Plan por fases

### Fase 0. Inventario y dependencias

Objetivo: preparar el cambio sin tocar comportamiento.

Tareas:

- listar todas las referencias a `App\Models\Ciudadano`, `App\Models\HistoriaSocial`, `App\Models\Apunte`;
- listar todas las referencias a `App\Services\CiudadanoService` y `App\Services\HistoriaSocialService`;
- identificar factories, seeders, tests, policies, scopes, observers y recursos Filament afectados;
- anotar posibles conflictos de nombre con clases ya existentes en módulos.

Entregable:

- mapa de dependencias por clase a mover.

### Fase 1. Consolidación de modelos de dominio en módulos

Objetivo: que el namespace real del dominio coincida con su contexto funcional.

Orden recomendado:

1. `Ciudadano`
2. `HistoriaSocial`
3. `Apunte`

Estrategia:

- crear o completar la clase definitiva en el módulo;
- mover relaciones, casts, scopes locales y documentación;
- actualizar imports en servicios, policies, middleware, observers, queries, tests y Filament;
- eliminar el stub solo cuando todas las referencias apunten al módulo.

Riesgos:

- rotura de imports en tests;
- referencias cruzadas con scopes o policies;
- factories o seeders atados al namespace antiguo.

Mitigación:

- hacer el movimiento uno a uno;
- ejecutar tests del área afectada tras cada traslado;
- no mezclar tres modelos en un mismo commit si aparecen dependencias no triviales.

### Fase 2. Consolidación de servicios de dominio en módulos

Objetivo: sacar de `app/Services` la lógica que pertenece claramente a un contexto funcional.

Orden recomendado:

1. `CiudadanoService` -> `Modules\Ciudadania\Services\CiudadanoService`
2. `HistoriaSocialService` -> `Modules\Intervencion\Services\HistoriaSocialService`

Estrategia:

- mover el servicio con el mismo contrato público siempre que sea posible;
- corregir bindings o resoluciones por contenedor si existieran;
- revisar quién lo usa desde controladores, componentes Livewire, commands y tests.

Riesgos:

- dependencias ocultas desde módulos no evidentes;
- servicios parcialmente "stub" que necesiten reescritura antes de mover.

Mitigación:

- si el servicio aún es demasiado provisional, moverlo primero tal cual y mejorar después;
- evitar rediseñar el servicio en la misma fase que el cambio de namespace.

### Fase 3. Troceado de `CiudadanoPage`

Objetivo: reducir el componente más cargado del interfaz operativo.

Responsabilidades actuales que conviene separar:

- cabecera y resumen del ciudadano;
- unidad de convivencia;
- relaciones y representante;
- timeline de historia social;
- toolbox de acciones;
- formularios de entrevista, anotación, derivación y gestión;
- integración con valoraciones y escalas;
- acceso a plan y generación de PDF.

Corte recomendado:

- componentes Livewire o subcomponentes Blade para paneles visuales;
- actions/services para operaciones de escritura;
- query helpers o services para composición de datos complejos.

Separación mínima deseable:

- `CiudadanoResumenPanel`
- `CiudadanoUnidadConvivenciaPanel`
- `CiudadanoTimelinePanel`
- `CiudadanoAccesosPanel`
- `CiudadanoToolboxPanel`
- acciones específicas por caso de uso:
  - guardar entrevista
  - guardar anotación
  - crear derivación
  - guardar gestión
  - guardar valoración
  - guardar escala
  - generar PDF

Criterio importante:

- el componente principal debe coordinar la pantalla, no ejecutar toda la lógica de negocio directamente.

### Fase 4. Troceado de `PlanPage`

Objetivo: separar edición, flujo y composición del plan de intervención.

Responsabilidades actuales que conviene separar:

- carga del plan y contexto de historia/UC;
- diagnóstico;
- selección de fichas;
- objetivos;
- actuaciones del ayuntamiento;
- compromisos del ciudadano;
- participantes;
- firmas;
- motivo obligatorio de cambios;
- cierre del plan;
- valoración de indicadores.

Corte recomendado:

- paneles o subcomponentes por sección funcional;
- actions específicas para guardar cambios;
- una capa de aplicación para cambios que requieren motivo y trazabilidad.

Separación mínima deseable:

- `PlanDiagnosticoPanel`
- `PlanObjetivosPanel`
- `PlanActuacionesPanel`
- `PlanCompromisosPanel`
- `PlanParticipantesPanel`
- `PlanFirmasPanel`
- `PlanCierrePanel`
- actions:
  - guardar diagnóstico
  - guardar seguimiento
  - aplicar cambios con motivo
  - guardar objetivo
  - guardar compromiso
  - guardar actuación
  - guardar participante
  - cerrar plan

### Fase 5. Limpieza posterior

Objetivo: cerrar la refactorización sin dejar deuda colgando.

Tareas:

- borrar stubs ya sustituidos;
- normalizar imports y namespaces;
- revisar documentación y PHPDoc;
- revisar tests con referencias antiguas;
- actualizar cualquier binding, provider o factory afectado.

## Orden de ejecución recomendado

1. Inventario de dependencias.
2. Movimiento de `Ciudadano`.
3. Movimiento de `HistoriaSocial`.
4. Movimiento de `Apunte`.
5. Movimiento de `CiudadanoService`.
6. Movimiento de `HistoriaSocialService`.
7. Troceado de `CiudadanoPage`.
8. Troceado de `PlanPage`.
9. Limpieza final.

Este orden evita una situación peligrosa: trocear UI mientras el dominio sigue partido entre `app/` y módulos.

## Riesgos principales

### Riesgo 1. Imports ambiguos durante la transición

Si conviven durante demasiado tiempo `App\Models\Ciudadano` y `Modules\Ciudadania\Models\Ciudadano`, el proyecto se vuelve frágil.

Respuesta:

- minimizar el tiempo de convivencia;
- hacer el cambio por clase y cerrar cada una del todo.

### Riesgo 2. Tests que dependen del namespace antiguo

Es probable que haya tests que referencien explícitamente modelos o servicios en `App\...`.

Respuesta:

- actualizar tests dentro de la misma fase del movimiento;
- no dejar aliases temporales innecesarios salvo bloqueo real.

### Riesgo 3. Refactorización visual mezclada con refactorización estructural

Si se cambia al mismo tiempo CSS, Blade, componentes Livewire y dominio, el diagnóstico de errores se complica mucho.

Respuesta:

- separar siempre estructura de dominio y refactor de pantalla;
- evitar cambios cosméticos en las fases de movimiento de clases.

### Riesgo 4. Extraer demasiado pronto componentes pequeños sin contrato claro

Partir `CiudadanoPage` y `PlanPage` sin definir antes qué datos reciben y qué acciones exponen solo reparte el caos.

Respuesta:

- definir primero cortes por responsabilidad;
- mover después la lógica de lectura/escritura a acciones o servicios;
- dejar el componente raíz como orquestador.

## Resultado esperado

Al final de este plan deberíamos tener:

- dominio funcional ubicado en su módulo real;
- `vida/app` reducido a infraestructura transversal y administración global;
- componentes Livewire operativos más pequeños y con responsabilidades claras;
- menos imports ambiguos;
- tests más coherentes con la arquitectura real del proyecto.

## Criterio de éxito

Se considerará completada la refactorización cuando:

- no queden modelos o servicios de dominio marcados como stub en `vida/app` si su destino funcional ya existe;
- `CiudadanoPage` y `PlanPage` hayan dejado de concentrar toda la lógica de escritura y composición;
- el código nuevo del dominio ya no nazca en `app/` por costumbre, sino en el módulo que le corresponde.
