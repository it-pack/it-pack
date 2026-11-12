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

    public const string EXCEPTION = 'exception';

    public const string THROWABLE = 'throwable';

    public const string DATA = 'data';

    public const string PAYLOAD = 'payload';

    public const string MESSAGE = 'message';

    public const string CORRELATION = 'correlation';

    // Identity and relationships

    public const string EXECUTION_ID = 'execution_id';

    public const string ACTIVITY_ID = 'activity_id';

    public const string OBSERVATION_ID = 'observation_id';

    public const string PARENT_EXECUTION_ID = 'parent_execution_id';

    public const string LINKED_EXECUTION_IDS = 'linked_execution_ids';

    public const string PARENT_ACTIVITY_ID = 'parent_activity_id';

    public const string PREDECESSOR_ID = 'predecessor_id';

    // Lifecycle, naming, and ordering

    public const string NAME = 'name';

    public const string KIND = 'kind';

    public const string STATUS = 'status';

    public const string RESULT = 'result';

    public const string SEQUENCE = 'sequence';

    public const string STARTED_AT = 'started_at';

    public const string ENDED_AT = 'ended_at';

    public const string CREATED_AT = 'created_at';

    public const string TIMESTAMP = 'timestamp';

    public const string TIME = 'time';

    public const string EXPIRES_AT = 'expires_at';

    public const string DURATION_NS = 'duration_ns';

    // Observation specifics

    public const string SEVERITY = 'severity';

    public const string LEVEL = 'level';

    public const string DIAGNOSTIC_CODE = 'diagnostic_code';

    // Measurement specifics

    public const string UNIT = 'unit';

    public const string VALUE = 'value';

    // Occurrence and snapshot typing

    public const string TYPE = 'type';

    public const string VERSION = 'version';

    public const string SOURCE = 'source';

    public const string SUBJECT = 'subject';

    public const string DATA_SCHEMA = 'data_schema';

    public const string CONTENT_TYPE = 'content_type';

    public const string EXTENSION_ATTRIBUTES = 'extension_attributes';

    public const string CLASSIFICATION = 'classification';

    public const string SCHEMA_VERSION = 'schema_version';

    public const string CONTENT_HASH = 'content_hash';

    // Context entries

    public const string KEY = 'key';

    // Throwable descriptions ('class' is a reserved word, hence the prefix)

    public const string THROWABLE_CLASS = 'class';

    public const string CODE = 'code';

    public const string PREVIOUS = 'previous';

    public const string TRACE = 'trace';
}
