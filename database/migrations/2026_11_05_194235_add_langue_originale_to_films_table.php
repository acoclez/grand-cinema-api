<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Indication de la langue originale du film.
     * La version projetée (VO ou VF) reste une propriété de la séance.
     */
    public function up(): void
    {
        Schema::table('films', function (Blueprint $table) {
            // Nullable pour les films déjà en base (la validation de l'API la rend obligatoire).
            $table->string('langue_originale', 50)->nullable()->after('classification');
        });
    }

    public function down(): void
    {
        Schema::table('films', function (Blueprint $table) {
            $table->dropColumn('langue_originale');
        });
    }
};
