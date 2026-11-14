# Module Rapports — Patrimoine de l'État

Génération de rapports PDF et Excel (.xlsx) dans `services/reporting-service`.

## Endpoints

Préfixe API : `/api/v1`

| Endpoint | Description |
|---|---|
| `GET /reports/types` | Liste des types de rapports + formats disponibles |
| `GET /reports/export/{type}?format=pdf\|xlsx\|csv` | Génère et télécharge le rapport en flux |
| `POST /reports/generate` | Génère un rapport (historique dans `rapports_generees`) |
| `GET /reports` | Historique des rapports générés |
| `GET /reports/{id}/download` | Télécharge un rapport existant |

### Types de rapports

- `patrimoine` — État du patrimoine (biens par domaine, secteur, service, regroupés par région/préfecture/commune)
- `valeurs-vnc` — Valeurs et VNC (acquisition, amortissement cumulé, VNC)
- `maintenances` — Historique et coûts des interventions
- `inventaires` — Écarts constatés par campagne

### Paramètres

```
format=pdf|xlsx|csv
region_id, prefecture_id, commune_id, domaine, secteur,
service_id, etat, du (YYYY-MM-DD), au (YYYY-MM-DD)
```

Filtre vide = tout. Réponse en flux avec `Content-Disposition: attachment;
filename="rapport-{type}-{AAAA-MM-JJ}.{ext}"`.

## Droits

- `rapport.gerer` : génération et téléchargement (`admin`, `gestionnaire`)
- `rapport.lire` : lecture de l'historique uniquement
- Un résultat vide renvoie `422 EMPTY` (« Aucun bien ne correspond à ces filtres »)

Chaque génération est journalisée dans `rapports_generees` (utilisateur, type,
format, filtres, date).

## Calculs

`app/Support/ValuationCalc.php` (pur, testable) :

- Amortissement linéaire : `valeur × (mois écoulés / durée de vie en mois)`
- VNC = acquisition − amortissement, **plancher à 0**
- Durée de vie absente → amortissement et VNC affichés `n.p.`
- Montants : `1 234 567 FCFA` (espace insécable comme séparateur de milliers)

## Exportateurs

- **PDF** : `app/Support/PdfExporter.php` — Dompdf (`barryvdh/laravel-dompdf`).
  Page de garde (armoiries, République, titre, filtres, utilisateur), en-tête et
  pied « Page X / N » sur les pages de contenu via `page_script`.
- **Excel** : `app/Support/XlsxExporter.php` — PhpSpreadsheet
  (`phpoffice/phpspreadsheet`). Feuilles : Synthèse, données, Par région.
  En-tête vert `#006a4e` / texte blanc, ligne figée, autofilter, nombres au
  format `#,##0 "FCFA"`, dates `JJ/MM/AAAA`, totaux avec formules `SUM`/`SUBTOTAL`.

## Personnalisation de l'en-tête PDF

- Armoiries : `resources/views/reports/header.blade.php` → remplacez
  `assets/armoiries.png` par votre fichier dans `resources/views/reports/assets/`.
- Couleur principale : constante `$vert = '#006a4e'` dans `PdfExporter::export()`.
- Typographie : famille définie dans `ReportView::document()` (`app/Support/ReportView.php`).

## Exemples générés

Données de démonstration seedées (`tools/seed_demo.sql` — 9 biens, 4 structures,
5 catégories) :

- `docs/exemples/rapport-patrimoine.pdf`
- `docs/exemples/rapport-valeurs-vnc.xlsx`
- `docs/exemples/rapport-maintenances.pdf`

## Normes comptables publiques appliquées

Alignement sur le cadre applicable aux États membres UEMOA
(directive 2012-03 « comptabilité des matières », SYSCOHADA/OHADA,
référentiels IPSAS) :

- **Registre des immobilisations** : chaque bien porte un `code_inventaire`
  unique (non réutilisable, RG-01), un `compte_comptable` OHADA (classe 2 :
  21 incorporelles, 22-24 corporelles, 25-27 financières), une `nature`
  (incorporelle / corporelle / financière) et un `immobilisation` booléen
  (les biens sous le seuil d'importance significative sont des charges).
- **Coût historique** : valorisation au coût d'acquisition (art. 36 OHADA),
  jamais réévaluée unilatéralement.
- **Dotations aux amortissements** : méthode linéaire obligatoire, à compter
  de la `date_mise_en_service` (à défaut date d'acquisition) ; plancher VNC 0.
- **Traçabilité des acquisitions** : `reference_decision` (n° d'arrêté,
  marché ou bon de commande).
- **Sorties définitives** : les motifs UEMOA (consommation, transfert,
  réforme, destruction, perte, vol, manquant d'inventaire) sont gérés par
  movement-service ; un bien sorti reste archivé pour audit (RG-04).
- **Inventaire permanent** : campagnes + constats par bien (inventaire
  tournant), écarts d'état et de quantité.

Référentiels : `tools/seed_normes.sql` (comptes OHADA par catégorie,
motifs de sortie, champs normes sur les biens de démonstration).

## Tests

`tests/Unit/ValuationCalcTest.php` couvre les calculs de VNC, amortissement,
amortissement plafonné et totaux. Exécution :

```bash
docker exec patrimoine-reporting vendor/bin/phpunit tests/Unit
```
