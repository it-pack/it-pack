<?php

namespace _Fixture;

interface ServiceInterface
{
	function name(): string;


	function collaborator(): Collaborator;


	function maybe(): ?Collaborator;


	function reset(): void;
}
