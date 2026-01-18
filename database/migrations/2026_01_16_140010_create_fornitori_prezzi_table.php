<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fornitori_prezzi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fornitore_id')
                ->constrained('fornitori')
                ->cascadeOnDelete();

            $table->enum('match_type', [
                'referenza',
                'modello',
                'seriale',
                'ean',
                'codice',
                'descrizione',
            ])->comment('Campo di matching per applicare il prezzo');

            $table->string('match_value', 255)->comment('Valore del campo di matching');
            $table->decimal('prezzo', 10, 2)->comment('Prezzo concordato con il fornitore');
            $table->text('note')->nullable();

            $table->timestamps();

            $table->unique(['fornitore_id', 'match_type', 'match_value'], 'uniq_fornitori_prezzi_match');
            $table->index('match_type', 'idx_fornitori_prezzi_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fornitori_prezzi');
    }
};
