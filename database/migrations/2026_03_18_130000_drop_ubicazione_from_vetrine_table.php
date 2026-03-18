<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('vetrine', 'ubicazione')) {
            return;
        }

        Schema::table('vetrine', function (Blueprint $table) {
            // Best-effort: gli indici potrebbero non esistere in tutti gli ambienti.
            try {
                $table->dropUnique('idx_vetrine_ubic_codice');
            } catch (\Throwable $e) {
                // noop
            }

            try {
                $table->dropIndex('idx_vetrine_ubicazione');
            } catch (\Throwable $e) {
                // noop
            }

            try {
                $table->dropIndex('idx_vetrine_ubic_attiva');
            } catch (\Throwable $e) {
                // noop
            }

            $table->dropColumn('ubicazione');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('vetrine', 'ubicazione')) {
            return;
        }

        Schema::table('vetrine', function (Blueprint $table) {
            $table->enum('ubicazione', ['mazzini', 'monastero', 'roma', 'altro'])
                ->default('altro')
                ->after('nome');

            $table->unique(['ubicazione', 'codice'], 'idx_vetrine_ubic_codice');
            $table->index('ubicazione', 'idx_vetrine_ubicazione');
            $table->index(['ubicazione', 'attiva'], 'idx_vetrine_ubic_attiva');
        });
    }
};
