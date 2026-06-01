# Instrucciones para Claude CLI — Usuario sin rol
## `docs/instrucciones-cli/usuario-sin-rol.md`

> Tres medidas complementarias para evitar que un usuario sin rol reciba un 403
> al hacer login y quede atrapado sin salida.
>
> **Módulos afectados:** `Modules/Usuarios` (o núcleo `app/`), Filament backoffice
> **Documentos de referencia:** `docs/modulo-usuarios-permisos.md`,
> `docs/front/ui-autenticacion.md`

---

## Contexto

Un usuario puede llegar a no tener rol asignado en tres situaciones:
- Fue creado por consola o por seeder sin asignarle rol.
- El administrador revocó su rol sin asignar otro.
- Se creó desde Filament y el campo de roles quedó vacío.

En cualquiera de estos casos el middleware `role:*` lanza un 403, que ahora mismo
es la pantalla de error genérica de Laravel. Esta tarea resuelve los tres
vectores de entrada y añade una salida digna para el caso residual.

---

## Paso 1 — Revisar el estado actual

```bash
# ¿Existe ya lógica de rol por defecto en el modelo User o en observers?
grep -r "consulta_basica\|rol.*defecto\|default.*role" app/ Modules/ --include="*.php" -l

# ¿Hay un observer o boot() en User que asigne roles?
grep -n "creating\|created\|assignRole" app/Models/User.php

# ¿Cómo está el UsuarioResource en Filament?
find . -name "UsuarioResource.php" -not -path "*/vendor/*"

# Tests de autorización existentes
php artisan test --filter=Usuario 2>&1 | tail -5
```

---

## Paso 2 — Rol por defecto en la creación

Añadir en el modelo `User` (o en su Observer si ya existe) la asignación
automática del rol `consulta_basica` cuando se crea un usuario sin roles.

**Ubicación preferida:** en el `booted()` del modelo, evento `created`.
Si ya existe un `UserObserver`, añadirlo ahí en el método `created()`.

```php
protected static function booted(): void
{
    static::created(function (User $user) {
        if ($user->roles()->count() === 0) {
            $user->assignRole('consulta_basica');
        }
    });
}
```

**Importante:**
- Usar `$user->roles()->count() === 0` y no `!$user->hasAnyRole(...)` para
  evitar falsos positivos si el rol ya se asignó antes del evento `created`.
- El rol `consulta_basica` debe existir en la base de datos. Verificar que el
  seeder de roles lo crea. Si no existe, el `assignRole` lanzará una excepción
  — añadir un guard:

```php
if ($user->roles()->count() === 0 && Role::where('name', 'consulta_basica')->exists()) {
    $user->assignRole('consulta_basica');
}
```

- Este comportamiento **no se aplica** a usuarios con `profesional_id = null`
  (administradores técnicos del sistema, rol `adm_sistema`). Añadir la condición:

```php
if ($user->roles()->count() === 0
    && $user->profesional_id !== null
    && Role::where('name', 'consulta_basica')->exists()) {
    $user->assignRole('consulta_basica');
}
```

---

## Paso 3 — Validación en el UsuarioResource de Filament

En `UsuarioResource`, localizar el formulario de creación y edición.
Añadir validación que impida guardar si el campo de roles está vacío.

El campo de roles probablemente es un `Select` o `CheckboxList` con
`->relationship('roles', 'name')`. Añadir `->required()` y un mensaje
de validación explícito:

```php
Forms\Components\Select::make('roles')
    ->label('Rol')
    ->relationship('roles', 'name')
    ->multiple()
    ->required()
    ->validationMessages([
        'required' => 'El usuario debe tener al menos un rol asignado.',
    ])
    ->helperText('Si no estás seguro, asigna "Consulta básica" como mínimo.')
    ->preload(),
```

Si el campo de roles no existe todavía en el formulario de Filament, crearlo
siguiendo el patrón anterior.

**Solo en el formulario de creación**, añadir también un valor por defecto
para que el administrador no tenga que seleccionarlo manualmente en el caso
más habitual:

```php
->default(fn () => Role::where('name', 'consulta_basica')->pluck('id')->toArray())
```

---

## Paso 4 — Pantalla de usuario sin rol

### 4.1 Ruta

Añadir en `routes/web.php`, **fuera** del grupo de rutas protegidas por rol
pero **dentro** del middleware `auth`:

```php
Route::middleware('auth')->get('/sin-rol', function () {
    return view('errors.sin-rol');
})->name('sin-rol');
```

### 4.2 Vista

Crear `resources/views/errors/sin-rol.blade.php`.

La vista extiende el layout de autenticación (`layouts.auth` o el equivalente
en el proyecto — el mismo que usa la pantalla de login) para mantener coherencia
visual.

Contenido:

```
[Logo / nombre de la aplicación]

Tu cuenta no tiene un perfil de acceso asignado.
Para acceder a la aplicación, contacta con tu responsable de unidad
y pídele que te asigne un perfil.

---

[Si $user->profesional existe y tiene nombre]
  Nombre:  [profesional->nombre_completo]

[Si $user->email existe]
  Email:   [user->email]

---

[Botón: Cerrar sesión]
```

El bloque de datos del usuario ayuda a verificar que se ha iniciado sesión con
la cuenta correcta antes de llamar a soporte.

Implementación de la vista (adaptar al sistema de plantillas del proyecto):

```blade
@extends('layouts.auth')

@section('content')
<div class="auth-card">
    <div class="auth-card__icon">
        {{-- Icono de candado o similar, coherente con el diseño del login --}}
    </div>

    <h1 class="auth-card__title">Sin perfil de acceso</h1>

    <p class="auth-card__body">
        Tu cuenta no tiene un perfil de acceso asignado.
        Para acceder a la aplicación, contacta con tu responsable de unidad
        y pídele que te asigne un perfil.
    </p>

    @php $user = Auth::user(); @endphp

    @if($user->profesional?->nombre_completo || $user->email)
    <div class="auth-card__user-info">
        @if($user->profesional?->nombre_completo)
        <div class="auth-card__user-row">
            <span class="auth-card__user-label">Nombre</span>
            <span class="auth-card__user-value">{{ $user->profesional->nombre_completo }}</span>
        </div>
        @endif
        @if($user->email)
        <div class="auth-card__user-row">
            <span class="auth-card__user-label">Email</span>
            <span class="auth-card__user-value">{{ $user->email }}</span>
        </div>
        @endif
    </div>
    @endif

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="btn btn-secondary">
            Cerrar sesión
        </button>
    </form>
</div>
@endsection
```

Verificar el nombre exacto del campo de nombre completo en `Profesional`
consultando `docs/modulo-usuarios-permisos.md`. Si el campo se llama distinto
(p.ej. `nombre` + `apellido1` + `apellido2`), concatenar apropiadamente:

```php
$user->profesional?->nombre . ' ' . $user->profesional?->apellido1
```

Usar `?->` (nullsafe) en todos los accesos al profesional por si `profesional_id` es null.

### 4.3 Redirección desde el login

Cuando un usuario autenticado no tiene ningún rol, en lugar de dejar que el
middleware lance un 403, redirigir proactivamente a `/sin-rol`.

Localizar dónde se produce la redirección post-login. Típicamente es en
`app/Http/Controllers/Auth/AuthenticatedSessionController.php` o en el
`RedirectIfAuthenticated` middleware, o en el método `redirectTo()` de
algún guard. Buscar:

```bash
grep -rn "intended\|home\|dashboard\|after.*login\|redirectTo" \
  app/Http/Controllers/Auth/ \
  app/Http/Middleware/ \
  app/Providers/ \
  --include="*.php"
```

En el punto de redirección post-login, añadir la comprobación:

```php
if (Auth::check() && Auth::user()->roles()->count() === 0) {
    return redirect()->route('sin-rol');
}
```

Si el proyecto usa `RouteServiceProvider::HOME` como constante de redirección,
la solución más limpia es crear un middleware `EnsureTieneRol` y añadirlo al
grupo de rutas operativas:

```php
// app/Http/Middleware/EnsureTieneRol.php

public function handle(Request $request, Closure $next): Response
{
    if (Auth::check() && Auth::user()->roles()->count() === 0) {
        return redirect()->route('sin-rol');
    }
    return $next($request);
}
```

Registrar el middleware en `bootstrap/app.php` (Laravel 12) o en
`app/Http/Kernel.php` con alias `tiene.rol`, y añadirlo al grupo de rutas
operativas junto a `auth`.

**La ruta `/sin-rol` no debe tener este middleware** — de lo contrario se
produciría un bucle de redirección.

---

## Paso 5 — Tests

Crear `tests/Feature/Auth/SinRolTest.php`:

```
TF-AUTH-SR-01 — Usuario sin roles es redirigido a /sin-rol tras el login
TF-AUTH-SR-02 — Usuario sin roles que accede directamente a /intervencion/agenda es redirigido a /sin-rol
TF-AUTH-SR-03 — La pantalla /sin-rol muestra el nombre del profesional si existe
TF-AUTH-SR-04 — La pantalla /sin-rol muestra el email si no hay profesional asociado
TF-AUTH-SR-05 — La pantalla /sin-rol no muestra el bloque de datos si ni nombre ni email están disponibles
TF-AUTH-SR-06 — Un usuario con rol accede normalmente y no ve /sin-rol
TF-AUTH-SR-07 — El botón "Cerrar sesión" en /sin-rol cierra la sesión y redirige al login
TF-AUTH-SR-08 — Usuario creado sin rol tiene rol consulta_basica asignado automáticamente
TF-AUTH-SR-09 — Usuario creado con profesional_id null no recibe el rol por defecto
TF-AUTH-SR-10 — Intentar guardar un usuario en Filament sin rol muestra error de validación
```

Para TF-AUTH-SR-10, usar `Livewire::test()` apuntando al `UsuarioResource`
de Filament, o un test de tipo `actingAs` + POST al endpoint del formulario.

Ejecutar al terminar:

```bash
php artisan test --filter=SinRol
php artisan test --filter=Auth
php artisan test 2>&1 | tail -5
```

---

## Lo que NO hay que hacer

- No cambiar el comportamiento del middleware `role:*` de Spatie — sigue
  funcionando como antes para usuarios que sí tienen rol pero no el correcto.
  Esta tarea solo cubre el caso de cero roles.
- No asignar el rol por defecto a usuarios ya existentes en la base de datos.
  Si hay usuarios sin rol en producción, es una decisión de negocio que debe
  tomarse explícitamente, no silenciarse con una migración automática.
- No eliminar la respuesta 403 de los middlewares de rol — es el comportamiento
  correcto cuando un usuario *tiene* rol pero no el adecuado para esa ruta.
- No implementar solicitud de rol desde la pantalla (eso es Opción C, descartada).

---

## Checklist de finalización

- [ ] `php artisan test --filter=SinRol` pasa los 10 tests
- [ ] `php artisan test --filter=Auth` sigue pasando los tests previos
- [ ] `php artisan test` no introduce fallos nuevos
- [ ] Un usuario nuevo creado con `User::create([...])` sin asignar rol
      tiene automáticamente el rol `consulta_basica`
- [ ] Un usuario con `profesional_id = null` creado sin rol **no** recibe
      el rol por defecto
- [ ] El formulario de Filament no permite guardar un usuario sin rol
- [ ] Un usuario sin roles que hace login llega a `/sin-rol`, no a un 403
- [ ] La pantalla `/sin-rol` muestra el nombre si existe el profesional
- [ ] La pantalla `/sin-rol` muestra el email si no hay nombre disponible
- [ ] El botón de cerrar sesión en `/sin-rol` funciona correctamente
- [ ] Acceder a `/sin-rol` sin sesión activa redirige al login (no error)
- [ ] Entrada añadida en `CHANGELOG.md`
