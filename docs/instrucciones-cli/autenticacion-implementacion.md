# Instrucciones para Claude CLI — Autenticación
## `docs/instrucciones-cli/autenticacion-implementacion.md`

> Instrucciones para implementar la pantalla de login y el sistema de autenticación
> del interfaz operacional de VIDA 360.
>
> **Diseño de referencia:** `docs/front/ui-autenticacion.md`
> **Tests a pasar:** `docs/instrucciones-cli/autenticacion-tests.md` (TF-AUTH-01 a TF-AUTH-23)
> **Principios generales del proyecto:** `docs/principios-vida360.md`

---

## Contexto

La autenticación de VIDA 360 usa Laravel Auth estándar. El interfaz operacional
vive en la raíz del dominio (`/`). El backoffice de Filament está en `/admin` y
tiene su propio sistema de autenticación — no tocar.

El stack del interfaz operacional es **Blade + Livewire 4 + Alpine.js + Bootstrap 5**.
No usar Filament ni Inertia para nada en este lado.

---

## Paso 1 — Revisar el estado actual antes de escribir código

Antes de crear ningún archivo, verificar qué existe ya:

```bash
# ¿Hay rutas de autenticación definidas?
grep -n "auth" routes/web.php

# ¿Existe algún controlador de autenticación?
ls app/Http/Controllers/Auth/

# ¿Hay vistas de login previas?
ls resources/views/auth/

# ¿El modelo User tiene los campos esperados?
grep -n "primer_acceso\|email_verified" database/migrations/*users*
```

Si Laravel Breeze o Fortify están instalados, revisar si sus rutas y controladores
son compatibles con lo que se describe aquí antes de proceder.

---

## Paso 2 — Migración: campo primer_acceso en users

Si `users` no tiene el campo `primer_acceso`:

```bash
php artisan make:migration add_primer_acceso_to_users_table
```

Contenido de la migración:

```php
Schema::table('users', function (Blueprint $table) {
    $table->boolean('primer_acceso')->default(true)->after('remember_token');
});
```

Añadir al factory de `User`:

```php
'primer_acceso' => true,
```

Añadir al `$fillable` de `User` si no está en el cast automático.

---

## Paso 3 — Configuración APP_ENV_LABEL

En `config/app.php`, añadir:

```php
'env_label' => env('APP_ENV_LABEL', 'Producción'),
```

En `.env.example`, añadir:

```
APP_ENV_LABEL=Producción
```

En `.env.testing`, añadir:

```
APP_ENV_LABEL=Testing
```

---

## Paso 4 — Rutas de autenticación

En `routes/web.php`, asegurarse de que existen:

```php
// Autenticación
Route::get('/login', [LoginController::class, 'mostrar'])->name('login')->middleware('guest');
Route::post('/login', [LoginController::class, 'autenticar'])->name('login.post')->middleware('guest');
Route::post('/logout', [LoginController::class, 'cerrarSesion'])->name('logout')->middleware('auth');

// Onboarding (primer acceso)
Route::get('/bienvenida', [OnboardingController::class, 'mostrar'])
    ->name('onboarding')
    ->middleware(['auth', 'primer.acceso']);
Route::post('/bienvenida', [OnboardingController::class, 'completar'])
    ->name('onboarding.completar')
    ->middleware(['auth', 'primer.acceso']);

// Raíz protegida
Route::get('/', fn() => view('inicio'))->name('inicio')->middleware('auth');
```

Nota: los nombres de los métodos siguen la convención del proyecto (español).

---

## Paso 5 — LoginController

Crear `app/Http/Controllers/Auth/LoginController.php`:

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Gestiona el acceso y la salida de la aplicación operacional.
 * El backoffice (/admin) tiene su propia autenticación Filament — no usar este controlador allí.
 */
class LoginController extends Controller
{
    /**
     * Muestra el formulario de login.
     */
    public function mostrar()
    {
        return view('auth.login');
    }

    /**
     * Procesa el intento de autenticación.
     *
     * @throws ValidationException
     */
    public function autenticar(Request $request)
    {
        $credenciales = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt($credenciales, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        $request->session()->regenerate();

        // Si es el primer acceso, redirigir a onboarding
        if (Auth::user()->primer_acceso) {
            return redirect()->route('onboarding');
        }

        return redirect()->intended(route('inicio'));
    }

    /**
     * Cierra la sesión activa.
     */
    public function cerrarSesion(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
```

---

## Paso 6 — Middleware PrimerAcceso

Crear `app/Http/Middleware/PrimerAcceso.php`:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Redirige a inicio si el usuario ya ha completado el onboarding.
 * Redirige a onboarding si el usuario aún no lo ha hecho.
 * Solo actúa sobre rutas que requieren gestionar este estado.
 */
class PrimerAcceso
{
    public function handle(Request $request, Closure $next)
    {
        // Si está en la ruta de onboarding pero ya completó el primer acceso → inicio
        if (! $request->user()->primer_acceso) {
            return redirect()->route('inicio');
        }

        return $next($request);
    }
}
```

Registrar en `bootstrap/app.php` (Laravel 12) o en `app/Http/Kernel.php`:

```php
'primer.acceso' => \App\Http\Middleware\PrimerAcceso::class,
```

---

## Paso 7 — OnboardingController

Crear `app/Http/Controllers/Auth/OnboardingController.php`:

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Gestiona la pantalla de primer acceso que se muestra una sola vez
 * tras la creación de la cuenta.
 */
class OnboardingController extends Controller
{
    /**
     * Muestra la pantalla de bienvenida con el contexto del usuario.
     */
    public function mostrar()
    {
        $usuario = Auth::user();

        // Obtener el centro de adscripción activo si existe
        $centro = $usuario->profesional?->centroActivo()?->nombre;

        return view('auth.onboarding', compact('usuario', 'centro'));
    }

    /**
     * Marca el onboarding como completado y redirige a inicio.
     */
    public function completar(Request $request)
    {
        Auth::user()->update(['primer_acceso' => false]);

        return redirect()->route('inicio');
    }
}
```

---

## Paso 8 — Vistas Blade

### `resources/views/auth/login.blade.php`

Estructura del layout (ver `docs/front/ui-autenticacion.md` sección 1 para el diseño):

- Pantalla dividida en dos columnas.
- Columna izquierda: bloque de identidad de la aplicación (logo, nombre, descripción, píldoras de módulos, nota de acceso restringido).
- Columna derecha: formulario de login con badge de entorno, campo email, campo password, botón "Entrar", enlace "¿Olvidaste tu contraseña?", enlace de soporte.

El badge de entorno usa `config('app.env_label')`.

Requisitos de accesibilidad mínimos:
- `<label>` asociado a cada campo de formulario.
- `autocomplete="email"` en el campo de email.
- `autocomplete="current-password"` en el campo de contraseña.
- `aria-describedby` en los campos si tienen mensajes de error.

No usar JavaScript en esta vista. El formulario debe funcionar sin Alpine.js.

### `resources/views/auth/onboarding.blade.php`

Vista simple con:
- Saludo personalizado con `$usuario->name`.
- Centro de adscripción `$centro` (si existe).
- Botón "Empezar" que hace POST a `route('onboarding.completar')`.

### `resources/views/inicio.blade.php`

Vista mínima de placeholder que incluye el topbar con el componente de avatar.
Necesaria para que pasen los tests TF-AUTH-15, TF-AUTH-16 y TF-AUTH-17.
El contenido real de esta vista se desarrollará en la fase de implementación del módulo correspondiente.

El topbar debe incluir:
- El nombre del usuario: `Auth::user()->name` (nombre completo, p.ej. "Juana López García").
- El componente de avatar con las iniciales: las dos primeras letras de las dos primeras palabras del `name`.

---

## Paso 9 — Componente Blade de avatar

Crear `resources/views/components/avatar.blade.php`:

```blade
@props(['usuario'])

@php
    // Extraer las dos primeras iniciales del nombre completo
    $palabras = explode(' ', trim($usuario->name));
    $iniciales = '';
    if (isset($palabras[0])) $iniciales .= strtoupper(substr($palabras[0], 0, 1));
    if (isset($palabras[1])) $iniciales .= strtoupper(substr($palabras[1], 0, 1));

    // Color determinista por ID de usuario
    $colores = ['bg-teal', 'bg-blue', 'bg-purple', 'bg-amber'];
    $color = $colores[$usuario->id % count($colores)];
@endphp

<div class="avatar avatar-sm {{ $color }}" title="{{ $usuario->name }}">
    {{ $iniciales }}
</div>
```

El color determinista garantiza que el mismo usuario siempre tenga el mismo color,
lo que facilita el reconocimiento visual en el log de accesos.

---

## Paso 10 — Ejecutar los tests

```bash
php artisan test tests/Feature/Auth/ --env=testing
```

Los tests deben pasar todos en verde. Si alguno está marcado con
`markTestIncomplete()`, documentar en qué fase se completará.

Al terminar, registrar en `CHANGELOG.md`:

```
## [sin versión] — AAAA-MM-DD
### Añadido
- Autenticación: login, logout, onboarding primer acceso (TF-AUTH-01 a TF-AUTH-23)
- Campo `primer_acceso` en tabla `users`
- Configuración `APP_ENV_LABEL` para badge de entorno en login
- Componente Blade `<x-avatar>` con iniciales y color determinista por ID
```

---

## Lo que NO hay que hacer

- No implementar autenticación federada (SSO/LDAP) en esta fase. El documento
  `ui-autenticacion.md` lo menciona como funcionalidad futura. El botón puede
  aparecer en la vista como elemento visual desactivado (`disabled`) si el diseño
  lo requiere, pero sin lógica detrás.
- No tocar `/admin` ni el sistema de autenticación de Filament.
- No usar `session()->flash()` para mensajes de éxito de login — Laravel redirige
  directamente, no hay mensaje de "has iniciado sesión correctamente".
- No añadir campos de nombre de usuario adicionales. El identificador de login
  es el email, sin excepciones.
