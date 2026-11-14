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
        @page { margin: 24mm 16mm 20mm; }
        * { box-sizing: border-box; }
        body { font-family: "DejaVu Sans", sans-serif; color: '.self::TEXTE.'; font-size: 10pt; line-height: 1.45; margin: 0; }
        h1 { font-size: 18pt; color: '.self::VERT.'; margin: 0 0 6mm; }
        h2 { font-size: 13pt; color: '.self::VERT.'; border-bottom: 1.5px solid '.self::VERT.'; padding-bottom: 2mm; margin: 9mm 0 4mm; }
        h3 { font-size: 11pt; color: '.self::VERT.'; margin: 5mm 0 2.5mm; }
        table { width: 100%; border-collapse: collapse; margin: 3mm 0 7mm; }
        th { background: '.self::VERT.'; color: #fff; font-size: 9pt; font-weight: bold; letter-spacing: .3px; text-align: left; padding: 2.4mm 2.6mm; }
        td { padding: 2.4mm 2.6mm; border-bottom: 0.3px solid #d8d5c8; font-size: 9.5pt; vertical-align: middle; }
        tr { page-break-inside: avoid; }
        tr:nth-child(even) td { background: #f6f7f4; }
        .num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .centrer { text-align: center; }
        .groupe td { background: #e8efe9 !important; font-weight: bold; color: '.self::VERT.'; padding-top: 3mm; padding-bottom: 3mm; }
        .groupe .groupe-nb { font-weight: normal; color: #475467; }
        .sous-total td { font-weight: bold; background: #f0f4f1; border-top: 1px solid '.self::VERT.'; }
        .total-general td { font-weight: bold; background: '.self::VERT.'; color: #fff; font-size: 10.5pt; padding-top: 3mm; padding-bottom: 3mm; }
        .cover { page-break-after: always; text-align: center; padding-top: 30mm; }
        .cover .embleme { font-size: 60pt; color: '.self::VERT.'; }
        .cover .rep { font-size: 15pt; font-weight: bold; letter-spacing: 1px; color: '.self::VERT.'; }
        .cover .devise { font-size: 11pt; color: #6b7280; margin: 2mm 0 12mm; }
        .cover .titre { font-size: 20pt; font-weight: bold; color: '.self::TEXTE.'; margin-bottom: 10mm; }
        .cover table { width: 80%; margin: 0 auto; }
        .cover td { border: none; padding: 1.8mm 3mm; text-align: left; font-size: 10pt; }
        .cover td:first-child { color: #6b7280; width: 40%; }
        .meta { font-size: 8.5pt; color: #6b7280; margin-top: 8mm; }
        .vide { text-align: center; color: #6b7280; padding: 10mm 0; font-style: italic; }
        .synthese { display: table; width: 100%; border: 1px solid #d8d5c8; margin-bottom: 5mm; }
        .synthese td { border: none; padding: 4mm 5mm; text-align: center; }
        .synthese .k { font-size: 8.5pt; color: #6b7280; text-transform: uppercase; letter-spacing: .5px; }
        .synthese .v { font-size: 14pt; font-weight: bold; color: '.self::VERT.'; }
        .section-casse { page-break-before: always; }
        ';
    }

    private static function emblemeHtml(): string
    {
        $png = resource_path('assets/armoiries.png');
        if (! is_file($png)) {
            $png = storage_path('app/armoiries.png');
        }
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
            <th>Code</th><th>Compte</th><th>Désignation</th><th>Catégorie</th><th>Domaine</th><th>Secteur</th>
            <th>Service</th><th>État</th><th>Date acq.</th><th class="num">Valeur (FCFA)</th>';
        if ($avecVnc) $html .= '<th class="num">Amort. (FCFA)</th><th class="num">VNC (FCFA)</th>';
        $html .= '</tr></thead><tbody>';

        $renderLigne = function (array $r) use ($avecVnc) {
            $etat = ['bon' => 'Bon', 'moyen' => 'Moyen', 'degrade' => 'Dégradé', 'hors_service' => 'Hors service'][$r['etat'] ?? ''] ?? self::np($r['etat']);
            $h = '<tr>
                <td>'.self::np($r['code']).'</td>
                <td>'.self::np($r['compte'] ?? null).'</td>
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

        // 9 colonnes texte puis les colonnes de montants (valeur, amort., VNC)
        $colsTexte = 9;
        $montants = function (array $lignes) use ($avecVnc) {
            $somme = fn (string $k) => array_sum(array_map(fn ($l) => (float) ($l[$k] ?? 0), $lignes));
            $h = '<td class="num">'.self::fmt($somme('valeur')).'</td>';
            if ($avecVnc) {
                $h .= '<td class="num">'.self::fmt($somme('amortissement')).'</td>'
                     .'<td class="num">'.self::fmt($somme('vnc')).'</td>';
            }
            return $h;
        };
        // Une seule ligne par niveau : libelle + effectif + sous-totaux
        $ligneGroupe = function (string $label, array $g) use ($colsTexte, $montants) {
            return '<tr class="groupe"><td colspan="'.$colsTexte.'">'.$label
                .' <span class="groupe-nb">('.$g['nb'].' bien'.($g['nb'] > 1 ? 's' : '').')</span></td>'
                .$montants($g['lignes']).'</tr>';
        };

        // Regroupement region > prefecture > commune
        $parRegion = ValuationCalc::groupTotals($tri, fn ($r) => $r['region'] ?? 'Non renseignée');
        foreach ($parRegion as $region => $gr) {
            $html .= $ligneGroupe('Région : '.self::e($region), $gr);

            $lignesRegion = $gr['lignes'];
            $parPref = ValuationCalc::groupTotals($lignesRegion, fn ($r) => $r['prefecture'] ?? 'Non renseignée');
            foreach ($parPref as $pref => $gp) {
                $html .= $ligneGroupe('&nbsp;&nbsp;Préfecture : '.self::e($pref), $gp);

                $parCommune = ValuationCalc::groupTotals($gp['lignes'], fn ($r) => $r['commune'] ?? 'Non renseignée');
                foreach ($parCommune as $commune => $gc) {
                    $html .= $ligneGroupe('&nbsp;&nbsp;&nbsp;Commune : '.self::e($commune), $gc);
                    foreach ($gc['lignes'] as $r) $html .= $renderLigne($r);
                }
            }
        }

        // Total general
        $t = ValuationCalc::totals($tri);
        $html .= '<tr class="total-general"><td colspan="'.$colsTexte.'">TOTAL GÉNÉRAL ('.$t['nb'].' biens)</td>'
            .$montants($tri)
            .'</tr></tbody></table>';

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

    /** Etat des immobilisations : ouverture -> mouvements -> cloture. */
    public static function etatImmobilisations(array $e): string
    {
        $fmtD = fn ($d) => \Carbon\Carbon::parse($d)->format('d/m/Y');
        $periode = 'Période du '.$fmtD($e['debut']).' au '.$fmtD($e['fin']);

        $o = $e['ouverture'];
        $c = $e['cloture'];
        $a = $e['acquisitions'];
        $d = $e['dotations'];
        $s = $e['sorties'];

        $html = '<h2>État des immobilisations</h2><p style="color:#6b7280;font-size:9pt;margin:0 0 3mm;">'.$periode.' — '.$e['nb_biens'].' bien(s) à la clôture</p>';

        // Tableau de synthese mouvemente
        $html .= '<table><thead><tr>
            <th>Poste</th><th class="num">Valeur brute (FCFA)</th><th class="num">Amort. cumulé (FCFA)</th><th class="num">VNC (FCFA)</th></tr></thead><tbody>'
            .'<tr><td>Valeur nette à l\'ouverture</td><td class="num">'.self::fmt($o['brut']).'</td><td class="num">'.self::fmt($o['amort']).'</td><td class="num">'.self::fmt($o['vnc']).'</td></tr>'
            .'<tr><td>&nbsp;&nbsp;Acquisitions de la période ('.count($a['lignes']).')</td><td class="num">+'.self::fmt($a['total']).'</td><td></td><td></td></tr>'
            .'<tr><td>&nbsp;&nbsp;Dotations aux amortissements</td><td></td><td class="num">+'.self::fmt($d['total']).'</td><td class="num">−'.self::fmt($d['total']).'</td></tr>'
            .'<tr><td>&nbsp;&nbsp;Sorties (VNC sortie)</td><td class="num">−'.self::fmt($s['vnc']).'</td><td></td><td class="num">−'.self::fmt($s['vnc']).'</td></tr>'
            .'<tr class="total-general"><td>Valeur nette à la clôture</td><td class="num">'.self::fmt($c['brut']).'</td><td class="num">'.self::fmt($c['amort']).'</td><td class="num">'.self::fmt($c['vnc']).'</td></tr>'
            .'</tbody></table>';

        // Acquisitions
        if ($a['lignes']) {
            $html .= '<h3>Acquisitions de la période</h3><table><thead><tr>
                <th>Date</th><th>Code</th><th>Désignation</th><th>Compte</th><th class="num">Valeur (FCFA)</th></tr></thead><tbody>';
            foreach ($a['lignes'] as $l) {
                $html .= '<tr><td class="centrer">'.self::dateFr($l['date']).'</td><td>'.self::np($l['code']).'</td><td>'.self::np($l['designation']).'</td><td>'.self::np($l['compte']).'</td><td class="num">'.self::fmt($l['valeur']).'</td></tr>';
            }
            $html .= '</tbody></table>';
        }

        // Dotations par bien
        if ($d['lignes']) {
            $html .= '<h3>Dotations aux amortissements</h3><table><thead><tr>
                <th>Code</th><th>Désignation</th><th>Compte</th><th class="centrer">Mois</th><th class="num">Dotation (FCFA)</th></tr></thead><tbody>';
            foreach ($d['lignes'] as $l) {
                $html .= '<tr><td>'.self::np($l['code']).'</td><td>'.self::np($l['designation']).'</td><td>'.self::np($l['compte']).'</td><td class="centrer">'.$l['nb_mois'].'</td><td class="num">'.self::fmt($l['dotation']).'</td></tr>';
            }
            $html .= '<tr class="sous-total"><td colspan="4">Total dotations</td><td class="num">'.self::fmt($d['total']).'</td></tr></tbody></table>';
        }

        // Sorties
        if ($s['lignes']) {
            $html .= '<h3>Sorties définitives de la période</h3><table><thead><tr>
                <th>Date</th><th>Référence</th><th>Code</th><th>Désignation</th><th>Motif</th>
                <th class="num">VNC sortie (FCFA)</th><th class="num">Valeur de cession (FCFA)</th></tr></thead><tbody>';
            foreach ($s['lignes'] as $l) {
                $html .= '<tr><td class="centrer">'.self::dateFr($l['date']).'</td><td>'.self::np($l['reference']).'</td><td>'.self::np($l['code']).'</td><td>'.self::np($l['designation']).'</td><td>'.self::np($l['motif']).'</td><td class="num">'.self::fmt($l['vnc_sortie']).'</td><td class="num">'.self::fmt($l['valeur_sortie']).'</td></tr>';
            }
            $html .= '<tr class="sous-total"><td colspan="5">Totaux</td><td class="num">'.self::fmt($s['vnc']).'</td><td class="num">'.self::fmt($s['cession']).'</td></tr></tbody></table>';
        }

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
