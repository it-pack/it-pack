<?php

namespace _Fixture;

interface DeepGrandchildClassInterface
{
	function grandchildMethod(): void;


	function abstractMethod(): string;


	function childOnlyMethod(): void;


	function concreteBaseMethod(): void;
}
