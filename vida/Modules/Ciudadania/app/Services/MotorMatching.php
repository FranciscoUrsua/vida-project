<?php

namespace Modules\Ciudadania\Services;

use App\Models\Ciudadano;
use App\Models\Scopes\AmbitoUoScope;
use Illuminate\Support\Collection;
use Modules\Ciudadania\Models\CiudadanoIdentificador;

/**
 * Motor de detección de posibles duplicados.
 *
 * Recibe datos normalizados de un ciudadano aún no guardado y devuelve
 * una colección de ResultadoMatching ordenada por score descendente.
 * Solo incluye resultados con score >= umbral_minimo.
 *
 * Algoritmo determinista sin IA: Jaro-Winkler para similitud de cadenas.
 * Ver docs/instrucciones-cli/instrucciones-cli-alta-ciudadano.md § Tarea 3.
 *
 * TODO: reemplazar búsqueda por nombre (carga ≤ 500) por índice hash
 *       determinista cuando esté disponible.
 */
class MotorMatching
{
    /**
     * Busca posibles duplicados del ciudadano descrito por $datosNormalizados.
     *
     * Orden de prioridad:
     *   1. Documento exacto (hash SHA-256) → bloqueo, no continúa.
     *   2. Fecha nacimiento + apellidos → Jaro-Winkler.
     *   3. Teléfono o email exacto (hash SHA-256).
     *
     * @param array<string, mixed> $datosNormalizados Salida de NormalizadorCiudadano::normalizar().
     * @return Collection<int, ResultadoMatching>
     */
    public function buscar(array $datosNormalizados): Collection
    {
        $resultados      = collect();
        $umbralMinimo    = (float) configuracion_sistema('matching.umbral_minimo', 0.60);
        $umbralBloqueo   = (float) configuracion_sistema('matching.umbral_bloqueo', 0.90);

        // Paso 1: documento exacto
        if (!empty($datosNormalizados['valor_documento'])) {
            $hash = hash('sha256', strtolower($datosNormalizados['valor_documento']));
            $id   = CiudadanoIdentificador::where('valor_hash', $hash)
                ->value('ciudadano_id');

            if ($id !== null) {
                $ciudadano = Ciudadano::withoutGlobalScope(AmbitoUoScope::class)->find($id);

                if ($ciudadano !== null) {
                    $resultados->push(new ResultadoMatching(
                        ciudadanoId:       $ciudadano->id,
                        nombreCompleto:    $ciudadano->nombre_completo,
                        documento:         $datosNormalizados['valor_documento'],
                        fechaNacimiento:   $ciudadano->fecha_nacimiento,
                        score:             1.0,
                        camposCoincidentes: ['documento'],
                        bloquea:           true,
                    ));

                    return $resultados; // bloqueo: no evaluar más
                }
            }
        }

        // Paso 2: fecha_nacimiento + apellidos (Jaro-Winkler)
        $tieneFecha     = !empty($datosNormalizados['fecha_nacimiento']);
        $tieneApellido1 = !empty($datosNormalizados['apellido1']);
        $tieneApellido2 = !empty($datosNormalizados['apellido2']);

        if ($tieneFecha && ($tieneApellido1 || $tieneApellido2)) {
            $fechaBuscada   = $datosNormalizados['fecha_nacimiento'];
            $apellido1Norm  = mb_strtolower($datosNormalizados['apellido1'] ?? '');
            $apellido2Norm  = mb_strtolower($datosNormalizados['apellido2'] ?? '');
            $nombreNorm     = mb_strtolower($datosNormalizados['nombre'] ?? '');

            // TODO: reemplazar por búsqueda hash cuando exista índice fecha_nacimiento_hash
            $candidatos = Ciudadano::withoutGlobalScope(AmbitoUoScope::class)
                ->take(500)
                ->get();

            foreach ($candidatos as $ciudadano) {
                try {
                    if ($ciudadano->fecha_nacimiento !== $fechaBuscada) {
                        continue;
                    }

                    $a1Cand = mb_strtolower($ciudadano->apellido1 ?? '');
                    $nCand  = mb_strtolower($ciudadano->nombre ?? '');

                    $score              = 0.0;
                    $camposCoincidentes = ['fecha_nacimiento'];

                    if ($tieneApellido1) {
                        $jwA1 = $this->jaroWinkler($apellido1Norm, $a1Cand);

                        if ($jwA1 > 0.92) {
                            $score              = 0.85;
                            $camposCoincidentes[] = 'apellido1';
                        } elseif ($jwA1 > 0.80 && $nombreNorm !== '') {
                            $jwN = $this->jaroWinkler($nombreNorm, $nCand);
                            if ($jwN > 0.80) {
                                $score              = 0.70;
                                $camposCoincidentes[] = 'nombre';
                                $camposCoincidentes[] = 'apellido1';
                            }
                        } elseif ($jwA1 > 0.60 && $nombreNorm !== '') {
                            // Coincidencia débil — solo nombre + apellido1
                            $jwN = $this->jaroWinkler($nombreNorm, $nCand);
                            if ($jwN > 0.60) {
                                $score              = 0.40;
                                $camposCoincidentes = ['nombre', 'apellido1'];
                            }
                        }
                    }

                    if ($score >= $umbralMinimo) {
                        $resultados->push(new ResultadoMatching(
                            ciudadanoId:       $ciudadano->id,
                            nombreCompleto:    $ciudadano->nombre_completo,
                            documento:         null,
                            fechaNacimiento:   $ciudadano->fecha_nacimiento,
                            score:             $score,
                            camposCoincidentes: $camposCoincidentes,
                            bloquea:           $score >= $umbralBloqueo,
                        ));
                    }
                } catch (\Throwable) {
                    // Ciudadano con campos cifrados ilegibles — saltar
                    continue;
                }
            }
        }

        // Paso 3: teléfono o email exacto
        if (!empty($datosNormalizados['telefono'])) {
            $hashTel  = hash('sha256', preg_replace('/\s+/', '', $datosNormalizados['telefono']));
            $ciudadano = Ciudadano::withoutGlobalScope(AmbitoUoScope::class)
                ->where('telefono_hash', $hashTel)->first();

            if ($ciudadano !== null && !$resultados->contains('ciudadanoId', $ciudadano->id)) {
                $score = 0.75;
                $resultados->push(new ResultadoMatching(
                    ciudadanoId:       $ciudadano->id,
                    nombreCompleto:    $ciudadano->nombre_completo,
                    documento:         null,
                    fechaNacimiento:   $ciudadano->fecha_nacimiento,
                    score:             $score,
                    camposCoincidentes: ['telefono'],
                    bloquea:           $score >= $umbralBloqueo,
                ));
            }
        }

        if (!empty($datosNormalizados['email'])) {
            $hashEmail = hash('sha256', strtolower(trim($datosNormalizados['email'])));
            $ciudadano  = Ciudadano::withoutGlobalScope(AmbitoUoScope::class)
                ->where('email_hash', $hashEmail)->first();

            if ($ciudadano !== null && !$resultados->contains('ciudadanoId', $ciudadano->id)) {
                $score = 0.75;
                $resultados->push(new ResultadoMatching(
                    ciudadanoId:       $ciudadano->id,
                    nombreCompleto:    $ciudadano->nombre_completo,
                    documento:         null,
                    fechaNacimiento:   $ciudadano->fecha_nacimiento,
                    score:             $score,
                    camposCoincidentes: ['email'],
                    bloquea:           $score >= $umbralBloqueo,
                ));
            }
        }

        return $resultados->sortByDesc('score')->values();
    }

    /**
     * Calcula la similitud Jaro-Winkler entre dos cadenas [0.0 – 1.0].
     *
     * @param string $s1
     * @param string $s2
     * @return float
     */
    private function jaroWinkler(string $s1, string $s2): float
    {
        if ($s1 === $s2) {
            return 1.0;
        }

        $len1 = mb_strlen($s1);
        $len2 = mb_strlen($s2);

        if ($len1 === 0 || $len2 === 0) {
            return 0.0;
        }

        $matchWindow = max((int) floor(max($len1, $len2) / 2) - 1, 0);
        $s1Matches   = array_fill(0, $len1, false);
        $s2Matches   = array_fill(0, $len2, false);
        $matches     = 0;

        for ($i = 0; $i < $len1; $i++) {
            $start = max(0, $i - $matchWindow);
            $end   = min($i + $matchWindow + 1, $len2);

            for ($j = $start; $j < $end; $j++) {
                if ($s2Matches[$j] || mb_substr($s1, $i, 1) !== mb_substr($s2, $j, 1)) {
                    continue;
                }
                $s1Matches[$i] = true;
                $s2Matches[$j] = true;
                $matches++;
                break;
            }
        }

        if ($matches === 0) {
            return 0.0;
        }

        $transpositions = 0;
        $k = 0;

        for ($i = 0; $i < $len1; $i++) {
            if (!$s1Matches[$i]) {
                continue;
            }
            while (!$s2Matches[$k]) {
                $k++;
            }
            if (mb_substr($s1, $i, 1) !== mb_substr($s2, $k, 1)) {
                $transpositions++;
            }
            $k++;
        }

        $jaro = (
            ($matches / $len1) +
            ($matches / $len2) +
            (($matches - $transpositions / 2) / $matches)
        ) / 3;

        $prefixLen = 0;
        $maxPrefix = min(4, min($len1, $len2));

        for ($i = 0; $i < $maxPrefix; $i++) {
            if (mb_substr($s1, $i, 1) === mb_substr($s2, $i, 1)) {
                $prefixLen++;
            } else {
                break;
            }
        }

        return $jaro + ($prefixLen * 0.1 * (1 - $jaro));
    }
}
