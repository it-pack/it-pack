<?php

namespace _Fixture;

interface NamedArgumentsDefaultsClassInterface
{
	function configure(
		string $host = 'localhost',
		int $port = 8080,
		bool $secure = false,
	): NamedArgumentsDefaultsClassInterface;
}
