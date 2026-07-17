<?php

declare(strict_types=1);

namespace _Fixture;

class ConstructorWithComplexDefaultsClass
{
	public const DEFAULT_TIMEOUT = 30;

	public function __construct(string $mode = 'default', array $flags = [], int $timeout = self::DEFAULT_TIMEOUT)
	{
	}
}
