<?php

declare(strict_types=1);

namespace ItPack\DeriveIt\Generator;

use Nette\PhpGenerator\Attribute;
use Nette\PhpGenerator\Factory;
use Nette\PhpGenerator\Literal;
use Nette\PhpGenerator\Method;
use Nette\PhpGenerator\Parameter;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionType;

/**
 * Maps a reflected public method onto a Nette Method, stripping the body and
 * forcing the abstract-public interface shape.
 *
 * Parameter and return typing is delegated entirely to
 * Nette\PhpGenerator\Factory::fromMethodReflection(), the single source of truth
 * for union / intersection / nullable / variadic / by-ref / readonly-aware types.
 * When a TypeSubstitutor is supplied, referenced class types and constant default
 * expressions are rewritten to their derived interface FQCNs (Phase 4 / 9.1 / 9.3).
 *
 * Attribute pass-through and `@throws` capture are opt-in via DeriveConfig (B2);
 * by default attributes are stripped so a derived port never leaks a third-party
 * attribute class.
 */
final class MethodSignatureFactory
{
    private readonly Factory $factory;

    public function __construct(
        ?Factory $factory = null,
        private readonly ?DeriveConfig $config = null,
    ) {
        $this->factory = $factory ?? new Factory();
    }

    public function fromMethod(ReflectionMethod $method, ?TypeSubstitutor $substitutor = null): Method
    {
        $signature = $this->factory->fromMethodReflection($method);

        // `fromMethodReflection()` copies the source docblock; derived ports are
        // terse by default (G8). `preserveDocblocks` re-emits it (FQCN-simplified)
        // and `throwsCapture` re-attaches `@throws` — both opt-in.
        $signature->setComment(null);

        $signature->setAbstract(true);
        $signature->setFinal(false);
        $signature->setVisibility(null);

        if ($substitutor !== null) {
            $this->substituteTypes($signature, $method, $substitutor);
            $this->rewriteDefaultValues($signature, $method, $substitutor);
        }

        if ($this->config !== null) {
            $signature->setAttributes($this->filterAttributes($signature->getAttributes()));
            foreach ($signature->getParameters() as $parameter) {
                $parameter->setAttributes($this->filterAttributes($parameter->getAttributes()));
            }

            // When the full phpdoc is preserved it already carries `@throws`
            // (and is FQCN-simplified), so the dedicated capture must not
            // duplicate it (G8 / EC9).
            if ($this->config->preserveDocblocks) {
                $this->applyDocblock($signature, $method, $substitutor);
            } elseif ($this->config->throwsCapture) {
                $this->applyThrows($signature, $method);
            }
        }

        return $signature;
    }

    /**
     * @return array<string, ReflectionParameter>
     */
    private function parametersByName(ReflectionMethod $method): array
    {
        $map = [];
        foreach ($method->getParameters() as $parameter) {
            $map[$parameter->getName()] = $parameter;
        }

        return $map;
    }

    private function substituteTypes(Method $signature, ReflectionMethod $method, TypeSubstitutor $substitutor): void
    {
        $parametersByName = $this->parametersByName($method);

        $returnType = $method->getReturnType();
        if ($returnType !== null) {
            $signature->setReturnType($substitutor->substitute($returnType));
        }

        foreach ($signature->getParameters() as $parameter) {
            $this->substituteParameterType($parameter, $parametersByName, $substitutor);
        }
    }

    /**
     * @param array<string, ReflectionParameter> $parametersByName
     */
    private function substituteParameterType(Parameter $parameter, array $parametersByName, TypeSubstitutor $substitutor): void
    {
        $reflection = $parametersByName[$parameter->getName()] ?? null;
        $type = $reflection?->getType();

        if ($type instanceof ReflectionType) {
            $parameter->setType($substitutor->substitute($type));
        }
    }

    /**
     * Rewrites constant default-value expressions (e.g. `Enum::DEFAULT`) when the
     * constant's class is present in the substitution map (A2).
     */
    private function rewriteDefaultValues(Method $signature, ReflectionMethod $method, TypeSubstitutor $substitutor): void
    {
        $parametersByName = $this->parametersByName($method);

        foreach ($signature->getParameters() as $parameter) {
            $reflection = $parametersByName[$parameter->getName()] ?? null;
            if ($reflection === null || !$reflection->isDefaultValueAvailable() || !$reflection->isDefaultValueConstant()) {
                continue;
            }

            $constantName = $reflection->getDefaultValueConstantName();
            if ($constantName === null) {
                continue;
            }

            $rewritten = $substitutor->rewriteConstantExpression($constantName);
            if ($rewritten !== $constantName) {
                $parameter->setDefaultValue(new Literal($rewritten));
            }
        }
    }

    /**
     * Returns the attributes to keep on the derived method/parameter.
     *
     * When `attributePassThrough` is off, nothing is kept. When on, attributes are
     * kept unless an `ownedAttributes` allow-list is configured (then only owned
     * attributes survive). `#[Override]` (and any global `Override`) is always
     * dropped — it is meaningless/invalid on an interface method (G7 / EC8).
     *
     * @param list<Attribute> $attributes
     * @return list<Attribute>
     */
    private function filterAttributes(array $attributes): array
    {
        if ($this->config === null || !$this->config->attributePassThrough) {
            return [];
        }

        $allowed = $this->config->ownedAttributes;
        $kept = [];

        foreach ($attributes as $attribute) {
            $name = ltrim($attribute->getName(), '\\');
            if ($name === 'Override') {
                continue;
            }
            if ($allowed === [] || in_array($name, $allowed, true) || in_array('\\' . $name, $allowed, true)) {
                $kept[] = $attribute;
            }
        }

        return $kept;
    }

    /**
     * Re-emits the source method's phpdoc on the derived method (G8 / EC9 / EC10),
     * stripping the `/** … *​/` delimiters so Nette re-wraps it cleanly. When a
     * substitutor is supplied, class FQCNs inside the comment are simplified to
     * their derived interface (e.g. `@param Foo $x` → `@param FooInterface $x`).
     */
    private function applyDocblock(Method $signature, ReflectionMethod $method, ?TypeSubstitutor $substitutor): void
    {
        $doc = $method->getDocComment();
        if ($doc === false) {
            return;
        }

        $comment = $this->normalizeDocComment($doc);
        if ($substitutor !== null) {
            $comment = $substitutor->simplifyDocblock($comment);
        }

        $signature->setComment($comment);
    }

    /**
     * Strips the leading `/**`, trailing `*\/` and per-line `* ` prefixes from a
     * reflection doc-comment, returning the bare inner text Nette expects.
     */
    private function normalizeDocComment(string $doc): string
    {
        $lines = preg_split('/\n/', $doc);
        if ($lines === false) {
            $lines = [];
        }
        $normalized = [];
        foreach ($lines as $line) {
            $normalized[] = (string) preg_replace('/^\s*\/?\*+\/?\s?/', '', $line);
        }

        return trim(implode("\n", $normalized), "\n");
    }

    private function applyThrows(Method $signature, ReflectionMethod $method): void
    {
        $doc = $method->getDocComment();
        if ($doc === false) {
            return;
        }

        $throws = [];
        if (preg_match_all('/@throws\s+([^\s*]+(?:<[^>]*>)?)(?:\s+(.*?))?(?=\n|\*\/|$)/', $doc, $matches, PREG_SET_ORDER) === 0) {
            return;
        }

        foreach ($matches as $match) {
            $throws[] = trim(sprintf('@throws %s %s', $match[1], $match[2] ?? ''));
        }

        if ($throws === []) {
            return;
        }

        // `fromMethodReflection()` already copied the method's docblock (including
        // any `@throws` it contained) into the comment, so strip those before
        // re-appending the captured set to avoid duplication.
        $existing = $signature->getComment() ?? '';
        $existingWithoutThrows = preg_replace('/^[ \t]*\*?[ \t]*@throws\b.*$/m', '', $existing);
        $existingWithoutThrows = trim((string) $existingWithoutThrows);

        $signature->setComment(
            ($existingWithoutThrows !== '' ? $existingWithoutThrows . "\n" : '') . implode("\n", $throws),
        );
    }
}
