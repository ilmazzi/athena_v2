<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE prodotti_finiti MODIFY stato ENUM('in_lavorazione','completato','venduto','scartato','annullato') DEFAULT 'in_lavorazione'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE prodotti_finiti MODIFY stato ENUM('in_lavorazione','completato','venduto','scartato') DEFAULT 'in_lavorazione'");
    }
};
