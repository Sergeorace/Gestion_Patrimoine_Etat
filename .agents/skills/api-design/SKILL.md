---
name: api-design
description: Design, implement, document, and test REST APIs, including endpoint conventions, validation, authentication, authorization, error handling, and integration.
---

# API design workflow

1. Inspect existing routes, API versioning, authentication, data models, and response conventions.
2. Use resource-oriented URLs and correct HTTP methods/status codes. Avoid breaking existing clients without an approved versioning or migration plan.
3. Validate request bodies, query parameters, and path parameters at the boundary.
4. Apply authentication and per-resource authorization. Never trust client-supplied ownership or role fields.
5. Return consistent JSON response and error shapes; do not leak stack traces, SQL, tokens, or internal secrets.
6. Support pagination, filtering, sorting, and limits for potentially large collections.
7. Make non-idempotent operations safe against accidental duplicate submission where appropriate.
8. Document endpoints with examples, required fields, response codes, and auth requirements.
9. Test success, malformed input, missing authentication, forbidden access, missing resources, and boundary cases.
