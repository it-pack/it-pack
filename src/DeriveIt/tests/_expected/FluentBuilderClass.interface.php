<?php

namespace _Fixture;

interface FluentBuilderClassInterface
{
	function withName(string $name): FluentBuilderClassInterface;


	function withVersion(int $version): FluentBuilderClassInterface;


	function build(): object;
}
