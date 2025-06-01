<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->text('announcement')->nullable()->default(null);
            $table->date('announcement_start_date')->nullable()->default(null);
            $table->date('announcement_end_date')->nullable()->default(null);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn('announcement');
            $table->dropColumn('announcement_start_date');
            $table->dropColumn('announcement_end_date');
        });
    }
};
