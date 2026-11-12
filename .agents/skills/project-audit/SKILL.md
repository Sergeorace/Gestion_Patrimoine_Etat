---
name: project-audit
description: Audit an existing software repository before making changes. Use when onboarding to an unfamiliar codebase, planning a feature, assessing technical debt, or preparing a migration.
---

# Project audit

1. Start with read-only discovery. Inspect the repository tree, README, package/composer files, environment examples, routes, database schema/migrations, tests, and Git status.
2. Identify the actual stack and versions from files; do not assume the user's requested stack is already present.
3. Map entry points, modules, data flow, authentication, external services, build commands, test commands, and deployment configuration.
4. Report confirmed facts separately from assumptions and unknowns. Never invent missing requirements.
5. Before editing, provide a concise plan listing files likely to change, risks, and verification steps.
6. Preserve existing behavior and conventions unless the task explicitly requests a change.
7. Do not expose secrets. Read `.env` only when necessary, never print values, and never commit credentials.
8. After implementation, summarize changes, commands run, test results, and remaining risks.
