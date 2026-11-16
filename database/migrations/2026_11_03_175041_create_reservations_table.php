<?php

use App\Enums\CanalVente;
use App\Enums\StatutReservation;
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
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 12)->unique()->comment('Numéro lisible communiqué au spectateur');
            $table->foreignId('user_id')->nullable()->comment('Null pour une vente au guichet ou un compte supprimé')->constrained()->nullOnDelete();
            $table->foreignId('seance_id')->constrained()->restrictOnDelete();
            $table->enum('statut', StatutReservation::valeurs())->default(StatutReservation::EnAttente->value);
            $table->enum('canal', CanalVente::valeurs());
            $table->unsignedInteger('montant_centimes')->default(0);
            $table->dateTime('expire_at')->nullable()->comment('Fin du blocage des places tant que la réservation est en attente');
            $table->timestamps();

            $table->index(['seance_id', 'statut']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
