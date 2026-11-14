<?php

namespace App\Http\Controllers;

use App\Services\ReportDataService;
use App\Support\ApiResponse;
use App\Support\PdfExporter;
use App\Support\ReportRenderer;
use App\Support\ValuationCalc;
use App\Support\XlsxExporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ReportController extends Controller
{
    use ApiResponse;

    private function get(string $service, string $path, array $params = [])
    {
        try {
            $r = Http::timeout(10)->withToken(env('SERVICE_TOKEN', ''))->get(env($service).$path, $params);
            return $r->successful() ? $r->json('data') : null;
        } catch (\Throwable) { return null; }
    }

    /** GET /api/v1/reports/dashboard - tableau de bord consolide */
    public function dashboard(Request $request)
    {
        $params = array_filter([
            'structure_id' => $request->query('structure_id'),
            'site_id' => $request->query('site_id'),
            'categorie_id' => $request->query('categorie_id'),
        ]);

        $assets = $this->get('ASSET_SERVICE_URL', '/api/v1/assets', $params + ['per_page' => 1]);
        $statsAsset = $this->get('ASSET_SERVICE_URL', '/api/v1/assets/stats', $params);
        $valStats = $this->get('VALUATION_SERVICE_URL', '/api/v1/valorisation/stats');
        $maintDue = $this->get('MAINTENANCE_SERVICE_URL', '/api/v1/maintenance/plans', ['a_echeance' => 30]);
        $mouvements = $this->get('MOVEMENT_SERVICE_URL', '/api/v1/movements', $params + ['per_page' => 5]);

        return $this->ok([
            'patrimoine' => [
                'nombre_biens' => $statsAsset['nombre_biens'] ?? $assets['total'] ?? 0,
                'valeur_totale' => $statsAsset['valeur_totale'] ?? 0,
            ],
            'valorisation' => $valStats ?? ['total_vnc' => 0, 'total_amortissements' => 0],
            'maintenance' => ['a_venir_30j' => is_array($maintDue) ? count($maintDue) : ($maintDue['total'] ?? 0)],
            'mouvements_recents' => is_array($mouvements) ? ($mouvements['data'] ?? $mouvements) : [],
            'structure_id' => $params['structure_id'] ?? null,
        ]);
    }

    /** POST /api/v1/reports/generate - genere un rapport (pdf/xlsx/csv) */
    public function generate(Request $request)
    {
        $d = $request->validate([
            'type' => 'required|in:inventaire_global,par_structure,valorisation,mouvements,maintenance',
            'format' => 'required|in:pdf,xlsx,csv',
            'structure_id' => 'nullable|uuid',
            'date_debut' => 'nullable|date',
            'date_fin' => 'nullable|date',
        ]);
        $claims = $request->attributes->get('jwt', []);

        // Recupere les donnees selon le type
        $data = match ($d['type']) {
            'valorisation' => $this->get('VALUATION_SERVICE_URL', '/api/v1/valorisation', ['per_page' => 5000]),
            'mouvements' => $this->get('MOVEMENT_SERVICE_URL', '/api/v1/movements', array_filter([
                'structure_source_id' => $d['structure_id'] ?? null, 'per_page' => 5000,
            ])),
            'maintenance' => $this->get('MAINTENANCE_SERVICE_URL', '/api/v1/maintenance/interventions', ['per_page' => 5000]),
            default => $this->get('ASSET_SERVICE_URL', '/api/v1/assets', array_filter([
                'structure_id' => $d['structure_id'] ?? null, 'per_page' => 5000,
            ])),
        };

        $id = (string) Str::uuid();
        DB::table('rapports_generees')->insert([
            'id' => $id, 'type' => $d['type'], 'format' => $d['format'],
            'parametres' => json_encode($d), 'genere_par' => $claims['sub'] ?? null,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Export CSV direct ; PDF/Excel necessitent un worker (hors-ligne documente)
        if ($d['format'] === 'csv') {
            return $this->ok(['id' => $id, 'telechargement' => "/api/v1/reports/$id/download"]);
        }
        return $this->ok(['id' => $id, 'statut' => 'en_cours', 'message' => 'Generation asynchrone via file d\'attente RabbitMQ']);
    }

    /**
     * GET /api/v1/reports/export/{type}?format=pdf|xlsx&region=&prefecture=&commune=
     *      &domaine=&secteur=&service=&etat=&du=&au=
     * Genere et telecharge un rapport PDF ou Excel (permission rapport.gerer).
     */
    public function export(Request $request, string $type)
    {
        if (! in_array($type, ReportDataService::TYPES, true)) {
            return $this->error('NOT_FOUND', 'Type de rapport inconnu.', 404);
        }
        $format = strtolower((string) $request->query('format', 'pdf'));
        if (! in_array($format, ['pdf', 'xlsx'], true)) {
            return $this->error('VALIDATION', 'Format attendu : pdf ou xlsx.', 422);
        }

        $data = app(ReportDataService::class);
        $f = $data->filters($request->query());
        $claims = $request->attributes->get('jwt', []);
        $user = $claims['nom'] ?? $claims['email'] ?? 'Inconnu';
        $quand = now()->format('d/m/Y à H:i');

        // ---- Collecte des donnees selon le type ----
        $titre = [
            'patrimoine' => 'État du patrimoine',
            'valeurs-vnc' => 'Valeurs et VNC',
            'maintenances' => 'Maintenances — historique et coûts',
            'inventaires' => 'Inventaires — écarts constatés',
        ][$type];

        $assets = null;
        $sectionHtml = '';
        $paysage = true;
        $kpis = [];
        $colonnes = [];
        $lignes = [];
        $formats = [];
        $nomFeuille = 'Rapport';
        $avecParRegion = false;

        if (in_array($type, ['patrimoine', 'valeurs-vnc'], true)) {
            $assets = $data->assets($f);
            if (! $assets) {
                return $this->error('EMPTY', 'Aucun bien ne correspond à ces filtres.', 422);
            }
            $s = $data->synthese($assets);
            $kpis = [
                'Nombre de biens' => $s['nombre_biens'],
                'Valeur totale' => $s['valeur_totale'],
                'Amortissements' => $s['amortissements'],
                'VNC totale' => $s['vnc_totale'],
            ];
            $sectionHtml = ReportRenderer::synthese($s)
                .'<h2>Biens</h2>'
                .ReportRenderer::tableauBiens($assets, $type === 'valeurs-vnc');
            $nomFeuille = $type === 'patrimoine' ? 'Biens' : 'Valeurs-VNC';
            $avecParRegion = true;
            $colonnes = [
                'code' => 'Code', 'designation' => 'Désignation', 'categorie' => 'Catégorie',
                'domaine_libelle' => 'Domaine', 'secteur' => 'Secteur', 'service' => 'Service',
                'etat' => 'État', 'date_acquisition' => 'Date acq.',
                'valeur' => 'Valeur', 'amortissement' => 'Amortissement', 'vnc' => 'VNC',
            ];
            $formats = ['valeur' => 'fcfa', 'amortissement' => 'fcfa', 'vnc' => 'fcfa', 'date_acquisition' => 'date', 'etat' => null];
            $lignes = array_map(function ($a) {
                $a['etat'] = ['bon' => 'Bon', 'moyen' => 'Moyen', 'degrade' => 'Dégradé', 'hors_service' => 'Hors service'][$a['etat'] ?? ''] ?? $a['etat'];
                return $a;
            }, $assets);
        } elseif ($type === 'maintenances') {
            $lignes = $data->interventions($f);
            if (! $lignes) {
                return $this->error('EMPTY', 'Aucune intervention ne correspond à ces filtres.', 422);
            }
            $kpis = [
                'Nombre d\'interventions' => count($lignes),
                'Coût total' => array_sum(array_map(fn ($l) => (float) $l['cout'], $lignes)),
            ];
            $sectionHtml = ReportRenderer::tableauMaintenances($lignes);
            $nomFeuille = 'Maintenances';
            $colonnes = [
                'code' => 'Code bien', 'bien' => 'Bien', 'type' => 'Type', 'statut' => 'Statut',
                'description' => 'Description', 'date_prevue' => 'Prévue le', 'date_realisee' => 'Réalisée le',
                'cout' => 'Coût',
            ];
            $formats = ['date_prevue' => 'date', 'date_realisee' => 'date', 'cout' => 'fcfa'];
            $lignes = array_map(fn ($l) => $l + ['type' => ['preventive' => 'Préventive', 'curative' => 'Corrective', 'panne' => 'Panne'][$l['type']] ?? $l['type'],
                'statut' => ['declaree' => 'Déclarée', 'planifiee' => 'Planifiée', 'en_cours' => 'En cours', 'terminee' => 'Terminée', 'annulee' => 'Annulée'][$l['statut']] ?? $l['statut']], $lignes);
            $paysage = false;
        } else { // inventaires
            $lignes = $data->inventaires($f);
            if (! $lignes) {
                return $this->error('EMPTY', 'Aucune campagne d\'inventaire ne correspond à ces filtres.', 422);
            }
            $kpis = [
                'Campagnes' => count($lignes),
                'Biens contrôlés' => array_sum(array_map(fn ($l) => (int) $l['nb_controles'], $lignes)),
                'Manquants' => array_sum(array_map(fn ($l) => (int) $l['nb_manquants'], $lignes)),
                'Écarts d\'état' => array_sum(array_map(fn ($l) => (int) $l['nb_ecarts'], $lignes)),
            ];
            $sectionHtml = ReportRenderer::tableauInventaires($lignes);
            $nomFeuille = 'Inventaires';
            $colonnes = [
                'code' => 'Campagne', 'libelle' => 'Libellé', 'structure' => 'Structure', 'site' => 'Site',
                'region' => 'Région', 'date_debut' => 'Début', 'date_fin' => 'Fin', 'statut' => 'Statut',
                'nb_controles' => 'Biens contrôlés', 'nb_manquants' => 'Manquants', 'nb_ecarts' => 'Écarts d\'état',
            ];
            $formats = ['date_debut' => 'date', 'date_fin' => 'date', 'nb_controles' => 'int', 'nb_manquants' => 'int', 'nb_ecarts' => 'int'];
            $lignes = array_map(fn ($l) => $l + ['statut' => ['planifiee' => 'Planifiée', 'en_cours' => 'En cours', 'cloturee' => 'Clôturée'][$l['statut']] ?? $l['statut']], $lignes);
            $paysage = true;
        }

        // Filtres affichés sur la page de garde
        $filtresLabels = $this->filtresLabels($f, $data);

        // ---- Journalisation ----
        $rapportId = (string) Str::uuid();
        DB::table('rapports_generees')->insert([
            'id' => $rapportId, 'type' => 'export_'.$type, 'format' => $format,
            'parametres' => json_encode(['filtres' => $f, 'user' => $user] + ['email' => $claims['email'] ?? null]),
            'genere_par' => $claims['sub'] ?? null,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $nomFichier = 'rapport-'.$type.'-'.now()->format('Y-m-d').'.'.$format;

        if ($format === 'pdf') {
            $html = ReportRenderer::render($titre, $sectionHtml, $filtresLabels, $user, $quand, $paysage);
            $pdf = PdfExporter::render($html, $titre, $paysage);
            return response($pdf, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$nomFichier.'"',
            ]);
        }

        // ---- Excel ----
        $xlsx = new XlsxExporter();
        $xlsx->build($titre, $type, ['quand' => $quand, 'user' => $user, 'kpis' => $kpis], $filtresLabels);
        $xlsx->addDataSheet($nomFeuille, $colonnes, $lignes, $formats);
        if ($avecParRegion && $assets) {
            $xlsx->addParRegion($assets);
        }
        return response($xlsx->output(), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$nomFichier.'"',
        ]);
    }

    /** Libelles humains des filtres pour la page de garde / feuille Synthèse. */
    private function filtresLabels(array $f, ReportDataService $data): array
    {
        $out = [];
        if ($f['region']) $out['Région'] = $f['region'];
        if ($f['prefecture']) $out['Préfecture'] = $f['prefecture'];
        if ($f['commune']) $out['Commune'] = $f['commune'];
        if ($f['domaine']) $out['Domaine'] = ReportDataService::DOMAINES[$f['domaine']] ?? $f['domaine'];
        if ($f['secteur']) $out['Secteur'] = $f['secteur'];
        if ($f['structure_id']) {
            $s = collect($data->structures())->get($f['structure_id']);
            $out['Service / ministère'] = $s->libelle ?? $f['structure_id'];
        }
        if ($f['etat']) {
            $out['État du bien'] = ['en_service' => 'En service', 'en_maintenance' => 'En maintenance', 'reforme' => 'Réformé'][$f['etat']] ?? $f['etat'];
        }
        if ($f['du']) $out['Du'] = \Carbon\Carbon::parse($f['du'])->format('d/m/Y');
        if ($f['au']) $out['Au'] = \Carbon\Carbon::parse($f['au'])->format('d/m/Y');
        return $out;
    }

    public function download(string $id)
    {
        $r = DB::table('rapports_generees')->find($id);
        if (! $r) return $this->error('NOT_FOUND', 'Rapport introuvable.', 404);
        $data = $this->getDataForReport($r);
        $csv = $this->toCsv($data);
        return response($csv, 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => "attachment; filename=rapport-{$r->type}.csv"]);
    }

    public function index(Request $request)
    {
        return $this->ok(DB::table('rapports_generees')->orderByDesc('genere_le')->paginate(30));
    }

    private function getDataForReport(object $r): array
    {
        $p = json_decode($r->parametres ?? '{}', true) ?: [];
        return match ($r->type) {
            'valorisation' => $this->get('VALUATION_SERVICE_URL', '/api/v1/valorisation', ['per_page' => 5000]) ?? [],
            'mouvements' => $this->get('MOVEMENT_SERVICE_URL', '/api/v1/movements', ['per_page' => 5000]) ?? [],
            'maintenance' => $this->get('MAINTENANCE_SERVICE_URL', '/api/v1/maintenance/interventions', ['per_page' => 5000]) ?? [],
            default => $this->get('ASSET_SERVICE_URL', '/api/v1/assets', ['per_page' => 5000]) ?? [],
        };
    }

    private function toCsv($data): string
    {
        $rows = is_array($data) ? ($data['data'] ?? $data) : [];
        if (! $rows) return "Aucune donnee\n";
        $out = fopen('php://temp', 'r+');
        fputcsv($out, array_keys((array) (is_object($rows[0]) ? get_object_vars($rows[0]) : $rows[0])));
        foreach ($rows as $row) {
            $row = is_object($row) ? get_object_vars($row) : (array) $row;
            fputcsv($out, array_map(fn ($v) => is_scalar($v) ? $v : json_encode($v), $row));
        }
        rewind($out);
        return stream_get_contents($out);
    }
}
