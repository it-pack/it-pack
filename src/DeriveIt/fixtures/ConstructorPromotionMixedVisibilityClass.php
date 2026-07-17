<?php

declare(strict_types=1);

namespace _Fixture;

class ConstructorPromotionMixedVisibilityClass
{
	public function __construct(
		public string $name,
		private string $email,
		protected string $password,
		public readonly int $age,
		string $plain,
	) {
	}
}
