<?php

declare(strict_types=1);

namespace ItPack\DeriveIt\Cli;

/**
 * Resolves the first declared type FQCN inside a PHP source file without
 * executing it (tokenizer scan), so the CLI can derive from a file path
 * directly (F-8 / R4-9 — extracted from {@see DeriveCommand::classFromFile()}).
 */
final readonly class FileTargetResolver
{
    public function resolve(string $file): string
    {
        $code = file_get_contents($file);
        if ($code === false) {
            throw new \RuntimeException(sprintf('cannot read file "%s".', $file));
        }

        $tokens = token_get_all($code);
        $namespace = '';
        $count = count($tokens);

        for ($i = 0; $i < $count; ++$i) {
            $token = $tokens[$i];
            if (!is_array($token)) {
                continue;
            }

            if ($token[0] === T_NAMESPACE) {
                $buffer = '';
                for ($j = $i + 1; $j < $count; ++$j) {
                    $next = $tokens[$j];
                    if ($next === ';' || $next === '{') {
                        break;
                    }
                    if (is_array($next)) {
                        $buffer .= $next[1];
                    }
                }
                $namespace = trim($buffer);

                continue;
            }

            if ($token[0] === T_DOUBLE_COLON) {
                continue;
            }

            if (in_array($token[0], [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true)) {
                for ($j = $i + 1; $j < $count; ++$j) {
                    $next = $tokens[$j];
                    if ($next === '{' || $next === '(') {
                        break;
                    }
                    if (is_array($next) && $next[0] === T_STRING) {
                        $name = $next[1];

                        return $namespace === '' ? $name : $namespace . '\\' . $name;
                    }
                }
            }
        }

        throw new \RuntimeException(sprintf('no class/interface/trait/enum declaration found in "%s".', $file));
    }
}
