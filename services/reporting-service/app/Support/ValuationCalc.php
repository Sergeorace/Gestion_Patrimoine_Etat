<?php

namespace App\Support;

use DateTimeImmutable;

/**
 * Calculs de valorisation — methodes pures, sans dependance BDD.
 *
 * Regles (spec rapports) :
 *  - Valeur totale = somme des valeurs d'acquisition.
 *  - Amortissement lineaire sur la duree de vie de la categorie.
 *  - VNC = valeur d'acquisition - amortissement cumule (plancher a 0).
 *  - Donnee absente -> null (affiche "n.p." dans les rendus).
 */
class ValuationCalc
{
    /**
     * @return array{acquisition: ?float, amortissement: ?float, vnc: ?float}
     */
    public static function compute(
        ?float $valeurAcquisition,
        ?int $dureeVieMois,
        ?string $dateAcquisition,
        ?string $asOf = null
    ): array {
        if ($valeurAcquisition === null) {
            return ['acquisition' => null, 'amortissement' => null, 'vnc' => null];
        }

        // Pas de duree de vie connue -> amortissement et VNC non determinables
        if (! $dureeVieMois || $dureeVieMois <= 0 || ! $dateAcquisition) {
            return [
                'acquisition' => $valeurAcquisition,
                'amortissement' => null,
                'vnc' => null,
            ];
        }

        $acq = (float) $valeurAcquisition;
        $duree = (int) $dureeVieMois;
        $debut = new DateTimeImmutable($dateAcquisition);
        $fin = $asOf ? new DateTimeImmutable($asOf) : new DateTimeImmutable('now');
        if ($fin < $debut) {
            $fin = $debut;
        }

        // Nombre de mois entiers ecoules entre acquisition et date de reference
        $mois = ($fin->format('Y') - $debut->format('Y')) * 12
            + ((int) $fin->format('n') - (int) $debut->format('n'))
            - ((int) $fin->format('j') < (int) $debut->format('j') ? 1 : 0);
        $mois = max(0, $mois);

        $dotationMois = $acq / $duree;
        $amortissement = min($acq, $dotationMois * min($mois, $duree));
        $vnc = max(0.0, $acq - $amortissement);

        return [
            'acquisition' => round($acq, 2),
            'amortissement' => round($amortissement, 2),
            'vnc' => round($vnc, 2),
        ];
    }

    /**
     * Dotation annuelle aux amortissements (OHADA : amortissement lineaire,
     * plan predéfini sur la duree de vie). Retourne null si non determinable.
     */
    public static function dotationAnnuelle(?float $valeur, ?int $dureeVieMois, ?string $dateMiseEnService): ?float
    {
        if ($valeur === null || ! $dureeVieMois || $dureeVieMois <= 0 || ! $dateMiseEnService) {
            return null;
        }
        return round(min((float) $valeur, ((float) $valeur / $dureeVieMois) * 12), 2);
    }

    /**
     * Regroupe des lignes (biens) par cle et calcule les sous-totaux.
     * Retourne [cle => ['nb' => int, 'valeur' => float, 'vnc' => float, 'lignes' => array]]
     */
    public static function groupTotals(array $lignes, callable $keyFn): array
    {
        $groupes = [];
        foreach ($lignes as $l) {
            $k = $keyFn($l);
            $g = &$groupes[$k];
            $g['nb'] = ($g['nb'] ?? 0) + 1;
            $g['valeur'] = ($g['valeur'] ?? 0.0) + (float) ($l['valeur'] ?? 0);
            $g['vnc'] = ($g['vnc'] ?? 0.0) + (float) ($l['vnc'] ?? 0);
            $g['lignes'][] = $l;
        }
        return $groupes;
    }

    public static function totals(array $lignes): array
    {
        return [
            'nb' => count($lignes),
            'valeur' => array_sum(array_map(fn ($l) => (float) ($l['valeur'] ?? 0), $lignes)),
            'vnc' => array_sum(array_map(fn ($l) => (float) ($l['vnc'] ?? 0), $lignes)),
        ];
    }
}
