<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Common;

/**
 * Marker interface for every exception originated by CommitIt.
 *
 * Concrete throwables live closer to where they carry meaning (domain exceptions
 * implement the {@see Contract\Exception} markers), but all of them are
 * {@see CommitItException} so consumers can catch the whole family.
 *
 * @api
 */
interface CommitItException extends \Throwable
{
}
