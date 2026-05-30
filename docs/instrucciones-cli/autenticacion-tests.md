# Tests funcionales — Autenticación
## `docs/instrucciones-cli/autenticacion-tests.md`

> Especificación de tests funcionales para la pantalla de login y el comportamiento
> de autenticación de VIDA 360. La implementación la realiza Claude CLI.
>
> **Referencia de diseño:** `docs/front/ui-autenticacion.md`
> **Modelo:** `app/Models/User.php` — tabla `users`
> **Rutas:** `routes/web.php` (rutas de autenticación Laravel estándar)
>
> Añadir los tests implementados a `tests/Feature/Auth/` y registrar en CHANGELOG.md.

---

## Convenciones

- **Framework:** PHPUnit con atributo `#[Test]`. No usar Pest.
- **Base de datos:** PostgreSQL (`vida_testing`). No usar SQLite.
- **Ubicación:** `tests/Feature/Auth/`
- **Patrón:** Dado / Cuando / Entonces.
- **Negativo obligatorio:** los tests de restricciones deben verificarse también en negativo.
- **Factories:** usar `User::factory()` para crear actores. No hardcodear credenciales.

---

## Actores reutilizados

Definir en `setUp()` de cada clase o en un trait `InteractuaConAutenticacion`:

- `$usuario` — usuario activo con email y contraseña conocidos, `email_verified_at` relleno.
- `$usuario_sin_verificar` — usuario con `email_verified_at = null`.
- `$usuario_inactivo` — usuario con cuenta desactivada (si el modelo lo soporta; ver nota TF-AUTH-08).

---

## Grupo A — Acceso al formulario de login

### TF-AUTH-01 — La ruta de login devuelve 200 para visitantes no autenticados

- **Dado** que no hay sesión activa.
- **Cuando** se hace GET a `/login`.
- **Entonces** la respuesta tiene código 200 y el HTML contiene el campo `email` y el campo `password`.

---

### TF-AUTH-02 — Un usuario autenticado es redirigido desde login a su pantalla de inicio

- **Dado** `$usuario` autenticado con `actingAs()`.
- **Cuando** hace GET a `/login`.
- **Entonces** recibe una redirección (302) hacia la ruta de inicio (`/` o la ruta configurada en `RouteServiceProvider::HOME`).

---

## Grupo B — Login con credenciales correctas

### TF-AUTH-03 — Login exitoso con email y contraseña correctos

- **Dado** `$usuario` con email `juana@madrid.es` y contraseña `secreto123`.
- **Cuando** se hace POST a `/login` con `['email' => 'juana@madrid.es', 'password' => 'secreto123']`.
- **Entonces** la respuesta redirige (302), el usuario queda autenticado en la sesión (`Auth::check()` devuelve `true`) y la redirección apunta a la ruta de inicio.

---

### TF-AUTH-04 — El email es case-insensitive en el login

- **Dado** `$usuario` creado con email `juana@madrid.es`.
- **Cuando** se hace POST a `/login` con `['email' => 'JUANA@MADRID.ES', 'password' => ...]`.
- **Entonces** el login es exitoso. *(Nota: verificar comportamiento de Laravel por defecto; si no es case-insensitive out-of-the-box, documentar la decisión y ajustar el test en consecuencia.)*

---

### TF-AUTH-05 — Tras login exitoso la sesión regenera el token CSRF

- **Dado** `$usuario` con credenciales correctas.
- **Cuando** se hace POST a `/login`.
- **Entonces** el token de sesión ha cambiado respecto al de antes del login (protección contra session fixation).

---

## Grupo C — Login con credenciales incorrectas

### TF-AUTH-06 — Login fallido con contraseña incorrecta

- **Dado** `$usuario` con email `juana@madrid.es`.
- **Cuando** se hace POST a `/login` con contraseña incorrecta.
- **Entonces** la respuesta redirige de vuelta al formulario, el usuario no queda autenticado, y la sesión contiene un error en el bag de errores para el campo `email`.

---

### TF-AUTH-07 — Login fallido con email inexistente

- **Dado** que no existe ningún usuario con email `noexiste@madrid.es`.
- **Cuando** se hace POST a `/login` con ese email.
- **Entonces** misma respuesta que TF-AUTH-06. El mensaje de error no distingue entre "email no existe" y "contraseña incorrecta" — ambos casos devuelven el mismo mensaje genérico.

---

### TF-AUTH-08 — Login fallido con campos vacíos

- **Dado** un visitante.
- **Cuando** se hace POST a `/login` con `email` y `password` vacíos.
- **Entonces** la respuesta redirige al formulario con errores de validación en ambos campos. No se intenta la autenticación.

---

### TF-AUTH-09 — El campo email debe tener formato de email válido

- **Dado** un visitante.
- **Cuando** se hace POST a `/login` con `email = 'noesuncorreo'`.
- **Entonces** la respuesta redirige con error de validación en el campo `email`.

---

## Grupo D — Protección por throttle

### TF-AUTH-10 — Demasiados intentos fallidos bloquean el acceso temporalmente

- **Dado** `$usuario` con credenciales incorrectas.
- **Cuando** se hacen 6 intentos de login fallidos consecutivos desde la misma IP y para el mismo email.
- **Entonces** el séptimo intento devuelve una respuesta con error de throttle (código 429 o redirección con error `auth.throttle`), independientemente de si las credenciales son correctas.

---

### TF-AUTH-11 — El throttle es por combinación de email + IP, no solo por IP

- **Dado** dos usuarios distintos (`$usuario_a`, `$usuario_b`) y una misma IP.
- **Cuando** se hacen 5 intentos fallidos para `$usuario_a` y luego 1 intento para `$usuario_b`.
- **Entonces** el intento de `$usuario_b` no está bloqueado (el throttle de `$usuario_a` no afecta a `$usuario_b`).

---

## Grupo E — Logout

### TF-AUTH-12 — Logout cierra la sesión correctamente

- **Dado** `$usuario` autenticado.
- **Cuando** se hace POST a `/logout`.
- **Entonces** `Auth::check()` devuelve `false`, la sesión ha sido invalidada y la respuesta redirige a `/login` o a la ruta configurada como destino de logout.

---

### TF-AUTH-13 — Logout requiere método POST (no GET)

- **Dado** `$usuario` autenticado.
- **Cuando** se hace GET a `/logout`.
- **Entonces** la respuesta es 405 (Method Not Allowed) o redirección sin cerrar la sesión. No debe ser posible cerrar sesión mediante un enlace GET fabricado por un tercero.

---

## Grupo F — Protección de rutas

### TF-AUTH-14 — Las rutas protegidas redirigen a login si no hay sesión

- **Dado** que no hay sesión activa.
- **Cuando** se hace GET a `/` (ruta de inicio de la aplicación operacional).
- **Entonces** la respuesta redirige a `/login`.

---

### TF-AUTH-15 — Un usuario autenticado puede acceder a la ruta de inicio

- **Dado** `$usuario` autenticado con `actingAs()`.
- **Cuando** hace GET a `/`.
- **Entonces** la respuesta tiene código 200.

---

## Grupo G — Identidad visible en la UI

### TF-AUTH-16 — El nombre del usuario aparece en la UI tras el login, no el email

- **Dado** `$usuario` con `name = 'Juana López García'` y `email = 'juana@madrid.es'`.
- **Cuando** `$usuario` autenticado hace GET a `/`.
- **Entonces** el HTML de la respuesta contiene `'Juana López'` (o las iniciales `'JL'`) y **no** contiene `'juana@madrid.es'` como texto visible en el topbar o el avatar.

---

### TF-AUTH-17 — Las iniciales del avatar se forman con las dos primeras letras del name

- **Dado** `$usuario` con `name = 'Juana López García'`.
- **Cuando** `$usuario` autenticado hace GET a `/`.
- **Entonces** el HTML contiene el texto `'JL'` en el elemento del avatar (no `'JU'` ni `'JLG'`).

---

## Grupo H — Primer acceso (onboarding)

### TF-AUTH-18 — Un usuario que nunca ha accedido ve la pantalla de onboarding

- **Dado** `$usuario` recién creado con `primer_acceso = true` (o equivalente según implementación).
- **Cuando** hace login exitoso.
- **Entonces** es redirigido a la ruta de onboarding (`/bienvenida` o similar), no a la pantalla de inicio normal.

---

### TF-AUTH-19 — Un usuario que ya ha accedido antes no ve la pantalla de onboarding

- **Dado** `$usuario` con `primer_acceso = false`.
- **Cuando** hace login exitoso.
- **Entonces** es redirigido directamente a la ruta de inicio (`/`), sin pasar por onboarding.

---

### TF-AUTH-20 — El onboarding muestra el nombre del usuario y su centro de adscripción

- **Dado** `$usuario` con `primer_acceso = true`, `name = 'Juana López García'`, adscrito a un centro con nombre `'CSS Carabanchel Norte'`.
- **Cuando** accede a la ruta de onboarding.
- **Entonces** el HTML contiene `'Juana López'` y `'CSS Carabanchel Norte'`.

---

### TF-AUTH-21 — Completar el onboarding marca primer_acceso como false

- **Dado** `$usuario` con `primer_acceso = true` en la pantalla de onboarding.
- **Cuando** hace clic en "Empezar" (POST o GET a la ruta de cierre de onboarding).
- **Entonces** `$usuario->fresh()->primer_acceso` es `false` y el usuario es redirigido a `/`.

---

### TF-AUTH-22 — Volver a la ruta de onboarding con primer_acceso = false redirige a inicio

- **Dado** `$usuario` con `primer_acceso = false`.
- **Cuando** intenta acceder directamente a la ruta de onboarding.
- **Entonces** es redirigido a `/`.

---

## Grupo I — Badge de entorno

### TF-AUTH-23 — El badge de entorno muestra el valor de APP_ENV_LABEL

- **Dado** que `APP_ENV_LABEL` está configurada como `'Staging'` en el entorno de test.
- **Cuando** un visitante hace GET a `/login`.
- **Entonces** el HTML contiene el texto `'Staging'` en el elemento del badge de entorno.

---

## Notas de implementación para Claude CLI

**Sobre `primer_acceso`:** si el modelo `User` no tiene este campo todavía, añadir la migración `add_primer_acceso_to_users_table` con `$table->boolean('primer_acceso')->default(true)` y actualizar el factory. Documentar la adición en CHANGELOG.md.

**Sobre `APP_ENV_LABEL`:** añadir al `.env.example` con valor por defecto `'Producción'`. Leer desde `config/app.php` como `'env_label' => env('APP_ENV_LABEL', 'Producción')`.

**Sobre el test TF-AUTH-04 (case-insensitive):** Laravel no normaliza el email antes de buscarlo por defecto. Si la decisión es mantener el comportamiento estándar (sensible a mayúsculas), documentarlo en `ui-autenticacion.md` y marcar el test como skipped con `$this->markTestSkipped('Email es case-sensitive por decisión de diseño')`.

**Sobre TF-AUTH-16 y TF-AUTH-17 (identidad visible):** estos tests requieren que la vista de inicio (`/`) esté mínimamente implementada con el topbar y el componente de avatar. Si la vista no existe aún, marcar con `$this->markTestIncomplete('Requiere vista de inicio implementada')`.
