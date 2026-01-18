<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('giacenze_sedi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('articolo_id')->constrained('articoli')->cascadeOnDelete();
            $table->foreignId('sede_id')->constrained('sedi')->cascadeOnDelete();
            $table->integer('quantita')->default(0);
            $table->integer('quantita_residua')->default(0);
            $table->timestamps();
            $table->unique(['articolo_id', 'sede_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('giacenze_sedi');
    }
};





