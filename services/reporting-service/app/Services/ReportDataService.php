<?php

namespace App\Services;

use App\Support\ValuationCalc;
use Illuminate\Support\Facades\Http;

/**
 * Collecte et normalise les donnees des rapports a partir des micro-services.
 * Toute la logique de filtrage et de calcul (VNC, totaux) est ici, cote serveur.
 */
class ReportDataService
{
    public const TYPES = ['patrimoine', 'valeurs-vnc', 'maintenances', 'inventaires', 'etat-immobilisations'];

    public const DOMAINES = [
        'domaine_public_immobilier' => 'Domaine public immobilier',
        'domaine_prive_immobilier' => 'Domaine privé immobilier',
        'education' => 'Éducation',
        'sante' => 'Santé',
        'infrastructures' => 'Infrastructures',
        'secteurs_productifs' => 'Secteurs productifs',
        'biens_meubles' => 'Biens meubles',
        'patrimoine_culturel' => 'Patrimoine culturel',
        'participations' => 'Participations',
    ];

    // Correspondance filtre "etat" (spec) -> statut(s) interne(s)
    private const ETAT_MAP = [
        'en_service' => ['affecte', 'disponible', 'enregistre'],
        'en_maintenance' => ['en_maintenance'],
        'reforme' => ['sorti', 'propose_sortie'],
    ];

    /** Filtres normalises depuis la requete. */
    public function filters(array $q): array
    {
        return [
            'region' => $this->str($q['region'] ?? null),
            'prefecture' => $this->str($q['prefecture'] ?? null),
            'commune' => $this->str($q['commune'] ?? null),
            'domaine' => $this->str($q['domaine'] ?? null),
            'secteur' => $this->str($q['secteur'] ?? null),
            'structure_id' => $this->str($q['service'] ?? $q['structure_id'] ?? null),
            'etat' => $this->str($q['etat'] ?? null),
            'du' => $this->str($q['du'] ?? null),
            'au' => $this->str($q['au'] ?? null),
        ];
    }

    private function str($v): ?string
    {
        $v = is_string($v) ? trim($v) : null;
        return ($v === '' || $v === null) ? null : $v;
    }

    // ------------------------------------------------------------------
    // Acces services (token statique inter-services)
    // ------------------------------------------------------------------

    private function get(string $service, string $path, array $params = [])
    {
        try {
            $r = Http::timeout(15)->withToken(env('SERVICE_TOKEN', ''))->get(env($service).$path, $params);
            return $r->successful() ? $r->json('data') : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** Recupere toutes les pages d'un endpoint pagine (plafond de securite). */
    private function fetchAll(string $service, string $path, array $params = [], int $maxPages = 200): array
    {
        $out = [];
        for ($page = 1; $page <= $maxPages; $page++) {
            $data = $this->get($service, $path, $params + ['page' => $page, 'per_page' => 100]);
            if (! is_array($data)) break;
            // Format pagine : { current_page, data: [...], last_page } ou tableau direct
            $rows = $data['data'] ?? $data;
            if (! is_array($rows) || ! count($rows)) break;
            foreach ($rows as $r) $out[] = (object) $r;
            $last = $data['last_page'] ?? 1;
            if ($page >= (int) $last) break;
        }
        return $out;
    }

    // ------------------------------------------------------------------
    // Referentiels (sites geo, categories, structures)
    // ------------------------------------------------------------------

    public function sites(): array
    {
        return collect($this->fetchAll('ORGANISATION_SERVICE_URL', '/api/v1/sites'))
            ->keyBy('id')->all();
    }

    public function categories(): array
    {
        return collect($this->fetchAll('ORGANISATION_SERVICE_URL', '/api/v1/categories'))
            ->keyBy('id')->all();
    }

    public function structures(): array
    {
        return collect($this->fetchAll('ORGANISATION_SERVICE_URL', '/api/v1/structures'))
            ->keyBy('id')->all();
    }

    // ------------------------------------------------------------------
    // Biens : collecte complete + enrichissement + filtrage
    // ------------------------------------------------------------------

    /**
     * Retourne les biens filtres, enrichis (geo, categorie, service, VNC).
     */
    public function assets(array $f): array
    {
        $sites = $this->sites();
        $categories = $this->categories();
        $structures = $this->structures();
        $asOf = $f['au'] ?: null;

        $rows = [];
        foreach ($this->fetchAll('ASSET_SERVICE_URL', '/api/v1/assets') as $b) {
            $b = (object) $b;
            $site = $sites[$b->site_id ?? ''] ?? null;
            $cat = $categories[$b->categorie_id ?? ''] ?? null;
            $struct = $structures[$b->structure_id ?? ''] ?? null;

            // OHADA : les dotations courent a partir de la mise en service
            // (a defaut, date d'acquisition).
            $val = ValuationCalc::compute(
                isset($b->valeur_acquisition) ? (float) $b->valeur_acquisition : null,
                $cat->duree_vie_mois ?? null,
                $b->date_mise_en_service ?? $b->date_acquisition ?? null,
                $asOf
            );

            $rows[] = [
                'id' => $b->id ?? null,
                'code' => $b->code_inventaire ?? 'n.p.',
                'designation' => $b->designation ?? 'n.p.',
                'categorie' => $cat->libelle ?? 'n.p.',
                'compte' => $b->compte_comptable ?? $cat->compte_comptable ?? null,
                'nature' => $b->nature ?? $cat->nature ?? 'corporelle',
                'immobilisation' => (bool) ($b->immobilisation ?? true),
                'reference_decision' => $b->reference_decision ?? null,
                'type' => $b->type_specifique ?? null,
                'domaine' => $b->domaine ?? null,
                'domaine_libelle' => self::DOMAINES[$b->domaine ?? ''] ?? ($b->domaine ?: 'n.p.'),
                'secteur' => $b->secteur ?? null,
                'structure_id' => $b->structure_id ?? null,
                'service' => $struct->libelle ?? 'n.p.',
                'region' => $site->region ?? null,
                'prefecture' => $site->prefecture ?? null,
                'commune' => $site->commune ?? null,
                'site' => $site->libelle ?? null,
                'etat' => $b->etat ?? null,
                'statut' => $b->statut ?? null,
                'date_acquisition' => $b->date_acquisition ?? null,
                'date_mise_en_service' => $b->date_mise_en_service ?? null,
                'duree_vie_mois' => $cat->duree_vie_mois ?? null,
                'valeur' => $val['acquisition'],
                'amortissement' => $val['amortissement'],
                'vnc' => $val['vnc'],
            ];
        }

        return array_values(array_filter($rows, fn ($r) => $this->matchAsset($r, $f)));
    }

    private function matchAsset(array $r, array $f): bool
    {
        if ($f['region'] && strcasecmp((string) $r['region'], $f['region']) !== 0) return false;
        if ($f['prefecture'] && strcasecmp((string) $r['prefecture'], $f['prefecture']) !== 0) return false;
        if ($f['commune'] && strcasecmp((string) $r['commune'], $f['commune']) !== 0) return false;
        if ($f['domaine'] && ($r['domaine'] ?? null) !== $f['domaine']) return false;
        if ($f['secteur'] && strcasecmp((string) $r['secteur'], $f['secteur']) !== 0) return false;
        if ($f['structure_id'] && ($r['structure_id'] ?? null) !== $f['structure_id']) return false;
        if ($f['etat']) {
            $statuts = self::ETAT_MAP[$f['etat']] ?? [$f['etat']];
            if (! in_array($r['statut'], $statuts, true)) return false;
        }
        $d = substr((string) $r['date_acquisition'], 0, 10);
        if ($f['du'] && $d && $d < $f['du']) return false;
        if ($f['au'] && $d && $d > $f['au']) return false;
        return true;
    }

    // ------------------------------------------------------------------
    // Maintenances : interventions enrichies des donnees du bien
    // ------------------------------------------------------------------

    public function interventions(array $f, ?array $assets = null): array
    {
        $assets = $assets ?? $this->assets($f);
        $parId = collect($assets)->keyBy('id');

        $rows = [];
        foreach ($this->fetchAll('MAINTENANCE_SERVICE_URL', '/api/v1/maintenance/interventions') as $i) {
            $i = (object) $i;
            // Seules les interventions des biens du perimetre filtre sont retenues
            $bien = $parId[$i->bien_id ?? ''] ?? null;
            if (! $bien) continue;

            $date = $i->date_prevue ?? $i->date_realisee ?? null;
            if ($f['du'] && $date && substr((string) $date, 0, 10) < $f['du']) continue;
            if ($f['au'] && $date && substr((string) $date, 0, 10) > $f['au']) continue;

            $rows[] = [
                'reference' => $i->id ?? 'n.p.',
                'bien' => $bien['designation'],
                'code' => $bien['code'],
                'type' => $i->type ?? 'n.p.',
                'statut' => $i->statut ?? 'n.p.',
                'description' => $i->description ?? null,
                'date_prevue' => $i->date_prevue ?? null,
                'date_realisee' => $i->date_realisee ?? null,
                'cout' => (float) (($i->cout_main_oeuvre ?? 0) + ($i->cout_pieces ?? 0)),
            ];
        }
        return $rows;
    }

    // ------------------------------------------------------------------
    // Inventaires : campagnes + constats (ecarts)
    // ------------------------------------------------------------------

    public function inventaires(array $f): array
    {
        $structures = $this->structures();
        $sites = $this->sites();

        $rows = [];
        foreach ($this->fetchAll('INVENTORY_SERVICE_URL', '/api/v1/inventories') as $c) {
            $c = (object) $c;
            $debut = substr((string) ($c->date_debut ?? ''), 0, 10);
            $fin = substr((string) ($c->date_fin ?? ''), 0, 10);
            if ($f['du'] && $fin && $fin < $f['du']) continue;
            if ($f['au'] && $debut && $debut > $f['au']) continue;
            if ($f['structure_id'] && ($c->structure_id ?? null) !== $f['structure_id']) continue;

            $site = $sites[$c->site_id ?? ''] ?? null;
            if ($f['region'] && strcasecmp((string) ($site->region ?? ''), $f['region']) !== 0) continue;

            // Constats de la campagne
            $constats = $this->fetchAll('INVENTORY_SERVICE_URL', "/api/v1/inventories/{$c->id}/constats");
            $nbControles = count($constats);
            $nbManquants = count(array_filter($constats, fn ($x) => ! ($x->present ?? false)));
            $nbEcarts = count(array_filter($constats, fn ($x) => ($x->present ?? false) && ! empty($x->etat_constate)));

            $rows[] = [
                'code' => $c->code ?? 'n.p.',
                'libelle' => $c->libelle ?? 'n.p.',
                'structure' => $structures[$c->structure_id ?? '']->libelle ?? 'n.p.',
                'site' => $site->libelle ?? null,
                'region' => $site->region ?? null,
                'date_debut' => $debut ?: null,
                'date_fin' => $fin ?: null,
                'statut' => $c->statut ?? 'n.p.',
                'nb_controles' => $nbControles,
                'nb_manquants' => $nbManquants,
                'nb_ecarts' => $nbEcarts,
            ];
        }
        return $rows;
    }

    // ------------------------------------------------------------------
    // Etat des immobilisations (IPSAS / etat financier public)
    // Ouverture -> acquisitions, dotations, sorties -> cloture.
    // ------------------------------------------------------------------

    public function etatImmobilisations(array $f): array
    {
        $debut = $f['du'] ?: date('Y-01-01');
        $fin = $f['au'] ?: date('Y-m-d');
        $avantDebut = date('Y-m-d', strtotime($debut.' -1 day'));

        // Univers des biens : filtres geo/domaine/service appliques,
        // sans filtre de dates (gere ici pour l'etat).
        $fUniverse = $f;
        $fUniverse['du'] = null;
        $fUniverse['au'] = null;
        $assets = $this->assets($fUniverse);
        $parId = collect($assets)->keyBy(fn ($r) => (string) $r['id']);

        // Toutes les sorties validees (permet de savoir si un bien est sorti
        // avant la periode, pendant, ou apres).
        $mvs = $this->fetchAll('MOVEMENT_SERVICE_URL', '/api/v1/movements', ['type' => 'sortie']);
        $sortieLe = [];   // bien_id => date de sortie la plus recente
        foreach ($mvs as $m) {
            if (! in_array($m->statut ?? '', ['validee', 'executee'], true)) continue;
            $d = substr((string) ($m->date_effet ?? $m->date_validation ?? $m->created_at ?? ''), 0, 10);
            if (! $d || ($sortieLe[$m->bien_id] ?? '') >= $d) continue;
            $sortieLe[$m->bien_id] = $d;
        }
        $sortiesPeriode = array_filter($mvs, function ($m) use ($debut, $fin, $sortieLe) {
            if (! in_array($m->statut ?? '', ['validee', 'executee'], true)) return false;
            $d = $sortieLe[$m->bien_id] ?? null;
            return $d && $d >= $debut && $d <= $fin;
        });

        $motifs = collect($this->fetchAll('MOVEMENT_SERVICE_URL', '/api/v1/movements/motifs-sortie'))->keyBy('id');

        $vncAt = fn (array $r, string $asOf): array => ValuationCalc::compute(
            $r['valeur'] !== null ? (float) $r['valeur'] : null,
            $r['duree_vie_mois'] ?? null,
            $r['date_mise_en_service'] ?? $r['date_acquisition'] ?? null,
            $asOf
        );

        // ---- Ouverture / cloture ----
        $ouv = ['brut' => 0.0, 'amort' => 0.0, 'vnc' => 0.0];
        $clot = ['brut' => 0.0, 'amort' => 0.0, 'vnc' => 0.0];
        $acquisitions = [];
        $dotations = [];
        $nbCloture = 0;

        foreach ($assets as $r) {
            $acq = (string) substr((string) $r['date_acquisition'], 0, 10);
            $sortie = $sortieLe[$r['id']] ?? null;
            if (! $sortie || $sortie > $fin) {
                // Bien present a la cloture
                if ($acq <= $fin) {
                    $v = $vncAt($r, $fin);
                    $clot['brut'] += (float) $r['valeur'];
                    $clot['amort'] += (float) $r['valeur'] - (float) ($v['vnc'] ?? 0);
                    $clot['vnc'] += (float) ($v['vnc'] ?? 0);
                    $nbCloture++;
                }
            }
            if ($acq < $debut && (! $sortie || $sortie > $avantDebut)) {
                $v = $vncAt($r, $avantDebut);
                $ouv['brut'] += (float) $r['valeur'];
                $ouv['amort'] += (float) $r['valeur'] - (float) ($v['vnc'] ?? 0);
                $ouv['vnc'] += (float) ($v['vnc'] ?? 0);
            }
            if ($acq >= $debut && $acq <= $fin) {
                $acquisitions[] = [
                    'date' => $r['date_acquisition'], 'code' => $r['code'], 'designation' => $r['designation'],
                    'compte' => $r['compte'], 'valeur' => $r['valeur'],
                ];
            }

            // ---- Dotations de la periode (mensualites OHADA) ----
            if (! ($r['immobilisation'] ?? true) || ! $r['duree_vie_mois'] || $r['valeur'] === null) continue;
            $debutService = $r['date_mise_en_service'] ?? $r['date_acquisition'];
            if (! $debutService) continue;
            $duree = (int) $r['duree_vie_mois'];
            $mensuel = (float) $r['valeur'] / $duree;
            $finService = $sortie && $sortie <= $fin ? $sortie : $fin;
            if ($finService < $debut) continue;

            $amortAvant = (float) ($vncAt($r, $avantDebut)['amortissement'] ?? 0);
            $reste = max(0.0, (float) $r['valeur'] - $amortAvant);
            $total = 0.0;
            $nbMois = 0;
            $debutMois = max($debut, substr($debutService, 0, 7).'-01');
            $cur = new \DateTimeImmutable($debutMois);
            $finM = new \DateTimeImmutable(substr($finService, 0, 7).'-01');
            while ($cur <= $finM && $total < $reste) {
                $total += min($mensuel, $reste - $total);
                $nbMois++;
                $cur = $cur->modify('+1 month');
            }
            if ($total > 0.005) {
                $dotations[] = [
                    'code' => $r['code'], 'designation' => $r['designation'], 'compte' => $r['compte'],
                    'nb_mois' => $nbMois,
                    'dotation' => round($total, 2),
                ];
            }
        }

        // ---- Sorties de la periode ----
        $lignesSorties = [];
        foreach ($sortiesPeriode as $m) {
            $r = $parId->get((string) $m->bien_id);
            if (! $r) continue;
            $dSortie = $sortieLe[$m->bien_id];
            $v = $vncAt($r, $dSortie);
            $lignesSorties[] = [
                'date' => $dSortie,
                'reference' => $m->reference ?? 'n.p.',
                'code' => $r['code'], 'designation' => $r['designation'], 'compte' => $r['compte'],
                'motif' => $motifs->get($m->motif_sortie_id)->libelle ?? ($m->motif ?? 'n.p.'),
                'vnc_sortie' => $v['vnc'],
                'valeur_sortie' => $m->valeur_sortie ?? null,
            ];
        }

        return [
            'debut' => $debut, 'fin' => $fin,
            'ouverture' => $ouv,
            'acquisitions' => ['total' => array_sum(array_column($acquisitions, 'valeur')), 'lignes' => $acquisitions],
            'dotations' => ['total' => array_sum(array_column($dotations, 'dotation')), 'lignes' => $dotations],
            'sorties' => [
                'vnc' => array_sum(array_map(fn ($l) => (float) $l['vnc_sortie'], $lignesSorties)),
                'cession' => array_sum(array_map(fn ($l) => (float) ($l['valeur_sortie'] ?? 0), $lignesSorties)),
                'lignes' => $lignesSorties,
            ],
            'cloture' => $clot,
            'nb_biens' => $nbCloture,
        ];
    }

    // ------------------------------------------------------------------
    // Synthese commune aux rapports de biens
    // ------------------------------------------------------------------

    public function synthese(array $assets): array
    {
        $t = ValuationCalc::totals($assets);
        return [
            'nombre_biens' => $t['nb'],
            'valeur_totale' => $t['valeur'],
            'amortissements' => array_sum(array_map(fn ($l) => (float) ($l['amortissement'] ?? 0), $assets)),
            'vnc_totale' => $t['vnc'],
        ];
    }
}
