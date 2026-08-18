<?php

namespace _Fixture;

interface UsesMultipleTraitsClassInterface
{
	function ownMethod(): void;


	function fromTrait(): void;


	function overridden(): string;
}
