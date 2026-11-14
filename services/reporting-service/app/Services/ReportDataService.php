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
    public const TYPES = ['patrimoine', 'valeurs-vnc', 'maintenances', 'inventaires'];

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
