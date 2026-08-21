<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Domain\Validation;

/**
 * Discriminates the kind of reference name being validated. The set is closed:
 * branches, tags, and remotes are the only reference kinds CommitIt models.
 */
enum RefKind: string
{
    case Branch = 'branch';
    case Tag = 'tag';
    case Remote = 'remote';
}
