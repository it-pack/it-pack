<?php

declare(strict_types=1);

namespace _Fixture;

class DocBlockRichClass
{
	/**
	 * Process the payload.
	 *
	 * @param \_Fixture\Collaborator $collaborator the collaborator to use
	 * @return \_Fixture\Collaborator the processed collaborator
	 * @throws \RuntimeException if processing fails
	 * @deprecated since v2.0; use processV2() instead
	 */
	public function process(\_Fixture\Collaborator $collaborator): \_Fixture\Collaborator
	{
	}


	/**
	 * @throws \DomainException when the input is invalid
	 */
	public function validate(string $input): bool
	{
	}
}
