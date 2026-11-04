<?php

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
        Schema::create('billets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seance_id')->constrained()->restrictOnDelete();
            $table->foreignId('place_id')->constrained()->restrictOnDelete();
            $table->foreignId('tarif_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('prix_centimes');
            $table->string('qr_token', 64)->unique()->comment('Contenu du QR code: aléatoire, sans donnée personnelle');
            $table->timestamp('utilise_at')->nullable()->comment('Heure du passage en salle');
            $table->timestamps();

            $table->unique(['seance_id', 'place_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billets');
    }
};
