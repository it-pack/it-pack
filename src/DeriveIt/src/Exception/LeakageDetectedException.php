<?php

declare(strict_types=1);

namespace ItPack\DeriveIt\Exception;

use LogicException;

/**
 * Thrown when a derived interface would leak the adaptee hierarchy (e.g. an
 * interface that extends or references a type it must not). Replaces the previous
 * generic LogicException so callers can distinguish leakage failures.
 */
final class LeakageDetectedException extends LogicException
{
}
