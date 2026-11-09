<?php

declare(strict_types=1);

// Run separately: php tests/target-policy-terminal-publication-regressions.php
// Factory-failure checks express the approved policy, not current behavior.
require __DIR__ . '/../vendor/autoload.php';

use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Application\ExecutionSnapshotFactoryInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\ExecutionInterface;
use ItPack\RunIt\Contract\Domain\ExecutionSnapshotInterface;
use ItPack\RunIt\Contract\Domain\InstantInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\Domain\Execution\ExecutionStatus;
use Tests\Support\Fixture\RunItTestKit;

$failures = [];
$check = /** Collect a failed assertion without stopping the remaining cases. */
    static function (bool $condition, string $message) use (&$failures): void {
        if (!$condition) {
            $failures[] = $message;
        }
    };
$definition = ExecutionDefinition::create(ExecutionName::fromString('op'), ExecutionKind::fromString('task'));

foreach ([false, true] as $factoryFails) {
    foreach ([false, true] as $businessFails) {
        $label = ($factoryFails ? 'factory failure' : 'factory success')
            . ', business ' . ($businessFails ? 'failure' : 'success');
        $factoryError = new RuntimeException('snapshot factory failed');
        $factory = new class ($factoryError) implements ExecutionSnapshotFactoryInterface {
            public int $calls = 0;

            /** Keep the error that the snapshot factory will throw. */
            public function __construct(private readonly RuntimeException $error)
            {
            }

            /** Count the attempt and fail snapshot creation with the configured error. */
            public function terminal(ExecutionInterface $execution): ExecutionSnapshotInterface
            {
                ++$this->calls;
                throw $this->error;
            }
        };
        $publisher = new class () implements ArtifactPublisherInterface {
            /** @var list<string> */
            public array $terminal = [];

            /** @var list<ExecutionInterface> */
            public array $executions = [];

            /** Record terminal executions and snapshots in publication order. */
            public function publish(ExecutionArtifactInterface $artifact): void
            {
                if ($artifact instanceof ExecutionInterface && $artifact->endedAt() instanceof InstantInterface) {
                    $this->terminal[] = 'execution';
                    $this->executions[] = $artifact;
                } elseif ($artifact instanceof ExecutionSnapshotInterface) {
                    $this->terminal[] = 'snapshot';
                }
            }
        };
        $reported = [];
        $outer = new RunItTestKit();
        $inner = new RunItTestKit(
            publisher: $publisher,
            onPublicationFailure: /** Collect publication errors for identity and count assertions. */
                static function (Throwable $error) use (&$reported): void {
                    $reported[] = $error;
                },
            storage: $outer->storage,
            snapshotFactory: $factoryFails ? $factory : null,
        );
        $businessError = new RuntimeException('business failed');
        $value = new stdClass();
        $outer->runner->run($definition, /** Check the inner outcome and restoration of the parent scope. */
            static function (ExecutionScopeInterface $parent) use (
                $inner,
                $definition,
                $businessFails,
                $businessError,
                $value,
                $check,
                $label,
            ): void {
                $caught = null;
                $result = null;
                try {
                    $result = $inner->runner->run($definition, /** Return the value or throw the business error. */
                        static function () use (
                            $businessFails,
                            $businessError,
                            $value,
                        ): object {
                            if ($businessFails) {
                                throw $businessError;
                            }

                            return $value;
                        });
                } catch (Throwable $throwable) {
                    $caught = $throwable;
                }

                $check($caught === ($businessFails ? $businessError : null), "$label: original exception identity");
                $check($result === ($businessFails ? null : $value), "$label: original result identity");
                $check($inner->storage->current() === $parent, "$label: prior context restored");
            });

        $check(!$outer->storage->current() instanceof ExecutionScopeInterface, "$label: outer context detached");
        $check($reported === ($factoryFails ? [$factoryError] : []), "$label: factory error reported exactly once");
        $check(!$factoryFails || $factory->calls === 1, "$label: factory called exactly once");
        $check(
            $publisher->terminal === ($factoryFails ? ['execution'] : ['execution', 'snapshot']),
            "$label: terminal execution published before any snapshot",
        );
        $check(count($publisher->executions) === 1, "$label: exactly one terminal execution published");
        $check(
            ($publisher->executions[0] ?? null)?->status()
                === ($businessFails ? ExecutionStatus::Failed : ExecutionStatus::Succeeded),
            "$label: terminal execution has the business outcome",
        );
    }
}

foreach ($failures as $failure) {
    fwrite(STDERR, "FAIL: $failure\n");
}

if ($failures !== []) {
    fwrite(STDERR, count($failures) . " terminal-publication target-policy checks failed.\n");
    exit(1);
}

fwrite(STDOUT, "Terminal-publication target-policy checks passed.\n");
