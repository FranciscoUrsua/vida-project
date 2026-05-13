# CLAUDE.md — VIDA 360

Instrucciones permanentes para Claude CLI en el proyecto VIDA 360.
Este fichero se aplica a todas las sesiones sin necesidad de repetirlo en cada prompt.

---

## 1. Antes de tocar cualquier fichero

1. `git pull origin main` — siempre como primer paso, sin excepciones.
2. Leer `docs/principios-vida360.md` — referencia de decisiones arquitectónicas y restricciones de dominio.
3. Leer `docs/documentacion-proyecto.md` — arquitectura general, modelos y convenciones.
4. Si la tarea afecta a un módulo específico, leer `docs/modulo-{nombre}.md` antes de escribir código.
5. Si existe un fichero en `docs/instrucciones-cli/` para la tarea, leerlo íntegramente antes de actuar.

---

## 2. Convenciones de código

### General
- Estándar PSR-12 en todo el código PHP.
- Nombres de variables, métodos y clases en inglés. Comentarios y PHPDoc en español.
- No usar `bcrypt()` manual; el cast `hashed` del modelo lo gestiona.
- No usar `factory()` en seeders de producción; usar `Model::create()` con datos explícitos.

### PHPDoc y comentarios
- PHPDoc obligatorio en todas las clases: descripción, `@property` de campos relevantes, `@throws` si aplica.
- PHPDoc obligatorio en todos los métodos públicos: descripción, `@param`, `@return`.
- Comentario inline obligatorio en lógica compleja o no evidente. Si el código necesita explicación, la lleva.
- En modelos con restricciones de dominio críticas (como `ColectivoProtegido`), documentar explícitamente
  la razón de la restricción en el PHPDoc de clase y en el método que la implementa.

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

### Tests
- Base de datos de test: PostgreSQL (`vida_testing`). No usar SQLite.
- Tests escritos antes o en paralelo a la implementación, nunca después.
- Cada test describe comportamiento observable desde fuera, no detalles de implementación.
- Incluir casos que deben fallar, no solo happy path.
- Para tests críticos de seguridad o dominio: verificar también en negativo (el test debe fallar
  si se elimina la restricción que protege).

---

## 3. Restricciones de dominio críticas

Estas restricciones nunca pueden relajarse sin decisión explícita documentada:

- **Colectivos protegidos:** no tienen baja lógica. `ColectivoProtegido::delete()` lanza `LogicException`.
  Ver principio 3.11 y `docs/instrucciones-cli/organizacion-colectivos-tests.md`.
- **Anotaciones privadas:** solo accesibles por su autor. Sin excepciones de rol ni jerarquía.
- **Consulta al padrón para VVG:** nunca se lanza. Ver principio 4.1.
- **La IA asiste, nunca decide:** ningún componente de IA ejecuta acciones con consecuencias
  sobre personas sin validación explícita de un profesional. Ver principio 3.9.
- **El pasado es inmutable:** los cambios de estado generan nuevos registros, no sobrescriben. Ver principio 4.2.

---

## 4. Al finalizar cada sesión

1. Añadir entrada a `CHANGELOG.md` con:
   - Fecha
   - Módulo o área afectada
   - Lista de cambios realizados (migraciones, modelos, recursos, tests)
   - Decisiones de implementación tomadas que no estaban en las instrucciones
2. `git add -A`
3. `git commit -m "<tipo>(<módulo>): <descripción concisa>"` — formato conventional commits.
   Ejemplos de tipo: `feat`, `fix`, `refactor`, `test`, `docs`, `chore`.
4. `git push origin main`

---

## 5. Mapa de documentación

| Documento | Cuándo leerlo |
|---|---|
| `docs/principios-vida360.md` | Siempre, antes de cualquier sesión |
| `docs/documentacion-proyecto.md` | Siempre, antes de cualquier sesión |
| `docs/modulo-{nombre}.md` | Antes de tocar ese módulo |
| `docs/instrucciones-cli/{fichero}.md` | Cuando se indique explícitamente en el prompt |
| `CHANGELOG.md` | Para entender el estado actual antes de una sesión de continuación |

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
