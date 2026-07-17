<?php

declare(strict_types=1);

namespace _Vendor\Package\SubNamespace;

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
