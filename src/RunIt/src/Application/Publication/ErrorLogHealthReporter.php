<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use Closure;
use ItPack\RunIt\Contract\Application\Publication\InternalHealthReporterInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\ThrowableMapperInterface;
use ItPack\RunIt\Domain\Shared\DefaultThrowableMapper;
use Throwable;

/**
 * Health reporter that writes failures to the PHP error log.
 *
 * The reported message is taken from the throwable mapper's safe description —
 * truncated and structural — rather than the raw exception message, so a fault
 * carrying sensitive content cannot leak through the one path that is
 * specifically reached when the publication pipeline itself fails. Artifact
 * identifiers are deliberately excluded so no business identifier or secret
 * reaches the error log through this sink.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final readonly class ErrorLogHealthReporter implements InternalHealthReporterInterface
{
    private const string LOG_PREFIX = '[runit] publication health: ';

    private Closure $logTo;

    public function __construct(
        private ThrowableMapperInterface $throwableMapper = new DefaultThrowableMapper(),
        ?Closure $logTo = null,
    ) {
        $this->logTo = $logTo ?? static function (string $line): void {
            error_log($line);
        };
    }

    public function report(Throwable $error, ExecutionArtifactInterface $artifact): void
    {
        $description = $this->throwableMapper->describe($error);

        ($this->logTo)(sprintf(
            self::LOG_PREFIX . '[%s] %s in %s:%d while publishing %s',
            $description->class(),
            $description->message(),
            $error->getFile(),
            $error->getLine(),
            $artifact::class,
        ));
    }
}
