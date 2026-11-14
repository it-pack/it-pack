<?php

declare(strict_types=1);

namespace ItPack\RunIt\Conformance\Verifier;

use ItPack\RunIt\Conformance\Contract\VerifierInterface;
use ItPack\RunIt\Conformance\Result\ConformanceResult;
use ItPack\RunIt\Conformance\Result\ConformanceViolation;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Port\ContextStorageInterface;
use ItPack\RunIt\Contract\Port\ContextTokenInterface;
use Throwable;

/**
 * Conformance checks for {@see ContextStorageInterface}.
 *
 * The contract models a last-in-first-out scope stack: {@see ContextStorageInterface::attach()}
 * returns an opaque token, {@see ContextStorageInterface::current()} reports the top scope, and
 * {@see ContextStorageInterface::detach()} accepts exactly the token produced by attach and pops
 * the matching scope. A scope instance is supplied by the caller (for example through the
 * RunIt test kit) because the storage only stores and returns it; its internal contents are
 * irrelevant to the contract.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class ContextStorageVerifier implements VerifierInterface
{
    public function port(): string
    {
        return ContextStorageInterface::class;
    }

    public function verify(ContextStorageInterface $storage, ExecutionScopeInterface $scope): ConformanceResult
    {
        $result = new ConformanceResult($this->port());

        [$token, $result] = $this->attachOrNull($storage, $scope, $result);
        if ($token === null) {
            return $result;
        }

        if ($storage->current() !== $scope) {
            $result = $result->add(new ConformanceViolation(
                'current.after-attach',
                'current() did not return the attached scope'
            ));
        }

        [$secondToken, $result] = $this->attachOrNull($storage, $scope, $result);

        if ($secondToken instanceof ContextTokenInterface) {
            if ($storage->current() !== $scope) {
                $result = $result->add(new ConformanceViolation(
                    'current.not-lifo',
                    'a second attach did not become the current scope'
                ));
            }

            $result = $this->detachOrNull($storage, $secondToken, $result);

            if ($storage->current() !== $scope) {
                $result = $result->add(new ConformanceViolation(
                    'detach.wrong-scope',
                    'detaching the inner scope did not restore the outer scope'
                ));
            }
        }

        $result = $this->detachOrNull($storage, $token, $result);

        if ($storage->current() instanceof ExecutionScopeInterface) {
            $result = $result->add(new ConformanceViolation(
                'detach.incomplete',
                'current() still returned a scope after detaching the last one'
            ));
        }

        return $result;
    }

    /**
     * @return array{0: ContextTokenInterface|null, 1: ConformanceResult}
     */
    private function attachOrNull(
        ContextStorageInterface $storage,
        ExecutionScopeInterface $scope,
        ConformanceResult $result,
    ): array {
        try {
            return [$storage->attach($scope), $result];
        } catch (Throwable $throwable) {
            return [null, $result->add(new ConformanceViolation('attach.throws', sprintf(
                'attach() threw: %s',
                $throwable->getMessage()
            )))];
        }
    }

    private function detachOrNull(
        ContextStorageInterface $storage,
        ?ContextTokenInterface $token,
        ConformanceResult $result,
    ): ConformanceResult {
        if (!$token instanceof ContextTokenInterface) {
            return $result;
        }

        try {
            $storage->detach($token);
        } catch (Throwable $throwable) {
            $result = $result->add(new ConformanceViolation('detach.throws', sprintf(
                'detach() threw: %s',
                $throwable->getMessage()
            )));
        }

        return $result;
    }
}
