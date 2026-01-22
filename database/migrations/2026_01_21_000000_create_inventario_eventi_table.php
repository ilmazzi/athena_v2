<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventario_eventi', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sessione_id')->index();
            $table->unsignedBigInteger('articolo_id')->nullable()->index();
            $table->unsignedBigInteger('sede_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('tipo', 64)->index();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->foreign('sessione_id')->references('id')->on('inventario_sessioni')->onDelete('cascade');
            $table->foreign('articolo_id')->references('id')->on('articoli')->onDelete('set null');
            $table->foreign('sede_id')->references('id')->on('sedi')->onDelete('set null');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventario_eventi');
    }
};
