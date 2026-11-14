<?php

namespace App\Support;

/**
 * Construit le HTML des rapports (page de garde, synthese, tableaux groupes).
 * Rendu ensuite en PDF (dompdf) ou utilise comme base des exports.
 */
class ReportRenderer
{
    public const VERT = '#006a4e';
    public const TEXTE = '#101828';

    public static function fmt(?float $v): string
    {
        if ($v === null) return 'n.p.';
        // espace fine insécable comme separateur de milliers
        return number_format($v, 0, ',', "\u{202F}").' FCFA';
    }

    public static function np($v): string
    {
        return ($v === null || $v === '') ? 'n.p.' : e((string) $v);
    }

    public static function dateFr(?string $d): string
    {
        if (! $d) return 'n.p.';
        $t = strtotime(substr($d, 0, 10));
        return $t ? date('d/m/Y', $t) : 'n.p.';
    }

    private static function e($s): string
    {
        return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    }

    // ------------------------------------------------------------------
    // En-tetes / styles
    // ------------------------------------------------------------------

    private static function css(bool $paysage = false): string
    {
        return '
        @page { margin: 24mm 15mm 18mm; }
        * { box-sizing: border-box; }
        body { font-family: "DejaVu Sans", sans-serif; color: '.self::TEXTE.'; font-size: 10pt; margin: 0; }
        h1 { font-size: 17pt; color: '.self::VERT.'; margin: 0 0 4mm; }
        h2 { font-size: 12pt; color: '.self::VERT.'; border-bottom: 1.5px solid '.self::VERT.'; padding-bottom: 1.5mm; margin: 6mm 0 3mm; }
        h3 { font-size: 10.5pt; color: '.self::VERT.'; margin: 4mm 0 2mm; }
        table { width: 100%; border-collapse: collapse; margin: 2mm 0 4mm; }
        th { background: '.self::VERT.'; color: #fff; font-size: 8.5pt; text-align: left; padding: 1.6mm 2mm; }
        td { padding: 1.4mm 2mm; border-bottom: 0.3px solid #d8d5c8; font-size: 9pt; vertical-align: top; }
        tr:nth-child(even) td { background: #f6f7f4; }
        .num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .centrer { text-align: center; }
        .groupe { background: #e8efe9 !important; font-weight: bold; color: '.self::VERT.'; }
        .sous-total td { font-weight: bold; background: #f0f4f1; border-top: 1px solid '.self::VERT.'; }
        .total-general td { font-weight: bold; background: '.self::VERT.'; color: #fff; font-size: 10pt; }
        .cover { page-break-after: always; text-align: center; padding-top: 30mm; }
        .cover .embleme { font-size: 60pt; color: '.self::VERT.'; }
        .cover .rep { font-size: 15pt; font-weight: bold; letter-spacing: 1px; color: '.self::VERT.'; }
        .cover .devise { font-size: 11pt; color: #6b7280; margin: 2mm 0 12mm; }
        .cover .titre { font-size: 20pt; font-weight: bold; color: '.self::TEXTE.'; margin-bottom: 10mm; }
        .cover table { width: 80%; margin: 0 auto; }
        .cover td { border: none; padding: 1.2mm 3mm; text-align: left; font-size: 10pt; }
        .cover td:first-child { color: #6b7280; width: 40%; }
        .meta { font-size: 8.5pt; color: #6b7280; margin-top: 8mm; }
        .vide { text-align: center; color: #6b7280; padding: 10mm 0; font-style: italic; }
        .synthese { display: table; width: 100%; border: 1px solid #d8d5c8; margin-bottom: 5mm; }
        .synthese td { border: none; padding: 3mm 4mm; text-align: center; }
        .synthese .k { font-size: 8.5pt; color: #6b7280; text-transform: uppercase; letter-spacing: .5px; }
        .synthese .v { font-size: 14pt; font-weight: bold; color: '.self::VERT.'; }
        .section-casse { page-break-before: always; }
        ';
    }

    private static function emblemeHtml(): string
    {
        $png = storage_path('app/armoiries.png');
        if (is_file($png)) {
            $b64 = base64_encode((string) file_get_contents($png));
            return '<img src="data:image/png;base64,'.$b64.'" style="height: 34mm;" alt="Armoiries">';
        }
        return '<div class="embleme">★</div>';
    }

    // ------------------------------------------------------------------
    // Page de garde
    // ------------------------------------------------------------------

    public static function cover(string $titre, array $filtres, string $user, string $quand): string
    {
        $lignes = '';
        foreach ($filtres as $label => $val) {
            if ($val === null || $val === '') continue;
            $lignes .= '<tr><td>'.self::e($label).'</td><td>'.self::e($val).'</td></tr>';
        }
        if (! $lignes) $lignes = '<tr><td colspan="2" style="text-align:center;color:#6b7280;">Aucun filtre appliqué (périmètre national)</td></tr>';

        return '
        <div class="cover">
            '.self::emblemeHtml().'
            <div class="rep">RÉPUBLIQUE TOGOLAISE</div>
            <div class="devise">Travail · Liberté · Patrie</div>
            <div class="titre">'.self::e($titre).'</div>
            <table>'.$lignes.'</table>
            <div class="meta">
                Généré le '.self::e($quand).' par '.self::e($user).'<br>
                Plateforme de gestion du patrimoine de l\'État — '.self::e($titre).'
            </div>
        </div>';
    }

    // ------------------------------------------------------------------
    // Sections
    // ------------------------------------------------------------------

    public static function synthese(array $s): string
    {
        return '
        <h2>Synthèse</h2>
        <table class="synthese">
            <tr>
                <td><div class="k">Biens</div><div class="v">'.number_format($s['nombre_biens'] ?? 0, 0, ',', "\u{202F}").'</div></td>
                <td><div class="k">Valeur totale</div><div class="v">'.self::fmt($s['valeur_totale'] ?? null).'</div></td>
                <td><div class="k">Amortissements</div><div class="v">'.self::fmt($s['amortissements'] ?? null).'</div></td>
                <td><div class="k">VNC totale</div><div class="v">'.self::fmt($s['vnc_totale'] ?? null).'</div></td>
            </tr>
        </table>';
    }

    /**
     * Tableau des biens groupe : region > prefecture > commune,
     * sous-totaux a chaque niveau + total general.
     */
    public static function tableauBiens(array $assets, bool $avecVnc = true): string
    {
        if (! $assets) {
            return '<p class="vide">Aucun bien ne correspond à ces filtres.</p>';
        }

        $tri = $assets;
        usort($tri, fn ($a, $b) =>
            [$a['region'] ?? '', $a['prefecture'] ?? '', $a['commune'] ?? '', $a['domaine_libelle'] ?? '', $a['secteur'] ?? '', $a['code'] ?? '']
            <=>
            [$b['region'] ?? '', $b['prefecture'] ?? '', $b['commune'] ?? '', $b['domaine_libelle'] ?? '', $b['secteur'] ?? '', $b['code'] ?? '']);

        $html = '<table><thead><tr>
            <th>Code</th><th>Désignation</th><th>Catégorie</th><th>Domaine</th><th>Secteur</th>
            <th>Service</th><th>État</th><th>Date acq.</th><th class="num">Valeur (FCFA)</th>';
        if ($avecVnc) $html .= '<th class="num">Amort. (FCFA)</th><th class="num">VNC (FCFA)</th>';
        $html .= '</tr></thead><tbody>';

        $renderLigne = function (array $r) use ($avecVnc) {
            $etat = ['bon' => 'Bon', 'moyen' => 'Moyen', 'degrade' => 'Dégradé', 'hors_service' => 'Hors service'][$r['etat'] ?? ''] ?? self::np($r['etat']);
            $h = '<tr>
                <td>'.self::np($r['code']).'</td>
                <td>'.self::np($r['designation']).'</td>
                <td>'.self::np($r['categorie']).'</td>
                <td>'.self::np($r['domaine_libelle']).'</td>
                <td>'.self::np($r['secteur']).'</td>
                <td>'.self::np($r['service']).'</td>
                <td>'.$etat.'</td>
                <td class="centrer">'.self::dateFr($r['date_acquisition'] ?? null).'</td>
                <td class="num">'.self::fmt($r['valeur'] ?? null).'</td>';
            if ($avecVnc) {
                $h .= '<td class="num">'.self::fmt($r['amortissement'] ?? null).'</td>
                       <td class="num">'.self::fmt($r['vnc'] ?? null).'</td>';
            }
            return $h.'</tr>';
        };

        // Regroupement region > prefecture > commune
        $parRegion = ValuationCalc::groupTotals($tri, fn ($r) => $r['region'] ?? 'Non renseignée');
        foreach ($parRegion as $region => $gr) {
            $html .= '<tr class="groupe"><td colspan="9">Région : '.self::e($region).' ('.$gr['nb'].' bien'.($gr['nb'] > 1 ? 's' : '').')</td>';
            if ($avecVnc) $html .= '<td></td><td></td>';
            $html .= '</tr>';

            $lignesRegion = $gr['lignes'];
            $parPref = ValuationCalc::groupTotals($lignesRegion, fn ($r) => $r['prefecture'] ?? 'Non renseignée');
            foreach ($parPref as $pref => $gp) {
                $html .= '<tr class="groupe"><td colspan="9">&nbsp;&nbsp;Préfecture : '.self::e($pref).' ('.$gp['nb'].')</td>';
                if ($avecVnc) $html .= '<td></td><td></td>';
                $html .= '</tr>';

                $parCommune = ValuationCalc::groupTotals($gp['lignes'], fn ($r) => $r['commune'] ?? 'Non renseignée');
                foreach ($parCommune as $commune => $gc) {
                    $html .= '<tr class="groupe"><td colspan="9">&nbsp;&nbsp;&nbsp;Commune : '.self::e($commune).' ('.$gc['nb'].')</td>';
                    if ($avecVnc) $html .= '<td></td><td></td>';
                    $html .= '</tr>';
                    foreach ($gc['lignes'] as $r) $html .= $renderLigne($r);
                    // sous-total commune (valeur + vnc + amortissements)
                    $amort = array_sum(array_map(fn ($l) => (float) ($l['amortissement'] ?? 0), $gc['lignes']));
                    $html .= '<tr class="sous-total"><td colspan="9">Sous-total — '.self::e($commune).'</td>
                        <td class="num">'.self::fmt($gc['valeur']).'</td>';
                    if ($avecVnc) {
                        $html .= '<td class="num">'.self::fmt($amort).'</td><td class="num">'.self::fmt($gc['vnc']).'</td>';
                    }
                    $html .= '</tr>';
                }
                // sous-total prefecture
                $amortP = array_sum(array_map(fn ($l) => (float) ($l['amortissement'] ?? 0), $gp['lignes']));
                $vncP = array_sum(array_map(fn ($l) => (float) ($l['vnc'] ?? 0), $gp['lignes']));
                $valP = array_sum(array_map(fn ($l) => (float) ($l['valeur'] ?? 0), $gp['lignes']));
                $html .= '<tr class="sous-total"><td colspan="9">Sous-total — Préfecture de '.self::e($pref).'</td>
                    <td class="num">'.self::fmt($valP).'</td>';
                if ($avecVnc) $html .= '<td class="num">'.self::fmt($amortP).'</td><td class="num">'.self::fmt($vncP).'</td>';
                $html .= '</tr>';
            }
            // sous-total region
            $amortR = array_sum(array_map(fn ($l) => (float) ($l['amortissement'] ?? 0), $lignesRegion));
            $vncR = array_sum(array_map(fn ($l) => (float) ($l['vnc'] ?? 0), $lignesRegion));
            $valR = array_sum(array_map(fn ($l) => (float) ($l['valeur'] ?? 0), $lignesRegion));
            $html .= '<tr class="sous-total"><td colspan="9">Sous-total — Région de '.self::e($region).'</td>
                <td class="num">'.self::fmt($valR).'</td>';
            if ($avecVnc) $html .= '<td class="num">'.self::fmt($amortR).'</td><td class="num">'.self::fmt($vncR).'</td>';
            $html .= '</tr>';
        }

        // Total general
        $t = ValuationCalc::totals($tri);
        $amortT = array_sum(array_map(fn ($l) => (float) ($l['amortissement'] ?? 0), $tri));
        $html .= '<tr class="total-general"><td colspan="9">TOTAL GÉNÉRAL ('.$t['nb'].' biens)</td>
            <td class="num">'.self::fmt($t['valeur']).'</td>';
        if ($avecVnc) $html .= '<td class="num">'.self::fmt($amortT).'</td><td class="num">'.self::fmt($t['vnc']).'</td>';
        $html .= '</tr></tbody></table>';

        return $html;
    }

    public static function tableauMaintenances(array $rows): string
    {
        if (! $rows) return '<p class="vide">Aucune intervention ne correspond à ces filtres.</p>';
        $html = '<table><thead><tr>
            <th>Bien</th><th>Code</th><th>Type</th><th>Statut</th><th>Description</th>
            <th>Prévue le</th><th>Réalisée le</th><th class="num">Coût (FCFA)</th></tr></thead><tbody>';
        $types = ['preventive' => 'Préventive', 'curative' => 'Corrective', 'panne' => 'Panne'];
        $statuts = ['declaree' => 'Déclarée', 'planifiee' => 'Planifiée', 'en_cours' => 'En cours', 'terminee' => 'Terminée', 'annulee' => 'Annulée'];
        $total = 0;
        foreach ($rows as $r) {
            $total += (float) $r['cout'];
            $html .= '<tr>
                <td>'.self::np($r['bien']).'</td>
                <td>'.self::np($r['code']).'</td>
                <td>'.self::np($types[$r['type']] ?? $r['type']).'</td>
                <td>'.self::np($statuts[$r['statut']] ?? $r['statut']).'</td>
                <td>'.self::np($r['description']).'</td>
                <td class="centrer">'.self::dateFr($r['date_prevue'] ?? null).'</td>
                <td class="centrer">'.self::dateFr($r['date_realisee'] ?? null).'</td>
                <td class="num">'.self::fmt($r['cout']).'</td></tr>';
        }
        $html .= '<tr class="total-general"><td colspan="7">TOTAL ('.count($rows).' interventions)</td>
            <td class="num">'.self::fmt($total).'</td></tr></tbody></table>';
        return $html;
    }

    public static function tableauInventaires(array $rows): string
    {
        if (! $rows) return '<p class="vide">Aucune campagne d\'inventaire ne correspond à ces filtres.</p>';
        $statuts = ['planifiee' => 'Planifiée', 'en_cours' => 'En cours', 'cloturee' => 'Clôturée'];
        $html = '<table><thead><tr>
            <th>Campagne</th><th>Libellé</th><th>Structure</th><th>Site</th><th>Région</th>
            <th>Début</th><th>Fin</th><th class="num">Biens contrôlés</th><th class="num">Manquants</th>
            <th class="num">Écarts d\'état</th><th>Statut</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            $html .= '<tr>
                <td>'.self::np($r['code']).'</td>
                <td>'.self::np($r['libelle']).'</td>
                <td>'.self::np($r['structure']).'</td>
                <td>'.self::np($r['site']).'</td>
                <td>'.self::np($r['region']).'</td>
                <td class="centrer">'.self::dateFr($r['date_debut'] ?? null).'</td>
                <td class="centrer">'.self::dateFr($r['date_fin'] ?? null).'</td>
                <td class="num">'.number_format($r['nb_controles'], 0, ',', "\u{202F}").'</td>
                <td class="num">'.number_format($r['nb_manquants'], 0, ',', "\u{202F}").'</td>
                <td class="num">'.number_format($r['nb_ecarts'], 0, ',', "\u{202F}").'</td>
                <td>'.self::np($statuts[$r['statut']] ?? $r['statut']).'</td></tr>';
        }
        $html .= '</tbody></table>';
        return $html;
    }

    /** Assemble le document HTML complet. */
    public static function render(string $titre, string $sectionHtml, array $filtres, string $user, string $quand, bool $paysage = false): string
    {
        return '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><style>'.self::css($paysage).'</style></head><body>'
            .self::cover($titre, $filtres, $user, $quand)
            .'<div class="section-casse"><h1>'.self::e($titre).'</h1>'.$sectionHtml.'</div>'
            .'</body></html>';
    }
}
