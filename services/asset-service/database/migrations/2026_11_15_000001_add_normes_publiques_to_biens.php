<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Normes comptables publiques (SYSCOHADA/OHADA, directive UEMOA 2012-03) :
 * - nature : incorporelle / corporelle / financiere (classes 21 / 22-24 / 25-27)
 * - compte_comptable : compte OHADA de l'actif immobilise (classe 2)
 * - date_mise_en_service : point de depart des dotations aux amortissements
 * - reference_decision : n° d'arrete, de marche ou de bon de commande (traçabilite)
 * - immobilisation : faux = bien de faible valeur (consommation, charge)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('biens', function (Blueprint $table) {
            $table->string('nature', 20)->default('corporelle')->after('type_specifique');
            $table->string('compte_comptable', 10)->nullable()->after('categorie_id');
            $table->date('date_mise_en_service')->nullable()->after('date_acquisition');
            $table->string('reference_decision')->nullable()->after('mode_acquisition');
            $table->boolean('immobilisation')->default(true)->after('valeur_acquisition');
            $table->index('compte_comptable');
        });
    }

    public function down(): void
    {
        Schema::table('biens', function (Blueprint $table) {
            $table->dropIndex('biens_compte_comptable_index');
            $table->dropColumn(['nature', 'compte_comptable', 'date_mise_en_service', 'reference_decision', 'immobilisation']);
        });
    }
};
