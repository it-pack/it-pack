<?php

namespace _Fixture;

interface ConcreteChildClassInterface
{
	function abstractMethod(): string;


	function childOnlyMethod(): void;


	function concreteBaseMethod(): void;
}
