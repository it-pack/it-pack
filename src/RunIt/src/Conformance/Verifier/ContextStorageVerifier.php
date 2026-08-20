<?php

declare(strict_types=1);

namespace ItPack\RunIt\Conformance\Verifier;

use ItPack\RunIt\Conformance\Result\ConformanceResult;
use ItPack\RunIt\Conformance\Result\ConformanceViolation;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Port\ContextStorageInterface;
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
final class ContextStorageVerifier
{
    public function port(): string
    {
        return ContextStorageInterface::class;
    }

    public function verify(ContextStorageInterface $storage, ExecutionScopeInterface $scope): ConformanceResult
    {
        $result = new ConformanceResult($this->port());

        $token = $this->safeAttach($storage, $scope, $result);
        if ($token === null) {
            return $result;
        }

        $top = $storage->current();
        if ($top !== $scope) {
            $result = $result->add(new ConformanceViolation('current.after-attach', 'current() did not return the attached scope'));
        }

        $secondToken = $this->safeAttach($storage, $scope, $result);
        if ($secondToken !== null) {
            if ($storage->current() !== $scope) {
                $result = $result->add(new ConformanceViolation('current.not-lifo', 'a second attach did not become the current scope'));
            }

            $this->safeDetach($storage, $secondToken, $result);
            if ($storage->current() !== $scope) {
                $result = $result->add(new ConformanceViolation('detach.wrong-scope', 'detaching the inner scope did not restore the outer scope'));
            }
        }

        $this->safeDetach($storage, $token, $result);
        if ($storage->current() !== null) {
            $result = $result->add(new ConformanceViolation('detach.incomplete', 'current() still returned a scope after detaching the last one'));
        }

        return $result;
    }

    private function safeAttach(ContextStorageInterface $storage, ExecutionScopeInterface $scope, ConformanceResult $result): ?\ItPack\RunIt\Contract\Port\ContextTokenInterface
    {
        try {
            $token = $storage->attach($scope);
        } catch (Throwable $e) {
            $result->add(new ConformanceViolation('attach.throws', sprintf('attach() threw: %s', $e->getMessage())));

            return null;
        }

        return $token;
    }

    private function safeDetach(ContextStorageInterface $storage, \ItPack\RunIt\Contract\Port\ContextTokenInterface $token, ConformanceResult $result): void
    {
        try {
            $storage->detach($token);
        } catch (Throwable $e) {
            $result->add(new ConformanceViolation('detach.throws', sprintf('detach() threw: %s', $e->getMessage())));
        }
    }
}
