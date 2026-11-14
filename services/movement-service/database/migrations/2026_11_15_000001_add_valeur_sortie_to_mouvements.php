<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ordre de sortie definitif (directive UEMOA 2012-03) :
 * la valeur de cession est consignee sur le mouvement de sortie
 * pour l'etat des immobilisations et la comptabilisation du produit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mouvements', function (Blueprint $table) {
            $table->decimal('valeur_sortie', 15, 2)->nullable()->after('motif_sortie_id');
        });
    }

    public function down(): void
    {
        Schema::table('mouvements', function (Blueprint $table) {
            $table->dropColumn('valeur_sortie');
        });
    }
};
