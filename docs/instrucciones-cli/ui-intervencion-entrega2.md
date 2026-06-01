# Instrucciones para Claude CLI — UI Intervención · Entrega 2
## `docs/instrucciones-cli/ui-intervencion-entrega2.md`

> Implementación del interfaz operativo del rol `intervencion` — Segunda entrega.
> Cubre la tabla de Mis casos, la búsqueda de ciudadano y el buzón de alertas
> y mensajes.
>
> **Diseño de referencia:** `docs/front/ui-intervencion.md` (secciones 4, 5 y 6)
> **Módulos afectados:** `Modules/Intervencion`, `Modules/Mensajes`
> **Prerequisito:** Entrega 1 completada y sus 14 tests pasando.

---

## Contexto

La Entrega 1 ha implementado el layout base y la agenda. Esta entrega añade tres
pantallas de gestión que comparten el mismo layout base (`layouts.operativo`) y
el mismo sidebar ya construido.

---

## Paso 1 — Revisar el estado antes de empezar

```bash
# Confirmar que la Entrega 1 está completa
php artisan test --filter=AgendaPage 2>&1 | tail -3

# Estado del módulo de Mensajes
php artisan test --filter=Mensajes 2>&1 | tail -3

# Rutas operativas actuales
php artisan route:list --path=intervencion
```

---

## Paso 2 — Rutas adicionales

Añadir en `Modules/Intervencion/routes/web.php`, dentro del grupo existente:

```php
Route::get('/casos', \Modules\Intervencion\Http\Livewire\MisCasosPage::class)->name('casos.index');
Route::get('/mensajes', \Modules\Mensajes\Http\Livewire\BuzonPage::class)->name('mensajes.index');
Route::get('/buscar', \Modules\Intervencion\Http\Livewire\BuscarCiudadanoPage::class)->name('buscar.index');
```

El componente `BuzonPage` vive en el módulo Mensajes porque la bandeja es
transversal al sistema. Si ya existe, no recrearlo.

---

## Paso 3 — Componente: MisCasosPage

Crear `Modules/Intervencion/app/Http/Livewire/MisCasosPage.php`.

### 3.1 Propiedades

```php
public string $busqueda = '';
public string $filtroSeguimiento = '';  // '' | 'vencido' | 'proximo' | 'programado' | 'sin'
public string $filtroPiso = '';         // '' | 'activo' | 'revision' | 'sin'
public string $filtroEsp = '';          // '' | 'con' | 'sin'
public string $ordenarPor = 'seg';      // 'seg' | 'nombre'
public int $pagina = 1;
public int $porPagina = 10;
```

### 3.2 Propiedad computada: casos

```php
public function getCasosProperty(): LengthAwarePaginator
```

Consulta `historias_sociales` donde `profesional_responsable_id = Auth::id()`.
Joins necesarios:
- `planes_intervencion` (tipo `general_asp`, activo más reciente)
- `planes_intervencion as planes_esp` (tipo `especializado`, COUNT)
- `alertas` (sin reconocer, para el icono de alerta)

Aplica filtros según las propiedades. La columna `fecha_siguiente_seguimiento`
vive en `seguimientos_plan` o en la relación del plan — consultar
`docs/modulo-intervencion.md` para la estructura exacta.

**Criterios del filtro de seguimiento:**
- `vencido`: `fecha_siguiente_seguimiento < today()`
- `proximo`: `fecha_siguiente_seguimiento BETWEEN today() AND today()+7`
- `programado`: `fecha_siguiente_seguimiento > today()+7`
- `sin`: `fecha_siguiente_seguimiento IS NULL`

**Orden por defecto** (`seg`): vencido → próximo → programado → sin programar.
Dentro de cada grupo, por fecha ascendente.

### 3.3 Nombre configurable del PISO

La cabecera de la columna y las etiquetas de estado no usan el literal "PISO"
sino el valor de `catalogos_sistema` con clave `nombre_plan_asp`. Si la clave
no existe, usar "PISO" como valor por defecto.

```php
protected function nombrePlanAsp(): string
{
    return CatalogoSistema::valor('nombre_plan_asp', 'PISO');
}
```

Exponer como propiedad pública en el componente para que la vista lo consuma.

### 3.4 Resets

Cuando cualquier filtro cambia, resetear `$pagina = 1`.
Usar `#[On('updated')]` de Livewire 4 o `updated{Property}` hooks.

### 3.5 Vista Blade

Tabla con columnas según `docs/front/ui-intervencion.md`, sección 4.2.
La cabecera de "PISO" usa `$this->nombrePlanAsp`.

**Semáforo de próximo seguimiento:**

| Estado | Fondo | Color texto | Icono |
|---|---|---|---|
| `vencido` | `#FAECE7` | `#712B13` | `ti-clock` |
| `proximo` | `#FAEEDA` | `#633806` | — |
| `programado` | `#EAF3DE` | `#27500A` | — |
| `sin programar` | — | `var(--color-text-secondary)` | — |

Clic en el nombre del ciudadano navega a `route('intervencion.ciudadano.show', $ciudadano->id)`.
Esta ruta no existe todavía (es Entrega 3): el enlace puede estar presente pero
sin funcionalidad real por ahora (`href="#"` con un comentario `// TODO: Entrega 3`).

Barra de paginación: páginas numeradas, botones anterior/siguiente.

---

## Paso 4 — Componente: BuscarCiudadanoPage

Crear `Modules/Intervencion/app/Http/Livewire/BuscarCiudadanoPage.php`.

### 4.1 Propiedades

```php
public string $query = '';
public string $campoBusqueda = 'nombre';  // 'nombre' | 'doc' | 'hsu' | 'alias'
public array $resultados = [];
public bool $buscado = false;
```

### 4.2 Método buscar()

```php
public function buscar(): void
```

Construye la consulta sobre `ciudadanos` según `$campoBusqueda`:

| Campo | Columna(s) |
|---|---|
| `nombre` | `CONCAT(nombre, ' ', apellido1, ' ', apellido2) ILIKE ?` |
| `doc` | `ciudadano_identificadores.valor` donde `tipo IN ('nif','nie','pasaporte')` |
| `hsu` | `ciudadano_identificadores.valor` donde `tipo = 'ni_hsu_cm'` |
| `alias` | `ciudadanos.alias` |

Cada resultado incluye la Historia Social activa si existe (`historias_sociales`
donde `estado != 'cerrada'` y la más reciente).

Determinar el nivel de acceso de cada resultado:
- **Nivel 1** (`propio`): `hs.unidad_organizativa_id` coincide con la UO del
  profesional autenticado.
- **Nivel 2** (`otra_uo`): existe Historia Social en otra UO, sin colectivo protegido.
- **Nivel 3** (`protegido`): el ciudadano tiene un colectivo con
  `requiere_aprobacion_previa = true` activo.
- Sin Historia Social en ninguna UO: nivel 1 si se crearía en la UO propia.

### 4.3 Enmascaramiento de datos en resultados protegidos (Nivel 3)

Para ciudadanos de colectivo protegido:
- El domicilio no se devuelve en la consulta (no mostrar ni enmascarar:
  simplemente no recuperar el campo).
- NI-HSU-CM no se muestra (misma regla que en el resto: solo si hay HS).
- El nombre sí se muestra — la restricción es sobre el contenido de la HS,
  no sobre la existencia del ciudadano.

### 4.4 Acceso a HS de otras UOs (Nivel 2)

Al hacer clic en "Ver Historia Social" de un resultado de nivel 2, antes de
navegar, registrar en `audits` (trait `Auditable`) el acceso con:
- `auditable_type = 'HistoriaSocial'`
- `auditable_id = $hs->id`
- `event = 'acceso_nivel2'`
- `user_id = Auth::id()`

Luego navegar normalmente a la Historia Social.

### 4.5 Modal de solicitud de acceso (Nivel 3)

Al hacer clic en "Solicitar acceso", abrir un modal con:
- Aviso del tipo de protección (texto del campo `descripcion` del colectivo).
- Campo `justificacion` (textarea, obligatorio, mínimo 20 caracteres).
- Botón "Enviar solicitud" que crea un registro en `accesos_protegidos_solicitudes`
  (si la tabla no existe, crear la migración siguiendo el modelo
  `docs/modulo-usuarios-permisos.md`).

```php
public function solicitarAcceso(int $ciudadanoId, string $justificacion): void
```

La solicitud va al supervisor de la UO responsable de la Historia Social
(crear alerta en `alertas` con `tipo = 'alerta'`, destinatario el supervisor).

### 4.6 Pie: dar de alta nuevo ciudadano

Al final del listado de resultados, mostrar siempre el bloque:

```
¿No está la persona que buscas?
[Dar de alta nuevo ciudadano]  ← disabled, title="Pendiente de implementación"
```

### 4.7 Vista Blade

Ver `docs/front/ui-intervencion.md`, sección 6 para los detalles visuales de
cada tipo de resultado.

---

## Paso 5 — Componente: BuzonPage

Este componente vive en `Modules/Mensajes`. Verificar si ya existe antes de crearlo.

### 5.1 Estructura de pestañas

Tres pestañas con estado en el componente:

```php
public string $pestana = 'alertas';  // 'alertas' | 'avisos' | 'mensajes'
public ?int $itemSeleccionado = null;
```

### 5.2 Pestaña Alertas

**Lista:** `alertas` donde `usuario_id = Auth::id()` y `tipo = 'alerta'`,
ordenadas por `vence_en ASC` (más urgente primero).

**Detalle:**
- Banner coral con el tiempo restante hasta `vence_en`.
  Calcular con `$alerta->vence_en->diffForHumans(now(), true)`.
- Botón "Reconocer alerta": ejecuta `reconocerAlerta(int $alertaId)`.

```php
public function reconocerAlerta(int $alertaId): void
{
    $alerta = Alerta::where('usuario_id', Auth::id())->findOrFail($alertaId);
    $alerta->update(['reconocida_en' => now()]);
    $this->itemSeleccionado = null;
    // Disparar evento para que el sidebar actualice su contador
    $this->dispatch('alerta-reconocida');
}
```

- Botón "Ir al contexto": navega a `$alerta->origen_url` (campo calculado a
  partir de `origen_type` y `origen_id` de la alerta — consultar
  `docs/modulo-mensajes.md` para la resolución polimórfica).

### 5.3 Pestaña Avisos

**Lista:** `alertas` donde `usuario_id = Auth::id()` y `tipo = 'aviso'`,
ordenadas por `created_at DESC`.

**Detalle:** sin banner de urgencia. Única acción: "Marcar como leído"
(actualiza `reconocida_en`).

### 5.4 Pestaña Mensajes

**Lista:** hilos donde el usuario participa (`mensajes_participantes`),
ordenados por `updated_at DESC`. Badge de no leído si `leido_en IS NULL`.

**Detalle:** hilo de mensajes con burbujas diferenciadas (propios/ajenos).
Ver `docs/modulo-mensajes.md` para la estructura de `mensajes_hilos` y `mensajes`.

**Área de composición:**

```php
public string $respuesta = '';
public function enviarRespuesta(int $hiloId): void
```

Dos botones auxiliares:
- **Adjuntar documento**: abre el file picker del navegador. El adjunto se
  sube con `spatie/laravel-medialibrary` al modelo `Mensaje`.
- **Enlazar expediente**: abre un mini-buscador de ciudadanos (reutilizar la
  lógica de `BuscarCiudadanoPage` como sub-componente o modal).
  Crea un registro en `mensajes_referencias_ciudadano`.

### 5.5 Botón "Nuevo mensaje"

```php
public bool $modalNuevoMensaje = false;
```

El modal contiene:
- Buscador de destinatario por nombre (o por rol + UO).
- Campo de asunto.
- Campo de cuerpo.
- Botón "Enviar" que crea el hilo y el primer mensaje.

---

## Paso 6 — Tests

### MisCasosPage

```
TF-LW-CAS-01 — Lista solo ciudadanos con profesional_responsable_id = Auth::id()
TF-LW-CAS-02 — Filtro 'vencido' devuelve solo casos con fecha_siguiente_seguimiento < today()
TF-LW-CAS-03 — Filtro PISO 'sin' devuelve solo ciudadanos sin plan general activo
TF-LW-CAS-04 — Cambiar filtro resetea la paginación a página 1
TF-LW-CAS-05 — La cabecera de la columna PISO usa el valor de catalogos_sistema
TF-LW-CAS-06 — La paginación muestra la página 2 si hay más de 10 resultados
TF-LW-CAS-07 — Orden por defecto es vencidos primero
```

### BuscarCiudadanoPage

```
TF-LW-BUS-01 — Buscar por nombre devuelve ciudadanos que coinciden
TF-LW-BUS-02 — Resultado de propia UO muestra nivel 1 (botón "Ir a Historia Social")
TF-LW-BUS-03 — Resultado de otra UO muestra nivel 2 (botón con nota de acceso registrado)
TF-LW-BUS-04 — Resultado de colectivo protegido muestra nivel 3 (botón "Solicitar acceso")
TF-LW-BUS-05 — Ciudadano protegido no expone domicilio en los resultados
TF-LW-BUS-06 — Acceso nivel 2 registra entrada en audits con event = 'acceso_nivel2'
TF-LW-BUS-07 — Solicitar acceso con justificacion vacía no crea la solicitud
TF-LW-BUS-08 — Solicitar acceso crea una alerta para el supervisor de la UO responsable
TF-LW-BUS-09 — NI-HSU-CM solo aparece en resultados que tienen Historia Social
TF-LW-BUS-10 — El pie "Dar de alta" es siempre visible aunque no haya resultados
```

### BuzonPage

```
TF-LW-BUZ-01 — La pestaña Alertas muestra solo las alertas del usuario autenticado
TF-LW-BUZ-02 — Reconocer una alerta rellena reconocida_en y la saca de la lista
TF-LW-BUZ-03 — No se puede reconocer la alerta de otro usuario
TF-LW-BUZ-04 — La pestaña Mensajes muestra solo los hilos en los que participa el usuario
TF-LW-BUZ-05 — Enviar respuesta añade el mensaje al hilo con el user_id correcto
TF-LW-BUZ-06 — Los avisos aparecen en la pestaña Avisos, no en Alertas
```

Ejecutar al terminar:

```bash
php artisan test --filter="MisCasosPage|BuscarCiudadanoPage|BuzonPage"
php artisan test --filter=Intervencion
php artisan test 2>&1 | tail -5
```

---

## Lo que NO hay que hacer

- No implementar la pantalla del ciudadano ni las herramientas de trabajo
  (es Entrega 3).
- No modificar la agenda ni el layout base de la Entrega 1.
- No implementar la integración real con AutoFirma ni la carpeta ciudadana.
- No crear rutas API.
- No implementar el módulo de Ciudadanía completo: solo las consultas de
  búsqueda definidas en el paso 4. El alta de ciudadano (`solicitarAcceso` aparte)
  queda como botón desactivado.
- No inventar campos en `ciudadanos` que no estén en la documentación. Si falta
  algún campo necesario, anotarlo con `// TODO:` y continuar.

---

## Checklist de finalización

- [ ] `php artisan test --filter="MisCasosPage|BuscarCiudadanoPage|BuzonPage"` pasa los 23 tests
- [ ] `php artisan test --filter=Intervencion` sigue pasando los 35 tests de backend
- [ ] `php artisan test` no introduce fallos nuevos
- [ ] Las rutas `/intervencion/casos`, `/intervencion/mensajes` y `/intervencion/buscar` responden
- [ ] El filtro de seguimiento funciona y resetea la paginación
- [ ] La cabecera "PISO" usa el parámetro configurable
- [ ] El acceso Nivel 2 queda registrado en `audits`
- [ ] El modal de acceso Nivel 3 requiere justificación
- [ ] Reconocer una alerta la elimina del listado sin recargar la página
- [ ] El botón "Dar de alta" está desactivado con `disabled`
- [ ] Entrada añadida en `CHANGELOG.md`
