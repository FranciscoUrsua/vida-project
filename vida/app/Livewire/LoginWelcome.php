<?php

namespace App\Livewire;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginWelcome extends Component
{
    public $username;
    public $password;
    public $errorMessage = '';

    protected $rules = [
        'username' => 'required|string',
        'password' => 'required|string',
    ];

    public function login()
    {
        $this->validate();

        try {
            if (Auth::attempt(['email' => $this->username, 'password' => $this->password])) {
                return redirect()->intended('/dashboard');
            } else {
                $this->errorMessage = 'Credenciales inválidas. Inténtalo de nuevo.';
            }
        } catch (ValidationException $e) {
            $this->errorMessage = 'Por favor, completa todos los campos correctamente.';
        }
    }

    public function render()
    {
        return view('livewire.login-welcome');
    }
}
