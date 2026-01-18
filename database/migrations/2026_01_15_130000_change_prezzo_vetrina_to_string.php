<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        Schema::table('articoli_vetrine', function (Blueprint $table) {
            // Change prezzo_vetrina to string to keep custom codes
            $table->string('prezzo_vetrina', 50)->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('articoli_vetrine', function (Blueprint $table) {
            $table->decimal('prezzo_vetrina', 10, 2)->nullable()->change();
        });
    }
};

