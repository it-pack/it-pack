<?php

namespace _Fixture;

interface NodeInterface
{
	function getNext(): ?NodeInterface;


	function addChild(NodeInterface $child): void;


	function label(): string;
}
