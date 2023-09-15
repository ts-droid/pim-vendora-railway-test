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
        Schema::create('prompts', function (Blueprint $table) {
            $table->id();
            $table->string('system_code')->nullable()->default(null);
            $table->string('group')->nullable()->default(null);
            $table->string('name')->nullable()->default(null);
            $table->longText('system')->nullable()->default(null);
            $table->longText('message')->nullable()->default(null);
            $table->json('inputs')->nullable()->default(null);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prompts');
    }
};
