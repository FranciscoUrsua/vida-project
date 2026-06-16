<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$sourceRoots = [
    $root.'/vida/app',
    $root.'/vida/Modules',
];
$outputPath = $root.'/docs/codigo-phpdoc.md';

$files = [];
foreach ($sourceRoots as $sourceRoot) {
    if (! is_dir($sourceRoot)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sourceRoot, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php' && str_contains($file->getPathname(), '/app/')) {
            $files[] = $file->getPathname();
        }
    }
}

sort($files);

$entities = [];
$warnings = [];

foreach ($files as $file) {
    $relative = ltrim(str_replace($root.'/', '', $file), '/');
    $tokens = token_get_all(file_get_contents($file));
    $namespace = '';
    $currentEntity = null;
    $lastDoc = null;
    $lastDocLine = null;
    $count = count($tokens);

    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];

        if (is_array($token) && $token[0] === T_NAMESPACE) {
            $namespace = readNamespace($tokens, $i + 1);
            continue;
        }

        if (is_array($token) && $token[0] === T_DOC_COMMENT) {
            $lastDoc = $token[1];
            $lastDocLine = $token[2];
            continue;
        }

        if (is_array($token) && in_array($token[0], [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true)) {
            if (previousSignificantToken($tokens, $i) === T_DOUBLE_COLON) {
                continue;
            }

            $name = readNameAfter($tokens, $i + 1);
            if ($name === null) {
                continue;
            }

            $kind = strtolower(token_name($token[0]));
            $kind = str_replace('t_', '', $kind);
            $doc = consumeNearbyDoc($lastDoc, $lastDocLine, $token[2]);
            $fqcn = trim($namespace.'\\'.$name, '\\');
            $currentEntity = [
                'kind' => $kind,
                'name' => $fqcn,
                'short_name' => $name,
                'file' => $relative,
                'line' => $token[2],
                'doc' => parseDoc($doc),
                'methods' => [],
            ];

            if ($doc === null) {
                $warnings[] = warning('Clase sin PHPDoc', $relative, $token[2], $fqcn, 'Falta docblock de cabecera.');
            }

            $entities[] = $currentEntity;
            $currentEntity = count($entities) - 1;
            $lastDoc = null;
            $lastDocLine = null;
            continue;
        }

        if (is_array($token) && $token[0] === T_FUNCTION && $currentEntity !== null) {
            $name = readFunctionNameAfter($tokens, $i + 1);
            if ($name === null) {
                continue;
            }

            $visibility = readVisibilityBefore($tokens, $i);
            $signature = readSignature($tokens, $i);
            $doc = consumeNearbyDoc($lastDoc, $lastDocLine, $token[2]);
            $parsedDoc = parseDoc($doc);
            $method = [
                'name' => $name,
                'line' => $token[2],
                'visibility' => $visibility,
                'signature' => $signature,
                'doc' => $parsedDoc,
            ];
            $entities[$currentEntity]['methods'][] = $method;

            if ($visibility === 'public') {
                $params = readParameters($signature);

                if ($doc === null) {
                    $warnings[] = warning('Método público sin PHPDoc', $relative, $token[2], $entities[$currentEntity]['name'].'::'.$name.'()', 'Falta docblock de método público.');
                } else {
                    $paramTags = array_keys($parsedDoc['tags']['param'] ?? []);
                    foreach ($params as $param) {
                        if (! in_array($param, $paramTags, true)) {
                            $warnings[] = warning('PHPDoc incompleto', $relative, $token[2], $entities[$currentEntity]['name'].'::'.$name.'()', 'Falta @param $'.$param.'.');
                        }
                    }

                    if (! in_array($name, ['__construct', '__destruct'], true) && ! isset($parsedDoc['tags']['return'])) {
                        $warnings[] = warning('PHPDoc incompleto', $relative, $token[2], $entities[$currentEntity]['name'].'::'.$name.'()', 'Falta @return.');
                    }
                }
            }

            $lastDoc = null;
            $lastDocLine = null;
        }
    }
}

$markdown = renderMarkdown($entities, $warnings);
file_put_contents($outputPath, $markdown);

echo 'Generated '.$outputPath.PHP_EOL;
echo count($entities).' documented symbols scanned'.PHP_EOL;
echo count($warnings).' warnings'.PHP_EOL;

function readNamespace(array $tokens, int $start): string
{
    $parts = '';
    for ($i = $start, $count = count($tokens); $i < $count; $i++) {
        $token = $tokens[$i];
        if ($token === ';' || $token === '{') {
            break;
        }
        if (is_array($token) && in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NS_SEPARATOR], true)) {
            $parts .= $token[1];
        }
    }

    return $parts;
}

function readNameAfter(array $tokens, int $start): ?string
{
    for ($i = $start, $count = count($tokens); $i < $count; $i++) {
        $token = $tokens[$i];
        if (is_array($token) && $token[0] === T_STRING) {
            return $token[1];
        }
        if ($token === '{') {
            return null;
        }
    }

    return null;
}

function readFunctionNameAfter(array $tokens, int $start): ?string
{
    for ($i = $start, $count = count($tokens); $i < $count; $i++) {
        $token = $tokens[$i];
        if ($token === '&') {
            continue;
        }
        if (is_array($token) && $token[0] === T_STRING) {
            return $token[1];
        }
        if ($token === '(') {
            return null;
        }
    }

    return null;
}

function previousSignificantToken(array $tokens, int $index): ?int
{
    for ($i = $index - 1; $i >= 0; $i--) {
        $token = $tokens[$i];
        if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }
        if (is_array($token)) {
            return $token[0];
        }

        return null;
    }

    return null;
}

function readVisibilityBefore(array $tokens, int $index): string
{
    for ($i = $index - 1; $i >= 0; $i--) {
        $token = $tokens[$i];
        if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_STATIC, T_ABSTRACT, T_FINAL], true)) {
            continue;
        }
        if (is_array($token) && $token[0] === T_PUBLIC) {
            return 'public';
        }
        if (is_array($token) && $token[0] === T_PROTECTED) {
            return 'protected';
        }
        if (is_array($token) && $token[0] === T_PRIVATE) {
            return 'private';
        }

        break;
    }

    return 'public';
}

function readSignature(array $tokens, int $functionIndex): string
{
    $signature = '';
    $depth = 0;
    $started = false;

    for ($i = $functionIndex, $count = count($tokens); $i < $count; $i++) {
        $token = $tokens[$i];
        $text = is_array($token) ? $token[1] : $token;
        $signature .= $text;

        if ($text === '(') {
            $depth++;
            $started = true;
        } elseif ($text === ')') {
            $depth--;
        } elseif ($started && $depth === 0 && ($text === '{' || $text === ';')) {
            $signature = substr($signature, 0, -1);
            break;
        }
    }

    return trim(preg_replace('/\s+/', ' ', $signature));
}

function readParameters(string $signature): array
{
    if (! preg_match('/\((.*)\)/', $signature, $matches)) {
        return [];
    }

    preg_match_all('/\$([A-Za-z_][A-Za-z0-9_]*)/', $matches[1], $matches);

    return $matches[1];
}

function consumeNearbyDoc(?string $doc, ?int $docLine, int $symbolLine): ?string
{
    if ($doc === null || $docLine === null) {
        return null;
    }

    $docLines = substr_count($doc, "\n") + 1;
    $docEndLine = $docLine + $docLines - 1;

    return $symbolLine - $docEndLine <= 3 ? $doc : null;
}

function parseDoc(?string $doc): array
{
    if ($doc === null) {
        return ['summary' => '', 'description' => '', 'tags' => []];
    }

    $lines = preg_split('/\R/', $doc);
    $body = [];
    $tags = [];

    foreach ($lines as $line) {
        $line = preg_replace('/^\s*\/\*\*|\s*\*\/\s*$/', '', $line);
        $line = preg_replace('/^\s*\*\s?/', '', $line);
        $line = trim($line);

        if ($line === '') {
            $body[] = '';
            continue;
        }

        if (preg_match('/^@([A-Za-z][A-Za-z0-9_-]*)\s*(.*)$/', $line, $matches)) {
            $tag = $matches[1];
            $value = trim($matches[2]);
            if ($tag === 'param' && preg_match('/\$([A-Za-z_][A-Za-z0-9_]*)/', $value, $paramMatch)) {
                $tags['param'][$paramMatch[1]] = $value;
            } else {
                $tags[$tag][] = $value;
            }
            continue;
        }

        $body[] = $line;
    }

    $body = trim(implode("\n", $body));
    $paragraphs = preg_split('/\n\s*\n/', $body);
    $summary = trim((string) ($paragraphs[0] ?? ''));
    $description = trim(implode("\n\n", array_slice($paragraphs, 1)));

    return ['summary' => $summary, 'description' => $description, 'tags' => $tags];
}

function warning(string $type, string $file, int $line, string $symbol, string $detail): array
{
    return compact('type', 'file', 'line', 'symbol', 'detail');
}

function renderMarkdown(array $entities, array $warnings): string
{
    $now = gmdate('Y-m-d H:i:s').' UTC';
    $publicMethods = 0;
    $documentedPublicMethods = 0;
    $classesWithDoc = 0;

    foreach ($entities as $entity) {
        if ($entity['doc']['summary'] !== '') {
            $classesWithDoc++;
        }
        foreach ($entity['methods'] as $method) {
            if ($method['visibility'] === 'public') {
                $publicMethods++;
                if ($method['doc']['summary'] !== '') {
                    $documentedPublicMethods++;
                }
            }
        }
    }

    $out = [];
    $out[] = '# Documentacion de codigo PHP';
    $out[] = '';
    $out[] = 'Generado el '.$now.' a partir de los docblocks PHPDoc compatibles con PHODoc.';
    $out[] = '';
    $out[] = '## Resumen';
    $out[] = '';
    $out[] = '- Ambito: `vida/app` y `vida/Modules/*/app`.';
    $out[] = '- Simbolos escaneados: '.count($entities).'.';
    $out[] = '- Cabeceras documentadas: '.$classesWithDoc.'/'.count($entities).'.';
    $out[] = '- Metodos publicos documentados: '.$documentedPublicMethods.'/'.$publicMethods.'.';
    $out[] = '- Alertas de comentarios: '.count($warnings).'.';
    $out[] = '';
    $out[] = '## Alertas';
    $out[] = '';

    if ($warnings === []) {
        $out[] = 'No se han detectado carencias basicas en clases ni metodos publicos.';
    } else {
        $grouped = [];
        foreach ($warnings as $warning) {
            $grouped[$warning['type']][] = $warning;
        }

        foreach ($grouped as $type => $items) {
            $out[] = '### '.$type.' ('.count($items).')';
            $out[] = '';
            foreach ($items as $warning) {
                $out[] = '- `'.$warning['symbol'].'` en `'.$warning['file'].':'.$warning['line'].'`: '.$warning['detail'];
            }
            $out[] = '';
        }
    }

    $out[] = '## Referencia';
    $out[] = '';

    foreach ($entities as $entity) {
        $out[] = '### `'.$entity['name'].'`';
        $out[] = '';
        $out[] = '- Tipo: '.$entity['kind'].'.';
        $out[] = '- Fichero: `'.$entity['file'].':'.$entity['line'].'`.';
        $out[] = '- Resumen: '.($entity['doc']['summary'] !== '' ? normalizeText($entity['doc']['summary']) : '_Sin resumen PHPDoc._');
        if ($entity['doc']['description'] !== '') {
            $out[] = '';
            $out[] = normalizeText($entity['doc']['description']);
        }

        $public = array_values(array_filter(
            $entity['methods'],
            static fn (array $method): bool => $method['visibility'] === 'public'
        ));

        if ($public !== []) {
            $out[] = '';
            $out[] = 'Metodos publicos:';
            $out[] = '';
            foreach ($public as $method) {
                $out[] = '- `'.$method['signature'].'`';
                $summary = $method['doc']['summary'] !== '' ? normalizeText($method['doc']['summary']) : '_Sin resumen PHPDoc._';
                $out[] = '  '.$summary;
                if (isset($method['doc']['tags']['return'][0])) {
                    $out[] = '  `@return` '.$method['doc']['tags']['return'][0];
                }
                if (isset($method['doc']['tags']['throws'])) {
                    foreach ($method['doc']['tags']['throws'] as $throws) {
                        $out[] = '  `@throws` '.$throws;
                    }
                }
            }
        }

        $out[] = '';
    }

    return implode("\n", $out)."\n";
}

function normalizeText(string $text): string
{
    return str_replace("\n", ' ', trim($text));
}
