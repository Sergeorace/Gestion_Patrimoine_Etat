---
name: docker-devops
description: Containerize applications and improve local development or deployment workflows using Docker, Compose, environment configuration, and CI pipelines.
---

# Docker and DevOps workflow

1. Inspect the application runtime, dependency manager, ports, persistent data needs, and existing deployment setup.
2. Create a minimal Dockerfile with pinned or bounded base-image versions and reproducible dependency installation.
3. Use multi-stage builds where they reduce image size or keep build tools out of runtime images.
4. Use Docker Compose for coordinated local services where useful; define health checks and persistent volumes deliberately.
5. Keep secrets out of images, source control, build arguments, and logs. Provide `.env.example` without real credentials.
6. Run as a non-root user where practical, expose only required ports, and avoid unnecessary packages.
7. Document build, start, stop, logs, reset, and migration commands.
8. Validate with `docker compose config`, build the image, start services, and run a smoke test.
9. Never delete volumes or databases unless the user explicitly approves data loss.
