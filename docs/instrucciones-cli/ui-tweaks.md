# UI Tweaks — lista viva de ajustes pendientes
## `docs/instrucciones-cli/ui-tweaks.md`

> Fichero de registro de ajustes menores de interfaz detectados durante la revisión
> de las pantallas implementadas. Cuando la lista tenga suficientes items, se genera
> una instrucción CLI consolidada para implementarlos todos de una vez.
>
> **Formato:** una entrada por ajuste, con fecha de detección y pantalla afectada.
> Cuando un ajuste se implementa, mover la entrada a `CHANGELOG.md` con la nota
> "Resuelto en [fecha]" y eliminarlo de aquí.

---

## 2026-06-09

- **Agenda — leyenda de colores:** el código de colores de las citas no está
  explicado en ningún sitio visible. El usuario no sabe en qué se diferencia
  una cita con fondo azul de una con fondo verde. Añadir una leyenda compacta
  y discreta debajo de la agenda (visible en las tres vistas) que explique
  el significado de cada color: azul primario = entrevista / valoración,
  verde = seguimiento, coral = urgencia, gris = evento interno.
  La leyenda debe usar los tokens del design system, no colores hardcodeados.

## 2026-06-14

- **Pantalla del ciudadano — proporciones de columna:** la columna izquierda
  (info del ciudadano) es demasiado estrecha respecto al área de herramientas.
  Cambiar la proporción de aproximadamente 1/4 + 3/4 actual a 1/3 + 2/3.
  Las herramientas complejas (valoración, escala, informes) se abren a pantalla
  completa de todas formas, así que el área de herramientas no necesita tanto
  espacio en el estado en reposo.

- **Pantalla del ciudadano — últimos accesos al expediente:** añadir al final
  de la columna izquierda (después de la historia social) una sección
  "Últimos accesos" que muestre los 5 accesos más recientes al expediente de
  este ciudadano por otros profesionales. Fuente: tabla `audits` donde
  `ciudadano_id = $ciudadano->id` y `user_id != Auth::id()`, ordenados por
  `created_at DESC`, límite 5. Cada entrada muestra: nombre del profesional,
  acción (`ver` / `editar` / etc.), fecha/hora relativa. Al final un enlace
  "Ver historial completo" que abre el historial completo (pantalla pendiente
  de diseño — dejar como TODO por ahora). Si la tabla `audits` no existe
  todavía, mostrar la sección vacía con texto "Sin accesos registrados"
  y comentario `// TODO: conectar con módulo Auditoría`.
  Referencia: `docs/modulo-auditoria.md` y principio 3.6 de
  `docs/principios-vida360.md`.

- **Pantalla del ciudadano — reorganización de la cabecera:** la cabecera
  actual está desorganizada en tres bandas y repite el nombre del ciudadano.
  Reorganizar así:
  - La topbar (banda superior con menú de usuario) se mantiene igual.
  - Eliminar la segunda banda horizontal ("← Mis casos · Nombre · [Abierta]").
  - La columna izquierda empieza directamente con la cabecera del ciudadano,
    estructurada en este orden:
    1. `← Mis casos` (enlace de retorno) + `[Ficha completa]` + `[⋯]` (fila superior)
    2. Nombre completo del ciudadano (texto grande, sin avatar con iniciales)
    3. Fila: número de HS + nombre del CSS (no el ID de UO, el nombre completo
       o abreviado del centro) + badge de estado de la HS ("Historia abierta",
       "En seguimiento", "Cerrada")
    4. Fila de datos de contacto: fecha de nacimiento · edad calculada
    5. Fila: DNI · teléfono
    6. Domicilio completo
  - El avatar con iniciales desaparece completamente — es superfluo.
  - "UO" como etiqueta desaparece — sustituir por el nombre real del CSS
    obtenido de la relación del profesional responsable. Si no está disponible
    (`centroActivo()` pendiente de implementación), mostrar el campo que sí
    exista (nombre de la UO, o simplemente omitir). Añadir comentario TODO.
  - El badge de estado de la HS debe ser explícito: no solo "Abierta" sino
    "Historia Social · Abierta" o usar un label previo "Estado HS:".

- **Pantalla del ciudadano — altura del div principal:** el contenedor
  principal tiene `height: 100vh` que no descuenta el alto del topbar,
  generando un scroll vertical innecesario de pocos píxeles. Cambiar a
  `height: calc(100vh - [alto del topbar en px])` o usar flexbox en el
  layout padre con `flex: 1; overflow: hidden` para que el contenedor
  ocupe exactamente el espacio restante. El alto del topbar es 56px
  según el design system (`--topbar-height: 56px` si está definido,
  o hardcodear `calc(100vh - 56px)` como solución provisional).

- **Error de preload de CSS:** al navegar entre pantallas aparece en consola:
  "The resource .../app-operativo-XXX.css was preloaded using link preload
  but not used within a few seconds". Esto ocurre porque Vite genera un
  `<link rel="preload">` para el CSS pero Livewire navega sin recargar la
  página y el navegador no lo usa en el tiempo esperado. Solución: en
  `vite.config.js`, revisar si hay configuración de `modulePreload` que
  esté generando preloads innecesarios para CSS. Alternativamente, en el
  layout `operativo.blade.php`, asegurarse de que el CSS se carga con
  `@vite` normal y no hay un `<link rel="preload">` adicional hardcodeado.
  Si el error persiste y no afecta al funcionamiento, añadir comentario
  documentando el issue y su causa como gap conocido.

- **Toolbox — iconos y texto desaparecen al seleccionar herramienta:** al
  hacer clic en una herramienta, la cuadrícula pasa a formato compacto
  (una fila) pero los iconos Lucide y el texto de los items desaparecen.
  La causa probable es que Livewire re-renderiza el DOM al cambiar
  `$herramientaActiva` y el script de inicialización de Lucide
  (`lucide.createIcons()`) no se vuelve a ejecutar, dejando los elementos
  `<i data-lucide="...">` sin convertir a SVG. Solución: añadir en el
  componente Livewire un evento que relance la inicialización de Lucide
  tras cada re-render:
  ```javascript
  document.addEventListener('livewire:updated', () => {
      lucide.createIcons({ 'stroke-width': 1.75 });
  });
  ```
  Este listener debe estar en el layout `operativo.blade.php` junto al
  listener `livewire:navigated` existente. Verificar que no se añade
  duplicado si ya existe.
