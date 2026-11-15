<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('utilisateur_id')->nullable()->index();
            $table->string('service_source')->index();
            $table->string('action')->index();
            $table->string('entite')->index();
            $table->uuid('entite_id')->nullable();
            $table->jsonb('avant')->nullable();
            $table->jsonb('apres')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('cree_le')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
