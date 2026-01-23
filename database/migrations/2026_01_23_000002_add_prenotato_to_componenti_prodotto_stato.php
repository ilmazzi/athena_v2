<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        DB::statement(
            "ALTER TABLE componenti_prodotto MODIFY COLUMN stato ENUM('prelevato','utilizzato','restituito','scartato','prenotato') NOT NULL DEFAULT 'utilizzato'"
        );
    }

    public function down()
    {
        DB::statement(
            "ALTER TABLE componenti_prodotto MODIFY COLUMN stato ENUM('prelevato','utilizzato','restituito','scartato') NOT NULL DEFAULT 'utilizzato'"
        );
    }
};
