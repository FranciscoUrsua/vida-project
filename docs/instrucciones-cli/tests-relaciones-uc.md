# Tests funcionales — Relaciones entre ciudadanos y UC solo lectura en ficha
# Archivo: Modules/Ciudadania/tests/Feature/Livewire/RelacionesCiudadanoTest.php
# Numeración: TF-LW-REL-01 a TF-LW-REL-20 (relaciones)
#             TF-LW-UC-01  a TF-LW-UC-04  (UC solo lectura en ficha)

---

## GRUPO REL — Panel de relaciones en FichaCiudadanoPage

### TF-LW-REL-01 — Panel se renderiza cuando hay relaciones vigentes
- **Dado** un ciudadano con una relación vigente (sin `fecha_fin`)
- **Cuando** cualquier usuario autenticado abre la ficha
- **Entonces** el panel "Relaciones" es visible y contiene una fila con el tipo y el
  nombre del ciudadano relacionado

### TF-LW-REL-02 — Panel se renderiza vacío cuando no hay relaciones
- **Dado** un ciudadano sin ninguna relación registrada
- **Cuando** cualquier usuario autenticado abre la ficha
- **Entonces** el panel "Relaciones" es visible con estado vacío (sin filas, con mensaje
  informativo)

### TF-LW-REL-03 — Relaciones cerradas no aparecen en la lista principal
- **Dado** un ciudadano con una relación con `fecha_fin` en el pasado
- **Cuando** el usuario abre la ficha
- **Entonces** esa relación no aparece en la lista principal de relaciones vigentes

### TF-LW-REL-04 — Relaciones cerradas visibles al expandir historial
- **Dado** un ciudadano con una relación cerrada (`fecha_fin` en el pasado)
- **Cuando** el usuario hace clic en "Ver historial"
- **Entonces** la relación cerrada aparece visible con su `fecha_fin` mostrada

### TF-LW-REL-05 — Botón "+ Añadir relación" visible para usuario con ciudadano.editar
- **Dado** un usuario con permiso `ciudadano.editar` (rol `tramitacion` o superior)
- **Cuando** abre la ficha de cualquier ciudadano
- **Entonces** el botón "+ Añadir relación" está presente en el panel

### TF-LW-REL-06 — Botón "+ Añadir relación" ausente para usuario sin ciudadano.editar
- **Dado** un usuario con rol `consulta_basica` (sin permiso `ciudadano.editar`)
- **Cuando** abre la ficha de un ciudadano
- **Entonces** el botón "+ Añadir relación" no aparece en el HTML renderizado

### TF-LW-REL-07 — Modal de nueva relación se abre al pulsar el botón
- **Dado** un usuario con `ciudadano.editar`
- **Cuando** hace clic en "+ Añadir relación"
- **Entonces** el modal de creación es visible con los campos tipo_relacion, buscador de
  ciudadano, fecha_inicio y (opcional) fecha_fin

### TF-LW-REL-08 — Crear relación guarda el registro y su recíproco
- **Dado** dos ciudadanos A y B, tipo de relación "padre/madre" con recíproco "hijo/a"
- **Cuando** el usuario crea la relación "A es padre/madre de B" desde la ficha de A
- **Entonces** existe un registro en `ciudadano_relaciones` (A→B, tipo padre/madre)
  y otro registro recíproco (B→A, tipo hijo/a), ambos creados en la misma transacción

### TF-LW-REL-09 — Crear relación simétrica genera un único registro extra del mismo tipo
- **Dado** dos ciudadanos A y B, tipo de relación "cónyuge" (simétrico, recíproco = cónyuge)
- **Cuando** el usuario crea la relación "A es cónyuge de B"
- **Entonces** existen exactamente dos registros: A→B cónyuge y B→A cónyuge

### TF-LW-REL-10 — La relación recíproca aparece en la ficha del otro ciudadano
- **Dado** que se ha creado la relación "A es padre de B"
- **Cuando** se abre la ficha de B
- **Entonces** aparece en el panel de relaciones "hijo/a de [nombre de A]"

### TF-LW-REL-11 — Crear relación sin ciudadano relacionado falla con validación
- **Dado** un usuario con `ciudadano.editar`
- **Cuando** envía el modal de nueva relación sin seleccionar ciudadano relacionado
- **Entonces** el componente muestra error de validación y no se crea ningún registro

### TF-LW-REL-12 — Crear relación sin tipo falla con validación
- **Dado** un usuario con `ciudadano.editar`
- **Cuando** envía el modal de nueva relación sin seleccionar tipo de relación
- **Entonces** el componente muestra error de validación

### TF-LW-REL-13 — No se puede crear una relación de un ciudadano consigo mismo
- **Dado** un usuario con `ciudadano.editar`
- **Cuando** intenta crear una relación entre el ciudadano y él mismo
  (ciudadano_id == ciudadano_relacionado_id)
- **Entonces** el componente muestra error de validación y no se guarda nada

### TF-LW-REL-14 — Modal de edición se abre al hacer clic en una relación vigente
- **Dado** un usuario con `ciudadano.editar` y una relación vigente en la ficha
- **Cuando** hace clic sobre esa relación
- **Entonces** se abre el modal de edición con los datos actuales precargados y el botón
  "Cerrar relación" visible

### TF-LW-REL-15 — "Cerrar relación" establece fecha_fin = hoy en ambos registros
- **Dado** una relación vigente A→B con su recíproca B→A
- **Cuando** el usuario hace clic en "Cerrar relación" desde el modal de edición
- **Entonces** ambos registros (directo y recíproco) tienen `fecha_fin = today()` y la
  relación desaparece de la lista principal de vigentes

### TF-LW-REL-16 — Editar observaciones de una relación no crea recíproco duplicado
- **Dado** una relación vigente A→B con su recíproca B→A ya existente
- **Cuando** el usuario edita el campo `observaciones` de la relación A→B
- **Entonces** el número total de registros en `ciudadano_relaciones` para este par no
  aumenta (no se crea un tercer registro)

### TF-LW-REL-17 — Usuario sin ciudadano.editar no puede invocar guardarRelacion
- **Dado** un usuario con rol `consulta_basica`
- **Cuando** intenta llamar al método `guardarRelacion` directamente sobre el componente
- **Entonces** el sistema lanza una excepción de autorización (403) y no se crea ningún
  registro

### TF-LW-REL-18 — El nombre del ciudadano relacionado enlaza a su ficha
- **Dado** una relación vigente en la ficha de A, apuntando a B
- **Cuando** el usuario ve la lista de relaciones
- **Entonces** el nombre de B es un enlace a `ciudadania.ciudadano.ficha` de B

### TF-LW-REL-19 — Buscador de ciudadano filtra por nombre en el modal
- **Dado** el modal de nueva relación abierto y varios ciudadanos en BD
- **Cuando** el usuario escribe al menos 3 caracteres en el buscador
- **Entonces** aparecen sugerencias coincidentes (máx. 8) con nombre y documento,
  sin incluir al propio ciudadano cuya ficha está abierta

### TF-LW-REL-20 — Panel muestra relaciones ordenadas: vigentes primero, por fecha_inicio desc
- **Dado** un ciudadano con varias relaciones vigentes con distintas fechas de inicio
- **Cuando** el usuario abre la ficha
- **Entonces** las relaciones vigentes aparecen ordenadas por `fecha_inicio` descendente
  (la más reciente primero)

---

## GRUPO UC — Unidad de convivencia en modo solo lectura (FichaCiudadanoPage)

### TF-LW-UC-01 — Panel UC muestra convivientes vigentes en modo solo lectura
- **Dado** un ciudadano que es miembro activo de una unidad de convivencia con otros
  miembros
- **Cuando** cualquier usuario autenticado abre la ficha del ciudadano
- **Entonces** el panel UC lista los nombres de los convivientes actuales sin ningún
  botón de edición, añadir miembro ni eliminar

### TF-LW-UC-02 — Panel UC muestra la relación cuando existe en ciudadano_relaciones
- **Dado** que A y B son convivientes y además existe la relación "A es padre de B"
- **Cuando** se abre la ficha de A
- **Entonces** en el panel UC aparece B con su tipo de relación ("hijo/a") además de
  su nombre

### TF-LW-UC-03 — Panel UC no se renderiza si el ciudadano no tiene UC activa
- **Dado** un ciudadano sin ninguna unidad de convivencia vigente
- **Cuando** cualquier usuario abre la ficha
- **Entonces** el panel UC no se renderiza (o muestra estado vacío sin botón de acción)

### TF-LW-UC-04 — No existe ningún método de escritura de UC en FichaCiudadanoPage
- **Dado** el componente `FichaCiudadanoPage`
- **Cuando** se inspecciona su clase PHP
- **Entonces** no existe ningún método público que modifique `unidades_convivencia` o
  `unidad_convivencia_miembros` (p. ej. `añadirMiembro`, `eliminarMiembro`, `crearUC`).
  La gestión completa de UC pertenece a `CiudadanoPage` en el módulo Intervención.
