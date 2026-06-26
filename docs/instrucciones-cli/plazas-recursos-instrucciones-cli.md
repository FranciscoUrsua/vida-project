# Instrucciones CLI — UI Plazas y recursos

> **Referencia funcional:** `docs/front/ui-plazas-recursos.md`
> **Módulos afectados:** `Modules/Intervencion` · `Modules/Supervision` · `Modules/Centros`
> **Roles implicados:** `intervencion` (TSR de primaria) · `intervencion` (TS del centro) · `supervision`

---

## 0. Obligaciones de arquitectura frontend

Las mismas que en `supervision-ui-instrucciones-cli.md §0`. Se repiten los puntos críticos:

- **Bootstrap 5.3 como primitiva.** Usar `btn`, `table`, `modal`, `form-control`, `badge`, `nav-tabs`, `list-group`, grid y spacing sin reinventarlos.
- **`op-*` para componentes de producto reutilizables** que Bootstrap no modela. Reutilizar los existentes; añadir en `_op-components.scss` solo si es genuinamente nuevo.
- **No escribir en `app-operativo.css`.** CSS nuevo va en `_op-components.scss` (si reutilizable) o en el SCSS de la pantalla (si específico), con comentario que justifica por qué Bootstrap no era suficiente.
- **Iconos: solo Heroicons** via `<x-heroicon-o-nombre class="icon-16" aria-hidden="true"/>`. Sin Lucide, Bootstrap Icons ni CDNs.
- **No estilos inline estructurales** en Blade. Solo para valores dinámicos inevitables (porcentajes calculados, coordenadas, etc.).
- **No clases Tailwind** en la superficie operativa Livewire.

---

## 1. Migraciones necesarias

Antes de implementar cualquier componente Livewire, verificar y crear si no existen:

### 1.1 Campo `compromiso_id` en `prescripciones`

```php
// Verificar si existe. Si no:
Schema::table('prescripciones', function (Blueprint $table) {
    $table->foreignId('compromiso_id')
          ->nullable()
          ->constrained('compromisos_ayuntamiento')
          ->nullOnDelete()
          ->after('plan_intervencion_id');
});
```

### 1.2 Campo `criterio_territorial` en `colecciones_plazas`

```php
Schema::table('colecciones_plazas', function (Blueprint $table) {
    $table->boolean('criterio_territorial')->default(false)->after('modo_acceso');
});
```

Si ya existe con otro nombre, documentarlo en SESSION.md y usar el nombre existente en todo el código nuevo.

### 1.3 Soft deletes en `profesionales` (si no existe)

```php
Schema::table('profesionales', function (Blueprint $table) {
    $table->softDeletes();
});
```

---

## 2. Nuevos componentes Livewire

### 2.1 `PrescribirRecursoModal` — Módulo Intervencion

**Archivo:** `Modules/Intervencion/app/Http/Livewire/PrescribirRecursoModal.php`

Componente modal de tres pasos montado dentro de `CiudadanoPage`. Propiedades de estado:

```php
public int $paso = 1;             // 1 | 2 | 3
public ?string $tipoRecurso = null;
public ?int $destinoId = null;    // ColeccionPlazas.id o Red.id
public string $tipoDestino = '';  // 'coleccion_plazas' | 'red'
public ?int $compromisoId = null; // nullable, vínculo opcional con plan
public string $notas = '';
```

Computed properties:

```php
#[Computed] public function opcionesDestino(): Collection
// Aplica heurística §2 del documento funcional:
// 1. Si existe red con el tipo de recurso → devuelve redes
// 2. Si no → devuelve ColeccionPlazas individuales
// Si criterio_territorial = true Y ciudadano tiene dirección:
//   ordenar por distancia (haversine sobre lat/lng de Centro)
// Si criterio_territorial = true Y ciudadano es PSH sin dirección:
//   ordenar sin criterio geográfico

#[Computed] public function compromisosSugeridos(): Collection
// Compromisos del Ayuntamiento del plan activo del ciudadano
// cuya prestación es compatible con $tipoRecurso
// Devuelve colección vacía si no hay plan activo

#[Computed] public function hayPlazaDisponible(): bool
// true si destino seleccionado tiene plazas libres ahora
```

Métodos públicos:

```php
public function avanzar(): void      // valida paso actual, incrementa $paso
public function retroceder(): void   // decrementa $paso sin validar
public function confirmar(): void    // crea Prescripcion + ListaEspera si aplica + Apunte
public function cancelar(): void     // resetea estado, cierra modal
```

`confirmar()` debe ejecutarse en una transacción de base de datos. En caso de error, rollback completo. Nunca crear el Apunte si la Prescripcion falló, ni al revés.

**Vista:** `Modules/Intervencion/resources/views/livewire/prescribir-recurso-modal.blade.php`

El modal usa el componente Bootstrap `modal` estándar. Los tres pasos se muestran con `@if ($paso === N)`, no con JavaScript. El indicador de pasos es un `nav` Bootstrap con `nav-pills` o similar, de solo lectura (los pasos no son navegables directamente, solo con los botones «Anterior» / «Siguiente»).

---

### 2.2 `RecursosPage` — Módulo Intervencion

**Archivo:** `Modules/Intervencion/app/Http/Livewire/RecursosPage.php`

**Ruta:** `/intervencion/recursos` (solo visible si `profesional->centro->tiene_plazas = true`)

Propiedades:

```php
public string $pestana = 'pendientes'; // 'pendientes' | 'activas'
public array $filtros = [];
```

Computed properties:

```php
#[Computed] public function prescripcionesPendientes(): LengthAwarePaginator
// Prescripciones con estado 'pendiente' | 'en_lista_espera'
// cuyo destino_id corresponde a una ColeccionPlazas del centro del profesional
// o a una Red de la que forma parte el centro
// Ordenadas por ListaEspera.posicion ASC, luego por fecha_prescripcion ASC

#[Computed] public function prescripcionesActivas(): LengthAwarePaginator
// Prescripciones con estado 'asignada' | 'activa'
// del mismo ámbito

#[Computed] public function previsionLiberaciones(): Collection
// Prescripciones activas con fecha_fin no nula en los próximos 30 días
// agrupadas por tipo_plaza
// Para el bloque de previsión en la cabecera de la pestaña Pendientes
```

Métodos:

```php
public function abrirModalAsignacion(int $prescripcionId): void
public function moverEnLista(int $prescripcionId, int $nuevaPosicion): void
// Registra el cambio en tabla de auditoría de lista de espera
public function cancelarPrescripcion(int $prescripcionId, string $motivo): void
public function marcarActiva(int $prescripcionId, string $fechaInicio): void
public function marcarFinalizada(int $prescripcionId): void
```

---

### 2.3 `AsignarPlazaModal` — Módulo Intervencion

Componente modal hijo de `RecursosPage`. Se abre desde `abrirModalAsignacion()`.

Muestra para la prescripción seleccionada:
- Datos básicos del ciudadano (nombre, edad, datos de contacto) y enlace «Ver ficha completa» → `ciudadania.ciudadano.ficha` en nueva pestaña.
- Notas del TSR prescriptor.
- Tabla de plazas disponibles del tipo solicitado (y tipos compatibles), con columnas: nombre/id de plaza, espacio, tipo, estado, fecha estimada de liberación si `ocupada`.

```php
public function asignar(int $plazaId, ?string $notaAsignacion = null): void
// Verifica que la plaza pertenece al centro del profesional autenticado
// Si tipo de espacio difiere del solicitado: registra la diferencia en notas
// Transacción:
//   Prescripcion: estado → 'asignada', plaza_id, fecha_asignacion = now()
//   Plaza: estado → 'ocupada'
//   ListaEspera: estado → 'asignada'
//   Alerta aviso → TSR de referencia del ciudadano
```

---

## 3. Modificaciones en componentes existentes

### 3.1 `CiudadanoPage` — añadir herramienta «Prescribir recurso»

Condición de visibilidad del botón: Historia Social abierta (`historia->cerrada_en === null`).

Añadir en la banda de recursos activos (nueva sección, bajo la banda del PISO):

```blade
{{-- Banda de recursos prescritos --}}
@if($prescripcionesActivas->isNotEmpty())
<div class="op-section mt-3">
    <h3 class="op-section__title">Recursos prescritos</h3>
    @foreach($prescripcionesActivas as $prescripcion)
        {{-- chip de estado + tipo + destino + acción cancelar --}}
    @endforeach
</div>
@endif
```

Computed property nueva en `CiudadanoPage`:

```php
#[Computed] public function prescripcionesActivas(): Collection
// Prescripciones del ciudadano con estado en:
// pendiente | en_lista_espera | asignada | activa
```

### 3.2 Guard de cierre de Historia Social

Verificar que el servicio o método que cierra una Historia Social (`HistoriaSocial::cerrar()` o similar) incluye la restricción:

```php
if ($this->prescripciones()
         ->whereIn('estado', ['pendiente','en_lista_espera','asignada','activa'])
         ->exists()) {
    throw new \DomainException(
        'No se puede cerrar la historia social con prescripciones activas.'
    );
}
```

Si este guard no existe, crearlo. Es una restricción de dominio crítica.

### 3.3 Sidebar de Intervencion — ítem condicional «Recursos»

Añadir al `Sidebar.php` del módulo Intervencion:

```php
#[Computed] public function tienePlazas(): bool
// true si el profesional autenticado está adscrito a un centro con tiene_plazas = true
// o a una red con centros que tienen plazas
```

En `sidebar.blade.php`, añadir el ítem entre «Mis casos» y «Buscar ciudadano»:

```blade
@if($this->tienePlazas)
<li class="op-nav__item">
    <a href="{{ route('intervencion.recursos') }}"
       class="op-nav__link @active('intervencion.recursos')">
        <x-heroicon-o-building-office-2 class="icon-16" aria-hidden="true"/>
        Recursos
    </a>
</li>
@endif
```

---

## 4. Tests funcionales

> **Convención:** PHPUnit `#[Test]`. Base de datos `vida_testing` (PostgreSQL). Archivos en `Modules/Intervencion/tests/Feature/Livewire/`. Incluir siempre caso negativo para restricciones de seguridad y dominio.

---

### Grupo A — Guard de Historia Social (TF-REC-A)

**TF-REC-A01 — No se puede cerrar una Historia Social con prescripción activa**
- Dado un ciudadano con Historia Social abierta y una prescripción en estado `asignada`.
- Cuando se intenta cerrar la Historia Social.
- Entonces se lanza `DomainException` y `historia->cerrada_en` sigue siendo `null`.

**TF-REC-A02 — Se puede cerrar una Historia Social sin prescripciones activas**
- Dado un ciudadano con Historia Social abierta y prescripciones solo en estado `finalizada` o `cancelada`.
- Cuando se cierra la Historia Social.
- Entonces `historia->cerrada_en` no es `null`.

**TF-REC-A03 — Guard aplica a todos los estados activos**
- Dado un ciudadano con una prescripción en estado `en_lista_espera`.
- Cuando se intenta cerrar la Historia Social.
- Entonces se lanza `DomainException`.

---

### Grupo B — Prescripción: creación desde CiudadanoPage (TF-REC-B)

**TF-REC-B01 — Botón «Prescribir recurso» visible con Historia Social abierta**
- Dado un ciudadano con Historia Social abierta.
- Cuando se monta `CiudadanoPage`.
- Entonces el DOM contiene el botón de la herramienta «Prescribir recurso».

**TF-REC-B02 — Botón «Prescribir recurso» no visible con Historia Social cerrada**
- Dado un ciudadano con `historia->cerrada_en` no nulo.
- Cuando se monta `CiudadanoPage`.
- Entonces el botón no está presente en el DOM.

**TF-REC-B03 — Prescripción con plaza disponible queda en estado asignada**
- Dado un ciudadano con Historia Social abierta.
- Y una `ColeccionPlazas` con `modo_acceso = prescripcion_directa` y al menos una plaza en estado `libre`.
- Cuando se completa el modal `PrescribirRecursoModal` seleccionando esa colección y se confirma.
- Entonces existe una `Prescripcion` con `estado = asignada` y `plaza_id` no nulo.
- Y la plaza seleccionada tiene `estado = ocupada`.

**TF-REC-B04 — Prescripción sin plaza disponible queda en lista de espera**
- Dado una `ColeccionPlazas` con todas las plazas en estado `ocupada` o `mantenimiento`.
- Cuando se confirma la prescripción hacia esa colección.
- Entonces `Prescripcion.estado = en_lista_espera`.
- Y existe un registro `ListaEspera` con `estado = activa` vinculado a esa prescripción.

**TF-REC-B05 — Prescripción crea apunte en la Historia Social**
- Dado el escenario del test B03 o B04.
- Cuando se confirma la prescripción.
- Entonces existe un `Apunte` de tipo `derivacion` en la Historia Social del ciudadano con referencia a la prescripción.

**TF-REC-B06 — Prescripción sin plan activo no muestra selector de compromisos**
- Dado un ciudadano sin plan de intervención en estado `activo` o `borrador`.
- Cuando se llega al paso 3 del modal.
- Entonces el DOM no contiene el selector «Vincular a un compromiso del plan».

**TF-REC-B07 — Prescripción con plan activo compatible muestra selector de compromisos**
- Dado un ciudadano con plan activo que tiene un compromiso del Ayuntamiento con prestación compatible con el tipo de recurso seleccionado.
- Cuando se llega al paso 3 del modal.
- Entonces el DOM contiene el selector con ese compromiso como opción.

**TF-REC-B08 — Vincular prescripción a compromiso guarda compromiso_id**
- Dado el escenario del test B07.
- Cuando el TSR selecciona el compromiso y confirma.
- Entonces `Prescripcion.compromiso_id` apunta al compromiso seleccionado.
- Y el estado del compromiso no ha cambiado (el plan no se modifica).

**TF-REC-B09 — Prescripción sin vínculo a compromiso tiene compromiso_id nulo**
- Dado el escenario del test B07 pero el TSR no selecciona ningún compromiso.
- Cuando confirma.
- Entonces `Prescripcion.compromiso_id` es null.

**TF-REC-B10 — Fallo en la creación del apunte hace rollback de la prescripción**
- Dado que `Apunte::create()` lanza una excepción (simular con mock).
- Cuando se confirma la prescripción.
- Entonces no existe ninguna `Prescripcion` nueva en la base de datos.
- Y no existe ningún `ListaEspera` nuevo.

**TF-REC-B11 — TSR no puede prescribir hacia una colección de otro ámbito territorial**

> Test de seguridad. El sistema filtra opciones, pero verificar también en el servidor.

- Dado una `ColeccionPlazas` que no aparece en las opciones del ciudadano (fuera de ámbito).
- Cuando se llama a `confirmar()` con ese `destino_id` directamente (sin pasar por el modal).
- Entonces la operación es rechazada con error de autorización y no se crea ninguna `Prescripcion`.

---

### Grupo C — Heurística de destino (TF-REC-C)

**TF-REC-C01 — Con red disponible, se ofrecen redes, no colecciones individuales**
- Dado un tipo de recurso para el que existe una `Red` activa con centros miembros que tienen el tipo de colección.
- Cuando se calculan `opcionesDestino` en el modal.
- Entonces los resultados son instancias de `Red`, no de `ColeccionPlazas`.

**TF-REC-C02 — Sin red, se ofrecen colecciones individuales**
- Dado un tipo de recurso sin ninguna `Red` activa que lo cubra.
- Cuando se calculan `opcionesDestino`.
- Entonces los resultados son instancias de `ColeccionPlazas`.

**TF-REC-C03 — Con criterio territorial y ciudadano con dirección, resultados ordenados por proximidad**
- Dado dos centros: `$cercano` a 0.5 km y `$lejano` a 5 km del domicilio del ciudadano.
- Y ambos con `ColeccionPlazas` de `criterio_territorial = true` del tipo requerido.
- Cuando se calculan `opcionesDestino`.
- Entonces `$cercano` aparece antes que `$lejano`.

**TF-REC-C04 — Con criterio territorial y ciudadano PSH sin dirección, no se ordena por distancia**
- Dado un ciudadano PSH sin `direccion` registrada.
- Y colecciones con `criterio_territorial = true`.
- Cuando se calculan `opcionesDestino`.
- Entonces no se lanza ninguna excepción y los resultados se devuelven sin orden geográfico.

**TF-REC-C05 — Sin criterio territorial, no se calcula distancia aunque el ciudadano tenga dirección**
- Dado colecciones con `criterio_territorial = false`.
- Cuando se calculan `opcionesDestino`.
- Entonces no se ejecuta ninguna query de distancia (verificar que no hay cálculo haversine en el SQL generado).

---

### Grupo D — RecursosPage: lista de espera y asignación (TF-REC-D)

**TF-REC-D01 — RecursosPage solo visible para TS adscrito a centro con plazas**
- Dado un profesional adscrito a un centro con `tiene_plazas = false`.
- Cuando accede a `/intervencion/recursos`.
- Entonces recibe 404 o es redirigido.

**TF-REC-D02 — RecursosPage lista prescripciones del ámbito del centro**
- Dado 3 prescripciones pendientes hacia colecciones del centro del TS y 2 hacia otros centros.
- Cuando se monta `RecursosPage`.
- Entonces la pestaña «Pendientes» muestra exactamente 3 prescripciones.

**TF-REC-D03 — Asignación de plaza actualiza todos los estados en transacción**
- Dado una prescripción `en_lista_espera` y una plaza `libre` del tipo correcto en el centro.
- Cuando el TS llama a `asignar($plazaId)`.
- Entonces:
  - `Prescripcion.estado = asignada`
  - `Prescripcion.plaza_id = $plazaId`
  - `Prescripcion.fecha_asignacion` no es null
  - `Plaza.estado = ocupada`
  - `ListaEspera.estado = asignada`
  - Existe una `Alerta` de tipo `aviso` hacia el TSR de referencia del ciudadano.

**TF-REC-D04 — Asignación de tipo de espacio diferente al solicitado no se bloquea**
- Dado una prescripción para plaza `pernocta` en habitación individual.
- Y solo disponible una plaza en espacio de tipo `matrimonial`.
- Cuando el TS asigna esa plaza con nota justificativa.
- Entonces la asignación se completa y la nota queda registrada en `Prescripcion.notas`.

**TF-REC-D05 — TS no puede asignar plaza de otro centro**
- Dado una plaza perteneciente a un centro distinto al del TS autenticado.
- Cuando se llama a `asignar($plazaId)`.
- Entonces la operación es rechazada con error de autorización.
- Y `Plaza.estado` no cambia.

**TF-REC-D06 — Reordenar lista de espera actualiza posiciones y registra auditoría**
- Dado 3 prescripciones en lista de espera con posiciones 1, 2, 3.
- Cuando se llama a `moverEnLista($prescripcion3Id, 1)`.
- Entonces la prescripción 3 pasa a posición 1 y las demás se recalculan.
- Y existe un registro de auditoría con la posición anterior y el profesional que realizó el cambio.

**TF-REC-D07 — Previsión de liberaciones solo muestra plazas con fecha_fin próxima**
- Dado una prescripción activa con `fecha_fin` en 10 días y otra con `fecha_fin` en 60 días.
- Cuando se calcula `previsionLiberaciones`.
- Entonces solo aparece la prescripción de 10 días (umbral: 30 días).

**TF-REC-D08 — Cancelar una prescripción libera la plaza si estaba asignada**
- Dado una prescripción `asignada` con `plaza_id` no nulo.
- Cuando se llama a `cancelarPrescripcion($id, $motivo)`.
- Entonces `Prescripcion.estado = cancelada`.
- Y `Plaza.estado = libre`.

**TF-REC-D09 — Cancelar una prescripción en lista de espera no afecta a ninguna plaza**
- Dado una prescripción `en_lista_espera` sin `plaza_id`.
- Cuando se cancela.
- Entonces `Prescripcion.estado = cancelada` y ninguna plaza cambia de estado.

---

### Grupo E — Banda de recursos en CiudadanoPage (TF-REC-E)

**TF-REC-E01 — Banda de recursos muestra prescripciones activas del ciudadano**
- Dado un ciudadano con una prescripción `asignada` y una `finalizada`.
- Cuando se monta `CiudadanoPage`.
- Entonces la banda de recursos muestra 1 prescripción (solo la activa).

**TF-REC-E02 — Cancelar prescripción desde la banda actualiza la vista**
- Dado un ciudadano con una prescripción `en_lista_espera`.
- Cuando el TSR la cancela desde la banda.
- Entonces la prescripción desaparece de la banda y `Prescripcion.estado = cancelada`.

---

## 5. Checklist de implementación

Para cada componente, antes de cerrar la tarea:

- [ ] Todas las operaciones de escritura están en transacciones de base de datos.
- [ ] El guard de cierre de Historia Social existe y está cubierto por tests A01–A03.
- [ ] `asignar()` en `AsignarPlazaModal` verifica que la plaza pertenece al centro del profesional autenticado.
- [ ] `confirmar()` en `PrescribirRecursoModal` verifica que el destino seleccionado aparece en `opcionesDestino` para ese ciudadano (no confiar solo en el estado del componente).
- [ ] El ítem «Recursos» en el sidebar de Intervención no aparece para profesionales sin plazas.
- [ ] Ninguna vista Blade usa estilos inline estructurales, clases Tailwind ni iconos que no sean Heroicons.
- [ ] No se ha escrito nada en `app-operativo.css`.
- [ ] Los campos `compromiso_id` y `criterio_territorial` existen en la base de datos antes de ejecutar los tests.
- [ ] Ejecutar al finalizar: `php artisan test --filter=Recursos` y `php artisan test --filter=Prescribir`.
- [ ] No ejecutar `php artisan migrate:fresh` salvo instrucción explícita.

---

## 6. Notas de implementación

**Cálculo de distancia haversine.** No usar una librería externa. La fórmula es simple y puede implementarse directamente en SQL (PostgreSQL soporta aritmética trigonométrica en el SELECT). Si en el futuro se activa PostGIS, esta implementación se reemplaza sin cambiar la interfaz del servicio.

**Auditoría de reordenamiento de lista de espera.** Crear una tabla `lista_espera_movimientos` con `lista_espera_id`, `posicion_anterior`, `posicion_nueva`, `profesional_id`, `created_at`. Si ya existe una tabla genérica de auditoría (`audits`) que cubra este caso, usarla en su lugar y documentarlo.

**Alerta al TSR tras asignación.** Usar `Alerta::create()` con `destinatario_type = 'usuario'` y `destinatario_usuario_id` del TSR activo del ciudadano en ese momento. El TSR activo se obtiene de `HistoriaSocial::profesionalActivo()` o equivalente. Si el ciudadano no tiene TSR activo (Historia Social sin profesional asignado), no generar alerta y registrar un log de advertencia.

**`opcionesDestino` y rendimiento.** Esta computed property puede ser costosa si hay muchos centros. Añadir `#[Computed(persist: true)]` con TTL corto (60 s) o calcularla solo cuando el paso 1 esté completado. No precalcularla al montar el componente.
