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
        Schema::create('places', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salle_id')->constrained()->cascadeOnDelete();
            $table->string('rangee', 3);
            $table->unsignedSmallInteger('numero');
            $table->boolean('pmr')->default(false)->comment('Place réservée aux personnes à mobilité réduite');
            $table->timestamps();

            $table->unique(['salle_id', 'rangee', 'numero']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('places');
    }
};
