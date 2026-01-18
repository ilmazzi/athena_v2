<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fatture_vendita')) {
            Schema::rename('fatture_vendita', 'proforme_deposito');
        }

        if (Schema::hasTable('proforme_deposito')) {
            Schema::table('proforme_deposito', function (Blueprint $table) {
                if (!Schema::hasColumn('proforme_deposito', 'stato')) {
                    $table->string('stato', 20)
                        ->default('da_fatturare')
                        ->after('note')
                        ->comment('Stato della proforma: da_fatturare, fatturata');
                }

                if (!Schema::hasColumn('proforme_deposito', 'fattura_pdf_path')) {
                    $table->string('fattura_pdf_path')
                        ->nullable()
                        ->after('stato')
                        ->comment('Percorso PDF fattura finale');
                }

                if (!Schema::hasColumn('proforme_deposito', 'fatturata_da')) {
                    $table->foreignId('fatturata_da')
                        ->nullable()
                        ->after('fattura_pdf_path')
                        ->constrained('users')
                        ->nullOnDelete()
                        ->comment('Utente che ha marcato come fatturata');
                }

                if (!Schema::hasColumn('proforme_deposito', 'fatturata_il')) {
                    $table->timestamp('fatturata_il')
                        ->nullable()
                        ->after('fatturata_da')
                        ->comment('Data di marcatura fatturata');
                }

                if (!Schema::hasColumn('proforme_deposito', 'fattura_note')) {
                    $table->text('fattura_note')
                        ->nullable()
                        ->after('fatturata_il')
                        ->comment('Note interne sulla fatturazione finale');
                }

                if (!Schema::hasColumn('proforme_deposito', 'fattura_numero')) {
                    $table->string('fattura_numero', 100)
                        ->nullable()
                        ->after('fattura_note')
                        ->comment('Numero documento fiscale definitivo');
                }

                if (!Schema::hasColumn('proforme_deposito', 'fattura_data')) {
                    $table->date('fattura_data')
                        ->nullable()
                        ->after('fattura_numero')
                        ->comment('Data documento fiscale definitivo');
                }

                if (!$this->indexExists('proforme_deposito', 'idx_proforme_deposito_stato')) {
                    $table->index('stato', 'idx_proforme_deposito_stato');
                }
            });
        }

        if (Schema::hasTable('movimenti_deposito') && Schema::hasColumn('movimenti_deposito', 'fattura_vendita_id')) {
            Schema::table('movimenti_deposito', function (Blueprint $table) {
                $table->dropForeign(['fattura_vendita_id']);
                $this->dropIndexIfExists($table, 'idx_mov_dep_fatt_vendita');
            });

            Schema::table('movimenti_deposito', function (Blueprint $table) {
                $table->unsignedBigInteger('proforma_id')
                    ->nullable()
                    ->after('fattura_id')
                    ->comment('Proforma deposito associata al movimento');
            });

            DB::statement('UPDATE movimenti_deposito SET proforma_id = fattura_vendita_id WHERE fattura_vendita_id IS NOT NULL');

            Schema::table('movimenti_deposito', function (Blueprint $table) {
                $table->dropColumn('fattura_vendita_id');
            });

            Schema::table('movimenti_deposito', function (Blueprint $table) {
                $table->foreign('proforma_id')
                    ->references('id')
                    ->on('proforme_deposito')
                    ->onDelete('set null');
                if (!$this->indexExists('movimenti_deposito', 'idx_mov_dep_proforma')) {
                    $table->index('proforma_id', 'idx_mov_dep_proforma');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('movimenti_deposito') && Schema::hasColumn('movimenti_deposito', 'proforma_id')) {
            Schema::table('movimenti_deposito', function (Blueprint $table) {
                $table->dropForeign(['proforma_id']);
                $this->dropIndexIfExists($table, 'idx_mov_dep_proforma');
            });

            Schema::table('movimenti_deposito', function (Blueprint $table) {
                $table->unsignedBigInteger('fattura_vendita_id')
                    ->nullable()
                    ->after('fattura_id');
            });

            DB::statement('UPDATE movimenti_deposito SET fattura_vendita_id = proforma_id WHERE proforma_id IS NOT NULL');

            Schema::table('movimenti_deposito', function (Blueprint $table) {
                $table->dropColumn('proforma_id');
            });

            if (Schema::hasTable('fatture_vendita')) {
                Schema::table('movimenti_deposito', function (Blueprint $table) {
                    $table->foreign('fattura_vendita_id')
                        ->references('id')
                        ->on('fatture_vendita')
                        ->onDelete('set null');
                    if (!$this->indexExists('movimenti_deposito', 'idx_mov_dep_fatt_vendita')) {
                        $table->index('fattura_vendita_id', 'idx_mov_dep_fatt_vendita');
                    }
                });
            }
        }

        if (Schema::hasTable('proforme_deposito')) {
            Schema::table('proforme_deposito', function (Blueprint $table) {
                if (Schema::hasColumn('proforme_deposito', 'fatturata_da')) {
                    $table->dropForeign(['fatturata_da']);
                }
                $this->dropIndexIfExists($table, 'idx_proforme_deposito_stato');
                if (Schema::hasColumn('proforme_deposito', 'stato')) {
                    $table->dropColumn('stato');
                }
                if (Schema::hasColumn('proforme_deposito', 'fattura_pdf_path')) {
                    $table->dropColumn('fattura_pdf_path');
                }
                if (Schema::hasColumn('proforme_deposito', 'fatturata_da')) {
                    $table->dropColumn('fatturata_da');
                }
                if (Schema::hasColumn('proforme_deposito', 'fatturata_il')) {
                    $table->dropColumn('fatturata_il');
                }
                if (Schema::hasColumn('proforme_deposito', 'fattura_note')) {
                    $table->dropColumn('fattura_note');
                }
                if (Schema::hasColumn('proforme_deposito', 'fattura_numero')) {
                    $table->dropColumn('fattura_numero');
                }
                if (Schema::hasColumn('proforme_deposito', 'fattura_data')) {
                    $table->dropColumn('fattura_data');
                }
            });
        }

        if (Schema::hasTable('proforme_deposito')) {
            Schema::rename('proforme_deposito', 'fatture_vendita');
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $database = DB::getDatabaseName();

        if (!$database) {
            return false;
        }

        $result = DB::selectOne(
            'SELECT COUNT(*) AS aggregate FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?',
            [$database, $table, $indexName]
        );

        return ($result->aggregate ?? 0) > 0;
    }

    private function dropIndexIfExists(Blueprint $table, string $indexName): void
    {
        if ($this->indexExists($table->getTable(), $indexName)) {
            $table->dropIndex($indexName);
        }
    }
};

