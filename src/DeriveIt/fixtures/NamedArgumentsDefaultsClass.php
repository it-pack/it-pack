<?php

declare(strict_types=1);

namespace _Fixture;

class NamedArgumentsDefaultsClass
{
	public function configure(string $host = 'localhost', int $port = 8080, bool $secure = false): static
	{
	}
}
