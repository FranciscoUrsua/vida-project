<?php

namespace App\Support\Ui;

use RuntimeException;
use Symfony\Component\Finder\Finder;

/**
 * Auditor de estilos de las superficies operativa y pública.
 *
 * Comprueba que las vistas Blade y el SCSS propio usan solo Bootstrap, sin
 * estilos inventados ni clases huérfanas. Reglas:
 *
 * - R1: toda clase usada en una vista existe en el CSS compilado de su bundle
 *   (o es un gancho del catálogo). Dentro de un atributo de clase, cada `{{ }}`
 *   se resuelve en literales (también en ternarios y `??`) o en una llamada a
 *   un método `clases…()`; las clases de esos métodos salen de fuentes que
 *   implementan {@see FuenteClasesCss}, y todas las que declaran tienen que
 *   existir en el CSS del bundle operativo.
 * - R2: sin estilos inline salvo valores dinámicos; sin bloques `<style>`.
 * - R3: sin colores literales en Blade ni en SCSS (salvo ficheros de tokens) y
 *   sin `var(--…)` que no estén definidas.
 * - R4: toda clase propia definida en SCSS está en el catálogo, toda entrada del
 *   catálogo está definida, y ninguna sigue `pendiente`.
 * - R5: toda clase del catálogo se usa en alguna vista, clase PHP o JS.
 * - R6: ninguna entrada de Vite del ámbito carga Tailwind.
 * - R7: las clases exigidas (`clases_exigidas`) existen en el CSS compilado de
 *   su bundle. Protege lo que ninguna vista usa como literal, como la familia
 *   del color de tema `protected`, que desaparece sin error de Sass si se
 *   reordenan los imports de `_bootstrap-vida.scss`.
 *
 * No toca la base de datos. Necesita el CSS compilado (`npm run build`).
 *
 * @see docs/instrucciones-cli/2026-09-bootstrap-unico.md §3
 */
final class AuditorUi
{
    /** Nombre de clase CSS válido (incluye formas Tailwind como `md:flex` o `w-1/2`). */
    private const PATRON_TOKEN_CLASE = '/^-?[A-Za-z_][\w:\/.\[\]%#-]*$/';

    /** Marcador que sustituye a `#{…}` en SCSS para no romper el conteo de llaves. */
    private const INTERPOLACION = '__INTERP__';

    private string $base;

    /** @var array<string, array<string, true>> Clases del CSS compilado, por bundle. */
    private array $clasesBundle = [];

    /** @var array<string, true> Variables CSS definidas en algún CSS compilado o vista. */
    private array $variablesDefinidas = [];

    /** @var array<string, true> Clases del CSS de Bootstrap sin modificar. */
    private array $clasesBootstrap = [];

    /** @var list<Infraccion> */
    private array $hallazgos = [];

    /**
     * @param array<string, mixed> $config Configuración `ui-auditoria` (con `base` opcional).
     * @param array<string, array<string, string>> $catalogo Clases del catálogo: nombre => [tipo, estado, descripcion].
     */
    public function __construct(
        private readonly array $config,
        private readonly array $catalogo,
    ) {
        $this->base = rtrim((string) ($config['base'] ?? base_path()), '/');
    }

    /**
     * Ejecuta la auditoría completa o limitada a las vistas de un módulo.
     *
     * Con `$modulo` solo se aplican R1 a R3 sobre las vistas de ese módulo
     * (`app` para las de `resources/views`): R4 a R6 son globales.
     *
     * @param string|null $modulo Nombre del módulo o `app`.
     *
     * @return list<Infraccion>
     *
     * @throws RuntimeException Si falta el CSS compilado o el de Bootstrap.
     */
    public function auditar(?string $modulo = null): array
    {
        $this->hallazgos = [];
        $this->cargarCssCompilado();
        $this->clasesBootstrap = $this->clasesDeCss($this->leerObligatorio(
            (string) $this->config['bootstrap_css'],
            'No se encuentra el CSS de Bootstrap. Ejecuta `npm ci`.',
        ));

        $vistas = $this->vistasDelAmbito();

        // Las variables definidas en estilos inline de cualquier vista cuentan como definidas.
        foreach ($vistas as $vista) {
            foreach ($this->atributos($vista['contenido'], 'style') as [$valor]) {
                foreach ($this->variablesDeclaradas($valor) as $variable) {
                    $this->variablesDefinidas[$variable] = true;
                }
            }
        }

        foreach ($vistas as $vista) {
            if ($modulo !== null && strcasecmp($vista['modulo'], $modulo) !== 0) {
                continue;
            }
            $this->auditarVista($vista);
        }

        if ($modulo === null) {
            $definidas = $this->auditarScss();
            $this->auditarCatalogo($definidas);
            $this->auditarUso();
            $this->auditarTailwind();
            $this->auditarClasesExigidas();
            $this->auditarFuentesClases();
        }

        return $this->hallazgos;
    }

    /**
     * Clases propias (no Bootstrap) definidas en los parciales SCSS, con su fichero.
     *
     * Sirve para generar o revisar el catálogo.
     *
     * @return array<string, string> clase => fichero relativo
     *
     * @throws RuntimeException Si falta el CSS de Bootstrap.
     */
    public function clasesPropiasDefinidas(): array
    {
        $this->clasesBootstrap = $this->clasesDeCss($this->leerObligatorio(
            (string) $this->config['bootstrap_css'],
            'No se encuentra el CSS de Bootstrap. Ejecuta `npm ci`.',
        ));

        $clases = [];
        foreach ($this->parcialesScss() as $relativa => $contenido) {
            foreach ($this->clasesDefinidasEnScss($contenido) as $clase => $linea) {
                if (! isset($this->clasesBootstrap[$clase])) {
                    $clases[$clase] ??= $relativa;
                }
            }
        }
        ksort($clases);

        return $clases;
    }

    // -------------------------------------------------------------------------
    // Carga de CSS compilado y vistas
    // -------------------------------------------------------------------------

    /**
     * Lee el manifest de Vite y extrae clases y variables del CSS de cada bundle.
     *
     * @throws RuntimeException Si falta el manifest o el CSS de una entrada.
     * @return void
     */
    private function cargarCssCompilado(): void
    {
        $manifest = json_decode($this->leerObligatorio(
            (string) $this->config['manifest'],
            'No hay CSS compilado: ejecuta `npm run build` antes de `ui:auditar`.',
        ), true);

        $this->clasesBundle = [];
        $this->variablesDefinidas = [];

        foreach ($this->config['bundles'] as $nombre => $bundle) {
            $fichero = $manifest[$bundle['entrada']]['file'] ?? null;
            if ($fichero === null) {
                throw new RuntimeException("El manifest no tiene la entrada {$bundle['entrada']}: ejecuta `npm run build`.");
            }
            $css = $this->leerObligatorio(
                dirname((string) $this->config['manifest']).'/'.$fichero,
                "Falta el CSS compilado de {$bundle['entrada']}: ejecuta `npm run build`.",
            );
            $this->clasesBundle[$nombre] = $this->clasesDeCss($css);
            foreach ($this->variablesDeclaradas($css) as $variable) {
                $this->variablesDefinidas[$variable] = true;
            }
        }
    }

    /**
     * Vistas Blade del ámbito, con su bundle y su módulo.
     *
     * @return list<array{ruta: string, contenido: string, bundle: string, modulo: string}>
     */
    private function vistasDelAmbito(): array
    {
        $vistas = [];
        foreach ($this->config['directorios_vistas'] as $directorio) {
            if (! is_dir("{$this->base}/{$directorio}")) {
                continue;
            }
            $finder = (new Finder)->files()->in("{$this->base}/{$directorio}")->name('*.blade.php')->sortByName();
            foreach ($finder as $fichero) {
                $relativa = $directorio.'/'.str_replace('\\', '/', $fichero->getRelativePathname());
                if ($this->encaja($relativa, $this->config['excluir'])) {
                    continue;
                }
                $bundle = $this->bundleDe($relativa);
                if ($bundle === null) {
                    continue;
                }
                $vistas[] = [
                    'ruta' => $relativa,
                    'contenido' => $fichero->getContents(),
                    'bundle' => $bundle,
                    'modulo' => preg_match('#^Modules/([^/]+)/#', $relativa, $m) ? $m[1] : 'app',
                ];
            }
        }

        return $vistas;
    }

    /**
     * Primer bundle cuyo patrón de vistas encaja con la ruta.
     *
     * @return ?string
     */
    private function bundleDe(string $relativa): ?string
    {
        foreach ($this->config['bundles'] as $nombre => $bundle) {
            if ($this->encaja($relativa, $bundle['vistas'])) {
                return $nombre;
            }
        }

        return null;
    }

    // -------------------------------------------------------------------------
    // R1 a R3 sobre vistas
    // -------------------------------------------------------------------------

    /**
     * Aplica R1, R2 y R3 a una vista.
     *
     * @param array{ruta: string, contenido: string, bundle: string, modulo: string} $vista
     * @return void
     */
    private function auditarVista(array $vista): void
    {
        $contenido = $this->sinComentariosBlade($vista['contenido']);
        $ruta = $vista['ruta'];
        $modulo = $vista['modulo'];
        $clasesValidas = $this->clasesBundle[$vista['bundle']];
        $informadas = [];

        // R1: clases de atributos class, wire:*.class, :class, @class y 'class' => '…'
        foreach ($this->clasesUsadas($contenido) as [$clase, $offset, $tipo]) {
            $linea = $this->linea($contenido, $offset);
            $clave = "{$tipo}:{$clase}".($tipo === 'opaca' ? ":{$linea}" : '');
            if (isset($informadas[$clave])) {
                continue;
            }
            $informadas[$clave] = true;

            if ($tipo === 'dinamica') {
                $this->anotar('R1', $ruta, $linea, "clase construida de forma dinámica: {$clase}…", $modulo, true);

                continue;
            }
            if ($tipo === 'opaca') {
                $this->anotar('R1', $ruta, $linea, "no se puede comprobar {{ {$clase} }}: usa literales o un método clases…() de una FuenteClasesCss", $modulo);

                continue;
            }
            $esGancho = ($this->catalogo[$clase]['tipo'] ?? null) === 'gancho';
            if (! isset($clasesValidas[$clase]) && ! $esGancho) {
                $this->anotar('R1', $ruta, $linea, $clase, $modulo);
            }
        }

        // R2 y R3 en atributos style
        foreach ($this->atributos($contenido, 'style') as [$valor, $offset]) {
            $linea = $this->linea($contenido, $offset);
            foreach ($this->declaraciones($valor) as $declaracion) {
                if (! str_contains($declaracion, '{{') && ! str_contains($declaracion, '{!!')) {
                    $this->anotar('R2', $ruta, $linea, $declaracion, $modulo);
                }
            }
            $this->coloresLiterales($valor, $ruta, $linea, $modulo);
        }

        // R2 y R3 en bloques <style>
        if (preg_match_all('#<style\b[^>]*>(.*?)</style>#si', $contenido, $bloques, PREG_OFFSET_CAPTURE)) {
            foreach ($bloques[1] as [$css, $offset]) {
                $linea = $this->linea($contenido, $offset);
                $this->anotar('R2', $ruta, $linea, 'bloque <style> en la vista', $modulo);
                $this->coloresLiterales($css, $ruta, $linea, $modulo);
            }
        }

        // R3: colores en atributos de presentación SVG/HTML
        if (preg_match_all('/\b(?:fill|stroke|stop-color|color|bgcolor)\s*=\s*"(#[0-9a-fA-F]{3,8})\b/', $contenido, $m, PREG_OFFSET_CAPTURE)) {
            foreach ($m[1] as [$color, $offset]) {
                $this->anotar('R3', $ruta, $this->linea($contenido, $offset), "color literal {$color}", $modulo);
            }
        }

        // R3: variables CSS usadas en cualquier parte de la vista
        if (preg_match_all('/var\(\s*(--[\w-]+)/', $contenido, $m, PREG_OFFSET_CAPTURE)) {
            foreach ($m[1] as [$variable, $offset]) {
                if (! isset($this->variablesDefinidas[$variable])) {
                    $this->anotar('R3', $ruta, $this->linea($contenido, $offset), "variable no definida {$variable}", $modulo);
                }
            }
        }
    }

    /**
     * Colores hexadecimales y rgb()/hsl() con valores literales dentro de CSS.
     *
     * @return void
     */
    private function coloresLiterales(string $css, string $ruta, int $linea, string $ambito): void
    {
        $css = preg_replace('/\{\{.*?\}\}|\{!!.*?!!\}/s', '', $css) ?? $css;
        if (preg_match_all('/#[0-9a-fA-F]{3,8}\b|(?:rgba?|hsla?)\(\s*[\d.][^)]*\)/', $css, $m)) {
            foreach ($m[0] as $color) {
                $this->anotar('R3', $ruta, $linea, "color literal {$color}", $ambito);
            }
        }
    }

    /**
     * Clases literales usadas en una vista, con su posición.
     *
     * Devuelve también los prefijos de clases construidas dinámicamente
     * (`badge-{{ $tipo }}`), de tipo `dinamica`, y las interpolaciones que no se
     * pueden comprobar, de tipo `opaca` (con la expresión en lugar de la clase).
     *
     * @return list<array{0: string, 1: int, 2: string}> [clase, offset, tipo: clase|dinamica|opaca]
     */
    private function clasesUsadas(string $contenido): array
    {
        $clases = [];

        // class="…" y wire:loading.class="…" (no :class ni x-bind:class, que son JS)
        $patron = '/(?<![\w:.-])(?:wire:[\w.-]*\.)?class\s*=\s*"([^"]*)"/';
        if (preg_match_all($patron, $contenido, $m, PREG_OFFSET_CAPTURE)) {
            foreach ($m[1] as [$valor, $offset]) {
                foreach ($this->clasesDeValorBlade($valor) as [$clase, $tipo]) {
                    $clases[] = [$clase, $offset, $tipo];
                }
            }
        }

        // :class="{ 'a': cond }" y x-bind:class: las claves literales de objeto o array
        if (preg_match_all('/(?:x-bind)?:class\s*=\s*"([^"]*)"/', $contenido, $m, PREG_OFFSET_CAPTURE)) {
            foreach ($m[1] as [$valor, $offset]) {
                foreach ($this->literalesDeExpresion($valor) as $literal) {
                    foreach (preg_split('/\s+/', trim($literal)) ?: [] as $clase) {
                        $clases[] = [$clase, $offset, 'clase'];
                    }
                }
            }
        }

        // @class([...])
        if (preg_match_all('/@class\s*\(/', $contenido, $m, PREG_OFFSET_CAPTURE)) {
            foreach ($m[0] as [$inicio, $offset]) {
                $argumentos = $this->entreParentesis($contenido, $offset + strlen($inicio) - 1);
                foreach ($this->clasesDeArrayPhp($argumentos) as $clase) {
                    $clases[] = [$clase, $offset, 'clase'];
                }
            }
        }

        // 'class' => '…' en @php, componentes y $attributes->merge()
        if (preg_match_all("/'class'\s*=>\s*'([^']*)'/", $contenido, $m, PREG_OFFSET_CAPTURE)) {
            foreach ($m[1] as [$valor, $offset]) {
                foreach (preg_split('/\s+/', trim($valor)) ?: [] as $clase) {
                    $clases[] = [$clase, $offset, 'clase'];
                }
            }
        }

        return array_values(array_filter(
            $clases,
            fn (array $c) => $c[0] !== '' && ($c[2] !== 'clase' || preg_match(self::PATRON_TOKEN_CLASE, $c[0]) === 1),
        ));
    }

    /**
     * Clases de un valor de atributo class con Blade dentro.
     *
     * Los literales de `{{ }}` que actúan como resultado (tras `?`, `:`, `=>`…)
     * se tratan como clases; las directivas (`@if`, `@error`…) se ignoran; un
     * texto pegado a `{{` o `}}` es una clase dinámica. Una expresión cuyo
     * resultado no se puede comprobar se devuelve entera, como `opaca`.
     *
     * @return list<array{0: string, 1: string}> [clase, tipo: clase|dinamica|opaca]
     */
    private function clasesDeValorBlade(string $valor): array
    {
        $clases = [];

        $valor = preg_replace_callback('/\{\{(.*?)\}\}|\{!!(.*?)!!\}/s', function (array $m) use (&$clases) {
            $expresion = $m[1] !== '' ? $m[1] : ($m[2] ?? '');
            if (! $this->esResultadoComprobable($expresion)) {
                $clases[] = [trim($expresion), 'opaca'];
            }
            foreach ($this->literalesDeExpresion($expresion) as $literal) {
                foreach (preg_split('/\s+/', trim($literal)) ?: [] as $clase) {
                    $clases[] = [$clase, 'clase'];
                }
            }

            return self::INTERPOLACION;
        }, $valor) ?? $valor;

        // Directivas Blade dentro del atributo: @if(...), @endif, @error('x')…
        $valor = preg_replace('/@\w+(\s*\((?:[^()]|\((?:[^()]|\([^()]*\))*\))*\))?/', ' ', $valor) ?? $valor;

        foreach (preg_split('/\s+/', trim($valor)) ?: [] as $token) {
            if ($token === '') {
                continue;
            }
            if (str_contains($token, self::INTERPOLACION)) {
                $prefijo = explode(self::INTERPOLACION, $token)[0];
                if ($prefijo !== '') {
                    $clases[] = [$prefijo, 'dinamica'];
                }

                continue;
            }
            $clases[] = [$token, 'clase'];
        }

        return $clases;
    }

    /**
     * Indica si todo lo que puede devolver una expresión de un atributo de clase
     * es comprobable: un literal, una llamada a un método `clases…()` (sus clases
     * se comprueban en su {@see FuenteClasesCss}), o un ternario o `??` cuyas
     * ramas lo son. Las condiciones de los ternarios no cuentan.
     *
     * @param string $expresion Código PHP de dentro de `{{ }}`.
     * @return bool
     */
    private function esResultadoComprobable(string $expresion): bool
    {
        $tokens = @token_get_all('<?php '.$expresion.';');
        $tokens = array_values(array_filter(
            $tokens,
            fn ($t) => ! is_array($t) || ! in_array($t[0], [T_OPEN_TAG, T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true),
        ));
        array_pop($tokens);

        return $this->tokensComprobables($tokens);
    }

    /**
     * Parte recursiva de {@see esResultadoComprobable()} sobre tokens de PHP.
     *
     * @param list<string|array{0: int, 1: string, 2: int}> $tokens
     * @return bool
     */
    private function tokensComprobables(array $tokens): bool
    {
        // Paréntesis que envuelven toda la expresión
        while (count($tokens) >= 2 && $tokens[0] === '(' && $this->cierreDe($tokens, 0) === count($tokens) - 1) {
            $tokens = array_slice($tokens, 1, -1);
        }
        if ($tokens === []) {
            return false;
        }

        // Ternario de nivel superior (en PHP 8 los anidados van entre paréntesis)
        $pregunta = $this->posicionNivelSuperior($tokens, fn ($t) => $t === '?');
        if ($pregunta !== null) {
            $resto = array_slice($tokens, $pregunta + 1);
            $dosPuntos = $this->posicionNivelSuperior($resto, fn ($t) => $t === ':');
            if ($dosPuntos === null) {
                return false;
            }
            $siVerdadero = array_slice($resto, 0, $dosPuntos);
            if ($siVerdadero === []) {
                // `a ?: b` devuelve la propia condición
                $siVerdadero = array_slice($tokens, 0, $pregunta);
            }

            return $this->tokensComprobables($siVerdadero)
                && $this->tokensComprobables(array_slice($resto, $dosPuntos + 1));
        }

        // `a ?? b`: puede salir cualquiera de las dos partes
        $coalesce = $this->posicionNivelSuperior($tokens, fn ($t) => is_array($t) && $t[0] === T_COALESCE);
        if ($coalesce !== null) {
            return $this->tokensComprobables(array_slice($tokens, 0, $coalesce))
                && $this->tokensComprobables(array_slice($tokens, $coalesce + 1));
        }

        if (count($tokens) === 1) {
            return is_array($tokens[0]) && $tokens[0][0] === T_CONSTANT_ENCAPSED_STRING;
        }

        // Llamada final a un método clases…(): `$x->clasesSuave()`, `Clase::clasesPunto($v)`
        $ultimo = count($tokens) - 1;
        if ($tokens[$ultimo] !== ')') {
            return false;
        }
        $apertura = $this->aperturaDe($tokens, $ultimo);
        $nombre = $tokens[$apertura - 1] ?? null;
        $operador = $tokens[$apertura - 2] ?? null;

        return is_array($nombre) && $nombre[0] === T_STRING && preg_match('/^clases[A-Z]/', $nombre[1]) === 1
            && is_array($operador) && in_array($operador[0], [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON], true);
    }

    /**
     * Primera posición de nivel superior (fuera de paréntesis, corchetes y
     * llaves) cuyo token cumple la condición.
     *
     * @param list<string|array{0: int, 1: string, 2: int}> $tokens
     * @param callable(string|array{0: int, 1: string, 2: int}): bool $condicion
     * @return int|null
     */
    private function posicionNivelSuperior(array $tokens, callable $condicion): ?int
    {
        $nivel = 0;
        foreach ($tokens as $i => $token) {
            if (in_array($token, ['(', '[', '{'], true)) {
                $nivel++;
            } elseif (in_array($token, [')', ']', '}'], true)) {
                $nivel--;
            } elseif ($nivel === 0 && $condicion($token)) {
                return $i;
            }
        }

        return null;
    }

    /**
     * Posición del paréntesis que cierra el que abre en `$inicio`.
     *
     * @param list<string|array{0: int, 1: string, 2: int}> $tokens
     * @return int|null
     */
    private function cierreDe(array $tokens, int $inicio): ?int
    {
        $nivel = 0;
        for ($i = $inicio, $n = count($tokens); $i < $n; $i++) {
            $nivel += match ($tokens[$i]) { '(' => 1, ')' => -1, default => 0 };
            if ($nivel === 0) {
                return $i;
            }
        }

        return null;
    }

    /**
     * Posición del paréntesis que abre el que cierra en `$fin`.
     *
     * @param list<string|array{0: int, 1: string, 2: int}> $tokens
     * @return int
     */
    private function aperturaDe(array $tokens, int $fin): int
    {
        $nivel = 0;
        for ($i = $fin; $i >= 0; $i--) {
            $nivel += match ($tokens[$i]) { ')' => 1, '(' => -1, default => 0 };
            if ($nivel === 0) {
                return $i;
            }
        }

        return 0;
    }

    /**
     * Literales de cadena de una expresión PHP/JS que actúan como valor devuelto.
     *
     * Se descartan los que se comparan (`=== 'x'`), los que son claves de `match`
     * o de array seguidas de `=>` y los argumentos de funciones (`route('x')`).
     * En objetos JS (`{ 'a': cond }`) se toman las claves.
     *
     * @return list<string>
     */
    private function literalesDeExpresion(string $expresion): array
    {
        $literales = [];
        if (! preg_match_all('/([\'"])((?:(?!\1)[^\\\\]|\\\\.)*)\1/', $expresion, $m, PREG_OFFSET_CAPTURE)) {
            return [];
        }
        foreach ($m[0] as $i => [$completo, $offset]) {
            $antes = rtrim(substr($expresion, 0, $offset));
            $despues = ltrim(substr($expresion, $offset + strlen($completo)));
            $previo = $antes === '' ? '' : substr($antes, -1);

            $esClaveObjetoJs = in_array($previo, ['{', ','], true) && str_starts_with($despues, ':');
            $esResultado = in_array($previo, ['', '?', ':', '>', '.'], true)
                && ! str_starts_with($despues, '=')
                && ! str_starts_with($despues, '!')
                && ! ($previo === '>' && ! str_ends_with($antes, '=>'));

            if ($esClaveObjetoJs || $esResultado) {
                $literales[] = $m[2][$i][0];
            }
        }

        return $literales;
    }

    /**
     * Clases de los argumentos de `@class([...])`: claves de los pares
     * `'clase' => condición` y valores sueltos.
     *
     * @return list<string>
     */
    private function clasesDeArrayPhp(string $argumentos): array
    {
        $interior = trim($argumentos);
        if (str_starts_with($interior, '[') && str_ends_with($interior, ']')) {
            $interior = substr($interior, 1, -1);
        }

        $clases = [];
        foreach ($this->dividirNivelSuperior($interior, ',') as $elemento) {
            $partes = $this->dividirNivelSuperior($elemento, '=>');
            $literal = trim($partes[0]);
            if (preg_match('/^([\'"])(.*)\1$/s', $literal, $m)) {
                foreach (preg_split('/\s+/', trim($m[2])) ?: [] as $clase) {
                    $clases[] = $clase;
                }
            }
        }

        return $clases;
    }

    // -------------------------------------------------------------------------
    // R3 y R4 sobre SCSS
    // -------------------------------------------------------------------------

    /**
     * Aplica R3 a los parciales SCSS y devuelve las clases propias definidas.
     *
     * @return array<string, array{0: string, 1: int}> clase => [fichero, línea]
     */
    private function auditarScss(): array
    {
        $tokens = $this->config['scss_tokens'];
        $propias = [];

        foreach ($this->parcialesScss() as $relativa => $contenido) {
            $limpio = $this->sinComentariosScss($contenido);

            if (! in_array(basename($relativa), $tokens, true)) {
                if (preg_match_all('/#[0-9a-fA-F]{3,8}\b|(?:rgba?|hsla?)\(\s*[\d.][^)]*\)/', $limpio, $m, PREG_OFFSET_CAPTURE)) {
                    foreach ($m[0] as [$color, $offset]) {
                        // `#{…}` es interpolación Sass, no un color
                        if (str_starts_with($color, '#') && ($limpio[$offset + 1] ?? '') === '{') {
                            continue;
                        }
                        $this->anotar('R3', $relativa, $this->linea($limpio, $offset), "color literal {$color}", 'scss');
                    }
                }
            }

            if (preg_match_all('/var\(\s*(--[\w-]+)/', $limpio, $m, PREG_OFFSET_CAPTURE)) {
                foreach ($m[1] as [$variable, $offset]) {
                    if (! isset($this->variablesDefinidas[$variable])) {
                        $this->anotar('R3', $relativa, $this->linea($limpio, $offset), "variable no definida {$variable}", 'scss');
                    }
                }
            }

            foreach ($this->clasesDefinidasEnScss($contenido) as $clase => $linea) {
                if (! isset($this->clasesBootstrap[$clase])) {
                    $propias[$clase] ??= [$relativa, $linea];
                }
            }
        }

        return $propias;
    }

    /**
     * R4: catálogo y SCSS deben coincidir, sin clases pendientes.
     *
     * @param array<string, array{0: string, 1: int}> $definidas
     * @return void
     */
    private function auditarCatalogo(array $definidas): void
    {
        foreach ($definidas as $clase => [$fichero, $linea]) {
            if (! isset($this->catalogo[$clase])) {
                $this->anotar('R4', $fichero, $linea, "{$clase} no está en config/ui-catalogo.php", 'scss');
            }
        }

        $pendientes = [];
        foreach ($this->catalogo as $clase => $entrada) {
            if (($entrada['tipo'] ?? null) !== 'gancho' && ! isset($definidas[$clase])) {
                $this->anotar('R4', 'config/ui-catalogo.php', 0, "{$clase} está en el catálogo pero no se define en el SCSS", 'catalogo');
            }
            if (($entrada['estado'] ?? null) === 'pendiente') {
                $pendientes[$definidas[$clase][0] ?? 'config/ui-catalogo.php'][] = $clase;
            }
        }

        // Una línea por fichero: el detalle de las pendientes está en el catálogo.
        foreach ($pendientes as $fichero => $clases) {
            $this->anotar('R4', $fichero, 0, count($clases) === 1
                ? "1 clase pendiente de revisar en el catálogo ({$clases[0]})"
                : count($clases).' clases pendientes de revisar en el catálogo', 'catalogo');
        }
    }

    /**
     * Clases que aparecen en los selectores de un SCSS, resolviendo el anidamiento
     * (`&__elemento`, `&--modificador`, `.padre .hijo`).
     *
     * @return array<string, int> clase => línea de la primera definición
     */
    private function clasesDefinidasEnScss(string $contenido): array
    {
        $codigo = $this->sinComentariosScss($contenido);
        $codigo = preg_replace_callback('/#\{[^}]*\}/', fn (array $m) => str_pad(self::INTERPOLACION, strlen($m[0]), '_'), $codigo) ?? $codigo;

        $clases = [];
        // Pila de marcos: selectores resueltos o null si el bloque no genera selectores (mixin, función).
        $pila = [[]];
        $buffer = '';
        $inicioBuffer = 0;
        $longitud = strlen($codigo);

        for ($i = 0; $i < $longitud; $i++) {
            $caracter = $codigo[$i];

            if ($caracter === '{') {
                $cabecera = trim($buffer);
                $padre = end($pila);

                if ($padre === null || preg_match('/^@(mixin|function)\b/', $cabecera)) {
                    $pila[] = null;
                } elseif (str_starts_with($cabecera, '@') || str_ends_with($cabecera, ':')) {
                    // @media, @include con bloque, @if…, o propiedad anidada: mismos selectores
                    $pila[] = $padre;
                } else {
                    $selectores = $this->resolverSelectores($padre, $cabecera);
                    $linea = $this->linea($codigo, $inicioBuffer + (strlen($buffer) - strlen(ltrim($buffer))));
                    foreach ($selectores as $selector) {
                        if (preg_match_all('/\.(-?[_a-zA-Z][\w-]*)/', $selector, $m)) {
                            foreach ($m[1] as $clase) {
                                if (! str_contains($clase, self::INTERPOLACION)) {
                                    $clases[$clase] ??= $linea;
                                }
                            }
                        }
                    }
                    $pila[] = $selectores;
                }
                $buffer = '';
                $inicioBuffer = $i + 1;
            } elseif ($caracter === '}') {
                if (count($pila) > 1) {
                    array_pop($pila);
                }
                $buffer = '';
                $inicioBuffer = $i + 1;
            } elseif ($caracter === ';') {
                $buffer = '';
                $inicioBuffer = $i + 1;
            } else {
                $buffer .= $caracter;
            }
        }

        return $clases;
    }

    /**
     * Combina los selectores del bloque padre con los de una regla anidada.
     *
     * @param list<string> $padres
     *
     * @return list<string>
     */
    private function resolverSelectores(array $padres, string $cabecera): array
    {
        $hijos = array_map('trim', $this->dividirNivelSuperior($cabecera, ','));
        if ($padres === []) {
            return $hijos;
        }

        $resueltos = [];
        foreach ($padres as $padre) {
            foreach ($hijos as $hijo) {
                $resueltos[] = str_contains($hijo, '&') ? str_replace('&', $padre, $hijo) : "{$padre} {$hijo}";
            }
        }

        return $resueltos;
    }

    // -------------------------------------------------------------------------
    // R5 y R6
    // -------------------------------------------------------------------------

    /**
     * R5: cada clase del catálogo aparece en alguna vista, clase PHP o JS.
     *
     * @return void
     */
    private function auditarUso(): void
    {
        $tokens = [];
        foreach ($this->config['directorios_uso'] as $directorio) {
            if (! is_dir("{$this->base}/{$directorio}")) {
                continue;
            }
            $finder = (new Finder)->files()->in("{$this->base}/{$directorio}")->name(['*.php', '*.js']);
            foreach ($finder as $fichero) {
                $relativa = $directorio.'/'.str_replace('\\', '/', $fichero->getRelativePathname());
                if ($this->encaja($relativa, $this->config['excluir_uso'] ?? [])) {
                    continue;
                }
                if (preg_match_all('/[\w-]+/', $fichero->getContents(), $m)) {
                    foreach ($m[0] as $token) {
                        $tokens[$token] = true;
                    }
                }
            }
        }

        foreach (array_keys($this->catalogo) as $clase) {
            if (! isset($tokens[$clase])) {
                $this->anotar('R5', 'config/ui-catalogo.php', 0, "{$clase} no se usa en ninguna vista, clase PHP ni JS", 'catalogo');
            }
        }
    }

    /**
     * R6: ninguna hoja del ámbito carga Tailwind.
     *
     * @return void
     */
    private function auditarTailwind(): void
    {
        $ficheros = [];
        foreach ($this->config['bundles'] as $bundle) {
            $ficheros[$bundle['entrada']] = (string) @file_get_contents("{$this->base}/{$bundle['entrada']}");
        }
        $ficheros += $this->parcialesScss();

        foreach ($ficheros as $relativa => $contenido) {
            $limpio = $this->sinComentariosScss($contenido);
            if (preg_match_all('/@import\s+[\'"]tailwindcss[\'"]|@tailwind\b|@source\b|@theme\b|@apply\b/', $limpio, $m, PREG_OFFSET_CAPTURE)) {
                foreach ($m[0] as [$directiva, $offset]) {
                    $this->anotar('R6', $relativa, $this->linea($limpio, $offset), "Tailwind: {$directiva}", 'scss');
                }
            }
        }
    }

    /**
     * R7: toda clase exigida existe en el CSS compilado de su bundle.
     *
     * @return void
     */
    private function auditarClasesExigidas(): void
    {
        foreach ($this->config['clases_exigidas'] ?? [] as $bundle => $clases) {
            foreach ($clases as $clase) {
                if (! isset($this->clasesBundle[$bundle][$clase])) {
                    $this->anotar('R7', 'config/ui-auditoria.php', 0, "«{$clase}» no está en el CSS compilado del bundle {$bundle}", 'scss');
                }
            }
        }
    }

    /**
     * R1 sobre las fuentes de clases: toda clase que declara una
     * {@see FuenteClasesCss} existe en el CSS compilado del bundle operativo.
     *
     * Las fuentes se localizan buscando `implements … FuenteClasesCss` en los
     * directorios de uso, de modo que una fuente nueva queda auditada sin
     * registrarla en ningún sitio.
     *
     * @return void
     */
    private function auditarFuentesClases(): void
    {
        $bundle = (string) ($this->config['bundle_fuentes'] ?? 'operativo');
        $clasesValidas = $this->clasesBundle[$bundle] ?? [];

        foreach ($this->config['directorios_uso'] as $directorio) {
            $ruta = "{$this->base}/{$directorio}";
            if (! is_dir($ruta)) {
                continue;
            }
            foreach (Finder::create()->files()->in($ruta)->name('*.php')->notName('*.blade.php') as $fichero) {
                $contenido = $fichero->getContents();
                if (! preg_match('/\bimplements\s+[\w\\\\,\s]*\bFuenteClasesCss\b/', $contenido)
                    || ! preg_match('/^\s*(?:final\s+|abstract\s+|readonly\s+)*(?:class|enum)\s+(\w+)/m', $contenido, $nombre)) {
                    continue;
                }
                $espacio = preg_match('/^namespace\s+([\w\\\\]+);/m', $contenido, $m) ? $m[1].'\\' : '';
                $fqcn = $espacio.$nombre[1];
                if (! class_exists($fqcn) && ! enum_exists($fqcn)) {
                    require_once $fichero->getRealPath();
                }
                $relativa = substr($fichero->getRealPath(), strlen((string) realpath($this->base)) + 1);

                foreach ($fqcn::clasesCss() as $clase) {
                    if (! isset($clasesValidas[$clase]) && ($this->catalogo[$clase]['tipo'] ?? null) !== 'gancho') {
                        $posicion = strpos($contenido, $clase);
                        $linea = $posicion === false ? 0 : $this->linea($contenido, $posicion);
                        $this->anotar('R1', $relativa, $linea, $clase, 'php');
                    }
                }
            }
        }
    }

    // -------------------------------------------------------------------------
    // Utilidades
    // -------------------------------------------------------------------------

    /**
     * Parciales SCSS propios (`_*.scss`), con ruta relativa.
     *
     * @return array<string, string> ruta relativa => contenido
     */
    private function parcialesScss(): array
    {
        $directorio = (string) $this->config['scss'];
        $parciales = [];
        foreach (glob("{$this->base}/{$directorio}/_*.scss") ?: [] as $fichero) {
            $parciales[$directorio.'/'.basename($fichero)] = (string) file_get_contents($fichero);
        }
        ksort($parciales);

        return $parciales;
    }

    /**
     * Clases que aparecen en los selectores de un CSS compilado.
     *
     * @return array<string, true>
     */
    private function clasesDeCss(string $css): array
    {
        $css = preg_replace('#/\*.*?\*/#s', '', $css) ?? $css;
        // Solo selectores: se quitan los bloques de declaraciones.
        $selectores = preg_replace('/\{[^{}]*\}/', ' ', $css) ?? $css;
        $clases = [];
        if (preg_match_all('/\.(-?[_a-zA-Z](?:[\w-]|\\\\.)*)/', $selectores, $m)) {
            foreach ($m[1] as $clase) {
                $clases[stripslashes($clase)] = true;
            }
        }

        return $clases;
    }

    /**
     * Nombres de variables CSS declaradas (`--nombre:`) en un texto.
     *
     * @return list<string>
     */
    private function variablesDeclaradas(string $css): array
    {
        return preg_match_all('/(--[\w-]+)\s*:/', $css, $m) ? $m[1] : [];
    }

    /**
     * Valores de un atributo HTML con su offset (no los enlazados con `:` de Alpine).
     *
     * @return list<array{0: string, 1: int}>
     */
    private function atributos(string $contenido, string $nombre): array
    {
        $patron = '/(?<![\w:.-])'.preg_quote($nombre, '/').'\s*=\s*"([^"]*)"/';

        return preg_match_all($patron, $contenido, $m, PREG_OFFSET_CAPTURE) ? $m[1] : [];
    }

    /**
     * Declaraciones de un estilo inline, sin partir las expresiones `{{ }}`.
     *
     * @return list<string>
     */
    private function declaraciones(string $estilo): array
    {
        return array_values(array_filter(
            array_map('trim', $this->dividirNivelSuperior($estilo, ';')),
            fn (string $d) => $d !== '',
        ));
    }

    /**
     * Divide un texto por un separador ignorando lo que va entre paréntesis,
     * corchetes, llaves o comillas.
     *
     * @return list<string>
     */
    private function dividirNivelSuperior(string $texto, string $separador): array
    {
        $partes = [];
        $actual = '';
        $nivel = 0;
        $comilla = null;
        $longitud = strlen($texto);
        $largoSeparador = strlen($separador);

        for ($i = 0; $i < $longitud; $i++) {
            $c = $texto[$i];
            if ($comilla !== null) {
                $actual .= $c;
                if ($c === '\\' && $i + 1 < $longitud) {
                    $actual .= $texto[++$i];
                } elseif ($c === $comilla) {
                    $comilla = null;
                }

                continue;
            }
            if ($c === '"' || $c === "'") {
                $comilla = $c;
            } elseif (in_array($c, ['(', '[', '{'], true)) {
                $nivel++;
            } elseif (in_array($c, [')', ']', '}'], true)) {
                $nivel--;
            } elseif ($nivel === 0 && substr($texto, $i, $largoSeparador) === $separador) {
                $partes[] = $actual;
                $actual = '';
                $i += $largoSeparador - 1;

                continue;
            }
            $actual .= $c;
        }
        $partes[] = $actual;

        return $partes;
    }

    /**
     * Contenido entre el paréntesis de apertura en `$apertura` y su cierre.
     *
     * @return string
     */
    private function entreParentesis(string $texto, int $apertura): string
    {
        $nivel = 0;
        $longitud = strlen($texto);
        for ($i = $apertura; $i < $longitud; $i++) {
            if ($texto[$i] === '(') {
                $nivel++;
            } elseif ($texto[$i] === ')' && --$nivel === 0) {
                return substr($texto, $apertura + 1, $i - $apertura - 1);
            }
        }

        return '';
    }

    /**
     * Quita comentarios Blade conservando los saltos de línea.
     *
     * @return string
     */
    private function sinComentariosBlade(string $contenido): string
    {
        return preg_replace_callback('/\{\{--.*?--\}\}/s', fn (array $m) => preg_replace('/[^\n]/', ' ', $m[0]) ?? '', $contenido) ?? $contenido;
    }

    /**
     * Quita comentarios SCSS (de bloque y de línea, salvo `//` en URLs) conservando los saltos de línea.
     *
     * @return string
     */
    private function sinComentariosScss(string $contenido): string
    {
        return preg_replace_callback('#/\*.*?\*/|(?<![:"\'])//[^\n]*#s', fn (array $m) => preg_replace('/[^\n]/', ' ', $m[0]) ?? '', $contenido) ?? $contenido;
    }

    /**
     * Número de línea (desde 1) de un offset.
     *
     * @return int
     */
    private function linea(string $texto, int $offset): int
    {
        return substr_count($texto, "\n", 0, min($offset, strlen($texto))) + 1;
    }

    /**
     * Si una ruta relativa encaja con alguno de los patrones fnmatch().
     *
     * @param list<string> $patrones
     * @return bool
     */
    private function encaja(string $ruta, array $patrones): bool
    {
        foreach ($patrones as $patron) {
            if (fnmatch($patron, $ruta)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Lee un fichero relativo a la base o lanza una excepción con `$mensaje`.
     *
     * @throws RuntimeException
     * @return string
     */
    private function leerObligatorio(string $relativa, string $mensaje): string
    {
        $ruta = "{$this->base}/{$relativa}";
        if (! is_file($ruta)) {
            throw new RuntimeException($mensaje);
        }

        return (string) file_get_contents($ruta);
    }

    /**
     * Registra un hallazgo.
     *
     * @return void
     */
    private function anotar(string $regla, string $fichero, int $linea, string $detalle, string $ambito, bool $esAviso = false): void
    {
        $this->hallazgos[] = new Infraccion($regla, $fichero, $linea, $detalle, $ambito, $esAviso);
    }
}
