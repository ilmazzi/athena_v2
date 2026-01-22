<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ddt', function (Blueprint $table) {
            if (!Schema::hasColumn('ddt', 'allegato_path')) {
                $table->string('allegato_path')->nullable()->after('data_carico');
            }
        });

        Schema::table('fatture', function (Blueprint $table) {
            if (!Schema::hasColumn('fatture', 'allegato_path')) {
                $table->string('allegato_path')->nullable()->after('data_carico');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ddt', function (Blueprint $table) {
            if (Schema::hasColumn('ddt', 'allegato_path')) {
                $table->dropColumn('allegato_path');
            }
        });

        Schema::table('fatture', function (Blueprint $table) {
            if (Schema::hasColumn('fatture', 'allegato_path')) {
                $table->dropColumn('allegato_path');
            }
        });
    }
};
