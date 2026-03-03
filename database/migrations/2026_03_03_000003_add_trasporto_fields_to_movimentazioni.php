<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('movimentazioni', function (Blueprint $table) {
            $table->string('trasporto_mezzo', 100)->nullable()->after('causale');
            $table->string('aspetto_beni', 100)->nullable()->after('trasporto_mezzo');
            $table->string('colli', 50)->nullable()->after('aspetto_beni');
            $table->string('vettore', 100)->nullable()->after('colli');
        });
    }

    public function down()
    {
        Schema::table('movimentazioni', function (Blueprint $table) {
            $table->dropColumn(['trasporto_mezzo', 'aspetto_beni', 'colli', 'vettore']);
        });
    }
};
