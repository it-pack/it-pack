<?php

namespace _Fixture;

interface FluentBuilderClassInterface
{
	function withName(string $name): static;


	function withVersion(int $version): static;


	function build(): object;
}
