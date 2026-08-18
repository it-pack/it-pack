<?php

namespace _Fixture;

interface NodeInterface
{
	function getNext(): ?Node;


	function addChild(Node $child): void;


	function label(): string;
}
