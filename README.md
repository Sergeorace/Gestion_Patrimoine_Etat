# Plateforme de gestion du patrimoine de l'État togolais

Solution numérique de recensement, suivi, affectation, maintenance, inventaire et sortie des biens publics de l'État togolais.

## Architecture

Micro-services Laravel (PHP 8.3+) + PostgreSQL 16 + RabbitMQ + Redis, orchestrés par Docker Compose, avec API Gateway Nginx et interface web (Inertia.js / Vue 3 / Tailwind CSS).

```
Navigateur ──▶ api-gateway (Nginx) ──▶ web-app (Inertia/Vue)
                                    └──▶ auth / organisation / asset / movement /
                                         inventory / maintenance / valuation /
                                         document / notification / reporting
                                              │                    │
                                        PostgreSQL (1 base/service)  RabbitMQ / Redis
```

## Structure du dépôt

```
docs/            Documentation par étape (analyse, UML, design, finances, fiche technique, guide)
services/        Un dossier par micro-service Laravel (étape 6)
frontend/        Application web Inertia/Vue (étape 6)
docker/          Configuration nginx, postgres init, etc.
docker-compose.yml
```

## Prérequis

- Docker Engine + Docker Compose v2
- 8 Go de RAM minimum recommandés pour l'ensemble des services

## Installation (une seule commande)

```bash
docker compose up -d --build
```

Puis exécuter les migrations et le seeding (voir `docs/06-guide-utilisation/` pour le détail).

## Comptes de démonstration

> En cours de définition — les comptes seront documentés à l'issue de l'initialisation des seeders (étape 6).

## Workflow Git

| Branche | Type | Statut | Contenu |
|---|---|---|---|
| `main` | permanente | stable | Production — fusion de `develop` par étape validée, avec tag de version |
| `develop` | permanente | intégration | Fusion des branches de fonctionnalités validées |
| `docs/analyse-besoin` | documentation | en cours | Étape 1 : analyse du besoin |
| `docs/conception-uml` | documentation | à venir | Étape 2 : diagrammes UML |
| `docs/design-figma` | documentation | à venir | Étape 3 : design UI/UX |
| `docs/proposition-financiere` | documentation | à venir | Étape 4 : chiffrage |
| `docs/fiche-technique` | documentation | à venir | Étape 5 : fiche technique |
| `docs/guide-utilisation` | documentation | à venir | Étape 7 : guide utilisateur |
| `feature/<service>-<fonctionnalité>` | fonctionnalité | à venir | Une branche = une fonctionnalité, PR vers `develop` |

Règles :

- Chaque fonctionnalité est développée sur sa propre branche `feature/<service>-<fonctionnalité>` créée depuis `develop`.
- Chaque branche fait l'objet d'une Pull Request vers `develop` ; aucun commit direct sur `main` ou `develop`.
- Après validation, merge dans `develop` (`--no-ff` ou squash) puis suppression de la branche.
- `develop` est fusionnée dans `main` à la fin de chaque étape complète, avec un tag `vX.Y.Z`.
- Commits : Conventional Commits, messages explicites, en français.

## Documentation

- [Analyse du besoin](docs/01-analyse-besoin/analyse-besoin.md) (étape 1)
- Les étapes suivantes seront ajoutées au fur et à mesure.

## Licence

Projet interne — État togolais. Tous droits réservés.
