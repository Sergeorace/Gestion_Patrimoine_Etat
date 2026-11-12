---
name: sql-database
description: Design, query, optimize, and troubleshoot relational databases using MySQL or PostgreSQL, including schemas, relationships, indexes, migrations, and data integrity.
---

# SQL database workflow

1. Identify the database engine and version before using engine-specific syntax.
2. Inspect the current schema and migrations before proposing changes.
3. Model entities with clear primary keys, foreign keys, appropriate data types, nullability, unique constraints, and check constraints where supported.
4. Normalize transactional data sensibly; denormalize only for a measured reason.
5. Add indexes based on query patterns and explain plans, not indiscriminately.
6. Use parameterized queries or the framework's query builder/ORM. Never concatenate untrusted input into SQL.
7. For updates/deletes, preview affected rows and use transactions when multiple changes must succeed together.
8. Never run destructive statements against production. Require explicit authorization, backup, and rollback plan.
9. Provide reversible migrations where practical and test referential integrity.
10. Explain assumptions and include sample queries or an ERD description when useful.
