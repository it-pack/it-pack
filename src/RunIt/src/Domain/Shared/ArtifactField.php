<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Shared;

/**
 * Canonical normalized artifact field names.
 *
 * Every artifact builds its `normalize()` projection from these keys, and the
 * security controls that reach back into that projection (`DefaultArtifactRedactor`,
 * `ErrorLogHealthReporter`) read the same constants. A field rename is therefore a
 * single-site change here: decoupling a literal from this vocabulary would make
 * redaction silently no-op, which is the S10 defect.
 *
 * Declared as an interface (rather than a const class) so it carries no instance
 * surface and satisfies the project's `requireInterface` rule without an exemption.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
interface ArtifactField
{
    public const string ID = 'id';

    public const string CONTEXT = 'context';

    public const string ATTRIBUTES = 'attributes';

    public const string THROWABLE = 'throwable';

    public const string DATA = 'data';

    public const string PAYLOAD = 'payload';

    public const string MESSAGE = 'message';
}
