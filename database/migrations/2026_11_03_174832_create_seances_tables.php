<?php

use App\Enums\LangueSeance;
use App\Enums\StatutSeance;
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
        Schema::create('seances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('film_id')->constrained()->restrictOnDelete();
            $table->foreignId('salle_id')->constrained()->restrictOnDelete();
            $table->dateTime('debut');
            $table->enum('langue', LangueSeance::valeurs());
            $table->enum('statut', StatutSeance::valeurs())->default(StatutSeance::Brouillon->value);
            $table->timestamps();

            $table->index(['debut', 'statut']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seances');
    }
};
