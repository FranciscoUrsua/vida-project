<?php

namespace App\Livewire;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Cambio de la propia contraseña desde el desplegable del usuario (27 nov 2026).
 *
 * Política (decisión de implementación, no había ninguna definida): hay que dar la contraseña
 * actual; la nueva tiene al menos 12 caracteres con letras y números, no puede ser la actual y
 * se escribe dos veces. Al cambiarla se cierran las demás sesiones abiertas del usuario (tabla
 * `sessions`) y se renueva la actual. No se consulta ningún servicio externo de contraseñas
 * filtradas (CLAUDE.md: ningún dato del usuario sale a terceros).
 */
#[Layout('layouts.operativo')]
class CambiarContrasena extends Component
{
    /** Contraseña actual. */
    public string $actual = '';

    /** Contraseña nueva. */
    public string $nueva = '';

    /** Contraseña nueva, repetida. */
    public string $nuevaConfirmacion = '';

    /** Si se acaba de cambiar (mensaje de confirmación). */
    public bool $cambiada = false;

    /**
     * Valida y guarda la nueva contraseña, y cierra las demás sesiones del usuario.
     *
     * @return void
     */
    public function guardar(): void
    {
        /** @var User $usuario */
        $usuario = auth()->user();
        $this->cambiada = false;

        $this->validate([
            'actual' => ['required', 'current_password'],
            'nueva' => ['required', 'same:nuevaConfirmacion', Password::min(12)->letters()->numbers()],
        ], [
            'actual.required' => 'Escribe tu contraseña actual.',
            'actual.current_password' => 'La contraseña actual no es correcta.',
            'nueva.required' => 'Escribe la contraseña nueva.',
            'nueva.same' => 'Las dos contraseñas nuevas no coinciden.',
        ]);

        if (Hash::check($this->nueva, $usuario->password)) {
            $this->addError('nueva', 'La contraseña nueva tiene que ser distinta de la actual.');

            return;
        }

        // El cast `hashed` del modelo la cifra (CLAUDE.md: sin bcrypt() manual)
        $usuario->update(['password' => $this->nueva]);

        // Quien tuviera la contraseña anterior pierde las sesiones que hubiera abierto con ella
        DB::table('sessions')
            ->where('user_id', $usuario->id)
            ->where('id', '!=', session()->getId())
            ->delete();
        session()->regenerate();

        $this->reset('actual', 'nueva', 'nuevaConfirmacion');
        $this->cambiada = true;
    }

    /**
     * Renderiza el formulario.
     *
     * @return View
     */
    public function render(): View
    {
        return view('livewire.cambiar-contrasena');
    }
}
