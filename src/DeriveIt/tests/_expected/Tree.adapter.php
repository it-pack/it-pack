<?php

namespace _Fixture;

interface TreeInterface
{
	function getRoot(): Node;


	function build(Node $seed): Node;


	function count(): int;
}

final class TreeAdapter implements TreeInterface
{
	private readonly Tree $adaptee;


	public function __construct(Tree $adaptee)
	{
		$this->adaptee = $adaptee;
	}


	public function getRoot(): Node
	{
		return $this->adaptee->getRoot();
	}


	public function build(Node $seed): Node
	{
		return $this->adaptee->build($seed);
	}


	public function count(): int
	{
		return $this->adaptee->count();
	}
}
