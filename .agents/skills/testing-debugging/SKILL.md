---
name: testing-debugging
description: Reproduce bugs, identify root causes, write automated tests, and verify fixes across frontend, backend, APIs, and desktop applications.
---

# Testing and debugging workflow

1. Reproduce the reported failure before changing code. Capture the exact command, error, stack trace, and environment.
2. Trace the failure to its root cause; distinguish the first meaningful error from secondary warnings.
3. Inspect adjacent code and existing tests. Avoid speculative edits or broad rewrites for a narrow defect.
4. Create or update a regression test that fails before the fix when feasible.
5. Make the smallest safe change consistent with project conventions.
6. Run the focused test first, then the broader test suite, lint/type checks, and build as appropriate.
7. If a test cannot run, state why and do not claim it passed.
8. Report root cause, changed files, verification commands/results, and any remaining uncertainty.
