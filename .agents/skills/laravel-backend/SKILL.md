---
name: laravel-backend
description: Implement and maintain Laravel/PHP backends, including routes, controllers, services, Eloquent models, validation, authentication, migrations, and tests.
---

# Laravel backend workflow

1. Inspect `composer.json`, Laravel version, route files, providers, models, migrations, policies, and existing tests.
2. Follow the installed Laravel version's conventions; verify APIs against the project's dependencies rather than assuming the latest version.
3. Keep controllers focused. Put reusable business rules in services/actions when that matches the existing architecture.
4. Validate all incoming data with Form Requests or the project's established validation pattern.
5. Enforce authorization with policies/gates or middleware; authentication alone is not authorization.
6. Use Eloquent relationships and eager loading appropriately. Avoid N+1 queries and mass-assignment vulnerabilities.
7. Design migrations to preserve existing data. Never drop or rewrite production data without explicit approval and a backup/rollback plan.
8. Keep secrets in environment configuration; update `.env.example` with variable names only.
9. Add feature/unit tests for success paths, validation failures, authorization failures, and edge cases.
10. Run migrations only against an explicitly designated development/test database. Run tests and relevant static checks before reporting.
