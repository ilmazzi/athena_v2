<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ddt', function (Blueprint $table) {
            $table->boolean('is_fatturato')
                ->default(false)
                ->after('numero_articoli');
            $table->timestamp('fatturato_at')
                ->nullable()
                ->after('is_fatturato');
            $table->foreignId('fatturato_da')
                ->nullable()
                ->after('fatturato_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('fattura_id')
                ->nullable()
                ->after('fatturato_da')
                ->constrained('fatture')
                ->nullOnDelete();
        });

        Schema::table('fatture', function (Blueprint $table) {
            $table->foreignId('ddt_origine_id')
                ->nullable()
                ->after('numero_articoli')
                ->constrained('ddt')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('fatture', function (Blueprint $table) {
            $table->dropForeign(['ddt_origine_id']);
            $table->dropColumn('ddt_origine_id');
        });

        Schema::table('ddt', function (Blueprint $table) {
            $table->dropForeign(['fatturato_da']);
            $table->dropForeign(['fattura_id']);
            $table->dropColumn([
                'is_fatturato',
                'fatturato_at',
                'fatturato_da',
                'fattura_id',
            ]);
        });
    }
};
