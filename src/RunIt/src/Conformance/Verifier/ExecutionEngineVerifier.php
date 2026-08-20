<?php

declare(strict_types=1);

namespace ItPack\RunIt\Conformance\Verifier;

use ItPack\RunIt\Conformance\Result\ConformanceResult;
use ItPack\RunIt\Conformance\Result\ConformanceViolation;
use ItPack\RunIt\Contract\Application\ExecutionEngineInterface;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use Throwable;

/**
 * Conformance checks for {@see ExecutionEngineInterface}.
 *
 * The contract requires that {@see ExecutionEngineInterface::run()} executes the
 * supplied operation and returns its value (whatever it is, including null), never
 * throws for ordinary operations, and that {@see ExecutionEngineInterface::capability()}
 * returns an {@see EngineCapabilityInterface} description. A scope is supplied by the
 * caller because the engine only needs it to drive execution; its contents are not
 * part of the engine contract.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class ExecutionEngineVerifier
{
    public function port(): string
    {
        return ExecutionEngineInterface::class;
    }

    public function verify(ExecutionEngineInterface $engine, ExecutionScopeInterface $scope): ConformanceResult
    {
        $result = new ConformanceResult($this->port());

        try {
            $value = $engine->run($scope, static fn (): int => 42);
            $again = $engine->run($scope, static fn (): int => 42);
        } catch (Throwable $e) {
            return $result->add(new ConformanceViolation('run.throws', sprintf('run() threw for a plain operation: %s', $e->getMessage())));
        }

        if ($value !== 42) {
            $result = $result->add(new ConformanceViolation('run.wrong-value', 'run() did not return the operation result'));
        }
        if ($value !== $again) {
            $result = $result->add(new ConformanceViolation('run.not-deterministic', 'run() returned different results for identical operations'));
        }

        try {
            $null = $engine->run($scope, static fn (): ?int => null);
        } catch (Throwable $e) {
            return $result->add(new ConformanceViolation('run.null.throws', sprintf('run() threw for a null-returning operation: %s', $e->getMessage())));
        }
        if ($null !== null) {
            $result = $result->add(new ConformanceViolation('run.null.wrong-value', 'run() did not return null for a null-returning operation'));
        }

        try {
            $engine->capability();
        } catch (Throwable $e) {
            return $result->add(new ConformanceViolation('capability.throws', sprintf('capability() threw: %s', $e->getMessage())));
        }

        return $result;
    }
}
