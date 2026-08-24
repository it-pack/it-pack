<?php

declare(strict_types=1);

namespace ItPack\DeriveIt\Cli;

use ItPack\DeriveIt\Contract\Cli\CommandInterface;
use ItPack\DeriveIt\DeriveIt;
use ItPack\DeriveIt\Generator\DeriveConfig;
use ItPack\DeriveIt\Generator\DeriveMode;
use ItPack\DeriveIt\Generator\Naming;

/**
 * Ad-hoc command-line driver for {@see DeriveIt} (Step 10.16 / G17).
 *
 * Exercises the public API end-to-end: derive a class or file to stdout, or to a
 * path / directory, in any of the supported modes (flat, per-trait, adapter,
 * family, family-adapter). Intentionally thin — all behavior lives here so it can
 * be unit-tested without a shell.
 *
 * Exit codes: {@see EXIT_SUCCESS} success · {@see EXIT_USAGE} usage error ·
 * {@see EXIT_DERIVATION} resolution / derivation error · {@see EXIT_OUTPUT} output error.
 *
 * Trust model (F13 / R.13): the CLI autoloads operator-supplied class names, so it
 * must only be run on code you trust.
 */
final class DeriveCommand implements CommandInterface
{
    /**
     * Permission bits for created output directories (no world-write, unlike the
     * previous magic `0o777`).
     */
    private const OUTPUT_DIR_MODE = 0o755;

    /**
     * Output key under which the combined family-adapter source is reported (the
     * mode emits one grouped source string, not a per-member map). Named in place
     * of the previous bare `'Family'` literal (F7 / R3-8).
     */
    private const FAMILY_ADAPTER_SOURCE_KEY = 'Family';

    /**
     * Process exit codes (F-1 / RP-1): named in place of bare integer literals.
     */
    private const EXIT_SUCCESS = 0;
    private const EXIT_USAGE = 1;
    private const EXIT_DERIVATION = 2;
    private const EXIT_OUTPUT = 3;

    /**
     * @param \Closure(string):void $output where derived source is written
     * @param \Closure(string):void $error  where diagnostics is written
     */
    public function __construct(
        private readonly \Closure $output,
        private readonly \Closure $error,
    ) {
    }

    private function write(string $data): void
    {
        ($this->output)($data);
    }

    private function writeErr(string $data): void
    {
        ($this->error)($data);
    }

    /**
     * @param list<string> $args argv without the script name
     */
    public function execute(array $args): int
    {
        if ($args === [] || in_array($args[0], ['-h', '--help', 'help'], true)) {
            $this->writeHelp();

            return $args === [] ? self::EXIT_USAGE : self::EXIT_SUCCESS;
        }

        try {
            $input = $this->parse($args);
        } catch (\InvalidArgumentException $e) {
            $this->writeErr('derive: ' . $e->getMessage() . "\n");
            $this->writeUsage();

            return self::EXIT_USAGE;
        }

        try {
            $classes = $this->resolveTargets($input->targets);
            $root = $input->root !== null ? $this->resolveRoot($input->root) : null;
        } catch (\Throwable $e) {
            $this->writeErr('derive: ' . $e->getMessage() . "\n");

            return self::EXIT_DERIVATION;
        }

        try {
            $sources = $this->runDerive(
                $input->mode,
                $classes,
                $input->config,
                $root,
                $input->namespacePrefixMap,
                $input->wrapperMap,
            );
        } catch (\Throwable $e) {
            $this->writeErr('derive: ' . $e->getMessage() . "\n");

            return self::EXIT_DERIVATION;
        }

        try {
            return $this->emit($sources, $input->out, $input->baseDir);
        } catch (\Throwable $e) {
            $this->writeErr('derive: ' . $e->getMessage() . "\n");

            return self::EXIT_OUTPUT;
        }
    }

    /**
     * @param list<string> $args
     */
    private function parse(array $args): DeriveCliInput
    {
        return $this->validate(new ArgParser()->parse($args));
    }

    private function validate(DeriveCliInput $input): DeriveCliInput
    {
        if ($input->root !== null) {
            // `--root` forces family mode (rejecting family-adapter here, where
            // the conflict is detectable); the single-target validation below
            // therefore never sees a root — no per-mode root check is needed.
            if ($input->mode === DeriveMode::FAMILY_ADAPTER) {
                throw new \InvalidArgumentException('--root is not supported with --mode=family-adapter (pass the member classes explicitly).');
            }
            $input = $input->withMode(DeriveMode::FAMILY);
        }

        if ($input->mode === DeriveMode::FLAT
            || $input->mode === DeriveMode::PER_TRAIT
            || $input->mode === DeriveMode::ADAPTER) {
            if (count($input->targets) !== 1) {
                throw new \InvalidArgumentException(sprintf('--mode=%s expects exactly one target (got %d).', $input->mode->value, count($input->targets)));
            }

            return $input;
        }

        if ($input->mode === DeriveMode::FAMILY) {
            if ($input->root === null && $input->targets === []) {
                throw new \InvalidArgumentException('--mode=family needs --root <class> or one or more <class> targets.');
            }

            return $input;
        }

        if (count($input->targets) < 1) {
            throw new \InvalidArgumentException('--mode=family-adapter needs one or more <class> targets.');
        }

        return $input;
    }

    /**
     * @param list<string> $targets
     * @return list<string> resolved FQCNs
     */
    private function resolveTargets(array $targets): array
    {
        $classes = [];
        foreach ($targets as $target) {
            $classes[] = $this->resolveType($target);
        }

        return $classes;
    }

    private function resolveRoot(string $root): string
    {
        return $this->resolveType($root);
    }

    private function resolveType(string $target): string
    {
        $fqcn = $target;
        if (is_file($target) && (str_ends_with($target, '.php') || str_contains($target, '/'))) {
            $fqcn = $this->classFromFile($target);
        }

        if (!class_exists($fqcn) && !interface_exists($fqcn) && !trait_exists($fqcn) && !enum_exists($fqcn)) {
            throw new \RuntimeException(sprintf('cannot resolve "%s" to a loadable class/interface/trait/enum (tried "%s").', $target, $fqcn));
        }

        return $fqcn;
    }

    /**
     * Extract the first declared type FQCN from a PHP source file without executing
     * it (tokenizer scan), so the CLI can point DeriveIt at a file directly.
     */
    private function classFromFile(string $file): string
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

    /**
     * @param list<string> $classes
     * @param array<string, string> $namespacePrefixMap
     * @param array<string, string> $wrapperMap
     * @return array<string, string> keyed by FQCN (or "Family") => rendered source
     */
    private function runDerive(
        DeriveMode $mode,
        array $classes,
        DeriveConfig $config,
        ?string $root,
        array $namespacePrefixMap,
        array $wrapperMap,
    ): array {
        return match ($mode) {
            DeriveMode::FLAT => [$classes[0] => DeriveIt::deriveWithResult($classes[0], $config)->source ?? ''],
            DeriveMode::PER_TRAIT => [$classes[0] => DeriveIt::derivePerTrait($classes[0], $config)],
            DeriveMode::ADAPTER => [$classes[0] => DeriveIt::deriveAdapter($classes[0], $wrapperMap, $config)],
            DeriveMode::FAMILY => $root !== null
                ? DeriveIt::deriveFamilyFromRoot($root, $namespacePrefixMap, $config)
                : DeriveIt::deriveFamily($classes, $config),
            DeriveMode::FAMILY_ADAPTER => [self::FAMILY_ADAPTER_SOURCE_KEY => DeriveIt::deriveFamilyAdapter($classes, $wrapperMap, $config)],
        };
    }

    /**
     * @param array<string, string> $sources
     */
    private function emit(array $sources, ?string $out, ?string $baseDir = null): int
    {
        if ($out === null) {
            $first = true;
            foreach ($sources as $key => $source) {
                if (!$first && count($sources) > 1) {
                    $this->write("\n// --- " . $key . " ---\n");
                }
                $this->write($source);
                $first = false;
            }

            return self::EXIT_SUCCESS;
        }

        $this->assertSafeOutputPath($out, $baseDir);

        $isDir = is_dir($out) || str_ends_with($out, DIRECTORY_SEPARATOR);
        if ($isDir) {
            if (!is_dir($out) && !mkdir($out, self::OUTPUT_DIR_MODE, true) && !is_dir($out)) {
                throw new \RuntimeException(sprintf('cannot create output directory "%s".', $out));
            }
            foreach ($sources as $key => $source) {
                $this->writeFile(rtrim($out, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $this->fileNameFor($key), $source);
            }

            return self::EXIT_SUCCESS;
        }

        if (count($sources) > 1) {
            throw new \RuntimeException(sprintf('derived %d interfaces; use --out=<dir> to write each to its own file.', count($sources)));
        }

        $this->writeFile($out, (string) reset($sources));

        return self::EXIT_SUCCESS;
    }

    /**
     * Rejects an `--out` path that escapes the (optional) `--base-dir` sandbox
     * (F-9 / RP-9). When no base dir is supplied the CLI keeps its documented
     * trusted-operator behavior and writes anywhere the process may. The check is
     * purely lexical (`..` / symlink-free canonicalization) so it works before the
     * directories exist.
     */
    private function assertSafeOutputPath(string $out, ?string $baseDir): void
    {
        if ($baseDir === null) {
            return;
        }

        $base = realpath($baseDir);
        if ($base === false) {
            throw new \RuntimeException(sprintf('cannot resolve --base-dir "%s".', $baseDir));
        }

        $canonical = $this->canonicalizePath($this->isAbsolutePath($out) ? $out : $base . DIRECTORY_SEPARATOR . $out);
        $baseLen = strlen($base);
        if (strncmp($canonical, $base, $baseLen) !== 0
            || (strlen($canonical) > $baseLen && $canonical[$baseLen] !== DIRECTORY_SEPARATOR)) {
            throw new \RuntimeException(sprintf('output path "%s" escapes --base-dir "%s".', $out, $baseDir));
        }
    }

    private function isAbsolutePath(string $path): bool
    {
        return $path !== '' && $path[0] === DIRECTORY_SEPARATOR;
    }

    /**
     * Collapses `.` and `..` segments without touching the filesystem, so the
     * result can be compared against the (realpath'd) base dir.
     */
    private function canonicalizePath(string $path): string
    {
        $segments = explode(DIRECTORY_SEPARATOR, $path);
        $out = [];
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($out);

                continue;
            }
            $out[] = $segment;
        }

        return ($path[0] === DIRECTORY_SEPARATOR ? DIRECTORY_SEPARATOR : '') . implode(DIRECTORY_SEPARATOR, $out);
    }

    private function fileNameFor(string $key): string
    {
        $short = Naming::shortName($key);
        $safe = preg_replace('/[^A-Za-z0-9_]/', '_', $short) ?? $short;

        return $safe . '.interface.php';
    }

    private function writeFile(string $path, string $source): void
    {
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, self::OUTPUT_DIR_MODE, true) && !is_dir($dir)) {
            throw new \RuntimeException(sprintf('cannot create directory "%s".', $dir));
        }
        if (file_put_contents($path, $source) === false) {
            throw new \RuntimeException(sprintf('cannot write file "%s".', $path));
        }
    }

    private function writeHelp(): void
    {
        $presetValues = implode('|', Preset::values());
        $this->write(<<<HELP
            derive — derive self-contained interfaces from existing PHP types

            Usage:
              derive [options] [--] <class-or-file> [<class> ...]
              derive --root=<class> [options]          (auto-discover a family)

            Modes (--mode):
              flat           single flat interface (default)
              per-trait      one interface per used trait, composed by the main interface
              adapter        flat interface + delegating adapter in one source
              family         derive a set of classes / a family reachable from --root
              family-adapter family of interfaces + one delegating adapter per member

            Output:
              (none)         print to stdout
              --out=FILE     write a single derived source to FILE
              --out=DIR/     write each member to <ShortName>.interface.php under DIR

            Options:
              --include-static        keep static methods on the derived surface
              --include-magic         keep __* magic methods
              --include-constants     lift constants into the interface
              --protected             also derive protected methods
              --preserve-docblocks    copy @param/@return/@throws phpdoc (FQCNs simplified)
              --audit-leakage         enable the deep leakage auditor (strict)
              --fail-on-empty          fail if the derived surface is empty
              --attribute-pass-through copy attributes (all, or owned via --owned-attr)
              --throws-capture         capture @throws from the adaptee phpdoc
              --preset={$presetValues}   apply a DeriveConfig preset
              --skip=M1,M2             drop these method names
              --only=M1,M2             keep only these method names
              --subst=Orig=Port,..     type-substitution map (FQCN => derived interface)
              --rename=old=new,..      rename methods uniformly
              --owned-attr=A,B         attribute FQCNs allowed when --attribute-pass-through
              --re-expose=I1,I2        owned interfaces to re-expose via `extends`
              --base-dir=DIR           sandbox --out to DIR (rejects path escapes)
              --ns-prefix=App\=App\Port\   namespace rewrite for --mode=family (root)
              --wrapper=Derived=Adapter     return-type wrapper map for adapter modes
              -h, --help               show this help

            Security:
              The CLI autoloads the class names you pass, so run it only on code you
              trust (F13 / R.13).

            Examples:
              derive 'App\Payments\Gateway'
              derive --mode=per-trait 'App\Payments\Gateway' --out=ports/
              derive --root='App\Payments\Gateway' --mode=family --out=ports/
              derive --mode=adapter 'App\Mail\Sender' --out=SenderAdapter.php

            HELP);
    }

    private function writeUsage(): void
    {
        $this->writeErr("Usage: derive [options] [--] <class-or-file> [<class> ...]  (see --help)\n");
    }
}
