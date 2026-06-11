<?php

namespace Modules\Ciudadania\Http\Livewire;

use App\Enums\OrigenDireccion;
use App\Models\Ciudadano;
use App\Models\Scopes\AmbitoUoScope;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Ciudadania\Contracts\FuenteIdentidadInterface;
use Modules\Ciudadania\Models\CiudadanoIdentificador;
use Modules\Ciudadania\Services\MotorMatching;
use Modules\Ciudadania\Services\NormalizadorCiudadano;

/**
 * Componente Livewire del flujo de alta de ciudadano.
 *
 * Cuatro fases secuenciales: busqueda → padron → formulario → confirmacion.
 * No hay navegación libre entre fases; se avanza al completar cada una.
 *
 * Ver docs/instrucciones-cli/instrucciones-cli-alta-ciudadano.md Tarea 4.
 * Ver docs/front/alta-ciudadano-funcional.md.
 */
#[Layout('layouts.operativo')]
class AltaCiudadano extends Component
{
    // -------------------------------------------------------------------------
    // Control de flujo
    // -------------------------------------------------------------------------

    /** @var string busqueda | padron | formulario | confirmacion */
    public string $fase = 'busqueda';

    // -------------------------------------------------------------------------
    // Fase busqueda
    // -------------------------------------------------------------------------

    /** @var string nif | nie | pasaporte */
    public string $busquedaTipoDoc = 'nif';

    public string $busquedaValorDoc = '';

    public string $busquedaNombre = '';

    public string $busquedaApellido1 = '';

    public string $busquedaApellido2 = '';

    public string $busquedaFechaNacimiento = '';

    /** @var list<array<string, mixed>> */
    public array $resultadosBusqueda = [];

    public bool $busquedaRealizada = false;

    // -------------------------------------------------------------------------
    // Fase padron
    // -------------------------------------------------------------------------

    /** @var string|null psh | vvg | representante | otra */
    public ?string $excepcionPadron = null;

    public bool $padronConsultado = false;

    public bool $padronEncontrado = false;

    // -------------------------------------------------------------------------
    // Fase formulario — datos del ciudadano
    // -------------------------------------------------------------------------

    public string $nombre = '';

    public string $apellido1 = '';

    public string $apellido2 = '';

    public string $fechaNacimiento = '';

    public string $sexo = '';

    public string $alias = '';

    public string $tipoDocumento = 'nif';

    public string $valorDocumento = '';

    public string $direccionTexto = '';

    public string $telefono = '';

    public string $email = '';

    /** @var array<string, string> ['nombre' => 'padron', ...] */
    public array $fuenteCampos = [];

    // -------------------------------------------------------------------------
    // Fase confirmacion
    // -------------------------------------------------------------------------

    public string $primeraDemanda = '';

    public string $accionPostAlta = 'ficha'; // cita | ficha | solo_alta

    public ?int $ciudadanoIdCreado = null;

    // -------------------------------------------------------------------------
    // Métodos de flujo
    // -------------------------------------------------------------------------

    /**
     * Busca posibles duplicados usando el motor de matching.
     * No transiciona de fase. Solo requiere al menos un criterio.
     */
    public function buscar(): void
    {
        $tieneCriterio = $this->busquedaValorDoc !== '' ||
            ($this->busquedaNombre !== '' && $this->busquedaFechaNacimiento !== '') ||
            ($this->busquedaApellido1 !== '' && $this->busquedaFechaNacimiento !== '');

        if (! $tieneCriterio) {
            $this->addError('busqueda', 'Introduce un documento o nombre + fecha de nacimiento.');

            return;
        }

        $datos = NormalizadorCiudadano::normalizar([
            'tipo_documento' => $this->busquedaTipoDoc,
            'valor_documento' => $this->busquedaValorDoc,
            'nombre' => $this->busquedaNombre,
            'apellido1' => $this->busquedaApellido1,
            'apellido2' => $this->busquedaApellido2,
            'fecha_nacimiento' => $this->busquedaFechaNacimiento,
        ]);

        $resultados = app(MotorMatching::class)->buscar($datos);

        $this->resultadosBusqueda = $resultados->map(fn ($r) => [
            'ciudadanoId' => $r->ciudadanoId,
            'nombreCompleto' => $r->nombreCompleto,
            'documento' => $r->documento,
            'fechaNacimiento' => $r->fechaNacimiento,
            'score' => $r->score,
            'camposCoincidentes' => $r->camposCoincidentes,
            'bloquea' => $r->bloquea,
        ])->all();

        $this->busquedaRealizada = true;
    }

    /**
     * Redirige a la ficha de un ciudadano existente.
     */
    public function seleccionarExistente(int $ciudadanoId): void
    {
        $this->redirectRoute('intervencion.ciudadano.show', $ciudadanoId);
    }

    /**
     * Precarga datos de búsqueda en el formulario y transiciona a la fase padron.
     * Requiere que la búsqueda haya sido realizada.
     */
    public function continuarConNuevoAlta(): void
    {
        if (! $this->busquedaRealizada) {
            return;
        }

        $this->nombre = $this->busquedaNombre;
        $this->apellido1 = $this->busquedaApellido1;
        $this->apellido2 = $this->busquedaApellido2;
        $this->fechaNacimiento = $this->busquedaFechaNacimiento;
        $this->tipoDocumento = $this->busquedaTipoDoc;
        $this->valorDocumento = $this->busquedaValorDoc;

        // VVG: no pasar por padrón, ir directo al formulario
        if ($this->excepcionPadron === 'vvg') {
            $this->fase = 'formulario';

            return;
        }

        $this->fase = 'padron';
    }

    /**
     * Consulta el padrón y precarga los datos si la persona está empadronada.
     *
     * RESTRICCIÓN DE SEGURIDAD VVG: si la excepción es 'vvg', este método
     * no invoca FuenteIdentidadInterface bajo ninguna circunstancia.
     * La condición se evalúa antes de cualquier llamada HTTP.
     */
    public function consultarPadron(): void
    {
        // VVG: la consulta al padrón no se realiza ni se registra. Ver principio 4.1.
        if ($this->excepcionPadron === 'vvg') {
            return;
        }

        $fuente = app(FuenteIdentidadInterface::class);
        $datos = $fuente->consultarDatos($this->valorDocumento);

        if ($datos !== null) {
            $this->nombre = $datos['nombre'] ?? $this->nombre;
            $this->apellido1 = $datos['apellido1'] ?? $this->apellido1;
            $this->apellido2 = $datos['apellido2'] ?? $this->apellido2;
            $this->fechaNacimiento = $datos['fecha_nacimiento'] ?? $this->fechaNacimiento;
            $this->sexo = $datos['sexo'] ?? $this->sexo;
            $this->direccionTexto = $datos['direccion_texto'] ?? $this->direccionTexto;

            foreach (['nombre', 'apellido1', 'apellido2', 'fecha_nacimiento', 'sexo', 'direccion_texto'] as $campo) {
                if (! empty($datos[$campo])) {
                    $this->fuenteCampos[$campo] = 'padron';
                }
            }

            $this->padronEncontrado = true;
            $this->padronConsultado = true;
            $this->fase = 'formulario';
        } else {
            $this->padronEncontrado = false;
            $this->padronConsultado = true;
            // Permanece en fase padron para que el profesional seleccione excepción
        }
    }

    /**
     * Registra la excepción de padrón y transiciona al formulario.
     * PSH y VVG solo están disponibles para roles intervencion y supervision.
     *
     * @param string $excepcion psh | vvg | representante | otra
     */
    public function seleccionarExcepcionPadron(string $excepcion): void
    {
        if (! in_array($excepcion, ['psh', 'vvg', 'representante', 'otra'], true)) {
            return;
        }

        // PSH y VVG requieren rol profesional — la UI oculta, el servidor rechaza
        if (in_array($excepcion, ['psh', 'vvg'], true)) {
            /** @var User $user */
            $user = auth()->user();
            if (! $user?->hasAnyRole(['intervencion', 'supervision'])) {
                return;
            }
        }

        $this->excepcionPadron = $excepcion;
        $this->fase = 'formulario';
    }

    /**
     * Valida, normaliza, ejecuta segunda pasada de matching y guarda el ciudadano.
     */
    public function guardar(): void
    {
        $this->validate($this->rules());

        $datosNormalizados = NormalizadorCiudadano::normalizar([
            'nombre' => $this->nombre,
            'apellido1' => $this->apellido1,
            'apellido2' => $this->apellido2,
            'tipo_documento' => $this->tipoDocumento,
            'valor_documento' => $this->valorDocumento,
            'telefono' => $this->telefono,
            'email' => $this->email,
        ]);

        // Segunda pasada de matching sobre datos completos normalizados
        $idsYaVistos = array_column($this->resultadosBusqueda, 'ciudadanoId');
        $segundaPasada = app(MotorMatching::class)->buscar($datosNormalizados);
        $nuevoBloqueo = $segundaPasada->first(fn ($r) => $r->bloquea && ! in_array($r->ciudadanoId, $idsYaVistos, true));

        if ($nuevoBloqueo !== null) {
            $this->resultadosBusqueda = $segundaPasada->map(fn ($r) => [
                'ciudadanoId' => $r->ciudadanoId,
                'nombreCompleto' => $r->nombreCompleto,
                'documento' => $r->documento,
                'fechaNacimiento' => $r->fechaNacimiento,
                'score' => $r->score,
                'camposCoincidentes' => $r->camposCoincidentes,
                'bloquea' => $r->bloquea,
            ])->all();
            $this->busquedaRealizada = true;
            $this->fase = 'busqueda';

            return;
        }

        // Aplicar valores normalizados al componente
        $this->nombre = $datosNormalizados['nombre'] ?? $this->nombre;
        $this->apellido1 = $datosNormalizados['apellido1'] ?? $this->apellido1;
        $this->apellido2 = $datosNormalizados['apellido2'] ?? $this->apellido2;
        $this->valorDocumento = $datosNormalizados['valor_documento'] ?? $this->valorDocumento;
        $this->telefono = $datosNormalizados['telefono'] ?? $this->telefono;
        $this->email = $datosNormalizados['email'] ?? $this->email;

        $ciudadano = DB::transaction(function () {
            $nivelIdentificacion = $this->calcularNivelIdentificacion();

            $telefonoHash = ! empty($this->telefono)
                ? hash('sha256', preg_replace('/\s+/', '', $this->telefono))
                : null;
            $emailHash = ! empty($this->email)
                ? hash('sha256', strtolower(trim($this->email)))
                : null;

            $ciudadano = Ciudadano::create([
                'alias' => $this->alias ?: null,
                'nombre' => $this->nombre,
                'apellido1' => $this->apellido1,
                'apellido2' => $this->apellido2 ?: null,
                'fecha_nacimiento' => $this->fechaNacimiento ?: null,
                'sexo' => $this->sexo,
                'direccion_texto' => $this->direccionTexto ?: null,
                'origen_direccion' => $this->direccionTexto ? OrigenDireccion::Profesional : null,
                'telefono' => $this->telefono ?: null,
                'telefono_hash' => $telefonoHash,
                'email' => $this->email ?: null,
                'email_hash' => $emailHash,
                'nivel_identificacion' => $nivelIdentificacion,
                'contexto_alta' => $this->excepcionPadron,
                'es_psh' => $this->excepcionPadron === 'psh',
                'es_vvg' => $this->excepcionPadron === 'vvg',
                'activo' => true,
            ]);

            if (! empty($this->valorDocumento)) {
                CiudadanoIdentificador::create([
                    'ciudadano_id' => $ciudadano->id,
                    'tipo' => $this->tipoDocumento,
                    'valor' => $this->valorDocumento,
                    'fecha_inicio' => today()->toDateString(),
                    'verificado' => false,
                    'fuente' => 'manual',
                ]);
            }

            return $ciudadano;
        });

        $this->ciudadanoIdCreado = $ciudadano->id;
        $this->fase = 'confirmacion';
    }

    /**
     * Persiste la primera demanda y redirige según la acción elegida.
     */
    public function confirmarAlta(): void
    {
        if ($this->primeraDemanda !== '' && $this->ciudadanoIdCreado !== null) {
            // El ciudadano recién creado no tiene historia social, por lo que
            // AmbitoUoScope lo filtraría. Se bypasea explícitamente para el alta.
            Ciudadano::withoutGlobalScope(AmbitoUoScope::class)
                ->find($this->ciudadanoIdCreado)
                ?->update(['primera_demanda' => $this->primeraDemanda]);
        }

        match ($this->accionPostAlta) {
            'cita' => $this->redirectRoute('ciudadania.ciudadano.nueva-cita', $this->ciudadanoIdCreado),
            'ficha' => $this->redirectRoute('ciudadania.ciudadano.ficha', $this->ciudadanoIdCreado),
            default => $this->redirectRoute('ciudadania.buscar'),
        };
    }

    // -------------------------------------------------------------------------
    // Helpers privados
    // -------------------------------------------------------------------------

    /**
     * @return array<string, string>
     */
    protected function rules(): array
    {
        $esPsh = $this->excepcionPadron === 'psh';

        return [
            'nombre' => $esPsh ? 'nullable|string|max:100' : 'required|string|max:100',
            'apellido1' => $esPsh ? 'nullable|string|max:100' : 'required|string|max:100',
            'apellido2' => 'nullable|string|max:100',
            'fechaNacimiento' => 'nullable|date|before:today',
            'sexo' => 'required|string',
            'alias' => $esPsh ? 'required|string|max:200' : 'nullable|string|max:200',
            'tipoDocumento' => 'nullable|string|in:nif,nie,pasaporte',
            'valorDocumento' => 'nullable|string|max:20',
            'direccionTexto' => 'nullable|string|max:500',
            'telefono' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'primeraDemanda' => 'nullable|string|max:2000',
        ];
    }

    /**
     * Determina el nivel de identificación según los datos disponibles.
     */
    private function calcularNivelIdentificacion(): string
    {
        if (! empty($this->valorDocumento)) {
            return 'identificado';
        }

        if (! empty($this->nombre) && ! empty($this->fechaNacimiento)) {
            return 'probable';
        }

        // Solo posible en contexto PSH sin ningún dato de identidad
        return 'no_identificado';
    }

    public function render(): View
    {
        return view('ciudadania::livewire.alta-ciudadano');
    }
}
