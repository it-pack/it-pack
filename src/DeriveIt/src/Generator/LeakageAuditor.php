<?php

declare(strict_types=1);

namespace ItPack\DeriveIt\Generator;

use ItPack\DeriveIt\Exception\LeakageDetectedException;
use Nette\PhpGenerator\InterfaceType;

/**
 * Deep leakage auditor (G1 / EC1, EC11, EC17).
 *
 * The shallow guard `InterfaceDeriver::assertSelfContained()` only forbids an
 * `extends` clause. This auditor goes further: after type substitution it scans
 * every method return and parameter type and flags any FQCN that is neither a
 * builtin nor resolvable inside the target namespace / an explicitly owned
 * namespace (the substitution map values and owned-namespace prefix rewrites).
 * Such a type is a concrete collaborator the port was supposed to shed.
 *
 * Off by default (see `DeriveConfig::$auditLeakage`) so existing golden files
 * that intentionally keep an unmapped foreign type are not broken; opt in once
 * the derive set / substitution map is complete.
 */
final class LeakageAuditor
{
    /**
     * @var list<string> PHP builtin type names that are never leakage.
     */
    private const BUILTINS = [
        'int', 'float', 'string', 'bool', 'array', 'callable', 'iterable', 'object',
        'mixed', 'void', 'never', 'null', 'true', 'false', 'self', 'static',
        'parent', 'resource',
    ];

    /**
     * @param list<string> $allowedNamespaces Namespace prefixes considered owned
     *                                        (target namespace + substitution-map
     *                                        value namespaces + owned-prefix rewrites).
     */
    public function audit(InterfaceType $interface, string $namespace, array $allowedNamespaces, bool $enabled): void
    {
        if (!$enabled) {
            return;
        }

        foreach ($interface->getMethods() as $method) {
            $returnType = $method->getReturnType();
            if (is_string($returnType) && $returnType !== '') {
                $this->auditType(
                    $returnType,
                    $namespace,
                    $allowedNamespaces,
                    $interface->getName(),
                    $method->getName(),
                    'return',
                );
            }

            foreach ($method->getParameters() as $parameter) {
                $paramType = $parameter->getType();
                if (is_string($paramType) && $paramType !== '') {
                    $this->auditType(
                        $paramType,
                        $namespace,
                        $allowedNamespaces,
                        $interface->getName(),
                        $method->getName(),
                        'param $' . $parameter->getName(),
                    );
                }
            }
        }
    }

    /**
     * Computes the owned-namespace allow-list for a derivation.
     *
     * @param array<string, string> $map Class FQCN => derived interface FQCN.
     * @param array<string, string> $namespacePrefixMap Owned-namespace rewrite.
     * @return list<string>
     */
    public static function allowedNamespaces(string $target, array $map, array $namespacePrefixMap): array
    {
        $allowed = [$target];

        foreach ($map as $fqcn) {
            $allowed[] = ($pos = strrpos($fqcn, '\\')) !== false ? substr($fqcn, 0, $pos) : '';
        }

        foreach ($namespacePrefixMap as $to) {
            $allowed[] = rtrim($to, '\\');
        }

        return array_values(array_unique($allowed));
    }

    private function auditType(
        string $type,
        string $namespace,
        array $allowedNamespaces,
        string $interfaceName,
        string $methodName,
        string $kind,
    ): void {
        $segments = explode('|', str_replace('&', '|', $type));

        foreach ($segments as $segment) {
            $segment = trim($segment, ' ?');
            if ($segment === '' || in_array(strtolower($segment), self::BUILTINS, true)) {
                continue;
            }

            if ($this->isAllowed($segment, $namespace, $allowedNamespaces)) {
                continue;
            }

            throw new LeakageDetectedException(sprintf(
                'Derived interface "%s" method %s (%s) references non-port type "%s" (leakage invariant).',
                $interfaceName,
                $kind,
                $methodName,
                $segment,
            ));
        }
    }

    private function isAllowed(string $segment, string $namespace, array $allowedNamespaces): bool
    {
        // Global (non-namespaced) class reference — e.g. DateTime — is a concrete leak.
        if (!str_contains($segment, '\\')) {
            return false;
        }

        if ($namespace !== '' && str_starts_with($segment, $namespace . '\\')) {
            return true;
        }

        foreach ($allowedNamespaces as $allowed) {
            if ($allowed !== '' && str_starts_with($segment, $allowed . '\\')) {
                return true;
            }
        }

        return false;
    }
}
