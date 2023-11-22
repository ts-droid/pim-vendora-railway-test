<?php

namespace App\Services;

use App\Models\PurchaseOrder;

class PurchaseOrderMotivator
{
    public function motivateQuantity(PurchaseOrder $purchaseOrder)
    {
        // Loop each line
        $purchaseOrder->lines->each(function($line) {
            $line->update([
                'ai_comment' => 'Add the AI comment here.',
            ]);
        });
    }
}
