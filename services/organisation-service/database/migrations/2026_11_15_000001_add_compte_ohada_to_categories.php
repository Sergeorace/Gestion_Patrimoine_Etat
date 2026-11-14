<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Categories = plan d'amortissement par nature de bien.
 * Ajout du compte OHADA et de la nature comptable par defaut,
 * repris automatiquement sur les biens de la categorie.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('compte_comptable', 10)->nullable()->after('libelle');
            $table->string('nature', 20)->default('corporelle')->after('compte_comptable');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['compte_comptable', 'nature']);
        });
    }
};
