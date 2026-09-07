<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Domain\Value;

/**
 * Distinguishes lightweight tags (direct refs to commits) from annotated
 * tags (which carry a message, tagger, and date).
 *
 * @api
 */
enum TagType: string
{
    case Lightweight = 'lightweight';
    case Annotated = 'annotated';
}
