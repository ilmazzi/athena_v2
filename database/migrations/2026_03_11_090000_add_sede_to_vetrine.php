<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('vetrine', function (Blueprint $table) {
            $table->foreignId('sede_id')
                ->nullable()
                ->constrained('sedi')
                ->nullOnDelete()
                ->after('ubicazione');
        });
    }

    public function down()
    {
        Schema::table('vetrine', function (Blueprint $table) {
            $table->dropForeign(['sede_id']);
            $table->dropColumn('sede_id');
        });
    }
};
