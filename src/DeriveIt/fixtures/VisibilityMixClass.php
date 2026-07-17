<?php

declare(strict_types=1);

namespace _Fixture;

class VisibilityMixClass
{
	public string $publicProp;
	protected string $protectedProp;
	private string $privateProp;


	public function publicMethod(): void
	{
	}


	protected function protectedMethod(): void
	{
	}


	private function privateMethod(): void
	{
	}
}
