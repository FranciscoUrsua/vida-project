<?php

namespace App\Services\Geocodificacion\Adaptadores;

use App\Enums\TipoNumeracion;
use App\Models\Callejero\CallejeroPortal;
use App\Services\Geocodificacion\Callejero\TextoBusqueda;
use App\Services\Geocodificacion\DireccionAnalizada;
use App\Services\Geocodificacion\GeocodificadorInterface;
use App\Services\Geocodificacion\ParserDireccion;
use App\Services\Geocodificacion\ResultadoGeocodificacion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Geocodificador local sobre el callejero oficial del Ayuntamiento de Madrid.
 *
 * Contrasta la dirección escrita con la copia local del callejero (`callejero_portales`,
 * comando `callejero:importar`): ninguna dirección de una persona sale de VIDA, porque
 * entre las personas atendidas hay víctimas de violencia de género y colectivos
 * protegidos (decisión 1 de las instrucciones).
 *
 * Solo normaliza cuando la dirección casa con un único número del callejero. Una
 * dirección ambigua (vía homónima sin clase, errata a la misma distancia de dos vías),
 * inexistente, sin número o de otro municipio queda sin normalizar, y la asignación de
 * centro por domicilio, pendiente: mejor un caso pendiente que una asignación
 * equivocada (decisión 3).
 *
 * @see docs/instrucciones-cli/2026-11-callejero-madrid.md
 */
class CallejeroMadridGeocodificador implements GeocodificadorInterface
{
    /** Identificador del adaptador en `geocoder.proveedor` y `geocoder_proveedor`. */
    public const IDENTIFICADOR = 'callejero';

    /** Partículas que el callejero guarda aparte del nombre («Avenida de la Albufera» → ALBUFERA). */
    private const PARTICULAS = '/^(de las|de los|de la|del|de)\s+/';

    /** Palabras que no van en mayúscula inicial en el nombre para mostrar. */
    private const PALABRAS_MENORES = ['de', 'del', 'la', 'las', 'los', 'el', 'y', 'a', 'al', 'en'];

    /**
     * Crea el adaptador.
     *
     * @param ParserDireccion $parser Análisis del texto, compartido con el simulador.
     */
    public function __construct(private readonly ParserDireccion $parser = new ParserDireccion) {}

    /**
     * Normaliza la dirección si casa con un único número del callejero.
     *
     * @param string $direccionTexto Texto libre tal como lo escribe el profesional.
     *
     * @return ResultadoGeocodificacion Nunca lanza: un error inesperado es un fallo.
     */
    public function normalizar(string $direccionTexto): ResultadoGeocodificacion
    {
        try {
            return $this->casar($this->parser->analizar($direccionTexto));
        } catch (Throwable $e) {
            Log::warning('Geocodificador del callejero: '.$e->getMessage());

            // Un error de la BD sí puede resolverse reintentando
            return ResultadoGeocodificacion::fallo(self::IDENTIFICADOR, 'Error al consultar el callejero.', reintentable: true);
        }
    }

    /**
     * Busca el número del callejero que corresponde a la dirección analizada.
     *
     * @param DireccionAnalizada $direccion
     *
     * @return ResultadoGeocodificacion
     */
    private function casar(DireccionAnalizada $direccion): ResultadoGeocodificacion
    {
        if ($direccion->numero === null || ! in_array($direccion->tipoNumeracion, [TipoNumeracion::Numero, TipoNumeracion::Km], true)) {
            return $this->fallo('Sin número: no se puede saber el portal.');
        }

        if ($direccion->localidad !== null && TextoBusqueda::normalizar($direccion->localidad) !== 'madrid') {
            return $this->fallo("Fuera del municipio de Madrid ({$direccion->localidad}).");
        }

        if ($direccion->codigoPostal !== null && ! CallejeroPortal::where('codigo_postal', $direccion->codigoPostal)->exists()) {
            return $this->fallo("El código postal {$direccion->codigoPostal} no es del municipio de Madrid.");
        }

        $nombres = $this->formasDelNombre($direccion->nombreVia);

        if ($nombres === []) {
            return $this->fallo('Sin nombre de vía.');
        }

        $portales = $this->portales($direccion, $nombres)->get();

        // Solo se buscan erratas si no existe ninguna vía con ese nombre exacto: «Mayor 99»
        // no debe acabar en «Mayo 99» porque la calle Mayor no tenga el 99.
        if ($portales->isEmpty() && ! $this->vias($direccion, $nombres)->exists()) {
            $portales = $this->porSimilitud($direccion, $nombres);
        }

        if ($portales->isEmpty()) {
            return $this->fallo('Dirección no encontrada en el callejero.');
        }

        if ($portales->count() > 1) {
            return $this->fallo("Dirección ambigua: casa con {$portales->count()} números del callejero.");
        }

        return self::resultadoDePortal($portales->first(), $direccion->tipoVia, $direccion->portal, $direccion->escalera, $direccion->piso, $direccion->puerta);
    }

    /**
     * Formas en que el nombre escrito puede estar en el callejero: tal cual y sin partícula.
     *
     * @param string $nombre Nombre escrito («de la Albufera»).
     *
     * @return list<string> Formas normalizadas, sin repetir.
     */
    private function formasDelNombre(string $nombre): array
    {
        $normalizado = TextoBusqueda::normalizar($nombre);
        $sinParticula = trim((string) preg_replace(self::PARTICULAS, '', $normalizado));

        return array_values(array_unique(array_filter([$normalizado, $sinParticula], fn (string $n) => $n !== '')));
    }

    /**
     * Vías con alguno de esos nombres (y la clase escrita, si la hay).
     *
     * @param DireccionAnalizada $direccion
     * @param list<string> $nombres Nombres normalizados.
     *
     * @return Builder<CallejeroPortal>
     */
    private function vias(DireccionAnalizada $direccion, array $nombres): Builder
    {
        return CallejeroPortal::query()
            ->whereIn('nombre_via_busqueda', $nombres)
            ->when($direccion->tipoVia !== null, fn (Builder $q) => $q->whereRaw(
                'lower(clase_via) = ?',
                // Sin tildes en los dos lados: el fichero escribe TRAVESIA, CALLEJON…
                [TextoBusqueda::normalizar((string) $direccion->tipoVia)],
            ));
    }

    /**
     * Números de esas vías que coinciden con el número y el calificador escritos.
     *
     * @param DireccionAnalizada $direccion
     * @param list<string> $nombres Nombres normalizados.
     *
     * @return Builder<CallejeroPortal>
     */
    private function portales(DireccionAnalizada $direccion, array $nombres): Builder
    {
        [$numero, $calificador] = $this->numeroYCalificador($direccion);

        return $this->vias($direccion, $nombres)
            ->where('clase_numero', $direccion->tipoNumeracion)
            ->where('numero', $numero)
            ->when(
                $calificador === null,
                fn (Builder $q) => $q->whereNull('calificador'),
                fn (Builder $q) => $q->where('calificador', $calificador),
            );
    }

    /**
     * Busca la vía admitiendo una errata, solo si hay un único nombre más cercano.
     *
     * Usa los trigramas para preseleccionar y la distancia de edición (con
     * transposiciones) para decidir. La similitud por trigramas sola no basta: «Mayro»
     * está más cerca de «Mayo» que de «Mayor» por trigramas, y las dos calles existen.
     *
     * @param DireccionAnalizada $direccion
     * @param list<string> $nombres Nombres normalizados.
     *
     * @return Collection<int, CallejeroPortal> Vacía si no hay candidato; con varios si es ambigua.
     */
    private function porSimilitud(DireccionAnalizada $direccion, array $nombres): Collection
    {
        $umbral = (float) config('geocodificacion.callejero.umbral_similitud', 0.3);
        $distancias = [];

        foreach ($nombres as $nombre) {
            $maximo = $this->erratasAdmitidas($nombre);

            if ($maximo === 0) {
                continue;
            }

            // El operador % usa el índice trigram; el umbral explícito lo hace configurable
            $candidatos = DB::table('callejero_portales')
                ->whereRaw('nombre_via_busqueda % ?', [$nombre])
                ->whereRaw('similarity(nombre_via_busqueda, ?) >= ?', [$nombre, $umbral])
                ->distinct()
                ->limit(50)
                ->pluck('nombre_via_busqueda');

            foreach ($candidatos as $candidato) {
                $distancia = self::distancia($nombre, (string) $candidato);

                if ($distancia <= $maximo) {
                    $distancias[(string) $candidato] = min($distancias[(string) $candidato] ?? PHP_INT_MAX, $distancia);
                }
            }
        }

        // Solo cuentan las vías que tienen ese número: el número también desempata
        $conNumero = collect($distancias)->filter(fn (int $d, string $nombre) => $this->portales($direccion, [$nombre])->exists());

        if ($conNumero->isEmpty()) {
            return collect();
        }

        $mejores = $conNumero->filter(fn (int $d) => $d === $conNumero->min())->keys()->all();

        // Con varios nombres empatados se devuelven todos sus números: la dirección es ambigua
        return $this->portales($direccion, $mejores)->get();
    }

    /**
     * Erratas que se admiten según la longitud del nombre: ninguna en nombres cortos.
     *
     * @param string $nombre Nombre normalizado.
     *
     * @return int
     */
    private function erratasAdmitidas(string $nombre): int
    {
        $largo = strlen($nombre);

        return match (true) {
            $largo < 5 => 0,
            $largo < 10 => 1,
            default => 2,
        };
    }

    /**
     * Distancia de edición con transposiciones (alineamiento óptimo de cadenas).
     *
     * «mayro» → «mayor» es 1 (dos letras cambiadas de orden), igual que «mayro» → «mayo».
     *
     * @param string $a Texto normalizado (ASCII).
     * @param string $b Texto normalizado (ASCII).
     *
     * @return int
     */
    public static function distancia(string $a, string $b): int
    {
        $la = strlen($a);
        $lb = strlen($b);
        $d = [];

        for ($i = 0; $i <= $la; $i++) {
            $d[$i] = [$i];
        }

        for ($j = 0; $j <= $lb; $j++) {
            $d[0][$j] = $j;
        }

        for ($i = 1; $i <= $la; $i++) {
            for ($j = 1; $j <= $lb; $j++) {
                $coste = $a[$i - 1] === $b[$j - 1] ? 0 : 1;
                $d[$i][$j] = min($d[$i - 1][$j] + 1, $d[$i][$j - 1] + 1, $d[$i - 1][$j - 1] + $coste);

                if ($i > 1 && $j > 1 && $a[$i - 1] === $b[$j - 2] && $a[$i - 2] === $b[$j - 1]) {
                    $d[$i][$j] = min($d[$i][$j], $d[$i - 2][$j - 2] + 1);
                }
            }
        }

        return $d[$la][$lb];
    }

    /**
     * Número sin «bis» y calificador escritos, en la forma del callejero.
     *
     * @param DireccionAnalizada $direccion
     *
     * @return array{0: string, 1: string|null}
     */
    private function numeroYCalificador(DireccionAnalizada $direccion): array
    {
        $numero = (string) $direccion->numero;

        if (str_ends_with($numero, ' bis')) {
            return [ltrim(substr($numero, 0, -4), '0') ?: '0', 'BIS'];
        }

        // El callejero guarda los kilómetros sin decimales
        $soloCifras = (string) preg_replace('/\D/', '', $numero);

        return [ltrim($soloCifras, '0') ?: '0', $direccion->calificador];
    }

    /**
     * Resultado con los datos de un número del callejero y las partes del domicilio que
     * no están en él (portal, escalera, piso, puerta). Lo usan este adaptador y el
     * autocompletado del domicilio.
     *
     * @param CallejeroPortal $portal Número del callejero.
     * @param string|null $tipoVia Tipo de vía como se escribió; si no, el del callejero.
     * @param string|null $portalInterior Portal dentro del número.
     * @param string|null $escalera
     * @param string|null $piso
     * @param string|null $puerta
     *
     * @return ResultadoGeocodificacion
     */
    public static function resultadoDePortal(
        CallejeroPortal $portal,
        ?string $tipoVia = null,
        ?string $portalInterior = null,
        ?string $escalera = null,
        ?string $piso = null,
        ?string $puerta = null,
    ): ResultadoGeocodificacion {
        return new ResultadoGeocodificacion(
            exito: true,
            tipoVia: $tipoVia ?? self::claseParaMostrar($portal->clase_via),
            // Con la partícula, que es parte del nombre oficial: «Paseo de la Castellana»
            nombreVia: trim(mb_strtolower((string) $portal->particula_via).' '.self::nombreParaMostrar($portal->nombre_via_mostrar)),
            tipoNumeracion: $portal->clase_numero,
            numero: $portal->numero.($portal->calificador !== null ? ' '.$portal->calificador : ''),
            portal: $portalInterior,
            escalera: $escalera,
            piso: $piso,
            puerta: $puerta,
            codigoPostal: $portal->codigo_postal,
            municipio: 'Madrid',
            latitud: $portal->lat,
            longitud: $portal->lng,
            proveedor: self::IDENTIFICADOR,
            codigoNdp: $portal->cod_ndp,
            codigoDistrito: $portal->distrito_codigo,
            codigoBarrio: $portal->barrio_codigo,
            seccionCensal: $portal->seccion_censal_codigo,
        );
    }

    /**
     * Clase de vía del callejero para mostrar («TRAVESIA» → «Travesía»).
     *
     * @param string $clase Clase como viene en el fichero, en mayúsculas sin tildes.
     *
     * @return string
     */
    public static function claseParaMostrar(string $clase): string
    {
        $conTilde = ['TRAVESIA' => 'Travesía', 'CALLEJON' => 'Callejón', 'AUTOVIA' => 'Autovía', 'GALERIA' => 'Galería'];

        return $conTilde[$clase] ?? mb_convert_case(mb_strtolower($clase), MB_CASE_TITLE);
    }

    /**
     * Nombre en mayúsculas del callejero con mayúscula inicial («ABAD JUAN CATALÁN» →
     * «Abad Juan Catalán», «DOS DE MAYO» → «Dos de Mayo»).
     *
     * @param string $nombre
     *
     * @return string
     */
    public static function nombreParaMostrar(string $nombre): string
    {
        $palabras = explode(' ', mb_convert_case(mb_strtolower($nombre), MB_CASE_TITLE));

        foreach ($palabras as $i => $palabra) {
            if ($i > 0 && in_array(mb_strtolower($palabra), self::PALABRAS_MENORES, true)) {
                $palabras[$i] = mb_strtolower($palabra);
            }
        }

        return implode(' ', $palabras);
    }

    /**
     * Resultado de fallo de este adaptador.
     *
     * @param string $motivo
     *
     * @return ResultadoGeocodificacion
     */
    private function fallo(string $motivo): ResultadoGeocodificacion
    {
        return ResultadoGeocodificacion::fallo(self::IDENTIFICADOR, $motivo);
    }
}
