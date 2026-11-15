# Étape 2 — Conception UML

Dossier de conception complet de la plateforme de gestion du patrimoine de l'État togolais.

## Organisation

```
docs/02-conception-uml/
├── README.md                  ← ce fichier
├── sources/                   ← sources Mermaid (.mmd) versionnées
│   ├── cas-utilisation/       ← 10 diagrammes (global + par module)
│   ├── sequences/             ← 7 diagrammes de séquence
│   ├── activites/             ← 3 diagrammes d'activité
│   ├── etats-transitions/     ← 3 diagrammes d'états
│   ├── classes/               ← 11 diagrammes de classes
│   ├── donnees/               ← 10 MLD (un schéma par base de service)
│   ├── composants.mmd         ← architecture micro-services
│   ├── deploiement.mmd        ← conteneurs Docker / réseau / volumes
│   └── objets.mmd             ← exemple d'instances
├── exports/                   ← exports SVG + PNG générés
└── tools/render_exports.py    ← script de (re)génération des exports
```

## Inventaire des diagrammes

### Cas d'utilisation

| Fichier | Contenu |
|---|---|
| `cas-utilisation/global.mmd` | Vue d'ensemble : 8 acteurs, 13 cas d'utilisation |
| `cas-utilisation/auth.mmd` | CU-01, CU-02 : connexion, 2FA, RBAC, verrouillage |
| `cas-utilisation/organisation.mmd` | CU-03 : structures, sites, nomenclatures, amortissement |
| `cas-utilisation/patrimoine.mmd` | CU-04 : fiches biens, QR codes, import, recherche |
| `cas-utilisation/mouvements.mmd` | CU-05, CU-06 : affectations, transferts, sorties |
| `cas-utilisation/inventaire.mmd` | CU-07 : campagnes, scan, écarts, récolement |
| `cas-utilisation/maintenance.mmd` | CU-08 : plans, pannes, interventions, prestataires |
| `cas-utilisation/valorisation.mmd` | CU-09 : amortissements, VNC, réévaluations |
| `cas-utilisation/documents.mmd` | CU-13 : GED, versionnage, contrôle d'accès |
| `cas-utilisation/notifications.mmd` | CU-11 : notifications, journal d'audit |
| `cas-utilisation/reporting.mmd` | CU-10 : tableaux de bord, états, exports |

### Diagrammes de séquence

| Fichier | Scénario |
|---|---|
| `sequences/connexion.mmd` | Login + 2FA + émission JWT |
| `sequences/enregistrement-bien.mmd` | Création fiche bien + QR + pièces |
| `sequences/affectation.mmd` | Demande → validation → exécution (scénario nominal CDC §6.3) |
| `sequences/maintenance.mmd` | Plan préventif, panne, ordre d'intervention, clôture |
| `sequences/inventaire.mmd` | Campagne, scan QR, mode hors-ligne, écarts, clôture |
| `sequences/cession.mmd` | Sortie du patrimoine : motif, PV, VNC, archivage |
| `sequences/rapport.mmd` | Tableau de bord national + export PDF |

### Diagrammes d'activité

| Fichier | Processus |
|---|---|
| `activites/affectation.mmd` | Workflow d'affectation/transfert avec validation |
| `activites/inventaire.mmd` | Campagne d'inventaire de bout en bout |
| `activites/sortie.mmd` | Sortie du patrimoine (réforme, cession, rebut…) |

### Diagrammes d'états-transitions

| Fichier | Objet |
|---|---|
| `etats-transitions/bien.mmd` | Cycle de vie d'un bien (CDC §6.4) |
| `etats-transitions/mouvement.mmd` | Demande de mouvement (brouillon → clôturé) |
| `etats-transitions/maintenance.mmd` | Intervention de maintenance |

### Diagrammes de classes

| Fichier | Périmètre |
|---|---|
| `classes/global.mmd` | Vue globale du domaine (14 entités clés) |
| `classes/auth.mmd` | Utilisateurs, rôles, permissions, jetons, journal |
| `classes/organisation.mmd` | Structures, sites, localisations, catégories, nomenclatures |
| `classes/asset.mmd` | Biens, spécificités par type, photos, imports |
| `classes/movement.mmd` | Mouvements, validations, motifs, historique |
| `classes/inventory.mmd` | Campagnes, équipes, constats, écarts, rapports |
| `classes/maintenance.mmd` | Plans, interventions, pannes, prestataires |
| `classes/valuation.mmd` | Valorisations, amortissements, réévaluations |
| `classes/document.mmd` | Documents, versions, règles d'accès |
| `classes/notification.mmd` | Notifications, audit_logs, abonnements, modèles |
| `classes/reporting.mmd` | Agrégats et rapports générés |

### Modèles de données (MLD — un schéma par base de service)

| Fichier | Base |
|---|---|
| `donnees/auth_db.mmd` | 7 tables |
| `donnees/organisation_db.mmd` | 6 tables |
| `donnees/asset_db.mmd` | 8 tables |
| `donnees/movement_db.mmd` | 5 tables |
| `donnees/inventory_db.mmd` | 6 tables |
| `donnees/maintenance_db.mmd` | 5 tables |
| `donnees/valuation_db.mmd` | 4 tables |
| `donnees/document_db.mmd` | 3 tables |
| `donnees/notification_db.mmd` | 4 tables |
| `donnees/reporting_db.mmd` | 5 tables (lecture agrégée) |

### Architecture

- `composants.mmd` — micro-services, gateway, bases, messagerie
- `deploiement.mmd` — conteneurs Docker, réseau `patrimoine-net`, volumes
- `objets.mmd` — exemple d'instances (affectation d'un véhicule)

## Régénérer les exports

```bash
# Nécessite Python 3 + requests (py -m pip install requests)
py docs/02-conception-uml/tools/render_exports.py          # tout
py docs/02-conception-uml/tools/render_exports.py affectation  # un seul motif
```

Les exports sont produits au format SVG et PNG dans `exports/` (mêmes chemins relatifs que `sources/`).

## Conventions

- Sources au format **Mermaid** (`.mmd`), syntaxecompatible avec GitHub, VS Code (extension Mermaid), et kroki.io.
- Chaque diagramme commence par un commentaire `%%` décrivant son objet (conformément au CDC §6.1).
- Les exports sont **générés** : ne pas les éditer à la main ; modifier la source `.mmd` puis relancer le script.
- Le MCD conceptuel est porté par `classes/global.mmd` ; les MLD par `donnees/*.mmd`.

## Annexes — Diagrammes

Tous les exports PNG générés depuis les sources Mermaid (dossier `exports/`).

### Architecture

**Diagramme de composants** — micro-services, gateway, bases, messagerie

![Composants](exports/composants.png)

**Diagramme de déploiement** — conteneurs Docker, réseau `patrimoine-net`, volumes

![Déploiement](exports/deploiement.png)

**Exemple d'instances** — objets métier (affectation d'un véhicule)

![Objets](exports/objets.png)

### Cas d'utilisation

**Vue globale** — 8 acteurs, 13 cas d'utilisation

![Cas d'utilisation global](exports/cas-utilisation/global.png)

**Authentification & RBAC**

![Cas d'utilisation auth](exports/cas-utilisation/auth.png)

**Organisation** — structures, sites, nomenclatures

![Cas d'utilisation organisation](exports/cas-utilisation/organisation.png)

**Patrimoine** — fiches biens, QR codes, import

![Cas d'utilisation patrimoine](exports/cas-utilisation/patrimoine.png)

**Mouvements** — affectations, transferts, sorties

![Cas d'utilisation mouvements](exports/cas-utilisation/mouvements.png)

**Inventaire** — campagnes, scan, écarts, récolement

![Cas d'utilisation inventaire](exports/cas-utilisation/inventaire.png)

**Maintenance** — plans, pannes, interventions

![Cas d'utilisation maintenance](exports/cas-utilisation/maintenance.png)

**Valorisation** — amortissements, VNC, réévaluations

![Cas d'utilisation valorisation](exports/cas-utilisation/valorisation.png)

**Documents** — GED, versionnage, contrôle d'accès

![Cas d'utilisation documents](exports/cas-utilisation/documents.png)

**Notifications & audit**

![Cas d'utilisation notifications](exports/cas-utilisation/notifications.png)

**Reporting** — tableaux de bord, états, exports

![Cas d'utilisation reporting](exports/cas-utilisation/reporting.png)

### Diagrammes de séquence

**Connexion** — login + 2FA + émission JWT

![Séquence connexion](exports/sequences/connexion.png)

**Enregistrement d'un bien** — fiche + QR + pièces

![Séquence enregistrement bien](exports/sequences/enregistrement-bien.png)

**Affectation** — demande → validation → exécution

![Séquence affectation](exports/sequences/affectation.png)

**Maintenance** — plan, panne, ordre d'intervention, clôture

![Séquence maintenance](exports/sequences/maintenance.png)

**Inventaire** — campagne, scan QR, hors-ligne, écarts, clôture

![Séquence inventaire](exports/sequences/inventaire.png)

**Cession / sortie** — motif, PV, VNC, archivage

![Séquence cession](exports/sequences/cession.png)

**Rapport** — tableau de bord national + export PDF

![Séquence rapport](exports/sequences/rapport.png)

### Diagrammes d'activité

**Affectation / transfert** — workflow avec validation

![Activité affectation](exports/activites/affectation.png)

**Campagne d'inventaire** — de bout en bout

![Activité inventaire](exports/activites/inventaire.png)

**Sortie du patrimoine** — réforme, cession, rebut…

![Activité sortie](exports/activites/sortie.png)

### Diagrammes d'états-transitions

**Cycle de vie d'un bien** (CDC §6.4)

![États bien](exports/etats-transitions/bien.png)

**Demande de mouvement** — brouillon → clôturé

![États mouvement](exports/etats-transitions/mouvement.png)

**Intervention de maintenance**

![États maintenance](exports/etats-transitions/maintenance.png)

### Diagrammes de classes

**Vue globale du domaine** — 14 entités clés

![Classes global](exports/classes/global.png)

**Auth** — utilisateurs, rôles, permissions, jetons

![Classes auth](exports/classes/auth.png)

**Organisation** — structures, sites, catégories

![Classes organisation](exports/classes/organisation.png)

**Asset** — biens, spécificités, photos, imports

![Classes asset](exports/classes/asset.png)

**Movement** — mouvements, validations, motifs

![Classes movement](exports/classes/movement.png)

**Inventory** — campagnes, constats, écarts

![Classes inventory](exports/classes/inventory.png)

**Maintenance** — plans, interventions, pannes, prestataires

![Classes maintenance](exports/classes/maintenance.png)

**Valuation** — valorisations, amortissements, réévaluations

![Classes valuation](exports/classes/valuation.png)

**Document** — documents, versions, règles d'accès

![Classes document](exports/classes/document.png)

**Notification** — notifications, audit_logs, modèles

![Classes notification](exports/classes/notification.png)

**Reporting** — agrégats et rapports générés

![Classes reporting](exports/classes/reporting.png)

### Modèles de données (MLD)

**auth_db** — 7 tables

![MLD auth_db](exports/donnees/auth_db.png)

**organisation_db** — 6 tables

![MLD organisation_db](exports/donnees/organisation_db.png)

**asset_db** — 8 tables

![MLD asset_db](exports/donnees/asset_db.png)

**movement_db** — 5 tables

![MLD movement_db](exports/donnees/movement_db.png)

**inventory_db** — 6 tables

![MLD inventory_db](exports/donnees/inventory_db.png)

**maintenance_db** — 5 tables

![MLD maintenance_db](exports/donnees/maintenance_db.png)

**valuation_db** — 4 tables

![MLD valuation_db](exports/donnees/valuation_db.png)

**document_db** — 3 tables

![MLD document_db](exports/donnees/document_db.png)

**notification_db** — 4 tables

![MLD notification_db](exports/donnees/notification_db.png)

**reporting_db** — 5 tables (lecture agrégée)

![MLD reporting_db](exports/donnees/reporting_db.png)
