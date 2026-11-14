<?php

namespace Tests\Unit;

use App\Support\ValuationCalc;
use PHPUnit\Framework\TestCase;

class ValuationCalcTest extends TestCase
{
    public function test_vnc_lineaire_moitie_duree_vie(): void
    {
        // 120 000 FCFA, duree 120 mois, 60 mois ecoules -> amortissement 60 000, VNC 60 000
        $r = ValuationCalc::compute(120000, 120, '2020-01-15', '2025-01-15');
        $this->assertEqualsWithDelta(60000, $r['amortissement'], 1);
        $this->assertEqualsWithDelta(60000, $r['vnc'], 1);
    }

    public function test_vnc_plancher_zero(): void
    {
        // Bien entierement amorti (duree depassee)
        $r = ValuationCalc::compute(50000, 12, '2020-01-01', '2025-01-01');
        $this->assertEquals(50000, $r['amortissement']);
        $this->assertEquals(0, $r['vnc']);
    }

    public function test_vnc_bien_recement_acquis(): void
    {
        // 1 mois ecoule sur 120 -> amortissement quasi nul
        $r = ValuationCalc::compute(120000, 120, '2025-01-10', '2025-02-10');
        $this->assertEqualsWithDelta(1000, $r['amortissement'], 50);
        $this->assertGreaterThan(110000, $r['vnc']);
    }

    public function test_duree_vie_absente_donne_np(): void
    {
        $r = ValuationCalc::compute(80000, null, '2020-01-01', '2025-01-01');
        $this->assertEquals(80000, $r['acquisition']);
        $this->assertNull($r['amortissement']);
        $this->assertNull($r['vnc']);
    }

    public function test_valeur_absente_donne_null(): void
    {
        $r = ValuationCalc::compute(null, 120, '2020-01-01');
        $this->assertNull($r['acquisition']);
        $this->assertNull($r['vnc']);
    }

    public function test_totaux_et_groupes(): void
    {
        $lignes = [
            ['valeur' => 100, 'vnc' => 60, 'region' => 'A'],
            ['valeur' => 200, 'vnc' => 120, 'region' => 'A'],
            ['valeur' => 50, 'vnc' => 30, 'region' => 'B'],
        ];
        $t = ValuationCalc::totals($lignes);
        $this->assertSame(3, $t['nb']);
        $this->assertEquals(350, $t['valeur']);
        $this->assertEquals(210, $t['vnc']);

        $g = ValuationCalc::groupTotals($lignes, fn ($l) => $l['region']);
        $this->assertCount(2, $g);
        $this->assertEquals(300, $g['A']['valeur']);
        $this->assertCount(2, $g['A']['lignes']);
    }
}
