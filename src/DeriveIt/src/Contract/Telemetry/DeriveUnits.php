<?php

declare(strict_types=1);

namespace ItPack\DeriveIt\Contract\Telemetry;

/**
 * Canonical unit strings emitted with {@see DeriveEventSinkInterface::measured()}
 * (REVIEW.md F-3 / R4-1). The RunIt bridge maps these onto its measurement
 * kinds; sinks written elsewhere should key off the same values.
 */
interface DeriveUnits
{
    /** Cardinality — mapped to RunIt's counter. */
    public const string UNIT_COUNT = 'count';

    /** Byte size — mapped to RunIt's size. */
    public const string UNIT_BYTES = 'By';

    /** Millisecond duration — mapped to RunIt's duration (reserved; not yet emitted). */
    public const string UNIT_MILLISECONDS = 'ms';

    /** Percentage — mapped to RunIt's ratio (reserved; not yet emitted). */
    public const string UNIT_PERCENT = '%';
}
