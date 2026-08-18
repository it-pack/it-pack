<?php

declare(strict_types=1);

namespace ItPack\DeriveIt\Generator;

use Nette\PhpGenerator\Factory;
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
 * When a TypeSubstitutor is supplied, referenced class types are rewritten to
 * their derived interface FQCNs (Phase 4).
 */
final class MethodSignatureFactory
{
    private readonly Factory $factory;

    public function __construct(?Factory $factory = null)
    {
        $this->factory = $factory ?? new Factory();
    }

    public function fromMethod(ReflectionMethod $method, ?TypeSubstitutor $substitutor = null): Method
    {
        $signature = $this->factory->fromMethodReflection($method);

        $signature->setAbstract(true);
        $signature->setFinal(false);
        $signature->setVisibility(null);

        if ($substitutor === null) {
            return $signature;
        }

        $parametersByName = $this->parametersByName($method);

        $returnType = $method->getReturnType();
        if ($returnType !== null) {
            $signature->setReturnType($substitutor->substitute($returnType));
        }

        foreach ($signature->getParameters() as $parameter) {
            $this->substituteParameterType($parameter, $parametersByName, $substitutor);
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
}
