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
        Schema::table('openai_usage', function (Blueprint $table) {
            $table->decimal('cost', 15, 8)->after('completion_tokens');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('openai_usage', function (Blueprint $table) {
            $table->dropColumn('cost');
        });
    }
};
