<?php

namespace App\Support;

use Dompdf\Dompdf;
use Dompdf\FontMetrics;
use Dompdf\Options;

/**
 * Export PDF A4 via dompdf.
 * - Page de garde : premiere page du document HTML.
 * - En-tete et pied "Page X / N" traces par page_script() sur les pages
 *   de contenu uniquement (page 1 = couverture, ignoree).
 */
class PdfExporter
{
    public static function render(string $html, string $titre, bool $paysage = false): string
    {
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('chroot', '/');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('a4', $paysage ? 'landscape' : 'portrait');
        $dompdf->render();

        self::decorerPages($dompdf, $titre);

        return $dompdf->output();
    }

    private static function decorerPages(Dompdf $dompdf, string $titre): void
    {
        $canvas = $dompdf->getCanvas();

        $canvas->page_script(function (int $num, int $count, $pdf, FontMetrics $fm) use ($titre) {
            if ($num <= 1) {
                return; // page de garde : pas d'en-tete/pied courant
            }
            $font = $fm->getFont('DejaVu Sans', 'normal');
            $W = $pdf->get_width();
            $H = $pdf->get_height();
            $marge = 40.0; // points
            $vert = [0.0, 0.416, 0.306];
            $gris = [0.4, 0.4, 0.4];

            // Coordonnees canvas : y mesure depuis le HAUT de la page.
            // Armoirie dans l'en-tete (ratio ~0.7 -> h=14pt, w=10pt).
            $img = resource_path('assets/armoiries.png');
            if (is_file($img)) {
                $pdf->image($img, $marge, 26, 10, 14);
            }

            // En-tete : filet vert + libelles
            // NB : text() dessine sur la page courante ; page_text() enregistrerait
            // un script "toutes pages" (et s'executerait aussi sur la couverture).
            $pdf->line($marge, 46, $W - $marge, 46, $vert, 0.8);
            $pdf->text($marge + 14, 32, 'République Togolaise · Travail · Liberté · Patrie', $font, 7, $gris);
            $pdf->text($W - $marge - 160, 32, $titre, $font, 8, $vert);

            // Pied : filet gris + numerotation
            $yBas = $H - 45;
            $pdf->line($marge, $yBas, $W - $marge, $yBas, [0.85, 0.85, 0.85], 0.5);
            $pdf->text($W / 2 - 25, $yBas + 12, "Page {$num} / {$count}", $font, 8, $gris);
        });
    }
}
