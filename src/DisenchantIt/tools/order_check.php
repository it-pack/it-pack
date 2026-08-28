#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * tools/order_check.php — token-level class declaration-order checker.
 *
 * Enforces the DisenchantIt declaration-order convention recorded in
 * docs/REFACTORING_PLAN.md (Milestone M5) and docs/REVIEW.md §7:
 *
 *   trait use → constants → properties/hooks → constructor
 *            → factory methods → non-static methods → static methods
 *
 *   with "public-first within non-static methods".
 *
 * A *factory method* is a named static constructor: `create()`, `default()`,
 * or a `from*()` static factory. Enums have no constructor and cannot hold
 * properties, so for enums only `cases/constants must precede methods` is
 * enforced (their static constructors conventionally sit among the methods).
 *
 * This is a lightweight pre-commit linter, not a code-style formatter. It
 * parses with the PHP tokenizer (no external dependency) and scans `src/`.
 *
 * Exit code is non-zero when any violation is found, so it can be wired into
 * CI at the user's discretion (see docs/AGENTS.md).
 */

const FACTORY_NAMES = ['create', 'default'];

function isFactoryMethod(string $name): bool
{
    if (in_array($name, FACTORY_NAMES, true)) {
        return true;
    }

    return preg_match('/^from[A-Z]/', $name) === 1;
}

/**
 * @param list<array{0:int|string,1:string,2?:int}> $tokens
 * @return list<array{file:string,line:int,message:string}>
 */
function checkFile(string $file, array $tokens): array
{
    $violations = [];
    $depth = 0;
    $parenDepth = 0;
    $collecting = false;
    $classDepth = 0;
    $pendingClass = false;
    $pendingIsEnum = false;
    $currentIsEnum = false;
    $members = []; // list of ['rank'=>int,'line'=>int,'label'=>string]

    $n = count($tokens);

    for ($i = 0; $i < $n; $i++) {
        $tok = $tokens[$i];
        $code = is_array($tok) ? $tok[0] : $tok;
        $line = is_array($tok) ? ($tok[2] ?? 0) : 0;

        if ($code === '{' || $code === T_CURLY_OPEN) {
            $depth++;
            if ($code === '{' && $pendingClass) {
                $pendingClass = false;
                $collecting = true;
                $classDepth = $depth;
                $currentIsEnum = $pendingIsEnum;
                $members = [];
            }
            continue;
        }

        if ($code === '}') {
            if ($collecting && $depth <= $classDepth) {
                $violations = array_merge($violations, validateMembers($file, $members, $currentIsEnum));
                $collecting = false;
                $currentIsEnum = false;
                $members = [];
            }
            $depth--;
            continue;
        }

        if ($code === '(') {
            $parenDepth++;
            continue;
        }

        if ($code === ')') {
            if ($parenDepth > 0) {
                $parenDepth--;
            }
            continue;
        }

        if (!$collecting) {
            // Detect a type declaration opener (not `::class`).
            if (in_array($code, [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true)) {
                $prev = previousSignificant($tokens, $i);
                if ($prev !== T_DOUBLE_COLON) {
                    $pendingClass = true;
                    $pendingIsEnum = ($code === T_ENUM);
                }
            }
            continue;
        }

        // Inside a class body at the member depth.
        if ($depth !== $classDepth || $parenDepth > 0) {
            continue;
        }

        if ($code === T_USE) {
            // Trait use inside a class body.
            $members[] = ['rank' => 0, 'line' => $line, 'label' => 'trait use'];
            continue;
        }

        if ($code === T_CONST || $code === T_CASE) {
            $members[] = ['rank' => 1, 'line' => $line, 'label' => 'constant/case'];
            continue;
        }

        if ($code === T_VARIABLE) {
            $members[] = ['rank' => 2, 'line' => $line, 'label' => 'property'];
            continue;
        }

        if ($code === T_FUNCTION) {
            $name = '';
            for ($j = $i + 1; $j < $n; $j++) {
                $c2 = is_array($tokens[$j]) ? $tokens[$j][0] : $tokens[$j];
                if (in_array($c2, [T_WHITESPACE, T_DOC_COMMENT, T_COMMENT, '&'], true)) {
                    continue;
                }
                if ($c2 === '(') {
                    break;
                }
                // Method name: a T_STRING, or a reserved word used as a method
                // name (e.g. `default` tokenizes as T_DEFAULT, `list` as T_LIST).
                $name = is_array($tokens[$j]) ? $tokens[$j][1] : (string) $c2;
                break;
            }

            $isStatic = false;
            $visibility = T_PUBLIC;
            for ($k = $i - 1; $k >= 0; $k--) {
                $c3 = is_array($tokens[$k]) ? $tokens[$k][0] : $tokens[$k];
                if (in_array($c3, [T_FUNCTION, T_VARIABLE, T_CONST, T_CASE, T_USE, '{', '}', '('], true)) {
                    break;
                }
                if ($c3 === T_STATIC) {
                    $isStatic = true;
                } elseif (in_array($c3, [T_PUBLIC, T_PRIVATE, T_PROTECTED], true)) {
                    $visibility = $c3;
                }
            }

            $rank = rankForMethod($currentIsEnum, $isStatic, $name, $visibility);
            $members[] = [
                'rank' => $rank,
                'line' => $line,
                'label' => sprintf(
                    '%s%s method %s',
                    $isStatic ? 'static ' : '',
                    $visibility === T_PRIVATE ? 'private' : ($visibility === T_PROTECTED ? 'protected' : 'public'),
                    $name,
                ),
            ];
            continue;
        }
    }

    if ($collecting) {
        $violations = array_merge($violations, validateMembers($file, $members, $currentIsEnum));
    }

    return $violations;
}

/**
 * @param list<array{0:int|string,1:string,2?:int}> $tokens
 */
function previousSignificant(array $tokens, int $i): int|string|null
{
    for ($j = $i - 1; $j >= 0; $j--) {
        $c = is_array($tokens[$j]) ? $tokens[$j][0] : $tokens[$j];
        if (in_array($c, [T_WHITESPACE, T_DOC_COMMENT, T_COMMENT], true)) {
            continue;
        }
        return $c;
    }

    return null;
}

function rankForMethod(bool $isEnum, ?bool $isStatic, string $name, int $visibility): int
{
    if ($isEnum) {
        // Enums have no constructor and cannot hold properties; their static
        // constructors conventionally sit among the methods, so only the
        // constant/method boundary is enforced (handled by monotonic ranks).
        return 5;
    }

    if ($name === '__construct') {
        return 3;
    }

    if ($isStatic === true && isFactoryMethod($name)) {
        return 4;
    }

    if ($isStatic === false) {
        return 5;
    }

    return 6;
}

/**
 * @param list<array{rank:int,line:int,label:string}> $members
 * @return list<array{file:string,line:int,message:string}>
 */
function validateMembers(string $file, array $members, bool $isEnum): array
{
    $violations = [];

    // 1) Monotonic category ordering (trait use → … → static methods).
    $prevRank = -1;
    foreach ($members as $m) {
        if ($m['rank'] < $prevRank) {
            $violations[] = [
                'file' => $file,
                'line' => $m['line'],
                'message' => sprintf(
                    'declaration-order: %s (rank %d) appears after a member of higher rank %d',
                    $m['label'],
                    $m['rank'],
                    $prevRank,
                ),
            ];
        }
        $prevRank = max($prevRank, $m['rank']);
    }

    // 2) Public-first within non-static methods (rank 5). Enums commonly mix
    // private static helpers after public instance methods, so skip for them.
    if (!$isEnum) {
        $seenPrivateNonStatic = false;
        foreach ($members as $m) {
            if ($m['rank'] !== 5) {
                continue;
            }
            if (str_contains($m['label'], 'private')) {
                $seenPrivateNonStatic = true;
            } elseif ($seenPrivateNonStatic && str_contains($m['label'], 'public')) {
                $violations[] = [
                    'file' => $file,
                    'line' => $m['line'],
                    'message' => sprintf(
                        'declaration-order: public %s appears after a private non-static method',
                        $m['label'],
                    ),
                ];
            }
        }
    }

    return $violations;
}

function main(): int
{
    $root = 'src';
    if (!is_dir($root)) {
        fwrite(STDERR, "src/ not found in " . getcwd() . "\n");
        return 2;
    }

    $paths = [];
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
    foreach ($rii as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $paths[] = $file->getPathname();
        }
    }

    sort($paths);

    $all = [];
    foreach ($paths as $path) {
        $src = file_get_contents($path);
        if ($src === false) {
            continue;
        }
        $tokens = @token_get_all($src);
        $all = array_merge($all, checkFile($path, $tokens));
    }

    if ($all === []) {
        echo "order_check: 0 violations across " . count($paths) . " file(s)\n";
        return 0;
    }

    foreach ($all as $v) {
        echo sprintf("%s:%d: %s\n", $v['file'], $v['line'], $v['message']);
    }
    fwrite(STDERR, sprintf("\norder_check: %d violation(s) found\n", count($all)));

    return 1;
}

exit(main());
