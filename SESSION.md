# SESSION — VIDA 360

_Actualizado: 2026-06-12_

## Tarea completada

Consolidación documental de la dirección frontend de VIDA 360:
- `docs/principios-vida360.md`: añadido el principio 4.18, que fija un sistema unificado de frontend basado en Tailwind CSS, tokens VIDA y componentes Blade/Livewire reutilizables.
- `CLAUDE.md`: añadidas reglas operativas para evitar nuevas vistas con Bootstrap, Foundation, Bootstrap Icons por CDN o estilos inline estructurales.
- `docs/design-system/README.md`: actualizado el contexto del producto, iconografía, carga de iconos, rutas reales y contenido de la carpeta para alinearlo con el principio 4.18.
- `docs/design-system/SKILL.md`: actualizado el stack, la ruta de tokens y las reglas rápidas para agentes.
- `docs/design-system/stylesheets/colors_and_type.css`: corregido el comentario de importación para apuntar a la ruta real.

## Estado actual

### Frontend — dirección decidida
- Filament sigue siendo la superficie de configuración y backoffice.
- Livewire sigue siendo la superficie operativa diaria.
- Ambas superficies deben compartir lenguaje visual, tokens VIDA y criterios de interacción.
- La base para UI nueva es Tailwind CSS + tokens VIDA + componentes propios.
- Bootstrap, Bootstrap Icons por CDN y estilos inline estructurales quedan como deuda heredada, no como patrón aceptado.

### Documentación alineada
- `docs/principios-vida360.md` contiene la decisión arquitectónica.
- `CLAUDE.md` contiene las instrucciones operativas para Claude CLI.
- `docs/design-system/README.md` y `docs/design-system/SKILL.md` ya no recomiendan Bootstrap, CDN de iconos ni artefactos inexistentes como `ui_kits/vida_app/kit.css`.

### Cambios no relacionados presentes en el árbol
- `.claude/worktrees/agent-ae6caac64cb2187e6` aparece modificado antes de esta tarea.
- `vida/phpstan-baseline.neon` aparece modificado antes de esta tarea.
- No forman parte de la consolidación frontend y no deben incluirse en el commit de esta tarea salvo decisión explícita.

## Pendientes conocidos

| Componente | Pendiente |
|---|---|
| Layout operativo Livewire | Retirar Bootstrap/Bootstrap Icons por CDN y migrar a Tailwind + componentes VIDA |
| Componentes VIDA | Crear biblioteca mínima Blade/Livewire: botones, inputs, selects, badges, paneles, tablas, modales, navegación, estados vacíos |
| Tema Filament | Revisar overrides `.fi-*` y centralizarlos en el tema VIDA solo cuando sean necesarios |
| Iconos | Decidir implementación técnica: Lucide vía build o Blade Icons, sin CDN en nuevas superficies |
| Vistas Livewire existentes | Migrar progresivamente clases Bootstrap e inline styles estructurales |
| Tests visuales/responsive | Añadir verificación básica desktop/tablet/móvil cuando se refactoricen pantallas operativas |

## Siguiente paso recomendado

**Tarea 2 — crear la base técnica del sistema frontend VIDA:**
- Definir punto único de tokens compartidos para implementación.
- Crear los primeros componentes Blade/Livewire VIDA.
- Preparar el layout operativo sin Bootstrap ni Bootstrap Icons por CDN.

Después, migrar pantalla por pantalla empezando por navegación, topbar/sidebar y formularios básicos de Livewire.

## Contexto relevante para retomar

- El commit `0e4c536 docs: define unified frontend principles` ya está en `master` y contiene el principio 4.18.
- La regla operativa acordada es: en Livewire no se usan Bootstrap ni estilos inline estructurales; la UI se construye con componentes VIDA basados en Tailwind. Filament usa su tema VIDA y sus componentes nativos.
- La aplicación es desktop-first: prioridad a densidad profesional, escaneabilidad y eficiencia en PC, con soporte responsive razonable para tablet y móvil.
