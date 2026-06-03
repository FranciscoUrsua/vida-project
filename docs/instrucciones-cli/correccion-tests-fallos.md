# correccion-tests-fallos.md

Instrucciones para corregir los 17 tests que fallan en la suite actual.
Generadas a partir del análisis de `resultado_tests.txt`.

---

## Contexto

La suite tiene 488 tests. Resultado actual: **17 fallos, 6 pendientes, 464 pasados**.
Los fallos se agrupan en tres bloques independientes con causas distintas.
Abordarlos en el orden indicado: el bloque 1 es el de mayor retorno por esfuerzo.

---

## Bloque 1 — `AutenticacionTest` (7 tests)

### Tests afectados

```
⨯ tf auth 03 login exitoso con email y password correctos
⨯ tf auth 11 el throttle es por email e ip no solo por ip
⨯ tf auth 15 usuario autenticado puede acceder a inicio
⨯ tf auth 16 el nombre del usuario aparece en la ui
⨯ tf auth 17 las iniciales del avatar son las dos primeras letras del name
⨯ tf auth 19 usuario recurrente va directamente a inicio
⨯ tf auth 21 completar onboarding marca primer acceso como false
```

### Causa raíz

El usuario de fixture `juana@madrid.es` **no tiene ningún rol asignado**.
Tras el login, el middleware redirige a `/sin-rol` en lugar de a `route('inicio')`.
Los tests 15, 16 y 17 reciben 302 en lugar de 200 por la misma razón: al hacer
`$this->actingAs($this->usuario)->get('/')` sin rol, la app redirige.

### Diagnóstico previo obligatorio

Antes de tocar código, verificar en `tests/Feature/Auth/AutenticacionTest.php`:

1. Cómo se crea `$this->usuario` en `setUp()`.
2. Si hay algún `assignRole(...)` o similar. Lo más probable es que no lo haya,
   o que use un factory que no asigna rol.

### Corrección

En el método `setUp()` de `AutenticacionTest`, asignar un rol operativo al usuario
después de crearlo. El rol concreto depende de los que existan en el sistema
(consultar `database/seeders/RolSeeder.php` o equivalente), pero debe ser cualquiera
que no dispare la redirección a `/sin-rol`. Ejemplo orientativo:

```php
protected function setUp(): void
{
    parent::setUp();

    $this->usuario = User::factory()->create([
        'name'          => 'Juana López García',
        'email'         => 'juana@madrid.es',
        'password'      => 'secreto123',
        'primer_acceso' => false,
    ]);

    // Sin rol, el middleware redirige a /sin-rol y los tests de acceso fallan.
    // Asignar el rol mínimo que permita acceder a route('inicio').
    $this->usuario->assignRole('consulta-basica'); // ajustar al nombre real del rol
}
```

Si el usuario de los tests de throttle (tf-auth-11) y onboarding (tf-auth-19, 21)
se crea de forma distinta dentro del propio test, aplicar el mismo `assignRole`
justo después de cada `User::factory()->create(...)` en esos métodos.

### Verificación

```bash
php artisan test tests/Feature/Auth/AutenticacionTest.php
```

Deben pasar los 7 tests ahora rojos. El test `tf auth 18` (usuario nuevo ve onboarding)
debe seguir pasando — crea intencionalmente un usuario sin `primer_acceso = false`,
no se le asigna rol porque se espera que vea onboarding antes de llegar a inicio.

---

## Bloque 2 — `PrestacionFilamentResourceTest` (9 tests)

### Tests afectados

```
⨯ un admin puede listar prestaciones en filament
⨯ el listado de filament filtra correctamente por tipo prestacion
⨯ el listado de filament filtra correctamente por activa
⨯ un admin puede crear una prestacion desde filament
⨯ el formulario de filament rechaza una prestacion sin nombre
⨯ el formulario de filament rechaza un codigo duplicado
⨯ un admin puede editar una prestacion desde filament
⨯ editar desde filament genera una version en versiones
⨯ el toggle de activa en el listado cambia el estado de la prestacion
```

### Errores observados

```
Error: Call to a member function getTableRecordKey() on null
Error: Call to a member function parseTableFilterName() on null
Error: Call to a member function getDefaultTestingSchemaName() on null
InvalidArgumentException: Invalid Livewire snapshot structure
```

Todos apuntan a que `$this->instance()` devuelve `null` — el componente Livewire/Filament
**no monta correctamente** en el contexto de test.

### Diagnóstico previo obligatorio

Abrir `Modules/Prestaciones/tests/Feature/PrestacionFilamentResourceTest.php` y revisar:

1. **Qué componente se está montando.** Buscar llamadas a `livewire(...)`.
   Verificar que la clase referenciada existe y es la correcta
   (p.ej. `PrestacionResource\Pages\ListPrestaciones`).

2. **Qué panel Filament usa el test.** Verificar si el test extiende alguna clase base
   con `$this->withPanel(...)` o similar, y que ese panel esté configurado en test.

3. **Si el recurso está registrado en el panel.**
   Abrir el `PanelProvider` del panel admin (probablemente `app/Providers/Filament/AdminPanelProvider.php`)
   y comprobar que `PrestacionResource::class` aparece en `->resources([...])`.
   Dado que los recursos Filament están centralizados en `app/Filament/Resources/` por
   decisión arquitectónica, verificar que el recurso de Prestaciones sigue esa convención
   o tiene un registro explícito.

4. **Si el usuario admin del test tiene `canAccessPanel()` = true.**
   El método `canAccessPanel(Panel $panel)` del modelo `User` determina quién entra al panel.
   El usuario de test debe tener el rol que lo habilita (normalmente `adm-sistema`).

### Correcciones posibles (aplicar la que corresponda según diagnóstico)

**A) El recurso no está registrado en el panel:**

```php
// En app/Providers/Filament/AdminPanelProvider.php, dentro de panel()
->resources([
    // ... otros recursos ...
    \App\Filament\Resources\PrestacionResource::class, // ajustar namespace
])
```

**B) El usuario del test no tiene acceso al panel:**

```php
// En setUp() del test
$this->admin = User::factory()->create();
$this->admin->assignRole('adm-sistema'); // rol con canAccessPanel = true
```

**C) El componente montado no es el correcto:**

Revisar las llamadas `livewire(...)` en el test. Si usa una clase inexistente
o movida, corregir a la ruta real del componente de página del recurso:

```php
// Ejemplo para el test de listado
livewire(\App\Filament\Resources\PrestacionResource\Pages\ListPrestaciones::class)
```

**D) Versión de Filament incompatible con la API de test:**

El error `getDefaultTestingSchemaName() on null` puede aparecer si el componente
montado es una página pero el test llama métodos de formulario sobre ella sin
especificar el esquema. En Filament v3+, algunos métodos de test requieren
indicar el nombre del formulario explícitamente:

```php
// En lugar de:
->fillForm([...])
// Usar:
->fillForm([...], 'form') // o el nombre que devuelva getDefaultTestingSchemaName()
```

Consultar `docs/instrucciones-cli/prestaciones-tests.md` para contexto adicional
sobre la arquitectura del módulo antes de hacer cambios.

### Verificación

```bash
php artisan test Modules/Prestaciones/tests/Feature/PrestacionFilamentResourceTest.php
```

Los 9 tests deben pasar. Ejecutar también los tests de consulta y modelo para
asegurar que no se ha roto nada colateral:

```bash
php artisan test --filter=Prestacion
```

---

## Bloque 3 — `CiudadanoPageTest` (1 test)

### Test afectado

```
⨯ ruta aplica policy y devuelve 403 sin acceso
```

### Error observado

```
Expected response status code [403] but received 404.

$this->actingAs($usuarioSinPermiso)
    ->get("/intervencion/ciudadano/{$this->historia->id}")
    ->assertForbidden();   // línea 131
```

### Causa raíz

El usuario sin permiso recibe **404 en lugar de 403**. Esto ocurre porque el
`GlobalScope` de autorización del módulo filtra los registros de `HistoriaSocial`
según la UO del usuario. Si el usuario no tiene acceso, el scope hace que el registro
"no exista" para Eloquent, y el Route Model Binding devuelve 404 antes de que la
policy pueda emitir un 403.

El comportamiento correcto es: el registro **existe** pero el usuario **no tiene permiso**.
La distinción 403/404 es semánticamente correcta y relevante para seguridad
(un 404 puede revelar o no la existencia del recurso según el contexto).

### Diagnóstico previo obligatorio

Abrir el controller o componente Livewire que maneja la ruta
`/intervencion/ciudadano/{historia}` y revisar:

1. Cómo se resuelve el parámetro `{historia}` — si usa Route Model Binding implícito
   o explícito.
2. Si el modelo `HistoriaSocial` tiene un `GlobalScope` que filtra por UO/autorización.
3. Si la policy `HistoriaSocialPolicy` tiene un método `view` que debería dispararse.

### Corrección

La solución es hacer que el binding encuentre siempre el registro (sin aplicar el
scope de autorización) y dejar que la policy emita el 403. Hay dos vías:

**Opción A — Binding sin scope (recomendada si el patrón es consistente en el proyecto):**

En el service provider del módulo o en la definición de la ruta, resolver el modelo
sin el global scope:

```php
// En RouteServiceProvider o en el módulo de Intervención
Route::bind('historia', function ($value) {
    return \Modules\Intervencion\Models\HistoriaSocial::withoutGlobalScopes()
        ->findOrFail($value);
});
```

Con esto, el binding siempre encuentra el registro. La policy `view` se ejecuta
y puede devolver `false`, lo que resulta en 403.

**Opción B — Abort explícito en el componente/controller:**

Si no se quiere modificar el binding, capturar el 404 en el componente y relanzar
como 403 cuando el registro existe pero el usuario no tiene acceso:

```php
$historia = HistoriaSocial::withoutGlobalScopes()->find($id);
if (! $historia) {
    abort(404);
}
$this->authorize('view', $historia); // lanza 403 si no tiene permiso
```

**Opción C — Verificar si el test está mal construido:**

Antes de cambiar código de producción, confirmar que `$usuarioSinPermiso` en el
test realmente NO debería tener acceso según las reglas de dominio. Si el test
asume un escenario que la policy no contempla, puede que sea el test quien
necesite ajuste, no la implementación.

Consultar `docs/principios-vida360.md` y la policy `HistoriaSocialPolicy` para
decidir qué opción es coherente con las decisiones arquitectónicas del proyecto.

### Verificación

```bash
php artisan test Modules/Intervencion/tests/Feature/Livewire/CiudadanoPageTest.php
```

El test que falla debe devolver 403. El resto del suite del componente (22 tests
que ya pasan) no debe verse afectado.

---

## Tests pendientes (…) — No son fallos, son deuda documentada

No requieren acción en esta sesión. Están registrados como `Pendiente` en los propios
tests con mensaje explicativo. Añadir al `BACKLOG.md` si aún no están:

| Test | Motivo pendiente |
|---|---|
| `el job no entrega si no supera la validacion k` | Requiere implementación del Job de extracción |
| `el job registra la version del perfil aplicado` | Requiere implementación del Job de extracción |
| `el k-anonimato no se aplica en tiempo real` | Test arquitectural — requiere decisión de diseño |
| `perfil personalizado con extracciones no puede eliminarse` | Pendiente de implementar restricción |
| `adm usuarios no puede adscribir usuarios a UO fuera de su ambito` | Pendiente de implementar scope de adscripción |
| `onboarding muestra nombre y centro del usuario` | Requiere componente UI de onboarding completo |

---

## Orden de ejecución recomendado

```bash
# 1. Bloque Auth (cambio en setUp, mayor retorno por esfuerzo)
php artisan test tests/Feature/Auth/AutenticacionTest.php

# 2. Bloque Filament (diagnóstico primero, luego corrección)
php artisan test Modules/Prestaciones/tests/Feature/PrestacionFilamentResourceTest.php

# 3. Bloque CiudadanoPage (un solo test, requiere decisión de binding)
php artisan test Modules/Intervencion/tests/Feature/Livewire/CiudadanoPageTest.php

# 4. Verificación final de no-regresión en los módulos tocados
php artisan test --filter=Auth
php artisan test --filter=Prestacion
php artisan test --filter=Intervencion
```

No ejecutar la suite completa al terminar — seguir la convención de CLAUDE.md sección 2.
