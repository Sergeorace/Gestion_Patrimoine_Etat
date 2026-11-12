---
name: base44-migration
description: Rebuild or migrate a Base44 application into an independent codebase by inventorying dependencies and replacing platform services while preserving behavior and data.
---

# Base44 application migration

1. Begin with a full inventory of pages, routes, components, workflows, data entities, permissions, authentication, integrations, automations, file storage, and environment configuration.
2. Search the entire repository for Base44 SDK imports, generated client calls, platform URLs, environment variables, and service-specific assumptions.
3. Build a dependency map: feature -> Base44 service -> replacement implementation. Mark unknown behavior explicitly.
4. Preserve a clean backup or Git branch before modifying files. Confirm the working tree and backup location.
5. Work in small phases: frontend inventory, data model, authentication/authorization, backend/API, file storage/integrations, UI rewiring, tests, deployment.
6. Choose replacements only after examining current project constraints and user requirements. Do not silently choose paid services or introduce vendor lock-in.
7. Recreate database schema and migrations, access rules, validation, and server-side authorization. Never rely only on frontend permissions.
8. Migrate data with a repeatable, validated process. Keep source data intact and produce counts/checksums or other reconciliation evidence.
9. Replace each Base44 call and test the corresponding user journey before moving to the next feature.
10. Search again for remaining Base44 references. Classify each as removed, intentionally retained, or unresolved.
11. Run builds, unit/integration/E2E tests, security checks, and manual smoke tests for all critical workflows.
12. Do not delete the original app, source data, backup, or compatibility layer without explicit approval.
13. Deliver an architecture summary, dependency replacement table, setup instructions, migration notes, test evidence, and known limitations.
