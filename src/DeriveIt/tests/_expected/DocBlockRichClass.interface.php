<?php

namespace _Fixture;

interface DocBlockRichClassInterface
{
	function process(Collaborator $collaborator): Collaborator;


	function validate(string $input): bool;
}
