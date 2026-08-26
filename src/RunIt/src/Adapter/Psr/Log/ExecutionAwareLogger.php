<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\Psr\Log;

use ItPack\RunIt\Contract\Application\ActivityScopeInterface;
use ItPack\RunIt\Contract\Application\CurrentExecutionInterface;
use ItPack\RunIt\Domain\Shared\ArtifactField;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;

/**
 * Decorates any PSR-3 logger so every log line is enriched with the active
 * RunIt execution (and, when present, activity) correlation.
 *
 * The decorator reads the ambient current execution through
 * {@see CurrentExecutionInterface} and adds `execution_id` (and `activity_id`
 * when an activity is active) to the logger context. When no execution is active
 * the original call passes through unchanged. Existing context values are
 * preserved according to the configured {@see LoggerCollisionPolicy}; by default
 * the caller wins and the execution correlation is added under a `runit.`
 * namespaced key.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class ExecutionAwareLogger extends AbstractLogger implements LoggerInterface
{
    public function __construct(
        private readonly LoggerInterface $inner,
        private readonly CurrentExecutionInterface $current,
        private readonly LoggerCollisionPolicy $collisionPolicy = LoggerCollisionPolicy::PreserveCaller,
        private readonly string $executionKey = ArtifactField::EXECUTION_ID,
        private readonly string $activityKey = ArtifactField::ACTIVITY_ID,
        private readonly string $namespacedPrefix = 'runit.',
    ) {
    }

    public function log(mixed $level, string|\Stringable $message, array $context = []): void
    {
        $scope = $this->current->get();

        if ($scope === null) {
            $this->inner->log($level, $message, $context);

            return;
        }

        $correlation = [$this->executionKey => $scope->execution()->id()->toString()];

        if ($scope instanceof ActivityScopeInterface) {
            $correlation[$this->activityKey] = $scope->activity()->id()->toString();
        }

        $context = $this->merge($context, $correlation);

        $this->inner->log($level, $message, $context);
    }

    /**
     * @param array<array-key, mixed> $context
     * @param array<string, string> $correlation
     * @return array<array-key, mixed>
     */
    private function merge(array $context, array $correlation): array
    {
        foreach ($correlation as $key => $value) {
            if (array_key_exists($key, $context)) {
                if ($this->collisionPolicy === LoggerCollisionPolicy::PreserveCaller) {
                    $context[$this->namespacedPrefix . $key] = $value;

                    continue;
                }

                // Overwrite: replace the caller-supplied value.
            }

            $context[$key] = $value;
        }

        return $context;
    }
}
