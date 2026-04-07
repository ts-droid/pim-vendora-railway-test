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
        Schema::table('emails', function (Blueprint $table) {
            $table->text('sender_name')->nullable()->default(null)->after('attachments');
            $table->text('sender_email')->nullable()->default(null)->after('sender_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('emails', function (Blueprint $table) {
            $table->dropColumn('sender_name');
            $table->dropColumn('sender_email');
        });
    }
};
