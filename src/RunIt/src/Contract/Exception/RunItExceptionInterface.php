<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Exception;

/**
 * Root marker interface implemented by every exception RunIt throws publicly.
 *
 * Concrete exception types implement this marker in addition to whatever
 * SPL exception they extend, so consumers can catch failures originating from
 * RunIt without depending on a framework or a package-specific base class.
 */
interface RunItExceptionInterface
{
}
