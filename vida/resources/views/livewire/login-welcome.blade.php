{{-- resources/views/livewire/login-welcome.blade.php --}}
<div class="min-vh-100 d-flex align-items-center justify-content-center bg-light">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8 col-xl-6">
                {{-- Hero Section --}}
                <div class="text-center mb-5">
                    <h1 class="display-4 fw-bold text-primary mb-3">VIDA</h1>
                    <p class="lead text-muted mb-4">
                        Visión Integral de Derechos y Atención Social. Una plataforma para una gestión proactiva de servicios sociales. Diseñada para empoderar a trabajadores sociales, administradores y entidades públicas, VIDA facilita la coordinación, valoración y entrega de prestaciones y recursos sociales, asegurando un enfoque centrado en los derechos de las personas y comunidades vulnerables.
                    </p>
                </div>

                {{-- Login Form Card --}}
                <div class="card shadow-lg border-0 rounded-3">
                    <div class="card-body p-5">
                        <div class="mb-4">
                            <h2 class="h4 fw-bold text-center text-dark mb-3">Accede a tu cuenta</h2>
                            @if($errorMessage)
                                <div class="alert alert-danger" role="alert">
                                    {{ $errorMessage }}
                                </div>
                            @endif
                        </div>

                        <form wire:submit="login">
                            <div class="mb-4">
                                <label for="username" class="form-label">Usuario o Email</label>
                                <input type="text" class="form-control form-control-lg @error('username') is-invalid @enderror" id="username" wire:model="username" placeholder="Introduce tu usuario o email" required>
                                @error('username') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="mb-4">
                                <label for="password" class="form-label">Contraseña</label>
                                <input type="password" class="form-control form-control-lg @error('password') is-invalid @enderror" id="password" wire:model="password" placeholder="Introduce tu contraseña" required>
                                @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg fw-semibold">Iniciar Sesión</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
