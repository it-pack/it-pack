<?php

declare(strict_types=1);

namespace _Fixture;

readonly class ReadonlyClass
{
	public function __construct(
		public string $id,
		public \DateTimeImmutable $createdAt,
	) {
	}
}
