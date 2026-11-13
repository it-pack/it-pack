<?php

declare(strict_types=1);

namespace Tools\ApiCompat;

use function array_diff;
use function array_keys;
use function array_map;
use function class_exists;
use function count;
use function dirname;
use function explode;

use const false;

use function file_get_contents;

use FilesystemIterator;

use function implode;
use function in_array;
use function interface_exists;
use function is_a;
use function is_subclass_of;
use function json_encode;

use JsonException;

use function ksort;

use const PHP_EOL;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionEnum;
use ReflectionException;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;
use RuntimeException;

use function sort;
use function sprintf;
use function str_starts_with;

use const T_CLASS;
use const T_ENUM;
use const T_INTERFACE;
use const T_NAME_FULLY_QUALIFIED;
use const T_NAME_QUALIFIED;
use const T_NAMESPACE;
use const T_STRING;

use function token_get_all;

use const true;

/**
 * Self-contained public-API backward-compatibility checker.
 *
 * Scans the configured public source directories with PHP reflection, compares
 * the extracted API surface against a committed baseline, and reports
 * compatibility breaks. It also diffs the documented normalization schema
 * separately. No third-party dependency is required (reflection only), so it
 * runs on every supported PHP version in the matrix.
 *
 * Usage:
 *   php tools/api-compat/api-compat.php baseline   # write current API + schema to baselines
 *   php tools/api-compat/api-compat.php check      # diff against baselines (exit 1 on breaks)
 */

final class ApiCompat
{
    private string $root;

    /** @var array{publicDirs: list<string>, internalNamespaces: list<string>, baseline: string, schema: array{path: string, baseline: string}} */
    private array $config;

    /**
     * @param list<string> $argv
     * @param array{publicDirs: list<string>, internalNamespaces: list<string>, baseline: string, schema: array{path: string, baseline: string}}|null $config
     * @throws JsonException
     */
    public function __construct(private array $argv, ?array $config = null)
    {
        $this->root = dirname(__DIR__, 2);
        $this->config = $config ?? $this->loadConfig();
    }

    /**
     * @throws ReflectionException
     * @throws JsonException
     */
    public function run(): int
    {
        $command = $this->argv[1] ?? 'check';

        if ($command === 'baseline') {
            return $this->writeBaseline();
        }

        if ($command === 'check') {
            return $this->check();
        }

        $this->stderr("Unknown command: {$command}\n");
        $this->stderr("Usage: api-compat.php [baseline|check]\n");

        return 2;
    }

    /**
     * @throws ReflectionException
     * @throws JsonException
     */
    private function check(): int
    {
        $current = $this->buildApiMap();
        $baselineFile = $this->root . '/' . $this->config['baseline'];

        if (!is_file($baselineFile)) {
            $this->stdout("No API baseline found at {$this->config['baseline']}.\n");
            $this->stdout("Run `composer api:baseline` to establish one (do this at the first release candidate).\n");
            $this->stdout("api:check exits 0 until a baseline exists.\n");

            return 0;
        }

        $baseline = $this->loadJson($baselineFile);
        $issues = $this->compareApi($current, $baseline);

        $schemaIssues = $this->compareSchema();
        $issues = [...$issues, ...$schemaIssues];

        $this->printReport($issues);

        return count($issues) > 0 ? 1 : 0;
    }

    /**
     * @return list<array{message: string, symbol: string, severity: string}>
     */
    private function compareApi(array $current, array $baseline): array
    {
        $issues = [];
        $internalNs = $this->config['internalNamespaces'];

        foreach ($baseline as $fqn => $old) {
            if (!isset($current[$fqn])) {
                $issues[] = $this->break("Removed public symbol: {$fqn}", $fqn);

                continue;
            }

            $new = $current[$fqn];

            foreach ($old['constants'] as $const => $vis) {
                if (!isset($new['constants'][$const])) {
                    $issues[] = $this->break("Removed constant {$fqn}::{$const}", $fqn);
                }
            }

            if ($old['kind'] !== $new['kind']) {
                $issues[] = $this->break("Symbol kind changed for {$fqn}: {$old['kind']} -> {$new['kind']}", $fqn);
            }

            foreach (array_diff($old['implements'], $new['implements']) as $interface) {
                $issues[] = $this->break("Removed implemented or extended interface {$interface} from {$fqn}", $fqn);
            }

            foreach ($old['methods'] as $name => $oldMethod) {
                if (!isset($new['methods'][$name])) {
                    $issues[] = $this->break("Removed method {$fqn}::{$name}()", $fqn);

                    continue;
                }

                $newMethod = $new['methods'][$name];
                $this->compareMethod($fqn, $name, $oldMethod, $newMethod, $old['kind'], $issues);
            }

            foreach ($new['methods'] as $name => $newMethod) {
                if (isset($old['methods'][$name])) {
                    continue;
                }

                // Older baselines omit PHP-internal methods, but already record
                // the interfaces that imposed those obligations.
                if (isset($newMethod['internalInterface'])
                    && in_array($newMethod['internalInterface'], $old['implements'], true)) {
                    continue;
                }

                if ($old['kind'] === 'interface') {
                    $issues[] = $this->break(
                        "Added method to interface {$fqn}::{$name}() (breaks third-party implementers)",
                        $fqn,
                    );

                    continue;
                }

                $issues[] = $this->add("Added method {$fqn}::{$name}()", $fqn);
            }

            if ($old['kind'] === 'enum') {
                foreach ($old['cases'] as $case) {
                    if (!in_array($case, $new['cases'], true)) {
                        $issues[] = $this->break("Removed enum case {$fqn}::{$case}", $fqn);
                    }
                }
            }
        }

        foreach ($current as $fqn => $new) {
            if (!isset($baseline[$fqn])) {
                $issues[] = $this->add("Added public symbol: {$fqn}", $fqn);

                continue;
            }

            if ($new['kind'] === 'enum') {
                foreach ($new['cases'] as $case) {
                    if (!in_array($case, $baseline[$fqn]['cases'], true)) {
                        $issues[] = $this->add("Added enum case {$fqn}::{$case}", $fqn);
                    }
                }
            }

            $this->checkInternalLeak($fqn, $new, $internalNs, $issues, $baseline[$fqn] ?? null);
        }

        return $issues;
    }

    /**
     * @param list<array{message: string, symbol: string, severity: string}> $issues
     */
    private function compareMethod(
        string $fqn,
        string $name,
        array $oldMethod,
        array $newMethod,
        string $kind,
        array &$issues,
    ): void {
        $methodId = "{$fqn}::{$name}()";

        $oldReturn = $oldMethod['return'];
        $newReturn = $newMethod['return'];

        if ($oldReturn !== $newReturn && $newReturn !== null) {
            if ($oldReturn === null || !$this->isSubtype($newReturn, $oldReturn)) {
                $issues[] = $this->break(
                    "Return type narrowed on {$methodId}: {$oldReturn} -> {$newReturn}",
                    $fqn,
                );
            }
        }

        $oldParams = $oldMethod['params'];
        $newParams = $newMethod['params'];

        foreach ($oldParams as $op) {
            $match = null;

            foreach ($newParams as $np) {
                if ($np['name'] === $op['name']) {
                    $match = $np;

                    break;
                }
            }

            if ($match === null) {
                $issues[] = $this->break("Removed parameter \${$op['name']} from {$methodId}", $fqn);

                continue;
            }

            if ($op['type'] !== $match['type'] && $match['type'] !== null) {
                if ($op['type'] === null || !$this->isSubtype($op['type'], $match['type'])) {
                    $issues[] = $this->break(
                        "Parameter type narrowed on \${$op['name']} of {$methodId}: {$op['type']} -> {$match['type']}",
                        $fqn,
                    );
                }
            }

            if ($op['optional'] && !$match['optional']) {
                $issues[] = $this->break("Parameter \${$op['name']} of {$methodId} became required", $fqn);
            }
        }

        foreach ($newParams as $np) {
            $exists = false;

            foreach ($oldParams as $op) {
                if ($op['name'] === $np['name']) {
                    $exists = true;

                    break;
                }
            }

            if (!$exists && !$np['optional']) {
                $issues[] = $this->break("Added required parameter \${$np['name']} to {$methodId}", $fqn);
            }
        }
    }

    /**
     * @param list<array{message: string, symbol: string, severity: string}> $issues
     */
    private function checkInternalLeak(string $fqn, array $new, array $internalNs, array &$issues, ?array $old): void
    {
        $types = [];

        foreach (($new['methods'] ?? []) as $m) {
            if ($m['return'] !== null) {
                $types[] = $m['return'];
            }

            foreach ($m['params'] as $p) {
                if ($p['type'] !== null) {
                    $types[] = $p['type'];
                }
            }
        }

        if ($new['extends'] !== null) {
            $types[] = $new['extends'];
        }

        foreach ($new['implements'] as $i) {
            $types[] = $i;
        }

        foreach ($types as $type) {
            foreach (explode('|', $type) as $member) {
                foreach ($internalNs as $ns) {
                    if (str_starts_with($member, $ns)) {
                        $issues[] = $this->break("Public API leaks internal type {$member} into {$fqn}", $fqn);

                        return;
                    }
                }
            }
        }
    }

    /**
     * @return list<array{message: string, symbol: string, severity: string}>
     * @throws JsonException
     */
    private function compareSchema(): array
    {
        $schemaFile = $this->root . '/' . $this->config['schema']['path'];
        $schemaBaseline = $this->root . '/' . $this->config['schema']['baseline'];

        if (!is_file($schemaFile)) {
            return [];
        }

        if (!is_file($schemaBaseline)) {
            $this->stdout(
                "No normalization-schema baseline; skipping schema diff (establish at RC via composer api:baseline).\n",
            );

            return [];
        }

        $current = $this->loadJson($schemaFile);
        $baseline = $this->loadJson($schemaBaseline);
        $issues = [];

        foreach ($baseline['artifacts'] ?? [] as $artifact => $oldFields) {
            $newFields = $current['artifacts'][$artifact] ?? [];

            foreach (array_diff($oldFields, $newFields) as $removed) {
                $issues[] = $this->break(
                    "Normalization schema removed field '{$removed}' from {$artifact}",
                    "schema:{$artifact}",
                );
            }

            foreach (array_diff($newFields, $oldFields) as $added) {
                $issues[] = $this->change(
                    "Normalization schema added field '{$added}' to {$artifact}",
                    "schema:{$artifact}",
                );
            }
        }

        $currentArtifacts = array_keys($current['artifacts'] ?? []);
        $baselineArtifacts = array_keys($baseline['artifacts'] ?? []);

        foreach (array_diff($currentArtifacts, $baselineArtifacts) as $added) {
            $issues[] = $this->change(
                "Normalization schema added artifact '{$added}'",
                "schema:{$added}",
            );
        }

        return $issues;
    }

    /**
     * @throws ReflectionException
     * @throws JsonException
     */
    private function writeBaseline(): int
    {
        $api = $this->buildApiMap();
        $this->writeJson($this->root . '/' . $this->config['baseline'], $api);

        $schemaFile = $this->root . '/' . $this->config['schema']['path'];

        if (is_file($schemaFile)) {
            $this->writeJson($this->root . '/' . $this->config['schema']['baseline'], $this->loadJson($schemaFile));
        }

        $this->stdout("Wrote API baseline to {$this->config['baseline']}\n");

        if (is_file($schemaFile)) {
            $this->stdout("Wrote normalization-schema baseline to {$this->config['schema']['baseline']}\n");
        }

        return 0;
    }

    /**
     * @return array<string, array<string, mixed>>
     * @throws ReflectionException
     */
    private function buildApiMap(): array
    {
        $symbols = $this->discoverSymbols();
        $map = [];

        foreach ($symbols as $fqn) {
            try {
                $rc = new \ReflectionClass($fqn);
            } catch (\Throwable) {
                continue;
            }

            if ($rc->isInternal()) {
                continue;
            }

            $entry = [
                'kind' => match (true) {
                    $rc->isInterface() => 'interface',
                    $rc->isEnum() => 'enum',
                    default => 'class',
                },
                'final' => $rc->isFinal(),
                'extends' => $rc->getParentClass() !== false ? $rc->getParentClass()->getName() : null,
                'implements' => array_map(
                    static fn (\ReflectionClass $i): string => $i->getName(),
                    $rc->getInterfaces(),
                ),
                'constants' => [],
                'methods' => [],
                'cases' => [],
            ];

            foreach ($rc->getReflectionConstants() as $const) {
                if ($const->isPublic() || $const->isProtected()) {
                    $entry['constants'][$const->getName()] = $const->isPublic() ? 'public' : 'protected';
                }
            }

            foreach ($rc->getMethods(ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_PROTECTED) as $m) {
                if ($m->isPrivate()) {
                    continue;
                }

                // Built-in interface methods are implementation obligations, unlike
                // methods inherited from built-in classes such as Exception.
                if ($m->getDeclaringClass()->isInternal() && !$m->getDeclaringClass()->isInterface()) {
                    continue;
                }
                $params = [];

                foreach ($m->getParameters() as $p) {
                    $params[] = [
                        'name' => $p->getName(),
                        'type' => $p->hasType() ? $this->typeToString($p->getType()) : null,
                        'optional' => $p->isOptional(),
                    ];
                }

                $entry['methods'][$m->getName()] = [
                    'static' => $m->isStatic(),
                    'return' => $m->hasReturnType() ? $this->typeToString($m->getReturnType()) : null,
                    'params' => $params,
                ];

                if ($m->getDeclaringClass()->isInternal() && $m->getDeclaringClass()->isInterface()) {
                    $entry['methods'][$m->getName()]['internalInterface'] = $m->getDeclaringClass()->getName();
                }
            }

            if ($rc->isEnum()) {
                $enum = new ReflectionEnum($fqn);

                foreach ($enum->getCases() as $case) {
                    $entry['cases'][] = $case->getName();
                }
            }

            $map[$fqn] = $entry;
        }

        ksort($map);

        return $map;
    }

    /**
     * @return list<string>
     */
    private function discoverSymbols(): array
    {
        $symbols = [];

        foreach ($this->config['publicDirs'] as $dir) {
            $abs = $this->root . '/' . $dir;

            if (!is_dir($abs)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(
                    $abs,
                    FilesystemIterator::SKIP_DOTS
                )
            );

            foreach ($iterator as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $fqn = $this->symbolFromFile($file->getPathname());

                if ($fqn !== null) {
                    $symbols[] = $fqn;
                }
            }
        }

        return $symbols;
    }

    private function symbolFromFile(string $path): ?string
    {
        $code = file_get_contents($path);

        if ($code === false) {
            return null;
        }

        $tokens = token_get_all($code);
        $namespace = '';
        $pendingNamespace = false;
        $candidate = null;

        foreach ($tokens as $token) {
            if (!is_array($token)) {
                if ($pendingNamespace) {
                    $pendingNamespace = false;
                }

                continue;
            }

            $type = $token[0];

            if ($type === T_NAMESPACE) {
                $namespace = '';
                $pendingNamespace = true;

                continue;
            }

            if ($pendingNamespace && in_array($type, [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_STRING], true)) {
                $namespace .= $token[1];

                continue;
            }

            if ($pendingNamespace && $type === ';') {
                $pendingNamespace = false;

                continue;
            }

            if (in_array($type, [T_CLASS, T_INTERFACE, T_ENUM], true)) {
                $candidate = $type;

                continue;
            }

            if ($candidate !== null && $type === T_STRING) {
                $name = $token[1];

                return $namespace !== '' ? $namespace . '\\' . $name : $name;
            }
        }

        return null;
    }

    private function typeToString(?ReflectionType $type): ?string
    {
        if ($type === null) {
            return null;
        }

        if ($type instanceof ReflectionNamedType) {
            $name = $type->getName();

            if ($name !== 'null' && $name !== 'mixed' && $type->allowsNull()) {
                return 'null|' . $name;
            }

            return $name;
        }

        if ($type instanceof ReflectionUnionType) {
            $parts = [];

            foreach ($type->getTypes() as $t) {
                $parts[] = $this->typeToString($t);
            }

            sort($parts);

            return implode('|', $parts);
        }

        if ($type instanceof \ReflectionIntersectionType) {
            $parts = [];

            foreach ($type->getTypes() as $t) {
                $parts[] = $this->typeToString($t);
            }

            sort($parts);

            return implode('&', $parts);
        }

        return (string) $type;
    }

    private function isSubtype(string $a, string $b): bool
    {
        if ($a === $b) {
            return true;
        }

        if ($b === 'mixed') {
            return true;
        }

        if ($a === 'mixed') {
            return false;
        }

        $aMembers = explode('|', $a);
        $bMembers = explode('|', $b);

        foreach ($aMembers as $am) {
            $ok = false;

            foreach ($bMembers as $bm) {
                if ($this->isSubtypeSingle($am, $bm)) {
                    $ok = true;

                    break;
                }
            }

            if (!$ok) {
                return false;
            }
        }

        return true;
    }

    private function isSubtypeSingle(string $a, string $b): bool
    {
        if ($a === $b) {
            return true;
        }

        if ($b === 'mixed') {
            return true;
        }

        if ($a === 'mixed') {
            return false;
        }

        if ((class_exists($a) || interface_exists($a)) && (class_exists($b) || interface_exists($b))) {
            return is_a($a, $b, true) || is_subclass_of($a, $b, true);
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     * @throws JsonException
     */
    private function loadConfig(): array
    {
        $file = __DIR__ . '/config.json';
        $data = $this->loadJson($file);

        foreach (['publicDirs', 'internalNamespaces', 'baseline', 'schema'] as $key) {
            if (!isset($data[$key])) {
                throw new RuntimeException("Missing config key: {$key}");
            }
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     * @throws JsonException
     */
    private function loadJson(string $file): array
    {
        $contents = file_get_contents($file);

        if ($contents === false) {
            throw new RuntimeException("Cannot read {$file}");
        }

        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($decoded)) {
            throw new RuntimeException("Invalid JSON in {$file}");
        }

        return $decoded;
    }

    /**
     * @throws JsonException
     */
    private function writeJson(string $file, array $data): void
    {
        $result = file_put_contents(
            $file,
            json_encode(
                $data,
                JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            )
            . PHP_EOL
        );

        if ($result === false) {
            throw new RuntimeException("Cannot write {$file}");
        }
    }

    /**
     * @param list<array{message: string, symbol: string, severity: string}> $issues
     */
    private function printReport(array $issues): void
    {
        if (count($issues) === 0) {
            $this->stdout("No backward-compatibility breaks detected.\n");

            return;
        }

        $breaks = 0;

        foreach ($issues as $issue) {
            if ($issue['severity'] === 'break') {
                $breaks++;
                $this->stdout("BREAK  {$issue['message']}\n");

                continue;
            }

            if ($issue['severity'] === 'change') {
                $this->stdout("CHANGE {$issue['message']}\n");

                continue;
            }

            $this->stdout("ADD    {$issue['message']}\n");
        }

        $this->stdout(sprintf("\n%d backward-compatibility break(s) detected.\n", $breaks));
    }

    /**
     * @return array{message: string, symbol: string, severity: string}
     */
    private function break(string $message, string $symbol): array
    {
        return ['message' => $message, 'symbol' => $symbol, 'severity' => 'break'];
    }

    /**
     * @return array{message: string, symbol: string, severity: string}
     */
    private function change(string $message, string $symbol): array
    {
        return ['message' => $message, 'symbol' => $symbol, 'severity' => 'change'];
    }

    /**
     * @return array{message: string, symbol: string, severity: string}
     */
    private function add(string $message, string $symbol): array
    {
        return ['message' => $message, 'symbol' => $symbol, 'severity' => 'add'];
    }

    private function stdout(string $line): void
    {
        fwrite(STDOUT, $line);
    }

    private function stderr(string $line): void
    {
        fwrite(STDERR, $line);
    }
}
