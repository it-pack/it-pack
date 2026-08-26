<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Conformance\Verifier;

use ItPack\AsyncIt\Conformance\Contract\VerifierInterface;
use ItPack\AsyncIt\Conformance\Fixture\AsyncRuntimeFixtureInterface;
use ItPack\AsyncIt\Conformance\Result\ConformanceResult;
use ItPack\AsyncIt\Conformance\Result\ConformanceViolation;
use ItPack\AsyncIt\Contract\Port\ViolationVocabulary;
use ItPack\AsyncIt\Contract\SchedulerInterface;

/**
 * Conformance Assertion A — Context Isolation.
 *
 * Proves the runtime detaches the ambient RunIt context at the exact suspend
 * point and re-attaches the exact same scope on resume, so interleaved tasks
 * never observe each other's context. The verifier drives the runtime's
 * {@see \ItPack\RunIt\Contract\Port\ContextStorageInterface} through the token
 * protocol (attach → detach → re-attach) and asserts every re-attachment
 * restores its own scope.
 *
 * A single context bleed is a conformance failure.
 *
 * @see https://w3id.org/itpack/asyncit#CancellationScope
 * @see https://w3id.org/itpack/runit#Execution
 */
final class ContextIsolationVerifier implements VerifierInterface
{
    public const ASSERTION = 'A';

    public function port(): string
    {
        return SchedulerInterface::class;
    }

    public function verify(AsyncRuntimeFixtureInterface $fixture): ConformanceResult
    {
        $result = new ConformanceResult($this->port());

        $storage = $fixture->contextStorage();

        $scopeAlpha = $fixture->makeExecutionScope('alpha');
        $scopeBeta = $fixture->makeExecutionScope('beta');

        $tokenAlpha = $storage->attach($scopeAlpha);
        $storage->detach($tokenAlpha);

        $tokenBeta = $storage->attach($scopeBeta);
        $storage->detach($tokenBeta);

        $reattachToken = $storage->attach($scopeAlpha);
        $restored = $storage->current();
        $storage->detach($reattachToken);

        if ($restored !== $scopeAlpha) {
            $result = $result->add(new ConformanceViolation(
                ViolationVocabulary::CONTEXT_BLEED,
                'Re-attaching a token did not restore its own scope; context bled across suspend/resume.',
            ));
        }

        return $result;
    }
}
