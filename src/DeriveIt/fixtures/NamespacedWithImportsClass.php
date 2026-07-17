<?php

declare(strict_types=1);

namespace _Fixture;

class NamespacedWithImportsClass
{
	public function __construct(
		public \Psr\Log\LoggerInterface $logger,
	) {
	}


	public function timestamp(): \DateTimeImmutable
	{
	}
}
