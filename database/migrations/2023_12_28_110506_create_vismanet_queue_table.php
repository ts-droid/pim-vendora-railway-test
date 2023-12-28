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
        Schema::create('vismanet_queue', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('order_number');
            $table->string('external_order_number');
            $table->string('method');
            $table->string('endpoint');
            $table->text('body');
            $table->timestamp('process_at')->nullable()->default(null);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vismanet_queue');
    }
};
