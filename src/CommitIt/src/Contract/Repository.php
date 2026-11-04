<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Contract;

/**
 * Vendor-neutral repository operations port.
 *
 * Every operation happens on a repository. References (branch/tag/remote names)
 * are validated value objects, never raw strings.
 *
 * The surface is intentionally split into focused ports — {@see CommitPort},
 * {@see BranchPort}, {@see TagPort}, and {@see RemotePort} — so that new
 * capabilities arrive as new ports composed into this union, rather than as edits
 * to a single god-interface. `Repository` stays the convenience union implementers
 * satisfy in one `implements` clause.
 *
 * The working tree is a *composed collaborator*, not part of this union: it is
 * reached via {@see self::getWorkingTree()}, which returns a {@see WorkingTree}.
 * (Flattening `WorkingTree` into `Repository` would force every repository to also
 * be a working-tree operator and conflict with the observer decorator from F2.)
 *
 * The {@see CommitPort::execute()} method is a generic git command passthrough and
 * is intentionally NON-portable; prefer the dedicated methods and only fall back
 * to {@see CommitPort::execute()} when none apply.
 *
 * @api
 */
interface Repository extends CommitPort, BranchPort, TagPort, RemotePort, WorkingTreePort, ConfigPort
{
}
