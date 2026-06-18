# CLAUDE.md — VIDA 360

Instrucciones permanentes para Claude CLI en el proyecto VIDA 360.
Este fichero se aplica a todas las sesiones sin necesidad de repetirlo en cada prompt.

---

## 1. Antes de tocar cualquier fichero

1. `git pull origin master` — siempre como primer paso, sin excepciones.
2. Leer `SESSION.md` — resume el estado actual y el siguiente paso recomendado.
3. Leer `docs/principios-vida360.md` — referencia de decisiones arquitectónicas y restricciones de dominio.
4. Leer `docs/decisiones-tecnicas.md` — decisiones técnicas que ya se han tomado, para no contradecirlas ni replantearlas inútilmente.
5. Leer `docs/documentacion-proyecto.md` — arquitectura general, modelos y convenciones.
6. Leer `docs/design-system/SKILL.md` - desing-system, antes de cualquier tarea de UI, Blade o Filament.
7. Si la tarea afecta a un módulo específico, leer `docs/modulo-{nombre}.md` antes de escribir código.
8. Si existe un fichero en `docs/instrucciones-cli/` para la tarea, leerlo íntegramente antes de actuar.

---

## 2. Convenciones de código

### General
- Estándar PSR-12 en todo el código PHP.
- Nombres de variables, métodos y clases en inglés. Comentarios y PHPDoc en español.
- No usar `bcrypt()` manual; el cast `hashed` del modelo lo gestiona.
- No usar `factory()` en seeders de producción; usar `Model::create()` con datos explícitos.

### PHPDoc y comentarios

**Cobertura obligatoria — sin excepciones:**

- **Todas las clases** (modelos, enums, Livewire, Filament, servicios, observers, traits, seeders):
  una línea de descripción que explique la *responsabilidad* de la clase, no su nombre.
  Añadir `@property` / `@property-read` para campos de modelos y propiedades Livewire relevantes.
  Añadir `@throws` si el constructor o `booted()` lanzan excepciones.

- **Todos los métodos públicos y protegidos**:
  una línea de descripción del *para qué* (no del cómo).
  `@param TipoExacto $nombre` por cada parámetro; omitir solo si el tipo nativo PHP es inequívoco.
  `@return TipoExacto` siempre, aunque el tipo esté declarado en la firma — usar `@return void` si aplica.

- **Métodos privados**: docblock cuando el nombre solo no basta para entender el contrato o la lógica no es trivial.

**Casos que generan carencias frecuentes — reglas concretas:**

| Tipo | Requisito mínimo |
|---|---|
| Enum (clase) | Docblock explicando qué representa el conjunto de valores |
| Enum (métodos `etiqueta()`, `label()`, etc.) | `@return string` + descripción de una línea |
| Livewire `#[Computed]` | `@return TipoExacto` obligatorio aunque el tipo esté declarado |
| Scope Eloquent | `@param Builder<self> $query` + `@return Builder<self>` |
| Relación Eloquent | `@return HasMany<Modelo>`, `@return BelongsTo<Modelo, self>`, etc. con genérico completo |
| Filament Page (List/Create/Edit/View) | Al menos: `/** Página de listado/creación/edición de {entidad}. */` |
| Observer (cada método) | `@param NombreModelo $model` + `@return void` |
| Closure en `booted()` | Comentario inline o extraer a método privado con docblock propio |

**Restricciones de dominio críticas** (p. ej. `ColectivoProtegido`): documentar la razón de la restricción
en el PHPDoc de clase y en el método que la implementa.

**Comentario inline**: obligatorio en lógica no evidente, workarounds o invariantes ocultas.
No documentar lo que el nombre ya dice.

**Autocomprobación al terminar cada tarea**: revisar todos los ficheros nuevos o modificados y
confirmar que cada clase y cada método público/protegido tienen su docblock antes de cerrar.

### Modelos Eloquent
- Soft deletes en todas las entidades sensibles. No hay hard deletes en producción.
- Enums PHP solo cuando el código toma decisiones basándose en el valor (principio 3.10).
- Campos clasificatorios sin lógica de negocio → `catalogos_sistema`, nunca enum.
- Los valores de `catalogos_sistema` nunca se referencian en `match`/`if`/`switch`.
- Versionado polimórfico mediante trait `Versionable` en todas las entidades no auxiliares (principio 3.5).

### Arquitectura de módulos
- Módulos nwidart v12: código en `Modules/NombreModulo/app/`; providers en `bootstrap/providers.php`.
- Filament Resources centralizados en `app/Filament/Resources/` — decisión arquitectónica deliberada, no moverlos.
- Filament para configuración y backoffice. Livewire para operación diaria de profesionales (principio 3.12).
- Toda integración con sistemas externos mediante adaptador con mock activo por defecto (principio 3.6).

### Frontend y UI
- Sistema unificado: Tailwind CSS + tokens VIDA + componentes Blade/Livewire propios. Ver principio 4.18 de `docs/principios-vida360.md`.
- No crear nuevas vistas, layouts ni componentes con Bootstrap, Foundation u otros frameworks visuales generalistas.
- No cargar Bootstrap ni Bootstrap Icons por CDN en nuevas superficies de aplicación.
- En Livewire no usar clases Bootstrap (`btn`, `row`, `col-*`, `form-control`, `form-select`, `alert`, `card`, etc.) para nuevas pantallas.
- Evitar estilos inline estructurales en Blade. Usar componentes VIDA, clases Tailwind y tokens CSS. Solo se admiten estilos inline para valores dinámicos inevitables.
- Filament usa su tema VIDA y componentes nativos. Los overrides sobre clases internas `.fi-*` deben estar centralizados en el tema y ser excepcionales.
- Mantener un único sistema de iconos por superficie. No mezclar Bootstrap Icons con Lucide/Blade Icons salvo decisión técnica documentada.

### Tests
- Base de datos de test: PostgreSQL (`vida_testing`). No usar SQLite.
- Tests escritos antes o en paralelo a la implementación, nunca después.
- Cada test describe comportamiento observable desde fuera, no detalles de implementación.
- Incluir casos que deben fallar, no solo happy path.
- Para tests críticos de seguridad o dominio: verificar también en negativo (el test debe fallar
  si se elimina la restricción que protege).
- Al finalizar una tarea, ejecutar únicamente los tests del módulo o grupo
  correspondiente (`--filter=NombreModulo` o la ruta concreta del fichero).
  No ejecutar la suite completa (`php artisan test` sin filtro) al finalizar.
  La suite completa se pasa a discreción: antes de merge a main, tras sesiones
  que hayan tocado código transversal, o cuando SESSION.md indique que procede.

---

## 3. Restricciones de dominio críticas

Estas restricciones nunca pueden relajarse sin decisión explícita documentada:

- **Colectivos protegidos:** no son accesibles fuera de la Unidad Orgánica responsable salvo aprobación explícita de un responsable de la UO.
- **Colectivos protegidos:** no tienen baja lógica. `ColectivoProtegido::delete()` lanza `LogicException`.
  Ver principio 3.11 y `docs/instrucciones-cli/organizacion-colectivos-tests.md`.
- **Anotaciones privadas:** solo accesibles por su autor. Sin excepciones de rol ni jerarquía.
- **Consulta al padrón para VVG:** nunca se lanza. Ver principio 4.1.
- **La IA asiste, nunca decide:** ningún componente de IA ejecuta acciones con consecuencias
  sobre personas sin validación explícita de un profesional. Ver principio 3.9.
- **El pasado es inmutable:** los cambios de estado generan nuevos registros, no sobrescriben. Ver principio 4.2.
- **Seguridad en datos personales:** los datos personales de ciudadanos se guardan cifrados en la BBDD, sin excepción.

---

## 4. Al finalizar cada sesión

1. Añadir entrada a `CHANGELOG.md` con:
   - Fecha
   - Módulo o área afectada
   - Lista de cambios realizados (migraciones, modelos, recursos, tests)
   - Decisiones de implementación tomadas que no estaban en las instrucciones
2. Si durante la sesión han surgido decisiones postergadas, integraciones pendientes,
   o ideas no implementadas, añadirlas a `BACKLOG.md` con fecha y módulo afectado.
   No esperar a que lo haga el desarrollador: si algo ha quedado pendiente, va al BACKLOG.
3. Actualizar `SESSION.md` con:
   - Qué tarea se acaba de completar (una línea)
   - En qué punto exacto está el proyecto
   - Cuál es el siguiente paso concreto recomendado
   - Cualquier contexto relevante para retomar sin fricción
   SESSION.md se actualiza al terminar cada tarea significativa (implementar un módulo,
   añadir tests, aplicar un conjunto de cambios), no al final del día de trabajo.
   Sobreescribir el contenido anterior: SESSION.md refleja solo el estado actual,
   el historial está en CHANGELOG.md.
4. `git add -A`
5. `git commit -m "<tipo>(<módulo>): <descripción concisa>"` — formato conventional commits.
   Ejemplos de tipo: `feat`, `fix`, `refactor`, `test`, `docs`, `chore`.
6. `git push origin master`

---

## 5. Mapa de documentación

### Raíz del proyecto
| Documento | Cuándo leerlo |
|---|---|
| `SESSION.md` | Siempre, como segundo paso tras el pull |
| `CHANGELOG.md` | Para entender el estado actual antes de una sesión de continuación |
| `BACKLOG.md` | Cuando se quiera consultar deuda técnica o ideas pendientes |

### docs/
| Documento | Cuándo leerlo |
|---|---|
| `docs/principios-vida360.md` | Siempre, antes de cualquier sesión |
| `docs/documentacion-proyecto.md` | Siempre, antes de cualquier sesión |
| `docs/decisiones-tecnicas.md` | Antes de tomar decisiones de arquitectura o elegir herramientas |
| `docs/modulo-{nombre}.md` | Antes de tocar ese módulo |
| `docs/instrucciones-cli/{fichero}.md` | Cuando se indique explícitamente en el prompt |

---

## 6. Estructura de instrucciones CLI

Las instrucciones detalladas de cada tarea están en `docs/instrucciones-cli/`.
Cuando el prompt diga *"ejecuta las instrucciones de X"*, leer ese fichero antes de actuar.

Ficheros disponibles:

| Fichero | Contenido |
|---|---|
| `organizacion-colectivos-tests.md` | Inmutabilidad de colectivos protegidos + 13 tests funcionales |
| `usuarios-tests.md` | 18 tests funcionales (TF-USU-16/17 pendientes de revisión Intervención) |
| `prestaciones-tests.md` | 13 tests funcionales (TF-PRE-13 parcial, pendiente Intervención) |
| `autenticacion-tests.md` | 23 tests funcionales de login, logout y onboarding (TF-AUTH-01 a TF-AUTH-23) |
| `autenticacion-implementacion.md` | Instrucciones paso a paso para implementar login, onboarding y componente avatar |
| `demo-worlds-cli.md` | Sistema de world-building para entornos de demo: infraestructura, escenarios, mundos YAML y página Filament |
| `tipo-ficha-implementacion.md` | TipoFichaResource: creador de fichas de valoración + 10 tests (TF-INT-H01 a H10) |
| `ficha-schema-snapshot.md` | Ficha con schema_snapshot + Versionable + pre-relleno: migración, modelo, inversión restricción TipoFicha, 12 tests (TF-INT-I01 a I12) |
