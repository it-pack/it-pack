RESOLUTION.md

This document explains

How does ConfigureIt produce one final configuration?

Contents

1. Resolution Philosophy

2. Source Priority

3. Merge Strategies

4. Variable Resolution

5. Placeholder Resolution

6. Includes

7. Imports

8. Defaults

9. Validation

10. Snapshot Generation

Example

base.yaml

↓

dev.yaml

↓

.env

↓

CLI

↓

Runtime

↓

Snapshot

Merge strategies

Replace

Deep Merge

Recursive

Append

Unique

Priority

First Wins

Last Wins

Variable resolution

${env(APP_ENV)}

↓

development
${database.host}

↓

localhost
${vault(secret/db)}

↓

******

Includes

imports:
- database.yaml
- cache.yaml
- mail.yaml

Environment overrides

config/

↓

packages/

↓

packages/dev/

↓

.env

↓

CLI

↓

Runtime

Snapshot generation

Documents

↓

Processors

↓

Resolved Graph

↓

Immutable Snapshot

↓

Application