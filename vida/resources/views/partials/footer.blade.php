{{-- Footer: Fijo en bottom --}}
<footer class="bg-white border-top mt-auto">
    <div class="container-fluid py-3">
        <div class="row align-items-center">
            <div class="col-md-6">
                <small class="text-muted">
                    &copy; {{ date('Y') }} Ayuntamiento de Madrid. Todos los derechos reservados.<br>
                    Plataforma VIDA v{{ config('app.version', '1.0') }} | Desarrollado para Servicios Sociales.
                </small>
            </div>
            <div class="col-md-6 text-md-end">
                <small class="text-muted">
                    <a href="/privacy" class="text-decoration-none">Política de Privacidad (RGPD)</a> | 
                    <a href="/contact" class="text-decoration-none">Contacto</a> | 
                    <a href="mailto:zrm@ggd.amsterdam.nl" class="text-decoration-none">Soporte</a>
                </small>
            </div>
        </div>
    </div>
</footer>
