<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use Closure;
use ItPack\RunIt\Contract\Application\Publication\InternalHealthReporterInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\ThrowableMapperInterface;
use ItPack\RunIt\Domain\Shared\ArtifactField;
use ItPack\RunIt\Domain\Shared\DefaultThrowableMapper;
use Throwable;

/**
 * Health reporter that writes failures to the PHP error log.
 *
 * The reported message is taken from the throwable mapper's safe description —
 * truncated and structural — rather than the raw exception message, so a fault
 * carrying sensitive content cannot leak through the one path that is
 * specifically reached when the publication pipeline itself fails. The message
 * includes the artifact type and id so operators can correlate the dropped or
 * recursive publication without it ever calling the guarded publisher.
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
        $id = $artifact->normalize()[ArtifactField::ID] ?? '';
        $idText = is_string($id) ? $id : '';

        $description = $this->throwableMapper->describe($error);

        ($this->logTo)(sprintf(
            self::LOG_PREFIX . '[%s] %s in %s:%d while publishing %s %s',
            $description->class(),
            $description->message(),
            $error->getFile(),
            $error->getLine(),
            $artifact::class,
            $idText,
        ));
    }
}
