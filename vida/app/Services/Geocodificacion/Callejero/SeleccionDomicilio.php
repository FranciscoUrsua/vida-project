<?php

namespace App\Services\Geocodificacion\Callejero;

use App\Enums\OrigenDireccion;
use App\Enums\TipoNumeroCallejero;
use App\Models\Callejero\CallejeroPortal;
use App\Models\Ciudadano;
use App\Services\Geocodificacion\Adaptadores\CallejeroMadridGeocodificador;
use Illuminate\Support\Facades\DB;
use Modules\Centro\Models\Centro;
use Modules\Organizacion\Models\Distrito;

/**
 * El domicilio se elige, no se adivina: búsqueda de vías y números del callejero oficial
 * y paso de la elección a los atributos de dirección de una persona.
 *
 * Un domicilio elegido en el callejero llega ya normalizado (origen `callejero`) y no
 * pasa por el geocodificador de texto. El texto libre queda como alternativa para
 * personas sin hogar, domicilios fuera de Madrid o direcciones que no estén en el
 * callejero; ese texto sí lo intenta casar el geocodificador (decisión 2 de las
 * instrucciones).
 *
 * El estado del campo es un array con las claves `portal_id`, `portal`, `escalera`,
 * `piso`, `puerta`, `texto` y `manual` (si se escribe a mano).
 *
 * @see docs/instrucciones-cli/2026-11-callejero-madrid.md
 */
class SeleccionDomicilio
{
    /** Caracteres mínimos para buscar vías. */
    public const MINIMO_BUSQUEDA = 3;

    /**
     * Si hay callejero cargado; sin él solo se puede escribir a mano.
     *
     * @return bool
     */
    public function hayCallejero(): bool
    {
        return CallejeroPortal::query()->exists();
    }

    /**
     * Estado vacío del campo de domicilio.
     *
     * @return array{portal_id: int|null, portal: string, escalera: string, piso: string, puerta: string, texto: string, manual: bool}
     */
    public function vacio(): array
    {
        return ['portal_id' => null, 'portal' => '', 'escalera' => '', 'piso' => '', 'puerta' => '', 'texto' => '', 'manual' => ! $this->hayCallejero()];
    }

    /**
     * Estado del campo a partir del domicilio guardado de una persona.
     *
     * Si se eligió en el callejero y el número sigue existiendo, vuelve a aparecer
     * elegido; si no, como texto libre.
     *
     * @param Ciudadano|Centro $modelo Entidad con el trait TieneDireccion.
     *
     * @return array{portal_id: int|null, portal: string, escalera: string, piso: string, puerta: string, texto: string, manual: bool}
     */
    public function desdeModelo(Ciudadano|Centro $modelo): array
    {
        $estado = $this->vacio();
        $estado['texto'] = (string) ($modelo->direccion_texto ?? '');

        $portal = $modelo->origen_direccion === OrigenDireccion::Callejero && $modelo->codigo_ndp
            ? CallejeroPortal::where('cod_ndp', $modelo->codigo_ndp)->first()
            : null;

        if ($portal === null) {
            $estado['manual'] = $estado['manual'] || $estado['texto'] !== '';

            return $estado;
        }

        return array_merge($estado, [
            'portal_id' => $portal->id,
            'portal' => (string) $modelo->portal,
            'escalera' => (string) $modelo->escalera,
            'piso' => (string) $modelo->piso,
            'puerta' => (string) $modelo->puerta,
            'manual' => false,
        ]);
    }

    /**
     * Vías cuyo nombre contiene el texto buscado, primero las que empiezan por él.
     *
     * @param string $texto Lo que escribe el profesional («albuf»).
     * @param int $limite
     *
     * @return list<array{cod_via: string, etiqueta: string, distritos: string}>
     */
    public function buscarVias(string $texto, int $limite = 12): array
    {
        $buscado = TextoBusqueda::normalizar($texto);

        if (mb_strlen($buscado) < self::MINIMO_BUSQUEDA) {
            return [];
        }

        // El LIKE con comodín inicial lo resuelve el índice trigram
        $filas = DB::table('callejero_portales')
            ->select('cod_via', 'clase_via', 'particula_via', 'nombre_via_mostrar', 'nombre_via_busqueda')
            ->selectRaw('string_agg(DISTINCT distrito_codigo, \',\' ORDER BY distrito_codigo) AS distritos')
            ->where('nombre_via_busqueda', 'like', '%'.addcslashes($buscado, '%_\\').'%')
            ->groupBy('cod_via', 'clase_via', 'particula_via', 'nombre_via_mostrar', 'nombre_via_busqueda')
            ->orderByRaw('nombre_via_busqueda NOT LIKE ?', [addcslashes($buscado, '%_\\').'%'])
            ->orderBy('nombre_via_busqueda')
            ->orderBy('clase_via')
            ->limit($limite)
            ->get();

        $nombresDistrito = Distrito::query()->pluck('nombre', 'codigo');

        return $filas->map(fn ($fila) => [
            'cod_via' => (string) $fila->cod_via,
            'etiqueta' => $this->etiquetaVia((string) $fila->clase_via, $fila->particula_via, (string) $fila->nombre_via_mostrar),
            'distritos' => collect(explode(',', (string) $fila->distritos))
                ->map(fn (string $codigo) => $nombresDistrito[$codigo] ?? $codigo)
                ->implode(', '),
        ])->all();
    }

    /**
     * Números de una vía, filtrados por lo que se escribe; en orden numérico y, dentro de
     * cada número, primero portales y parcelas (donde vive la gente).
     *
     * @param string $codVia
     * @param string $texto Número tecleado («12»), o vacío para los primeros.
     * @param int $limite
     *
     * @return list<array{id: int, etiqueta: string, tipo: string|null}>
     */
    public function numerosDeVia(string $codVia, string $texto = '', int $limite = 40): array
    {
        $cifras = (string) preg_replace('/\D/', '', $texto);

        return CallejeroPortal::query()
            ->where('cod_via', $codVia)
            ->when($cifras !== '', fn ($q) => $q->where('numero', 'like', $cifras.'%'))
            ->orderByRaw('CAST(numero AS integer)')
            ->orderByRaw('tipo_numero NOT IN (?, ?)', [TipoNumeroCallejero::Portal->value, TipoNumeroCallejero::Parcela->value])
            ->orderByRaw('calificador IS NOT NULL')
            ->orderBy('calificador')
            ->limit($limite)
            ->get()
            ->map(fn (CallejeroPortal $p) => [
                'id' => $p->id,
                'etiqueta' => $p->numero.($p->calificador !== null ? ' '.$p->calificador : '').($p->codigo_postal ? " · {$p->codigo_postal}" : ''),
                // Solo se indica cuando no es un portal
                'tipo' => $p->tipo_numero === TipoNumeroCallejero::Portal ? null : $p->tipo_numero->etiqueta(),
            ])
            ->all();
    }

    /**
     * Texto con el que se muestra una vía («Avenida de la Albufera»).
     *
     * @param string $clase
     * @param string|null $particula
     * @param string $nombre Nombre con tildes, en mayúsculas.
     *
     * @return string
     */
    public function etiquetaVia(string $clase, ?string $particula, string $nombre): string
    {
        // Sin partícula no queda un hueco doble («Calle Mayor»)
        return implode(' ', array_filter([
            CallejeroMadridGeocodificador::claseParaMostrar($clase),
            mb_strtolower(trim((string) $particula)),
            CallejeroMadridGeocodificador::nombreParaMostrar($nombre),
        ], fn (string $parte) => $parte !== ''));
    }

    /**
     * Atributos de dirección que corresponden al estado del campo.
     *
     * Con un número elegido: normalizada con los datos del callejero, origen `callejero` y
     * el texto compuesto a partir de lo elegido. Con texto libre: el texto, con origen
     * `profesional`, para que lo intente el geocodificador. Vacío: sin domicilio y sin
     * datos territoriales.
     *
     * @param array<string, mixed> $estado Estado del campo (ver la descripción de la clase).
     *
     * @return array<string, mixed>
     */
    public function atributos(array $estado): array
    {
        $portal = empty($estado['manual']) && ! empty($estado['portal_id'])
            ? CallejeroPortal::find((int) $estado['portal_id'])
            : null;

        if ($portal !== null) {
            $complemento = fn (string $clave) => trim((string) ($estado[$clave] ?? '')) ?: null;
            $resultado = CallejeroMadridGeocodificador::resultadoDePortal(
                $portal,
                portalInterior: $complemento('portal'),
                escalera: $complemento('escalera'),
                piso: $complemento('piso'),
                puerta: $complemento('puerta'),
            );

            return [
                'direccion_texto' => $this->textoDe($resultado->tipoVia, $resultado->nombreVia, $resultado->numero, $complemento('portal'), $complemento('escalera'), $complemento('piso'), $complemento('puerta')),
                'origen_direccion' => OrigenDireccion::Callejero,
                ...$resultado->atributosDireccion(),
            ];
        }

        $texto = trim((string) ($estado['texto'] ?? ''));

        if ($texto === '') {
            // Sin domicilio: no quedan códigos de un domicilio anterior
            return [
                'direccion_texto' => null,
                'origen_direccion' => null,
                'direccion_normalizada' => false,
                'geocoder_proveedor' => null,
                'codigo_ndp' => null,
                'distrito_codigo' => null,
                'barrio_codigo' => null,
                'seccion_censal_codigo' => null,
            ];
        }

        return ['direccion_texto' => $texto, 'origen_direccion' => OrigenDireccion::Profesional];
    }

    /**
     * Texto del domicilio elegido: «Avenida de la Albufera 120, esc. A, 3º izq».
     *
     * @param string|null $tipoVia
     * @param string|null $nombreVia
     * @param string|null $numero
     * @param string|null $portal
     * @param string|null $escalera
     * @param string|null $piso
     * @param string|null $puerta
     *
     * @return string
     */
    private function textoDe(?string $tipoVia, ?string $nombreVia, ?string $numero, ?string $portal, ?string $escalera, ?string $piso, ?string $puerta): string
    {
        $partes = [trim("{$tipoVia} {$nombreVia} {$numero}")];

        if ($portal !== null) {
            $partes[] = "portal {$portal}";
        }

        if ($escalera !== null) {
            $partes[] = "esc. {$escalera}";
        }

        $vivienda = trim(($piso !== null ? (ctype_digit($piso) ? "{$piso}º" : $piso) : '').' '.($puerta ?? ''));

        if ($vivienda !== '') {
            $partes[] = $vivienda;
        }

        return implode(', ', $partes);
    }
}
