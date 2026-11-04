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
        Schema::create('films', function (Blueprint $table) {
            $table->id();
            $table->string('titre');
            $table->text('synopsis');
            $table->unsignedSmallInteger('duree_minutes');
            $table->string('genre', 50);
            $table->string('classification', 20)->comment('Tous publics, -12, -16, -18');
            $table->string('affiche_url')->nullable();
            $table->string('bande_annonce_url')->nullable();
            $table->timestamp('archive_at')->nullable()->comment('Film retiré du site public, conservé pour les statistiques');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('films');
    }
};
