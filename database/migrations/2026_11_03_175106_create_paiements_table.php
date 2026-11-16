<?php

use App\Enums\StatutPaiement;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('paiements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->string('prestataire', 30)->default('stripe');
            $table->string('reference_externe')->nullable()->unique()->comment('Identifiant du paiement chez le prestataire, aucune donnée de carte');
            $table->enum('statut', StatutPaiement::valeurs())->default(StatutPaiement::EnAttente->value);
            $table->unsignedInteger('montant_centimes');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('paiements');
    }
};
