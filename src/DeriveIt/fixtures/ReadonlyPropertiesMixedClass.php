<?php

declare(strict_types=1);

namespace _Fixture;

class ReadonlyPropertiesMixedClass
{
	public string $mutable;
	public readonly string $immutable;


	public function __construct(
		public readonly int $readonlyPromoted,
		public int $mutablePromoted,
	) {
	}
}
