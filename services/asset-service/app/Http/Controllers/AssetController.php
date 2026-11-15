<?php

namespace App\Http\Controllers;

use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;

class AssetController extends Controller
{
    use ApiResponse;

    private const TYPES = ['immobilier', 'vehicule', 'informatique', 'mobilier', 'autre'];

    /** GET /api/v1/assets - recherche multicritere + pagination */
    public function index(Request $request)
    {
        $q = DB::table('biens')->whereNull('deleted_at');

        // Perimetre RG-06 : un agent rattache a une structure ne voit que ses
        // biens ; les agents centraux (sans structure_id) et l'admin voient tout.
        $claims = $request->attributes->get('jwt', []);
        $perms = $claims['permissions'] ?? [];
        $admin = in_array('*', $perms, true);
        if (! $admin && ! empty($claims['structure_id'])) {
            $q->where('structure_id', $claims['structure_id']);
        }

        foreach (['statut', 'etat', 'type_specifique', 'structure_id', 'site_id', 'categorie_id', 'domaine', 'secteur'] as $f) {
            if ($v = $request->query($f)) $q->where($f, $v);
        }
        if ($s = $request->query('search')) {
            $q->where(fn ($x) => $x->where('designation', 'ilike', "%$s%")
                ->orWhere('code_inventaire', 'ilike', "%$s%"));
        }
        if ($d = $request->query('date_from')) $q->where('date_acquisition', '>=', $d);
        if ($d = $request->query('date_to')) $q->where('date_acquisition', '<=', $d);

        $sort = $request->query('sort', 'created_at');
        $dir = strtolower($request->query('dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowed = ['created_at', 'code_inventaire', 'designation', 'valeur_acquisition', 'date_acquisition'];
        $q->orderBy(in_array($sort, $allowed) ? $sort : 'created_at', $dir);

        return $this->ok($q->paginate(max(1, min((int) $request->query('per_page', 20), 100))));
    }

    /** GET /api/v1/assets/{id} - fiche complete */
    public function show(Request $request, string $id)
    {
        $bien = DB::table('biens')->whereNull('deleted_at')->find($id);
        if (! $bien) return $this->error('NOT_FOUND', 'Bien introuvable.', 404);
        if ($resp = $this->horsPerimetre($request, $bien)) return $resp;

        $bien->specifique = match ($bien->type_specifique) {
            'immobilier' => DB::table('specifiques_immobiliers')->where('bien_id', $id)->first(),
            'vehicule' => DB::table('specifiques_vehicules')->where('bien_id', $id)->first(),
            'informatique' => DB::table('specifiques_informatique')->where('bien_id', $id)->first(),
            default => null,
        };
        $bien->photos = DB::table('photos_biens')->where('bien_id', $id)->get();
        return $this->ok($bien);
    }

    /** POST /api/v1/assets - creation fiche bien (RG-01 code non reutilisable) */
    public function store(Request $request)
    {
        $claims = $request->attributes->get('jwt', []);
        $d = $request->validate([
            'designation' => 'required|string|max:255',
            'description' => 'nullable|string',
            'categorie_id' => 'required|uuid',
            'type_specifique' => 'required|in:'.implode(',', self::TYPES),
            'date_acquisition' => 'required|date',
            'date_mise_en_service' => 'nullable|date',
            'mode_acquisition' => 'required|in:achat,don,transfert,construction,recuperation',
            'reference_decision' => 'nullable|string|max:100',
            'valeur_acquisition' => 'required|numeric|min:0',
            'immobilisation' => 'nullable|boolean',
            'nature' => 'nullable|in:incorporelle,corporelle,financiere',
            'compte_comptable' => 'nullable|string|max:10',
            'devise' => 'nullable|string|size:3',
            'structure_id' => 'nullable|uuid',
            'site_id' => 'nullable|uuid',
            'localisation_id' => 'nullable|uuid',
            'caracteristiques' => 'nullable|array',
            'immobilier' => 'nullable|array',
            'vehicule' => 'nullable|array',
            'informatique' => 'nullable|array',
        ]);

        // Perimetre RG-06 : un agent rattache a une structure est force a
        // rattacher le bien a SA structure (le client ne peut pas choisir).
        $structureId = $claims['structure_id'] ?? null;
        if (! $structureId && in_array('*', $claims['permissions'] ?? [], true)) {
            $structureId = $d['structure_id'] ?? null;
        }
        if (! $structureId) {
            return $this->error('VALIDATION', 'Structure responsable requise.', 422);
        }

        $uuid = (string) Str::uuid();
        $id = null;

        // Retry en cas de collision concurrente sur code_inventaire (unique)
        for ($tentative = 0; $tentative < 3; $tentative++) {
            $code = $this->generateCode();
            $id = (string) Str::uuid();
            try {
                DB::transaction(function () use ($d, $id, $uuid, $code, $structureId, $claims) {
            DB::table('biens')->insert([
                'id' => $id, 'code_inventaire' => $code, 'uuid' => $uuid,
                'designation' => $d['designation'], 'description' => $d['description'] ?? null,
                'categorie_id' => $d['categorie_id'], 'type_specifique' => $d['type_specifique'],
                'caracteristiques' => isset($d['caracteristiques']) ? json_encode($d['caracteristiques']) : null,
                'date_acquisition' => $d['date_acquisition'], 'mode_acquisition' => $d['mode_acquisition'],
                'date_mise_en_service' => $d['date_mise_en_service'] ?? null,
                'reference_decision' => $d['reference_decision'] ?? null,
                'valeur_acquisition' => $d['valeur_acquisition'], 'devise' => $d['devise'] ?? 'XOF',
                'immobilisation' => $d['immobilisation'] ?? true,
                'nature' => $d['nature'] ?? 'corporelle',
                'compte_comptable' => $d['compte_comptable'] ?? null,
                'structure_id' => $structureId, 'site_id' => $d['site_id'] ?? null,
                'localisation_id' => $d['localisation_id'] ?? null,
                'statut' => 'enregistre', 'etat' => 'bon',
                'cree_par' => $claims['sub'] ?? null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            match ($d['type_specifique']) {
                'immobilier' => DB::table('specifiques_immobiliers')->insert(['bien_id' => $id] + ($d['immobilier'] ?? [])),
                'vehicule' => DB::table('specifiques_vehicules')->insert(['bien_id' => $id] + ($d['vehicule'] ?? [])),
                'informatique' => DB::table('specifiques_informatique')->insert(['bien_id' => $id] + ($d['informatique'] ?? [])),
                default => null,
            };
                });
                break; // transaction reussie
            } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                continue; // collision sur le code : on regenere
            }
        }
        if (! $id) {
            return $this->error('CONFLICT', 'Impossible de generer un code unique. Reessayez.', 409);
        }

        // Evenement asynchrone
        $this->publish('bien.enregistre', ['bien_id' => $id, 'code' => $code]);

        $bien = DB::table('biens')->find($id);
        $bien->qr_code_url = "/api/v1/assets/$id/qrcode.svg";
        return $this->created($bien);
    }

    /** PUT /api/v1/assets/{id} */
    public function update(Request $request, string $id)
    {
        $bien = DB::table('biens')->whereNull('deleted_at')->find($id);
        if (! $bien) return $this->error('NOT_FOUND', 'Bien introuvable.', 404);
        if ($resp = $this->horsPerimetre($request, $bien)) return $resp;
        if ($bien->statut === 'sorti') {
            return $this->error('BIEN_SORTI', 'Un bien sorti n\'est plus modifiable (RG-04).', 409);
        }
        $d = $request->validate([
            'designation' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'etat' => 'sometimes|in:bon,degrade,hors_service',
            'valeur_acquisition' => 'sometimes|numeric|min:0',
            'date_mise_en_service' => 'sometimes|nullable|date',
            'reference_decision' => 'nullable|string|max:100',
            'immobilisation' => 'sometimes|boolean',
            'nature' => 'sometimes|in:incorporelle,corporelle,financiere',
            'compte_comptable' => 'nullable|string|max:10',
            'caracteristiques' => 'nullable|array',
        ]);
        if (isset($d['caracteristiques'])) $d['caracteristiques'] = json_encode($d['caracteristiques']);
        DB::table('biens')->where('id', $id)->update($d + ['updated_at' => now()]);
        $this->audit($request, 'bien.modifier', $id, (array) $bien, $d);
        return $this->ok(DB::table('biens')->find($id));
    }

    /** DELETE /api/v1/assets/{id} (soft delete, sauf si sorti -> archive conserve) */
    public function destroy(Request $request, string $id)
    {
        $bien = DB::table('biens')->whereNull('deleted_at')->find($id);
        if (! $bien) return $this->error('NOT_FOUND', 'Bien introuvable.', 404);
        if ($resp = $this->horsPerimetre($request, $bien)) return $resp;
        if ($bien->statut === 'sorti') {
            return $this->error('BIEN_SORTI', 'Suppression interdite : bien sorti conserve pour audit.', 409);
        }
        DB::table('biens')->where('id', $id)->update(['deleted_at' => now()]);
        return $this->ok(['message' => 'Bien supprime.']);
    }

    /** PATCH /api/v1/assets/{id}/affectation - INTERNE (movement-service via SERVICE_TOKEN) */
    public function affecter(Request $request, string $id)
    {
        if (! $this->isServiceCall($request)) {
            return $this->error('FORBIDDEN', 'Endpoint reserve aux services internes.', 403);
        }
        $bien = DB::table('biens')->whereNull('deleted_at')->find($id);
        if (! $bien) return $this->error('NOT_FOUND', 'Bien introuvable.', 404);
        $d = $request->validate([
            'structure_id' => 'required_unless:statut,sorti|nullable|uuid',
            'site_id' => 'nullable|uuid',
            'localisation_id' => 'nullable|uuid',
            'statut' => 'required|in:disponible,affecte,en_maintenance,sorti',
        ]);
        // Une sortie conserve la structure d'origine pour l'audit : on ne
        // reecrit les rattachements que s'ils sont fournis.
        $maj = ['statut' => $d['statut'], 'updated_at' => now()];
        foreach (['structure_id', 'site_id', 'localisation_id'] as $f) {
            if (array_key_exists($f, $d) && $d[$f] !== null) $maj[$f] = $d[$f];
        }
        DB::table('biens')->where('id', $id)->update($maj);
        return $this->ok(DB::table('biens')->find($id));
    }

    /** PATCH /api/v1/assets/{id}/etat - INTERNE (services via SERVICE_TOKEN) */
    public function etat(Request $request, string $id)
    {
        if (! $this->isServiceCall($request)) {
            return $this->error('FORBIDDEN', 'Endpoint reserve aux services internes.', 403);
        }
        $bien = DB::table('biens')->whereNull('deleted_at')->find($id);
        if (! $bien) return $this->error('NOT_FOUND', 'Bien introuvable.', 404);
        $d = $request->validate([
            'etat' => 'required|in:bon,degrade,hors_service',
            'statut' => 'sometimes|in:disponible,affecte,en_maintenance',
        ]);
        DB::table('biens')->where('id', $id)->update($d + ['updated_at' => now()]);
        return $this->ok(DB::table('biens')->find($id));
    }

    /** GET /api/v1/assets/{id}/fiche - fiche de stock (grand livre par bien) :
     *  fiche bien + mouvements + valorisation historique. */
    public function fiche(Request $request, string $id)
    {
        $bien = DB::table('biens')->whereNull('deleted_at')->find($id);
        if (! $bien) return $this->error('NOT_FOUND', 'Bien introuvable.', 404);
        if ($resp = $this->horsPerimetre($request, $bien)) return $resp;
        $bien->specifique = match ($bien->type_specifique) {
            'immobilier' => DB::table('specifiques_immobiliers')->where('bien_id', $id)->first(),
            'vehicule' => DB::table('specifiques_vehicules')->where('bien_id', $id)->first(),
            'informatique' => DB::table('specifiques_informatique')->where('bien_id', $id)->first(),
            default => null,
        };
        $bien->photos = DB::table('photos_biens')->where('bien_id', $id)->get();

        $svc = fn () => Http::timeout(5)->withToken(env('SERVICE_TOKEN', ''));
        $toObj = fn ($rows) => collect($rows ?? [])->map(fn ($r) => (object) $r);

        // Mouvements du bien (entrees, affectations, sorties) - ordre chronologique
        $mouvements = collect();
        try {
            $r = $svc()->get(env('MOVEMENT_SERVICE_URL').'/api/v1/movements', ['bien_id' => $id, 'per_page' => 100]);
            if ($r->successful()) $mouvements = $toObj($r->json('data.data') ?? $r->json('data'));
        } catch (\Throwable) {}

        // Historique de valorisation du bien
        $valorisation = null;
        try {
            $r = $svc()->get(env('VALUATION_SERVICE_URL')."/api/v1/valorisation/$id");
            if ($r->successful()) $valorisation = $r->json('data');
        } catch (\Throwable) {}

        // Interventions de maintenance du bien
        $interventions = collect();
        try {
            $r = $svc()->get(env('MAINTENANCE_SERVICE_URL').'/api/v1/interventions', ['bien_id' => $id, 'per_page' => 100]);
            if ($r->successful()) $interventions = $toObj($r->json('data.data') ?? $r->json('data'));
        } catch (\Throwable) {}

        // Documents joints au bien
        $documents = collect();
        try {
            $r = $svc()->get(env('DOCUMENT_SERVICE_URL').'/api/v1/documents', ['entite_type' => 'bien', 'entite_type_id' => $id, 'per_page' => 100]);
            if ($r->successful()) $documents = $toObj($r->json('data.data') ?? $r->json('data'));
        } catch (\Throwable) {}

        $bien->mouvements = $mouvements->sortBy(fn ($m) => $m->date_effet ?? $m->created_at ?? '')->values();
        $bien->valorisation = $valorisation;
        $bien->interventions = $interventions;
        $bien->documents = $documents;
        return $this->ok($bien);
    }

    /** GET /api/v1/assets/{id}/qrcode.svg */
    public function qrcode(Request $request, string $id)
    {
        $bien = DB::table('biens')->whereNull('deleted_at')->find($id);
        if (! $bien) return $this->error('NOT_FOUND', 'Bien introuvable.', 404);
        if ($resp = $this->horsPerimetre($request, $bien)) return $resp;
        $svg = \SimpleSoftwareIO\QrCode\QrCode::format('svg')->size(300)->generate($bien->code_inventaire);
        return response($svg, 200, ['Content-Type' => 'image/svg+xml']);
    }

    /** GET /api/v1/assets/stats - statistiques pour reporting */
    public function stats(Request $request)
    {
        $base = DB::table('biens')->whereNull('deleted_at');
        $claims = $request->attributes->get('jwt', []);
        $perms = (array) ($claims['permissions'] ?? []);
        if (! in_array('*', $perms, true) && ! empty($claims['structure_id'])) {
            $base->where('structure_id', $claims['structure_id']);
        }
        return $this->ok([
            'total' => (clone $base)->count(),
            'par_statut' => (clone $base)->select('statut', DB::raw('count(*) as n'))->groupBy('statut')->pluck('n', 'statut'),
            'par_categorie' => (clone $base)->select('categorie_id', DB::raw('count(*) as n'), DB::raw('sum(valeur_acquisition) as valeur'))->groupBy('categorie_id')->get(),
            'par_structure' => (clone $base)->select('structure_id', DB::raw('count(*) as n'), DB::raw('sum(valeur_acquisition) as valeur'))->groupBy('structure_id')->get(),
            'valeur_totale' => (clone $base)->sum('valeur_acquisition'),
        ]);
    }

    /**
     * Retourne une reponse 403 si le bien est hors du perimetre RG-06 de
     * l'appelant, null sinon. Les agents centraux (sans structure_id) et
     * les administrateurs ('*') voient tout.
     */
    private function horsPerimetre(Request $request, object $bien)
    {
        $claims = $request->attributes->get('jwt', []);
        $perms = (array) ($claims['permissions'] ?? []);
        if (in_array('*', $perms, true) || ($claims['sub'] ?? '') === 'service') {
            return null;
        }
        $sid = $claims['structure_id'] ?? null;
        if ($sid && ($bien->structure_id ?? null) !== $sid) {
            return $this->error('FORBIDDEN_PERIMETRE', 'Ce bien est hors de votre perimetre (RG-06).', 403);
        }
        return null;
    }

    /** Vrai si l'appel provient d'un autre micro-service (SERVICE_TOKEN). */
    private function isServiceCall(Request $request): bool
    {
        $claims = $request->attributes->get('jwt', []);
        return ($claims['sub'] ?? '') === 'service';
    }

    private function generateCode(): string
    {
        $year = now()->format('Y');
        // La generation est protegee par retry cote store() en cas de
        // collision concurrente sur la contrainte unique.
        $last = DB::table('biens')->where('code_inventaire', 'like', "PAT-$year-%")
            ->orderByDesc('code_inventaire')->value('code_inventaire');
        $seq = $last ? ((int) substr($last, -6)) + 1 : 1;
        return sprintf('PAT-%s-%06d', $year, $seq);
    }

    private function publish(string $event, array $payload): void
    {
        try {
            app(\App\Support\EventBus::class)->publish($event, $payload);
        } catch (\Throwable $e) {
            report($e); // ne bloque pas le metier si RabbitMQ est indisponible
        }
    }

    private function audit(Request $request, string $action, string $entite, array $avant, array $apres): void
    {
        try {
            Http::timeout(3)->withToken(env('SERVICE_TOKEN', ''))->post(env('NOTIFICATION_SERVICE_URL').'/api/v1/audit', [
                'utilisateur_id' => $request->attributes->get('jwt')['sub'] ?? null,
                'service_source' => 'asset-service',
                'action' => $action,
                'entite' => 'bien',
                'entite_id' => $entite,
                'avant' => $avant,
                'apres' => $apres,
                'ip' => $request->ip(),
            ]);
        } catch (\Throwable) { /* audit best-effort */ }
    }
}
