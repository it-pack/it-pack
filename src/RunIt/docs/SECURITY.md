# Security Policy

## Reporting a vulnerability

Do not open a public issue for a suspected security vulnerability. Report it
privately to the package maintainers through the ItPack security contact, with
reproduction details and the affected version.

## Security model

Context, observations, occurrences, and snapshots can carry sensitive data.
RunIt must classify values, redact before external publication, bound payloads,
and never serialize arbitrary PHP objects. Secret context is deny-by-default
for export. Security fixes receive priority over feature work.
