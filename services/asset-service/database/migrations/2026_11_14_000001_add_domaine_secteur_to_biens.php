<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('biens', function (Blueprint $table) {
            $table->string('domaine')->nullable()->index()->after('type_specifique');
            $table->string('secteur')->nullable()->after('domaine');
        });
    }

    public function down(): void
    {
        Schema::table('biens', function (Blueprint $table) {
            $table->dropIndex(['domaine']);
            $table->dropColumn(['domaine', 'secteur']);
        });
    }
};
