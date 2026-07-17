<?php

declare(strict_types=1);

namespace _Fixture;

class StaticHeavyClass
{
	public static string $publicStatic;
	private static array $privateStatic = [];


	public static function staticPublicMethod(): static
	{
	}


	private static function staticPrivateHelper(): void
	{
	}


	public function instanceMethod(): void
	{
	}
}
