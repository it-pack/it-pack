<?php

declare(strict_types=1);

namespace ItPack\DeriveIt\Generator;

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

        $signature->setAbstract(true);
        $signature->setFinal(false);
        $signature->setVisibility(null);

        if ($substitutor !== null) {
            $this->substituteTypes($signature, $method, $substitutor);
            $this->rewriteDefaultValues($signature, $method, $substitutor);
        }

        if ($this->config !== null) {
            if (!$this->config->attributePassThrough) {
                $this->stripAttributes($signature);
            }

            if ($this->config->throwsCapture) {
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

    private function stripAttributes(Method $signature): void
    {
        $signature->setAttributes([]);

        foreach ($signature->getParameters() as $parameter) {
            $parameter->setAttributes([]);
        }
    }

    private function applyThrows(Method $signature, ReflectionMethod $method): void
    {
        $doc = $method->getDocComment();
        if ($doc === false) {
            return;
        }

        $throws = [];
        if (preg_match_all('/@throws\s+([^\s*]+)(?:\s+(.*?))?(?=\n|\*\/|$)/', $doc, $matches, PREG_SET_ORDER) === 0) {
            return;
        }

        foreach ($matches as $match) {
            $throws[] = trim(sprintf('@throws %s %s', $match[1], $match[2] ?? ''));
        }

        if ($throws === []) {
            return;
        }

        $existing = $signature->getComment();
        $signature->setComment(($existing !== null ? $existing . "\n" : '') . implode("\n", $throws));
    }
}
