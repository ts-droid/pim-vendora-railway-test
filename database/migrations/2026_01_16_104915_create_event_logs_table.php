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
        Schema::create('event_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event_type');
            $table->string('display_name');
            $table->text('log')->nullable()->default(null);
            $table->string('change_key')->nullable()->default(null);
            $table->text('change_from')->nullable()->default(null);
            $table->text('change_to')->nullable()->default(null);
            $table->json('metadata')->nullable()->default(null);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_logs');
    }
};
