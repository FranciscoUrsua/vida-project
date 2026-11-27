<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Cifra `datos` y `notas` de todas las fichas (decisión del desarrollador del 27 nov 2026,
 * ficha sociodemográfica, bloque C): son datos personales de ciudadanos y CLAUDE.md §3 exige
 * guardarlos cifrados sin excepción.
 *
 * `datos` pasa de jsonb a texto (el cast `encrypted:array` guarda el JSON cifrado) y se
 * cifran las filas existentes con el mismo formato que el cast. También se cifran esos dos
 * valores en las versiones ya guardadas de las fichas (`versiones`), que hasta ahora los
 * guardaban en claro. Nadie consultaba `fichas.datos` por SQL.
 *
 * @see docs/instrucciones-cli/2026-11-ficha-sociodemografica.md, bloque C
 */
return new class extends Migration
{
    /** Tipo polimórfico de las fichas en `versiones`. */
    private const TIPO = 'Modules\\Intervencion\\Models\\Ficha';

    /**
     * Convierte la columna y cifra fichas y versiones.
     *
     * @return void
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE fichas ALTER COLUMN datos TYPE text USING datos::text');

        DB::table('fichas')->orderBy('id')->select(['id', 'datos', 'notas'])->chunkById(200, function ($fichas): void {
            foreach ($fichas as $ficha) {
                DB::table('fichas')->where('id', $ficha->id)->update([
                    // Mismo formato que los casts `encrypted:array` y `encrypted` (encryptString)
                    'datos' => $ficha->datos === null ? null : Crypt::encryptString($ficha->datos),
                    'notas' => $ficha->notas === null ? null : Crypt::encryptString($ficha->notas),
                ]);
            }
        });

        $this->recorrerVersiones(function (array $datos): array {
            if (array_key_exists('datos', $datos) && $datos['datos'] !== null) {
                $datos['datos'] = Crypt::encryptString(is_string($datos['datos']) ? $datos['datos'] : json_encode($datos['datos']));
            }
            if (array_key_exists('notas', $datos) && $datos['notas'] !== null) {
                $datos['notas'] = Crypt::encryptString($datos['notas']);
            }

            return $datos;
        });
    }

    /**
     * Descifra y devuelve `datos` a jsonb.
     *
     * @return void
     */
    public function down(): void
    {
        DB::table('fichas')->orderBy('id')->select(['id', 'datos', 'notas'])->chunkById(200, function ($fichas): void {
            foreach ($fichas as $ficha) {
                DB::table('fichas')->where('id', $ficha->id)->update([
                    'datos' => $ficha->datos === null ? null : Crypt::decryptString($ficha->datos),
                    'notas' => $ficha->notas === null ? null : Crypt::decryptString($ficha->notas),
                ]);
            }
        });

        DB::statement('ALTER TABLE fichas ALTER COLUMN datos TYPE jsonb USING datos::jsonb');

        $this->recorrerVersiones(function (array $datos): array {
            if (is_string($datos['datos'] ?? null)) {
                $datos['datos'] = json_decode(Crypt::decryptString($datos['datos']), true);
            }
            if (is_string($datos['notas'] ?? null)) {
                $datos['notas'] = Crypt::decryptString($datos['notas']);
            }

            return $datos;
        });
    }

    /**
     * Aplica una transformación al JSON de cada versión de ficha.
     *
     * @param callable(array<string, mixed>): array<string, mixed> $transformar
     *
     * @return void
     */
    private function recorrerVersiones(callable $transformar): void
    {
        DB::table('versiones')->where('versionable_type', self::TIPO)->orderBy('id')->select(['id', 'datos'])
            ->chunkById(200, function ($versiones) use ($transformar): void {
                foreach ($versiones as $version) {
                    $datos = json_decode((string) $version->datos, true);
                    if (is_array($datos)) {
                        DB::table('versiones')->where('id', $version->id)->update(['datos' => json_encode($transformar($datos))]);
                    }
                }
            });
    }
};
