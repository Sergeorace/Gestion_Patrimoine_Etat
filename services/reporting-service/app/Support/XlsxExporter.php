<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Export Excel .xlsx via PhpSpreadsheet.
 * - Feuille "Synthèse" : filtres, date, indicateurs.
 * - Feuilles de données selon le type.
 * - Feuille "Par région" pour les rapports de biens.
 * - En-têtes vert #006a4e / texte blanc, ligne figée, filtres auto,
 *   montants au format FCFA, dates JJ/MM/AAAA, totaux par formules.
 */
class XlsxExporter
{
    private const VERT = 'FF006A4E';
    private const LIGNE = 'FFD8D5C8';

    private Spreadsheet $book;
    private int $row = 1;

    public function __construct()
    {
        $this->book = new Spreadsheet();
    }

    public function build(string $titre, string $type, array $contexte, array $filtresLabels): self
    {
        $this->sheetSynthese($titre, $type, $contexte, $filtresLabels);
        return $this;
    }

    // ------------------------------------------------------------------
    // Feuille Synthèse
    // ------------------------------------------------------------------

    private function sheetSynthese(string $titre, string $type, array $contexte, array $filtresLabels): void
    {
        $ws = $this->book->getActiveSheet();
        $ws->setTitle('Synthèse');

        $ws->setCellValue('A1', 'RÉPUBLIQUE TOGOLAISE — Travail · Liberté · Patrie');
        $ws->mergeCells('A1:D1');
        $ws->getStyle('A1')->getFont()->setBold(true)->setSize(12)->getColor()->setRGB('006A4E');

        $ws->setCellValue('A2', $titre);
        $ws->getStyle('A2')->getFont()->setBold(true)->setSize(14);
        $ws->mergeCells('A2:D2');

        $r = 4;
        $ws->setCellValue("A{$r}", 'Date de génération');
        $ws->setCellValue("B{$r}", $contexte['quand']);
        $ws->setCellValue("C{$r}", 'Généré par');
        $ws->setCellValue("D{$r}", $contexte['user']);
        $this->labelRow($ws, $r);
        $r += 2;

        $ws->setCellValue("A{$r}", 'Filtres appliqués');
        $ws->mergeCells("A{$r}:D{$r}");
        $ws->getStyle("A{$r}")->getFont()->setBold(true);
        $r++;
        if (! $filtresLabels) {
            $ws->setCellValue("A{$r}", 'Aucun filtre (périmètre national)');
            $ws->mergeCells("A{$r}:D{$r}");
            $r += 2;
        }
        foreach ($filtresLabels as $label => $val) {
            $this->setTexte($ws, "A{$r}", $label);
            $this->setTexte($ws, "B{$r}", $val);
            $this->labelRow($ws, $r);
            $r++;
        }
        $r++;

        // Indicateurs clés
        $ws->setCellValue("A{$r}", 'Indicateurs clés');
        $ws->mergeCells("A{$r}:D{$r}");
        $ws->getStyle("A{$r}")->getFont()->setBold(true);
        $r++;
        $this->entete($ws, $r, ['Indicateur', 'Valeur']);
        $r++;
        foreach (($contexte['kpis'] ?? []) as $k => $v) {
            $this->setTexte($ws, "A{$r}", $k);
            if (is_numeric($v) && str_contains((string) $k, 'Valeur') || str_contains((string) $k, 'VNC') || str_contains((string) $k, 'Amortissement') || str_contains((string) $k, 'Coût')) {
                $ws->setCellValueExplicit("B{$r}", (float) $v, DataType::TYPE_NUMERIC);
                $ws->getStyle("B{$r}")->getNumberFormat()->setFormatCode('#,##0 "FCFA"');
            } else {
                $this->setTexte($ws, "B{$r}", $v);
            }
            $this->labelRow($ws, $r);
            $r++;
        }
        $ws->getColumnDimension('A')->setWidth(32);
        $ws->getColumnDimension('B')->setWidth(30);
        $ws->getColumnDimension('C')->setWidth(18);
        $ws->getColumnDimension('D')->setWidth(30);
    }

    // ------------------------------------------------------------------
    // Feuille de données générique
    // ------------------------------------------------------------------

    /**
     * @param array<int,string> $colonnes  [cle => libelle]
     * @param array<int,array>  $lignes
     * @param array<string,string> $formatsParCle : 'fcfa'|'date'|'int'
     */
    public function addDataSheet(string $nom, array $colonnes, array $lignes, array $formatsParCle = [], bool $sousTotaux = false): Worksheet
    {
        $nom = mb_substr($nom, 0, 31);
        $ws = $this->book->createSheet();
        $ws->setTitle($nom);

        // En-tête
        $col = 'A';
        $dernier = 'A';
        foreach ($colonnes as $cle => $libelle) {
            $ws->setCellValue($col.$this->row, $libelle);
            $dernier = $col;
            $col++;
        }
        $this->entete($ws, $this->row);
        $this->row++;

        $colDebut = 1;
        $colFin = count($colonnes);
        $premiereLigne = $this->row;
        $indexValeur = null;
        $indexVnc = null;
        $cles = array_keys($colonnes);
        foreach ($cles as $i => $cle) {
            if ($cle === 'valeur') $indexValeur = $i + 1;
            if ($cle === 'vnc') $indexVnc = $i + 1;
        }

        foreach ($lignes as $l) {
            $col = 'A';
            foreach ($cles as $cle) {
                $v = $l[$cle] ?? null;
                $fmt = $formatsParCle[$cle] ?? null;
                $this->ecrireCellule($ws, $col.$this->row, $v, $fmt);
                $col++;
            }
            $this->row++;
        }
        $derniereLigne = $this->row - 1;

        // Ligne de totaux avec vraies formules
        if ($lignes && $derniereLigne >= $premiereLigne) {
            $ws->setCellValue('A'.$this->row, 'TOTAL');
            $ws->getStyle('A'.$this->row)->getFont()->setBold(true);
            if ($indexValeur) {
                $lettre = $this->lettreColonne($indexValeur);
                $ws->setCellValue($lettre.$this->row, '=SUBTOTAL(9,'.$lettre.$premiereLigne.':'.$lettre.$derniereLigne.')');
                $ws->getStyle($lettre.$this->row)->getNumberFormat()->setFormatCode('#,##0 "FCFA"');
            }
            if ($indexVnc) {
                $lettre = $this->lettreColonne($indexVnc);
                $ws->setCellValue($lettre.$this->row, '=SUBTOTAL(9,'.$lettre.$premiereLigne.':'.$lettre.$derniereLigne.')');
                $ws->getStyle($lettre.$this->row)->getNumberFormat()->setFormatCode('#,##0 "FCFA"');
            }
            foreach (range($colDebut, $colFin) as $i) {
                $ws->getStyle($this->lettreColonne($i).$this->row)->getFont()->setBold(true);
            }
            $this->row++;
        }

        // Finition : ligne figee, filtres auto, largeurs
        $ws->freezePane('A'.($premiereLigne));
        if ($derniereLigne >= $premiereLigne) {
            $ws->setAutoFilter('A'.($premiereLigne - 1).':'.$dernier.$derniereLigne);
        }
        foreach (range(1, $colFin) as $i) {
            $ws->getColumnDimension($this->lettreColonne($i))->setAutoSize(true);
        }
        $this->row = 1;
        return $ws;
    }

    // ------------------------------------------------------------------
    // Feuille "Par région"
    // ------------------------------------------------------------------

    public function addParRegion(array $assets): self
    {
        $ws = $this->book->createSheet();
        $ws->setTitle('Par région');

        $colonnes = ['region' => 'Région', 'prefecture' => 'Préfecture', 'commune' => 'Commune',
                     'nb' => 'Nombre de biens', 'valeur' => 'Valeur totale', 'vnc' => 'VNC totale'];
        $this->row = 1;
        $this->addDataSheetTo($ws, $colonnes, $this->lignesRegion($assets), ['valeur' => 'fcfa', 'vnc' => 'fcfa', 'nb' => 'int']);
        return $this;
    }

    private function lignesRegion(array $assets): array
    {
        $groupes = [];
        foreach ($assets as $a) {
            $k = implode('|', [$a['region'] ?? '', $a['prefecture'] ?? '', $a['commune'] ?? '']);
            $g = &$groupes[$k];
            $g['region'] = $a['region'] ?? 'Non renseignée';
            $g['prefecture'] = $a['prefecture'] ?? 'Non renseignée';
            $g['commune'] = $a['commune'] ?? 'Non renseignée';
            $g['nb'] = ($g['nb'] ?? 0) + 1;
            $g['valeur'] = ($g['valeur'] ?? 0) + (float) ($a['valeur'] ?? 0);
            $g['vnc'] = ($g['vnc'] ?? 0) + (float) ($a['vnc'] ?? 0);
        }
        ksort($groupes);
        return array_values($groupes);
    }

    private function addDataSheetTo(Worksheet $ws, array $colonnes, array $lignes, array $formats): void
    {
        $this->row = 1;
        $col = 'A';
        foreach ($colonnes as $libelle) {
            $ws->setCellValue($col.$this->row, $libelle);
            $col++;
        }
        $this->entete($ws, $this->row);
        $this->row++;
        $premiere = $this->row;
        $cles = array_keys($colonnes);
        $dernier = 'A';
        foreach ($lignes as $l) {
            $col = 'A';
            foreach ($cles as $cle) {
                $this->ecrireCellule($ws, $col.$this->row, $l[$cle] ?? null, $formats[$cle] ?? null);
                $dernier = $col;
                $col++;
            }
            $this->row++;
        }
        $derniere = $this->row - 1;
        if ($derniere >= $premiere) {
            $ws->setCellValue('A'.$this->row, 'TOTAL');
            $lettreValeur = $this->lettreColonne(array_search('valeur', $cles, true) + 1);
            $lettreNb = $this->lettreColonne(array_search('nb', $cles, true) + 1);
            $ws->setCellValue($lettreValeur.$this->row, '=SUBTOTAL(9,'.$lettreValeur.$premiere.':'.$lettreValeur.$derniere.')');
            $ws->getStyle($lettreValeur.$this->row)->getNumberFormat()->setFormatCode('#,##0 "FCFA"');
            $lettreVnc = array_search('vnc', $cles, true);
            if ($lettreVnc !== false) {
                $lettreVnc = $this->lettreColonne($lettreVnc + 1);
                $ws->setCellValue($lettreVnc.$this->row, '=SUBTOTAL(9,'.$lettreVnc.$premiere.':'.$lettreVnc.$derniere.')');
                $ws->getStyle($lettreVnc.$this->row)->getNumberFormat()->setFormatCode('#,##0 "FCFA"');
            }
            $ws->setCellValue($lettreNb.$this->row, '=SUBTOTAL(9,'.$lettreNb.$premiere.':'.$lettreNb.$derniere.')');
            $ws->getStyle('A'.$this->row)->getFont()->setBold(true);
            $this->row++;
        }
        $ws->freezePane('A'.($premiere));
        $ws->setAutoFilter('A'.($premiere - 1).':'.$dernier.($this->row - 1));
        foreach (range(1, count($colonnes)) as $i) {
            $ws->getColumnDimension($this->lettreColonne($i))->setAutoSize(true);
        }
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /** Ecrit une cellule texte en neutralisant les caracteres de debut de formule. */
    private function setTexte(Worksheet $ws, string $coord, $v): void
    {
        $v = (string) $v;
        if (preg_match('/^[=+\-@]/', $v)) {
            $ws->setCellValueExplicit($coord, "'".$v, DataType::TYPE_STRING);
        } else {
            $ws->setCellValue($coord, $v);
        }
    }

    private function ecrireCellule(Worksheet $ws, string $coord, $v, ?string $fmt): void
    {
        if ($v === null || $v === '') {
            $ws->setCellValueExplicit($coord, 'n.p.', DataType::TYPE_STRING);
            return;
        }
        // Anti-injection de formules : un texte commencant par =, +, -, @ est force en chaine
        if (is_string($v) && preg_match('/^[=+\-@]/', $v)) {
            $ws->setCellValueExplicit($coord, "'".$v, DataType::TYPE_STRING);
            return;
        }
        if ($fmt === 'fcfa') {
            $ws->setCellValueExplicit($coord, is_numeric($v) ? (float) $v : 0, DataType::TYPE_NUMERIC);
            $ws->getStyle($coord)->getNumberFormat()->setFormatCode('#,##0 "FCFA"');
        } elseif ($fmt === 'date') {
            $ws->setCellValue($coord, \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($v));
            $ws->getStyle($coord)->getNumberFormat()->setFormatCode('DD/MM/YYYY');
        } elseif ($fmt === 'int') {
            $ws->setCellValueExplicit($coord, (int) $v, DataType::TYPE_NUMERIC);
            $ws->getStyle($coord)->getNumberFormat()->setFormatCode('#,##0');
        } else {
            $ws->setCellValue($coord, $v);
        }
    }

    private function entete(Worksheet $ws, int $row): void
    {
        $cols = $ws->getHighestColumn();
        // determine la plage : de A a derniere colonne utilisee sur cette ligne
        $cell = $ws->getCell('A'.$row);
        $range = 'A'.$row.':'.$this->lettreColonne($this->nbColonnesEntete($ws, $row)).$row;
        $ws->getStyle($range)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '006A4E']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);
    }

    private function nbColonnesEntete(Worksheet $ws, int $row): int
    {
        // colonnes remplies sur la ligne d'en-tete
        $n = 0;
        foreach ($ws->getRowIterator($row, $row) as $r) {
            foreach ($r->getCellIterator() as $cell) {
                if ($cell->getValue() !== null) $n++;
            }
        }
        return max(1, $n);
    }

    private function labelRow(Worksheet $ws, int $row): void
    {
        $ws->getStyle('A'.$row)->getFont()->setItalic(true)->getColor()->setRGB('6B7280');
    }

    private function lettreColonne(int $n): string
    {
        $s = '';
        while ($n > 0) {
            $n--;
            $s = chr(65 + ($n % 26)).$s;
            $n = intdiv($n, 26);
        }
        return $s;
    }

    public function output(): string
    {
        $this->book->setActiveSheetIndex(0);
        ob_start();
        (new Xlsx($this->book))->save('php://output');
        return ob_get_clean();
    }
}
