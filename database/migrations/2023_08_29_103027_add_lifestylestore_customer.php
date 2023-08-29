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
        $customer = \App\Models\Customer::create([
            'external_id' => '0',
            'customer_number' => 'vendora',
            'vat_number' => 'SE556843545601',
            'org_number' => '5568435456',
            'name' => 'Vendora Nordic Aktiebolag',
            'country' => 'SE',
            'shop_url' => 'https://www.lifestylestore.se/',
            'shop_search_url' => 'https://www.lifestylestore.se/search/?q={query}',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \App\Models\Customer::where('customer_number', 'vendora')->delete();
    }
};
